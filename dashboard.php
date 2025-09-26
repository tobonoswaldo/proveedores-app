<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'bases/config.php';
include 'bases/funciones.php';
include 'bases/datetime_es.php';


// 🚀 Validación de sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}
/*
$page = $_GET['page'] ?? 'welcome';
$routes = [
  'welcome'            => __DIR__ . 'welcome.php',
  'proveedores'        => __DIR__ . 'programas/listar_proveedores.php',
  // agrega más módulos aquí
];
$file = $routes[$page] ?? $routes['welcome'];
include $file;
*/
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Dashboard | <?php echo $nombreSistema; ?></title>
  <!-- Tell the browser to be responsive to screen width -->
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <!-- Ionicons -->
  <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
  <!-- Tempusdominus Bbootstrap 4 -->
  <link rel="stylesheet" href="plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
  <!-- iCheck -->
  <link rel="stylesheet" href="plugins/icheck-bootstrap/icheck-bootstrap.min.css">
  <!-- JQVMap -->
  <link rel="stylesheet" href="plugins/jqvmap/jqvmap.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <!-- overlayScrollbars -->
  <link rel="stylesheet" href="plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
  <!-- Daterange picker -->
  <link rel="stylesheet" href="plugins/daterangepicker/daterangepicker.css">
  <!-- summernote -->
  <link rel="stylesheet" href="plugins/summernote/summernote-bs4.css">
  <!-- Google Font: Source Sans Pro -->
  <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet">
  <!-- Tu branding y overrides globales -->
  <link rel="stylesheet" href="dist/css/custom.css?v=4">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

  <!-- Navbar -->
  <nav class="main-header navbar navbar-expand navbar-dark">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="dashboard.php" class="nav-link">Inicio</a>
      </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      <li class="nav-item">
        <!--<div class="user-info">-->
        <span class="user-name">
            <i class="fa fa-user"></i> <?php echo $_SESSION['usuario']; ?>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        </span>
        <a href="programas/logout.php" class="logout">
            <i class="fa fa-sign-out-alt"></i>
            Salir&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        </a>
        <!--</div> -->
      </li>
    </ul>
  </nav>
  <!-- /.navbar -->

  <!-- Main Sidebar Container -->
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="index3.html" class="brand-link">
      <img src="imagenes/logo.png" alt="<?php echo $nombreSistema; ?> Logo" class="brand-image img-circle elevation-3"
           style="opacity: .8">
      <span class="brand-text font-weight-light">Receptor</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
      <!-- Sidebar user panel (optional) -->
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <img src="dist/img/user2-160x160.jpg" class="img-circle elevation-2" alt="User Image">
        </div>
        <div class="info">
          <a href="dashboard.php?page=proveedores&editar=<?= urlencode($_SESSION['user_id']) ?>" class="d-block"><?php echo $_SESSION['usuario']; ?></a>
        </div>
      </div>

      <!-- Sidebar Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <!-- Add icons to the links using the .nav-icon class
               with font-awesome or any other icon font library -->
          <li class="nav-item has-treeview menu-open">
            <a href="#" class="nav-link active">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>
                Dashboard
                <i class="right fas fa-angle-left"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="dashboard.php?page=listar_proveedores" class="nav-link">
                  <i class="nav-icon fas fa-copy"></i>
                  <p>Proveedores</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="dashboard.php?page=listar_facturas" class="nav-link">
                  <i class="nav-icon fas fa-copy"></i>
                  <p>Facturas</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="dashboard.php?page=ordenes" class="nav-link">
                  <i class="nav-icon fas fa-book"></i>
                  <p>Ordenes de compra</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="dashboard.php" class="nav-link">
                  <i class="nav-icon fas fa-chart-pie"></i>
                  <p>Tableros</p>
                </a>
              </li>
            </ul>
          </li>
          <li class="nav-header">Documentación</li>
          <li class="nav-item">
            <a href="" class="nav-link">
              <i class="nav-icon fas fa-file"></i>
              <p>Manual(pendiente)</p>
            </a>
          </li>
        </ul>
      </nav>
      <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
  </aside>

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
      <!-- comentado ya que no hace sentido repetir temas -->
      <!--  <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="page-title">Dashboard</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item">Home</li>
              <li class="breadcrumb-item">Inicio</li>
            </ol>
          </div>
        </div>
      -->
      </div>
    </div>

    <!-- /.content-header -->

    <!-- Main content -->
<section class="content"><center>
  <div class="container-fluid" id="app-content">
    <?php
      // Definir que estamos dentro del dashboard (para evitar accesos directos a módulos)
      define('IN_DASHBOARD', true);

      // Router de páginas permitidas
      $routes = [
        'welcome'            => __DIR__ . '/welcome.php',
        'listar_proveedores' => __DIR__ . '/programas/listar_proveedores.php',
        'proveedores'        => __DIR__ . '/programas/proveedores.php',
        'listar_facturas'    => __DIR__ . '/programas/listar_facturas.php',
        'facturas'           => __DIR__ . '/programas/facturas.php',
        'facnew'             => __DIR__ . '/programas/cargar_xml.php',
        'facdel'             => __DIR__ . '/programas/eliminar_factura.php',
        'ordenes'            => __DIR__ . '/programas/ordenes_compra_listar.php',
        'ordennew'           => __DIR__ . '/programas/oc_agregar_facturas.php',
        'ordencan'           => __DIR__ . '/programas/oc_cancelar.php',
        'ordenview'           => __DIR__ . '/programas/oc_ver.php'
        // agrega más módulos aquí
      ];

      // Lee la página solicitada o usa 'welcome'
      $page = $_GET['page'] ?? 'welcome';

      // Incluye el parcial correspondiente (si no existe, vuelve a welcome)
      $file = $routes[$page] ?? $routes['welcome'];
      include $file;
    ?>
  </div><!-- /.container-fluid -->
  </center>
</section>
<!-- /.content -->
  </div>
  <!-- /.content-wrapper -->
  <footer class="main-footer">
    <strong>Copyright &copy; 2024-2025 <a href="">SolucionesIO</a>.</strong>
    All rights reserved.
    <div class="float-right d-none d-sm-inline-block">
      <b>Version</b> 2.0.0
    </div>
  </footer>

  <!-- Control Sidebar -->
  <aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
  </aside>
  <!-- /.control-sidebar -->
</div>
<!-- ./wrapper -->

<!-- jQuery -->
<script src="plugins/jquery/jquery.min.js"></script>
<!-- jQuery UI 1.11.4 -->
<script src="plugins/jquery-ui/jquery-ui.min.js"></script>
<!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->
<script>
  $.widget.bridge('uibutton', $.ui.button)
</script>
<!-- Bootstrap 4 -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- ChartJS -->
<script src="plugins/chart.js/Chart.min.js"></script>
<!-- Sparkline -->
<script src="plugins/sparklines/sparkline.js"></script>
<!-- JQVMap -->
<script src="plugins/jqvmap/jquery.vmap.min.js"></script>
<script src="plugins/jqvmap/maps/jquery.vmap.world.js"></script>
<!-- jQuery Knob Chart -->
<script src="plugins/jquery-knob/jquery.knob.min.js"></script>
<!-- daterangepicker -->
<script src="plugins/moment/moment.min.js"></script>
<script src="plugins/daterangepicker/daterangepicker.js"></script>
<!-- Tempusdominus Bootstrap 4 -->
<script src="plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
<!-- Summernote -->
<script src="plugins/summernote/summernote-bs4.min.js"></script>
<!-- overlayScrollbars -->
<script src="plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
<!-- FastClick -->
<script src="plugins/fastclick/fastclick.js"></script>
<!-- AdminLTE App -->
<script src="dist/js/adminlte.js"></script>
<!-- AdminLTE dashboard demo (This is only for demo purposes) -->
<script src="dist/js/pages/dashboard.js"></script>
<!-- AdminLTE for demo purposes -->
<script src="dist/js/demo.js"></script>
</body>
</html>
