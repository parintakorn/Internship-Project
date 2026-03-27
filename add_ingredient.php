<?php
include 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $id = $_POST['id'];
    $name = $_POST['name'];
    $qty = $_POST['qty'];
    $unit = $_POST['unit'];

    $sql = "INSERT INTO INGREDIENT (INGREDIENTID, INGREDIENTNAME, QTYONHAND, UNIT)
            VALUES (:id, :name, :qty, :unit)";

    $stid = oci_parse($conn, $sql);

    oci_bind_by_name($stid, ":id", $id);
    oci_bind_by_name($stid, ":name", $name);
    oci_bind_by_name($stid, ":qty", $qty);
    oci_bind_by_name($stid, ":unit", $unit);

    if (oci_execute($stid)) {
        echo "<script>alert('เพิ่มข้อมูลสำเร็จ!'); window.location='index.php';</script>";
    } else {
        echo "<script>alert('เพิ่มข้อมูลไม่สำเร็จ');</script>";
    }
}
?>

<h2>เพิ่มวัตถุดิบใหม่</h2>

<form method="POST">
    <label>ID:</label><br>
    <input type="number" name="id" required><br><br>

    <label>ชื่อวัตถุดิบ:</label><br>
    <input type="text" name="name" required><br><br>

    <label>จำนวนคงเหลือ:</label><br>
    <input type="number" name="qty" required><br><br>

    <label>หน่วย:</label><br>
    <input type="text" name="unit" required><br><br>

    <button type="submit">บันทึก</button>
</form>

<p><a href="index.php">⬅ กลับหน้าแรก</a></p>
