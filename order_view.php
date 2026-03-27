<?php
require 'connect.php';

if (!isset($_GET['id'])) {
    header('Location: order_list.php');
    exit;
}
$orderid = $_GET['id'];

// ดึงข้อมูล transaction
$tsql = "SELECT t.ORDERID, 
                TO_CHAR(t.ORDERDATE, 'DD-MON-YYYY') as ORDERDATE_DISPLAY,
                t.ORDERDATE, t.ORDERTIME, t.TOTALPRICE, t.DISCOUNTMEMBER,
                t.MENUTYPEID, t.COURSEID, t.CUSTOMERID, t.PAYMENT_METHOD, 
                t.SLIP_FILENAME, t.DEPOSIT_SLIP_FILENAME,
                mt.TYPENAME, mc.COURSENAME, mc.COURSEPRICE,
                m.CUSTOMERNAME
         FROM TRANSACTION t
         LEFT JOIN MENU_TYPE mt ON t.MENUTYPEID = mt.MENUTYPEID
         LEFT JOIN MENU_COURSE mc ON t.COURSEID = mc.COURSEID
         LEFT JOIN MEMBER m ON t.CUSTOMERID = m.CUSTOMERID
         WHERE t.ORDERID = :oid";
$tst = oci_parse($conn, $tsql);
oci_bind_by_name($tst, ":oid", $orderid);
oci_execute($tst);
$trans = oci_fetch_assoc($tst);

if (!$trans) {
    header('Location: order_list.php');
    exit;
}

// ✅ นับจำนวนคน/คอร์ส
// ✅ นับจำนวนคน/คอร์ส (แก้ปัญหาแสดง 1)
// ✅ วิธีที่ถูกต้อง 100% สำหรับ OCI8
// ✅ นับจาก ORDER_SECTION.PERSON_COUNT (ถูกต้อง 100%)
$courseSql = "SELECT SUM(PERSON_COUNT) as TOTAL_PERSONS 
              FROM ORDER_SECTION 
              WHERE ORDERID = :oid";
$courseSt = oci_parse($conn, $courseSql);
oci_bind_by_name($courseSt, ":oid", $orderid);
oci_execute($courseSt);
$courseResult = oci_fetch_assoc($courseSt);
oci_free_statement($courseSt);
$courseCount = intval($courseResult['TOTAL_PERSONS'] ?? 0);




// ดึงรายการเมนู
$itemsql = "SELECT oi.MENUID, oi.QUANTITY, oi.TYPE, m.MENUNAME, m.PRICE_ALACARTE, m.PRICE_OMAKASE
            FROM ORDER_ITEM oi
            LEFT JOIN MENU m ON oi.MENUID = m.MENUID
            WHERE oi.ORDERID = :oid";
$ist = oci_parse($conn, $itemsql);
oci_bind_by_name($ist, ":oid", $orderid);
oci_execute($ist);


$items = [];
while ($r = oci_fetch_assoc($ist)) {
    $items[] = $r;
}

// บันทึกการแก้ไข
if (isset($_POST['save'])) {
    $posted_menu = $_POST['menu_id'] ?? [];
    $posted_qty = $_POST['qty'] ?? [];
    $posted_type = $_POST['type'] ?? [];
    $payment_method = $_POST['payment_method'] ?? null;

    // จัดการไฟล์สลิปหลัก
    $slipFilename = $trans['SLIP_FILENAME'] ?? null;
    if (isset($_FILES['slip_file']) && $_FILES['slip_file']['error'] == UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg', 'application/pdf'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        $fileType = mime_content_type($_FILES['slip_file']['tmp_name']);
        if (in_array($fileType, $allowedTypes) && $_FILES['slip_file']['size'] <= $maxSize) {
            $ext = pathinfo($_FILES['slip_file']['name'], PATHINFO_EXTENSION);
            $newName = 'slip_' . $orderid . '_' . time() . '.' . strtolower($ext);
            $uploadDir = __DIR__ . '/uploads/slips/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $destPath = $uploadDir . $newName;
            if (move_uploaded_file($_FILES['slip_file']['tmp_name'], $destPath)) {
                $slipFilename = $newName;
                if ($trans['SLIP_FILENAME'] && file_exists($uploadDir . $trans['SLIP_FILENAME'])) {
                    unlink($uploadDir . $trans['SLIP_FILENAME']);
                }
            }
        }
    }

    // จัดการไฟล์สลิปมัดจำ
    $depositSlipFilename = $trans['DEPOSIT_SLIP_FILENAME'] ?? null;
    if (isset($_FILES['deposit_slip_file']) && $_FILES['deposit_slip_file']['error'] == UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg', 'application/pdf'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        $fileType = mime_content_type($_FILES['deposit_slip_file']['tmp_name']);
        if (in_array($fileType, $allowedTypes) && $_FILES['deposit_slip_file']['size'] <= $maxSize) {
            $ext = pathinfo($_FILES['deposit_slip_file']['name'], PATHINFO_EXTENSION);
            $newName = 'deposit_' . $orderid . '_' . time() . '.' . strtolower($ext);
            $uploadDir = __DIR__ . '/uploads/slips/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $destPath = $uploadDir . $newName;
            if (move_uploaded_file($_FILES['deposit_slip_file']['tmp_name'], $destPath)) {
                $depositSlipFilename = $newName;
                if ($trans['DEPOSIT_SLIP_FILENAME'] && file_exists($uploadDir . $trans['DEPOSIT_SLIP_FILENAME'])) {
                    unlink($uploadDir . $trans['DEPOSIT_SLIP_FILENAME']);
                }
            }
        }
    }

    // ลบรายการเก่าทั้งหมด
    $delSql = "DELETE FROM ORDER_ITEM WHERE ORDERID = :oid";
    $delSt = oci_parse($conn, $delSql);
    oci_bind_by_name($delSt, ":oid", $orderid);
    oci_execute($delSt);

    // คำนวณราคาใหม่
    $menutypeid = $trans['MENUTYPEID'];
    $courseid = $trans['COURSEID'];
    $coursePrice = $trans['COURSEPRICE'] ?? 0;

    // คำนวณคอร์สจาก ORDER_SECTION ทั้งหมด
$total = 0;
if ($menutypeid == 2) {
    $courseSumSql = "SELECT SUM(mc.COURSEPRICE * os.PERSON_COUNT) as TOTAL_COURSE
                     FROM ORDER_SECTION os
                     LEFT JOIN MENU_COURSE mc ON os.COURSEID = mc.COURSEID
                     WHERE os.ORDERID = :oid";
    $courseSumSt = oci_parse($conn, $courseSumSql);
    oci_bind_by_name($courseSumSt, ":oid", $orderid);
    oci_execute($courseSumSt);
    $courseSumRow = oci_fetch_assoc($courseSumSt);
    $total = floatval($courseSumRow['TOTAL_COURSE'] ?? 0);
}

    for ($i = 0; $i < count($posted_menu); $i++) {
    $mid = $posted_menu[$i];
    $qty = $posted_qty[$i];
    $type = isset($posted_type[$i]) ? $posted_type[$i] : null;

    if (empty($mid) || $qty <= 0) continue;

    // กำหนด CHARGE_FLAG (A La Carte = Y, Omakase extra = Y, Omakase course = N)
    $chargeFlag = ($menutypeid == 1 || ($menutypeid == 2 && $type == 'extra')) ? 'Y' : 'N';
    
    // บันทึก ORDER_ITEM พร้อม CHARGE_FLAG
    $insSql = "INSERT INTO ORDER_ITEM (ORDERID, MENUID, QUANTITY, TYPE, CHARGE_FLAG, SECTION_NUMBER) 
               VALUES (:oid, :mid, :qty, :type, :cflag, 1)";
    $insSt = oci_parse($conn, $insSql);
    oci_bind_by_name($insSt, ":oid", $orderid);
    oci_bind_by_name($insSt, ":mid", $mid);
    oci_bind_by_name($insSt, ":qty", $qty);
    oci_bind_by_name($insSt, ":type", $type);
    oci_bind_by_name($insSt, ":cflag", $chargeFlag);
    oci_execute($insSt);

    // คำนวณราคา
    if ($chargeFlag == 'Y') {
        if ($menutypeid == 1) {
            // A La Carte - ใช้ PRICE_ALACARTE
            $priceSql = "SELECT PRICE_ALACARTE FROM MENU WHERE MENUID = :mid";
        } else {
            // Omakase - ใช้ PRICE_OMAKASE (ค่าแลกซื้อ)
            $priceSql = "SELECT PRICE_OMAKASE FROM MENU WHERE MENUID = :mid";
        }
        
        $priceSt = oci_parse($conn, $priceSql);
        oci_bind_by_name($priceSt, ":mid", $mid);
        oci_execute($priceSt);
        $priceRow = oci_fetch_assoc($priceSt);
        
        if ($menutypeid == 1) {
            $price = $priceRow ? $priceRow['PRICE_ALACARTE'] : 0;
        } else {
            $price = $priceRow ? $priceRow['PRICE_OMAKASE'] : 0;
        }
        
        $total += ($price * $qty);
    }
}

    // คำนวณส่วนลด
    $discountPercent = 0;
    $discountAmount = 0;

    if ($trans['CUSTOMERID']) {
        $discountSql = "SELECT ml.DISCOUNT FROM MEMBER c LEFT JOIN MEMBER_LEVEL ml ON c.LEVELID = ml.LEVELID WHERE c.CUSTOMERID = :custid";
        $discountStid = oci_parse($conn, $discountSql);
        oci_bind_by_name($discountStid, ":custid", $trans['CUSTOMERID']);
        oci_execute($discountStid);
        $discountRow = oci_fetch_assoc($discountStid);

        if ($discountRow && $discountRow['DISCOUNT']) {
            $discountPercent = $discountRow['DISCOUNT'];
            $discountAmount = $total * ($discountPercent / 100);
            $total = $total - $discountAmount;
        }
    }

    date_default_timezone_set('Asia/Bangkok');
    $orderTime = date('H:i:s');

    $upSql = "UPDATE TRANSACTION 
              SET TOTALPRICE = :total, 
                  DISCOUNTMEMBER = :disc, 
                  ORDERTIME = :ordertime,
                  PAYMENT_METHOD = :payment,
                  SLIP_FILENAME = :slip,
                  DEPOSIT_SLIP_FILENAME = :deposit_slip
              WHERE ORDERID = :oid";
    $upSt = oci_parse($conn, $upSql);
    oci_bind_by_name($upSt, ":total", $total);
    oci_bind_by_name($upSt, ":disc", $discountAmount);
    oci_bind_by_name($upSt, ":ordertime", $orderTime);
    oci_bind_by_name($upSt, ":payment", $payment_method);
    oci_bind_by_name($upSt, ":slip", $slipFilename);
    oci_bind_by_name($upSt, ":deposit_slip", $depositSlipFilename);
    oci_bind_by_name($upSt, ":oid", $orderid);
    oci_execute($upSt);

    oci_commit($conn);
    header("Location: order_view.php?id=" . $orderid . "&updated=1");
    exit;
}

// ดึงรายการเมนูทั้งหมด
$menuSql = "SELECT MENUID, MENUNAME, PRICE_ALACARTE FROM MENU ORDER BY MENUID";
$menuSt = oci_parse($conn, $menuSql);
oci_execute($menuSt);
$allMenus = [];
while ($m = oci_fetch_assoc($menuSt)) {
    $allMenus[] = $m;
}

// ตรวจสอบไฟล์สลิปทั้ง 2 ไฟล์
$slipExists = false;
$slipPath = '';
$depositSlipExists = false;
$depositSlipPath = '';
if ($trans['SLIP_FILENAME']) {
    $slipPath = 'uploads/slips/' . $trans['SLIP_FILENAME'];
    $slipExists = file_exists(__DIR__ . '/' . $slipPath);
}
if ($trans['DEPOSIT_SLIP_FILENAME']) {
    $depositSlipPath = 'uploads/slips/' . $trans['DEPOSIT_SLIP_FILENAME'];
    $depositSlipExists = file_exists(__DIR__ . '/' . $depositSlipPath);
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Order Details</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #fafafa; }
        .top-bar {
            width: 100%; background-color: #f5f5f5; padding: 12px 18px; display: flex;
            align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); position: fixed;
            top: 0; left: 0; z-index: 20;
        }
        .back-btn {
            font-size: 22px; margin-right: 15px; cursor: pointer; padding: 6px 10px;
            border-radius: 6px; border: 1px solid #ccc; background: white; transition: background 0.2s;
        }
        .back-btn:hover { background: #e8e8e8; }
        .container {
            max-width: 1000px; margin: 90px auto 40px; padding: 30px; background: white;
            border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .alert-success {
            background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px;
            border-radius: 6px; margin-bottom: 20px;
        }
        h2 { color: #2c3e50; margin-bottom: 20px; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        .info-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;
            margin-bottom: 30px; background: #f8f9fa; padding: 20px; border-radius: 6px;
        }
        .info-item { display: flex; flex-direction: column; }
        .info-label { font-size: 12px; color: #666; text-transform: uppercase; margin-bottom: 5px; }
        .info-value { font-size: 16px; font-weight: bold; color: #2c3e50; }
        h3 { color: #34495e; margin-top: 30px; margin-bottom: 15px; font-size: 20px; }
        .item-row {
            display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 10px; margin-bottom: 12px;
            align-items: center;
        }
        select, input { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
        select:focus, input:focus { outline: none; border-color: #3498db; }
        .remove-btn { background: #e74c3c; color: white; border: none; padding: 10px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .remove-btn:hover { background: #c0392b; }
        .add-btn {
            background: #27ae60; color: white; border: none; padding: 10px 20px; border-radius: 6px;
            cursor: pointer; font-weight: 600; margin-top: 10px; margin-bottom: 20px;
        }
        .add-btn:hover { background: #229954; }
        .summary-box { background: #f8f9fa; padding: 20px; border-radius: 6px; margin-top: 20px; }
        .summary-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #ddd; }
        .summary-row:last-child { border-bottom: none; font-size: 20px; font-weight: bold; color: #27ae60; }
        .save-btn {
            width: 100%; background: #3498db; color: white; border: none; padding: 15px;
            font-size: 18px; font-weight: 700; border-radius: 6px; cursor: pointer; margin-top: 20px;
        }
        .save-btn:hover { background: #2980b9; }
        .type-badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .badge-alacarte { background: #3498db; color: white; }
        .badge-omakase { background: #e74c3c; color: white; }
        .type-select { width: 100px; padding: 8px; border-radius: 6px; border: 1px solid #ccc; font-size: 13px; }
        .payment-section {
            background: #fff3cd; padding: 20px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #f39c12;
        }
        .payment-section h3 { margin-top: 0; color: #856404; }
        .payment-methods { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 15px; }
        .payment-method-btn {
            padding: 12px; background: #fff; border: 2px solid #e0e0e0; border-radius: 8px; cursor: pointer;
            transition: all 0.3s; font-weight: 600; font-size: 15px; text-align: center;
        }
        .payment-method-btn:hover { background: #3498db; color: white; border-color: #3498db; }
        .payment-method-btn.active { background: #27ae60; color: white; border-color: #27ae60; }
        .payment-badge { display: inline-block; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: bold; }
        .payment-cash { background: #27ae60; color: white; }
        .payment-transfer { background: #3498db; color: white; }
        .payment-none { background: #95a5a6; color: white; }
        
        /* Slip Viewer Styles */
        .slip-preview {
            background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #27ae60;
            text-align: center; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
            z-index: 1000; max-width: 90%; max-height: 90vh; overflow: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .slip-preview h4 { color: #155724; margin: 0 0 15px 0; }
        .slip-image { max-width: 100%; max-height: 70vh; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .slip-actions { margin-top: 15px; }
        .slip-btn { 
            display: inline-block; padding: 10px 20px; margin: 0 5px; border-radius: 6px; 
            text-decoration: none; font-weight: 600; cursor: pointer; transition: all 0.3s; border: none;
        }
        .btn-view { background: #3498db; color: white; }
        .btn-view:hover { background: #2980b9; }
        .btn-download { background: #27ae60; color: white; }
        .btn-download:hover { background: #229954; }
        .slip-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0,0,0,0.5); z-index: 999;
        }
        .attachment-section {
            background: #e8f5e8; padding: 15px; border-radius: 6px; margin-top: 15px; border-left: 4px solid #27ae60;
        }
        .attachment-section label { display: block; font-weight: 600; color: #155724; margin-bottom: 8px; }
        .file-input { 
            width: 100%; padding: 12px; border: 2px dashed #27ae60; border-radius: 8px; background: white; 
            cursor: pointer; font-size: 14px;
        }
        .file-input:hover { border-color: #229954; background: #f8fff8; }
        .current-slip {
            margin-top: 10px; padding: 10px; background: #d4edda; border-radius: 4px; font-size: 13px;
        }
        .deposit-section {
            background: #fff8e1; border-left: 4px solid #f39c12;
        }
    </style>
</head>
<body>

<div class="top-bar">
    <button class="back-btn" onclick="window.location.href='order_list.php'">←</button>
    <h2 style="margin: 0;">Order Details</h2>
</div>

<div class="container">

<?php if (isset($_GET['updated'])): ?>
    <div class="alert-success">✅ บันทึกการแก้ไขเรียบร้อยแล้ว</div>
<?php endif; ?>

<h2>📋 ออเดอร์ #<?= htmlspecialchars($trans['ORDERID']) ?></h2>

<div class="info-grid">
    <div class="info-item">
        <div class="info-label">วันที่สั่ง</div>
        <?php 
$orderDateStr = $trans['ORDERDATE_DISPLAY'] ?? '';
$orderDateFormatted = $orderDateStr ? date('d M Y', strtotime($orderDateStr)) : '-';
$today = date('Y-m-d');
$orderDate = $orderDateStr ? date('Y-m-d', strtotime($orderDateStr)) : '';
$isToday = ($orderDate === $today);
?>
<div class="info-value">
    <?= $orderDateFormatted ?>
    <?php if ($isToday): ?>
        <br><small style="color:#27ae60;font-weight:bold;">📍 วันนี้</small>
    <?php endif; ?>
</div>
    </div>
    <div class="info-item">
        <div class="info-label">เวลาสั่ง</div>
        <div class="info-value"><?= htmlspecialchars($trans['ORDERTIME']) ?></div>
    </div>
    <div class="info-item">
        <div class="info-label">ประเภทเมนู</div>
        <div class="info-value">
            <?php $badgeClass = $trans['MENUTYPEID'] == 1 ? 'badge-alacarte' : 'badge-omakase'; ?>
            <span class="type-badge <?= $badgeClass ?>"><?= htmlspecialchars($trans['TYPENAME'] ?? '-') ?></span>
        </div>
    </div>
    <?php if ($courseCount > 0): ?>
<div class="info-item">
    <div class="info-label">จำนวนคน/คอร์ส</div>
    <div class="info-value"><span style="color: #27ae60; font-size: 18px; font-weight: bold;"><?= $courseCount ?></span> คน</div>
</div>
<?php endif; ?>

    <div class="info-item">
        <div class="info-label">วิธีชำระเงิน</div>
        <div class="info-value">
            <?php
            $paymentMethod = $trans['PAYMENT_METHOD'] ?? null;
            if ($paymentMethod === 'cash') {
                echo '<span class="payment-badge payment-cash">💵 เงินสด</span>';
            } elseif ($paymentMethod === 'transfer') {
                echo '<span class="payment-badge payment-transfer">📱 โอนเงิน</span>';
            } else {
                echo '<span class="payment-badge payment-none">⚠️ ไม่ระบุ</span>';
            }
            ?>
        </div>
    </div>
    <?php if ($trans['SLIP_FILENAME'] && $slipExists): ?>
    <div class="info-item">
        <div class="info-label">สลิปหลัก</div>
        <div class="info-value">
            <a href="#" onclick="showSlipPreview('main')" style="color: #27ae60; font-weight: 600;">👁️ ดูสลิป</a>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($trans['DEPOSIT_SLIP_FILENAME'] && $depositSlipExists): ?>
    <div class="info-item">
        <div class="info-label">สลิปมัดจำ</div>
        <div class="info-value">
            <a href="#" onclick="showSlipPreview('deposit')" style="color: #f39c12; font-weight: 600;">👁️ ดูสลิปมัดจำ</a>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($trans['COURSENAME']): ?>
    <div class="info-item">
        <div class="info-label">คอร์ส</div>
        <div class="info-value"><?= htmlspecialchars($trans['COURSENAME']) ?> <span style="color: #27ae60;">(<?= number_format($trans['COURSEPRICE'], 2) ?> ฿)</span></div>
    </div>
    <?php endif; ?>
    <?php if ($trans['CUSTOMERNAME']): ?>
    <div class="info-item">
        <div class="info-label">สมาชิก</div>
        <div class="info-value"><?= htmlspecialchars($trans['CUSTOMERNAME']) ?></div>
    </div>
    <?php endif; ?>
</div>


<!-- Slip Preview Modals -->
<div class="slip-overlay" id="slipOverlayMain" onclick="closeSlipPreview('main')"></div>
<div id="slipModalMain" class="slip-preview" style="display: none;">
    <h4>📸 สลิปการโอนเงินหลัก</h4>
    <?php 
    $mainExt = strtolower(pathinfo($trans['SLIP_FILENAME'], PATHINFO_EXTENSION));
    if ($slipExists && in_array($mainExt, ['jpg', 'jpeg', 'png', 'gif'])): ?>
        <img src="<?= $slipPath ?>" alt="สลิปหลัก" class="slip-image">
    <?php else: ?>
        <div style="padding: 40px; text-align: center; color: #666;">
            <i>📄 ไฟล์ PDF - คลิกดาวน์โหลดเพื่อดู</i>
        </div>
    <?php endif; ?>
    <div class="slip-actions">
        <?php if ($slipExists): ?>
        <a href="<?= $slipPath ?>" class="slip-btn btn-download" download>💾 ดาวน์โหลด</a>
        <?php endif; ?>
        <button class="slip-btn btn-view" onclick="closeSlipPreview('main')">❌ ปิด</button>
    </div>
</div>

<div class="slip-overlay" id="slipOverlayDeposit" onclick="closeSlipPreview('deposit')"></div>
<div id="slipModalDeposit" class="slip-preview" style="display: none;">
    <h4>💰 สลิปมัดจำ</h4>
    <?php 
    $depositExt = strtolower(pathinfo($trans['DEPOSIT_SLIP_FILENAME'], PATHINFO_EXTENSION));
    if ($depositSlipExists && in_array($depositExt, ['jpg', 'jpeg', 'png', 'gif'])): ?>
        <img src="<?= $depositSlipPath ?>" alt="สลิปมัดจำ" class="slip-image">
    <?php else: ?>
        <div style="padding: 40px; text-align: center; color: #666;">
            <i>📄 ไฟล์ PDF - คลิกดาวน์โหลดเพื่อดู</i>
        </div>
    <?php endif; ?>
    <div class="slip-actions">
        <?php if ($depositSlipExists): ?>
        <a href="<?= $depositSlipPath ?>" class="slip-btn btn-download" download>💾 ดาวน์โหลด</a>
        <?php endif; ?>
        <button class="slip-btn btn-view" onclick="closeSlipPreview('deposit')">❌ ปิด</button>
    </div>
</div>

<form method="POST" enctype="multipart/form-data">
    <h3>🍣 รายการเมนู</h3>
    <div id="items">
        <?php foreach($items as $it): ?>
        <div class="item-row">
            <select name="menu_id[]" required>
                <option value="">-- เลือกเมนู --</option>
                <?php foreach($allMenus as $menu): ?>
                    <option value="<?= $menu['MENUID'] ?>" <?= $menu['MENUID'] == $it['MENUID'] ? 'selected' : '' ?>>
                        <?= $menu['MENUNAME'] ?> (<?= number_format($menu['PRICE_ALACARTE'], 2) ?> ฿)
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="number" name="qty[]" value="<?= $it['QUANTITY'] ?>" min="1" required>
            <select name="type[]" class="type-select">
                <option value="course" <?= (isset($it['TYPE']) && $it['TYPE'] == 'course') ? 'selected' : '' ?>>ในคอร์ส</option>
                <option value="extra" <?= (isset($it['TYPE']) && $it['TYPE'] == 'extra') ? 'selected' : '' ?>>แถม/เพิ่ม</option>
            </select>
            <button type="button" class="remove-btn" onclick="removeRow(this)">✕</button>
        </div>
        <?php endforeach; ?>
        <?php if (empty($items)): ?>
        <div class="item-row">
            <select name="menu_id[]"><option value="">-- เลือกเมนู --</option><?php foreach($allMenus as $menu): ?><option value="<?= $menu['MENUID'] ?>"><?= $menu['MENUNAME'] ?> (<?= number_format($menu['PRICE_ALACARTE'], 2) ?> ฿)</option><?php endforeach; ?></select>
            <input type="number" name="qty[]" value="1" min="1">
            <select name="type[]" class="type-select"><option value="course">ในคอร์ส</option><option value="extra">แถม/เพิ่ม</option></select>
            <button type="button" class="remove-btn" onclick="removeRow(this)">✕</button>
        </div>
        <?php endif; ?>
    </div>
    <button type="button" class="add-btn" onclick="addRow()">➕ เพิ่มรายการ</button>

    <div class="payment-section">
        <h3>💳 แก้ไขวิธีการชำระเงิน</h3>
        <div class="payment-methods">
            <div class="payment-method-btn <?= ($trans['PAYMENT_METHOD'] === 'cash') ? 'active' : '' ?>" data-method="cash" onclick="selectPayment('cash')">💵 เงินสด</div>
            <div class="payment-method-btn <?= ($trans['PAYMENT_METHOD'] === 'transfer') ? 'active' : '' ?>" data-method="transfer" onclick="selectPayment('transfer')">📱 โอนเงิน</div>
        </div>
        <input type="hidden" name="payment_method" id="payment-method" value="<?= htmlspecialchars($trans['PAYMENT_METHOD'] ?? 'cash') ?>">
    </div>

    <!-- สลิปหลัก -->
    <div class="attachment-section">
        <label for="slip_file">📎 แนบสลิปการโอนเงินหลัก (JPG, PNG, GIF, PDF - สูงสุด 5MB)</label>
        <input type="file" name="slip_file" id="slip_file" class="file-input" accept="image/*,.pdf">
        <?php if ($trans['SLIP_FILENAME']): ?>
        <div class="current-slip">
            💾 ปัจจุบัน: <strong><?= htmlspecialchars($trans['SLIP_FILENAME']) ?></strong> 
            (ไฟล์ใหม่จะแทนที่ไฟล์เก่า)
        </div>
        <?php endif; ?>
    </div>

    <!-- สลิปมัดจำ -->
    <div class="attachment-section deposit-section">
        <label for="deposit_slip_file">💰 แนบสลิปมัดจำ (JPG, PNG, GIF, PDF - สูงสุด 5MB)</label>
        <input type="file" name="deposit_slip_file" id="deposit_slip_file" class="file-input" accept="image/*,.pdf">
        <?php if ($trans['DEPOSIT_SLIP_FILENAME']): ?>
        <div class="current-slip">
            💾 ปัจจุบัน: <strong><?= htmlspecialchars($trans['DEPOSIT_SLIP_FILENAME']) ?></strong> 
            (ไฟล์ใหม่จะแทนที่ไฟล์เก่า)
        </div>
        <?php endif; ?>
    </div>

    <div class="summary-box">
        <div class="summary-row"><span>ราคาคอร์ส:</span><span><?= number_format($trans['COURSEPRICE'] ?? 0, 2) ?> ฿</span></div>
        <?php if ($trans['MENUTYPEID'] == 1): ?>
        <div class="summary-row">
            <span>รายการเมนู:</span>
            <span><?php $itemTotal = 0; foreach($items as $it) { $price = $trans['MENUTYPEID'] == 1 ? $it['PRICE_ALACARTE'] : 0; $itemTotal += ($price * $it['QUANTITY']); } echo number_format($itemTotal, 2); ?> ฿</span>
        </div>
        <?php endif; ?>
        <?php if ($trans['DISCOUNTMEMBER'] > 0): ?>
        <div class="summary-row"><span>ส่วนลดสมาชิก:</span><span style="color: #e74c3c;">-<?= number_format($trans['DISCOUNTMEMBER'], 2) ?> ฿</span></div>
        <?php endif; ?>
        <div class="summary-row"><span>ยอดรวม:</span><span><?= number_format($trans['TOTALPRICE'], 2) ?> ฿</span></div>
    </div>

    <button type="submit" name="save" class="save-btn">💾 บันทึกการแก้ไข</button>
</form>

</div>

<script>
const allMenusData = <?= json_encode($allMenus) ?>;

function addRow() {
    const itemsDiv = document.getElementById('items');
    const newRow = document.createElement('div');
    newRow.className = 'item-row';
    let optionsHTML = '<option value="">-- เลือกเมนู --</option>';
    allMenusData.forEach(menu => {
        optionsHTML += `<option value="${menu.MENUID}">${menu.MENUNAME} (${parseFloat(menu.PRICE_ALACARTE).toFixed(2)} ฿)</option>`;
    });
    newRow.innerHTML = `
        <select name="menu_id[]" required>${optionsHTML}</select>
        <input type="number" name="qty[]" value="1" min="1" required>
        <select name="type[]" class="type-select">
            <option value="course">ในคอร์ส</option>
            <option value="extra">แถม/เพิ่ม</option>
        </select>
        <button type="button" class="remove-btn" onclick="removeRow(this)">✕</button>
    `;
    itemsDiv.appendChild(newRow);
}

function removeRow(btn) {
    const rows = document.querySelectorAll('.item-row');
    if (rows.length > 1) {
        btn.parentElement.remove();
    } else {
        alert('ต้องมีรายการอย่างน้อย 1 รายการ');
    }
}

function selectPayment(method) {
    document.querySelectorAll('.payment-method-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`[data-method="${method}"]`).classList.add('active');
    document.getElementById('payment-method').value = method;
}

function showSlipPreview(type) {
    if (type === 'main') {
        document.getElementById('slipModalMain').style.display = 'block';
        document.getElementById('slipOverlayMain').style.display = 'block';
    } else if (type === 'deposit') {
        document.getElementById('slipModalDeposit').style.display = 'block';
        document.getElementById('slipOverlayDeposit').style.display = 'block';
    }
}

function closeSlipPreview(type) {
    if (type === 'main') {
        document.getElementById('slipModalMain').style.display = 'none';
        document.getElementById('slipOverlayMain').style.display = 'none';
    } else if (type === 'deposit') {
        document.getElementById('slipModalDeposit').style.display = 'none';
        document.getElementById('slipOverlayDeposit').style.display = 'none';
    }
}
</script>

</body>
</html>
