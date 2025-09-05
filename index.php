<?php
/* Archivo: index.php
   Descripción: Punto de entrada principal del sistema.
   - Si el usuario tiene sesión → redirige al dashboard.
   - Si no tiene sesión → redirige al login.
*/

session_start();

// Si el usuario ya está logueado → Dashboard
if (isset($_SESSION['usuario'])) {
    header("Location: dashboard.php");
    exit();
}

// Si no hay sesión → Login
header("Location: login.php");
exit();
?>
