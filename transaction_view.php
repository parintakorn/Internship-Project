<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'connect.php';

$orderid = $_GET['id'] ?? null;

if (!$orderid) {
    header("Location: transaction_list.php");
    exit();
}

echo "<!-- DEBUG: Looking for Order ID: $orderid -->";

// ดึงข้อมูลหลัก
$sql = "SELECT t.*, mt.TYPENAME, mc.COURSENAME, mc.COURSEPRICE,
               TO_CHAR(t.ORDERDATE, 'DD-MON-YYYY') as ORDERDATE_DISPLAY
        FROM TRANSACTION t
        LEFT JOIN MENU_TYPE mt ON t.MENUTYPEID = mt.MENUTYPEID
        LEFT JOIN MENU_COURSE mc ON t.COURSEID = mc.COURSEID
        WHERE t.ORDERID = :oid";
$stid = oci_parse($conn, $sql);
oci_bind_by_name($stid, ":oid", $orderid);

if (!oci_execute($stid)) {
    $e = oci_error($stid);
    die("Query Error: " . $e['message']);
}

$order = oci_fetch_assoc($stid);

if (!$order) {
    echo "<!-- DEBUG: No order found with ID: $orderid -->";
    die("Order not found");
}

echo "<!-- DEBUG: Order found -->";
echo "<!-- DEBUG Order Data: " . print_r($order, true) . " -->";

// ดึงรายการเมนูที่สั่ง
// ดึงรายการเมนูที่สั่ง พร้อม CHARGE_FLAG และ MENUTYPEID
$itemSql = "SELECT oi.QUANTITY, oi.MENUID, oi.CHARGE_FLAG, oi.SECTION_NUMBER,
                   m.MENUNAME, m.PRICE_ALACARTE, m.PRICE_OMAKASE,
                   os.MENUTYPEID
            FROM ORDER_ITEM oi
            LEFT JOIN MENU m ON oi.MENUID = m.MENUID
            LEFT JOIN ORDER_SECTION os ON oi.ORDERID = os.ORDERID AND oi.SECTION_NUMBER = os.SECTION_NUMBER
            WHERE oi.ORDERID = :oid";
$itemStid = oci_parse($conn, $itemSql);
oci_bind_by_name($itemStid, ":oid", $orderid);

if (!oci_execute($itemStid)) {
    $e = oci_error($itemStid);
    echo "<!-- DEBUG: Item query error: " . $e['message'] . " -->";
}
// ดึงข้อมูล Section ทั้งหมด (คอร์สแต่ละคน)
$sectionSql = "SELECT os.SECTION_NUMBER, os.PERSON_COUNT, os.COURSEID,
                      mc.COURSENAME, mc.COURSEPRICE
               FROM ORDER_SECTION os
               LEFT JOIN MENU_COURSE mc ON os.COURSEID = mc.COURSEID
               WHERE os.ORDERID = :oid
               ORDER BY os.SECTION_NUMBER";
$sectionStid = oci_parse($conn, $sectionSql);
oci_bind_by_name($sectionStid, ":oid", $orderid);
oci_execute($sectionStid);

echo "<!-- DEBUG: Section query executed -->";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Transaction Detail</title>
    <style>
        body { 
            font-family: Arial; 
            margin: 0; 
            padding: 0;
            background: #fafafa;
        }
        
        .top-bar {
            width: 100%;
            background-color: #f5f5f5;
            padding: 12px 18px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 20;
        }
        
        .back-btn {
            font-size: 22px;
            margin-right: 15px;
            cursor: pointer;
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid #ccc;
            background: white;
        }
        
        .container {
            margin-top: 90px;
            margin-left: auto;
            margin-right: auto;
            max-width: 900px;
            padding: 30px;
        }
        
        .info-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        
        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-label {
            font-weight: bold;
            width: 180px;
            color: #555;
        }
        
        .info-value {
            flex: 1;
            color: #333;
        }
        
        table {
            width: 100%;
            background: white;
            border-collapse: collapse;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        table, th, td {
            border: 1px solid #ddd;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
        }
        
        th {
            background: #3498db;
            color: white;
            font-weight: bold;
        }
        
        .total-row {
            background: #f8f9fa;
            font-weight: bold;
            font-size: 18px;
        }
        
        .price {
            color: #27ae60;
            font-weight: bold;
        }
        
        .type-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: bold;
        }
        
        .type-alacarte {
            background: #3498db;
            color: white;
        }
        
        .type-omakase {
            background: #e74c3c;
            color: white;
        }
        
        .type-buffet {
            background: #f39c12;
            color: white;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        
        .btn-back {
            background: #95a5a6;
            color: white;
        }
        
        .btn-back:hover {
            background: #7f8c8d;
        }
        
        h3 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="top-bar">
    <button class="back-btn" onclick="window.location.href='transaction_list.php'">←</button>
    <h2>Transaction Detail - Order #<?= htmlspecialchars($order['ORDERID']) ?></h2>
</div>

<div class="container">
    
    <!-- Order Information -->
    <div class="info-card">
        <h3>📋 ข้อมูลออเดอร์</h3>
        
        <div class="info-row">
            <div class="info-label">Order ID:</div>
            <div class="info-value"><strong><?= htmlspecialchars($order['ORDERID']) ?></strong></div>
        </div>
        
        <div class="info-row">
            <div class="info-label">Customer ID:</div>
            <div class="info-value"><?= $order['CUSTOMERID'] ? htmlspecialchars($order['CUSTOMERID']) : '<em>Guest</em>' ?></div>
        </div>
        
        <div class="info-row">
            <div class="info-label">Order Date:</div>
            <div class="info-value"><?php
$displayDate = $order['ORDERDATE_DISPLAY'] ?? null;
echo $displayDate ? date('d F Y', strtotime($displayDate)) : '-';
?></div>

        </div>
        
        <div class="info-row">
            <div class="info-label">Order Time:</div>
            <div class="info-value"><?= $order['ORDERTIME'] ? htmlspecialchars($order['ORDERTIME']) : '-' ?></div>
        </div>
        
        <div class="info-row">
            <div class="info-label">Menu Type:</div>
            <div class="info-value">
                <?php 
                $typeClass = '';
                if ($order['MENUTYPEID'] == 1) $typeClass = 'type-alacarte';
                elseif ($order['MENUTYPEID'] == 2) $typeClass = 'type-omakase';
                elseif ($order['MENUTYPEID'] == 3) $typeClass = 'type-buffet';
                ?>
                <span class="type-badge <?= $typeClass ?>">
                    <?= htmlspecialchars($order['TYPENAME']) ?>
                </span>
            </div>
        </div>
        
        <?php if ($order['COURSENAME']): ?>
        <div class="info-row">
            <div class="info-label">Course:</div>
            <div class="info-value">
                <?= htmlspecialchars($order['COURSENAME']) ?>
                <span style="color: #27ae60; font-weight: bold;">
                    (<?= number_format($order['COURSEPRICE'], 2) ?> ฿)
                </span>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="info-row">
            <div class="info-label">Discount (Member):</div>
            <div class="info-value">
                <?= $order['DISCOUNTMEMBER'] ? number_format($order['DISCOUNTMEMBER'], 2) . ' ฿' : '0.00 ฿' ?>
            </div>
        </div>
        
        <div class="info-row" style="border-bottom: none; font-size: 20px; margin-top: 10px;">
            <div class="info-label" style="color: #2c3e50;">Total Price:</div>
            <div class="info-value price" style="font-size: 24px;">
                <?= number_format($order['TOTALPRICE'], 2) ?> ฿
            </div>
        </div>
    </div>
    
    <!-- Menu Items -->
    <h3>🍱 รายการเมนูที่สั่ง</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 50px;">#</th>
                <th>Menu Name</th>
                <th style="width: 100px;">Quantity</th>
                <th style="width: 150px;">Price/Unit</th>
                <th style="width: 150px;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
        <?php 
$no = 1;
$subtotal = 0;
$courseTotal = 0;
$hasItems = false;

// แสดงคอร์สทุก Section
while ($section = oci_fetch_assoc($sectionStid)): 
    if ($section['COURSEID'] && $section['COURSENAME']):
        $personCount = intval($section['PERSON_COUNT']);
        $coursePrice = floatval($section['COURSEPRICE']);
        $sectionTotal = $coursePrice * $personCount;
        $courseTotal += $sectionTotal;
?>
    <tr style="background: #f0f8ff;">
        <td style="text-align: center;"><?= $no++ ?></td>
        <td>
            <strong><?= htmlspecialchars($section['COURSENAME']) ?></strong> 
            <em>(Section #<?= $section['SECTION_NUMBER'] ?>)</em>
        </td>
        <td style="text-align: center;"><?= $personCount ?> ท่าน</td>
        <td style="text-align: right;"><?= number_format($coursePrice, 2) ?> ฿</td>
        <td style="text-align: right;" class="price"><?= number_format($sectionTotal, 2) ?> ฿</td>
    </tr>
<?php 
    endif;
endwhile;
        
        while ($item = oci_fetch_assoc($itemStid)): 
    $hasItems = true;
    
    // คำนวณราคาตาม CHARGE_FLAG และ MENUTYPEID
    $chargeFlag = $item['CHARGE_FLAG'] ?? 'N';
    $sectionTypeId = intval($item['MENUTYPEID'] ?? 0);
    $unitPrice = 0;
    $itemLabel = '';
    
    if ($chargeFlag == 'Y') {
        // คิดเงิน - ใช้ราคาตามประเภท Section
        if ($sectionTypeId == 1) {
            // A La Carte
            $unitPrice = $item['PRICE_ALACARTE'];
        } else {
            // Omakase - ค่าแลกซื้อ
            $unitPrice = $item['PRICE_OMAKASE'];
            $itemLabel = ' (ค่าแลกซื้อ)';
        }
    } else {
        // ไม่คิดเงิน - Buffet
        $unitPrice = 0;
        $itemLabel = ' (Buffet - ไม่คิดเงิน)';
    }
    
    $itemTotal = $unitPrice * $item['QUANTITY'];
    $subtotal += $itemTotal;
?>
            <tr>
                <td style="text-align: center;"><?= $no++ ?></td>
<td>
    <?= htmlspecialchars($item['MENUNAME']) ?>
    <?php if ($itemLabel): ?>
        <em style="color: #666; font-size: 12px;"><?= $itemLabel ?></em>
    <?php endif; ?>
</td>                <td style="text-align: center;"><?= $item['QUANTITY'] ?></td>
                <td style="text-align: right;"><?= number_format($unitPrice, 2) ?> ฿</td>
                <td style="text-align: right;" class="price"><?= number_format($itemTotal, 2) ?> ฿</td>
            </tr>
        <?php endwhile; ?>
        
        <?php if (!$hasItems && (!$order['COURSENAME'] || $order['MENUTYPEID'] == 1)): ?>
            <tr>
                <td colspan="5" style="text-align: center; padding: 20px; color: #999;">
                    <em>ไม่มีรายการเมนูเพิ่มเติม</em>
                </td>
            </tr>
        <?php endif; ?>
        
        <?php if ($courseTotal > 0): ?>
    <tr class="total-row">
        <td colspan="4" style="text-align: right; padding-right: 15px;">Course Total:</td>
        <td style="text-align: right;" class="price"><?= number_format($courseTotal, 2) ?> ฿</td>
    </tr>
<?php endif; ?>

<?php if ($hasItems && $subtotal > 0): ?>
    <tr class="total-row">
        <td colspan="4" style="text-align: right; padding-right: 15px;">Additional Items:</td>
        <td style="text-align: right;" class="price"><?= number_format($subtotal, 2) ?> ฿</td>
    </tr>
<?php endif; ?>

        
        <tr class="total-row" style="background: #2c3e50; color: white; font-size: 18px;">
            <td colspan="4" style="text-align: right; padding-right: 15px;">GRAND TOTAL:</td>
            <td style="text-align: right; color: #2ecc71; font-size: 20px;">
                <?= number_format($order['TOTALPRICE'], 2) ?> ฿
            </td>
        </tr>
        </tbody>
    </table>
    
    <button class="btn btn-back" onclick="window.location.href='transaction_list.php'">
        ← กลับไปรายการธุรกรรม
    </button>
</div>

</body>
</html>