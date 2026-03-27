<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

include 'config.php';

$withdrawalId = $_GET['id'] ?? '';

if (empty($withdrawalId)) {
    echo json_encode(['success' => false, 'error' => 'กรุณาระบุรหัส']);
    exit;
}

try {
    $sql = "SELECT 
                wh.WITHDRAWAL_ID,
                'INV-' || LPAD(i.INVENTORY_ID, 6, '0') as INVENTORY_CODE,
                p.PRODUCT_NAME,
                p.CATEGORY,
                wh.AMOUNT,
                TO_CHAR(wh.WITHDRAW_DATE, 'DD/MM/YYYY HH24:MI:SS') as WITHDRAW_DATE,
                wh.REASON,
                TRUNC(SYSDATE - wh.WITHDRAW_DATE) as DAYS_AGO
            FROM WITHDRAWAL_HISTORY wh
            JOIN INVENTORY i ON wh.INVENTORY_ID = i.INVENTORY_ID
            JOIN PRODUCTS p ON i.PRODUCT_ID = p.PRODUCT_ID
            WHERE wh.WITHDRAWAL_ID = :withdrawal_id";
    
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':withdrawal_id', $withdrawalId);
    oci_execute($stid);
    
    $withdrawal = oci_fetch_array($stid, OCI_ASSOC);
    
    if ($withdrawal) {
        echo json_encode([
            'success' => true,
            'withdrawal' => $withdrawal
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูล']);
    }
    
    oci_free_statement($stid);
    oci_close($conn);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>