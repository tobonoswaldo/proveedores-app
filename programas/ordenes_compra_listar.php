<?php
if (!defined('APP_ROOT')) {
  define('APP_ROOT', dirname(__DIR__)); // desde /programas sube a la raíz del proyecto
}
require_once APP_ROOT . '/bases/auth.php';
requireLogin();

function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function n($v){return number_format((float)$v,2);}

$fx = getFiltroExternoSeguro('proveedor_rfc');

$sql = "SELECT oc.id, oc.folio, oc.proveedor_rfc, oc.fecha_emision, oc.total, oc.estatus, oc.estado_pago,
        /* próxima programación pendiente */
        (SELECT MIN(p.fecha_programada) FROM oc_pagos p
           WHERE p.oc_id=oc.id AND (p.fecha_pago IS NULL OR p.fecha_pago='0000-00-00')) AS proxima_prog,
        /* última fecha de pago */
        (SELECT MAX(p.fecha_pago) FROM oc_pagos p WHERE p.oc_id=oc.id) AS ultima_pago,
        /* cuántos comprobantes tiene */
        (SELECT COUNT(*) FROM oc_pagos p WHERE p.oc_id=oc.id AND p.comprobante_pago_path IS NOT NULL) AS cnt_comp
        FROM ordenes_compra oc".$fx['clause_where']."
        ORDER BY oc.id DESC";

$stmt=$conn->prepare($sql);
if($fx['params']) $stmt->bind_param($fx['types'],...$fx['params']);
$stmt->execute();
$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Órdenes de compra</title>
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- Tu branding y overrides globales -->
    <link rel="stylesheet" href="dist/css/custom.css">
  </head>

  <body class="dashboard-page">
    <section class="content mod-proveedores view-list">
        <div class="form-container" style="width:95%; max-width:1400px;">
            <div class="form-header">
                <i class="fas fa-list-ul fa fa-list-ul"></i> Listado de Ordenes de Compra
            </div>
    </section>
    <div class="wrap">
        <div class="toolbar" class="float: left;" >
        <?php if (($_SESSION['user_externo'] ?? 'N') === 'S'): ?>
          <!-- Externo: va directo -->
          <div class="mb-3 text-left">
            <a class="btn btn-sm btn-primary" href="dashboard.php?page=ordennew&nueva=1"><i class="fas fa-plus mr-1"></i> Nueva orden de compra </a>
          </div>
        <?php else: ?>
          <!-- Interno: pide RFC -->
          <form method="post" action="dashboard.php?page=ordennew" class="inline">
            <div class="mb-3 text-left">
              <input type="hidden" name="nueva" value="1">
              <input id="rfcProv" name="proveedor_rfc" type="text"
                    required maxlength="13"
                    pattern="[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}"
                    oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9&Ñ]/g,'');"
                    placeholder="Ingresa RFC para crear">
              <button class="btn btn-sm btn-primary" type="submit">
                <i class="fas fa-plus mr-1"></i> Nueva orden de compra
              </button>
            </div>
          </form>
        <?php endif; ?>
        </div>
      <?php if (!empty($_POST['err']) && $_POST['err']==='rfc'): ?>
        <div class="alert alert-err">RFC inválido o faltante.</div>
      <?php endif; ?>
        <div class="card">
          <table class="table table-hover table-striped mb-0" id="tabla-proveedores">
            <thead>
              <tr>
                <th>ID</th>
                <th>Folio</th>
                <th>Proveedor RFC</th>
                <th>Fecha</th>
                <th class="text-right">Total</th>
                <th>Estatus</th>
                <th>Estado pago</th>
                <th>Próx. programación</th>
                <th>Últ. pago</th>
                <th>Comprobantes</th>
                <th></th>
                <th></th>
              </tr>
            </thead>
            <tbody>
            <?php if(!$rows): ?>
              <tr><td colspan="11" class="muted">Sin registros.</td></tr>
            <?php else: foreach($rows as $oc): ?>
              <tr>
                <td><?= (int)$oc['id'] ?></td>
                <td><?= e($oc['folio']) ?></td>
                <td><?= e($oc['proveedor_rfc']) ?></td>
                <td><?= e($oc['fecha_emision']) ?></td>
                <td class="text-right"><?= n($oc['total']) ?></td>
                <td><?= e($oc['estatus']) ?></td>
                <td><?= e($oc['estado_pago']) ?></td>
                <td><?= e($oc['proxima_prog'] ?: '-') ?></td>
                <td><?= e($oc['ultima_pago'] ?: '-') ?></td>
                <td>
                  <?php if((int)$oc['cnt_comp']>0): ?>
                    <i class="fa fa-file-pdf" title="Comprobantes: <?= (int)$oc['cnt_comp'] ?>"></i> x <?= (int)$oc['cnt_comp'] ?>
                  <?php else: ?>
                    <span class="muted">–</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a class="btn btn-outline" href="dashboard.php?page=ordenview&oc_id=<?= (int)$oc['id'] ?>"><i class="fa fa-eye"></i> Ver</a>
                </td>
                <td>
                  <?php if (strtoupper($oc['estatus']) !== 'CANCELADA' && (($_SESSION['user_externo'] ?? 'N') !== 'S')): ?>
                    <form method="post" action="dashboard.php?page=ordencan&oc_id=<?= (int)$oc['id']?>" class="inline"
                          onsubmit="return confirm('¿Cancelar la OC #<?= (int)$oc['id'] ?>? Se desasociarán sus facturas.');">
                      <input type="hidden" name="oc_id" value="<?= (int)$oc['id'] ?>">
                      <button type="submit" class="btn btn-danger">
                        <i class="fa fa-ban"></i> Cancelar
                      </button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
    </div>
  </body>
</html>
