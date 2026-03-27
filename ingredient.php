<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // ปิดแสดง error บนหน้า แต่เก็บ log

ob_start();
ob_clean();
include 'connect.php';
date_default_timezone_set('Asia/Bangkok');



// ====================== ฟังก์ชันช่วยเหลือ ======================
function getFileIcon($fileType) {
    $icons = [
        'pdf' => '📕', 'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️',
        'xlsx' => '📗', 'xls' => '📗', 'docx' => '📘', 'doc' => '📘', 'txt' => '📄'
    ];
    return $icons[strtolower($fileType)] ?? '📎';
}

function formatFileSize($bytes) {
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    elseif ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    else return $bytes . ' bytes';
}



// ---------------------- AUTO GENERATE NEXT INGREDIENT ID ----------------------
$stid = oci_parse($conn, "SELECT MAX(INGREDIENTID) AS MAX_ID FROM INGREDIENT");
oci_execute($stid);
$row = oci_fetch_assoc($stid);
$next_id = $row['MAX_ID'] ? $row['MAX_ID'] + 1 : 10001;

// ---------------------- RECORD INGREDIENT TRANSACTION (IN/OUT) ----------------------
if (isset($_POST['record_transaction'])) {
    $ingredientId = $_POST['ingredient_id'];
    $transactionType = $_POST['transaction_type'];
    $quantity = floatval($_POST['transaction_qty']);
    $transactionNote = $_POST['transaction_note'];
    $transactionImage = '';
    
    if (isset($_FILES['transaction_image']) && $_FILES['transaction_image']['error'] == 0) {
        $uploadDir = 'transaction_images/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileExtension = strtolower(pathinfo($_FILES['transaction_image']['name'], PATHINFO_EXTENSION));
        $newFileName = 'TRANS_' . $ingredientId . '_' . time() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $newFileName;
        
        if (move_uploaded_file($_FILES['transaction_image']['tmp_name'], $uploadPath)) {
            $transactionImage = $uploadPath;
        }
    }
    
    $sql = "INSERT INTO INGREDIENT_TRANSACTION (TRANSACTION_ID, INGREDIENTID, TRANSACTION_TYPE, QUANTITY, TRANSACTION_NOTE, TRANSACTION_IMAGE, TRANSACTION_DATE)
            VALUES (TRANSACTION_SEQ.NEXTVAL, :id, :type, :qty, :note, :img, SYSDATE)";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":id", $ingredientId);
    oci_bind_by_name($stid, ":type", $transactionType);
    oci_bind_by_name($stid, ":qty", $quantity);
    oci_bind_by_name($stid, ":note", $transactionNote);
    oci_bind_by_name($stid, ":img", $transactionImage);
    oci_execute($stid);
    
    if ($transactionType == 'IN') {
        $sql2 = "UPDATE INGREDIENT SET QTYONHAND = QTYONHAND + :qty WHERE INGREDIENTID = :id";
    } else {
        $sql2 = "UPDATE INGREDIENT SET QTYONHAND = QTYONHAND - :qty WHERE INGREDIENTID = :id";
    }
    $stid2 = oci_parse($conn, $sql2);
    oci_bind_by_name($stid2, ":qty", $quantity);
    oci_bind_by_name($stid2, ":id", $ingredientId);
    oci_execute($stid2);
    
    oci_commit($conn);
    header("Location: ingredient.php?edit_id=" . $ingredientId . "&msg=transaction_recorded");
    exit();
}

// ---------------------- DELETE TRANSACTION ----------------------
if (isset($_GET['delete_transaction'])) {
    $transactionId = $_GET['delete_transaction'];
    $ingredientId = $_GET['ingredient_id'];
    
    // ดึงข้อมูล transaction ก่อนลบ
    $sqlGet = "SELECT TRANSACTION_TYPE, QUANTITY, TRANSACTION_IMAGE FROM INGREDIENT_TRANSACTION WHERE TRANSACTION_ID = :tid";
    $stidGet = oci_parse($conn, $sqlGet);
    oci_bind_by_name($stidGet, ":tid", $transactionId);
    oci_execute($stidGet);
    $trans = oci_fetch_assoc($stidGet);
    
    if ($trans) {
        // คืนสต็อก (ถ้าเป็น IN ให้ลบออก, ถ้าเป็น OUT ให้เพิ่มกลับ)
        if ($trans['TRANSACTION_TYPE'] == 'IN') {
            $sql2 = "UPDATE INGREDIENT SET QTYONHAND = QTYONHAND - :qty WHERE INGREDIENTID = :id";
        } else {
            $sql2 = "UPDATE INGREDIENT SET QTYONHAND = QTYONHAND + :qty WHERE INGREDIENTID = :id";
        }
        $stid2 = oci_parse($conn, $sql2);
        $qty = floatval($trans['QUANTITY']);
        oci_bind_by_name($stid2, ":qty", $qty);
        oci_bind_by_name($stid2, ":id", $ingredientId);
        oci_execute($stid2);
        
        // ลบรูปภาพถ้ามี
        if (!empty($trans['TRANSACTION_IMAGE']) && file_exists($trans['TRANSACTION_IMAGE'])) {
            unlink($trans['TRANSACTION_IMAGE']);
        }
        
        // ลบ transaction
        $sqlDel = "DELETE FROM INGREDIENT_TRANSACTION WHERE TRANSACTION_ID = :tid";
        $stidDel = oci_parse($conn, $sqlDel);
        oci_bind_by_name($stidDel, ":tid", $transactionId);
        oci_execute($stidDel);
        
        oci_commit($conn);
        header("Location: ingredient.php?edit_id=" . $ingredientId . "&msg=transaction_deleted");
        exit();
    }
}

// ---------------------- ADD DATA ----------------------
// ---------------------- ADD DATA (แก้ใหม่ - รองรับไฟล์แนบ) ----------------------
if (isset($_POST['add'])) {
    $imagePath = '';
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $uploadDir = 'ingredient_images/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $newFileName = 'ING_' . $_POST['id'] . '_' . time() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $newFileName;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
            $imagePath = $uploadPath;
        }
    }
    
    $sql = "INSERT INTO INGREDIENT (INGREDIENTID, INGREDIENTNAME, QTYONHAND, UNIT, QRCODE, BARCODE, IMAGEPATH)
            VALUES (:id, :name, :qty, :unit, :qr, :bar, :img)";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":id", $_POST['id']);
    oci_bind_by_name($stid, ":name", $_POST['name']);
    oci_bind_by_name($stid, ":qty", $_POST['qty']);
    oci_bind_by_name($stid, ":unit", $_POST['unit']);
    oci_bind_by_name($stid, ":qr", $_POST['qrcode']);
    oci_bind_by_name($stid, ":bar", $_POST['barcode']);
    oci_bind_by_name($stid, ":img", $imagePath);
    oci_execute($stid);
    
    // จัดการไฟล์แนบ (ถ้ามี)
    if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] == 0) {
        $file = $_FILES['attachment_file'];
        $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'xlsx', 'xls', 'docx', 'doc', 'txt'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
        $fileSize = $file['size'];
        $maxSize = 10 * 1024 * 1024;
        
        if (in_array($fileExtension, $allowed_types) && $fileSize <= $maxSize) {
            $uploadDir = 'ingredient_attachments/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $newFileName = 'ATTACH_' . $_POST['id'] . '_' . time() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $description = trim($_POST['attachment_description']);
                
                $sql_attach = "INSERT INTO INGREDIENT_ATTACHMENTS 
                        (ATTACHMENT_ID, INGREDIENTID, FILE_NAME, FILE_PATH, FILE_TYPE, FILE_SIZE, DESCRIPTION, UPLOAD_DATE)
                        VALUES (ATTACHMENT_SEQ.NEXTVAL, :id, :fname, :fpath, :ftype, :fsize, :desc, SYSDATE)";
                $stid_attach = oci_parse($conn, $sql_attach);
                oci_bind_by_name($stid_attach, ":id", $_POST['id']);
                oci_bind_by_name($stid_attach, ":fname", $originalName);
                oci_bind_by_name($stid_attach, ":fpath", $uploadPath);
                oci_bind_by_name($stid_attach, ":ftype", $fileExtension);
                oci_bind_by_name($stid_attach, ":fsize", $fileSize);
                oci_bind_by_name($stid_attach, ":desc", $description);
                oci_execute($stid_attach);
            }
        }
    }
    
    oci_commit($conn);
    header("Location: ingredient.php");
    exit();
}

// ---------------------- DELETE ----------------------
if (isset($_GET['delete'])) {
    $sqlImg = "SELECT IMAGEPATH FROM INGREDIENT WHERE INGREDIENTID = :id";
    $stidImg = oci_parse($conn, $sqlImg);
    oci_bind_by_name($stidImg, ":id", $_GET['delete']);
    oci_execute($stidImg);
    $imgRow = oci_fetch_assoc($stidImg);
    if ($imgRow && !empty($imgRow['IMAGEPATH']) && file_exists($imgRow['IMAGEPATH'])) {
        unlink($imgRow['IMAGEPATH']);
    }
    
    $sql = "DELETE FROM INGREDIENT WHERE INGREDIENTID = :id";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":id", $_GET['delete']);
    oci_execute($stid);
    oci_commit($conn);
    header("Location: ingredient.php");
    exit();
}

// ---------------------- RECORD DAMAGED ITEM ----------------------
if (isset($_POST['record_damage'])) {
    $ingredientId = $_POST['ingredient_id'];
    $damagedQty = floatval($_POST['damaged_qty']);
    $damagedNote = $_POST['damaged_note'];
    $damagedImage = '';
    
    if (isset($_FILES['damaged_image']) && $_FILES['damaged_image']['error'] == 0) {
        $uploadDir = 'damaged_images/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileExtension = strtolower(pathinfo($_FILES['damaged_image']['name'], PATHINFO_EXTENSION));
        $newFileName = 'DAMAGED_' . $ingredientId . '_' . time() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $newFileName;
        
        if (move_uploaded_file($_FILES['damaged_image']['tmp_name'], $uploadPath)) {
            $damagedImage = $uploadPath;
        }
    }
    
    $sql = "INSERT INTO INGREDIENT_DAMAGE (INGREDIENTID, DAMAGED_QTY, DAMAGED_NOTE, DAMAGED_IMAGE, DAMAGED_DATE)
            VALUES (:id, :qty, :note, :img, SYSDATE)";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":id", $ingredientId);
    oci_bind_by_name($stid, ":qty", $damagedQty);
    oci_bind_by_name($stid, ":note", $damagedNote);
    oci_bind_by_name($stid, ":img", $damagedImage);
    oci_execute($stid);
    
    $sql2 = "UPDATE INGREDIENT SET QTYONHAND = QTYONHAND - :qty WHERE INGREDIENTID = :id";
    $stid2 = oci_parse($conn, $sql2);
    oci_bind_by_name($stid2, ":qty", $damagedQty);
    oci_bind_by_name($stid2, ":id", $ingredientId);
    oci_execute($stid2);
    
    oci_commit($conn);
    header("Location: ingredient.php?edit_id=" . $ingredientId . "&msg=damage_recorded");
    exit();
}

// ---------------------- UPDATE ----------------------
if (isset($_POST['edit'])) {
    $imagePath = $_POST['existing_image'];
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $uploadDir = 'ingredient_images/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        if (!empty($imagePath) && file_exists($imagePath)) unlink($imagePath);
        
        $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $newFileName = 'ING_' . $_POST['id'] . '_' . time() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $newFileName;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
            $imagePath = $uploadPath;
        }
    }
    
    $sql = "UPDATE INGREDIENT SET INGREDIENTNAME = :name, QTYONHAND = :qty, UNIT = :unit,
                QRCODE = :qr, BARCODE = :bar, IMAGEPATH = :img WHERE INGREDIENTID = :id";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":name", $_POST['name']);
    oci_bind_by_name($stid, ":qty", $_POST['qty']);
    oci_bind_by_name($stid, ":unit", $_POST['unit']);
    oci_bind_by_name($stid, ":qr", $_POST['qrcode']);
    oci_bind_by_name($stid, ":bar", $_POST['barcode']);
    oci_bind_by_name($stid, ":img", $imagePath);
    oci_bind_by_name($stid, ":id", $_POST['id']);
    oci_execute($stid);
    header("Location: ingredient.php");
    exit();
}

// ---------------------- ADD QUANTITY ----------------------
if (isset($_POST['add_qty_table'])) {
    $add_qty = floatval($_POST['add_qty']);
    $id = $_POST['id'];
    $sql = "UPDATE INGREDIENT SET QTYONHAND = QTYONHAND + :add_qty WHERE INGREDIENTID = :id";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":add_qty", $add_qty);
    oci_bind_by_name($stid, ":id", $id);
    oci_execute($stid);
    header("Location: ingredient.php");
    exit();
}

// ---------------------- SAVE STOCK COUNT ----------------------
// ---------------------- SAVE STOCK COUNT (บันทึกส่วนต่างอย่างเดียว) ----------------------


// ---------------------- UPLOAD ATTACHMENT ----------------------
// ---------------------- UPLOAD ATTACHMENT ----------------------
// ---------------------- UPLOAD ATTACHMENT ----------------------
if (isset($_POST['upload_attachment'])) {
    $ingredientId = $_POST['ingredient_id'];
    $description = trim($_POST['attachment_description']);
    
    if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] == 0) {
        $file = $_FILES['attachment_file'];
        $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'xlsx', 'xls', 'docx', 'doc', 'txt'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
        $fileSize = $file['size'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        if (!in_array($fileExtension, $allowed_types)) {
            header("Location: ingredient.php?edit_id=" . $ingredientId . "&error=invalid_type");
            exit();
        }
        
        if ($fileSize > $maxSize) {
            header("Location: ingredient.php?edit_id=" . $ingredientId . "&error=file_too_large");
            exit();
        }
        
        $uploadDir = 'ingredient_attachments/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $newFileName = 'ATTACH_' . $ingredientId . '_' . time() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $newFileName;
        
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $sql = "INSERT INTO INGREDIENT_ATTACHMENTS 
                    (ATTACHMENT_ID, INGREDIENTID, FILE_NAME, FILE_PATH, FILE_TYPE, FILE_SIZE, DESCRIPTION, UPLOAD_DATE)
                    VALUES (ATTACHMENT_SEQ.NEXTVAL, :id, :fname, :fpath, :ftype, :fsize, :desc, SYSDATE)";
            $stid = oci_parse($conn, $sql);
            oci_bind_by_name($stid, ":id", $ingredientId);
            oci_bind_by_name($stid, ":fname", $originalName);
            oci_bind_by_name($stid, ":fpath", $uploadPath);
            oci_bind_by_name($stid, ":ftype", $fileExtension);
            oci_bind_by_name($stid, ":fsize", $fileSize);
            oci_bind_by_name($stid, ":desc", $description);
            
            if (oci_execute($stid, OCI_COMMIT_ON_SUCCESS)) {
                oci_free_statement($stid);
                header("Location: ingredient.php?edit_id=" . $ingredientId . "&msg=attachment_uploaded");
                exit();
            } else {
                unlink($uploadPath);
                oci_free_statement($stid);
                header("Location: ingredient.php?edit_id=" . $ingredientId . "&error=db_error");
                exit();
            }
        } else {
            header("Location: ingredient.php?edit_id=" . $ingredientId . "&error=upload_failed");
            exit();
        }
    } else {
        header("Location: ingredient.php?edit_id=" . $ingredientId . "&error=no_file");
        exit();
    }
}



// ---------------------- DELETE ATTACHMENT ----------------------
if (isset($_GET['delete_attachment'])) {
    $attachmentId = $_GET['delete_attachment'];
    $ingredientId = $_GET['ingredient_id'];
    
    $sql = "SELECT FILE_PATH FROM INGREDIENT_ATTACHMENTS WHERE ATTACHMENT_ID = :id";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":id", $attachmentId);
    oci_execute($stid);
    $file = oci_fetch_assoc($stid);
    
    if ($file && file_exists($file['FILE_PATH'])) unlink($file['FILE_PATH']);
    
    $sql2 = "DELETE FROM INGREDIENT_ATTACHMENTS WHERE ATTACHMENT_ID = :id";
    $stid2 = oci_parse($conn, $sql2);
    oci_bind_by_name($stid2, ":id", $attachmentId);
    oci_execute($stid2);
    oci_commit($conn);
    
    header("Location: ingredient.php?edit_id=" . $ingredientId . "&msg=attachment_deleted");
    exit();
}
if (isset($_GET['report']) && isset($_GET['ingredient_id']) && isset($_GET['period'])) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $nls_stid = oci_parse($conn, "ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'");
    oci_execute($nls_stid);

    $ingredientId = $_GET['ingredient_id'];
    $period = $_GET['period'];

    $date_cond = "";
    switch ($period) {
    case 'daily':
        $date_cond = "TRUNC(%s) = TRUNC(SYSDATE)";
        break;
    case 'monthly':
        $date_cond = "TRUNC(%s, 'MM') = TRUNC(SYSDATE, 'MM')";
        break;
    case 'yearly':
        $date_cond = "EXTRACT(YEAR FROM %s) = EXTRACT(YEAR FROM SYSDATE)";
        break;
    default:
        echo json_encode(['error' => 'ช่วงเวลาไม่ถูกต้อง']);
        exit;
}

    $sql = "
    SELECT 
        TO_CHAR(it.TRANSACTION_ID) AS TRANSACTION_ID,
        it.TRANSACTION_TYPE,
        it.QUANTITY,
        it.TRANSACTION_NOTE,
        TO_CHAR(it.TRANSACTION_DATE, 'DD/MM/YYYY') || ' ' || 
        NVL(TO_CHAR(it.TRANSACTION_DATE, 'HH24:MI:SS'), '00:00:00') AS TRANSACTION_DATE,
        'transaction' AS SOURCE
    FROM INGREDIENT_TRANSACTION it
    WHERE it.INGREDIENTID = :id_trans
      AND " . sprintf($date_cond, 'it.TRANSACTION_DATE') . "

    UNION ALL

    SELECT 
        oi.ORDERID || '_' || oi.MENUID AS TRANSACTION_ID,
        'OUT_FROM_SALE' AS TRANSACTION_TYPE,
        (oi.QUANTITY * r.QTYUSED) AS QUANTITY,
        'จาก Order ' || t.ORDERID || ' (' || m.MENUNAME || ')' AS TRANSACTION_NOTE,
        TO_CHAR(t.ORDERDATE, 'DD/MM/YYYY') || ' ' || 
        NVL(t.ORDERTIME, '00:00:00') AS TRANSACTION_DATE,
        'order' AS SOURCE
    FROM ORDER_ITEM oi
    JOIN RECIPE r ON oi.MENUID = r.MENUID AND r.INGREDIENTID = :id_order1
    JOIN MENU m ON oi.MENUID = m.MENUID
    JOIN TRANSACTION t ON oi.ORDERID = t.ORDERID
    WHERE r.INGREDIENTID = :id_order1
      AND " . sprintf($date_cond, 't.ORDERDATE') . "
      AND t.ORDERDATE IS NOT NULL

    UNION ALL

    SELECT 
        t.ORDERID || '_' || cm.MENUID || '_COURSE' AS TRANSACTION_ID,
        'OUT_FROM_OMAKASE' AS TRANSACTION_TYPE,
        (cm.QUANTITY * r.QTYUSED * os.PERSON_COUNT) AS QUANTITY,
        'จาก Omakase Order ' || t.ORDERID || ' (' || m.MENUNAME || ') [' || os.PERSON_COUNT || ' คน]' AS TRANSACTION_NOTE,
        TO_CHAR(t.ORDERDATE, 'DD/MM/YYYY') || ' ' || 
        NVL(t.ORDERTIME, '00:00:00') AS TRANSACTION_DATE,
        'omakase' AS SOURCE
    FROM TRANSACTION t
    JOIN ORDER_SECTION os ON t.ORDERID = os.ORDERID
    JOIN COURSE_MENU cm ON os.COURSEID = cm.COURSEID
    JOIN RECIPE r ON cm.MENUID = r.MENUID AND r.INGREDIENTID = :id_order2
    JOIN MENU m ON cm.MENUID = m.MENUID
    WHERE r.INGREDIENTID = :id_order2
      AND os.MENUTYPEID = 2
      AND " . sprintf($date_cond, 't.ORDERDATE') . "
      AND t.ORDERDATE IS NOT NULL
    ORDER BY TRANSACTION_DATE DESC
    ";

    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":id_trans", $ingredientId);
    oci_bind_by_name($stid, ":id_order1", $ingredientId);
    oci_bind_by_name($stid, ":id_order2", $ingredientId);

    if (!oci_execute($stid)) {
        echo json_encode(['error' => 'Execute error: ' . oci_error($stid)['message']]);
        exit;
    }

    $data = [
        'ingredient_name' => '',
        'unit' => '',
        'current_stock' => 0,
        'summary' => ['in' => 0, 'out_general' => 0, 'out_sale' => 0, 'total_out' => 0],
        'data' => []
    ];

    $first = true;
    while ($row = oci_fetch_assoc($stid)) {
    if ($first) {
        $first = false;
        $ing_stid = oci_parse($conn, "SELECT INGREDIENTNAME, UNIT, QTYONHAND FROM INGREDIENT WHERE INGREDIENTID = :id");
        oci_bind_by_name($ing_stid, ":id", $ingredientId);
        oci_execute($ing_stid);
        $ing = oci_fetch_assoc($ing_stid);
        $data['ingredient_name'] = $ing['INGREDIENTNAME'] ?? 'ไม่พบชื่อ';
        $data['unit'] = $ing['UNIT'] ?? '-';
        $data['current_stock'] = floatval($ing['QTYONHAND'] ?? 0);
        oci_free_statement($ing_stid);
    }

    $qty = floatval($row['QUANTITY']);
    $source = $row['SOURCE'];
    $is_out = ($source === 'order' || $source === 'omakase' || $row['TRANSACTION_TYPE'] !== 'IN');

    // แก้ type_display ให้แสดงชัดเจนขึ้น
    if ($source === 'omakase') {
        $type_display = '🍱 หักจาก Omakase Course';
    } else if ($source === 'order') {
        $type_display = '📦 หักจาก A La Carte';
    } else if ($is_out) {
        $type_display = '📤 เบิกออกทั่วไป';
    } else {
        $type_display = '📥 รับเข้า';
    }

    // สรุปยอด
    if (!$is_out) {
        $data['summary']['in'] += $qty;
    } else {
        if ($source === 'order' || $source === 'omakase') {
            $data['summary']['out_sale'] += abs($qty);
        } else {
            $data['summary']['out_general'] += abs($qty);
        }
    }

    $data['data'][] = [
        'date'     => $row['TRANSACTION_DATE'],
        'type'     => $type_display,
        'quantity' => ($is_out ? '-' : '+') . number_format(abs($qty), 2),
        'note'     => $row['TRANSACTION_NOTE'] ?? '-',
        'source'   => $source
    ];
}

$data['summary']['total_out'] = $data['summary']['out_general'] + $data['summary']['out_sale'];

// คำนวณสต็อกย้อนหลัง
$back_stock = $data['current_stock'];
if ($period === 'daily') {
    $back_stock += $data['summary']['out_sale'];
    $back_label = 'สต็อกเมื่อวาน';
    $back_desc = '(ก่อนวันนี้)';
} elseif ($period === 'monthly') {
    $back_stock += $data['summary']['out_sale'];
    $back_label = 'สต็อกเดือนก่อน';
    $back_desc = '(ก่อนเดือนนี้)';
} elseif ($period === 'yearly') {
    $back_stock += $data['summary']['out_sale'];
    $back_label = 'สต็อกปีที่แล้ว';
    $back_desc = '(ข้อมูลทั้งปี 2025)';
}

$data['summary']['back_stock'] = $back_stock;
$data['summary']['back_label'] = $back_label;
$data['summary']['back_desc'] = $back_desc;

echo json_encode($data, JSON_UNESCAPED_UNICODE);
exit;
}

    
?>
<!-- วางต่อจาก PHP ข้างบน หลังจาก ?> -->

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ingredient Management</title>

<script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.5/JsBarcode.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

<!-- วาง CSS ทั้งหมดที่ให้มาในโค้ดเดิมตรงนี้ -->

<style>


.btn-download {
    background: #2196f3;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}

.btn-delete-attach {
    background: #e74c3c;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}

.file-icon {
    font-size: 24px;
    margin-right: 10px;
}

body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #fafafa; }
.top-bar { width: 100%; background-color: rgba(255,255,255,0.95); padding: 15px 20px; display: flex; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.15); position: fixed; top: 0; left: 0; z-index: 20; backdrop-filter: blur(10px); }
.menu-btn, .back-btn { font-size: 24px; margin-right: 15px; cursor: pointer; padding: 8px 12px; border-radius: 8px; border: none; background: #667eea; color: white; transition: all 0.3s; display: flex; align-items: center; justify-content: center; }
.menu-btn:hover, .back-btn:hover { background: #5568d3; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
#sidebar { width: 280px; height: 100vh; position: fixed; top: 0; left: -280px; background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%); color: white; transition: left 0.3s ease; padding-top: 60px; z-index: 1000; box-shadow: 4px 0 15px rgba(0,0,0,0.3); overflow-y: auto; }
#sidebar.active { left: 0; }
.sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
.sidebar-header h3 { font-size: 24px; margin-bottom: 5px; color: #fff; }
.sidebar-header p { font-size: 12px; color: rgba(255,255,255,0.7); }
#sidebar a { display: flex; align-items: center; padding: 15px 25px; text-decoration: none; color: rgba(255,255,255,0.9); font-size: 16px; transition: all 0.3s; border-left: 3px solid transparent; }
#sidebar a:hover { background: rgba(255,255,255,0.1); border-left-color: #667eea; padding-left: 30px; }
.overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; }
.overlay.active { display: block; }
.container { margin-top: 120px; margin-left: 30px; padding-bottom: 50px; }
.form-section { background: white; padding: 25px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 700px; }
.form-section h3 { margin-top: 0; color: #333; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
.form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box; }
.form-group textarea { min-height: 80px; resize: vertical; font-family: Arial, sans-serif; }
.form-group input:focus, .form-group textarea:focus { outline: none; border-color: #3498db; }
.code-hint { font-size: 12px; color: #7f8c8d; margin-top: 5px; }
.generate-btn { background: #9b59b6; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; margin-top: 5px; margin-right: 5px; }
.generate-btn:hover { background: #8e44ad; }
.scan-btn { background: #e74c3c; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; margin-top: 5px; }
.scan-btn:hover { background: #c0392b; }
.image-preview { max-width: 150px; max-height: 150px; margin-top: 10px; border: 2px solid #ddd; border-radius: 4px; display: none; }
.image-preview.show { display: block; }
table { background: white; border-collapse: collapse; width: 98%; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
table, th, td { border: 1px solid #ddd; }
th, td { padding: 10px 8px; text-align: center; font-size: 13px; }
th { background: #3498db; color: white; font-weight: bold; }
tr:hover { background: #f5f5f5; }
.btn { padding: 8px 15px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; transition: all 0.2s; }
.btn-success { background: #27ae60; color: white; }
.btn-success:hover { background: #229954; }
.btn-warning { background: #f39c12; color: white; }
.btn-danger { background: #e74c3c; color: white; }
.btn-secondary { background: #95a5a6; color: white; }
.btn-primary { background: #3498db; color: white; }
.btn-primary:hover { background: #2980b9; }
.action-buttons { display: flex; gap: 5px; justify-content: center; align-items: center; flex-wrap: wrap; }
.qty-badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 12px; }
.qty-low { background: #e74c3c; color: white; }
.qty-medium { background: #f39c12; color: white; }
.qty-high { background: #27ae60; color: white; }
.page-header { display: flex; align-items: center; margin-bottom: 20px; }
.page-header .icon { font-size: 24px; margin-right: 10px; }
.qty-input { width: 60px; padding: 5px; margin-right: 5px; border-radius: 4px; border: 1px solid #ccc; }
.stock-count-input { width: 70px; padding: 5px; border-radius: 4px; border: 2px solid #3498db; font-weight: bold; text-align: center; }
.diff-badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-weight: bold; min-width: 60px; font-size: 12px; }
.diff-positive { background: #27ae60; color: white; }
.diff-negative { background: #e74c3c; color: white; }
.diff-neutral { background: #95a5a6; color: white; }
.success-msg { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; max-width: 700px; }
.code-display { font-family: 'Courier New', monospace; font-size: 12px; color: #2c3e50; background: #ecf0f1; padding: 4px 8px; border-radius: 4px; display: inline-block; }
.code-icon { margin-right: 5px; }
.product-img { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 2px solid #ddd; cursor: pointer; transition: transform 0.2s; }
.product-img:hover { transform: scale(1.1); }
.no-image { width: 50px; height: 50px; background: #ecf0f1; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #95a5a6; font-size: 20px; }

/* Transaction Section Styles */
.transaction-section {
    background: #e8f5e9;
    border: 2px solid #4caf50;
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
}

.transaction-section h4 {
    margin-top: 0;
    color: #2e7d32;
    display: flex;
    align-items: center;
    gap: 8px;
}

.transaction-type-selector {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.type-btn {
    flex: 1;
    padding: 12px;
    border: 2px solid #ddd;
    background: white;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.3s;
}

.type-btn:hover {
    transform: translateY(-2px);
}

.type-btn.in {
    border-color: #4caf50;
    color: #4caf50;
}

.type-btn.out {
    border-color: #ff9800;
    color: #ff9800;
}

.type-btn.active.in {
    background: #4caf50;
    color: white;
}

.type-btn.active.out {
    background: #ff9800;
    color: white;
}

.transaction-history {
    background: white;
    padding: 15px;
    border-radius: 8px;
    margin-top: 15px;
    max-height: 400px;
    overflow-y: auto;
}

.transaction-item {
    padding: 12px;
    background: #f5f5f5;
    margin-bottom: 10px;
    border-radius: 4px;
    border-left: 4px solid #ddd;
    position: relative;
}

.transaction-item.in {
    border-left-color: #4caf50;
    background: #f1f8f4;
}

.transaction-item.out {
    border-left-color: #ff9800;
    background: #fff8f0;
}

.transaction-item:hover {
    background: #e8e8e8;
}

.transaction-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.transaction-qty {
    font-size: 18px;
    font-weight: bold;
}

.transaction-qty.in {
    color: #4caf50;
}

.transaction-qty.out {
    color: #ff9800;
}

.transaction-date {
    font-size: 11px;
    color: #7f8c8d;
}

.transaction-type-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
    margin-left: 10px;
}

.transaction-type-badge.in {
    background: #4caf50;
    color: white;
}

.transaction-type-badge.out {
    background: #ff9800;
    color: white;
}

.transaction-note {
    color: #555;
    font-size: 13px;
    margin: 5px 0;
}

.transaction-img-thumb {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 4px;
    border: 2px solid #ddd;
    cursor: pointer;
    margin-top: 8px;
}

.transaction-img-thumb:hover {
    transform: scale(1.05);
}

/* ปุ่มลบ Transaction */
.btn-delete-transaction {
    background: #e74c3c;
    color: white;
    border: none;
    padding: 4px 8px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 11px;
    position: absolute;
    top: 8px;
    right: 8px;
    opacity: 0.7;
    transition: all 0.3s;
}

.btn-delete-transaction:hover {
    opacity: 1;
    transform: scale(1.1);
}


/* Barcode Scanner Modal */
.scanner-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 9999; }
.scanner-modal.active { display: flex; align-items: center; justify-content: center; }
.scanner-content { background: white; padding: 20px; border-radius: 8px; max-width: 600px; width: 90%; }
.scanner-content h3 { margin-top: 0; }
#scanner-container { width: 100%; height: 400px; background: #000; border-radius: 4px; overflow: hidden; position: relative; }
.scanner-result { background: #d4edda; padding: 15px; border-radius: 4px; margin-top: 15px; display: none; }
.scanner-result.show { display: block; }
.close-scanner { background: #e74c3c; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-top: 15px; }
.close-scanner:hover { background: #c0392b; }

/* Code Display Styles */
.code-display-container { 
    display: flex; 
    flex-direction: column; 
    align-items: center; 
    gap: 5px;
}
.qr-code-img, .barcode-img { 
    background: white; 
    padding: 10px; 
    border-radius: 4px; 
    border: 2px solid #ddd;
    cursor: pointer;
    transition: transform 0.2s;
}
.qr-code-img:hover, .barcode-img:hover {
    transform: scale(1.05);
    border-color: #3498db;
}
.code-text { 
    font-size: 10px; 
    color: #7f8c8d; 
    font-family: 'Courier New', monospace;
}

/* Stock Count Styles */
.stock-count-form {
    display: flex;
    align-items: center;
    gap: 5px;
    justify-content: center;
}

.system-qty-display {
    background: #ecf0f1;
    padding: 6px 10px;
    border-radius: 4px;
    font-weight: bold;
    color: #2c3e50;
    font-size: 12px;
}

.count-input-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.actual-count-input {
    width: 70px;
    padding: 6px;
    border: 2px solid #3498db;
    border-radius: 4px;
    text-align: center;
    font-weight: bold;
    font-size: 13px;
}

.actual-count-input:focus {
    outline: none;
    border-color: #2980b9;
    box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
}

.btn-save-count {
    background: #27ae60;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    font-weight: bold;
    transition: all 0.3s;
}

.btn-save-count:hover {
    background: #229954;
    transform: scale(1.05);
}
</style>
</head>
<body>

<div class="top-bar">
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
    <button class="back-btn" onclick="window.location.href='homepage.php'">←</button>
    <h2>Ingredient Management</h2>
</div>

<div id="overlay" class="overlay" onclick="toggleMenu()"></div>

<div id="sidebar">
    <div class="sidebar-header">
        <h3>🍱 Menu</h3>
        <p>Restaurant Management</p>
    </div>
    <a href="homepage.php">🏠 Home</a>
    <a href="ingredient.php">🥬 Ingredient</a>
    <a href="menu_list.php">🍽️ Menu List</a>
    <a href="recipe_list.php">📝 Recipe</a>
    <a href="order_list.php">🛒 Order</a>
    <a href="transaction_list.php">💳 Transaction</a>
    <a href="profit_list.php">📊 Profit</a>
    <a href="member_list.php">👥 Members</a>
    <a href="course_menu_manage.php">🍱 Course</a>
</div>

<div id="scanner-modal" class="scanner-modal">
    <div class="scanner-content">
        <h3>📷 Scan Barcode</h3>
        <div id="scanner-container"></div>
        <div id="scanner-result" class="scanner-result">
            <strong>Detected Barcode:</strong> <span id="barcode-value"></span>
        </div>
        <button class="close-scanner" onclick="closeScanner()">Close Scanner</button>
    </div>
</div>

<div class="container">



<!-- ADD NEW INGREDIENT FORM -->
<!-- ADD NEW INGREDIENT FORM -->
<?php if (!isset($_GET['edit_id'])): ?>
    
<div class="form-section">
    <h3>➕ Add New Ingredient</h3>
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Ingredient ID</label>
            <input type="number" name="id" id="new_id" value="<?= $next_id ?>" readonly style="background:#eee;">
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Ingredient Name</label>
                <input type="text" name="name" required placeholder="เช่น มะเขือเทศ">
            </div>
            <div class="form-group">
                <label>Unit</label>
                <input type="text" name="unit" required placeholder="เช่น kg, pcs, box">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Quantity on Hand</label>
                <input type="number" step="0.01" name="qty" required placeholder="0.00">
            </div>
            <div class="form-group">
                <label>Product Image</label>
                <input type="file" name="image" accept="image/*" onchange="previewImage(event, 'preview_new')">
                <img id="preview_new" class="image-preview">
            </div>
        </div>
        
        <div class="form-row">
    <div class="form-group">
        <label>QR Code</label>
        <input type="text" name="qrcode" placeholder="QR12345">
        <div class="code-hint">💡 สำหรับสแกน QR Code</div>
    </div>
    <div class="form-group">
        <label>Barcode</label>
        <input type="text" name="barcode" id="barcode_input" placeholder="1234567890123">
        <div class="code-hint">💡 รหัสบาร์โค้ด 13 หลัก</div>
    </div>
</div>

<!-- ปุ่มกลาง Generate ทั้ง 2 อันพร้อมกัน -->
<div style="text-align: center; margin: 15px 0;">
    <button type="button" class="generate-btn" onclick="generateCode()" style="font-size: 14px; padding: 10px 20px;">
        🎲 Generate QR & Barcode (เหมือนกัน)
    </button>
    <button type="button" class="scan-btn" onclick="openScanner()">📷 Scan Barcode</button>
</div>
        
        <!-- แทนส่วน attachment เดิมทั้งหมด -->
<!-- แทนส่วนแนบไฟล์เดิม -->
<div class="attachment-section" style="background: #f0f9ff; border-color: #0284c7; margin-top: 25px; padding: 20px; border-radius: 12px;">
    <h4 style="color: #0369a1; margin: 0 0 12px 0; font-size: 1.25rem;">
        📎 แนบไฟล์เอกสาร/รูปภาพ (ถ้ามี)
    </h4>
    
    <p style="color: #475569; margin-bottom: 1.25rem;">
        ใบสั่งซื้อ, COA, ใบเสนอราคา, รูปสินค้า, เอกสารอื่น ๆ (PDF, JPG, Excel, Word ได้)
    </p>
    
    <div style="text-align: center; margin-top: 20px;">
    <a href="ingredient_attachment.php" 
       class="btn" 
       style="background: #0ea5e9; color: white; padding: 12px 30px; border: none; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-block;">
        แนบไฟล์ / ดูประวัติไฟล์รวมทั้งหมด →
    </a>
</div>
    
    <p style="margin-top: 1rem; color: #64748b; font-size: 0.875rem; text-align: center; font-style: italic;">
        ※ หลังจากเพิ่มวัตถุดิบสำเร็จแล้ว คุณสามารถแนบไฟล์เพิ่มเติมได้ตลอดเวลา
    </p>
</div>




<!-- ยังไม่มีประวัติ เพราะยังไม่ได้สร้างวัตถุดิบ -->
        <div style="margin-top: 2rem; text-align: center; color: #64748b; font-style: italic;">
            ※ ประวัติไฟล์จะแสดงได้หลังจากเพิ่มวัตถุดิบสำเร็จแล้ว (ในหน้าแก้ไข)
        </div>
        
        <button type="submit" name="add" class="btn btn-success" style="margin-top: 15px;">💾 Add Ingredient</button>
    </form>
</div>

<?php endif; ?>

<!-- ============================ -->
<!-- EDIT MODE (เริ่มที่นี่) -->
<!-- ============================ -->
<?php if (isset($_GET['edit_id'])): ?>
    <?php
    $edit_id = $_GET['edit_id'];
    $sql = "SELECT * FROM INGREDIENT WHERE INGREDIENTID = :id";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":id", $edit_id);
    oci_execute($stid);
    $edit_row = oci_fetch_assoc($stid);
    ?>

    <!-- Success Messages -->
    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] == 'damage_recorded'): ?>
            <div class="success-msg">⚠️ บันทึกวัตถุดิบเสียหายเรียบร้อยแล้ว!</div>
        <?php elseif ($_GET['msg'] == 'transaction_recorded'): ?>
            <div class="success-msg">📦 บันทึกการเคลื่อนไหววัตถุดิบเรียบร้อยแล้ว!</div>
        <?php elseif ($_GET['msg'] == 'transaction_deleted'): ?>
            <div class="success-msg" style="background: #fff3cd; border-color: #ffc107; color: #856404;">
                🗑️ ลบรายการเรียบร้อยและคืนจำนวนสต็อกแล้ว!
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- 1. EDIT INGREDIENT FORM -->
    <div class="form-section">
        <h3>✏️ Edit Ingredient: <?= htmlspecialchars($edit_row['INGREDIENTNAME']) ?></h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= $edit_row['INGREDIENTID'] ?>">
            <input type="hidden" name="existing_image" value="<?= $edit_row['IMAGEPATH'] ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Ingredient Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($edit_row['INGREDIENTNAME']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Unit</label>
                    <input type="text" name="unit" value="<?= htmlspecialchars($edit_row['UNIT']) ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Quantity on Hand</label>
                    <input type="number" step="0.01" name="qty" value="<?= $edit_row['QTYONHAND'] ?>" required>
                </div>
                <div class="form-group">
                    <label>Product Image</label>
                    <input type="file" name="image" accept="image/*" onchange="previewImage(event, 'preview_edit')">
                    <?php if (!empty($edit_row['IMAGEPATH']) && file_exists($edit_row['IMAGEPATH'])): ?>
                        <img src="<?= $edit_row['IMAGEPATH'] ?>" id="preview_edit" class="image-preview show">
                    <?php else: ?>
                        <img id="preview_edit" class="image-preview">
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>QR Code</label>
                    <input type="text" name="qrcode" value="<?= htmlspecialchars($edit_row['QRCODE']) ?>">
                </div>
                <div class="form-group">
                    <label>Barcode</label>
                    <input type="text" name="barcode" value="<?= htmlspecialchars($edit_row['BARCODE']) ?>">
                </div>
            </div>
            
            <button type="submit" name="edit" class="btn btn-primary">💾 Update</button>
            <button type="button" class="btn btn-secondary" onclick="window.location.href='ingredient.php'">❌ Cancel</button>

<!-- เพิ่ม 3 ปุ่มรายงาน -->
<!-- ปุ่มรายงาน (วางหลังปุ่ม Update และ Cancel) -->
<div style="display: inline-flex; gap: 10px; margin-left: 15px; align-items: center;">
    <button type="button" class="btn" style="background: #10b981; color: white;" onclick="showReport('daily', <?= $edit_row['INGREDIENTID'] ?>)">
        📅 รายวัน
    </button>
    <button type="button" class="btn" style="background: #3b82f6; color: white;" onclick="showReport('monthly', <?= $edit_row['INGREDIENTID'] ?>)">
        📊 รายเดือน
    </button>
    <button type="button" class="btn" style="background: #8b5cf6; color: white;" onclick="showReport('yearly', <?= $edit_row['INGREDIENTID'] ?>)">
        📈 รายปี
    </button>
</div>

        </form>
    </div>

    <!-- 2. TRANSACTION SECTION -->
    <div class="transaction-section">
        <h4>📦 บันทึกวัตถุดิบเข้า-ออก</h4>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="ingredient_id" value="<?= $edit_row['INGREDIENTID'] ?>">
            <input type="hidden" name="transaction_type" id="transaction_type" value="IN">
            
            <div class="transaction-type-selector">
                <button type="button" class="type-btn in active" onclick="selectTransactionType('IN')">
                    📥 รับเข้า (IN)
                </button>
                <button type="button" class="type-btn out" onclick="selectTransactionType('OUT')">
                    📤 เบิกออก (OUT)
                </button>
            </div>
            
            <div class="form-group">
                <label>จำนวน</label>
                <input type="number" step="0.01" name="transaction_qty" required placeholder="ระบุจำนวน">
            </div>
            
            <div class="form-group">
                <label>หมายเหตุ</label>
                <textarea name="transaction_note" placeholder="เช่น รับจากซัพพลายเออร์ ABC"></textarea>
            </div>
            
            <div class="form-group">
                <label>รูปภาพประกอบ</label>
                <input type="file" name="transaction_image" accept="image/*" onchange="previewImage(event, 'preview_transaction')">
                <img id="preview_transaction" class="image-preview">
            </div>
            
            <button type="submit" name="record_transaction" class="btn btn-success">💾 บันทึกการเคลื่อนไหว</button>
        </form>

        <div class="transaction-history">
            <h5 style="margin-top:0;">📋 ประวัติการเคลื่อนไหวล่าสุด</h5>
            <?php
            $sql_trans = "SELECT * FROM INGREDIENT_TRANSACTION WHERE INGREDIENTID = :id ORDER BY TRANSACTION_DATE DESC";
            $stid_trans = oci_parse($conn, $sql_trans);
            oci_bind_by_name($stid_trans, ":id", $edit_id);
            oci_execute($stid_trans);
            
            $has_trans = false;
            while ($trans = oci_fetch_assoc($stid_trans)) {
                $has_trans = true;
                $trans_class = strtolower($trans['TRANSACTION_TYPE']);
                $trans_date = $trans['TRANSACTION_DATE'] ? date('d/m/Y H:i', strtotime($trans['TRANSACTION_DATE'])) : '-';
                ?>
                <div class="transaction-item <?= $trans_class ?>">
                    <!-- ปุ่มลบ -->
                    <button class="btn-delete-transaction" 
                            onclick="if(confirm('⚠️ ยืนยันการลบรายการนี้?\n\nจำนวน: <?= number_format($trans['QUANTITY'], 2) ?> <?= $edit_row['UNIT'] ?>\nสต็อกจะถูกคืนกลับอัตโนมัติ')) 
                                     window.location.href='ingredient.php?delete_transaction=<?= $trans['TRANSACTION_ID'] ?>&ingredient_id=<?= $edit_id ?>'">
                        🗑️
                    </button>
                    
                    <div class="transaction-item-header">
                        <div>
                            <span class="transaction-qty <?= $trans_class ?>">
                                <?= $trans['TRANSACTION_TYPE'] == 'IN' ? '+' : '-' ?>
                                <?= number_format($trans['QUANTITY'], 2) ?> <?= $edit_row['UNIT'] ?>
                            </span>
                            <span class="transaction-type-badge <?= $trans_class ?>">
                                <?= $trans['TRANSACTION_TYPE'] == 'IN' ? '📥 รับเข้า' : '📤 เบิกออก' ?>
                            </span>
                        </div>
                        <span class="transaction-date">🕐 <?= $trans_date ?></span>
                    </div>
                    
                    <?php if (!empty($trans['TRANSACTION_NOTE'])): ?>
                        <div class="transaction-note">📝 <?= htmlspecialchars($trans['TRANSACTION_NOTE']) ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($trans['TRANSACTION_IMAGE']) && file_exists($trans['TRANSACTION_IMAGE'])): ?>
                        <img src="<?= $trans['TRANSACTION_IMAGE'] ?>" class="transaction-img-thumb"
                             onclick="window.open('<?= $trans['TRANSACTION_IMAGE'] ?>', '_blank')">
                    <?php endif; ?>
                </div>
                <?php
            }
            
            if (!$has_trans) {
                echo '<p style="text-align:center; color:#999; padding:20px;">ยังไม่มีประวัติการเคลื่อนไหว</p>';
            }
            ?>
        </div>
    </div>

    <!-- 3. DAMAGE SECTION -->
   
            <?php
            
            // ============= รายงานวัตถุดิบ =============
function getIngredientReport($conn, $ingredientId, $period) {
    $sql = "SELECT 
                it.TRANSACTION_TYPE,
                it.QUANTITY,
                it.TRANSACTION_NOTE,
                it.TRANSACTION_DATE,
                i.INGREDIENTNAME,
                i.UNIT,
                i.QTYONHAND as CURRENT_STOCK
            FROM INGREDIENT_TRANSACTION it
            JOIN INGREDIENT i ON it.INGREDIENTID = i.INGREDIENTID
            WHERE it.INGREDIENTID = :id";
    
    switch($period) {
        case 'daily': $sql .= " AND TRUNC(it.TRANSACTION_DATE) = TRUNC(SYSDATE)"; break;
        case 'monthly': $sql .= " AND TRUNC(it.TRANSACTION_DATE, 'MM') = TRUNC(SYSDATE, 'MM')"; break;
        case 'yearly': $sql .= " AND EXTRACT(YEAR FROM it.TRANSACTION_DATE) = EXTRACT(YEAR FROM SYSDATE)"; break;
    }
    
    $sql .= " ORDER BY it.TRANSACTION_DATE DESC";
    
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":id", $ingredientId);
    oci_execute($stid);
    
    $report = ['data' => [], 'summary' => ['in' => 0, 'out' => 0, 'stock' => 0]];
    while($row = oci_fetch_assoc($stid)) {
        $report['data'][] = $row;
        if($row['TRANSACTION_TYPE'] == 'IN') {
            $report['summary']['in'] += floatval($row['QUANTITY']);
        } else {
            $report['summary']['out'] += floatval($row['QUANTITY']);
        }
    }
    $report['summary']['stock'] = floatval($row['CURRENT_STOCK'] ?? 0);
    return $report;
}

            ?>
        </div>
    </div>

    <!-- 4. ดูไฟล์แนบย้อนหลัง 1 ปี (READ ONLY) -->
    <!-- 4. ส่วนแนบไฟล์ใหม่ + รายการไฟล์เก่า -->


 
        <div class="attachment-list" style="max-height: 320px;">
            <?php
            $sql_attach = "SELECT 
                            ATTACHMENT_ID, FILE_NAME, FILE_PATH, FILE_TYPE, FILE_SIZE, 
                            DESCRIPTION, UPLOAD_DATE
                          FROM INGREDIENT_ATTACHMENTS 
                          WHERE INGREDIENTID = :id 
                          ORDER BY UPLOAD_DATE DESC";
            
            $stid_attach = oci_parse($conn, $sql_attach);
            oci_bind_by_name($stid_attach, ":id", $edit_row['INGREDIENTID']);
            oci_execute($stid_attach);
            
            $has_files = false;
            while ($attach = oci_fetch_assoc($stid_attach)):
                $has_files = true;
                $upload_date = $attach['UPLOAD_DATE'] ? date('d/m/Y H:i', strtotime($attach['UPLOAD_DATE'])) : '-';
                $icon = getFileIcon($attach['FILE_TYPE']);
                $file_size = formatFileSize($attach['FILE_SIZE']);
                $full_filename = htmlspecialchars($attach['FILE_NAME']) . '.' . $attach['FILE_TYPE'];
            ?>
                <div class="attachment-item">
                    <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                        <span class="file-icon" style="font-size: 28px;"><?= $icon ?></span>
                        <div class="attachment-info">
                            <div class="attachment-name"><?= $full_filename ?></div>
                            <div class="attachment-meta">
                                🕐 <?= $upload_date ?> • <?= $file_size ?>
                            </div>
                            <?php if (!empty($attach['DESCRIPTION'])): ?>
                                <div class="attachment-desc">📝 <?= htmlspecialchars($attach['DESCRIPTION']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="attachment-actions">
                        <a href="<?= htmlspecialchars($attach['FILE_PATH']) ?>" class="btn-download" target="_blank">👁️</a>
                        <a href="<?= htmlspecialchars($attach['FILE_PATH']) ?>" class="btn-download" download>📥</a>
                    </div>
                </div>
            <?php endwhile; ?>

            
        </div>
    </div>
</div>

<?php endif; ?>
        
       
<!-- ปิด EDIT MODE -->

<!-- TABLE LIST -->
<?php if (!isset($_GET['edit_id'])): ?>
<div class="page-header">
    <span class="icon">📦</span>
    <h2>Ingredient List</h2>
</div>

<table>
<thead>
    <tr>
        <th>ID</th>
        <th>Image</th>
        <th>Name</th>
        <th>Qty on Hand</th>
        <th>Unit</th>
        <th>QR Code</th>
        <th>Barcode</th>
        <th>นับจริง</th>
        <th>ส่วนต่าง</th>
        <th>Actions</th>
    </tr>
</thead>
<tbody>
    <?php
    $sql = "SELECT * FROM INGREDIENT ORDER BY INGREDIENTID";
    $stid = oci_parse($conn, $sql);
    oci_execute($stid);
    
    while ($row = oci_fetch_assoc($stid)):
        $qty = floatval($row['QTYONHAND']);
        
        if ($qty <= 10) {
            $qty_class = 'qty-low';
        } elseif ($qty <= 50) {
            $qty_class = 'qty-medium';
        } else {
            $qty_class = 'qty-high';
        }
    ?>
        <tr>
            <td><?= $row['INGREDIENTID'] ?></td>
            <td>
                <?php if (!empty($row['IMAGEPATH']) && file_exists($row['IMAGEPATH'])): ?>
                    <img src="<?= $row['IMAGEPATH'] ?>" class="product-img" 
                         onclick="window.open('<?= $row['IMAGEPATH'] ?>', '_blank')">
                <?php else: ?>
                    <div class="no-image">📷</div>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($row['INGREDIENTNAME']) ?></td>
            <td>
                <span class="qty-badge <?= $qty_class ?>">
                    <?= number_format($qty, 2) ?>
                </span>
            </td>
            <td><?= htmlspecialchars($row['UNIT']) ?></td>
            <td>
                <?php if (!empty($row['QRCODE'])): ?>
                    <div class="code-display-container">
                        <div id="qr_<?= $row['INGREDIENTID'] ?>"></div>
                        <div class="code-text"><?= htmlspecialchars($row['QRCODE']) ?></div>
                    </div>
                    
                <?php else: ?>
                    <span style="color:#999;">-</span>
                <?php endif; ?>
            </td>
            <td>
    <?php if (!empty($row['BARCODE'])): ?>
        <div class="code-display-container">
            <svg id="barcode_<?= $row['INGREDIENTID'] ?>"></svg>
            <div class="code-text"><?= htmlspecialchars($row['BARCODE']) ?></div>
        </div>
    <?php else: ?>
        <span style="color:#999;">-</span>
    <?php endif; ?>
</td>
            <td>
    <input type="number" 
           step="0.01" 
           id="actual_<?= $row['INGREDIENTID'] ?>"
           class="actual-count-input"
           placeholder="นับจริง"
           title="กรอกจำนวนที่นับได้จริง"
           data-system="<?= $qty ?>"
           oninput="calculateDiff(<?= $row['INGREDIENTID'] ?>)"
           style="width: 90px; margin: 0 auto;">
</td>
<td>
    <span id="diff_<?= $row['INGREDIENTID'] ?>" 
          class="diff-badge diff-neutral"
          style="min-width: 80px; text-align: center; display: inline-block;">
        -
    </span>
</td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-primary" 
                            onclick="window.location.href='ingredient.php?edit_id=<?= $row['INGREDIENTID'] ?>'">
                        ✏️ Edit
                    </button>
                    
                    <button class="btn btn-danger" 
                            onclick="if(confirm('⚠️ ยืนยันการลบ <?= htmlspecialchars($row['INGREDIENTNAME']) ?>?')) 
                                     window.location.href='ingredient.php?delete=<?= $row['INGREDIENTID'] ?>'">
                        🗑️ Delete
                    </button>
                </div>
            </td>
        </tr>
    <?php endwhile; ?>
</tbody>
</table>

<?php endif; ?> 
<!-- End of TABLE LIST -->

</div> <!-- Close container -->

<script>
function toggleMenu() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
}

function previewImage(event, previewId) {
    const preview = document.getElementById(previewId);
    const file = event.target.files[0];
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.add('show');
        }
        reader.readAsDataURL(file);
    }
}

function generateCode() {
    const timestamp = Date.now();
    const random = Math.floor(Math.random() * 10000);
    
    // สร้าง 12 หลักแรก
    let barcode12 = String(timestamp).slice(-11) + String(random).slice(-1);
    barcode12 = barcode12.padStart(12, '0');
    
    // คำนวณ Check Digit สำหรับ EAN-13
    let sum = 0;
    for (let i = 0; i < 12; i++) {
        let digit = parseInt(barcode12[i]);
        sum += (i % 2 === 0) ? digit : digit * 3;
    }
    let checkDigit = (10 - (sum % 10)) % 10;
    
    let code = barcode12 + checkDigit;
    
    // ⭐ ใส่ค่าเดียวกันให้ทั้ง Barcode และ QR Code เสมอ
    document.querySelector(`input[name="barcode"]`).value = code;
    document.querySelector(`input[name="qrcode"]`).value = code;
    
    console.log('✅ Generated code for both QR & Barcode:', code);
}

function openScanner() {
    document.getElementById('scanner-modal').classList.add('active');
    
    Quagga.init({
        inputStream: {
            name: "Live",
            type: "LiveStream",
            target: document.querySelector('#scanner-container')
        },
        decoder: {
            readers: ["ean_reader", "code_128_reader", "code_39_reader"]
        }
    }, function(err) {
        if (err) {
            console.log(err);
            return;
        }
        Quagga.start();
    });
    
    Quagga.onDetected(function(result) {
        const code = result.codeResult.code;
        document.getElementById('barcode_input').value = code;
        document.getElementById('barcode-value').textContent = code;
        document.getElementById('scanner-result').classList.add('show');
        
        setTimeout(() => {
            closeScanner();
        }, 2000);
    });
}

function closeScanner() {
    Quagga.stop();
    document.getElementById('scanner-modal').classList.remove('active');
    document.getElementById('scanner-result').classList.remove('show');
}

function selectTransactionType(type) {
    document.getElementById('transaction_type').value = type;
    
    const inBtn = document.querySelector('.type-btn.in');
    const outBtn = document.querySelector('.type-btn.out');
    
    if (type === 'IN') {
        inBtn.classList.add('active');
        outBtn.classList.remove('active');
    } else {
        outBtn.classList.add('active');
        inBtn.classList.remove('active');
    }
}

function showFileName(input, displayId) {
    const display = document.getElementById(displayId);
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const size = formatFileSize(file.size);
        display.innerHTML = `📁 <strong>${file.name}</strong> (${size})`;
        display.style.color = '#27ae60';
    }
}

function formatFileSize(bytes) {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
    return bytes + ' bytes';
}
function calculateDiff(ingredientId) {
    const actualInput = document.getElementById('actual_' + ingredientId);
    const diffDisplay = document.getElementById('diff_' + ingredientId);
    
    const systemQty = parseFloat(actualInput.dataset.system);
    const actualQty = parseFloat(actualInput.value);
    
    if (isNaN(actualQty) || actualInput.value === '') {
        diffDisplay.textContent = '-';
        diffDisplay.className = 'diff-badge diff-neutral';
        return;
    }
    
    const difference = actualQty - systemQty;
    
    // แสดงผล
    if (difference > 0) {
        diffDisplay.textContent = '+' + difference.toFixed(2);
        diffDisplay.className = 'diff-badge diff-positive';
    } else if (difference < 0) {
        diffDisplay.textContent = difference.toFixed(2);
        diffDisplay.className = 'diff-badge diff-negative';
    } else {
        diffDisplay.textContent = '0.00';
        diffDisplay.className = 'diff-badge diff-neutral';
    }
}
let currentIngredientId = '<?= $edit_id ?? "" ?>';

function showReport(period, ingredientId) {
    if (!ingredientId) {
        alert('ไม่พบรหัสวัตถุดิบ');
        return;
    }

    const titles = {
        daily: '📅 รายงานรายวัน',
        monthly: '📊 รายเดือน',
        yearly: '📈 รายปี'
    };

    // สร้างหรืออัปเดต modal
    let modal = document.getElementById('report-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'report-modal';
        modal.className = 'scanner-modal';
        document.body.appendChild(modal);
    }

    modal.style.display = 'block'; // แสดง modal ทันที

    modal.innerHTML = `
        <div class="scanner-content" style="max-width:95%; max-height:90vh; overflow-y:auto; background:white; padding:25px; border-radius:12px; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); z-index:10000; width:90%;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3>${titles[period] || 'รายงาน'}</h3>
                <button onclick="document.getElementById('report-modal').style.display='none'" style="background:#e74c3c; color:white; border:none; padding:8px 16px; border-radius:4px; cursor:pointer;">✕</button>
            </div>
            <div id="report-content" style="min-height:200px; text-align:center; padding:50px 0; color:#666;">
                กำลังโหลดข้อมูล...
            </div>
        </div>
    `;

    fetch(`ingredient.php?report=1&ingredient_id=${ingredientId}&period=${period}`)
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
            return response.json(); // เปลี่ยนเป็น .json() เลย เพราะตอนนี้มันส่ง JSON จริง
        })
        .then(data => {
            console.log('Parsed data:', data); // debug

            if (data.error) throw new Error('Server error: ' + data.error);

            let html = `
    <div style="background:#f8fafc; padding:20px; border-radius:10px; margin-bottom:20px; box-shadow:0 2px 8px rgba(0,0,0,0.1);">
        <h4 style="margin-top:0; color:#1e40af;">${data.ingredient_name || 'ไม่พบชื่อ'} (${data.unit || '-'}) - ${titles[period]}</h4>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:15px; text-align:center;">
            <!-- ช่องแรก: สต็อกย้อนหลัง (หน้าสุด) -->
            <div style="background:#e0f2fe; padding:15px; border-radius:8px; border:2px dashed #0284c7;">
                <div style="font-size:32px; font-weight:bold; color:#0369a1;">${(data.summary?.back_stock || 0).toFixed(2)}</div>
                <div><strong>${data.summary?.back_label || 'สต็อกย้อนหลัง'}</strong></div>
                <small>${data.summary?.back_desc || '(ก่อนช่วงนี้)'}</small>
            </div>

            <!-- รับเข้า -->
            <div style="background:#dcfce7; padding:15px; border-radius:8px;">
                <div style="font-size:28px; font-weight:bold; color:#15803d;">+${(data.summary?.in || 0).toFixed(2)}</div>
                <div>รับเข้า (${period === 'daily' ? 'วันนี้' : period === 'monthly' ? 'เดือนนี้' : 'ปีนี้'})</div>
            </div>

            <!-- เบิกออกทั่วไป -->
            <div style="background:#fee2e2; padding:15px; border-radius:8px;">
                <div style="font-size:28px; font-weight:bold; color:#b91c1c;">-${(data.summary?.out_general || 0).toFixed(2)}</div>
                <div>เบิกออกทั่วไป (${period === 'daily' ? 'วันนี้' : period === 'monthly' ? 'เดือนนี้' : 'ปีนี้'})</div>
            </div>

            <!-- หักจากออเดอร์ -->
            <div style="background:#fef3c7; padding:15px; border-radius:8px;">
                <div style="font-size:28px; font-weight:bold; color:#b45309;">-${(data.summary?.out_sale || 0).toFixed(2)}</div>
                <div>หักจากออเดอร์ (${period === 'daily' ? 'วันนี้' : period === 'monthly' ? 'เดือนนี้' : 'ปีนี้'})</div>
            </div>

            <!-- คงเหลือปัจจุบัน -->
            <div style="background:#dbeafe; padding:15px; border-radius:8px; border:2px solid #3b82f6;">
                <div style="font-size:32px; font-weight:bold; color:#1d4ed8;">${(data.current_stock || 0).toFixed(2)}</div>
                <div><strong>คงเหลือปัจจุบัน</strong></div>
            </div>
        </div>
    </div>

    <table style="width:100%; border-collapse:collapse; margin-top:15px;">
        <thead>
            <tr style="background:#e5e7eb;">
                <th style="padding:12px; border:1px solid #d1d5db;">วันที่</th>
                <th style="padding:12px; border:1px solid #d1d5db;">ประเภท</th>
                <th style="padding:12px; border:1px solid #d1d5db;">จำนวน</th>
                <th style="padding:12px; border:1px solid #d1d5db;">หมายเหตุ</th>
            </tr>
        </thead>
        <tbody>`;

            if (!data.data || data.data.length === 0) {
                html += `<tr><td colspan="4" style="padding:40px; text-align:center; color:#888;">ไม่มีรายการในช่วงเวลานี้</td></tr>`;
            } else {
                data.data.forEach(row => {
                    const isPositive = row.quantity.startsWith('+');
                    const rowStyle = row.source === 'order' ? 'background:#fffbeb;' :
                                    (isPositive ? 'background:#f0fdf4;' : 'background:#fef2f2;');
                    html += `
                        <tr style="${rowStyle}">
                            <td style="padding:12px; border:1px solid #d1d5db;">${row.date}</td>
                            <td style="padding:12px; border:1px solid #d1d5db; font-weight:bold;">${row.type}</td>
                            <td style="padding:12px; border:1px solid #d1d5db; font-weight:bold; color:${isPositive ? '#15803d' : '#b91c1c'};">
                                ${row.quantity} ${data.unit || ''}
                            </td>
                            <td style="padding:12px; border:1px solid #d1d5db;">${row.note}</td>
                        </tr>`;
                });
            }

            html += '</tbody></table>';

            document.getElementById('report-content').innerHTML = html;
        })
        .catch(err => {
            console.error('Report error:', err);
            document.getElementById('report-content').innerHTML = `
                <div style="color:#dc2626; padding:30px; text-align:center; background:#fee2e2; border-radius:8px;">
                    <strong>โหลดรายงานไม่สำเร็จ</strong><br>${err.message}
                </div>`;
        });
}
</script>
<!-- Report Modal -->
<div id="report-modal" class="scanner-modal" style="background: rgba(0,0,0,0.85);">
    <div class="scanner-content" style="max-width: 95%; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 id="report-title">รายงานวัตถุดิบ</h3>
            <button class="close-scanner" onclick="closeReport()">✕</button>
        </div>
        <div id="report-content"></div>
    </div>
</div>
<?php
// รับพารามิเตอร์รายงาน (AJAX)

?>
<!-- Report Modal -->
<div id="report-modal" class="scanner-modal" style="display:none; background:rgba(0,0,0,0.85);">
    <div class="scanner-content" style="max-width:95%; max-height:90vh; overflow-y:auto; background:white; padding:25px; border-radius:12px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 id="report-title">รายงานวัตถุดิบ</h3>
            <button class="close-scanner" onclick="document.getElementById('report-modal').style.display='none'">✕</button>
        </div>
        <div id="report-content" style="min-height:200px;"></div>
    </div>
</div>
<script>
// รอให้หน้าเว็บโหลดเสร็จก่อน
window.addEventListener('load', function() {
    <?php
    // ดึงข้อมูล QR Code ทั้งหมดมาสร้างทีเดียว
    $sql_qr = "SELECT INGREDIENTID, QRCODE FROM INGREDIENT WHERE QRCODE IS NOT NULL ORDER BY INGREDIENTID";
    $stid_qr = oci_parse($conn, $sql_qr);
    oci_execute($stid_qr);
    
    while ($qr_row = oci_fetch_assoc($stid_qr)):
        if (!empty($qr_row['QRCODE'])):
    ?>
    // สร้าง QR Code สำหรับ ID: <?= $qr_row['INGREDIENTID'] ?>

    (function() {
        var element = document.getElementById("qr_<?= $qr_row['INGREDIENTID'] ?>");
        if (element && typeof QRCode !== 'undefined') {
            try {
                new QRCode(element, {
                    text: "<?= addslashes($qr_row['QRCODE']) ?>",
                    width: 60,
                    height: 60,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
                console.log("✅ QR Code created for ID: <?= $qr_row['INGREDIENTID'] ?>");
            } catch(e) {
                console.error("❌ QR Error ID <?= $qr_row['INGREDIENTID'] ?>:", e);
            }
        } else {
            console.warn("⚠️ Element or QRCode library not found for ID: <?= $qr_row['INGREDIENTID'] ?>");
        }
    })();
    
    <?php
        endif;
    endwhile;
    oci_free_statement($stid_qr);
    ?>
});
</script>
<script>
// สร้าง Barcode ทั้งหมด
window.addEventListener('load', function() {
    <?php
    $sql_bar = "SELECT INGREDIENTID, BARCODE FROM INGREDIENT WHERE BARCODE IS NOT NULL ORDER BY INGREDIENTID";
    $stid_bar = oci_parse($conn, $sql_bar);
    oci_execute($stid_bar);
    
    while ($bar_row = oci_fetch_assoc($stid_bar)):
        if (!empty($bar_row['BARCODE'])):
    ?>
    
    (function() {
        var element = document.getElementById("barcode_<?= $bar_row['INGREDIENTID'] ?>");
        var barcodeValue = "<?= addslashes($bar_row['BARCODE']) ?>";
        
        if (element && typeof JsBarcode !== 'undefined') {
            try {
                // ตรวจสอบความยาว
                if (barcodeValue.length === 13) {
                    // ลองใช้ EAN13 ก่อน
                    try {
                        JsBarcode(element, barcodeValue, {
                            format: "EAN13",
                            width: 1,
                            height: 40,
                            displayValue: false
                        });
                        console.log("✅ EAN13 Barcode created for ID: <?= $bar_row['INGREDIENTID'] ?>");
                    } catch(e) {
                        // ถ้า EAN13 ผิด ใช้ CODE128 แทน
                        console.warn("⚠️ EAN13 invalid, using CODE128 for ID: <?= $bar_row['INGREDIENTID'] ?>");
                        JsBarcode(element, barcodeValue, {
                            format: "CODE128",
                            width: 1,
                            height: 40,
                            displayValue: false
                        });
                    }
                } else {
                    // ไม่ใช่ 13 หลัก ใช้ CODE128
                    JsBarcode(element, barcodeValue, {
                        format: "CODE128",
                        width: 1,
                        height: 40,
                        displayValue: false
                    });
                    console.log("✅ CODE128 Barcode created for ID: <?= $bar_row['INGREDIENTID'] ?>");
                }
            } catch(e) {
                console.error("❌ Barcode Error ID <?= $bar_row['INGREDIENTID'] ?>:", e);
                // แสดงเลขแทน
                element.innerHTML = '<text style="font-family:monospace;font-size:11px;" x="0" y="20">' + barcodeValue + '</text>';
            }
        }
    })();
    
    <?php
        endif;
    endwhile;
    oci_free_statement($stid_bar);
    ?>
});
</script>
</body>
</html>