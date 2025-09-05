<?php
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $proveedor_id = $_POST['proveedor_id'];
    $numero_factura = $_POST['numero_factura'];
    $monto = $_POST['monto'];
    $fecha_emision = $_POST['fecha_emision'];
    $fecha_vencimiento = $_POST['fecha_vencimiento'];

    $sql = "INSERT INTO facturas (proveedor_id, numero_factura, monto, fecha_emision, fecha_vencimiento) 
            VALUES (?,?,?,?,?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isdss", $proveedor_id, $numero_factura, $monto, $fecha_emision, $fecha_vencimiento);

    if ($stmt->execute()) {
        echo "Factura registrada correctamente";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<h2>Registrar Factura</h2>
<form method="POST">
    <select name="proveedor_id">
        <?php
        $res = $conn->query("SELECT id,nombre FROM proveedores");
        while ($row = $res->fetch_assoc()) {
            echo "<option value='".$row['id']."'>".$row['nombre']."</option>";
        }
        ?>
    </select><br>
    <input type="text" name="numero_factura" placeholder="Número de factura" required><br>
    <input type="number" step="0.01" name="monto" placeholder="Monto" required><br>
    <input type="date" name="fecha_emision" required><br>
    <input type="date" name="fecha_vencimiento" required><br>
    <button type="submit">Guardar</button>
</form>
