<?php

list($whereExterno, $privilegio) = filtroExternoWhere('emisor_rfc');

if (!isset($_SESSION['usuario'])) { header("Location: ../login.php"); exit(); }

$sql = "SELECT id, uuid, emisor_rfc, receptor_rfc, fecha, total, xml_path, pdf_path
        FROM facturas_cxp {$whereExterno}
        ORDER BY fecha DESC, id DESC";
$res = $conn->query($sql);

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Facturas CxP | <?php echo $nombreSistema; ?></title>
  <link rel="stylesheet" href="../css/estilos.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <!-- Tu branding y overrides globales -->
  <link rel="stylesheet" href="dist/css/custom.css">
</head>
<body class="dashboard-page">
  <section class="content mod-proveedores view-list">
    <div class="form-container" style="width:95%; max-width:1400px;">
      <div class="form-header"><i class="fas fa-file-invoice fa fa-file-invoice"></i> Facturas por Pagar</div>

      <div class="mb-3 text-left">
        <a href="dashboard.php?page=facnew" class="btn btn-sm btn-primary"><i class="fas fa-plus mr-1"></i> Nueva factura</a>
      </div>

      <table class="table table-hover table-striped mb-0" id="tabla-proveedores">
        <thead>
          <tr>
            <th>Acciones</th>
            <th>UUID</th>
            <th>Emisor RFC</th>
            <th>Receptor RFC</th>
            <th>Fecha</th>
            <th>Total</th>
            <th>XML</th>
            <th>PDF</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row = $res->fetch_assoc()): ?>
          <tr>
            <td style="white-space:nowrap;">
              <form action="dashboard.php?page=facdel" method="POST" onsubmit="return confirm('¿Eliminar factura y archivos?');" style="display:inline;">
                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                <button type="submit" class="btn-editar" style="background:#dc3545;"><i class="fa fa-trash"></i></button>
              </form>
            </td>
            <td><?php echo htmlspecialchars($row['uuid']); ?></td>
            <td><?php echo htmlspecialchars(strtoupper($row['emisor_rfc'])); ?></td>
            <td><?php echo htmlspecialchars(strtoupper($row['receptor_rfc'])); ?></td>
            <td><?php echo htmlspecialchars($row['fecha']); ?></td>
            <td><?php echo number_format((float)$row['total'], 2); ?></td>
            <td>
              <?php if (!empty($row['xml_path'])): ?>
                <a href="<?php echo htmlspecialchars($row['xml_path']); ?>" target="_blank"><i class="fa fa-file-code"></i></a>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($row['pdf_path'])): ?>
                <a href="<?php echo htmlspecialchars($row['pdf_path']); ?>" target="_blank"><i class="fa fa-file-pdf pdf-icon"></i></a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </section>
</body>
</html>
