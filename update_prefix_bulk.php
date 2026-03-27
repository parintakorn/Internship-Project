<?php
header('Content-Type: application/json; charset=utf-8');
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    $updates = $data['updates'] ?? [];
    
    if (empty($updates)) {
        echo json_encode([
            'success' => false,
            'error' => 'ไม่มีข้อมูลที่จะอัพเดท'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    try {
        $count = 0;
        foreach ($updates as $update) {
            $sql = "UPDATE INVENTORY SET PREFIX_ID = :prefix_id WHERE INVENTORY_ID = :inventory_id";
            $stid = oci_parse($conn, $sql);
            oci_bind_by_name($stid, ':prefix_id', $update['newPrefixId']);
            oci_bind_by_name($stid, ':inventory_id', $update['inventoryId']);
            
            if (oci_execute($stid, OCI_NO_AUTO_COMMIT)) {
                $count++;
            }
            oci_free_statement($stid);
        }
        
        oci_commit($conn);
        oci_close($conn);
        
        echo json_encode([
            'success' => true,
            'count' => $count,
            'message' => "อัพเดทสำเร็จ $count รายการ"
        ], JSON_UNESCAPED_UNICODE);
        
    } catch(Exception $e) {
        oci_rollback($conn);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}
?>