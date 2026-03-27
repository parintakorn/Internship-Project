<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$productId = $_POST['productId'] ?? '';

if (empty($productId)) {
    echo json_encode(['success' => false, 'error' => 'กรุณาระบุรหัสสินค้า']);
    exit;
}

$productId = intval($productId);

try {
    $conn = oci_connect("restaurant", "1234", "localhost/XEPDB1", 'AL32UTF8');
    
    if (!$conn) {
        throw new Exception('ไม่สามารถเชื่อมต่อฐานข้อมูล');
    }
    
    // ตรวจสอบว่าสินค้ามีอยู่จริง
    $checkProductSql = "SELECT PRODUCT_ID, PRODUCT_NAME FROM PRODUCTS WHERE PRODUCT_ID = :product_id";
    $checkProductStmt = oci_parse($conn, $checkProductSql);
    oci_bind_by_name($checkProductStmt, ':product_id', $productId);
    oci_execute($checkProductStmt);
    $product = oci_fetch_array($checkProductStmt, OCI_ASSOC);
    
    if (!$product) {
        oci_close($conn);
        echo json_encode(['success' => false, 'error' => 'ไม่พบสินค้า']);
        exit;
    }
    
    // ตรวจสอบว่ามีการใช้งานในคลังหรือไม่ (ทั้งหมด)
    $checkAllSql = "SELECT COUNT(*) as TOTAL FROM INVENTORY WHERE PRODUCT_ID = :product_id";
    $checkAllStmt = oci_parse($conn, $checkAllSql);
    oci_bind_by_name($checkAllStmt, ':product_id', $productId);
    oci_execute($checkAllStmt);
    $allResult = oci_fetch_array($checkAllStmt, OCI_ASSOC);
    $totalCount = $allResult['TOTAL'];
    
   if ($totalCount > 0) {
    // ลบข้อมูลใน INVENTORY ก่อน
    $deleteInventorySql = "DELETE FROM INVENTORY WHERE PRODUCT_ID = :product_id";
    $deleteInventoryStmt = oci_parse($conn, $deleteInventorySql);
    oci_bind_by_name($deleteInventoryStmt, ':product_id', $productId);
    oci_execute($deleteInventoryStmt);
    $inventoryDeleted = oci_num_rows($deleteInventoryStmt);
}
        // แยกนับตาม STATUS
        $checkDetailSql = "SELECT 
                            SUM(CASE WHEN STATUS = 'IN_STOCK' THEN 1 ELSE 0 END) as IN_STOCK,
                            SUM(CASE WHEN STATUS = 'WITHDRAWN' THEN 1 ELSE 0 END) as WITHDRAWN
                           FROM INVENTORY WHERE PRODUCT_ID = :product_id";
        $checkDetailStmt = oci_parse($conn, $checkDetailSql);
        oci_bind_by_name($checkDetailStmt, ':product_id', $productId);
        oci_execute($checkDetailStmt);
        $detailResult = oci_fetch_array($checkDetailStmt, OCI_ASSOC);
        
        $inStockCount = $detailResult['IN_STOCK'] ?? 0;
        $withdrawnCount = $detailResult['WITHDRAWN'] ?? 0;
        
        $errorMsg = 'ไม่สามารถลบได้ เนื่องจาก:';
        if ($inStockCount > 0) {
            $errorMsg .= '\n- มีสินค้าในคลัง ' . $inStockCount . ' รายการ';
        }
        if ($withdrawnCount > 0) {
            $errorMsg .= '\n- มีประวัติการเบิก ' . $withdrawnCount . ' รายการ';
        }
        $errorMsg .= '\n\nเพื่อรักษาความถูกต้องของข้อมูล';
        
        oci_close($conn);
        echo json_encode([
            'success' => false, 
            'error' => $errorMsg,
            'inStock' => $inStockCount,
            'withdrawn' => $withdrawnCount
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // ถ้าไม่มีข้อมูลในคลังเลย ลบได้
    $deleteSql = "DELETE FROM PRODUCTS WHERE PRODUCT_ID = :product_id";
    $deleteStmt = oci_parse($conn, $deleteSql);
    oci_bind_by_name($deleteStmt, ':product_id', $productId);
    $result = oci_execute($deleteStmt);
    
    if ($result) {
        $rowsDeleted = oci_num_rows($deleteStmt);
        oci_commit($conn);
        oci_free_statement($deleteStmt);
        oci_close($conn);
        
        if ($rowsDeleted > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'ลบวัตถุดิบสำเร็จ: ' . $product['PRODUCT_NAME']
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลถูกลบ']);
        }
    } else {
        $error = oci_error($deleteStmt);
        oci_rollback($conn);
        throw new Exception($error['message']);
    }
    
} catch (Exception $e) {
    if (isset($conn)) {
        oci_rollback($conn);
        oci_close($conn);
    }
    error_log("delete_product.php Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>