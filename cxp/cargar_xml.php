<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ============================================================================
 *  CXP | cargar_xml.php
 *  - Sube XML CFDI, prelee y valida (RFC/UUID y usuarios externos)
 *  - Guarda archivos en /proveedores-app/cxp/documentos/<RFC>/<UUID>.(xml|pdf)
 *  - Precarga TODOS los datos (incluye impuestos) y permite guardar en BD
 *  - Redirige a listar_facturas.php al finalizar
 * ============================================================================ */

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../bases/funciones.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: ../login.php");
    exit();
}

$precarga = null;          // Datos parseados del CFDI para precargar el formulario
$xml_rel = null;           // Ruta WEB-relativa del XML para la BD
$msg = null;               // Mensaje de estado para el usuario

/* ============================================================================
 *  PASO 1: SUBIR Y LEER XML
 * ============================================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subirXml'])) {
/*
    // Aquí solo reforzamos el candado para externos:
    if (isExterno()) {
        $rfcUsuario = strtoupper((string)currentRFC());
        $rfcEmisor  = strtoupper((string)($data['emisor_rfc'] ?? ''));
        if ($rfcEmisor === '' || $rfcEmisor !== $rfcUsuario) {
            http_response_code(403);
            exit('No autorizado: el RFC('.$rfcEmisor.') emisor del CFDI no coincide con su usuario('.$rfcUsuario.').');
        }
    }
*/
    $res = cfdi_cargar_y_guardar($_FILES['xml_cfdi'] ?? []);

    if (!$res['ok']) {
        $msg = $res['msg'];
    } else {
        $d = $res['data'];            // Datos del CFDI parseados
        $xml_abs = $res['ruta_abs'];  // Ruta absoluta en disco (para limpiar si algo falla)
        $xml_rel = $res['ruta_rel'];  // Ruta web-relativa (para BD / formulario)

        // Prevalidación RFC emisor
        if (!validar_rfc_mex($d['emisor_rfc'])) {
            if (is_file($xml_abs)) @unlink($xml_abs);
            $msg = "RFC emisor inválido en el XML.";
        } else {
            // Validación de usuario externo (RFC emisor debe coincidir con username) con excepción
            $valid = puede_subir_emisor($conn, $_SESSION['usuario'], $d['emisor_rfc']);
            if (!$valid['ok']) {
                if (is_file($xml_abs)) @unlink($xml_abs);
                $msg = "Bloqueado: " . $valid['msg'];
            } else {
                // Prevalidación UUID (si existe en el timbre)
                if (!empty($d['uuid']) && !validar_uuid($d['uuid'])) {
                    if (is_file($xml_abs)) @unlink($xml_abs);
                    $msg = "UUID inválido en el XML.";
                } else {
                    $precarga = $d;
                    $msg = "XML cargado y validado correctamente.";
                }
            }
        }
    }
}

/* ============================================================================
 *  PASO 2: GUARDAR EN BD (INSERT/UPDATE)
 * ============================================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardarFactura'])) {

    // Campos esperados del formulario (mantener orden)
    $campos = [
        'uuid','emisor_rfc','emisor_nombre','receptor_rfc','receptor_nombre',
        'serie','folio','fecha','subtotal','total','moneda',
        'forma_pago','metodo_pago','uso_cfdi','xml_path',
        'total_impuestos_trasladados','total_impuestos_retenidos',
        'iva_trasladado','ieps_trasladado','isr_retenido','iva_retenido'
    ];

    // Normaliza todos los campos de POST
    $data = [];
    foreach ($campos as $c) $data[$c] = trim($_POST[$c] ?? '');

    // --- Normalización de xml_path a ruta web-relativa (/proveedores-app/cxp/documentos/...) ---
    $appBase = rtrim($GLOBALS['APP_WEB_BASE'] ?? '/proveedores-app', '/');
    $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');

    if (!empty($data['xml_path'])) {
        // Quitar esquema/host si viene completo
        $data['xml_path'] = preg_replace('#^https?://[^/]+#', '', $data['xml_path']);

        // Si viene con filesystem (/opt/homebrew/var/www...), recortar a relativo
        if ($docroot && strpos($data['xml_path'], $docroot) === 0) {
            $data['xml_path'] = substr($data['xml_path'], strlen($docroot));
        }
        // Si comienza con /cxp/documentos, prefijar con /proveedores-app
        if (strpos($data['xml_path'], $appBase.'/cxp/documentos/') !== 0) {
            if (strpos($data['xml_path'], '/cxp/documentos/') === 0) {
                $data['xml_path'] = $appBase . $data['xml_path'];
            }
        }
    }

    // Prevalidaciones mínimas
    if (!validar_rfc_mex($data['emisor_rfc'])) {
        $msg = "RFC emisor inválido.";
        goto render;
    }
    if (!empty($data['uuid']) && !validar_uuid($data['uuid'])) {
        $msg = "UUID inválido.";
        goto render;
    }

    // Validación de externo (otra vez por seguridad)
    $valid = puede_subir_emisor($conn, $_SESSION['usuario'], $data['emisor_rfc']);
    if (!$valid['ok']) {
        $msg = "Bloqueado: " . $valid['msg'];
        goto render;
    }

    // Normaliza fecha a DATETIME MySQL
    $fechaMysql = null;
    if (!empty($data['fecha'])) {
        // de "YYYY-MM-DDTHH:MM:SS" a "YYYY-MM-DD HH:MM:SS"
        $fechaMysql = str_replace('T', ' ', substr($data['fecha'], 0, 19));
    }

    // Casteos numéricos
    $subtotal = (float)($data['subtotal'] ?: 0);
    $total    = (float)($data['total'] ?: 0);
    $totTras  = (float)($data['total_impuestos_trasladados'] ?: 0);
    $totRet   = (float)($data['total_impuestos_retenidos']   ?: 0);
    $ivaTras  = (float)($data['iva_trasladado']  ?: 0);
    $iepsTras = (float)($data['ieps_trasladado'] ?: 0);
    $isrRet   = (float)($data['isr_retenido']    ?: 0);
    $ivaRet   = (float)($data['iva_retenido']    ?: 0);

    // --- PDF opcional (guardar ruta RELATIVA para BD) ---
    $pdfPathRel = null;
    if (!empty($_FILES['pdf_cfdi']['name'])) {
        $savePdf = guardar_pdf_factura($_FILES['pdf_cfdi'], $data['emisor_rfc'], $data['uuid'] ?: null);
        if (!$savePdf['ok']) {
            $msg = "PDF no guardado: ".$savePdf['msg'];
            goto render;
        }
        $pdfPathRel = $savePdf['ruta_rel'];   // <- SE GUARDA EN BD
    }

    // INSERT / UPDATE
    $sql = "INSERT INTO facturas_cxp
            (uuid, emisor_rfc, emisor_nombre, receptor_rfc, receptor_nombre, serie, folio, fecha,
             subtotal, total, moneda, forma_pago, metodo_pago, uso_cfdi, xml_path, pdf_path,
             total_impuestos_trasladados, total_impuestos_retenidos,
             iva_trasladado, ieps_trasladado, isr_retenido, iva_retenido)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
              emisor_rfc=VALUES(emisor_rfc), emisor_nombre=VALUES(emisor_nombre),
              receptor_rfc=VALUES(receptor_rfc), receptor_nombre=VALUES(receptor_nombre),
              serie=VALUES(serie), folio=VALUES(folio), fecha=VALUES(fecha),
              subtotal=VALUES(subtotal), total=VALUES(total), moneda=VALUES(moneda),
              forma_pago=VALUES(forma_pago), metodo_pago=VALUES(metodo_pago),
              uso_cfdi=VALUES(uso_cfdi), xml_path=VALUES(xml_path), pdf_path=VALUES(pdf_path),
              total_impuestos_trasladados=VALUES(total_impuestos_trasladados),
              total_impuestos_retenidos=VALUES(total_impuestos_retenidos),
              iva_trasladado=VALUES(iva_trasladado), ieps_trasladado=VALUES(ieps_trasladado),
              isr_retenido=VALUES(isr_retenido), iva_retenido=VALUES(iva_retenido)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $msg = "Error de preparación SQL: " . $conn->error;
        goto render;
    }

    // TIPOS (22): ssssssss dd sssss s dddddd
    $stmt->bind_param(
        "ssssssssddssssssdddddd",
        $data['uuid'],
        $data['emisor_rfc'], $data['emisor_nombre'],
        $data['receptor_rfc'], $data['receptor_nombre'],
        $data['serie'], $data['folio'], $fechaMysql,
        $subtotal, $total, $data['moneda'],
        $data['forma_pago'], $data['metodo_pago'], $data['uso_cfdi'],
        $data['xml_path'], $pdfPathRel,
        $totTras, $totRet, $ivaTras, $iepsTras, $isrRet, $ivaRet
    );

    if ($stmt->execute()) {
        header("Location: listar_facturas.php");
        exit();
    } else {
        $msg = "Error al guardar en BD: " . $stmt->error;
    }
}

/* ============================================================================
 *  RENDER
 * ============================================================================ */
render:
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Cargar XML | <?php echo htmlspecialchars($nombreSistema); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../css/estilos.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="dashboard-page">

<div class="form-container" style="max-width:940px;">
  <div class="form-header"><i class="fa fa-file-code"></i> Cargar XML CFDI</div>

  <?php if ($msg): ?>
    <div class="alert-info" style="margin-bottom:12px;"><?php echo htmlspecialchars($msg); ?></div>
  <?php endif; ?>

  <!-- PASO 1: SUBIR XML -->
  <form method="POST" enctype="multipart/form-data" class="styled-form" style="margin-bottom:20px;">
    <label for="xml_cfdi">Archivo XML del CFDI:</label>
    <input id="xml_cfdi" type="file" name="xml_cfdi" accept=".xml,text/xml,application/xml" required>
    <button type="submit" name="subirXml"><i class="fa fa-upload"></i> Subir y leer</button>
  </form>

  <!-- PASO 2: PRECARGA + EDICIÓN -->
  <?php if ($precarga): ?>
    <div class="form-header" style="margin-top:10px;"><i class="fa fa-clipboard-list"></i> Datos precargados</div>

    <form method="POST" enctype="multipart/form-data" class="styled-form">
      <!-- Emisor -->
      <label for="emisor_rfc">RFC Emisor:</label>
      <div class="input-group"><i class="fa fa-building"></i>
        <input id="emisor_rfc" type="text" name="emisor_rfc" required
               value="<?php echo htmlspecialchars($precarga['emisor_rfc']); ?>">
      </div>

      <label for="emisor_nombre">Nombre Emisor:</label>
      <div class="input-group"><i class="fa fa-user"></i>
        <input id="emisor_nombre" type="text" name="emisor_nombre"
               value="<?php echo htmlspecialchars($precarga['emisor_nombre']); ?>">
      </div>

      <!-- Receptor -->
      <label for="receptor_rfc">RFC Receptor:</label>
      <div class="input-group"><i class="fa fa-id-card"></i>
        <input id="receptor_rfc" type="text" name="receptor_rfc" required
               value="<?php echo htmlspecialchars($precarga['receptor_rfc']); ?>">
      </div>

      <label for="receptor_nombre">Nombre Receptor:</label>
      <div class="input-group"><i class="fa fa-user"></i>
        <input id="receptor_nombre" type="text" name="receptor_nombre"
               value="<?php echo htmlspecialchars($precarga['receptor_nombre']); ?>">
      </div>

      <!-- Comprobante -->
      <label for="uuid">UUID:</label>
      <div class="input-group"><i class="fa fa-hashtag"></i>
        <input id="uuid" type="text" name="uuid"
               value="<?php echo htmlspecialchars($precarga['uuid']); ?>">
      </div>

      <label for="serie">Serie:</label>
      <div class="input-group"><i class="fa fa-tag"></i>
        <input id="serie" type="text" name="serie"
               value="<?php echo htmlspecialchars($precarga['serie']); ?>">
      </div>

      <label for="folio">Folio:</label>
      <div class="input-group"><i class="fa fa-barcode"></i>
        <input id="folio" type="text" name="folio"
               value="<?php echo htmlspecialchars($precarga['folio']); ?>">
      </div>

      <label for="fecha">Fecha del Comprobante:</label>
      <div class="input-group"><i class="fa fa-calendar"></i>
        <input id="fecha" type="datetime-local" name="fecha"
               value="<?php echo $precarga['fecha'] ? date('Y-m-d\TH:i:s', strtotime($precarga['fecha'])) : ''; ?>">
      </div>

      <label for="subtotal">SubTotal:</label>
      <div class="input-group"><i class="fa fa-dollar-sign"></i>
        <input id="subtotal" type="number" step="0.01" name="subtotal"
               value="<?php echo htmlspecialchars($precarga['subtotal']); ?>">
      </div>

      <label for="total">Total:</label>
      <div class="input-group"><i class="fa fa-dollar-sign"></i>
        <input id="total" type="number" step="0.01" name="total"
               value="<?php echo htmlspecialchars($precarga['total']); ?>">
      </div>

      <label for="moneda">Moneda:</label>
      <div class="input-group"><i class="fa fa-coins"></i>
        <input id="moneda" type="text" name="moneda"
               value="<?php echo htmlspecialchars($precarga['moneda']); ?>">
      </div>

      <label for="forma_pago">Forma de Pago:</label>
      <div class="input-group"><i class="fa fa-credit-card"></i>
        <input id="forma_pago" type="text" name="forma_pago"
               value="<?php echo htmlspecialchars($precarga['forma_pago']); ?>">
      </div>

      <label for="metodo_pago">Método de Pago:</label>
      <div class="input-group"><i class="fa fa-receipt"></i>
        <input id="metodo_pago" type="text" name="metodo_pago"
               value="<?php echo htmlspecialchars($precarga['metodo_pago']); ?>">
      </div>

      <label for="uso_cfdi">Uso CFDI:</label>
      <div class="input-group"><i class="fa fa-file-alt"></i>
        <input id="uso_cfdi" type="text" name="uso_cfdi"
               value="<?php echo htmlspecialchars($precarga['uso_cfdi']); ?>">
      </div>

      <!-- Impuestos Totales -->
      <label for="total_impuestos_trasladados">Total Impuestos Trasladados:</label>
      <div class="input-group"><i class="fa fa-file-invoice-dollar"></i>
        <input id="total_impuestos_trasladados" type="number" step="0.01" name="total_impuestos_trasladados"
               value="<?php echo htmlspecialchars($precarga['total_impuestos_trasladados']); ?>">
      </div>

      <label for="total_impuestos_retenidos">Total Impuestos Retenidos:</label>
      <div class="input-group"><i class="fa fa-file-invoice-dollar"></i>
        <input id="total_impuestos_retenidos" type="number" step="0.01" name="total_impuestos_retenidos"
               value="<?php echo htmlspecialchars($precarga['total_impuestos_retenidos']); ?>">
      </div>

      <!-- Desglose clave -->
      <label for="iva_trasladado">IVA Trasladado:</label>
      <div class="input-group"><i class="fa fa-percent"></i>
        <input id="iva_trasladado" type="number" step="0.01" name="iva_trasladado"
               value="<?php echo htmlspecialchars($precarga['iva_trasladado']); ?>">
      </div>

      <label for="ieps_trasladado">IEPS Trasladado:</label>
      <div class="input-group"><i class="fa fa-percent"></i>
        <input id="ieps_trasladado" type="number" step="0.01" name="ieps_trasladado"
               value="<?php echo htmlspecialchars($precarga['ieps_trasladado']); ?>">
      </div>

      <label for="isr_retenido">ISR Retenido:</label>
      <div class="input-group"><i class="fa fa-hand-holding-dollar"></i>
        <input id="isr_retenido" type="number" step="0.01" name="isr_retenido"
               value="<?php echo htmlspecialchars($precarga['isr_retenido']); ?>">
      </div>

      <label for="iva_retenido">IVA Retenido:</label>
      <div class="input-group"><i class="fa fa-hand-holding-dollar"></i>
        <input id="iva_retenido" type="number" step="0.01" name="iva_retenido"
               value="<?php echo htmlspecialchars($precarga['iva_retenido']); ?>">
      </div>

      <!-- Campos informativos (no guardados) -->
      <div class="grid-2" style="gap:12px; margin-top:10px;">
        <div>
          <label>Fecha Timbrado (informativo):</label>
          <div class="input-group"><i class="fa fa-clock"></i>
            <input type="text" value="<?php echo htmlspecialchars($precarga['fecha_timbrado'] ?? ''); ?>" readonly>
          </div>
        </div>
        <div>
          <label>Lugar de Expedición (informativo):</label>
          <div class="input-group"><i class="fa fa-map-marker-alt"></i>
            <input type="text" value="<?php echo htmlspecialchars($precarga['lugar_expedicion'] ?? ''); ?>" readonly>
          </div>
        </div>
      </div>

      <!-- Rutas -->
      <input type="hidden" name="xml_path" value="<?php echo htmlspecialchars($xml_rel); ?>">

      <!-- PDF opcional -->
      <label for="pdf_cfdi">PDF de la factura (opcional):</label>
      <input id="pdf_cfdi" type="file" name="pdf_cfdi" accept="application/pdf">

      <button type="submit" name="guardarFactura"><i class="fa fa-save"></i> Guardar</button>
    </form>
  <?php endif; ?>
</div>

</body>
</html>
