<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

try {
    // เชื่อมต่อฐานข้อมูล
    $conn = oci_connect("restaurant", "1234", "localhost/XEPDB1", 'AL32UTF8');
    
    if (!$conn) {
        $error = oci_error();
        throw new Exception('ไม่สามารถเชื่อมต่อฐานข้อมูล: ' . $error['message']);
    }
    
    // Query ข้อมูล
    $sql = "SELECT 
                PRODUCT_ID, 
                PRODUCT_NAME, 
                NVL(CATEGORY, 'ไม่ระบุ') as CATEGORY,
                TO_CHAR(CREATED_DATE, 'YYYY-MM-DD') as CREATED_DATE
            FROM PRODUCTS 
            ORDER BY PRODUCT_NAME";
    
    $stmt = oci_parse($conn, $sql);
    
    if (!$stmt) {
        $error = oci_error($conn);
        throw new Exception('Parse Error: ' . $error['message']);
    }
    
    $result = oci_execute($stmt);
    
    if (!$result) {
        $error = oci_error($stmt);
        throw new Exception('Execute Error: ' . $error['message']);
    }
    
    $products = array();
    
    while ($row = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS)) {
        $products[] = array(
            'PRODUCT_ID' => $row['PRODUCT_ID'],
            'PRODUCT_NAME' => $row['PRODUCT_NAME'],
            'CATEGORY' => $row['CATEGORY']
        );
    }
    
    oci_free_statement($stmt);
    oci_close($conn);
    
    echo json_encode([
        'success' => true,
        'products' => $products
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("get_products.php Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>