<?php
/**
 * Archivo: bases/funciones.php
 * Descripción: Funciones reutilizables del sistema.
 * Nota: Compatible con login que valida: WHERE email=? AND password=PASSWORD(?)
 */

/**
 * Genera un password temporal aleatorio.
 */
function generar_password_temporal(int $len = 10): string
{
    $alf = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#%*';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $alf[random_int(0, strlen($alf)-1)];
    }
    return $out;
}

/**
 * Crea o actualiza un usuario externo tomando como username el RFC.
 *
 * Tabla usuarios (según describe):
 *  - id int(11) PK AI
 *  - username varchar(50) UNIQUE  (usaremos el RFC)
 *  - nombre varchar(100) NOT NULL
 *  - email varchar(100) UNIQUE NOT NULL
 *  - password varchar(255) NOT NULL
 *  - externo char(1) NOT NULL DEFAULT 'N'
 *
 * @param mysqli     $conn   Conexión activa
 * @param string     $rfc    RFC del proveedor (será el username)
 * @param string     $nombre Nombre completo/razón social
 * @param string     $email  Correo de contacto/login
 * @param string|nil $pwdPlano  Password en claro (opcional; si null se genera uno)
 * @param bool       $actualizarPasswordSiExiste  Si true, al existir usuario se reemplaza el password
 *
 * @return array [ok=>bool, msg=>string, usuario_id=>int|null, username=>string, password_temporal=>string|null]
 */
function crear_usuario(
    mysqli $conn,
    string $rfc,
    string $nombre,
    string $email,
    ?string $pwdPlano = null,
    bool $actualizarPasswordSiExiste = false
): array {
    // Normalizar
    $username = strtoupper(trim($rfc));
    $nombre   = trim($nombre);
    $email    = trim($email);

    // Validaciones mínimas
    if ($username === '' || !preg_match("/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{2,3}$/", $username)) {
        return ['ok'=>false, 'msg'=>'RFC inválido para username', 'usuario_id'=>null, 'username'=>$username, 'password_temporal'=>null];
    }
    if ($nombre === '') {
        return ['ok'=>false, 'msg'=>'El nombre no puede estar vacío', 'usuario_id'=>null, 'username'=>$username, 'password_temporal'=>null];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok'=>false, 'msg'=>'Email inválido', 'usuario_id'=>null, 'username'=>$username, 'password_temporal'=>null];
    }

    // Generar password temporal si no se proporcionó
    $pwdGenerado = false;
    if ($pwdPlano === null) {
        $pwdPlano = generar_password_temporal(10);
        $pwdGenerado = true;
    }

    // Buscar por username
    $sqlU = "SELECT id FROM usuarios WHERE username = ? LIMIT 1";
    $stmt = $conn->prepare($sqlU);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $resU = $stmt->get_result();
    $idByUsername = ($resU && $resU->num_rows === 1) ? (int)$resU->fetch_assoc()['id'] : 0;
    $stmt->close();

    // Buscar por email
    $sqlE = "SELECT id FROM usuarios WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($sqlE);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resE = $stmt->get_result();
    $idByEmail = ($resE && $resE->num_rows === 1) ? (int)$resE->fetch_assoc()['id'] : 0;
    $stmt->close();

    // Conflicto: username y email pertenecen a usuarios distintos
    if ($idByUsername > 0 && $idByEmail > 0 && $idByUsername !== $idByEmail) {
        return ['ok'=>false, 'msg'=>'Conflicto: username (RFC) y email pertenecen a usuarios distintos', 'usuario_id'=>null, 'username'=>$username, 'password_temporal'=>null];
    }

    // Determinar si es update o insert
    $userId = $idByUsername ?: $idByEmail;

    if ($userId > 0) {
        // UPDATE → asegurar externo='S'. Si se pide, actualiza el password.
        if ($actualizarPasswordSiExiste) {
            $sqlUpd = "UPDATE usuarios
                       SET nombre = ?, email = ?, externo = 'S', password = PASSWORD(?)
                       WHERE id = ?";
            $stmt = $conn->prepare($sqlUpd);
            $stmt->bind_param("sssi", $nombre, $email, $pwdPlano, $userId);
        } else {
            $sqlUpd = "UPDATE usuarios
                       SET nombre = ?, email = ?, externo = 'S'
                       WHERE id = ?";
            $stmt = $conn->prepare($sqlUpd);
            $stmt->bind_param("ssi", $nombre, $email, $userId);
        }

        if (!$stmt->execute()) {
            $msg = 'No se pudo actualizar el usuario existente: '.$stmt->error;
            $stmt->close();
            return ['ok'=>false, 'msg'=>$msg, 'usuario_id'=>null, 'username'=>$username, 'password_temporal'=>null];
        }
        $stmt->close();

        return [
            'ok' => true,
            'msg' => 'Usuario externo actualizado',
            'usuario_id' => $userId,
            'username' => $username,
            // Solo devolvemos password si realmente lo actualizamos o si fue generado ad hoc y se pidió update
            'password_temporal' => $actualizarPasswordSiExiste ? $pwdPlano : null
        ];
    }

    // INSERT → nuevo usuario externo con PASSWORD() en SQL
    $sqlIns = "INSERT INTO usuarios (username, nombre, email, password, externo)
               VALUES (?, ?, ?, PASSWORD(?), 'S')";
    $stmt = $conn->prepare($sqlIns);
    $stmt->bind_param("ssss", $username, $nombre, $email, $pwdPlano);

    if (!$stmt->execute()) {
        $msg = 'Error al crear usuario: '.$stmt->error;
        $stmt->close();
        return ['ok'=>false, 'msg'=>$msg, 'usuario_id'=>null, 'username'=>$username, 'password_temporal'=>null];
    }

    $newId = $stmt->insert_id;
    $stmt->close();

    return [
        'ok' => true,
        'msg' => 'Usuario externo creado',
        'usuario_id' => $newId,
        'username' => $username,
        'password_temporal' => $pwdGenerado ? $pwdPlano : $pwdPlano // lo retornamos para notificar al proveedor por el canal que definas
    ];
}

/**
 * -------- CFDI / CxP: cargar, guardar y leer XML --------
 * - Guarda el XML en cxp/documentos/<RFC_EMISOR>/
 * - Lee atributos clave del CFDI (3.3/4.0) y los regresa en un array
 */

/* ========= BASE WEB de tu app (ajústala si cambias carpeta) ========= */
if (!isset($GLOBALS['APP_WEB_BASE'])) {
    // Tu app corre en http://localhost/template
    $GLOBALS['APP_WEB_BASE'] = '/template'; // sin slash final
}

/* ========= Paths CxP ========= */

/** Directorio ABSOLUTO donde se guardan documentos */
function cxp_base_dir(): string {
    // .../template/cxp/documentos/
    return rtrim(realpath(__DIR__ . '/..'), '/') . '/cxp/documentos/';
}

/** Prefijo WEB-relativo (lo que va a BD y a los links) */
function cxp_public_rel_base(): string {
    return rtrim($GLOBALS['APP_WEB_BASE'] ?? '/template', '/') . '/cxp/documentos/';
}

/** Parsea un CFDI desde un archivo */
function cfdi_parse_from_file(string $path): array {
    if (!is_readable($path)) {
        return ['ok'=>false, 'msg'=>"No se puede leer: $path", 'data'=>null];
    }
    $xmlString = file_get_contents($path);
    return cfdi_parse_from_string($xmlString);
}

/* ========= Guardado XML/PDF (deben devolver ruta_abs y ruta_rel) ========= */

function cfdi_cargar_y_guardar(array $file): array {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['ok'=>false, 'msg'=>'No se recibió archivo válido', 'ruta_abs'=>null, 'ruta_rel'=>null, 'data'=>null];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'xml') return ['ok'=>false, 'msg'=>'El archivo debe ser .xml', 'ruta_abs'=>null, 'ruta_rel'=>null, 'data'=>null];

    $raw = file_get_contents($file['tmp_name']);
    $parsed = cfdi_parse_from_string($raw);
    if (!$parsed['ok']) return ['ok'=>false, 'msg'=>'XML CFDI inválido', 'ruta_abs'=>null, 'ruta_rel'=>null, 'data'=>null];

    $d = $parsed['data'];
    $dirAbs = cxp_dir_por_rfc($d['emisor_rfc']);
    $fileName = (!empty($d['uuid']) ? strtoupper($d['uuid']) : 'CFDI_'.date('Ymd_His')).'.xml';

    $destAbs = $dirAbs . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
        if (file_put_contents($destAbs, $raw) === false) return ['ok'=>false, 'msg'=>'No se pudo guardar el XML', 'ruta_abs'=>null, 'ruta_rel'=>null, 'data'=>null];
    }
    $destRel = cxp_public_rel_path($d['emisor_rfc'], $fileName); // ✅ para BD

    return ['ok'=>true, 'msg'=>'XML guardado', 'ruta_abs'=>$destAbs, 'ruta_rel'=>$destRel, 'data'=>$d];
}

/**
 * Regresa info mínima del usuario actual (por nombre de sesión).
 * Recomendación: guarda también username y externo en la sesión al loguear.
 */
function obtener_usuario_min(mysqli $conn, string $nombreSesion): ?array {
    $sql = "SELECT id, username, externo, email, nombre FROM usuarios WHERE nombre = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $nombreSesion);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
        return $res->fetch_assoc();
    }
    return null;
}

/**
 * Valida si un usuario (por nombre en sesión) puede subir un XML con el RFC emisor dado.
 * Regla:
 *  - Si no es externo (externo != 'S') => permitido.
 *  - Si es externo (externo == 'S') => el RFC emisor debe ser su username (RFC).
 *  - Excepción: si $_SESSION['override_externo'] === 'S', se permite.
 */
function puede_subir_emisor(mysqli $conn, string $nombreSesion, string $emisorRFC): array {
    $emisorRFC = strtoupper(trim($emisorRFC));

    // Excepción por sesión (habilitada por admin)
    if (isset($_SESSION['override_externo']) && $_SESSION['override_externo'] === 'S') {
        return ['ok' => true, 'msg' => 'Excepción de externo aplicada'];
    }

    $u = obtener_usuario_min($conn, $nombreSesion);
    if (!$u) {
        return ['ok' => false, 'msg' => 'Usuario de sesión no encontrado'];
    }

    // Si no es externo, puede subir cualquier emisor
    if (strtoupper($u['externo']) !== 'S') {
        return ['ok' => true, 'msg' => 'Usuario interno: permitido'];
    }

    // Es externo: el emisor debe ser su RFC (username)
    $userRFC = strtoupper(trim($u['username'] ?? ''));
    if ($userRFC === '' || $emisorRFC === '') {
        return ['ok' => false, 'msg' => 'RFC insuficiente para validar'];
    }

    if ($userRFC !== $emisorRFC) {
        return ['ok' => false, 'msg' => 'El RFC emisor del XML no corresponde a su usuario externo'];
    }

    return ['ok' => true, 'msg' => 'Validación de emisor correcta'];
}

function cfdi_parse_from_string(string $xmlString): array {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlString);
    if ($xml === false) {
        return ['ok'=>false, 'msg'=>'XML no válido', 'data'=>null];
    }

    // Namespaces
    $ns = $xml->getNamespaces(true);
    $xml->registerXPathNamespace('cfdi', $ns['cfdi'] ?? 'http://www.sat.gob.mx/cfd/3');
    $xml->registerXPathNamespace('tfd',  $ns['tfd']  ?? 'http://www.sat.gob.mx/TimbreFiscalDigital');

    // Comprobante
    $compAttr = $xml->attributes();
    $version  = (string)($compAttr['Version'] ?? $compAttr['version'] ?? '');
    $serie    = (string)($compAttr['Serie']   ?? $compAttr['serie']   ?? '');
    $folio    = (string)($compAttr['Folio']   ?? $compAttr['folio']   ?? '');
    $fecha    = (string)($compAttr['Fecha']   ?? $compAttr['fecha']   ?? '');
    $subtotal = (string)($compAttr['SubTotal']?? $compAttr['subTotal']?? '');
    $total    = (string)($compAttr['Total']   ?? $compAttr['total']   ?? '');
    $moneda   = (string)($compAttr['Moneda']  ?? $compAttr['moneda']  ?? '');
    $formaPago= (string)($compAttr['FormaPago']??$compAttr['formaDePago']??'');
    $metodoPago=(string)($compAttr['MetodoPago']??$compAttr['metodoDePago']??'');
    $lugarExp = (string)($compAttr['LugarExpedicion']??$compAttr['LugarExpedición']??'');

    // Emisor
    $emisor = $xml->xpath('//cfdi:Emisor');
    $emAttr = $emisor ? $emisor[0]->attributes() : null;
    $emRfc  = $emAttr ? (string)($emAttr['Rfc'] ?? $emAttr['rfc'] ?? '') : '';
    $emNom  = $emAttr ? (string)($emAttr['Nombre'] ?? $emAttr['nombre'] ?? '') : '';

    // Receptor
    $receptor = $xml->xpath('//cfdi:Receptor');
    $reAttr   = $receptor ? $receptor[0]->attributes() : null;
    $reRfc    = $reAttr ? (string)($reAttr['Rfc'] ?? $reAttr['rfc'] ?? '') : '';
    $reNom    = $reAttr ? (string)($reAttr['Nombre'] ?? $reAttr['nombre'] ?? '') : '';
    $usoCfdi  = $reAttr ? (string)($reAttr['UsoCFDI'] ?? $reAttr['UsoCfdi'] ?? $reAttr['usoCFDI'] ?? '') : '';

    // Timbre (UUID)
    $tfd   = $xml->xpath('//tfd:TimbreFiscalDigital');
    $tfAtt = $tfd ? $tfd[0]->attributes() : null;
    $uuid  = $tfAtt ? (string)($tfAtt['UUID'] ?? $tfAtt['Uuid'] ?? '') : '';
    $fechaTimbrado = $tfAtt ? (string)($tfAtt['FechaTimbrado'] ?? '') : '';

    // ---------- IMPUESTOS ----------
    // Acumuladores por código SAT: 001=ISR, 002=IVA, 003=IEPS
    $tras = ['001'=>0.0,'002'=>0.0,'003'=>0.0,'OTROS'=>0.0];
    $ret  = ['001'=>0.0,'002'=>0.0,'003'=>0.0,'OTROS'=>0.0];

    $asFloat = function($v) {
        if ($v === null || $v === '') return 0.0;
        return (float)str_replace([','], [''], (string)$v);
    };

    // Totales en Comprobante/Impuestos
    $impNode = $xml->xpath('//cfdi:Comprobante/cfdi:Impuestos');
    $totTrasComprob = 0.0; $totRetComprob = 0.0;
    if ($impNode) {
        $iAttr = $impNode[0]->attributes();
        $totTrasComprob = $asFloat($iAttr['TotalImpuestosTrasladados'] ?? $iAttr['totalImpuestosTrasladados'] ?? 0);
        $totRetComprob  = $asFloat($iAttr['TotalImpuestosRetenidos']  ?? $iAttr['totalImpuestosRetenidos']  ?? 0);

        // Desglose en Comprobante
        $trs = $xml->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado');
        foreach ($trs as $n) {
            $a = $n->attributes();
            $imp = (string)($a['Impuesto'] ?? $a['impuesto'] ?? '');
            $imp = $imp ?: 'OTROS';
            $tras[$imp] = ($tras[$imp] ?? 0.0) + $asFloat($a['Importe'] ?? $a['importe'] ?? 0);
        }
        $rts = $xml->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Retenciones/cfdi:Retencion');
        foreach ($rts as $n) {
            $a = $n->attributes();
            $imp = (string)($a['Impuesto'] ?? $a['impuesto'] ?? '');
            $imp = $imp ?: 'OTROS';
            $ret[$imp] = ($ret[$imp] ?? 0.0) + $asFloat($a['Importe'] ?? $a['importe'] ?? 0);
        }
    }

    // Si no hay desglose en Comprobante, sumar por Conceptos
    if ($totTrasComprob == 0 && $totRetComprob == 0 && $tras['001']==0 && $tras['002']==0 && $tras['003']==0 && $ret['001']==0 && $ret['002']==0 && $ret['003']==0) {
        $trsC = $xml->xpath('//cfdi:Conceptos/cfdi:Concepto/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado');
        foreach ($trsC as $n) {
            $a = $n->attributes();
            $imp = (string)($a['Impuesto'] ?? $a['impuesto'] ?? '');
            $imp = $imp ?: 'OTROS';
            $tras[$imp] = ($tras[$imp] ?? 0.0) + $asFloat($a['Importe'] ?? $a['importe'] ?? 0);
        }
        $rtsC = $xml->xpath('//cfdi:Conceptos/cfdi:Concepto/cfdi:Impuestos/cfdi:Retenciones/cfdi:Retencion');
        foreach ($rtsC as $n) {
            $a = $n->attributes();
            $imp = (string)($a['Impuesto'] ?? $a['impuesto'] ?? '');
            $imp = $imp ?: 'OTROS';
            $ret[$imp] = ($ret[$imp] ?? 0.0) + $asFloat($a['Importe'] ?? $a['importe'] ?? 0);
        }
        $totTrasComprob = array_sum($tras);
        $totRetComprob  = array_sum($ret);
    }

    // Map cómodos
    $iva_trasladado  = $tras['002'] ?? 0.0;
    $ieps_trasladado = $tras['003'] ?? 0.0;
    $isr_retenido    = $ret['001']  ?? 0.0;
    $iva_retenido    = $ret['002']  ?? 0.0;

    return [
        'ok'  => true,
        'msg' => 'OK',
        'data'=> [
            'version' => $version,
            'serie'   => $serie,
            'folio'   => $folio,
            'fecha'   => $fecha,
            'subtotal'=> $subtotal,
            'total'   => $total,
            'moneda'  => $moneda,
            'forma_pago'  => $formaPago,
            'metodo_pago' => $metodoPago,
            'lugar_expedicion' => $lugarExp,

            'emisor_rfc'     => strtoupper($emRfc),
            'emisor_nombre'  => $emNom,
            'receptor_rfc'   => strtoupper($reRfc),
            'receptor_nombre'=> $reNom,
            'uso_cfdi'       => $usoCfdi,
            'uuid'           => strtoupper($uuid),
            'fecha_timbrado' => $fechaTimbrado,

            // Impuestos
            'total_impuestos_trasladados' => $totTrasComprob,
            'total_impuestos_retenidos'   => $totRetComprob,
            'iva_trasladado'  => $iva_trasladado,
            'ieps_trasladado' => $ieps_trasladado,
            'isr_retenido'    => $isr_retenido,
            'iva_retenido'    => $iva_retenido
        ]
    ];
}

/* ========= Utilidades CxP ========= */

// Validar RFC (personas físicas/morales)
function validar_rfc_mex(string $rfc): bool {
    $rfc = strtoupper(trim($rfc));
    return (bool)preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{2,3}$/', $rfc);
}

// Validar UUID (SAT timbre)
function validar_uuid(string $uuid): bool {
    $uuid = strtoupper(trim($uuid));
    return (bool)preg_match('/^[0-9A-F]{8}\-[0-9A-F]{4}\-[0-9A-F]{4}\-[0-9A-F]{4}\-[0-9A-F]{12}$/', $uuid);
}

// Guardar PDF de factura en carpeta por RFC, con nombre UUID.pdf (o timestamp si no hay UUID)
function guardar_pdf_factura(array $file, string $emisorRFC, ?string $uuid = null, int $maxMB = 10): array {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['ok'=>false, 'msg'=>'No se recibió PDF válido', 'ruta_abs'=>null, 'ruta_rel'=>null];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') return ['ok'=>false, 'msg'=>'El archivo debe ser PDF', 'ruta_abs'=>null, 'ruta_rel'=>null];
    if ($file['size'] > $maxMB*1024*1024) return ['ok'=>false, 'msg'=>"El PDF excede {$maxMB}MB", 'ruta_abs'=>null, 'ruta_rel'=>null];

    $dirAbs = cxp_dir_por_rfc($emisorRFC);
    $fileName = ($uuid ? strtoupper($uuid) : 'CFDI_'.date('Ymd_His')).'.pdf';

    $destAbs = $dirAbs . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
        $raw = file_get_contents($file['tmp_name']);
        if (file_put_contents($destAbs, $raw) === false) return ['ok'=>false, 'msg'=>'No se pudo guardar el PDF', 'ruta_abs'=>null, 'ruta_rel'=>null];
    }
    $destRel = cxp_public_rel_path($emisorRFC, $fileName); // ✅ para BD

    return ['ok'=>true, 'msg'=>'PDF guardado', 'ruta_abs'=>$destAbs, 'ruta_rel'=>$destRel];
}


/** Borrado seguro (recibe ABS) */
function borrar_archivo_cxp(string $absPath): bool {
    $base = realpath(cxp_base_dir());
    $real = realpath($absPath);
    if ($real === false || $base === false) return false;
    if (strpos($real, $base) !== 0) return false;
    return is_file($real) ? @unlink($real) : false;
}

/** Crea/asegura carpeta por RFC (ABS) */
function cxp_dir_por_rfc(string $rfc): string {
    $rfc = strtoupper(trim($rfc));
    $base = cxp_base_dir();
    if (!is_dir($base)) mkdir($base, 0777, true);
    $dest = $base . $rfc . '/';
    if (!is_dir($dest)) mkdir($dest, 0777, true);
    return $dest; // ABS
}

/** Construye ruta WEB-relativa para BD: /template/cxp/documentos/RFC/archivo */
function cxp_public_rel_path(string $rfc, string $filename): string {
    return rtrim(cxp_public_rel_base(), '/') . '/' . strtoupper(trim($rfc)) . '/' . $filename;
}

/** Convierte ruta REL (BD) → ABS (disco) */
function cxp_abs_from_rel(string $rel): ?string {
    $prefix = cxp_public_rel_base(); // /template/cxp/documentos/
    if (strpos($rel, $prefix) !== 0) return null;
    $tail = substr($rel, strlen($prefix)); // RFC/archivo
    return cxp_base_dir() . $tail;         // ABS
}

// -----------------------------------------------------------------------------
// Actualiza el estatus de la OC según detalle y facturas asociadas.
// Reglas:
// - Si no hay renglones en la OC  -> BORRADOR
// - Si hay renglones y sum(facturas)=0              -> ABIERTA
// - Si 0 < sum(facturas) < total_OC (tolerancia)    -> PARCIAL
// - Si sum(facturas) >= total_OC - tolerancia       -> CERRADA
// -----------------------------------------------------------------------------
function actualizar_estatus_oc(mysqli $conn, int $oc_id, float $tolerancia = 0.01): void {
    // ¿Tiene renglones?
    $tiene = 0;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM ordenes_compra_detalle WHERE oc_id=?");
    $stmt->bind_param('i', $oc_id);
    $stmt->execute();
    $stmt->bind_result($tiene);
    $stmt->fetch();
    $stmt->close();

    if ($tiene == 0) {
        $estado = 'BORRADOR';
        $stmt = $conn->prepare("UPDATE ordenes_compra SET estatus=? WHERE id=?");
        $stmt->bind_param('si', $estado, $oc_id);
        $stmt->execute();
        $stmt->close();
        return;
    }

    // Total OC
    $totalOc = 0.0;
    $stmt = $conn->prepare("SELECT total FROM ordenes_compra WHERE id=?");
    $stmt->bind_param('i', $oc_id);
    $stmt->execute();
    $stmt->bind_result($totalOc);
    $stmt->fetch();
    $stmt->close();

    // Suma facturas asociadas
    $sumFact = 0.0;
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total),0) FROM facturas_cxp WHERE orden_compra_id=?");
    $stmt->bind_param('i', $oc_id);
    $stmt->execute();
    $stmt->bind_result($sumFact);
    $stmt->fetch();
    $stmt->close();

    if ($sumFact <= $tolerancia) {
        $estado = 'ABIERTA';
    } elseif ($sumFact >= ($totalOc - $tolerancia)) {
        $estado = 'CERRADA';
    } else {
        $estado = 'PARCIAL';
    }

    $stmt = $conn->prepare("UPDATE ordenes_compra SET estatus=? WHERE id=?");
    $stmt->bind_param('si', $estado, $oc_id);
    $stmt->execute();
    $stmt->close();
}

function filtroExterno(string $campo = 'rfc', string $prefix = 'where'): array {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $esExterno = ($_SESSION['user_externo'] ?? 'N') === 'S';
    $rfc       = $_SESSION['user_rfc'] ?? '';
    if ($esExterno && $rfc !== '') {
        $whereExterno = " {$prefix} {$campo} = '" . addslashes($rfc) . "'";
        $privilegio   = 'N';
    } else {
        $whereExterno = '';
        $privilegio   = 'S';
    }
    return [$whereExterno, $privilegio];
}
function filtroExternoWhere(string $campo = 'rfc'): array { return filtroExterno($campo, 'where'); }
function filtroExternoAnd(string $campo = 'rfc'): array   { return filtroExterno($campo, 'and'); }

// -------------------------------------------------------------
// Filtro para usuarios EXTERNOS (versión segura p/ consultas preparadas)
// Devuelve:
//  - clause_where: " WHERE <campo> = ?"   (o "")
//  - clause_and:   " AND <campo> = ?"     (o "")
//  - params:       array con valores para bind_param
//  - types:        string de tipos (ej. "s")
//  - privilegio:   "N" si externo, "S" si interno
// -------------------------------------------------------------
if (!function_exists('getFiltroExternoSeguro')) {
    function getFiltroExternoSeguro(string $campo = 'rfc'): array {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }

        $esExterno = (($_SESSION['user_externo'] ?? 'N') === 'S');
        // Por compatibilidad: si no hay user_rfc, tomamos user_username
        $rfc = $_SESSION['user_rfc'] ?? $_SESSION['user_username'] ?? '';

        if ($esExterno && $rfc !== '') {
            return [
                'clause_where' => " WHERE {$campo} = ?",
                'clause_and'   => " AND {$campo} = ?",
                'params'       => [$rfc],
                'types'        => "s",
                'privilegio'   => "N",
            ];
        }

        return [
            'clause_where' => "",
            'clause_and'   => "",
            'params'       => [],
            'types'        => "",
            'privilegio'   => "S",
        ];
    }
}

// Helper para ver la SQL final (copia/pega en MySQL)
function sql_with_params(mysqli $conn, string $sql, string $types, ...$params): string {
    $i = 0;
    foreach (str_split($types) as $t) {
        $v = $params[$i++];
        if ($v === null) {
            $rep = "NULL";
        } else {
            switch ($t) {
                case 'i': // integer
                case 'd': // double
                    $rep = (string)$v;
                    break;
                case 's': // string
                case 'b': // blob (lo tratamos como string para debug)
                default:
                    $rep = "'" . $conn->real_escape_string((string)$v) . "'";
            }
        }
        // Sustituye solo el PRIMER ? que quede
        $sql = preg_replace('/\?/', $rep, $sql, 1);
    }
    return $sql;
}

// Ruta base de la app (p. ej. /template/ o /receptor/)
$BASE_URL = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';

function asset($rel) {
  global $BASE_URL;
  return $BASE_URL . ltrim($rel, '/');
}

function safe_status(int $code): void {
    if (!headers_sent()) { http_response_code($code); }
}

?>