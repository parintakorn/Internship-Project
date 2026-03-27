<?php
header('Content-Type: application/json; charset=utf-8');
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inventoryId = $_POST['inventoryId'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    if (empty($inventoryId) || empty($amount)) {
        echo json_encode([
            'success' => false,
            'error' => 'กรุณากรอกข้อมูลให้ครบถ้วน'
        ]);
        exit;
    }
    
    try {
        // ตรวจสอบสถานะและน้ำหนักคงเหลือ
        $queryCheck = "SELECT STATUS, WEIGHT FROM INVENTORY WHERE INVENTORY_ID = :inventory_id";
        $stidCheck = oci_parse($conn, $queryCheck);
        oci_bind_by_name($stidCheck, ':inventory_id', $inventoryId);
        oci_execute($stidCheck);
        $inventory = oci_fetch_assoc($stidCheck);
        oci_free_statement($stidCheck);
        
        if (!$inventory) {
            throw new Exception('ไม่พบข้อมูลวัตถุดิบ');
        }
        
        if ($inventory['STATUS'] !== 'IN_STOCK') {
            throw new Exception('วัตถุดิบนี้ถูกเบิกออกแล้ว');
        }
        
        if ($amount > $inventory['WEIGHT']) {
            throw new Exception('จำนวนที่เบิกมากกว่าที่มีในคลัง (คงเหลือ ' . $inventory['WEIGHT'] . ' ก.)');
        }
        
        // บันทึกประวัติการเบิก
        $queryWithdraw = "INSERT INTO WITHDRAWAL_HISTORY 
                          (WITHDRAWAL_ID, INVENTORY_ID, AMOUNT, REASON, WITHDRAW_DATE) 
                          VALUES 
                          ((SELECT NVL(MAX(WITHDRAWAL_ID), 0) + 1 FROM WITHDRAWAL_HISTORY), 
                           :inventory_id, :amount, :reason, SYSDATE)";
        
        $stidWithdraw = oci_parse($conn, $queryWithdraw);
        oci_bind_by_name($stidWithdraw, ':inventory_id', $inventoryId);
        oci_bind_by_name($stidWithdraw, ':amount', $amount);
        oci_bind_by_name($stidWithdraw, ':reason', $reason);
        
        if (!oci_execute($stidWithdraw)) {
            $e = oci_error($stidWithdraw);
            throw new Exception($e['message']);
        }
        oci_free_statement($stidWithdraw);
        
        // อัพเดทสถานะและน้ำหนักคงเหลือ
        $newWeight = $inventory['WEIGHT'] - $amount;
        $newStatus = ($newWeight <= 0) ? 'WITHDRAWN' : 'IN_STOCK';
        
        $queryUpdate = "UPDATE INVENTORY 
                        SET WEIGHT = :new_weight, 
                            STATUS = :new_status,
                            LAST_UPDATED = SYSDATE
                        WHERE INVENTORY_ID = :inventory_id";
        
        $stidUpdate = oci_parse($conn, $queryUpdate);
        oci_bind_by_name($stidUpdate, ':new_weight', $newWeight);
        oci_bind_by_name($stidUpdate, ':new_status', $newStatus);
        oci_bind_by_name($stidUpdate, ':inventory_id', $inventoryId);
        
        if (oci_execute($stidUpdate)) {
            oci_commit($conn);
            echo json_encode([
                'success' => true,
                'message' => 'เบิกวัตถุดิบสำเร็จ',
                'remainingWeight' => $newWeight
            ]);
        } else {
            $e = oci_error($stidUpdate);
            throw new Exception($e['message']);
        }
        
        oci_free_statement($stidUpdate);
        oci_close($conn);
        
    } catch (Exception $e) {
        oci_rollback($conn);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
?>