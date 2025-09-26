<?php
/**
 * eliminar_factura.php
 * ------------------------------------------------------------------
 * - Recibe el ID de la factura (POST o GET).
 * - Busca rutas de XML/PDF en BD.
 * - Elimina el registro de facturas_cxp.
 * - Borra del disco los archivos relacionados (si existen).
 * - Redirige a listar_facturas.php (siempre).
 * 
 * Requiere:
 *  - $_SESSION['usuario'] para control de acceso.
 *  - funciones.php con: cxp_abs_from_rel() y borrar_archivo_cxp().
 */


// 1) Seguridad básica: sesión activa
if (!isset($_SESSION['usuario'])) {
    header("Location: ../login.php");
    exit();
}

// 2) Tomar el ID (acepta POST o GET)
$id = isset($_POST['id']) ? (int)$_POST['id'] : (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: listar_facturas.php");
    exit();
}

// 3) Obtener rutas (web-relativas) de XML y PDF antes de borrar
$xmlRel = $pdfRel = null;

$stmt = $conn->prepare("SELECT xml_path, pdf_path FROM facturas_cxp WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res && $res->num_rows === 1) {
    $row = $res->fetch_assoc();
    $xmlRel = $row['xml_path'] ?? null;
    $pdfRel = $row['pdf_path'] ?? null;
}
$stmt->close();

// 4) Eliminar el registro de BD
$stmt = $conn->prepare("DELETE FROM facturas_cxp WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

// 5) Intentar borrar archivos del disco (best effort)
//    Convertimos de ruta web-relativa -> absoluta y borramos solo dentro de cxp/documentos.
$xmlAbs = $xmlRel ? cxp_abs_from_rel($xmlRel) : null;
$pdfAbs = $pdfRel ? cxp_abs_from_rel($pdfRel) : null;

if ($xmlAbs) { borrar_archivo_cxp($xmlAbs); }
if ($pdfAbs) { borrar_archivo_cxp($pdfAbs); }

// 6) Redirigir al listado
#header("Location: listar_facturas.php");
echo '<script>location.href='.json_encode('dashboard.php?page=listar_facturas').';</script>';
exit();
