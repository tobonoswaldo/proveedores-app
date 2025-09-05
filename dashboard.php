<?php
session_start();
include 'config.php';

// 🚀 Validación de sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | <?php echo $nombreSistema; ?></title>
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script>
        // Alternar acordeón
        function toggleMenu() {
            document.querySelector(".sidebar").classList.toggle("active");
        }

        // Cargar un módulo dentro del contenedor dinámico
        function cargarModulo(ruta) {
            document.getElementById("contenedor").src = ruta;
            // 🚀 Cierra menú automáticamente al seleccionar opción
            document.querySelector(".sidebar").classList.remove("active");
        }
    </script>
</head>
<body class="dashboard-page">
    <!-- Header -->
    <header class="main-header">
        <div class="menu-logo">
            <div class="menu-icon" onclick="toggleMenu()">
                <i class="fa fa-bars"></i>
            </div>
            <div class="logo"><?php echo $nombreSistema; ?></div>
        </div>
        <div class="user-info">
        <span class="user-name">
            <i class="fa fa-user"></i> <?php echo $_SESSION['usuario']; ?>
        </span>
        <a href="logout.php" class="logout">
            <i class="fa fa-sign-out-alt"></i>
            Salir&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        </a>
    </div>
    </header>
    <!-- Sidebar tipo acordeón -->
    <nav class="sidebar">
        <ul>
            <li><a href="#" onclick="cargarModulo('inicio.php')"><i class="fa fa-home"></i> Inicio</a></li>
            <li><a href="#" onclick="cargarModulo('listar_proveedores.php')"><i class="fa fa-truck"></i> Proveedores</a></li>
            <li><a href="#" onclick="cargarModulo('cxp/listar_facturas.php')"><i class="fa fa-file-invoice-dollar"></i> Facturas</a></li>
            <li><a href="#" onclick="cargarModulo('ordenes_compra_listar.php')"><i class="fa fa-credit-card"></i> Ordenes de Compra</a></li>
            <li><a href="logout.php"><i class="fa fa-sign-out-alt"></i> Salir</a></li>
        </ul>
    </nav>

    <!-- Contenido dinámico -->
    <main class="content">
        <iframe id="contenedor" src="inicio.php" frameborder="0"></iframe>
    </main>

    <!-- Footer -->
    <footer class="main-footer">
        <p>&copy; <?php echo date("Y"); ?> - <?php echo $nombreSistema; ?></p>
    </footer>
</body>
</html>
