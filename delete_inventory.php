<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

include 'config.php';

$inventoryCode = $_POST['code'] ?? '';

if (empty($inventoryCode)) {
    echo json_encode(['success' => false, 'error' => 'กรุณาระบุรหัสคลัง']);
    exit;
}

try {
    // แปลง INV-000001 เป็น INVENTORY_ID
    // INV-000001 -> ตัดเอาแค่ตัวเลขหลัง INV-
    $inventoryId = intval(str_replace('INV-', '', $inventoryCode));
    
    if ($inventoryId <= 0) {
        throw new Exception('รหัสคลังไม่ถูกต้อง');
    }
    
    // ตรวจสอบว่ามีข้อมูลอยู่จริง
    $checkSql = "SELECT i.INVENTORY_ID, p.PRODUCT_NAME, i.STATUS 
                 FROM INVENTORY i
                 JOIN PRODUCTS p ON i.PRODUCT_ID = p.PRODUCT_ID
                 WHERE i.INVENTORY_ID = :inventory_id";
    $checkStmt = oci_parse($conn, $checkSql);
    oci_bind_by_name($checkStmt, ':inventory_id', $inventoryId);
    oci_execute($checkStmt);
    $inventory = oci_fetch_array($checkStmt, OCI_ASSOC);
    oci_free_statement($checkStmt);
    
    if (!$inventory) {
        throw new Exception('ไม่พบข้อมูลคลัง');
    }
    
    // ตรวจสอบว่ามีประวัติการเบิกหรือไม่
    $checkHistorySql = "SELECT COUNT(*) as TOTAL FROM WITHDRAWAL_HISTORY WHERE INVENTORY_ID = :inventory_id";
    $checkHistoryStmt = oci_parse($conn, $checkHistorySql);
    oci_bind_by_name($checkHistoryStmt, ':inventory_id', $inventoryId);
    oci_execute($checkHistoryStmt);
    $historyResult = oci_fetch_array($checkHistoryStmt, OCI_ASSOC);
    oci_free_statement($checkHistoryStmt);
    
    if ($historyResult['TOTAL'] > 0) {
        throw new Exception('ไม่สามารถลบได้ เนื่องจากมีประวัติการเบิก ' . $historyResult['TOTAL'] . ' รายการ');
    }
    
    // ลบข้อมูล
    $deleteSql = "DELETE FROM INVENTORY WHERE INVENTORY_ID = :inventory_id";
    $deleteStmt = oci_parse($conn, $deleteSql);
    oci_bind_by_name($deleteStmt, ':inventory_id', $inventoryId);
    $result = oci_execute($deleteStmt);
    
    if ($result) {
        $rowsDeleted = oci_num_rows($deleteStmt);
        oci_commit($conn);
        oci_free_statement($deleteStmt);
        oci_close($conn);
        
        if ($rowsDeleted > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'ลบวัตถุดิบสำเร็จ: ' . $inventory['PRODUCT_NAME']
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลถูกลบ']);
        }
    } else {
        $error = oci_error($deleteStmt);
        throw new Exception($error['message']);
    }
    
} catch (Exception $e) {
    if (isset($conn)) {
        oci_rollback($conn);
        oci_close($conn);
    }
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>