<?php
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $factura_id = $_POST['factura_id'];
    $fecha_pago = $_POST['fecha_pago'];
    $monto = $_POST['monto'];
    $metodo_pago = $_POST['metodo_pago'];
    $referencia = $_POST['referencia'];

    $sql = "INSERT INTO pagos (factura_id, fecha_pago, monto, metodo_pago, referencia) 
            VALUES (?,?,?,?,?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isdss", $factura_id, $fecha_pago, $monto, $metodo_pago, $referencia);

    if ($stmt->execute()) {
        $conn->query("UPDATE facturas SET estado='pagada' WHERE id=$factura_id");
        echo "Pago registrado correctamente";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<h2>Registrar Pago</h2>
<form method="POST">
    <select name="factura_id">
        <?php
        $res = $conn->query("SELECT id,numero_factura FROM facturas WHERE estado='pendiente'");
        while ($row = $res->fetch_assoc()) {
            echo "<option value='".$row['id']."'>".$row['numero_factura']."</option>";
        }
        ?>
    </select><br>
    <input type="date" name="fecha_pago" required><br>
    <input type="number" step="0.01" name="monto" placeholder="Monto" required><br>
    <select name="metodo_pago">
        <option value="transferencia">Transferencia</option>
        <option value="efectivo">Efectivo</option>
        <option value="cheque">Cheque</option>
    </select><br>
    <input type="text" name="referencia" placeholder="Referencia"><br>
    <button type="submit">Guardar</button>
</form>
