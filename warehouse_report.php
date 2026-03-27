<?php
include 'config.php';

// ========================================
// รับค่า Filter
// ========================================
$filterType = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selectedYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : 'all';

// สร้าง WHERE clause ตาม filter
$whereClause = "";
if ($filterType == 'daily') {
    $whereClause = "AND TRUNC(i.RECEIVE_DATE) = TO_DATE('$selectedDate', 'YYYY-MM-DD')";
} elseif ($filterType == 'monthly') {
    $whereClause = "AND TO_CHAR(i.RECEIVE_DATE, 'YYYY-MM') = '$selectedMonth'";
} elseif ($filterType == 'yearly') {
    $whereClause = "AND TO_CHAR(i.RECEIVE_DATE, 'YYYY') = '$selectedYear'";
}

// เพิ่ม filter หมวดหมู่
if ($categoryFilter != 'all') {
    $whereClause .= " AND p.CATEGORY = '$categoryFilter'";
}

// ========================================
// ดึงข้อมูลสถิติ
// ========================================

// จำนวนวัตถุดิบทั้งหมด
$query1 = "SELECT COUNT(*) as TOTAL FROM PRODUCTS";
$stid1 = oci_parse($conn, $query1);
oci_execute($stid1);
$row1 = oci_fetch_assoc($stid1);
$stats['total_products'] = $row1['TOTAL'];
oci_free_statement($stid1);

// จำนวนวัตถุดิบในคลัง
$query2 = "SELECT COUNT(*) as TOTAL FROM INVENTORY WHERE STATUS = 'IN_STOCK'";
$stid2 = oci_parse($conn, $query2);
oci_execute($stid2);
$row2 = oci_fetch_assoc($stid2);
$stats['in_stock'] = $row2['TOTAL'];
oci_free_statement($stid2);

// น้ำหนักรวมในคลัง
$query3 = "SELECT NVL(SUM(WEIGHT), 0) as TOTAL_WEIGHT FROM INVENTORY WHERE STATUS = 'IN_STOCK'";
$stid3 = oci_parse($conn, $query3);
oci_execute($stid3);
$row3 = oci_fetch_assoc($stid3);
$stats['total_weight'] = $row3['TOTAL_WEIGHT'];
oci_free_statement($stid3);

// จำนวนที่เบิกออกวันนี้
$query4 = "SELECT COUNT(*) as TOTAL FROM WITHDRAWAL_HISTORY WHERE TRUNC(WITHDRAW_DATE) = TRUNC(SYSDATE)";
$stid4 = oci_parse($conn, $query4);
oci_execute($stid4);
$row4 = oci_fetch_assoc($stid4);
$stats['today_withdrawals'] = $row4['TOTAL'];
oci_free_statement($stid4);

// น้ำหนักที่เบิกออกตาม filter
$query5 = "SELECT NVL(SUM(wh.AMOUNT), 0) as TOTAL_WITHDRAWN
           FROM WITHDRAWAL_HISTORY wh
           JOIN INVENTORY i ON wh.INVENTORY_ID = i.INVENTORY_ID
           WHERE 1=1 " . str_replace('i.RECEIVE_DATE', 'wh.WITHDRAW_DATE', $whereClause);
$stid5 = oci_parse($conn, $query5);
oci_execute($stid5);
$row5 = oci_fetch_assoc($stid5);
$stats['total_withdrawn'] = $row5['TOTAL_WITHDRAWN'];
oci_free_statement($stid5);

// มูลค่ารวมในคลัง
$query6 = "SELECT NVL(SUM(i.WEIGHT * p.PRICE_PER_GRAM), 0) as TOTAL_VALUE 
           FROM INVENTORY i 
           JOIN PRODUCTS p ON i.PRODUCT_ID = p.PRODUCT_ID
           WHERE i.STATUS = 'IN_STOCK'";
$stid6 = oci_parse($conn, $query6);
oci_execute($stid6);
$row6 = oci_fetch_assoc($stid6);
$stats['total_value'] = $row6['TOTAL_VALUE'];
oci_free_statement($stid6);

// ดึงรายการหมวดหมู่
$queryCategories = "SELECT DISTINCT CATEGORY FROM PRODUCTS ORDER BY CATEGORY";
$stidCategories = oci_parse($conn, $queryCategories);
oci_execute($stidCategories);
$categories = [];
while ($catRow = oci_fetch_assoc($stidCategories)) {
    $categories[] = $catRow['CATEGORY'];
}
oci_free_statement($stidCategories);

// ========================================
// ดึงข้อมูลรายการวัตถุดิบในคลัง (ใช้ PREFIX จาก PREFIXES)
// ========================================
$queryInventory = "SELECT 
                    pf.PREFIX_CODE || '-' || LPAD(i.INVENTORY_ID, 6, '0') as INVENTORY_CODE,
                    p.PRODUCT_NAME,
                    p.CATEGORY,
                    i.WEIGHT,
                    TO_CHAR(i.RECEIVE_DATE, 'DD/MM/YYYY') as RECEIVE_DATE,
                    i.STATUS,
                    TRUNC(SYSDATE - i.RECEIVE_DATE) as DAYS_IN_STOCK,
                    i.INVENTORY_ID
                   FROM INVENTORY i
                   JOIN PRODUCTS p ON i.PRODUCT_ID = p.PRODUCT_ID
                   LEFT JOIN PREFIXES pf ON i.PREFIX_ID = pf.PREFIX_ID
                   WHERE i.STATUS = 'IN_STOCK' $whereClause
                   ORDER BY i.RECEIVE_DATE DESC";
$stidInventory = oci_parse($conn, $queryInventory);
oci_execute($stidInventory);
$inventoryList = [];
while ($invRow = oci_fetch_assoc($stidInventory)) {
    $inventoryList[] = $invRow;
}
oci_free_statement($stidInventory);

// ========================================
// ดึงข้อมูลรายการที่เบิกออก (ใช้ PREFIX จาก PREFIXES)
// ========================================
$queryWithdrawn = "SELECT 
                    wh.WITHDRAWAL_ID,
                    pf.PREFIX_CODE || '-' || LPAD(i.INVENTORY_ID, 6, '0') as INVENTORY_CODE,
                    p.PRODUCT_NAME,
                    p.CATEGORY,
                    wh.AMOUNT,
                    TO_CHAR(wh.WITHDRAW_DATE, 'DD/MM/YYYY HH24:MI') as WITHDRAW_DATE,
                    wh.REASON,
                    TRUNC(SYSDATE - wh.WITHDRAW_DATE) as DAYS_AGO
                   FROM WITHDRAWAL_HISTORY wh
                   JOIN INVENTORY i ON wh.INVENTORY_ID = i.INVENTORY_ID
                   JOIN PRODUCTS p ON i.PRODUCT_ID = p.PRODUCT_ID
                   LEFT JOIN PREFIXES pf ON i.PREFIX_ID = pf.PREFIX_ID
                   WHERE 1=1 " . str_replace('i.RECEIVE_DATE', 'wh.WITHDRAW_DATE', $whereClause) . "
                   ORDER BY wh.WITHDRAW_DATE DESC";
$stidWithdrawn = oci_parse($conn, $queryWithdrawn);
oci_execute($stidWithdrawn);
$withdrawnList = [];
while ($wdRow = oci_fetch_assoc($stidWithdrawn)) {
    $withdrawnList[] = $wdRow;
}
oci_free_statement($stidWithdrawn);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานคลังวัตถุดิบ</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Kanit', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 0;
        }

        .top-bar {
            width: 100%;
            background: rgba(255,255,255,0.95);
            padding: 15px 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 20;
            backdrop-filter: blur(10px);
        }

        .menu-btn, .back-btn {
            font-size: 24px;
            margin-right: 15px;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 8px;
            border: none;
            background: #667eea;
            color: white;
            transition: all 0.3s;
        }

        .menu-btn:hover, .back-btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        #sidebar {
            width: 280px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: -280px;
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            color: white;
            transition: left 0.3s ease;
            padding-top: 60px;
            z-index: 1000;
            box-shadow: 4px 0 15px rgba(0,0,0,0.3);
            overflow-y: auto;
        }

        #sidebar.active {
            left: 0;
        }

        #sidebar a {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            text-decoration: none;
            color: rgba(255,255,255,0.9);
            font-size: 16px;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        #sidebar a:hover {
            background: rgba(255,255,255,0.1);
            border-left-color: #667eea;
            padding-left: 30px;
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }

        .overlay.active {
            display: block;
        }

        .container {
            margin-top: 90px;
            margin-left: 30px;
            margin-right: 30px;
            padding-bottom: 50px;
        }

        .filter-box {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 10px 20px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }

        .filter-tab:hover {
            border-color: #3498db;
            background: #f0f8ff;
        }

        .filter-tab.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .date-selector {
            display: none;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            margin-top: 10px;
        }

        .date-selector.show {
            display: block;
        }

        .date-selector input, .date-selector select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-right: 10px;
            font-family: 'Kanit', sans-serif;
        }

        .btn-filter {
            background: #27ae60;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-family: 'Kanit', sans-serif;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 5px solid;
        }

        .summary-card.products { border-color: #3498db; }
        .summary-card.stock { border-color: #27ae60; }
        .summary-card.weight { border-color: #f39c12; }
        .summary-card.withdrawn { border-color: #e74c3c; }
        .summary-card.today { border-color: #9b59b6; }
        .summary-card.value { border-color: #16a085; }

        .summary-label {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .summary-value {
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
        }

        .summary-sub {
            font-size: 11px;
            color: #95a5a6;
            margin-top: 5px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .btn-action {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Kanit', sans-serif;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-report-daily {
            background: #3498db;
            color: white;
        }

        .btn-report-monthly {
            background: #e67e22;
            color: white;
        }

        .btn-report-yearly {
            background: #9b59b6;
            color: white;
        }

        .btn-export {
            background: #27ae60;
            color: white;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        table {
            background: white;
            border-collapse: collapse;
            width: 100%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        table, th, td {
            border: 1px solid #ddd;
        }

        th, td {
            padding: 12px 8px;
            text-align: center;
        }

        th {
            background: #34495e;
            color: white;
            font-weight: bold;
        }

        tr:hover {
            background: #f5f5f5;
        }

        .category-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
            background: #e3f2fd;
            color: #1565c0;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-in-stock {
            background: #d4edda;
            color: #155724;
        }

        .btn-view, .btn-delete {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin: 0 3px;
            transition: all 0.3s;
        }

        .btn-view {
            background: #3498db;
            color: white;
        }

        .btn-view:hover {
            background: #2980b9;
            transform: scale(1.1);
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-delete:hover {
            background: #c0392b;
            transform: scale(1.1);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .modal-header h2 {
            color: #667eea;
            font-size: 24px;
        }

        .modal-close {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
        }

        .modal-close:hover {
            background: #c0392b;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-label {
            font-weight: 600;
            color: #666;
        }

        .detail-value {
            color: #333;
            font-weight: 500;
        }

        .confirm-modal .modal-content {
            max-width: 400px;
            text-align: center;
        }

        .confirm-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-confirm {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Kanit', sans-serif;
            transition: all 0.3s;
        }

        .btn-confirm.yes {
            background: #e74c3c;
            color: white;
        }

        .btn-confirm.yes:hover {
            background: #c0392b;
        }

        .btn-confirm.no {
            background: #95a5a6;
            color: white;
        }

        .btn-confirm.no:hover {
            background: #7f8c8d;
        }

        @media print {
            body {
                background: white;
            }
            .top-bar, .filter-box, .action-buttons {
                display: none;
            }
            .container {
                margin-top: 0;
            }
        }

        @media (max-width: 768px) {
            .container {
                margin-left: 10px;
                margin-right: 10px;
            }
            .summary-cards {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>

<div class="top-bar">
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
    <button class="back-btn" onclick="window.location.href='homepage.php'">←</button>
    <h2 style="margin:0">📊 รายงานคลังวัตถุดิบ</h2>
</div>

<div id="overlay" class="overlay" onclick="toggleMenu()"></div>

<div id="sidebar">
    <div style="padding:20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)">
        <h3 style="margin:0;margin-bottom:5px">🍱 Menu</h3>
        <p style="font-size:12px;color:rgba(255,255,255,0.7);margin:0">Restaurant Management</p>
    </div>
    <a href="index.php">🏠 Home</a>
    <a href="ingredient_warehouse.php">📦 คลังวัตถุดิบ</a>
    <a href="warehouse_report.php">📊 รายงานคลัง</a>
    <a href="ingredient.php">🥬 วัตถุดิบ</a>
    <a href="menu_list.php">🍽️ เมนู</a>
    <a href="recipe_list.php">📝 สูตรอาหาร</a>
    <a href="order_list.php">🛒 ออเดอร์</a>
    <a href="transaction_list.php">💳 ธุรกรรม</a>
    <a href="profit_list.php">💰 กำไร</a>
    <a href="member_list.php">👥 สมาชิก</a>
</div>

<div class="container">
    <h3>📊 สรุปรายงานคลังวัตถุดิบ</h3>
    
    <div class="filter-box">
        <strong style="font-size:16px;display:block;margin-bottom:15px">🔍 กรองตามช่วงเวลา</strong>
        
        <div class="filter-tabs">
            <div class="filter-tab <?=$filterType=='all'?'active':''?>" onclick="selectFilter('all')">📅 ทั้งหมด</div>
            <div class="filter-tab <?=$filterType=='daily'?'active':''?>" onclick="selectFilter('daily')">📆 รายวัน</div>
            <div class="filter-tab <?=$filterType=='monthly'?'active':''?>" onclick="selectFilter('monthly')">📊 รายเดือน</div>
            <div class="filter-tab <?=$filterType=='yearly'?'active':''?>" onclick="selectFilter('yearly')">📈 รายปี</div>
        </div>
        
        <div id="daily-selector" class="date-selector <?=$filterType=='daily'?'show':''?>">
            <form method="GET" style="display:inline">
                <input type="hidden" name="filter" value="daily">
                <input type="hidden" name="category" value="<?=$categoryFilter?>">
                <input type="date" name="date" value="<?=$selectedDate?>" required>
                <button type="submit" class="btn-filter">ดูข้อมูล</button>
            </form>
        </div>
        
        <div id="monthly-selector" class="date-selector <?=$filterType=='monthly'?'show':''?>">
            <form method="GET" style="display:inline">
                <input type="hidden" name="filter" value="monthly">
                <input type="hidden" name="category" value="<?=$categoryFilter?>">
                <input type="month" name="month" value="<?=$selectedMonth?>" required>
                <button type="submit" class="btn-filter">ดูข้อมูล</button>
            </form>
        </div>
        
        <div id="yearly-selector" class="date-selector <?=$filterType=='yearly'?'show':''?>">
            <form method="GET" style="display:inline">
                <input type="hidden" name="filter" value="yearly">
                <input type="hidden" name="category" value="<?=$categoryFilter?>">
                <select name="year" required>
                    <?php for($y=date('Y'); $y>=2020; $y--): ?>
                        <option value="<?=$y?>" <?=$y==$selectedYear?'selected':''?>><?=$y+543?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn-filter">ดูข้อมูล</button>
            </form>
        </div>
    </div>
    
    <div class="summary-cards">
        <div class="summary-card products">
            <div class="summary-label">📦 ชนิดวัตถุดิบ</div>
            <div class="summary-value"><?=number_format($stats['total_products'])?></div>
            <div class="summary-sub">ทั้งหมดในระบบ</div>
        </div>
        
        <div class="summary-card stock">
            <div class="summary-label">✅ รายการในคลัง</div>
            <div class="summary-value"><?=number_format($stats['in_stock'])?></div>
            <div class="summary-sub">สถานะมีสินค้า</div>
        </div>
        
        <div class="summary-card weight">
            <div class="summary-label">⚖️ น้ำหนักรวม</div>
            <div class="summary-value"><?=number_format($stats['total_weight'], 2)?></div>
            <div class="summary-sub">กรัม</div>
        </div>
        
        <div class="summary-card withdrawn">
            <div class="summary-label">📤 เบิกออก (ตาม Filter)</div>
            <div class="summary-value"><?=number_format($stats['total_withdrawn'], 2)?></div>
            <div class="summary-sub">กรัม</div>
        </div>
        
        <div class="summary-card today">
            <div class="summary-label">📋 เบิกออกวันนี้</div>
            <div class="summary-value"><?=number_format($stats['today_withdrawals'])?></div>
            <div class="summary-sub">รายการ</div>
        </div>
        
        <div class="summary-card value">
            <div class="summary-label">💰 มูลค่าประมาณการ</div>
            <div class="summary-value"><?=number_format($stats['total_value'], 2)?></div>
            <div class="summary-sub">บาท</div>
        </div>
    </div>
    
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:15px">
        <h3 style="margin:0">📋 รายละเอียดวัตถุดิบในคลัง</h3>
        
        <div class="action-buttons">
    <select onchange="filterByCategory(this.value)" style="padding:10px 15px;border:2px solid #3498db;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;background:white;color:#2c3e50;font-family:'Kanit',sans-serif">
        <option value="all" <?=$categoryFilter=='all'?'selected':''?>>📋 หมวดหมู่: ทั้งหมด</option>
        <?php foreach($categories as $cat): ?>
            <option value="<?=$cat?>" <?=$categoryFilter==$cat?'selected':''?>><?=$cat?></option>
        <?php endforeach; ?>
    </select>
    
    <a href="warehouse_report_daily.php?date=<?=$selectedDate?>" class="btn-action btn-report-daily">
        📋 รายงานรายวัน
    </a>
    
    <a href="warehouse_report_monthly.php?month=<?=$selectedMonth?>" class="btn-action btn-report-monthly">
        📋 รายงานรายเดือน
    </a>
    
    <a href="warehouse_report_yearly.php?year=<?=$selectedYear?>" class="btn-action btn-report-yearly">
        📋 รายงานรายปี
    </a>
    
    <button onclick="window.print()" class="btn-action btn-export">
        🖨️ พิมพ์รายงาน
    </button>
    
    <a href="ingredient_price_setup.php" class="btn-action" style="background:#9b59b6;color:white">
        ⚙️ ตั้งค่าราคาวัตถุดิบ
    </a>
    
    <a href="update_inventory_prefix.php" class="btn-action" style="background:#e67e22;color:white">
        🔄 แก้ไข Prefix เก่า
    </a>
</div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>รหัส</th>
                <th>ชื่อวัตถุดิบ</th>
                <th>หมวดหมู่</th>
                <th>น้ำหนัก (กรัม)</th>
                <th>วันที่รับเข้า</th>
                <th>จำนวนวันในคลัง</th>
                <th>สถานะ</th>
                <th>จัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php if(count($inventoryList) > 0): ?>
                <?php foreach($inventoryList as $item): ?>
                <tr>
                    <td><strong><?=htmlspecialchars($item['INVENTORY_CODE'])?></strong></td>
                    <td><?=htmlspecialchars($item['PRODUCT_NAME'])?></td>
                    <td><span class="category-badge"><?=htmlspecialchars($item['CATEGORY'])?></span></td>
                    <td><?=number_format($item['WEIGHT'], 2)?></td>
                    <td><?=$item['RECEIVE_DATE']?></td>
                    <td><?=$item['DAYS_IN_STOCK']?> วัน</td>
                    <td><span class="status-badge status-in-stock">มีในคลัง</span></td>
                    <td>
                        <button onclick="viewInventory('<?=$item['INVENTORY_CODE']?>')" class="btn-view" title="ดูรายละเอียด">
                            👁️
                        </button>
                        <button onclick="deleteInventory('<?=$item['INVENTORY_CODE']?>')" class="btn-delete" title="ลบ">
                            🗑️
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="padding:30px;color:#999">
                        <em>ไม่มีข้อมูลในช่วงเวลาที่เลือก</em>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <p style="margin-top:20px;color:#fff;background:rgba(0,0,0,0.3);padding:10px;border-radius:5px">
        <strong>Total Records:</strong> <?=count($inventoryList)?> รายการ
    </p>
    
    <!-- เพิ่มส่วนรายงานการเบิกออก -->
    <div style="margin-top:40px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h3 style="margin:0">📤 รายงานการเบิกออก</h3>
            <button onclick="toggleWithdrawnTable()" class="btn-action" style="background:#e74c3c;color:white">
                <span id="toggleIcon">👁️</span> <span id="toggleText">แสดงรายการ</span>
            </button>
        </div>
        
        <div id="withdrawnTableContainer" style="display:none">
            <table>
                <thead>
                    <tr>
                        <th>รหัสคลัง</th>
                        <th>ชื่อวัตถุดิบ</th>
                        <th>หมวดหมู่</th>
                        <th>น้ำหนักที่เบิก (กรัม)</th>
                        <th>วันที่เบิก</th>
                        <th>เหตุผล</th>
                        <th>เบิกไปแล้ว</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($withdrawnList) > 0): ?>
                        <?php foreach($withdrawnList as $item): ?>
                        <tr>
                            <td><strong><?=htmlspecialchars($item['INVENTORY_CODE'])?></strong></td>
                            <td><?=htmlspecialchars($item['PRODUCT_NAME'])?></td>
                            <td><span class="category-badge"><?=htmlspecialchars($item['CATEGORY'])?></span></td>
                            <td style="color:#e74c3c;font-weight:600"><?=number_format($item['AMOUNT'], 2)?></td>
                            <td><?=$item['WITHDRAW_DATE']?></td>
                            <td style="text-align:left;padding-left:15px">
                                <?=htmlspecialchars($item['REASON'] ?: '-')?>
                            </td>
                            <td><?=$item['DAYS_AGO']?> วัน</td>
                            <td>
                                <button onclick="viewWithdrawal(<?=$item['WITHDRAWAL_ID']?>)" class="btn-view" title="ดูรายละเอียด">
                                    👁️
                                </button>
                                <button onclick="deleteWithdrawal(<?=$item['WITHDRAWAL_ID']?>, '<?=htmlspecialchars($item['PRODUCT_NAME'])?>')" class="btn-delete" title="ลบ">
                                    🗑️
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="padding:30px;color:#999">
                                <em>ไม่มีรายการเบิกออกในช่วงเวลาที่เลือก</em>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <p style="margin-top:20px;color:#fff;background:rgba(0,0,0,0.3);padding:10px;border-radius:5px">
                <strong>Total Withdrawals:</strong> <?=count($withdrawnList)?> รายการ | 
                <strong>น้ำหนักรวม:</strong> <?=number_format($stats['total_withdrawn'], 2)?> กรัม
            </p>
        </div>
    </div>
</div>

<!-- Modal ดูรายละเอียด -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>📋 รายละเอียดวัตถุดิบ</h2>
            <button class="modal-close" onclick="closeViewModal()">✕</button>
        </div>
        <div id="viewDetails"></div>
    </div>
</div>

<!-- Modal ยืนยันการลบ -->
<div id="deleteModal" class="modal confirm-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>⚠️ ยืนยันการลบ</h2>
            <button class="modal-close" onclick="closeDeleteModal()">✕</button>
        </div>
        <p style="font-size:16px;margin:20px 0;color:#666">
            คุณแน่ใจหรือไม่ที่จะลบวัตถุดิบนี้?<br>
            <strong id="deleteItemName" style="color:#e74c3c"></strong>
        </p>
        <div class="confirm-buttons">
            <button class="btn-confirm yes" onclick="confirmDelete()">✓ ยืนยันลบ</button>
            <button class="btn-confirm no" onclick="closeDeleteModal()">✕ ยกเลิก</button>
        </div>
    </div>
</div>

<!-- Modal ดูรายละเอียดการเบิก -->
<div id="viewWithdrawalModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>📤 รายละเอียดการเบิก</h2>
            <button class="modal-close" onclick="closeViewWithdrawalModal()">✕</button>
        </div>
        <div id="viewWithdrawalDetails"></div>
    </div>
</div>

<!-- Modal ยืนยันการลบการเบิก -->
<div id="deleteWithdrawalModal" class="modal confirm-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>⚠️ ยืนยันการลบ</h2>
            <button class="modal-close" onclick="closeDeleteWithdrawalModal()">✕</button>
        </div>
        <p style="font-size:16px;margin:20px 0;color:#666">
            คุณแน่ใจหรือไม่ที่จะลบรายการเบิกนี้?<br>
            <strong id="deleteWithdrawalName" style="color:#e74c3c"></strong>
        </p>
        <div class="confirm-buttons">
            <button class="btn-confirm yes" onclick="confirmDeleteWithdrawal()">✓ ยืนยันลบ</button>
            <button class="btn-confirm no" onclick="closeDeleteWithdrawalModal()">✕ ยกเลิก</button>
        </div>
    </div>
</div>

<script>
let currentDeleteCode = '';
let currentDeleteWithdrawalId = null;

function toggleMenu() {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('overlay').classList.toggle('active');
}

function selectFilter(type) {
    document.querySelectorAll('.date-selector').forEach(el => el.classList.remove('show'));
    if(type === 'all') {
        window.location.href = 'warehouse_report.php?category=<?=$categoryFilter?>';
    } else {
        document.getElementById(type + '-selector').classList.add('show');
    }
}

function filterByCategory(category) {
    const params = new URLSearchParams(window.location.search);
    params.set('category', category);
    window.location.href = 'warehouse_report.php?' + params.toString();
}

function viewInventory(code) {
    document.getElementById('viewModal').classList.add('active');
    document.getElementById('viewDetails').innerHTML = '<div style="text-align:center;padding:20px">⏳ กำลังโหลด...</div>';
    
    fetch('view_inventory_detail.php?code=' + encodeURIComponent(code))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const item = data.inventory;
                const history = data.history || [];
                
                let historyHtml = '';
                if (history.length > 0) {
                    historyHtml = '<h3 style="color:#667eea;margin-top:20px;margin-bottom:10px">📤 ประวัติการเบิก</h3>';
                    history.forEach(h => {
                        historyHtml += `
                            <div class="detail-row">
                                <span>${h.WITHDRAW_DATE}</span>
                                <span style="color:#e74c3c;font-weight:600">${h.AMOUNT} กรัม</span>
                            </div>
                        `;
                    });
                }
                
                document.getElementById('viewDetails').innerHTML = `
                    <div class="detail-row">
                        <span class="detail-label">รหัสวัตถุดิบ:</span>
                        <span class="detail-value">${item.INVENTORY_CODE}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">ชื่อวัตถุดิบ:</span>
                        <span class="detail-value">${item.PRODUCT_NAME}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">หมวดหมู่:</span>
                        <span class="detail-value">${item.CATEGORY}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">น้ำหนัก:</span>
                        <span class="detail-value">${item.WEIGHT} กรัม</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">วันที่รับเข้า:</span>
                        <span class="detail-value">${item.RECEIVE_DATE}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">จำนวนวันในคลัง:</span>
                        <span class="detail-value">${item.DAYS_IN_STOCK} วัน</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">สถานะ:</span>
                        <span class="detail-value" style="color:#27ae60;font-weight:600">${item.STATUS}</span>
                    </div>
                    ${historyHtml}
                `;
            } else {
                document.getElementById('viewDetails').innerHTML = '<div style="color:#e74c3c;text-align:center">ไม่พบข้อมูล</div>';
            }
        })
        .catch(error => {
            document.getElementById('viewDetails').innerHTML = '<div style="color:#e74c3c;text-align:center">เกิดข้อผิดพลาด</div>';
        });
}

function closeViewModal() {
    document.getElementById('viewModal').classList.remove('active');
}

function deleteInventory(code) {
    currentDeleteCode = code;
    document.getElementById('deleteItemName').textContent = code;
    document.getElementById('deleteModal').classList.add('active');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
    currentDeleteCode = '';
}

function confirmDelete() {
    if (!currentDeleteCode) return;
    
    fetch('delete_inventory.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'code=' + encodeURIComponent(currentDeleteCode)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ ลบข้อมูลสำเร็จ');
            location.reload();
        } else {
            alert('✗ เกิดข้อผิดพลาด: ' + data.error);
        }
        closeDeleteModal();
    })
    .catch(error => {
        alert('✗ ไม่สามารถเชื่อมต่อได้');
        closeDeleteModal();
    });
}

function toggleWithdrawnTable() {
    const container = document.getElementById('withdrawnTableContainer');
    const icon = document.getElementById('toggleIcon');
    const text = document.getElementById('toggleText');
    
    if (container.style.display === 'none') {
        container.style.display = 'block';
        icon.textContent = '🙈';
        text.textContent = 'ซ่อนรายการ';
    } else {
        container.style.display = 'none';
        icon.textContent = '👁️';
        text.textContent = 'แสดงรายการ';
    }
}

function viewWithdrawal(withdrawalId) {
    document.getElementById('viewWithdrawalModal').classList.add('active');
    document.getElementById('viewWithdrawalDetails').innerHTML = '<div style="text-align:center;padding:20px">⏳ กำลังโหลด...</div>';
    
    fetch('view_withdrawal_detail.php?id=' + withdrawalId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const w = data.withdrawal;
                document.getElementById('viewWithdrawalDetails').innerHTML = `
                    <div class="detail-row">
                        <span class="detail-label">รหัสการเบิก:</span>
                        <span class="detail-value">WD-${String(w.WITHDRAWAL_ID).padStart(6, '0')}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">รหัสคลัง:</span>
                        <span class="detail-value">${w.INVENTORY_CODE}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">ชื่อวัตถุดิบ:</span>
                        <span class="detail-value">${w.PRODUCT_NAME}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">หมวดหมู่:</span>
                        <span class="detail-value">${w.CATEGORY}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">น้ำหนักที่เบิก:</span>
                        <span class="detail-value" style="color:#e74c3c;font-weight:600">${w.AMOUNT} กรัม</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">วันที่เบิก:</span>
                        <span class="detail-value">${w.WITHDRAW_DATE}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">เหตุผล:</span>
                        <span class="detail-value">${w.REASON || '-'}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">เบิกไปแล้ว:</span>
                        <span class="detail-value">${w.DAYS_AGO} วัน</span>
                    </div>
                `;
            } else {
                document.getElementById('viewWithdrawalDetails').innerHTML = '<div style="color:#e74c3c;text-align:center">ไม่พบข้อมูล</div>';
            }
        })
        .catch(error => {
            document.getElementById('viewWithdrawalDetails').innerHTML = '<div style="color:#e74c3c;text-align:center">เกิดข้อผิดพลาด</div>';
        });
}

function closeViewWithdrawalModal() {
    document.getElementById('viewWithdrawalModal').classList.remove('active');
}

function deleteWithdrawal(withdrawalId, productName) {
    currentDeleteWithdrawalId = withdrawalId;
    document.getElementById('deleteWithdrawalName').textContent = productName;
    document.getElementById('deleteWithdrawalModal').classList.add('active');
}

function closeDeleteWithdrawalModal() {
    document.getElementById('deleteWithdrawalModal').classList.remove('active');
    currentDeleteWithdrawalId = null;
}

function confirmDeleteWithdrawal() {
    if (!currentDeleteWithdrawalId) return;
    
    fetch('delete_withdrawal.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'withdrawalId=' + currentDeleteWithdrawalId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ ลบรายการเบิกสำเร็จ');
            location.reload();
        } else {
            alert('✗ เกิดข้อผิดพลาด: ' + data.error);
        }
        closeDeleteWithdrawalModal();
    })
    .catch(error => {
        alert('✗ ไม่สามารถเชื่อมต่อได้');
        closeDeleteWithdrawalModal();
    });
}

document.addEventListener('keydown', function(e) {
    if(e.key === 'Escape') {
        document.getElementById('sidebar').classList.remove('active');
        document.getElementById('overlay').classList.remove('active');
        closeViewModal();
        closeDeleteModal();
        closeViewWithdrawalModal();
        closeDeleteWithdrawalModal();
    }
});
</script>
<script src="auth_guard.js"></script>

</body>
</html>

<?php
oci_close($conn);
?>