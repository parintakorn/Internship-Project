<?php
header('Content-Type: application/json; charset=utf-8');
include 'config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prefixId = $_POST['prefixId'] ?? '';
    
    if (empty($prefixId)) {
        echo json_encode([
            'success' => false,
            'error' => 'ไม่พบ Prefix ID'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    try {
        // ตรวจสอบว่ามีการใช้ Prefix นี้ใน inventory หรือไม่
        $checkSql = "SELECT COUNT(*) AS CNT FROM INVENTORY WHERE PREFIX_ID = :prefix_id";
        $checkStid = oci_parse($conn, $checkSql);
        oci_bind_by_name($checkStid, ':prefix_id', $prefixId);
        oci_execute($checkStid);
        $row = oci_fetch_assoc($checkStid);
        
        if ($row['CNT'] > 0) {
            echo json_encode([
                'success' => false,
                'error' => 'ไม่สามารถลบ Prefix นี้ได้ เนื่องจากมีการใช้งานอยู่'
            ], JSON_UNESCAPED_UNICODE);
            oci_free_statement($checkStid);
            oci_close($conn);
            exit;
        }
        oci_free_statement($checkStid);
        
        // ลบ Prefix
        $sql = "DELETE FROM PREFIXES WHERE PREFIX_ID = :prefix_id";
        $stid = oci_parse($conn, $sql);
        oci_bind_by_name($stid, ':prefix_id', $prefixId);
        $result = oci_execute($stid);
        
        if ($result) {
            oci_commit($conn);
            echo json_encode([
                'success' => true,
                'message' => 'ลบ Prefix สำเร็จ'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            $e = oci_error($stid);
            throw new Exception($e['message']);
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