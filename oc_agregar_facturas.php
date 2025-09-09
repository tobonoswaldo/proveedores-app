<?php
// oc_agregar_facturas.php — Crear OC (si nueva) y asociar/desasociar facturas
// ---------------------------------------------------------------------------------
// Requisitos:
//  - Tabla: ordenes_compra(id, folio, proveedor_rfc, fecha_emision, total, estatus, estado_pago, ...)
//  - Tabla: facturas_cxp(id, uuid, emisor_rfc, receptor_rfc, fecha, total, xml_path, pdf_path, orden_compra_id)
//  - Control de sesión: externos solo ven su RFC (via abortIfExternoAndDifferentRFC)
// ---------------------------------------------------------------------------------

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function n($v){ return number_format((float)$v, 2); }

// ---------------------------------------------------------------------------------
// 0) Crear OC en BORRADOR si llegan con ?nueva=1 y no hay oc_id
//    - Folio secuencial basado en id: OC-YYYYMMDD-000123
//    - Externo => proveedor_rfc = su RFC; Interno => opcional (NULL)
// ---------------------------------------------------------------------------------
$oc_id = (int)($_GET['oc_id'] ?? $_POST['oc_id'] ?? 0);

if ($oc_id <= 0 && isset($_GET['nueva'])) {
    // RFC para externos; para internos opcional
    if (($_SESSION['user_externo'] ?? 'N') === 'S') {
        $proveedorRFC = strtoupper($_SESSION['user_rfc'] ?? $_SESSION['user_username'] ?? '');
    } else {
        $proveedorRFC = strtoupper(trim($_GET['proveedor_rfc'] ?? $_POST['proveedor_rfc'] ?? ''));
        if ($proveedorRFC === '') { $proveedorRFC = null; }
    }

    // Folio temporal (no nulo) para poder insertar
    $folioTmp = 'OC-TMP-'.substr(bin2hex(random_bytes(6)), 0, 6);

    $conn->begin_transaction();
    try {
        // 1) Insert BORRADOR
        $sql = "INSERT INTO ordenes_compra
                   (folio, proveedor_rfc, fecha_emision, total, estatus, estado_pago)
                VALUES
                   (?, NULLIF(?,''), CURDATE(), 0, 'BORRADOR', 'Pendiente')";
        $stmt = $conn->prepare($sql);
        $tmp  = $proveedorRFC ?? '';
        $stmt->bind_param('ss', $folioTmp, $tmp);
        $stmt->execute();
        $oc_id = (int)$stmt->insert_id;
        $stmt->close();

        // 2) Folio secuencial basado en id
        $folioFinal = 'OC-'.date('Ymd').'-'.str_pad((string)$oc_id, 6, '0', STR_PAD_LEFT);

        // 3) Update folio
        $up = $conn->prepare("UPDATE ordenes_compra SET folio=? WHERE id=?");
        $up->bind_param('si', $folioFinal, $oc_id);
        $up->execute();
        $up->close();

        $conn->commit();

        // Redirigir con oc_id listo
        header("Location: oc_agregar_facturas.php?oc_id={$oc_id}");
        exit;
    } catch (Throwable $e) {
        $conn->rollback();
        http_response_code(500);
        exit('Error al crear la OC: '.$e->getMessage());
    }
}

// ---------------------------------------------------------------------------------
// 1) Cargar cabecera OC y candado para externos
// ---------------------------------------------------------------------------------
if ($oc_id <= 0) { http_response_code(400); exit('OC inválida'); }

$stmt = $conn->prepare("SELECT id, folio, proveedor_rfc, fecha_emision, total, estatus, estado_pago
                        FROM ordenes_compra WHERE id=?");
$stmt->bind_param('i', $oc_id);
$stmt->execute();
$oc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$oc) { http_response_code(404); exit('OC no encontrada'); }

// Externo: solo su RFC
abortIfExternoAndDifferentRFC($oc['proveedor_rfc']);

// ---------------------------------------------------------------------------------
// 2) POST acciones: asociar / quitar
// ---------------------------------------------------------------------------------
$msg = null;

function ints(array $a): array {
    return array_values(array_unique(array_map(static fn($x)=> (int)$x, $a)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'asociar') {
        $ids = isset($_POST['facturas']) ? ints($_POST['facturas']) : [];
        if (!$ids) { $msg = "Selecciona al menos una factura disponible."; goto post_done; }

        // Traer las facturas candidatas y validar condiciones
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $conn->begin_transaction();
        try {
            // Bloquea OC
            $stmt = $conn->prepare("SELECT id, proveedor_rfc FROM ordenes_compra WHERE id=? FOR UPDATE");
            $stmt->bind_param('i', $oc_id);
            $stmt->execute();
            $ocRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$ocRow) { throw new Exception('OC no encontrada'); }
            $ocRFC = $ocRow['proveedor_rfc'];

            // Candidatas: solo sin OC y (si ocRFC) del mismo RFC
            if (!empty($ocRFC)) {
                $q = "SELECT id, emisor_rfc FROM facturas_cxp
                      WHERE id IN ($placeholders) AND orden_compra_id IS NULL AND emisor_rfc=?";
                $stmt = $conn->prepare($q);
                $bindTypes = $types . 's';
                $params = $ids; $params[] = $ocRFC;
                $stmt->bind_param($bindTypes, ...$params);
            } else {
                // OC sin RFC: se permite, pero todas deben ser del mismo RFC; lo fijamos luego
                $q = "SELECT id, emisor_rfc FROM facturas_cxp
                      WHERE id IN ($placeholders) AND orden_compra_id IS NULL";
                $stmt = $conn->prepare($q);
                $stmt->bind_param($types, ...$ids);
            }
            $stmt->execute();
            $cand = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (count($cand) !== count($ids)) {
                throw new Exception('Una o más facturas ya fueron asociadas por otro proceso o no cumplen condiciones.');
            }

            // Si la OC no tiene proveedor_rfc, definirlo con el RFC de estas facturas (todas deben tener el mismo)
            if (empty($ocRFC)) {
                $rfcs = array_values(array_unique(array_map(static fn($r)=> $r['emisor_rfc'], $cand)));
                if (count($rfcs) !== 1) {
                    throw new Exception('Todas las facturas a asociar deben ser del mismo RFC cuando la OC no tiene proveedor asignado.');
                }
                $nuevoRFC = $rfcs[0];
                $up = $conn->prepare("UPDATE ordenes_compra SET proveedor_rfc=? WHERE id=?");
                $up->bind_param('si', $nuevoRFC, $oc_id);
                $up->execute();
                $up->close();
            }

            // Asociar (solo las sin OC para evitar carreras)
            $q = "UPDATE facturas_cxp SET orden_compra_id=? WHERE id IN ($placeholders) AND orden_compra_id IS NULL";
            $stmt = $conn->prepare($q);
            $bindTypes = 'i' . $types;
            $params = array_merge([$oc_id], $ids);
            $stmt->bind_param($bindTypes, ...$params);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            header("Location: ordenes_compra_listar.php");
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $msg = "No fue posible asociar: " . $e->getMessage();
        }
    }

    if ($accion === 'quitar') {
        $ids = isset($_POST['facturas_asociadas']) ? ints($_POST['facturas_asociadas']) : [];
        if (!$ids) { $msg = "Selecciona al menos una factura asociada para quitar."; goto post_done; }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $conn->begin_transaction();
        try {
            // Solo quitar si pertenecen a esta OC
            $q = "UPDATE facturas_cxp SET orden_compra_id=NULL
                  WHERE orden_compra_id=? AND id IN ($placeholders)";
            $stmt = $conn->prepare($q);
            $bindTypes = 'i' . $types;
            $params = array_merge([$oc_id], $ids);
            $stmt->bind_param($bindTypes, ...$params);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            header("Location: ordenes_compra_listar.php");
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $msg = "No fue posible quitar: " . $e->getMessage();
        }
    }
}
post_done:

// ---------------------------------------------------------------------------------
// 3) Datos para la vista: asociadas y disponibles
//    - Disponibles: si OC tiene proveedor_rfc => solo de ese RFC; si no, todas sin OC (solo para internos)
// ---------------------------------------------------------------------------------
$asociadas = [];
$stmt = $conn->prepare("SELECT id, uuid, emisor_rfc, receptor_rfc, fecha, total, xml_path, pdf_path
                        FROM facturas_cxp
                        WHERE orden_compra_id=?
                        ORDER BY fecha DESC, id DESC");
$stmt->bind_param('i', $oc_id);
$stmt->execute();
$asociadas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!empty($oc['proveedor_rfc'])) {
    $stmt = $conn->prepare("SELECT id, uuid, emisor_rfc, receptor_rfc, fecha, total, xml_path, pdf_path
                            FROM facturas_cxp
                            WHERE orden_compra_id IS NULL AND emisor_rfc=?
                            ORDER BY fecha DESC, id DESC");
    $stmt->bind_param('s', $oc['proveedor_rfc']);
} else {
    // Si no hay proveedor en la OC y el usuario es externo, realmente no debería pasar; por si acaso:
    if (($_SESSION['user_externo'] ?? 'N') === 'S') {
        $rfc = strtoupper($_SESSION['user_rfc'] ?? $_SESSION['user_username'] ?? '');
        $stmt = $conn->prepare("SELECT id, uuid, emisor_rfc, receptor_rfc, fecha, total, xml_path, pdf_path
                                FROM facturas_cxp
                                WHERE orden_compra_id IS NULL AND emisor_rfc=?
                                ORDER BY fecha DESC, id DESC");
        $stmt->bind_param('s', $rfc);
    } else {
        // Interno: todas sin OC (permitimos fijar proveedor al asociar)
        $stmt = $conn->prepare("SELECT id, uuid, emisor_rfc, receptor_rfc, fecha, total, xml_path, pdf_path
                                FROM facturas_cxp
                                WHERE orden_compra_id IS NULL
                                ORDER BY fecha DESC, id DESC");
    }
}
$stmt->execute();
$disponibles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------------------------------------------------------------------------------
// 4) Render
// ---------------------------------------------------------------------------------
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>OC <?= e($oc['folio']) ?> · Asociar facturas</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="css/estilos.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="dashboard-page">
<div class="wrap">

  <div class="card">
    <div class="flex-between">
      <div>
        <h2>OC <?= e($oc['folio']) ?> · <?= e($oc['proveedor_rfc'] ?: '—') ?></h2>
        <p>
          Estatus: <strong><?= e($oc['estatus']) ?></strong> ·
          Pago: <strong><?= e($oc['estado_pago']) ?></strong> ·
          Total: <strong>$<?= n($oc['total']) ?></strong>
        </p>
      </div>
      <div>
        <a class="btn btn-outline" href="oc_ver.php?oc_id=<?= (int)$oc['id'] ?>">
          <i class="fa fa-eye"></i> Ver OC
        </a>
      </div>
    </div>
    <?php if(!empty($msg)): ?>
      <div class="alert alert-err"><?= e($msg) ?></div>
    <?php endif; ?>
  </div>

  <div class="grid-2 gap">
    <!-- Facturas disponibles: asociar -->
    <div class="card">
      <h3>Facturas disponibles (sin OC)</h3>
      <form method="post">
        <input type="hidden" name="oc_id" value="<?= (int)$oc['id'] ?>">
        <input type="hidden" name="accion" value="asociar">
        <table class="tbl">
          <thead>
            <tr>
              <th></th>
              <th>UUID</th>
              <th>Emisor</th>
              <th>Receptor</th>
              <th>Fecha</th>
              <th class="text-right">Total</th>
              <th>Docs</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$disponibles): ?>
            <tr><td colspan="7" class="muted">No hay facturas disponibles.</td></tr>
          <?php else: foreach($disponibles as $f): ?>
            <tr>
              <td><input type="checkbox" name="facturas[]" value="<?= (int)$f['id'] ?>"></td>
              <td><?= e($f['uuid']) ?></td>
              <td><?= e($f['emisor_rfc']) ?></td>
              <td><?= e($f['receptor_rfc']) ?></td>
              <td><?= e($f['fecha']) ?></td>
              <td class="text-right"><?= n($f['total']) ?></td>
              <td>
                <?php if(!empty($f['xml_path'])): ?>
                  <a href="/<?= e($f['xml_path']) ?>" target="_blank" title="XML"><i class="fa fa-file-code"></i></a>
                <?php endif; ?>
                <?php if(!empty($f['pdf_path'])): ?>
                  &nbsp;<a href="/<?= e($f['pdf_path']) ?>" target="_blank" title="PDF"><i class="fa fa-file-pdf"></i></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
        <button class="btn btn-primary" type="submit"><i class="fa fa-link"></i> Asociar seleccionadas</button>
      </form>
    </div>

    <!-- Facturas asociadas: quitar -->
    <div class="card">
      <h3>Facturas asociadas a esta OC</h3>
      <form method="post" onsubmit="return confirm('¿Quitar las facturas seleccionadas de esta OC?');">
        <input type="hidden" name="oc_id" value="<?= (int)$oc['id'] ?>">
        <input type="hidden" name="accion" value="quitar">
        <table class="tbl">
          <thead>
            <tr>
              <th></th>
              <th>UUID</th>
              <th>Emisor</th>
              <th>Receptor</th>
              <th>Fecha</th>
              <th class="text-right">Total</th>
              <th>Docs</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$asociadas): ?>
            <tr><td colspan="7" class="muted">Sin facturas asociadas.</td></tr>
          <?php else: foreach($asociadas as $f): ?>
            <tr>
              <td><input type="checkbox" name="facturas_asociadas[]" value="<?= (int)$f['id'] ?>"></td>
              <td><?= e($f['uuid']) ?></td>
              <td><?= e($f['emisor_rfc']) ?></td>
              <td><?= e($f['receptor_rfc']) ?></td>
              <td><?= e($f['fecha']) ?></td>
              <td class="text-right"><?= n($f['total']) ?></td>
              <td>
                <?php if(!empty($f['xml_path'])): ?>
                  <a href="/<?= e($f['xml_path']) ?>" target="_blank" title="XML"><i class="fa fa-file-code"></i></a>
                <?php endif; ?>
                <?php if(!empty($f['pdf_path'])): ?>
                  &nbsp;<a href="/<?= e($f['pdf_path']) ?>" target="_blank" title="PDF"><i class="fa fa-file-pdf"></i></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
        <button class="btn btn-outline" type="submit"><i class="fa fa-unlink"></i> Quitar seleccionadas</button>
      </form>
    </div>
  </div>

</div>
</body>
</html>
