<?php
/**
 * Acciones de pago:
 *  - programar: crea fila PROGRAMADO (fecha_programada, monto)
 *  - pagar: crea fila PAGADO (fecha_pagada, monto, metodo, referencia)
 *  - eliminar: borra una fila de oc_pagos (id)
 *  - cambiar_estado: fuerza estado_pago (opcional, si requieren override)
 *
 * Todas las acciones recalculan vía triggers.
 */
session_start();
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['usuario'])) { header('Location: login.php'); exit(); }

$accion = $_POST['accion'] ?? '';
$oc_id  = (int)($_POST['oc_id'] ?? 0);
if ($oc_id<=0) { http_response_code(400); exit('OC inválida'); }

try {
  if ($accion === 'programar') {
    $fecha = trim($_POST['fecha_programada'] ?? '');
    $monto = (float)($_POST['monto_programado'] ?? 0);
    if (!$fecha || $monto<=0) throw new Exception('Datos incompletos');
    $stmt = $conn->prepare("INSERT INTO oc_pagos(oc_id,tipo,fecha_programada,monto,nota,creado_por) VALUES(?, 'PROGRAMADO', ?, ?, ?, ?)");
    $nota = trim($_POST['nota_programada'] ?? '');
    $user = $_SESSION['usuario'];
    $stmt->bind_param('isdss', $oc_id, $fecha, $monto, $nota, $user);
    $stmt->execute(); $stmt->close();
    header("Location: oc_ver.php?oc_id=".$oc_id."&ok=1"); exit();
  }

  if ($accion === 'pagar') {
    $fecha = trim($_POST['fecha_pagada'] ?? '');
    $monto = (float)($_POST['monto_pagado'] ?? 0);
    if (!$fecha || $monto<=0) throw new Exception('Datos incompletos');
    $met   = trim($_POST['metodo'] ?? '');
    $ref   = trim($_POST['referencia'] ?? '');
    $nota  = trim($_POST['nota_pago'] ?? '');
    $user  = $_SESSION['usuario'];
    $stmt = $conn->prepare("INSERT INTO oc_pagos(oc_id,tipo,fecha_pagada,monto,metodo,referencia,nota,creado_por) VALUES(?, 'PAGADO', ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isdssss', $oc_id, $fecha, $monto, $met, $ref, $nota, $user);
    $stmt->execute(); $stmt->close();
    header("Location: oc_ver.php?oc_id=".$oc_id."&ok=1"); exit();
  }

  if ($accion === 'eliminar') {
    $pago_id = (int)($_POST['pago_id'] ?? 0);
    if ($pago_id<=0) throw new Exception('Pago inválido');
    $stmt = $conn->prepare("DELETE FROM oc_pagos WHERE id=? AND oc_id=?");
    $stmt->bind_param('ii', $pago_id, $oc_id);
    $stmt->execute(); $stmt->close();
    header("Location: oc_ver.php?oc_id=".$oc_id."&ok=1"); exit();
  }

  if ($accion === 'cambiar_estado') {
    $estado = $_POST['estado_pago'] ?? 'Pendiente';
    $valid  = ['Pendiente','Proceso de Pago','Pago Parcial','Pagada'];
    if (!in_array($estado,$valid,true)) throw new Exception('Estado inválido');
    /* Si fuerzan 'Pagada' con un monto menor al total, sigue siendo override manual. */
    $stmt = $conn->prepare("UPDATE ordenes_compra SET estado_pago=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param('si', $estado, $oc_id);
    $stmt->execute(); $stmt->close();
    header("Location: oc_ver.php?oc_id=".$oc_id."&ok=1"); exit();
  }

  throw new Exception('Acción no reconocida');
} catch (Throwable $e) {
  header("Location: oc_ver.php?oc_id=".$oc_id."&err=".urlencode($e->getMessage())); exit();
}
