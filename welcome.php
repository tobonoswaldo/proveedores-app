<?php
// welcome.php
// Requiere que $nombreSistema y es_hoy() estén definidos antes del include.
$info = es_hoy();
?>
<table align="center" cellpadding="5" cellspacing="5" width="400">
  <tr>
    <td class="cabecera">
      <div align="center"><img src="imagenes/logo.png" width="400" height="300" border="0" alt="Logo"></div>
    </td>
  </tr>
  <tr>
    <td align="center">
      <div class="welcome-block">
        <h2 class="welcome-title">Bienvenido a <?php echo $nombreSistema; ?></h2>
        <h2 class="welcome-meta"><?= htmlspecialchars($info['saludo']) ."&nbsp;&nbsp;" . $_SESSION['usuario'] ?></h2>
        <div class="welcome-meta">
          <?= ucfirst($info['fecha']) ?> — <?= $info['hora'] ?> hrs
        </div>
      </div>
    </td>
  </tr>
</table>
