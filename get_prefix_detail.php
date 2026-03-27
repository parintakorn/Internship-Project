<?php
header('Content-Type: application/json; charset=utf-8');
include 'config.php';

if (isset($_GET['id'])) {
    $prefixId = $_GET['id'];
    
    try {
        $sql = "SELECT PREFIX_ID, PREFIX_CODE, DESCRIPTION, CREATED_DATE 
                FROM PREFIXES 
                WHERE PREFIX_ID = :prefix_id";
        
        $stid = oci_parse($conn, $sql);
        oci_bind_by_name($stid, ':prefix_id', $prefixId);
        oci_execute($stid);
        
        $prefix = oci_fetch_assoc($stid);
        
        if ($prefix) {
            echo json_encode([
                'success' => true,
                'prefix' => $prefix
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'ไม่พบข้อมูล Prefix'
            ], JSON_UNESCAPED_UNICODE);
        }
        
        oci_free_statement($stid);
        oci_close($conn);
        
    } catch(Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'ไม่พบ Prefix ID'
    ], JSON_UNESCAPED_UNICODE);
}
?>