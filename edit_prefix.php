<?php
header('Content-Type: application/json; charset=utf-8');
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prefixId = $_POST['prefixId'] ?? '';
    $newDescription = trim($_POST['description'] ?? '');
    
    if (empty($prefixId) || empty($newDescription)) {
        echo json_encode([
            'success' => false,
            'error' => 'กรุณากรอกข้อมูลให้ครบถ้วน'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    try {
        // อัพเดท Description (ไม่ให้แก้ PREFIX_CODE เพราะมีการใช้งานอยู่แล้ว)
        $sql = "UPDATE PREFIXES 
                SET DESCRIPTION = :description 
                WHERE PREFIX_ID = :prefix_id";
        
        $stid = oci_parse($conn, $sql);
        oci_bind_by_name($stid, ':description', $newDescription);
        oci_bind_by_name($stid, ':prefix_id', $prefixId);
        
        if (!oci_execute($stid)) {
            $error = oci_error($stid);
            throw new Exception($error['message']);
        }
        
        oci_commit($conn);
        oci_free_statement($stid);
        oci_close($conn);
        
        echo json_encode([
            'success' => true,
            'message' => 'แก้ไข Prefix สำเร็จ'
        ], JSON_UNESCAPED_UNICODE);
        
    } catch(Exception $e) {
        if (isset($conn)) {
            oci_rollback($conn);
            oci_close($conn);
        }
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}
?>