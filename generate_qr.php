<?php
header('Content-Type: application/json');
include 'config.php';
require_once 'phpqrcode/qrlib.php'; // ต้องมี library PHPQRCode

try {
    // ตรวจสอบข้อมูลที่จำเป็น
    if (!isset($_POST['prefixCode']) || !isset($_POST['productId']) || !isset($_POST['weight']) || !isset($_POST['receiveDate'])) {
        throw new Exception('ข้อมูลไม่ครบถ้วน');
    }
    
    $prefixCode = strtoupper(trim($_POST['prefixCode'])); // รับ prefixCode แทน prefixId
    $productId = $_POST['productId'];
    $weight = floatval($_POST['weight']);
    $receiveDate = $_POST['receiveDate'];
    
    // เริ่ม Transaction
    oci_execute(oci_parse($conn, "BEGIN NULL; END;"), OCI_NO_AUTO_COMMIT);
    
    // ดึงข้อมูล Prefix จาก PREFIX_CODE
    $prefixQuery = "SELECT PREFIX_ID, PREFIX_CODE FROM PREFIXES WHERE PREFIX_CODE = :prefixCode";
    $prefixStid = oci_parse($conn, $prefixQuery);
    oci_bind_by_name($prefixStid, ':prefixCode', $prefixCode);
    oci_execute($prefixStid);
    $prefixRow = oci_fetch_assoc($prefixStid);
    
    if (!$prefixRow) {
        throw new Exception('ไม่พบข้อมูล Prefix');
    }
    
    $prefixId = $prefixRow['PREFIX_ID'];
    $prefixCode = $prefixRow['PREFIX_CODE'];
    oci_free_statement($prefixStid);
    
    // ดึงข้อมูลสินค้า
    $productQuery = "SELECT PRODUCT_NAME FROM PRODUCTS WHERE PRODUCT_ID = :productId";
    $productStid = oci_parse($conn, $productQuery);
    oci_bind_by_name($productStid, ':productId', $productId);
    oci_execute($productStid);
    $productRow = oci_fetch_assoc($productStid);
    
    if (!$productRow) {
        throw new Exception('ไม่พบข้อมูลสินค้า');
    }
    
    $productName = $productRow['PRODUCT_NAME'];
    oci_free_statement($productStid);
    
    // แปลงน้ำหนักจากกิโลกรัมเป็นกรัม
    $weightInGrams = $weight * 1000;
    
    // แปลงวันที่เป็น Oracle Date Format
    $oracleDate = date('Y-m-d', strtotime($receiveDate));
    
    // เพิ่มข้อมูลลงตาราง INVENTORY
    $insertQuery = "INSERT INTO INVENTORY (PREFIX_ID, PRODUCT_ID, WEIGHT, RECEIVE_DATE, STATUS) 
                    VALUES (:prefixId, :productId, :weight, TO_DATE(:receiveDate, 'YYYY-MM-DD'), 'IN_STOCK') 
                    RETURNING INVENTORY_ID INTO :inventoryId";
    
    $stid = oci_parse($conn, $insertQuery);
    oci_bind_by_name($stid, ':prefixId', $prefixId);
    oci_bind_by_name($stid, ':productId', $productId);
    oci_bind_by_name($stid, ':weight', $weightInGrams);
    oci_bind_by_name($stid, ':receiveDate', $oracleDate);
    oci_bind_by_name($stid, ':inventoryId', $inventoryId, 20);
    
    if (!oci_execute($stid, OCI_NO_AUTO_COMMIT)) {
        $error = oci_error($stid);
        throw new Exception($error['message']);
    }
    
    oci_free_statement($stid);
    
    // สร้างรหัสวัตถุดิบแบบใหม่: PREFIX-XXXXXX
    $inventoryCode = $prefixCode . '-' . str_pad($inventoryId, 6, '0', STR_PAD_LEFT);
    
    // สร้าง QR Code
    $qrDir = 'qrcodes/';
    if (!file_exists($qrDir)) {
        mkdir($qrDir, 0777, true);
    }
    
    $qrFile = $qrDir . $inventoryCode . '.png';
    
    // ข้อมูลที่จะใส่ใน QR Code
    $qrData = $inventoryCode;
    
    // สร้าง QR Code
    QRcode::png($qrData, $qrFile, QR_ECLEVEL_L, 10);
    
    // Commit Transaction
    oci_commit($conn);
    oci_close($conn);
    
    // ส่งข้อมูลกลับ
    echo json_encode([
        'success' => true,
        'inventoryId' => $inventoryCode,
        'productName' => $productName,
        'weight' => number_format($weight, 2),
        'receiveDate' => date('d/m/Y', strtotime($receiveDate)),
        'qrUrl' => $qrFile
    ], JSON_UNESCAPED_UNICODE);
    
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