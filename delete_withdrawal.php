<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

include 'config.php';

$withdrawalId = $_POST['withdrawalId'] ?? '';

if (empty($withdrawalId)) {
    echo json_encode(['success' => false, 'error' => 'กรุณาระบุรหัสการเบิก']);
    exit;
}

try {
    // ตรวจสอบว่ามีข้อมูลอยู่จริง
    $checkSql = "SELECT wh.WITHDRAWAL_ID, p.PRODUCT_NAME 
                 FROM WITHDRAWAL_HISTORY wh
                 JOIN INVENTORY i ON wh.INVENTORY_ID = i.INVENTORY_ID
                 JOIN PRODUCTS p ON i.PRODUCT_ID = p.PRODUCT_ID
                 WHERE wh.WITHDRAWAL_ID = :withdrawal_id";
    $checkStmt = oci_parse($conn, $checkSql);
    oci_bind_by_name($checkStmt, ':withdrawal_id', $withdrawalId);
    oci_execute($checkStmt);
    $withdrawal = oci_fetch_array($checkStmt, OCI_ASSOC);
    oci_free_statement($checkStmt);
    
    if (!$withdrawal) {
        throw new Exception('ไม่พบรายการเบิก');
    }
    
    // ลบข้อมูล
    $deleteSql = "DELETE FROM WITHDRAWAL_HISTORY WHERE WITHDRAWAL_ID = :withdrawal_id";
    $deleteStmt = oci_parse($conn, $deleteSql);
    oci_bind_by_name($deleteStmt, ':withdrawal_id', $withdrawalId);
    $result = oci_execute($deleteStmt);
    
    if ($result) {
        oci_commit($conn);
        echo json_encode([
            'success' => true, 
            'message' => 'ลบรายการเบิกสำเร็จ: ' . $withdrawal['PRODUCT_NAME']
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('ไม่สามารถลบได้');
    }
    
    oci_free_statement($deleteStmt);
    oci_close($conn);
    
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