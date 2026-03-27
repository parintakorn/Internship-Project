<?php
header('Content-Type: application/json; charset=utf-8');
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inventoryCode = $_POST['inventoryCode'] ?? '';
    
    if (empty($inventoryCode)) {
        echo json_encode([
            'success' => false,
            'error' => 'กรุณากรอกรหัสวัตถุดิบ'
        ]);
        exit;
    }
    
    // แปลง INV-000001 เป็น 1
    $inventoryId = str_replace('INV-', '', $inventoryCode);
    $inventoryId = ltrim($inventoryId, '0'); // ลบ 0 ข้างหน้า
    
    try {
        $query = "SELECT 
                    i.INVENTORY_ID,
                    'INV-' || LPAD(i.INVENTORY_ID, 6, '0') as INVENTORY_CODE,
                    p.PRODUCT_NAME,
                    i.WEIGHT,
                    TO_CHAR(i.RECEIVE_DATE, 'DD/MM/YYYY') as RECEIVE_DATE,
                    i.STATUS
                  FROM INVENTORY i
                  JOIN PRODUCTS p ON i.PRODUCT_ID = p.PRODUCT_ID
                  WHERE i.INVENTORY_ID = :inventory_id";
        
        $stid = oci_parse($conn, $query);
        oci_bind_by_name($stid, ':inventory_id', $inventoryId);
        oci_execute($stid);
        
        if ($row = oci_fetch_assoc($stid)) {
            echo json_encode([
                'success' => true,
                'inventory' => $row
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'ไม่พบรหัสวัตถุดิบในระบบ'
            ]);
        }
        
        oci_free_statement($stid);
        oci_close($conn);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
?>