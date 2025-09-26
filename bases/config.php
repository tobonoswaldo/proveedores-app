<?php
//¿Donde estamos?
$produccion = 'N';

// ===== CONFIGURACIÓN DE LA BASE DE DATOS =====
if($produccion == 'S'){
    $host = 'localhost';
    $user = 'provapp';
    $pass = 'TuClaveFuerte!';   // ajusta
    $db   = 'proveedoresapp';
    $nombreSistema = 'Receptor';
}else{
    $host = "127.0.0.1";
    $user = "root";
    $pass = "NuevaPass123!"; 
    $db   = "proveedoresDB";
    $nombreSistema = 'Pruebas';
}

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { die('Error de conexión: ' . $conn->connect_error); }
$conn->set_charset('utf8mb4');

// RUTA BASE WEB para construir enlaces (¡importante!)
$GLOBALS['APP_WEB_BASE'] = '/template';
//============== PRODUCCION ==============//

?>