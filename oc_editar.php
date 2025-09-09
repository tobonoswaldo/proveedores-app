<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}

$oc_id=(int)($_GET['oc_id'] ?? 0);
if($oc_id<=0){ http_response_code(400); exit('OC inválida'); }

$stmt=$conn->prepare("SELECT id, folio, proveedor_rfc FROM ordenes_compra WHERE id=?");
$stmt->bind_param('i',$oc_id);
$stmt->execute();
$oc=$stmt->get_result()->fetch_assoc();
$stmt->close();
if(!$oc){ http_response_code(404); exit('OC no encontrada'); }
abortIfExternoAndDifferentRFC($oc['proveedor_rfc']);
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Editar OC <?= e($oc['folio']) ?></title>
<link rel="stylesheet" href="css/estilos.css">
</head><body class="dashboard-page">
<div class="wrap">
  <div class="card" id="pagos">
    <h3>Agregar pago a OC <?= e($oc['folio']) ?></h3>
    <form method="post" action="oc_pago_accion.php" enctype="multipart/form-data">
      <input type="hidden" name="accion" value="agregar_pago">
      <input type="hidden" name="oc_id" value="<?= (int)$oc_id ?>">
      <div class="grid-2">
        <div>
          <label>Fecha programada *</label>
          <input type="date" name="fecha_programada" required>
        </div>
        <div>
          <label>Monto *</label>
          <input type="number" step="0.01" name="monto" required>
        </div>
        <div>
          <label>Fecha de pago</label>
          <input type="date" name="fecha_pago">
        </div>
        <div>
          <label>Comprobante (PDF)</label>
          <input type="file" name="comprobante_pdf" accept="application/pdf">
        </div>
        <div class="col-span-2">
          <label>Observaciones</label>
          <input type="text" name="observaciones" maxlength="255" placeholder="Opcional">
        </div>
      </div>
      <button class="btn btn-primary" type="submit">Guardar pago</button>
      <a class="btn" href="oc_ver.php?oc_id=<?= (int)$oc_id ?>">Volver</a>
    </form>
  </div>
</div>
</body></html>
