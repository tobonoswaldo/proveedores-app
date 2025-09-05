<?php
/**
 * Asociar facturas a una Orden de Compra
 * - Muestra SOLO facturas sin OC del mismo RFC proveedor de la OC
 * - Si la OC está CERRADA, se oculta el formulario (UX) —los triggers ya bloquean
 * - Tras asociar, redirige al detalle de la OC (oc_ver.php?ok=1)
 */

session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php'); exit();
}

/* ---------------------------------------------------------------------------
 * Parámetros
 * -------------------------------------------------------------------------*/
$oc_id = isset($_GET['oc_id']) ? (int)$_GET['oc_id'] : 0;
if ($oc_id <= 0) { http_response_code(400); exit('OC inválida.'); }

/* ---------------------------------------------------------------------------
 * Cabecera de la OC
 * -------------------------------------------------------------------------*/
$sqlOc = "SELECT id, folio, proveedor_rfc, fecha_emision, moneda, total, estatus
          FROM ordenes_compra WHERE id = ?";
$stmt = $conn->prepare($sqlOc);
$stmt->bind_param('i', $oc_id);
$stmt->execute();
$oc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$oc) { http_response_code(404); exit('OC no encontrada.'); }

$ocCerrada = ($oc['estatus'] === 'CERRADA');

/* ---------------------------------------------------------------------------
 * POST: asociar facturas seleccionadas
 * -------------------------------------------------------------------------*/
$alert = null; $err = null;

if (!$ocCerrada && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['facturas'])) {
    $ids = array_map('intval', $_POST['facturas'] ?? []);
    if (!$ids) {
        $alert = 'No seleccionaste facturas.';
    } else {
        $ok = 0; $skip = 0;
        $conn->begin_transaction();
        try {
            $sqlUpd = "UPDATE facturas_cxp
                       SET orden_compra_id=?, orden_compra_folio=?
                       WHERE id=? AND orden_compra_id IS NULL";
            $upd = $conn->prepare($sqlUpd);
            foreach ($ids as $fid) {
                $upd->bind_param('isi', $oc['id'], $oc['folio'], $fid);
                $upd->execute();
                ($upd->affected_rows === 1) ? $ok++ : $skip++;
            }
            $upd->close();
            $conn->commit();
            // Estatus/totales se recalculan por triggers. Redirige al detalle.
            header('Location: oc_ver.php?oc_id='.(int)$oc['id'].'&ok=1');
            exit();
        } catch (Throwable $e) {
            $conn->rollback();
            $err = 'Error al asociar: '.$e->getMessage();
        }
    }
}

/* ---------------------------------------------------------------------------
 * Listado de facturas elegibles (sin OC y del mismo proveedor)
 * -------------------------------------------------------------------------*/
$buscar = trim($_GET['q'] ?? '');

$sqlList = "SELECT id, uuid, emisor_rfc, receptor_rfc, fecha, total
            FROM facturas_cxp
            WHERE orden_compra_id IS NULL
              AND emisor_rfc = ?
              ".($buscar !== '' ? "AND (uuid LIKE CONCAT('%',?,'%'))" : "")."
            ORDER BY fecha DESC, id DESC
            LIMIT 500";
if ($buscar !== '') {
    $stmt = $conn->prepare($sqlList);
    $stmt->bind_param('ss', $oc['proveedor_rfc'], $buscar);
} else {
    $stmt = $conn->prepare($sqlList);
    $stmt->bind_param('s', $oc['proveedor_rfc']);
}
$stmt->execute();
$facturas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ---------------------------------------------------------------------------
 * Render
 * -------------------------------------------------------------------------*/
function fmt($n){ return number_format((float)$n, 2); }
$badgeClass = [
    'BORRADOR'=>'badge-draft','ABIERTA'=>'badge-open',
    'PARCIAL'=>'badge-partial','CERRADA'=>'badge-closed'
][$oc['estatus']] ?? 'badge-draft';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Asociar facturas | <?= htmlspecialchars($oc['folio']) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="css/estilos.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="dashboard-page">
<div class="wrap">
  <div class="card">
    <h2>Asociar facturas a la OC <strong><?= htmlspecialchars($oc['folio']) ?></strong></h2>
    <div class="muted">
      Proveedor RFC: <strong><?= htmlspecialchars($oc['proveedor_rfc']) ?></strong>
      · Estatus: <span class="badge-pill <?= $badgeClass ?>"><?= htmlspecialchars($oc['estatus']) ?></span>
    </div>

    <?php if ($alert): ?><div class="alert alert-ok"><?= htmlspecialchars($alert) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="toolbar">
      <form method="get">
        <input type="hidden" name="oc_id" value="<?= (int)$oc['id'] ?>">
        <input class="search" type="text" name="q" placeholder="Buscar por UUID…" value="<?= htmlspecialchars($buscar) ?>">
        <button class="btn btn-outline" type="submit"><i class="fa fa-search"></i> Buscar</button>
      </form>
      <div style="display:flex;gap:8px">
        <a class="btn btn-outline" href="oc_ver.php?oc_id=<?= (int)$oc['id'] ?>"><i class="fa fa-clipboard-list"></i> Ver detalle de la OC</a>
        <a class="btn btn-outline" href="ordenes_compra_listar.php"><i class="fa fa-arrow-left"></i> Regresar</a>
      </div>
    </div>

    <?php if ($ocCerrada): ?>
      <div class="alert alert-err">Esta Orden de Compra está <strong>CERRADA</strong>. No admite más facturas.</div>
    <?php else: ?>
      <form method="post">
        <table class="tbl">
          <thead>
          <tr>
            <th class="col-select"><input type="checkbox" id="chkAll" onclick="document.querySelectorAll('input[name=&quot;facturas[]&quot;]').forEach(c=>c.checked=this.checked)"></th>
            <th>UUID</th>
            <th>Emisor RFC</th>
            <th>Receptor RFC</th>
            <th>Fecha</th>
            <th class="text-right">Total</th>
          </tr>
          </thead>
          <tbody>
          <?php if (!$facturas): ?>
            <tr><td colspan="6" class="muted">No hay facturas disponibles (sin OC) para este proveedor.</td></tr>
          <?php else: foreach ($facturas as $f): ?>
            <tr>
              <td><input type="checkbox" name="facturas[]" value="<?= (int)$f['id'] ?>"></td>
              <td><?= htmlspecialchars($f['uuid']) ?></td>
              <td><?= htmlspecialchars($f['emisor_rfc']) ?></td>
              <td><?= htmlspecialchars($f['receptor_rfc']) ?></td>
              <td><?= htmlspecialchars($f['fecha']) ?></td>
              <td class="text-right"><?= fmt($f['total']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>

        <div class="toolbar">
          <button class="btn btn-primary" type="submit"><i class="fa fa-link"></i> Asociar seleccionadas</button>
          <span class="badge">Se muestran solo facturas <strong>sin OC</strong> del RFC proveedor.</span>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
