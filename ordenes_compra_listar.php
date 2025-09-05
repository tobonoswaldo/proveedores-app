<?php
/**
 * Listado de Órdenes de Compra + alta rápida
 * - Crea OC (cabecera mínima) y redirige a oc_agregar_facturas.php
 * - Muestra acción “Agregar facturas” SOLO si la OC NO está CERRADA
 */

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bases/funciones.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php'); exit();
}

/* Alta rápida de OC */
$msgErr = null;
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['crear_oc'])) {
    $proveedor_rfc = strtoupper(trim($_POST['proveedor_rfc'] ?? ''));
    $folio_input   = trim($_POST['folio'] ?? '');
    $fecha_emision = trim($_POST['fecha_emision'] ?? date('Y-m-d'));

    if (!validar_rfc_mex($proveedor_rfc)) {
        $msgErr = 'RFC de proveedor inválido.';
        goto LISTAR;
    }
    $folio = ($folio_input !== '') ? $folio_input : ('OC-'.date('Ymd-His'));

    $sql = "INSERT INTO ordenes_compra
            (folio, proveedor_rfc, fecha_emision, moneda, tipo_cambio,
             subtotal, descuento_total, impuestos_tras, impuestos_ret, total, estatus)
            VALUES (?, ?, ?, 'MXN', NULL, 0, 0, 0, 0, 0, 'BORRADOR')";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { $msgErr = 'Error preparando SQL: '.$conn->error; goto LISTAR; }
    $stmt->bind_param('sss', $folio, $proveedor_rfc, $fecha_emision);
    if (!$stmt->execute()) {
        $msgErr = ($conn->errno==1062) ? 'Folio duplicado para ese proveedor.' : ('Error al crear OC: '.$conn->error);
        $stmt->close(); goto LISTAR;
    }
    $newId = $stmt->insert_id;
    $stmt->close();

    header('Location: oc_agregar_facturas.php?oc_id='.(int)$newId);
    exit();
}

LISTAR:
/* Búsqueda y listado */
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $like = '%'.$q.'%';
    $stmt = $conn->prepare("SELECT id, folio, proveedor_rfc, fecha_emision, moneda, total, estatus
                            FROM ordenes_compra
                            WHERE folio LIKE ? OR proveedor_rfc LIKE ?
                            ORDER BY id DESC LIMIT 500");
    $stmt->bind_param('ss', $like, $like);
} else {
    $stmt = $conn->prepare("SELECT id, folio, proveedor_rfc, fecha_emision, moneda, total, estatus
                            FROM ordenes_compra
                            ORDER BY id DESC LIMIT 500");
}
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function fmt($n){ return number_format((float)$n, 2); }
function badgeClass($e){
  return ['BORRADOR'=>'badge-draft','ABIERTA'=>'badge-open','PARCIAL'=>'badge-partial','CERRADA'=>'badge-closed'][$e] ?? 'badge-draft';
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Órdenes de Compra</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="css/estilos.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="dashboard-page">
<div class="wrap">

  <div class="card">
    <h2>Órdenes de Compra</h2>

    <?php if ($msgErr): ?><div class="alert alert-err"><?= htmlspecialchars($msgErr) ?></div><?php endif; ?>

    <div class="toolbar">
      <!-- Buscador -->
      <form method="get">
        <input class="search" type="text" name="q" placeholder="Buscar por folio o RFC…" value="<?= htmlspecialchars($q) ?>">
        <button class="btn btn-outline" type="submit"><i class="fa fa-search"></i> Buscar</button>
      </form>
      <!-- Alta rápida -->
      <form method="post" style="display:flex;gap:8px;align-items:center">
        <input type="hidden" name="crear_oc" value="1">
        <input type="text" name="proveedor_rfc" placeholder="RFC proveedor" required style="max-width:180px">
        <input type="text" name="folio" placeholder="Folio (opcional)" style="max-width:180px">
        <input type="date" name="fecha_emision" value="<?= date('Y-m-d') ?>" style="max-width:160px">
        <button class="btn btn-primary" type="submit"><i class="fa fa-plus"></i> Nueva orden de compra</button>
      </form>
    </div>
  </div>

  <div class="card">
    <table class="tbl">
      <thead>
      <tr>
        <th>ID</th><th>Folio</th><th>Proveedor RFC</th><th>Fecha</th><th>Moneda</th>
        <th class="text-right">Total</th><th>Estado</th><th>Acciones</th>
      </tr>
      </thead>
      <tbody>
      <?php if(!$rows): ?>
        <tr><td colspan="8" class="muted">Sin órdenes de compra.</td></tr>
      <?php else: foreach($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= htmlspecialchars($r['folio']) ?></td>
          <td><?= htmlspecialchars($r['proveedor_rfc']) ?></td>
          <td><?= htmlspecialchars($r['fecha_emision']) ?></td>
          <td><?= htmlspecialchars($r['moneda']) ?></td>
          <td class="text-right"><?= fmt($r['total']) ?></td>
          <td><span class="badge-pill <?= badgeClass($r['estatus']) ?>"><?= htmlspecialchars($r['estatus']) ?></span></td>
          <td>
            <a class="btn btn-outline" href="oc_ver.php?oc_id=<?= (int)$r['id'] ?>"><i class="fa fa-eye"></i> Ver detalle</a>
            <?php if ($r['estatus'] !== 'CERRADA'): ?>
              <a class="btn btn-outline" href="oc_agregar_facturas.php?oc_id=<?= (int)$r['id'] ?>"><i class="fa fa-file-invoice"></i> Agregar facturas</a>
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
