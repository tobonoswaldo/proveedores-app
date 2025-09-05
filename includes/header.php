<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Proveedores</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
<header>
    <div class="contenedor-header">
        <h1>Administración de Proveedores</h1>
        <?php if (isset($_SESSION['usuario'])): ?>
            <nav>
                <a href="dashboard.php">Inicio</a> |
                <a href="proveedores.php">Proveedores</a> |
                <a href="facturas.php">Facturas</a> |
                <a href="pagos.php">Pagos</a> |
                <a href="logout.php">Salir</a>
            </nav>
        <?php endif; ?>
    </div>
    <hr>
</header>
<main>
