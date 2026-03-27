<?php
header('Content-Type: application/json; charset=utf-8');
include 'config.php';

$code = isset($_GET['code']) ? $_GET['code'] : '';

if (empty($code)) {
    echo json_encode([
        'success' => false,
        'error' => 'ไม่พบรหัสวัตถุดิบ'
    ]);
    exit;
}

// แปลง INV-000001 เป็น 1
$inventoryId = str_replace('INV-', '', $code);
$inventoryId = ltrim($inventoryId, '0');

try {
    // ดึงข้อมูลวัตถุดิบ
    $query = "SELECT 
                'INV-' || LPAD(i.INVENTORY_ID, 6, '0') as INVENTORY_CODE,
                p.PRODUCT_NAME,
                p.CATEGORY,
                i.WEIGHT,
                TO_CHAR(i.RECEIVE_DATE, 'DD/MM/YYYY HH24:MI:SS') as RECEIVE_DATE,
                TRUNC(SYSDATE - i.RECEIVE_DATE) as DAYS_IN_STOCK,
                CASE 
                    WHEN i.STATUS = 'IN_STOCK' THEN 'มีในคลัง'
                    WHEN i.STATUS = 'WITHDRAWN' THEN 'เบิกออกแล้ว'
                    ELSE i.STATUS
                END as STATUS
              FROM INVENTORY i
              JOIN PRODUCTS p ON i.PRODUCT_ID = p.PRODUCT_ID
              WHERE i.INVENTORY_ID = :inventory_id";
    
    $stid = oci_parse($conn, $query);
    oci_bind_by_name($stid, ':inventory_id', $inventoryId);
    oci_execute($stid);
    
    if ($inventory = oci_fetch_assoc($stid)) {
        // ดึงประวัติการเบิก
        $queryHistory = "SELECT 
                            TO_CHAR(wh.WITHDRAW_DATE, 'DD/MM/YYYY HH24:MI:SS') as WITHDRAW_DATE,
                            wh.AMOUNT,
                            wh.REASON
                         FROM WITHDRAWAL_HISTORY wh
                         WHERE wh.INVENTORY_ID = :inventory_id
                         ORDER BY wh.WITHDRAW_DATE DESC";
        
        $stidHistory = oci_parse($conn, $queryHistory);
        oci_bind_by_name($stidHistory, ':inventory_id', $inventoryId);
        oci_execute($stidHistory);
        
        $history = [];
        while ($row = oci_fetch_assoc($stidHistory)) {
            $history[] = $row;
        }
        oci_free_statement($stidHistory);
        
        echo json_encode([
            'success' => true,
            'inventory' => $inventory,
            'history' => $history
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'ไม่พบข้อมูลวัตถุดิบ'
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
?>