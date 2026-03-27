<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

include 'config.php';

$productId = $_POST['productId'] ?? '';
$price = $_POST['price'] ?? '';

if (empty($productId) || empty($price)) {
    echo json_encode(['success' => false, 'error' => 'กรุณาระบุข้อมูลให้ครบถ้วน']);
    exit;
}

$productId = intval($productId);
$price = floatval($price);

if ($price < 0) {
    echo json_encode(['success' => false, 'error' => 'ราคาต้องมากกว่าหรือเท่ากับ 0']);
    exit;
}

try {
    $sql = "UPDATE PRODUCTS SET PRICE_PER_GRAM = :price WHERE PRODUCT_ID = :product_id";
    $stid = oci_parse($conn, $sql);
    
    oci_bind_by_name($stid, ':price', $price);
    oci_bind_by_name($stid, ':product_id', $productId);
    
    $result = oci_execute($stid);
    
    if ($result) {
        oci_commit($conn);
        echo json_encode([
            'success' => true,
            'message' => 'บันทึกราคาสำเร็จ'
        ]);
    } else {
        $error = oci_error($stid);
        throw new Exception($error['message']);
    }
    
    oci_free_statement($stid);
    oci_close($conn);
    
} catch (Exception $e) {
    if (isset($conn)) {
        oci_rollback($conn);
        oci_close($conn);
    }
    echo json_encode([
        'success' => false,
        'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}
?>