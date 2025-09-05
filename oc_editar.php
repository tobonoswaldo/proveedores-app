<?php
/**
 * oc_editar.php
 * Editar cabecera de la OC y desasociar facturas.
 */
session_start();
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['usuario'])) { header('Location: login.php'); exit(); }

$oc_id = (int)($_GET['oc_id'] ?? 0);
if ($oc_id<=0) { http_response_code(400); exit('OC inválida'); }

/* Obtener cabecera */
$stmt = $conn->prepare("SELECT id, folio, proveedor_rfc, fecha_emision, fecha_entrega, moneda, observaciones FROM ordenes_compra WHERE id=?");
$stmt->bind_param('i', $oc_id); $stmt->execute();
$oc = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$oc){ http_response_code(404); exit('OC no encontrada'); }

/* POST: guardar cabecera */
$msg = $err = null;
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['guardar'])) {
  $fecha_emision = trim($_POST['fecha_emision'] ?? $oc['fecha_emision']);
  $fecha_entrega = trim($_POST['fecha_entrega'] ?? '');
  $observaciones = trim($_POST['observaciones'] ?? '');
  $moneda        = trim($_POST['moneda'] ?? 'MXN');

  $stmt = $conn->prepare("UPDATE ordenes_compra SET fecha_emision=?, fecha_entrega=?, moneda=?, observaciones=?, updated_at=NOW() WHERE id=?");
  $stmt->bind_param('ssssi', $fecha_emision, $fecha_entrega, $moneda, $observaciones, $oc_id);
  if ($stmt->execute()) { $msg="Cambios guardados"; } else { $err="Error: ".$conn->error; }
  $stmt->close();
  // recargar cabecera
  $stmt = $conn->prepare("SELECT id, folio, proveedor_rfc, fecha_emision, fecha_entrega, moneda, observaciones FROM ordenes_compra WHERE id=?");
  $stmt->bind_param('i', $oc_id); $stmt->execute();
  $oc = $stmt->get_result()->fetch_assoc(); $stmt->close();
}

/* Facturas asociadas */
$stmt = $conn->prepare("SELECT id, uuid, fecha, total FROM facturas_cxp WHERE orden_compra_id=? ORDER BY fecha DESC");
$stmt->bind_param('i', $oc_id); $stmt->execute();
$facturas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

function fmt($n){ return number_format((float)$n,2); }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Editar OC <?= htmlspecialchars($oc['folio']) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="css/estilos.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="dashboard-page">
<div class="wrap">

  <div class="card">
    <h2>Editar OC: <strong><?= htmlspecialchars($oc['folio']) ?></strong></h2>
    <?php if($msg): ?><div class="alert alert-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if($err): ?><div class="alert alert-err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <form method="post" style="display:grid;grid-template-columns:repeat(2,minmax(240px,1fr));gap:12px">
      <div>
        <label>Proveedor RFC</label>
        <input type="text" value="<?= htmlspecialchars($oc['proveedor_rfc']) ?>" disabled>
      </div>
      <div>
        <label>Folio</label>
        <input type="text" value="<?= htmlspecialchars($oc['folio']) ?>" disabled>
      </div>
      <div>
        <label>Fecha emisión</label>
        <input type="date" name="fecha_emision" value="<?= htmlspecialchars($oc['fecha_emision']) ?>" required>
      </div>
      <div>
        <label>Fecha entrega (opcional)</label>
        <input type="date" name="fecha_entrega" value="<?= htmlspecialchars($oc['fecha_entrega']) ?>">
      </div>
      <div>
        <label>Moneda</label>
        <input type="text" name="moneda" value="<?= htmlspecialchars($oc['moneda']) ?>" maxlength="3">
      </div>
      <div style="grid-column:1/-1">
        <label>Observaciones</label>
        <textarea name="observaciones" rows="3"><?= htmlspecialchars($oc['observaciones']) ?></textarea>
      </div>
      <div style="grid-column:1/-1;display:flex;gap:8px">
        <button class="btn btn-primary" type="submit" name="guardar" value="1"><i class="fa fa-save"></i> Guardar</button>
        <a class="btn btn-outline" href="oc_ver.php?oc_id=<?= (int)$oc_id ?>"><i class="fa fa-eye"></i> Ver OC</a>
        <a class="btn btn-outline" href="oc_agregar_facturas.php?oc_id=<?= (int)$oc_id ?>"><i class="fa fa-file-invoice"></i> Agregar facturas</a>
      </div>
    </form>
  </div>

  <div class="card" style="margin-top:16px">
    <h3>Facturas asociadas</h3>
    <table class="tbl">
      <thead><tr><th>UUID</th><th>Fecha</th><th class="text-right">Total</th><th>Acción</th></tr></thead>
      <tbody>
      <?php if(!$facturas): ?>
        <tr><td colspan="4" class="muted">Sin facturas.</td></tr>
      <?php else: foreach($facturas as $f): ?>
        <tr>
          <td><?= htmlspecialchars($f['uuid']) ?></td>
          <td><?= htmlspecialchars($f['fecha']) ?></td>
          <td class="text-right"><?= fmt($f['total']) ?></td>
          <td>
            <a class="btn btn-danger" href="oc_quitar_factura.php?oc_id=<?= (int)$oc_id ?>&fact_id=<?= (int)$f['id'] ?>"
               onclick="return confirm('¿Quitar esta factura de la OC?')">
               <i class="fa fa-unlink"></i> Quitar
            </a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

</div>
</body>
</html>
