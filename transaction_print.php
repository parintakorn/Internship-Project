<?php
include 'connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$orderid = isset($_GET['id']) ? $_GET['id'] : null;

if (!$orderid) {
    die("<h3>Error: ไม่พบ Order ID ใน URL</h3><p>กรุณาระบุ ?id=xxx</p>");
}

// ดึงข้อมูล Transaction - ใช้ TO_CHAR เหมือน transaction_list
$sqlTrans = "SELECT t.*, TO_CHAR(t.ORDERDATE, 'DD-MON-YYYY') as ORDERDATE_DISPLAY 
             FROM TRANSACTION t WHERE t.ORDERID = :oid";
$stidTrans = oci_parse($conn, $sqlTrans);

if (!$stidTrans) {
    $e = oci_error($conn);
    die("Parse Error: " . htmlspecialchars($e['message']));
}

oci_bind_by_name($stidTrans, ':oid', $orderid);
$exec = oci_execute($stidTrans);

if (!$exec) {
    $e = oci_error($stidTrans);
    die("Execute Error: " . htmlspecialchars($e['message']));
}

$trans = oci_fetch_assoc($stidTrans);

if (!$trans) {
    die("<h3>Error: ไม่พบข้อมูลธุรกรรม</h3><p>Order ID: " . htmlspecialchars($orderid) . " ไม่มีในระบบ</p>");
}

// ดึงข้อมูลเพิ่มเติม
$customerName = '';
$typename = '';
$courseName = '';
$coursePrice = 0;

// ดึงชื่อลูกค้า (ถ้ามี)
if ($trans['CUSTOMERID']) {
    $sqlCustomer = "SELECT CUSTOMERNAME FROM MEMBER WHERE CUSTOMERID = :cid";
    $stidCust = oci_parse($conn, $sqlCustomer);
    oci_bind_by_name($stidCust, ':cid', $trans['CUSTOMERID']);
    oci_execute($stidCust);
    $custRow = oci_fetch_assoc($stidCust);
    if ($custRow) {
        $customerName = $custRow['CUSTOMERNAME'];
    }
}

// ดึงชื่อ Menu Type
if ($trans['MENUTYPEID']) {
    $sqlType = "SELECT TYPENAME FROM MENU_TYPE WHERE MENUTYPEID = :mid";
    $stidType = oci_parse($conn, $sqlType);
    oci_bind_by_name($stidType, ':mid', $trans['MENUTYPEID']);
    oci_execute($stidType);
    $typeRow = oci_fetch_assoc($stidType);
    if ($typeRow) {
        $typename = $typeRow['TYPENAME'];
    }
}

// ✅ ดึงทุก SECTION (คอร์สทั้งหมด)
$sqlSections = "SELECT os.SECTION_NUMBER, os.MENUTYPEID, os.COURSEID, os.PERSON_COUNT,
                       mt.TYPENAME, mc.COURSENAME, mc.COURSEPRICE
                FROM ORDER_SECTION os
                LEFT JOIN MENU_TYPE mt ON os.MENUTYPEID = mt.MENUTYPEID
                LEFT JOIN MENU_COURSE mc ON os.COURSEID = mc.COURSEID
                WHERE os.ORDERID = :oid
                ORDER BY os.SECTION_NUMBER";
$stidSections = oci_parse($conn, $sqlSections);
oci_bind_by_name($stidSections, ':oid', $orderid);
oci_execute($stidSections);

$sections = [];
while ($section = oci_fetch_assoc($stidSections)) {
    $sections[] = $section;
}

// ✅ แก้ Query Items เพิ่ม CHARGE_FLAG + MENUTYPEID
$sqlItems = "SELECT oi.MENUID, oi.QUANTITY, oi.TYPE, oi.SECTION_NUMBER, oi.CHARGE_FLAG,
                    m.MENUNAME, m.PRICE_ALACARTE, m.PRICE_OMAKASE, os.MENUTYPEID
             FROM ORDER_ITEM oi
             JOIN MENU m ON oi.MENUID = m.MENUID
             LEFT JOIN ORDER_SECTION os ON oi.ORDERID = os.ORDERID AND oi.SECTION_NUMBER = os.SECTION_NUMBER
             WHERE oi.ORDERID = :oid
             ORDER BY oi.SECTION_NUMBER, oi.MENUID";
$stidItems = oci_parse($conn, $sqlItems);
oci_bind_by_name($stidItems, ':oid', $orderid);
oci_execute($stidItems);

$items = [];
while ($item = oci_fetch_assoc($stidItems)) {
    $items[] = $item;
}

// ✅ คำนวณ Sub Total = รวมทุกอย่างที่แสดงใน receipt
$subTotal = 0;

// 1. รวมคอร์สทุก SECTION
foreach ($sections as $section) {
    if ($section['COURSEID'] && $section['COURSEPRICE'] > 0) {
        $amount = floatval($section['COURSEPRICE']) * intval($section['PERSON_COUNT']);
        $subTotal += $amount;
    }
}

// 2. รวมเมนูทุกรายการ
foreach ($items as $item) {
    $sectionTypeId = intval($item['MENUTYPEID'] ?? 0);
    
    // คำนวณราคาตามประเภท Section
    if ($sectionTypeId == 1) {
        // A La Carte - ใช้ PRICE_ALACARTE
        $itemAmount = floatval($item['PRICE_ALACARTE']) * intval($item['QUANTITY']);
    } else {
        // Omakase - ใช้ PRICE_OMAKASE
        $itemAmount = floatval($item['PRICE_OMAKASE']) * intval($item['QUANTITY']);
    }
    
    $subTotal += $itemAmount;
}

$discount = floatval($trans['DISCOUNTMEMBER'] ?? 0);
$grandTotal = floatval($trans['TOTALPRICE'] ?? 0);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Receipt - Order #<?= htmlspecialchars($orderid) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'TH Sarabun New', 'Sarabun', 'Tahoma', 'Arial', sans-serif;
    }
    
    body {
        font-family: 'TH Sarabun New', 'Sarabun', 'Tahoma', 'Arial', sans-serif;
        background: #f0f0f0;
        padding: 20px;
        font-weight: 400;
    }
    
    .receipt {
        width: 300px;
        margin: 0 auto;
        background: white;
        padding: 20px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    
    .header {
        text-align: center;
        border-bottom: 2px dashed #000;
        padding-bottom: 15px;
        margin-bottom: 15px;
    }
    
    .logo {
        width: 192px;
        height: 192px;
        margin: 0 auto 10px;
        display: block;
        border-radius: 8px;
    }
    
    .restaurant-name {
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 8px;
    }
    
    .restaurant-info {
        font-size: 11px;
        line-height: 1.6;
        margin-bottom: 5px;
        font-weight: 400;
    }
    
    .receipt-info {
        font-size: 12px;
        margin-bottom: 15px;
        font-weight: 500;
    }
    
    .receipt-info div {
        margin-bottom: 3px;
    }
    
    .items-table {
        width: 100%;
        margin-bottom: 15px;
        border-bottom: 1px dashed #000;
        padding-bottom: 10px;
    }
    
    .items-header {
        display: flex;
        justify-content: space-between;
        font-weight: 700;
        font-size: 12px;
        margin-bottom: 8px;
        border-bottom: 1px solid #000;
        padding-bottom: 5px;
    }
    
    .item-row {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        margin-bottom: 5px;
        font-weight: 400;
    }
    
    .item-qty {
        width: 30px;
        font-weight: 600;
    }
    
    .item-name {
        flex: 1;
        padding: 0 10px;
        font-weight: 400;
    }
    
    .item-type {
        font-size: 10px;
        color: #666;
        font-weight: 400;
    }
    
    .item-amount {
        text-align: right;
        width: 70px;
        font-weight: 600;
    }
    
    .totals {
        font-size: 13px;
        margin-bottom: 15px;
        font-weight: 500;
    }
    
    .totals-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
    }
    
    .totals-row.grand-total {
        font-weight: 700;
        font-size: 16px;
        border-top: 2px solid #000;
        border-bottom: 2px solid #000;
        padding: 8px 0;
        margin-top: 10px;
    }
    
    .signature-area {
        border-top: 2px dashed #000;
        padding-top: 20px;
        margin-top: 20px;
        text-align: center;
        font-weight: 500;
    }
    
    .signature-line {
        margin: 30px auto 10px;
        width: 200px;
        border-top: 1px dotted #000;
    }
    
    .footer {
        text-align: center;
        font-size: 11px;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 2px dashed #000;
        font-weight: 400;
    }
    
    .print-button {
        position: fixed;
        bottom: 30px;
        right: 30px;
        padding: 15px 30px;
        background: #27ae60;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        cursor: pointer;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        font-weight: 600;
    }
    
    .print-button:hover {
        background: #229954;
    }
    
    /* Print styles for 58mm thermal paper */
    @media print {
        @page {
            size: 58mm 210mm;
            margin: 0;
        }
        
        body {
            background: white;
            padding: 0;
            margin: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            font-weight: 400;
            font-family: 'TH Sarabun New', 'Tahoma', sans-serif !important;
        }
        
        * {
            font-family: 'TH Sarabun New', 'Tahoma', sans-serif !important;
        }
        
        .receipt {
            width: 58mm;
            max-width: 58mm;
            box-shadow: none;
            padding: 2mm 3mm;
            margin: 0;
        }
        
        .header {
            padding-bottom: 2mm;
            margin-bottom: 2mm;
        }
        
        .logo {
            width: 36mm;
            height: 36mm;
            margin-bottom: 1mm;
        }
        
        .restaurant-name {
            font-size: 14pt;
            font-weight: 700;
            margin-bottom: 1mm;
            color: #000;
        }
        
        .restaurant-info {
            font-size: 8pt;
            line-height: 1.4;
            margin-bottom: 0;
            color: #000;
            font-weight: 400;
        }
        
        .receipt-info {
            font-size: 9pt;
            margin-bottom: 2mm;
            color: #000;
            font-weight: 500;
        }
        
        .receipt-info div {
            margin-bottom: 0.5mm;
            line-height: 1.3;
        }
        
        .items-table {
            margin-bottom: 2mm;
            padding-bottom: 2mm;
        }
        
        .items-header {
            font-size: 9pt;
            font-weight: 700;
            margin-bottom: 1mm;
            padding-bottom: 1mm;
            color: #000;
        }
        
        .item-row {
            font-size: 9pt;
            margin-bottom: 1mm;
            line-height: 1.3;
            color: #000;
            font-weight: 400;
        }
        
        .item-qty {
            width: 8mm;
            font-weight: 600;
        }
        
        .item-name {
            flex: 1;
            padding: 0 2mm;
            font-weight: 400;
        }
        
        .item-type {
            font-size: 7pt;
        }
        
        .item-amount {
            text-align: right;
            width: 15mm;
            font-weight: 600;
        }
        
        .totals {
            font-size: 10pt;
            margin-bottom: 2mm;
            color: #000;
            font-weight: 500;
        }
        
        .totals-row {
            margin-bottom: 1.5mm;
            line-height: 1.3;
        }
        
        .totals-row.grand-total {
            font-weight: 700;
            font-size: 12pt;
            padding: 2mm 0;
            margin-top: 2mm;
            color: #000;
        }
        
        .signature-area {
            padding-top: 3mm;
            margin-top: 3mm;
            font-size: 8pt;
            color: #000;
            font-weight: 500;
        }
        
        .signature-line {
            margin: 5mm auto 2mm;
            width: 40mm;
        }
        
        .footer {
            font-size: 8pt;
            margin-top: 2mm;
            padding-top: 2mm;
            color: #000;
            line-height: 1.4;
            font-weight: 400;
        }
        
        .print-button {
            display: none;
        }
        
        * {
            color: #000 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        .header,
        .items-table,
        .signature-area,
        .footer {
            border-color: #000 !important;
        }
        
        .totals-row.grand-total {
            border-top-color: #000 !important;
            border-bottom-color: #000 !important;
            border-width: 2px !important;
        }
    }
    </style>
</head>
<body>

<div class="receipt">
    <div class="header">
        <img src="img/1212.jpg" alt="BIGURI Logo" class="logo">
        <div class="restaurant-name">BIGURI OMAKASE</div>
        <div class="restaurant-info">
            <br>
            52 ซอยเสือใหญ่อุทิศ (รัชดาภิเษก36แยก11)<br>
            แขวงจันทรเกษม เขตจตุจักร<br>
            กรุงเทพมหานคร 10900<br>
            Tel: 062-953-2761<br>
        </div>
    </div>
    
    <div class="receipt-info">
        <div>Date: <?= $trans['ORDERDATE_DISPLAY'] ? date('d M Y', strtotime($trans['ORDERDATE_DISPLAY'])) : '-' ?></div>
        <div>Time: <?= htmlspecialchars($trans['ORDERTIME'] ?? '-') ?></div>
        <?php if ($trans['CUSTOMERID']): ?>
        <div>Customer: <?= htmlspecialchars($customerName) ?></div>
        <?php else: ?>
        <div>Customer: Guest</div>
        <?php endif; ?>
        <?php if ($trans['MENUTYPEID'] == 2 || $trans['MENUTYPEID'] == 3): ?>
        <div>Type: <?= htmlspecialchars($typename) ?></div>
        <?php else: ?>
        <div>Type: <?= htmlspecialchars($typename ?: 'A la carte') ?></div>
        <?php endif; ?>
        <div>Order ID: #<?= htmlspecialchars($orderid) ?></div>
    </div>
    
    <div class="items-table">
        <div class="items-header">
            <div class="item-qty">Qty.</div>
            <div class="item-name">Item</div>
            <div class="item-amount">Amount</div>
        </div>
        
        <!-- ✅ แสดงคอร์สทุกอันจาก ORDER_SECTION -->
        <?php foreach ($sections as $section): ?>
            <?php if ($section['COURSEID'] && $section['COURSENAME']): ?>
            <div class="item-row">
                <div class="item-qty"><?= $section['PERSON_COUNT'] ?></div>
                <div class="item-name"><?= htmlspecialchars($section['COURSENAME']) ?></div>
                <div class="item-amount"><?= number_format(floatval($section['COURSEPRICE']) * intval($section['PERSON_COUNT']), 2) ?></div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <!-- ✅ แสดง Items - Buffet (ไม่มีราคา) vs แลกซื้อ (มีราคา) -->
        <?php foreach ($items as $item): ?>
            <?php 
$sectionTypeId = intval($item['MENUTYPEID'] ?? 0);

// คำนวณราคาที่จะแสดง
if ($sectionTypeId == 1) {
    $displayPrice = floatval($item['PRICE_ALACARTE']) * intval($item['QUANTITY']);
} else {
    $displayPrice = floatval($item['PRICE_OMAKASE']) * intval($item['QUANTITY']);
}

// ✅ กำหนด label ตามเงื่อนไข - ตรวจสอบจากราคา OMAKASE
$label = '';
if ($sectionTypeId == 2) { // Omakase/Buffet
    $priceOmakase = floatval($item['PRICE_OMAKASE']);
    if ($priceOmakase == 0) {
        // ราคา Omakase = 0 = Buffet
        $label = '(Buffet)';
    } else {
        // ราคา Omakase > 0 = แลกซื้อ
        $label = '(แลกซื้อ)';
    }
}
?>
            
            <div class="item-row">
                <div class="item-qty"><?= $item['QUANTITY'] ?></div>
                <div class="item-name">
                    <?= htmlspecialchars($item['MENUNAME']) ?>
                    <?php if ($label): ?>
                        <div class="item-type"><?= $label ?></div>
                    <?php endif; ?>
                </div>
                <div class="item-amount"><?= number_format($displayPrice, 2) ?></div>
            </div>
        <?php endforeach; ?>
        
        <?php if (count($items) == 0 && empty($sections)): ?>
        <div class="item-row">
            <div class="item-name" style="text-align: center; color: #999;">ไม่มีรายการอาหาร</div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="totals">
        <div class="totals-row">
            <div>Sub Total</div>
            <div><?= number_format($subTotal, 2) ?></div>
        </div>
        
        <?php if ($discount > 0): ?>
        <div class="totals-row">
            <div>Member Discount</div>
            <div>-<?= number_format($discount, 2) ?></div>
        </div>
        <?php endif; ?>
        
        <div class="totals-row grand-total">
            <div>Grand Total</div>
            <div><?= number_format($grandTotal, 2) ?></div>
        </div>
    </div>
    
    <div class="signature-area">
        <div class="signature-line"></div>
        <div>Customer Signature</div>
    </div>
    
    <div class="footer">
        Thank you for dining with us<br>
        Facebook : BIGURI Omakase & Buffet
    </div>
</div>

<button class="print-button" onclick="window.print()">🖨️ Print Receipt</button>

</body>
</html>