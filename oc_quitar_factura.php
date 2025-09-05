<?php
/**
 * Quitar la asociación de una factura a la OC.
 * Redirige al detalle de la OC.
 */
session_start();
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['usuario'])) { header('Location: login.php'); exit(); }

$oc_id   = (int)($_GET['oc_id'] ?? 0);
$fact_id = (int)($_GET['fact_id'] ?? 0);
if ($oc_id<=0 || $fact_id<=0) { http_response_code(400); exit('Parámetros inválidos'); }

$stmt = $conn->prepare("UPDATE facturas_cxp SET orden_compra_id=NULL, orden_compra_folio=NULL WHERE id=? AND orden_compra_id=?");
$stmt->bind_param('ii', $fact_id, $oc_id);
$stmt->execute();
$stmt->close();

/* Los triggers recalculan totales/estatus. */
header("Location: oc_ver.php?oc_id=".$oc_id."&ok=1");
exit();
