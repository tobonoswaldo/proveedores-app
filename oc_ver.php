<?php
/**
 * oc_ver.php
 * -----------------------------------------------------------------------------
 * Detalle de Orden de Compra:
 *  - Muestra cabecera, renglones y facturas asociadas
 *  - Panel de pagos (programar pago, registrar pago, cambiar estatus de pago)
 *  - Acciones: Editar OC, Agregar facturas, Regresar
 *
 * Requisitos de BD (previos):
 *  - Tabla ordenes_compra con columnas: estatus, estado_pago, fecha_programacion_pago,
 *    fecha_pagada, monto_pagado, subtotal, impuestos_tras, impuestos_ret, total, etc.
 *  - Tabla ordenes_compra_detalle con importes calculados por renglón.
 *  - Tabla facturas_cxp con vínculos a orden_compra_id y docs (xml_path, pdf_path).
 *  - Tabla oc_pagos para histórico de pagos (PROGRAMADO y PAGADO).
 *  - SP/Triggers que recalculan totales/estatus operativos y de pago automáticamente.
 */

session_start();
require_once __DIR__ . '/config.php';

// -----------------------------------------------------------------------------
// Seguridad básica
// -----------------------------------------------------------------------------
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

// -----------------------------------------------------------------------------
// Parámetros
// -----------------------------------------------------------------------------
$oc_id = isset($_GET['oc_id']) ? (int)$_GET['oc_id'] : 0;
if ($oc_id <= 0) {
    http_response_code(400);
    exit('OC inválida.');
}

// -----------------------------------------------------------------------------
// Cabecera de la OC
// -----------------------------------------------------------------------------
$sqlOc = "SELECT id, folio, proveedor_rfc, fecha_emision, fecha_entrega, moneda, tipo_cambio,
                 subtotal, descuento_total, impuestos_tras, impuestos_ret, total, estatus,
                 estado_pago, fecha_programacion_pago, fecha_pagada, monto_pagado, observaciones
          FROM ordenes_compra
          WHERE id = ?";
$stmt = $conn->prepare($sqlOc);
$stmt->bind_param('i', $oc_id);
$stmt->execute();
$oc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$oc) {
    http_response_code(404);
    exit('Orden de compra no encontrada.');
}

// -----------------------------------------------------------------------------
// Detalle (renglones) de la OC
// -----------------------------------------------------------------------------
$sqlDet = "SELECT renglon, clave_prod_serv, descripcion, unidad_clave, unidad_desc,
                  cantidad, precio_unitario, importe_descuento, importe_base,
                  imp_trasladados, imp_retenidos, importe_neto
           FROM ordenes_compra_detalle
           WHERE oc_id = ?
           ORDER BY renglon ASC";
$stmt = $conn->prepare($sqlDet);
$stmt->bind_param('i', $oc_id);
$stmt->execute();
$det = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Totales calculados desde renglones (para comparación visual)
$calc_base = $calc_tras = $calc_ret = $calc_total = 0.0;
foreach ($det as $r) {
    $calc_base  += (float)$r['importe_base'];
    $calc_tras  += (float)$r['imp_trasladados'];
    $calc_ret   += (float)$r['imp_retenidos'];
    $calc_total += (float)$r['importe_neto'];
}
$descuadre = abs($calc_total - (float)$oc['total']) > 0.01;

// -----------------------------------------------------------------------------
// Facturas asociadas a la OC
// -----------------------------------------------------------------------------
$sqlFx = "SELECT id, uuid, fecha, total, xml_path, pdf_path
          FROM facturas_cxp
          WHERE orden_compra_id = ?
          ORDER BY fecha DESC, id DESC";
$stmt = $conn->prepare($sqlFx);
$stmt->bind_param('i', $oc_id);
$stmt->execute();
$fx = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$fact_total = 0.0;
foreach ($fx as $f) { $fact_total += (float)$f['total']; }

// -----------------------------------------------------------------------------
// Pagos / Programaciones de pago
// -----------------------------------------------------------------------------
$sqlPg = "SELECT id, tipo, fecha_programada, fecha_pagada, monto, metodo, referencia, nota, created_at, creado_por
          FROM oc_pagos
          WHERE oc_id = ?
          ORDER BY created_at DESC, id DESC";
$stmt = $conn->prepare($sqlPg);
$stmt->bind_param('i', $oc_id);
$stmt->execute();
$pagos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// -----------------------------------------------------------------------------
// Helpers de formato
// -----------------------------------------------------------------------------
function fmt($n) { return number_format((float)$n, 2); }

function badge_estatus($e) {
    $map = [
        'BORRADOR' => 'badge-draft',
        'ABIERTA'  => 'badge-open',
        'PARCIAL'  => 'badge-partial',
        'CERRADA'  => 'badge-closed'
    ];
    return $map[$e] ?? 'badge-draft';
}
function badge_pago($e) {
    $map = [
        'Pendiente'       => 'badge-draft',
        'Proceso de Pago' => 'badge-open',
        'Pago Parcial'    => 'badge-partial',
        'Pagada'          => 'badge-closed'
    ];
    return $map[$e] ?? 'badge-draft';
}

// Reglas de UI: bloquear agregar facturas si OC cerrada o ya pagada
$bloquearAgregarFacturas = ($oc['estatus'] === 'CERRADA') || ($oc['estado_pago'] === 'Pagada');

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>OC <?= htmlspecialchars($oc['folio']) ?> | <?= htmlspecialchars($nombreSistema ?? 'Sistema') ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="css/estilos.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="dashboard-page">
<div class="wrap">

  <!-- Mensajes -->
  <?php if (!empty($_GET['err'])): ?>
    <div class="alert alert-err"><?= htmlspecialchars($_GET['err']) ?></div>
  <?php elseif (!empty($_GET['ok'])): ?>
    <div class="alert alert-ok">Operación realizada correctamente.</div>
  <?php endif; ?>

  <!-- Cabecera OC -->
  <div class="card">
    <h2>OC: <strong><?= htmlspecialchars($oc['folio']) ?></strong></h2>
    <div class="muted">
      Proveedor RFC: <strong><?= htmlspecialchars($oc['proveedor_rfc']) ?></strong>
      · Emisión: <?= htmlspecialchars($oc['fecha_emision']) ?>
      <?php if (!empty($oc['fecha_entrega'])): ?> · Entrega: <?= htmlspecialchars($oc['fecha_entrega']) ?><?php endif; ?>
      · Estatus: <span class="badge-pill <?= badge_estatus($oc['estatus']) ?>"><?= htmlspecialchars($oc['estatus']) ?></span>
      · Estatus de pago: <span class="badge-pill <?= badge_pago($oc['estado_pago']) ?>"><?= htmlspecialchars($oc['estado_pago']) ?></span>
    </div>

    <div class="toolbar">
      <a class="btn btn-outline" href="ordenes_compra_listar.php"><i class="fa fa-arrow-left"></i> Regresar</a>
      <a class="btn btn-outline" href="oc_editar.php?oc_id=<?= (int)$oc_id ?>"><i class="fa fa-pen-to-square"></i> Editar OC</a>
      <?php if (!$bloquearAgregarFacturas): ?>
        <a class="btn btn-primary" href="oc_agregar_facturas.php?oc_id=<?= (int)$oc_id ?>"><i class="fa fa-file-invoice"></i> Agregar facturas</a>
      <?php endif; ?>
    </div>

    <?php if (!empty($oc['observaciones'])): ?>
      <div class="badge"><?= nl2br(htmlspecialchars($oc['observaciones'])) ?></div>
    <?php endif; ?>
  </div>

  <!-- Detalle de la OC -->
  <div class="card">
    <h3>Detalle</h3>
    <table class="tbl">
      <thead>
        <tr>
          <th>#</th>
          <th>Clave</th>
          <th>Descripción</th>
          <th>Unidad</th>
          <th class="text-right">Cantidad</th>
          <th class="text-right">P.Unit</th>
          <th class="text-right">Desc.</th>
          <th class="text-right">Base</th>
          <th class="text-right">Trasl.</th>
          <th class="text-right">Ret.</th>
          <th class="text-right">Importe</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$det): ?>
        <tr><td colspan="11" class="muted">Sin renglones.</td></tr>
      <?php else: foreach ($det as $r): ?>
        <tr>
          <td><?= (int)$r['renglon'] ?></td>
          <td><?= htmlspecialchars($r['clave_prod_serv']) ?></td>
          <td><?= htmlspecialchars($r['descripcion']) ?></td>
          <td><?= htmlspecialchars($r['unidad_desc'] ?: $r['unidad_clave']) ?></td>
          <td class="text-right"><?= number_format((float)$r['cantidad'], 4) ?></td>
          <td class="text-right"><?= number_format((float)$r['precio_unitario'], 6) ?></td>
          <td class="text-right"><?= fmt($r['importe_descuento']) ?></td>
          <td class="text-right"><?= fmt($r['importe_base']) ?></td>
          <td class="text-right"><?= fmt($r['imp_trasladados']) ?></td>
          <td class="text-right"><?= fmt($r['imp_retenidos']) ?></td>
          <td class="text-right"><strong><?= fmt($r['importe_neto']) ?></strong></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="7" class="text-right">Totales (cálculo líneas)</th>
          <th class="text-right"><?= fmt($calc_base) ?></th>
          <th class="text-right"><?= fmt($calc_tras) ?></th>
          <th class="text-right"><?= fmt($calc_ret) ?></th>
          <th class="text-right"><?= fmt($calc_total) ?></th>
        </tr>
        <tr>
          <th colspan="7" class="text-right">Totales (cabecera)</th>
          <th class="text-right"><?= fmt($oc['subtotal']) ?></th>
          <th class="text-right"><?= fmt($oc['impuestos_tras']) ?></th>
          <th class="text-right"><?= fmt($oc['impuestos_ret']) ?></th>
          <th class="text-right"><?= fmt($oc['total']) ?></th>
        </tr>
      </tfoot>
    </table>

    <?php if ($descuadre): ?>
      <div class="alert alert-err">Los totales calculados por renglón no coinciden con los de cabecera.</div>
    <?php endif; ?>
  </div>

  <!-- Facturas asociadas -->
  <div class="card">
    <h3>Facturas asociadas</h3>
    <table class="tbl">
      <thead>
        <tr>
          <th>UUID</th>
          <th>Fecha</th>
          <th class="text-right">Total</th>
          <th>Docs</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$fx): ?>
        <tr><td colspan="4" class="muted">Sin facturas asociadas.</td></tr>
      <?php else: foreach ($fx as $f): ?>
        <tr>
          <td><?= htmlspecialchars($f['uuid']) ?></td>
          <td><?= htmlspecialchars($f['fecha']) ?></td>
          <td class="text-right"><?= fmt($f['total']) ?></td>
          <td>
            <?php if (!empty($f['xml_path'])): ?>
              <a href="<?= htmlspecialchars($f['xml_path']) ?>" target="_blank" title="XML"><i class="fa fa-file-code"></i></a>
            <?php endif; ?>
            <?php if (!empty($f['pdf_path'])): ?>
              &nbsp;<a href="<?= htmlspecialchars($f['pdf_path']) ?>" target="_blank" title="PDF"><i class="fa fa-file-pdf"></i></a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="2" class="text-right">Total facturado</th>
          <th class="text-right"><?= fmt($fact_total) ?></th>
          <th></th>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- Panel de pagos -->
  <div class="card">
    <h3>Pagos</h3>

    <!-- Resumen de pago -->
    <div class="toolbar">
      <div class="badge">Total OC: <strong><?= fmt($oc['total']) ?></strong></div>
      <div class="badge">Pagado: <strong><?= fmt($oc['monto_pagado']) ?></strong></div>
      <div class="badge">Pendiente: <strong><?= fmt(max(0, (float)$oc['total'] - (float)$oc['monto_pagado'])) ?></strong></div>
      <div class="badge">Estatus de pago: <span class="badge-pill <?= badge_pago($oc['estado_pago']) ?>"><?= htmlspecialchars($oc['estado_pago']) ?></span></div>
      <?php if (!empty($oc['fecha_programacion_pago'])): ?>
        <div class="badge">Prog. pago: <?= htmlspecialchars($oc['fecha_programacion_pago']) ?></div>
      <?php endif; ?>
      <?php if (!empty($oc['fecha_pagada'])): ?>
        <div class="badge">Fecha pagada: <?= htmlspecialchars($oc['fecha_pagada']) ?></div>
      <?php endif; ?>
    </div>

    <!-- Cambiar estatus (override opcional) -->
    <form method="post" action="oc_pago_accion.php" class="toolbar">
      <input type="hidden" name="accion" value="cambiar_estado">
      <input type="hidden" name="oc_id" value="<?= (int)$oc_id ?>">
      <select name="estado_pago">
        <?php foreach (['Pendiente','Proceso de Pago','Pago Parcial','Pagada'] as $e): ?>
          <option value="<?= $e ?>" <?= $oc['estado_pago']===$e ? 'selected' : '' ?>><?= $e ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-outline" type="submit"><i class="fa fa-flag"></i> Cambiar estatus</button>
    </form>

    <!-- Programar pago -->
    <form method="post" action="oc_pago_accion.php" class="toolbar">
      <input type="hidden" name="accion" value="programar">
      <input type="hidden" name="oc_id" value="<?= (int)$oc_id ?>">
      <input type="date" name="fecha_programada" required>
      <input type="number" step="0.01" min="0" name="monto_programado" placeholder="Monto" required>
      <input type="text" name="nota_programada" placeholder="Nota (opcional)" class="w-2">
      <button class="btn btn-outline" type="submit"><i class="fa fa-calendar-plus"></i> Programar</button>
    </form>

    <!-- Registrar pago -->
    <form method="post" action="oc_pago_accion.php" class="toolbar">
      <input type="hidden" name="accion" value="pagar">
      <input type="hidden" name="oc_id" value="<?= (int)$oc_id ?>">
      <input type="date" name="fecha_pagada" required>
      <input type="number" step="0.01" min="0" name="monto_pagado" placeholder="Monto" required>
      <input type="text" name="metodo" placeholder="Método (Transferencia, etc.)">
      <input type="text" name="referencia" placeholder="Referencia">
      <input type="text" name="nota_pago" placeholder="Nota (opcional)" class="w-2">
      <button class="btn btn-primary" type="submit"><i class="fa fa-money-bill"></i> Guardar pago</button>
    </form>

    <!-- Histórico de programaciones/pagos -->
    <table class="tbl">
      <thead>
        <tr>
          <th>Tipo</th>
          <th>Fecha programada</th>
          <th>Fecha pagada</th>
          <th class="text-right">Monto</th>
          <th>Método</th>
          <th>Referencia</th>
          <th>Nota</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$pagos): ?>
        <tr><td colspan="8" class="muted">Sin registros de pago.</td></tr>
      <?php else: foreach ($pagos as $p): ?>
        <tr>
          <td><?= htmlspecialchars($p['tipo']) ?></td>
          <td><?= htmlspecialchars($p['fecha_programada'] ?: '') ?></td>
          <td><?= htmlspecialchars($p['fecha_pagada'] ?: '') ?></td>
          <td class="text-right"><?= fmt($p['monto']) ?></td>
          <td><?= htmlspecialchars($p['metodo'] ?: '') ?></td>
          <td><?= htmlspecialchars($p['referencia'] ?: '') ?></td>
          <td><?= htmlspecialchars($p['nota'] ?: '') ?></td>
          <td>
            <form method="post" action="oc_pago_accion.php" onsubmit="return confirm('¿Eliminar registro?')">
              <input type="hidden" name="accion" value="eliminar">
              <input type="hidden" name="oc_id" value="<?= (int)$oc_id ?>">
              <input type="hidden" name="pago_id" value="<?= (int)$p['id'] ?>">
              <button class="btn btn-danger" type="submit" title="Eliminar"><i class="fa fa-trash"></i></button>
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
