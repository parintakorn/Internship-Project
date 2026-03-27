<?php
// transaction_delete.php
include 'connect.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: transaction_list.php");
    exit;
}

$orderId = $_GET['id'];
$deleteSuccess = false;

try {
    // 1. ลบจาก order_profit
    $sqlProfit = "DELETE FROM order_profit WHERE orderid = :orderid";
    $stidProfit = oci_parse($conn, $sqlProfit);
    oci_bind_by_name($stidProfit, ":orderid", $orderId);
    oci_execute($stidProfit, OCI_NO_AUTO_COMMIT);

    // 2. ลบจาก order_item
    $sqlOrderItem = "DELETE FROM order_item WHERE orderid = :orderid";
    $stidOrderItem = oci_parse($conn, $sqlOrderItem);
    oci_bind_by_name($stidOrderItem, ":orderid", $orderId);
    oci_execute($stidOrderItem, OCI_NO_AUTO_COMMIT);

    // 3. ลบจาก transaction
    $sqlTransaction = "DELETE FROM transaction WHERE orderid = :orderid";
    $stidTransaction = oci_parse($conn, $sqlTransaction);
    oci_bind_by_name($stidTransaction, ":orderid", $orderId);
    oci_execute($stidTransaction, OCI_NO_AUTO_COMMIT);

    // commit ทั้งหมด
    oci_commit($conn);
    $deleteSuccess = true;

} catch (Exception $e) {
    oci_rollback($conn);
    $deleteSuccess = false;
}

// ปิด statement และ connection
oci_free_statement($stidProfit);
oci_free_statement($stidOrderItem);
oci_free_statement($stidTransaction);
oci_close($conn);

// ส่งกลับไปหน้า list
if ($deleteSuccess) {
    header("Location: transaction_list.php?deleted=success");
} else {
    header("Location: transaction_list.php?deleted=fail");
}
exit;
