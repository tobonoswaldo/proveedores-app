<?php
/* Archivo: proveedores.php
   Descripción: Alta y edición de proveedores con validaciones y carga de PDFs.
   - Modo nuevo: formulario vacío, PDFs forzosos (excepto INE representante).
   - Modo edición: precarga datos; si no subes nuevos PDFs, conserva los actuales.
   - Tras guardar/actualizar: redirige a listar_proveedores.php
*/

session_start();
include 'config.php';

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
    $subePDF("ine_representante", "ine_representante_pdf", $ine_representante_pdf);
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
        require_once __DIR__ . '/bases/funciones.php';
        // Crear usuario externo con RFC como username
        $res = crear_usuario($conn, $rfc, $nombre, $email,$rfc, false /* true si quieres actualizar el password al existir */);

        if (!$res['ok']) {
            error_log('[crear_usuario] '.$res['msg']);
        } else {
            echo "usuario creado: " . $rfc ." <br>"; 
        }
    }

    if ($stmt->execute()) {
        header("Location: listar_proveedores.php");
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
</head>
<body class="dashboard-page">

<div class="form-container">
    <div class="form-header">
        <i class="fa fa-truck"></i> <?php echo $modoEdicion ? "Editar Proveedor" : "Nuevo Proveedor"; ?>
    </div>

    <!-- Formulario: el ORDEN y ESPACIOS de los PDFs se mantiene fijo (sin bucles) -->
    <form action="proveedores.php<?php echo $modoEdicion ? '?editar='.$idEditar : ''; ?>"
          method="POST" enctype="multipart/form-data" class="styled-form">

        <!-- ===== Datos principales ===== -->
        <div class="input-group">
            <i class="fa fa-building"></i>
            <input type="text" name="nombre" placeholder="Nombre del proveedor" required
                   value="<?php echo htmlspecialchars($nombre); ?>">
        </div>

        <div class="input-group">
            <i class="fa fa-id-card"></i>
            <input type="text" name="rfc" placeholder="RFC" required maxlength="13"
                   pattern="^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{2,3}$"
                   value="<?php echo htmlspecialchars($rfc); ?>">
        </div>

        <div class="input-group">
            <i class="fa fa-phone"></i>
            <input type="text" name="telefono" placeholder="Teléfono (10 dígitos)" required maxlength="10"
                   pattern="^\d{10}$" value="<?php echo htmlspecialchars($telefono); ?>">
        </div>

        <div class="input-group">
            <i class="fa fa-envelope"></i>
            <input type="email" name="email" placeholder="Correo electrónico" required
                   value="<?php echo htmlspecialchars($email); ?>">
        </div>

        <div class="input-group">
            <i class="fa fa-map-marker-alt"></i>
            <input type="text" name="direccion" placeholder="Dirección completa" required
                   value="<?php echo htmlspecialchars($direccion); ?>">
        </div>

        <!-- ===== Documentos en ORDEN FIJO (cada bloque conserva su espacio) ===== -->

        <!-- 1) Constancia de situación fiscal (PDF) -->
        <label>Constancia de situación fiscal (PDF):</label>
        <input type="file" name="constancia" accept="application/pdf" <?php echo $modoEdicion ? "" : "required"; ?>>
        <div class="doc-slot">
            <?php if ($modoEdicion): ?>
                <?php if ($constancia_pdf): ?>
                    <a href="<?php echo $constancia_pdf; ?>" target="_blank">
                        <i class="fa fa-file-pdf pdf-icon"></i> Ver actual
                    </a>
                <?php else: ?>
                    <span class="placeholder">—</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- 2) Identificación oficial vigente (PDF) -->
        <label>Identificación oficial vigente (PDF):</label>
        <input type="file" name="ine" accept="application/pdf" <?php echo $modoEdicion ? "" : "required"; ?>>
        <div class="doc-slot">
            <?php if ($modoEdicion): ?>
                <?php if ($ine_pdf): ?>
                    <a href="<?php echo $ine_pdf; ?>" target="_blank">
                        <i class="fa fa-file-pdf pdf-icon"></i> Ver actual
                    </a>
                <?php else: ?>
                    <span class="placeholder">—</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- 3) Acta constitutiva (PDF) -->
        <label>Acta constitutiva (PDF):</label>
        <input type="file" name="acta" accept="application/pdf" <?php echo $modoEdicion ? "" : "required"; ?>>
        <div class="doc-slot">
            <?php if ($modoEdicion): ?>
                <?php if ($acta_pdf): ?>
                    <a href="<?php echo $acta_pdf; ?>" target="_blank">
                        <i class="fa fa-file-pdf pdf-icon"></i> Ver actual
                    </a>
                <?php else: ?>
                    <span class="placeholder">—</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- 4) INE del representante (PDF - opcional) -->
        <label>INE del representante legal (PDF - personas morales):</label>
        <input type="file" name="ine_representante" accept="application/pdf">
        <div class="doc-slot">
            <?php if ($modoEdicion): ?>
                <?php if ($ine_representante_pdf): ?>
                    <a href="<?php echo $ine_representante_pdf; ?>" target="_blank">
                        <i class="fa fa-file-pdf pdf-icon"></i> Ver actual
                    </a>
                <?php else: ?>
                    <span class="placeholder">—</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- 5) Datos bancarios (PDF) -->
        <label>Datos bancarios (PDF):</label>
        <input type="file" name="banco" accept="application/pdf" <?php echo $modoEdicion ? "" : "required"; ?>>
        <div class="doc-slot">
            <?php if ($modoEdicion): ?>
                <?php if ($banco_pdf): ?>
                    <a href="<?php echo $banco_pdf; ?>" target="_blank">
                        <i class="fa fa-file-pdf pdf-icon"></i> Ver actual
                    </a>
                <?php else: ?>
                    <span class="placeholder">—</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- 6) Comprobante de domicilio (PDF) -->
        <label>Comprobante de domicilio (PDF):</label>
        <input type="file" name="domicilio" accept="application/pdf" <?php echo $modoEdicion ? "" : "required"; ?>>
        <div class="doc-slot">
            <?php if ($modoEdicion): ?>
                <?php if ($domicilio_pdf): ?>
                    <a href="<?php echo $domicilio_pdf; ?>" target="_blank">
                        <i class="fa fa-file-pdf pdf-icon"></i> Ver actual
                    </a>
                <?php else: ?>
                    <span class="placeholder">—</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <button type="submit" name="guardar"><?php echo $modoEdicion ? "Actualizar" : "Guardar"; ?></button>
    </form>
</div>

</body>
</html>
