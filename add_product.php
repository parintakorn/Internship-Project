<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$productName = $_POST['productName'] ?? '';
$category = $_POST['category'] ?? '';

if (empty($productName) || empty($category)) {
    echo json_encode(['success' => false, 'error' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
    exit;
}

try {
    $conn = oci_connect("restaurant", "1234", "localhost/XEPDB1", 'AL32UTF8');
    
    if (!$conn) {
        $error = oci_error();
        throw new Exception('ไม่สามารถเชื่อมต่อฐานข้อมูล');
    }
    
    // หา PRODUCT_ID ถัดไป
    $seqSql = "SELECT NVL(MAX(PRODUCT_ID), 0) + 1 as NEXT_ID FROM PRODUCTS";
    $seqStmt = oci_parse($conn, $seqSql);
    oci_execute($seqStmt);
    $seqRow = oci_fetch_array($seqStmt, OCI_ASSOC);
    $nextId = $seqRow['NEXT_ID'];
    
    // Insert ข้อมูล
    $sql = "INSERT INTO PRODUCTS (PRODUCT_ID, PRODUCT_NAME, CATEGORY, CREATED_DATE) 
            VALUES (:product_id, :product_name, :category, SYSDATE)";
    
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':product_id', $nextId);
    oci_bind_by_name($stmt, ':product_name', $productName);
    oci_bind_by_name($stmt, ':category', $category);
    
    $result = oci_execute($stmt);
    
    if ($result) {
        oci_commit($conn);
        oci_free_statement($stmt);
        oci_close($conn);
        
        echo json_encode([
            'success' => true,
            'message' => 'เพิ่มวัตถุดิบสำเร็จ',
            'productId' => $nextId
        ], JSON_UNESCAPED_UNICODE);
    } else {
        $error = oci_error($stmt);
        oci_rollback($conn);
        throw new Exception($error['message']);
    }
    
} catch (Exception $e) {
    error_log("add_product.php Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>