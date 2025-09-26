<?php


// 🚀 Validación de sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

list($whereExterno, $privilegio) = filtroExternoWhere('rfc');

// Consulta de proveedores
$sql = "SELECT id, nombre, rfc, telefono, email, direccion,
               constancia_pdf, ine_pdf, acta_pdf, ine_representante_pdf,
               banco_pdf, domicilio_pdf
        FROM proveedores {$whereExterno} ";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Proveedores | <?php echo $nombreSistema; ?></title>
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- Tu branding y overrides globales -->
    <link rel="stylesheet" href="dist/css/custom.css">
</head>
<body class="dashboard-page">
    <section class="content mod-proveedores view-list">
        <div class="form-container" style="width:95%; max-width:1400px;">
            <div class="form-header">
                <i class="fas fa-list-ul fa fa-list-ul"></i> Listado de Proveedores
            </div>
            <?php if($privilegio == 'S'){ ?>
                        <!-- Botón nuevo proveedor -->
                        <div class="mb-3 text-left">
                            <a href="dashboard.php?page=proveedores" class="btn btn-sm btn-primary"><i class="fas fa-plus mr-1"></i> Nuevo Proveedor</a>
                        </div>
            <?php } ?>
    </section>
        <table class="table table-hover table-striped mb-0" id="tabla-proveedores">
            <thead>
                <tr>
                    <th>Acciones</th>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>RFC</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Dirección</th>
                    <th>Constancia</th>
                    <th>INE</th>
                    <th>Acta</th>
                    <th>INE R. L.</th>
                    <th>Banco</th>
                    <th>Domicilio</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()) { ?>
                    <tr>
                        <!-- Botón Editar -->
                        <td>
                            <!-- <a href="dashboard.php?page=proveedores&view=editar&id=<?= urlencode($row['id']) ?>" class="btn-editar"> -->
                            <a href="dashboard.php?page=proveedores&editar=<?= urlencode($row['id']) ?>" class="btn-editar">
                                <i class="fa fa-edit"></i>
                            </a>
                        </td>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($row['rfc']); ?></td>
                        <td><?php echo htmlspecialchars($row['telefono']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['direccion']); ?></td>
                        <!-- Documentos PDF con ícono -->
                        <td align="center">
                            <?php if($row['constancia_pdf']) { ?>
                                <a href="<?php echo $row['constancia_pdf']; ?>" target="_blank">
                                    <i class="fa fa-file-pdf pdf-icon"></i>
                                </a>
                            <?php } ?>
                        </td>
                        <td align="center">
                            <?php if($row['ine_pdf']) { ?>
                                <a href="<?php echo $row['ine_pdf']; ?>" target="_blank">
                                    <i class="fa fa-file-pdf pdf-icon"></i>
                                </a>
                            <?php } ?>
                        </td>
                        <td align="center">
                            <?php if($row['acta_pdf']) { ?>
                                <a href="<?php echo $row['acta_pdf']; ?>" target="_blank">
                                    <i class="fa fa-file-pdf pdf-icon"></i>
                                </a>
                            <?php } ?>
                        </td>
                        <td align="center">
                            <?php if($row['ine_representante_pdf']) { ?>
                                <a href="<?php echo $row['ine_representante_pdf']; ?>" target="_blank">
                                    <i class="fa fa-file-pdf pdf-icon"></i>
                                </a>
                            <?php } ?>
                        </td>
                        <td align="center">
                            <?php if($row['banco_pdf']) { ?>
                                <a href="<?php echo $row['banco_pdf']; ?>" target="_blank">
                                    <i class="fa fa-file-pdf pdf-icon"></i>
                                </a>
                            <?php } ?>
                        </td>
                        <td align="center">
                            <?php if($row['domicilio_pdf']) { ?>
                                <a href="<?php echo $row['domicilio_pdf']; ?>" target="_blank">
                                    <i class="fa fa-file-pdf pdf-icon"></i>
                                </a>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

</body>
</html>
