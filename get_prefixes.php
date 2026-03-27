<?php
header('Content-Type: application/json; charset=utf-8');
include 'config.php';

try {
    $sql = "SELECT PREFIX_ID, PREFIX_CODE, DESCRIPTION, CREATED_DATE 
            FROM PREFIXES 
            ORDER BY PREFIX_CODE";
    
    $stid = oci_parse($conn, $sql);
    oci_execute($stid);
    
    $prefixes = [];
    while ($row = oci_fetch_assoc($stid)) {
        $prefixes[] = $row;
    }
    
    oci_free_statement($stid);
    oci_close($conn);
    
    echo json_encode([
        'success' => true,
        'prefixes' => $prefixes
    ], JSON_UNESCAPED_UNICODE);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
