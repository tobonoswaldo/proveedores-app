<?php
// oc_ver.php — Detalle de OC con pagos y facturas asociadas
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function n($v){ return number_format((float)$v, 2); }

$oc_id = (int)($_GET['oc_id'] ?? 0);
if ($oc_id <= 0) { http_response_code(400); exit('OC inválida'); }

/* Cabecera OC */
$stmt = $conn->prepare("
  SELECT id, folio, proveedor_rfc, fecha_emision, moneda, total, estatus, estado_pago
  FROM ordenes_compra WHERE id=?
");
$stmt->bind_param('i', $oc_id);
$stmt->execute();
$oc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$oc) { http_response_code(404); exit('OC no encontrada'); }

/* Candado para EXTERNOS */
abortIfExternoAndDifferentRFC($oc['proveedor_rfc']);

/* Pagos de la OC */
$stmt = $conn->prepare("
  SELECT id, monto, fecha_programada, fecha_pago, comprobante_pago_path, observaciones
  FROM oc_pagos
  WHERE oc_id=?
  ORDER BY COALESCE(fecha_programada,'9999-12-31') ASC, id ASC
");
$stmt->bind_param('i', $oc_id);
$stmt->execute();
$pagos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Facturas asociadas a la OC */
$stmt = $conn->prepare("
  SELECT id, uuid, emisor_rfc, receptor_rfc, fecha, total, xml_path, pdf_path
  FROM facturas_cxp
  WHERE orden_compra_id = ?
  ORDER BY fecha DESC, id DESC
");
$stmt->bind_param('i', $oc_id);
$stmt->execute();
$facturas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>OC <?= e($oc['folio']) ?></title>
  <link rel="stylesheet" href="css/estilos.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="dashboard-page">
<div class="wrap">

  <!-- Cabecera -->
  <div class="card">
    <div class="flex-between">
      <div>
        <h2>OC <?= e($oc['folio']) ?> · <?= e($oc['proveedor_rfc']) ?></h2>
        <p>
          Estatus: <strong><?= e($oc['estatus']) ?></strong> ·
          Pago: <strong><?= e($oc['estado_pago']) ?></strong> ·
          Total: <strong>$<?= n($oc['total']) ?></strong>
        </p>
      </div>

      <div class="actions">
        <a class="btn btn-primary" href="oc_agregar_facturas.php?oc_id=<?= (int)$oc['id'] ?>">
          <i class="fa fa-file-invoice"></i> Asociar facturas
        </a>
      </div>
    </div>
  </div>

  <!-- Facturas asociadas (restaurado) -->
  <div class="card">
    <h3>Facturas asociadas</h3>
    <table class="tbl">
      <thead>
        <tr>
          <th>ID</th>
          <th>UUID</th>
          <th>Emisor RFC</th>
          <th>Receptor RFC</th>
          <th>Fecha</th>
          <th class="text-right">Total</th>
          <th>Docs</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$facturas): ?>
        <tr><td colspan="7" class="muted">Sin facturas asociadas.</td></tr>
      <?php else: foreach($facturas as $f): ?>
        <tr>
          <td><?= (int)$f['id'] ?></td>
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
  </div>

  <!-- Pagos: programación, aplicación y comprobantes (se mantiene) -->
  <div class="card">
    <div class="flex-between">
      <h3>Pagos (programación, aplicación y comprobantes)</h3>
      <a class="btn btn-primary" href="oc_editar.php?oc_id=<?= (int)$oc['id'] ?>#pagos"><i class="fa fa-plus"></i> Agregar pago</a>
    </div>
    <table class="tbl">
      <thead>
        <tr>
          <th>ID</th>
          <th>Fecha programada</th>
          <th class="text-right">Monto</th>
          <th>Fecha de pago</th>
          <th>Comprobante</th>
          <th>Notas</th>
          <th>Adjuntar/Reemplazar</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$pagos): ?>
        <tr><td colspan="7" class="muted">Sin pagos.</td></tr>
      <?php else: foreach($pagos as $p): ?>
        <tr>
          <td><?= (int)$p['id'] ?></td>
          <td><?= e($p['fecha_programada'] ?: '-') ?></td>
          <td class="text-right">$<?= n($p['monto']) ?></td>
          <td><?= e($p['fecha_pago'] ?: '-') ?></td>
          <td>
            <?php if (!empty($p['comprobante_pago_path'])): ?>
              <a href="/<?= e($p['comprobante_pago_path']) ?>" target="_blank" title="Comprobante PDF">
                <i class="fa fa-file-pdf"></i>
              </a>
            <?php else: ?>
              <span class="muted">–</span>
            <?php endif; ?>
          </td>
          <td><?= e($p['observaciones'] ?? '') ?></td>
          <td>
            <form method="post" action="oc_pago_accion.php" enctype="multipart/form-data" class="inline">
              <input type="hidden" name="accion" value="adjuntar_comprobante">
              <input type="hidden" name="oc_id" value="<?= (int)$oc['id'] ?>">
              <input type="hidden" name="pago_id" value="<?= (int)$p['id'] ?>">
              <input type="file" name="comprobante_pdf" accept="application/pdf" required>
              <button class="btn btn-outline" type="submit"><i class="fa fa-paperclip"></i> Subir</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

</div>
</body>
</html>
