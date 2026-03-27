<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

include 'config.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['prices']) || !is_array($data['prices'])) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

try {
    $updated = 0;
    
    foreach ($data['prices'] as $item) {
        $productId = intval($item['productId']);
        $price = floatval($item['price']);
        
        if ($price >= 0) {
            $sql = "UPDATE PRODUCTS SET PRICE_PER_GRAM = :price WHERE PRODUCT_ID = :product_id";
            $stid = oci_parse($conn, $sql);
            
            oci_bind_by_name($stid, ':price', $price);
            oci_bind_by_name($stid, ':product_id', $productId);
            
            if (oci_execute($stid)) {
                $updated++;
            }
            
            oci_free_statement($stid);
        }
    }
    
    oci_commit($conn);
    oci_close($conn);
    
    echo json_encode([
        'success' => true,
        'updated' => $updated,
        'message' => "บันทึกราคาสำเร็จ {$updated} รายการ"
    ]);
    
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