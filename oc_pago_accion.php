<?php
// Acciones de pago: agregar y adjuntar comprobante
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$accion = $_POST['accion'] ?? '';
$oc_id  = (int)($_POST['oc_id'] ?? 0);
if ($oc_id<=0) { http_response_code(400); exit('OC inválida'); }

/* Candado por RFC */
$stmt=$conn->prepare("SELECT proveedor_rfc FROM ordenes_compra WHERE id=?");
$stmt->bind_param('i',$oc_id);
$stmt->execute();
$oc=$stmt->get_result()->fetch_assoc();
$stmt->close();
if(!$oc){ http_response_code(404); exit('OC no encontrada'); }
abortIfExternoAndDifferentRFC($oc['proveedor_rfc']);

/* Helper: guardar PDF y devolver ruta relativa */
function guardar_comprobante(array $file, int $ocId, int $pagoId): array {
    if (empty($file['name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['ok'=>false,'msg'=>'Archivo no recibido'];
    }
    $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
    if ($ext!=='pdf') return ['ok'=>false,'msg'=>'El comprobante debe ser PDF'];
    $relDir="cxp/documentos/oc_{$ocId}";
    $absDir=__DIR__."/{$relDir}";
    if (!is_dir($absDir)) { mkdir($absDir,0775,true); }
    $relPath="{$relDir}/pago_{$pagoId}.pdf";
    $absPath=__DIR__."/{$relPath}";
    if (!move_uploaded_file($file['tmp_name'],$absPath)) {
        return ['ok'=>false,'msg'=>'No se pudo guardar el archivo'];
    }
    @chmod($absPath,0644);
    return ['ok'=>true,'ruta'=>$relPath];
}

if ($accion==='agregar_pago') {
    $monto=(float)($_POST['monto'] ?? 0);
    $fprog=trim($_POST['fecha_programada'] ?? '');
    $fpago=trim($_POST['fecha_pago'] ?? '');
    $obs  =trim($_POST['observaciones'] ?? '');
    if ($monto<=0 || $fprog==='') { http_response_code(400); exit('Datos de pago inválidos'); }

    $stmt=$conn->prepare("INSERT INTO oc_pagos (oc_id,monto,fecha_programada,fecha_pago,observaciones)
                          VALUES (?, ?, ?, NULLIF(?,''), ?)");
    $stmt->bind_param('idsss',$oc_id,$monto,$fprog,$fpago,$obs);
    $stmt->execute();
    $pago_id=$stmt->insert_id;
    $stmt->close();

    if (!empty($_FILES['comprobante_pdf']['name'])) {
        $save=guardar_comprobante($_FILES['comprobante_pdf'],$oc_id,$pago_id);
        if ($save['ok']) {
            $ruta=$save['ruta'];
            $up=$conn->prepare("UPDATE oc_pagos SET comprobante_pago_path=? WHERE id=?");
            $up->bind_param('si',$ruta,$pago_id);
            $up->execute();
            $up->close();
        }
        // si falla el PDF, dejamos el pago creado; puedes añadir flash-msg si quieres
    }

    header("Location: oc_ver.php?oc_id={$oc_id}");
    exit();
}

if ($accion==='adjuntar_comprobante') {
    $pago_id=(int)($_POST['pago_id'] ?? 0);
    if ($pago_id<=0) { http_response_code(400); exit('Pago inválido'); }

    $save=guardar_comprobante($_FILES['comprobante_pdf'] ?? [],$oc_id,$pago_id);
    if ($save['ok']) {
        $ruta=$save['ruta'];
        $up=$conn->prepare("UPDATE oc_pagos SET comprobante_pago_path=? WHERE id=?");
        $up->bind_param('si',$ruta,$pago_id);
        $up->execute();
        $up->close();
    }
    header("Location: oc_ver.php?oc_id={$oc_id}");
    exit();
}

http_response_code(400);
echo 'Acción no soportada';
