<?php
header('Content-Type: application/json; charset=utf-8');
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inventoryId = $_POST['inventoryId'] ?? '';
    $newPrefixId = $_POST['newPrefixId'] ?? '';
    
    if (empty($inventoryId) || empty($newPrefixId)) {
        echo json_encode([
            'success' => false,
            'error' => 'ข้อมูลไม่ครบถ้วน'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    try {
        $sql = "UPDATE INVENTORY SET PREFIX_ID = :prefix_id WHERE INVENTORY_ID = :inventory_id";
        $stid = oci_parse($conn, $sql);
        oci_bind_by_name($stid, ':prefix_id', $newPrefixId);
        oci_bind_by_name($stid, ':inventory_id', $inventoryId);
        
        if (oci_execute($stid)) {
            oci_commit($conn);
            echo json_encode([
                'success' => true,
                'message' => 'อัพเดทสำเร็จ'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            throw new Exception('ไม่สามารถอัพเดทได้');
        }
        
        oci_free_statement($stid);
        oci_close($conn);
        
    } catch(Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}
?>