<?php
// oc_cancelar.php — Cancelar OC y desasociar facturas (con historial)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

// Solo internos pueden cancelar
if (($_SESSION['user_externo'] ?? 'N') === 'S') {
    http_response_code(403);
    exit('No autorizado para cancelar órdenes de compra.');
}

$oc_id = (int)($_POST['oc_id'] ?? 0);
if ($oc_id <= 0) { http_response_code(400); exit('OC inválida'); }

$conn->begin_transaction();

try {
    // Bloquear cabecera
    $stmt = $conn->prepare("SELECT id, estatus FROM ordenes_compra WHERE id=? FOR UPDATE");
    $stmt->bind_param('i', $oc_id);
    $stmt->execute();
    $oc = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$oc) { throw new Exception('OC no encontrada'); }
    if (strtoupper($oc['estatus']) === 'CANCELADA') {
        // Ya cancelada; idempotente
        $conn->commit();
        header("Location: oc_ver.php?oc_id={$oc_id}");
        exit();
    }

    // Traer facturas asociadas (y bloquear filas)
    $q = "SELECT id, uuid, emisor_rfc, receptor_rfc, total, fecha
          FROM facturas_cxp WHERE orden_compra_id=? FOR UPDATE";
    $stmt = $conn->prepare($q);
    $stmt->bind_param('i', $oc_id);
    $stmt->execute();
    $facturas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Insertar historial de facturas desasociadas
    if ($facturas) {
        $ins = $conn->prepare("INSERT INTO oc_facturas_historial
            (oc_id, factura_id, uuid, emisor_rfc, receptor_rfc, total, fecha, motivo)
            VALUES (?,?,?,?,?,?,?,?)");
        $motivo = 'Cancelación de OC';
        foreach ($facturas as $f) {
            $fid   = (int)$f['id'];
            $uuid  = $f['uuid'] ?? null;
            $erfc  = $f['emisor_rfc'] ?? null;
            $rrfc  = $f['receptor_rfc'] ?? null;
            $total = $f['total'] ?? null;
            $fecha = $f['fecha'] ?? null;
            $ins->bind_param('iisssdss', $oc_id, $fid, $uuid, $erfc, $rrfc, $total, $fecha, $motivo);
            $ins->execute();
        }
        $ins->close();
    }

    // Desasociar facturas
    $up = $conn->prepare("UPDATE facturas_cxp SET orden_compra_id=NULL WHERE orden_compra_id=?");
    $up->bind_param('i', $oc_id);
    $up->execute();
    $up->close();

    // Marcar OC cancelada + fecha
    $upd = $conn->prepare("UPDATE ordenes_compra
                           SET estatus='CANCELADA', fecha_cancelacion=CURDATE()
                           WHERE id=?");
    $upd->bind_param('i', $oc_id);
    $upd->execute();
    $upd->close();

    $conn->commit();
    header("Location: oc_ver.php?oc_id={$oc_id}");
    exit();

} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo "Error al cancelar: " . $e->getMessage();
}
