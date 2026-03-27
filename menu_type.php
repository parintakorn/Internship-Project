<?php
// เชื่อม Oracle
$conn = oci_connect("RESTAURANT", "1234", "localhost/XEPDB1");

// ถ้ากดปุ่มเพิ่ม
if(isset($_POST['add'])){
    $name = $_POST['name'];
    $sql = "INSERT INTO Menu_type (TYPE_NAME) VALUES (:name)";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":name", $name);

    if(oci_execute($stid)){
        $message = "เพิ่มประเภทเมนูสำเร็จ!";
    } else {
        $message = "เพิ่มข้อมูลไม่สำเร็จ";
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
<meta charset="UTF-8">
<title>จัดการประเภทเมนู</title>
</head>
<body>

<h2>เพิ่มประเภทเมนู</h2>

<form method="POST">
    <label>ชื่อประเภทเมนู:</label>
    <input type="text" name="name" required>
    <button type="submit" name="add">เพิ่ม</button>
</form>

<p style="color:green;">
    <?php if(isset($message)) echo $message; ?>
</p>

<hr>

<h2>รายการประเภทเมนู</h2>

<table border="1" cellpadding="5">
<tr>
    <th>ID</th>
    <th>ชื่อประเภท</th>
</tr>

<?php
// แสดงรายการทั้งหมด
$sql2 = "SELECT * FROM Menu_type ORDER BY TYPE_ID";
$stid2 = oci_parse($conn, $sql2);
oci_execute($stid2);

while($row = oci_fetch_assoc($stid2)){
    echo "<tr>";
    echo "<td>".$row['TYPE_ID']."</td>";
    echo "<td>".$row['TYPE_NAME']."</td>";
    echo "</tr>";
}
?>
</table>

</body>
</html>

