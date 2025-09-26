<?php
session_start();
include 'bases/config.php';
include 'bases/funciones.php';
$error = null;

// 🚀 Si ya existe sesión activa → redirigir al dashboard
#if (isset($_SESSION['usuario'])) {
#    header("Location: dashboard.php");
#    exit();
#}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Usuario y contraseña requeridos';
    } else {
        // Validación con PASSWORD() en SQL
        $sql = "SELECT id, username, nombre, email, externo FROM usuarios WHERE username=? AND password=PASSWORD(?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            // Variables clásicas que ya usabas
            $_SESSION['usuario'] = $user['nombre'];
            // Variables nuevas para control de acceso global
            $_SESSION['user_id']       = (int)$user['id'];
            $_SESSION['user_nombre']   = $user['nombre'];
            $_SESSION['user_username'] = $user['username'];   // == RFC si así lo definiste
            $_SESSION['user_externo']  = $user['externo'];    // 'S' / 'N'
            $_SESSION['user_rfc']      = $user['username'];   // alias directo
            
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Usuario o contraseña incorrectos";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Login | <?php echo $nombreSistema; ?></title>
  <!-- Tell the browser to be responsive to screen width -->
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <!-- Google Font: Source Sans Pro -->
  <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet">
  <!-- Tu branding y overrides globales -->
  <link rel="stylesheet" href="dist/css/custom.css">
</head>
<body class="hold-transition login-page">
<div class="login-box">
  <div class="login-logo">
				<span style="color: white; font-weight: bold; font-size: 24px; font-family: sans-serif;"><img src="imagenes/logo.png" height="190"></span>
  </div>
  <!-- /.login-logo -->
  <div class="card">
    <div class="card-body login-card-body">
      <p class="login-box-msg">Iniciar Sesion</p>

      <!-- <form action="index3.html" method="POST"> -->
        <form method="POST" >
        <div class="input-group mb-3">
          <input type="RFC" name="username" class="form-control" placeholder="RFC" required>
          <div class="input-group-append">
            <div class="input-group-text">
              <span class="fas fa-envelope"></span>
            </div>
          </div>
        </div>
        <div class="input-group mb-3">
          <input type="password" name="password" class="form-control" placeholder="Contraseña" required>
          <div class="input-group-append">
            <div class="input-group-text">
              <span class="fas fa-lock"></span>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-8">
            <div class="icheck-primary">
              <input type="checkbox" id="remember">
              <label for="remember">
                Recordarmee
              </label>
            </div>
          </div>
          <!-- /.col -->
          <div class="col-4">
            <button type="submit" class="btn btn-primary btn-block btn-flat">Entrar</button>
          </div>
          <!-- /.col -->
        </div>
      </form>
      <?php if(isset($error)) echo "<div class='error'>$error</div>"; ?>
      <p class="mb-1">
        <a href="#">Recuperar contraseña</a>
      </p>
    </div>
    <!-- /.login-card-body -->
  </div>
</div>
<!-- /.login-box -->

<!-- jQuery -->
<script src="plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

</body>
</html>
