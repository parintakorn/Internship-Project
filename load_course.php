<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'connect.php';

$typeId = isset($_GET['type']) ? (int)$_GET['type'] : 0;

if ($typeId == 0) {
    echo '<option value="">-- เลือกประเภทเมนูก่อน --</option>';
    exit;
}

$sql = "SELECT COURSEID, COURSENAME, COURSEPRICE 
        FROM MENU_COURSE 
        WHERE MENUTYPEID = " . $typeId . " 
        ORDER BY NVL(SORT_ORDER, COURSEID), COURSEID";

$stid = oci_parse($conn, $sql);

if (!$stid) {
    $e = oci_error($conn);
    echo '<option value="">Parse Error: ' . htmlentities($e['message']) . '</option>';
    exit;
}

$result = oci_execute($stid);

if (!$result) {
    $e = oci_error($stid);
    echo '<option value="">Execute Error: ' . htmlentities($e['message']) . '</option>';
    exit;
}

echo '<option value="">-- เลือกคอร์ส --</option>';

$count = 0;
while ($row = oci_fetch_assoc($stid)) {
    $count++;
    echo '<option value="' . htmlspecialchars($row['COURSEID']) . '" data-price="' . htmlspecialchars($row['COURSEPRICE']) . '">';
    echo htmlspecialchars($row['COURSENAME']) . ' (' . number_format($row['COURSEPRICE']) . ' บาท)';
    echo '</option>';
}

if ($count == 0) {
    echo '<option value="">-- ไม่พบคอร์ส (Type: ' . $typeId . ') --</option>';
}

oci_free_statement($stid);
oci_close($conn);
?>