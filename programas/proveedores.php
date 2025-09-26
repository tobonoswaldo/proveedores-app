<?php
/* Archivo: proveedores.php
   Descripción: Alta y edición de proveedores con validaciones y carga de PDFs.
   - Modo nuevo: formulario vacío, PDFs forzosos (excepto INE representante).
   - Modo edición: precarga datos; si no subes nuevos PDFs, conserva los actuales.
   - Tras guardar/actualizar: redirige a listar_proveedores.php
*/

error_reporting(E_ALL);
ini_set('display_errors', 1);
// 🚀 Validación de sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$idEditar    = isset($_GET['editar']) ? intval($_GET['editar']) : 0;
$modoEdicion = $idEditar > 0;

// ===== Variables base =====
$nombre = $rfc = $telefono = $email = $direccion = "";

// Rutas actuales de documentos (se preservan si no se sube uno nuevo)
$constancia_pdf = $ine_pdf = $acta_pdf = $ine_representante_pdf = $banco_pdf = $domicilio_pdf = "";

// ===== Si es edición, cargar datos existentes =====
if ($modoEdicion) {
    $sql = "SELECT * FROM proveedores WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idEditar);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 1) {
        $row = $res->fetch_assoc();
        $nombre                 = $row['nombre'];
        $rfc                    = $row['rfc'];
        $telefono               = $row['telefono'];
        $email                  = $row['email'];
        $direccion              = $row['direccion'];
        $constancia_pdf         = $row['constancia_pdf'];
        $ine_pdf                = $row['ine_pdf'];
        $acta_pdf               = $row['acta_pdf'];
        $ine_representante_pdf  = $row['ine_representante_pdf'];
        $banco_pdf              = $row['banco_pdf'];
        $domicilio_pdf          = $row['domicilio_pdf'];
    }
    $stmt->close();
}

// ===== Procesar formulario =====
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['guardar'])) {

    // Campos base
    $nombre    = trim($_POST['nombre']);
    $rfc       = strtoupper(trim($_POST['rfc']));
    $telefono  = trim($_POST['telefono']);
    $email     = trim($_POST['email']);
    $direccion = trim($_POST['direccion']);

    // === VALIDACIONES ===
    if ($nombre === "") { die("Error: El nombre es obligatorio"); }
    if (!preg_match("/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{2,3}$/", $rfc)) { die("Error: RFC inválido"); }
    if (!preg_match("/^\d{10}$/", $telefono)) { die("Error: El teléfono debe ser de 10 dígitos"); }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { die("Error: Email inválido"); }

    // === Manejo de archivos ===
    $carpeta  = "uploads/";
    $maxBytes = 2 * 1024 * 1024; // 2MB
    if (!is_dir($carpeta)) { mkdir($carpeta, 0777, true); }

    // Helper para subir un PDF (respeta espacio y orden, sin bucles)
    $subePDF = function($campoInput, $prefijoColumna, &$destinoActual) use ($carpeta, $maxBytes) {

        if (!isset($_FILES[$campoInput]) || $_FILES[$campoInput]['error'] !== UPLOAD_ERR_OK) {
            // No se subió nuevo: conservar el actual (espacio y orden se respetan en el formulario)
            return;
        }
        $ext = strtolower(pathinfo($_FILES[$campoInput]['name'], PATHINFO_EXTENSION));
        if ($ext !== "pdf") { die("Error: El archivo {$campoInput} debe ser PDF"); }
        if ($_FILES[$campoInput]['size'] > $maxBytes) { die("Error: El archivo {$campoInput} excede 2MB"); }

        $nuevoNombre = $prefijoColumna . "_" . time() . ".pdf";
        $destino     = $carpeta . $nuevoNombre;
        if (!move_uploaded_file($_FILES[$campoInput]['tmp_name'], $destino)) {
            die("Error al subir el archivo {$campoInput}");
        }
        $destinoActual = $destino;
    };

    // Cargar en ORDEN FIJO (sin foreach) — mantén este orden
    // 1) Constancia de situación fiscal (PDF, forzoso en alta)
    $subePDF("constancia", "constancia_pdf", $constancia_pdf);
    if (!$modoEdicion && empty($constancia_pdf)) { die("Error: La constancia de situación fiscal es obligatoria"); }

    // 2) Identificación oficial vigente (PDF, forzoso en alta)
    $subePDF("ine", "ine_pdf", $ine_pdf);
    if (!$modoEdicion && empty($ine_pdf)) { die("Error: La identificación oficial es obligatoria"); }

    // 3) Acta constitutiva (PDF, forzoso en alta)
    $subePDF("acta", "acta_pdf", $acta_pdf);
    if (!$modoEdicion && empty($acta_pdf)) { die("Error: El acta constitutiva es obligatoria"); }

    // 4) INE del representante (PDF, opcional; personas morales)
    $subePDF("representante", "ine_representante_pdf", $ine_representante_pdf);
    // No se fuerza en alta; se preserva si existe

    // 5) Datos bancarios (PDF, forzoso en alta)
    $subePDF("banco", "banco_pdf", $banco_pdf);
    if (!$modoEdicion && empty($banco_pdf)) { die("Error: Los datos bancarios (PDF) son obligatorios"); }

    // 6) Comprobante de domicilio (PDF, forzoso en alta)
    $subePDF("domicilio", "domicilio_pdf", $domicilio_pdf);
    if (!$modoEdicion && empty($domicilio_pdf)) { die("Error: El comprobante de domicilio es obligatorio"); }

    // === INSERT / UPDATE ===
    if ($modoEdicion) {
        
        $sql = "UPDATE proveedores SET 
                    nombre=?, rfc=?, telefono=?, email=?, direccion=?,
                    constancia_pdf=?, ine_pdf=?, acta_pdf=?, ine_representante_pdf=?, banco_pdf=?, domicilio_pdf=?
                WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssssssssi",
            $nombre, $rfc, $telefono, $email, $direccion,
            $constancia_pdf, $ine_pdf, $acta_pdf, $ine_representante_pdf, $banco_pdf, $domicilio_pdf,
            $idEditar
        );
    } else {
        $sql = "INSERT INTO proveedores
                    (nombre, rfc, telefono, email, direccion,
                     constancia_pdf, ine_pdf, acta_pdf, ine_representante_pdf, banco_pdf, domicilio_pdf)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssssssss",
            $nombre, $rfc, $telefono, $email, $direccion,
            $constancia_pdf, $ine_pdf, $acta_pdf, $ine_representante_pdf, $banco_pdf, $domicilio_pdf
        );

        // Crear usuario externo con RFC como username
        $res = crear_usuario($conn, $rfc, $nombre, $email,$rfc, false /* true si quieres actualizar el password al existir */);

        if (!$res['ok']) {
            error_log('[crear_usuario] '.$res['msg']);
        } else {
            echo "usuario creado: " . $rfc ." <br>"; 
        }
    }

    if ($stmt->execute()) {
        
        #header("Location: dashboard.php?page=listar_proveedores");
         echo '<script>location.href='.json_encode('dashboard.php?page=listar_proveedores').';</script>';
        exit();
    } else {
        echo "Error en la operación: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo $modoEdicion ? "Editar Proveedor" : "Nuevo Proveedor"; ?> | <?php echo $nombreSistema; ?></title>
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- Tu branding y overrides globales -->
    <link rel="stylesheet" href="dist/css/custom.css?v=5">
</head>
<body class="dashboard-page">
              
<form action="dashboard.php?page=proveedores&view=guardar&<?php echo $modoEdicion ? '&editar='.$idEditar : ''; ?>" method="POST" enctype="multipart/form-data" class="styled-form">
    <section class="content mod-proveedores view-edit">
    <div class="form-container">
        <div class="edit-proveedor size-md">

        <div class="edit-title"><i class="fas fa-truck"></i> Editar Proveedor</div>

        <!-- NOMBRE -->
        <div class="form-row triple-row">
            <div class="col-auto icon"><i class="fas fa-id-badge"></i></div>
                <div class="col-12 col-sm-3 col-md-3 col-label">
                    <label for="nombre">Nombre</label>
                </div>
            <div class="col-12 col-sm-9 col-md-8 col-input">
            <input id="nombre" type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($nombre) ?>" required>
            </div>
        </div>

        <!-- RFC -->
        <div class="form-row triple-row">
            <div class="col-auto icon"><i class="fas fa-address-card"></i></div>
                <div class="col-12 col-sm-3 col-md-3 col-label">
                    <label for="rfc">RFC</label>
                </div>
            <div class="col-12 col-sm-9 col-md-8 col-input">
            <input id="rfc" type="text" name="rfc" class="form-control" value="<?= htmlspecialchars($rfc) ?>">
            </div>
        </div>

        <!-- TELÉFONO -->
        <div class="form-row triple-row">
            <div class="col-auto icon"><i class="fas fa-phone"></i></div>
                <div class="col-12 col-sm-3 col-md-3 col-label">
                    <label for="telefono">Teléfono</label>
                </div>
            <div class="col-12 col-sm-9 col-md-8 col-input">
            <input id="telefono" type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($telefono) ?>">
            </div>
        </div>

        <!-- EMAIL -->
        <div class="form-row triple-row">
            <div class="col-auto icon"><i class="fas fa-envelope"></i></div>
                <div class="col-12 col-sm-3 col-md-3 col-label">
                    <label for="mail">Email</label>
                </div>
            <div class="col-12 col-sm-9 col-md-8 col-input">
            <input id="mail" type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>">
            </div>
        </div>

        <!-- DIRECCIÓN -->
        <div class="form-row triple-row">
            <div class="col-auto icon"><i class="fas fa-map-marker-alt"></i></div>
                <div class="col-12 col-sm-3 col-md-3 col-label">
                    <label for="direccion">Dirección</label>
                </div>
            <div class="col-12 col-sm-9 col-md-8 col-input">
            <input id="direccion" type="text" name="direccion" class="form-control" value="<?= htmlspecialchars($direccion) ?>">
            </div>
        </div>

        <!-- CONSTANCIA (PDF) -->
        <div class="form-row triple-row">
            <div class="col-auto icon"><i class="fas fa-file-pdf"></i></div>
                <div class="col-12 col-sm-3 col-md-3 col-label">
                    <label for="constancia">Constancia (PDF)</label>
                </div>
            <div class="col-12 col-sm-9 col-md-8 col-input">
            <div class="custom-file">
                <input type="file" class="custom-file-input" id="constancia" name="constancia" accept="application/pdf" <?php echo $modoEdicion ? "" : "required"; ?>>
                <label class="custom-file-label" for="constancia">Seleccionar archivo…</label>
            </div>
            <?php if(!empty($constancia_pdf)): ?>
                <small class="pdf-actual"><a href="<?= htmlspecialchars(asset($constancia_pdf)) ?>" accept="application/pdf" target="_blank"><i class="fas fa-file-pdf"></i>Ver actual</a></small>
            <?php endif; ?>
            </div>
        </div>

        <!-- INE (PDF) -->
        <div class="form-row triple-row">
            <div class="col-auto icon"><i class="fas fa-file-pdf"></i></div>
            <div class="col-12 col-sm-3 col-md-3 col-label">
            <label for="ine">Identificación oficial vigente (PDF)</label>
            </div>
            <div class="col-12 col-sm-9 col-md-8 col-input">
            <div class="custom-file">
                <input type="file" class="custom-file-input" id="ine" name="ine" accept="application/pdf" <?php echo $modoEdicion ? "" : "required"; ?>>
                <label class="custom-file-label" for="ine">Seleccionar archivo…</label>
            </div>
            <?php if(!empty($ine_pdf)): ?>
                <small class="pdf-actual"><a href="<?= htmlspecialchars(asset($ine_pdf)) ?>" target="_blank"><i class="fas fa-file-pdf"></i>Ver actual</a></small>
            <?php endif; ?>
            </div>
        </div>

        <!-- ACTA (PDF) -->
        <div class="form-row triple-row">
            <div class="col-auto icon"><i class="fas fa-file-pdf"></i></div>
            <div class="col-12 col-sm-3 col-md-3 col-label">
            <label for="acta">Acta constitutiva (PDF)</label>
            </div>
            <div class="col-12 col-sm-9 col-md-8 col-input">
            <div class="custom-file">
                <input type="file" class="custom-file-input" id="acta" name="acta" accept="application/pdf" <?php echo $modoEdicion ? "" : "required"; ?>>
                <label class="custom-file-label" for="acta">Seleccionar archivo…</label>
            </div>
            <?php if(!empty($acta_pdf)): ?>
                <small class="pdf-actual"><a href="<?= htmlspecialchars(asset($acta_pdf)) ?>" target="_blank"><i class="fas fa-file-pdf"></i>Ver actual</a></small>
            <?php endif; ?>
            </div>
        </div>

        <!-- INE REPRESENTANTE (PDF) -->
        <div class="form-row triple-row">
            <div class="col-auto icon"><i class="fas fa-file-pdf"></i></div>
            <div class="col-12 col-sm-3 col-md-3 col-label">
            <label for="representante">INE del representante legal (PDF)</label>
            </div>
            <div class="col-12 col-sm-9 col-md-8 col-input">
            <div class="custom-file">
                <input type="file" class="custom-file-input" id="representante" name="representante" accept="application/pdf" <?php echo $modoEdicion ? "" : "required"; ?>>
                <label class="custom-file-label" for="representante">Seleccionar archivo…</label>
            </div>
            <?php if(!empty($ine_representante_pdf)): ?>
                <small class="pdf-actual"><a href="<?= htmlspecialchars(asset($ine_representante_pdf)) ?>" target="_blank"><i class="fas fa-file-pdf"></i>Ver actual</a></small>
            <?php endif; ?>
            </div>
        </div>

        <!-- DATOS BANCARIOS (PDF) -->
        <div class="form-row triple-row">
            <div class="col-auto icon"><i class="fas fa-file-pdf"></i></div>
            <div class="col-12 col-sm-3 col-md-3 col-label">
            <label for="banco">Datos bancarios (PDF)</label>
            </div>
            <div class="col-12 col-sm-9 col-md-8 col-input">
            <div class="custom-file">
                <input type="file" class="custom-file-input" id="banco" name="banco" accept="application/pdf" <?php echo $modoEdicion ? "" : "required"; ?>>
                <label class="custom-file-label" for="banco">Seleccionar archivo…</label>
            </div>
            <?php if(!empty($banco_pdf)): ?>
                <small class="pdf-actual"><a href="<?= htmlspecialchars(asset($banco_pdf)) ?>" target="_blank"><i class="fas fa-file-pdf"></i>Ver actual</a></small>
            <?php endif; ?>
            </div>
        </div>

        <!-- COMPROBANTE DOMICILIO (PDF) -->
        <div class="form-row triple-row">
            <div class="col-auto icon"><i class="fas fa-file-pdf"></i></div>
            <div class="col-12 col-sm-3 col-md-3 col-label">
            <label for="domicilio">Comprobante de domicilio (PDF)</label>
            </div>
            <div class="col-12 col-sm-9 col-md-8 col-input">
            <div class="custom-file">
                <input type="file" class="custom-file-input" id="domicilio" name="domicilio" accept="application/pdf" <?php echo $modoEdicion ? "" : "required"; ?>>
                <label class="custom-file-label" for="domicilio">Seleccionar archivo…</label>
            </div>
            <?php if(!empty($domicilio_pdf)): ?>
                <small class="pdf-actual"><a href="<?= htmlspecialchars(asset($domicilio_pdf)) ?>" target="_blank"><i class="fas fa-file-pdf"></i>Ver actual</a></small>
            <?php endif; ?>
            </div>
        </div>

        <div class="text-right mt-3">
            <a href="dashboard.php?page=listar_proveedores" class="btn btn-primary" data-app>Cancelar</a>
            <button type="submit" name="guardar" class="btn btn-primary"><?php echo $modoEdicion ? "Actualizar" : "Guardar"; ?></button>
        </div>

        </div>
    </div>
    </section>
</form>
</body>
</html>
