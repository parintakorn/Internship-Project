<?php
header('Content-Type: application/json; charset=utf-8');
include 'config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prefixCode = strtoupper(trim($_POST['prefixCode'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    
    if (empty($prefixCode) || empty($description)) {
        echo json_encode([
            'success' => false,
            'error' => 'กรุณากรอกข้อมูลให้ครบถ้วน'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    try {
        // ตรวจสอบว่ามี Prefix นี้อยู่แล้วหรือไม่
        $checkSql = "SELECT COUNT(*) AS CNT FROM PREFIXES WHERE PREFIX_CODE = :prefix_code";
        $checkStid = oci_parse($conn, $checkSql);
        oci_bind_by_name($checkStid, ':prefix_code', $prefixCode);
        oci_execute($checkStid);
        $row = oci_fetch_assoc($checkStid);
        
        if ($row['CNT'] > 0) {
            echo json_encode([
                'success' => false,
                'error' => 'มี Prefix นี้อยู่ในระบบแล้ว'
            ], JSON_UNESCAPED_UNICODE);
            oci_free_statement($checkStid);
            oci_close($conn);
            exit;
        }
        oci_free_statement($checkStid);
        
        // เพิ่ม Prefix ใหม่
        $sql = "INSERT INTO PREFIXES (PREFIX_CODE, DESCRIPTION, CREATED_DATE) 
                VALUES (:prefix_code, :description, SYSDATE)";
        
        $stid = oci_parse($conn, $sql);
        oci_bind_by_name($stid, ':prefix_code', $prefixCode);
        oci_bind_by_name($stid, ':description', $description);
        $result = oci_execute($stid);
        
        if ($result) {
            oci_commit($conn);
            echo json_encode([
                'success' => true,
                'message' => 'เพิ่ม Prefix สำเร็จ'
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