<?php
session_start();
include 'config.php';
$error = null;

// 🚀 Si ya existe sesión activa → redirigir al dashboard
if (isset($_SESSION['usuario'])) {
    header("Location: dashboard.php");
    exit();
}

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
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login | <?php echo $nombreSistema; ?></title>
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="avatar">
            <i class="fa fa-user"></i>
        </div>
        <h2>Iniciar Sesión</h2>
        <form method="POST">
            <div class="input-group">
                <i class="fa fa-envelope"></i>
                <input type="username" name="username" placeholder="RFC" required>
            </div>
            <div class="input-group">
                <i class="fa fa-lock"></i>
                <input type="password" name="password" placeholder="Contraseña" required>
            </div>
            <div class="options">
                <label><input type="checkbox" name="remember"> Recordarme</label>
                <a href="#">¿Olvidaste tu contraseña?</a>
            </div>
            <button type="submit">Login</button>
        </form>
        <?php if(isset($error)) echo "<div class='error'>$error</div>"; ?>
    </div>
</body>
</html>
