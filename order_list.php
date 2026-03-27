<?php
include 'connect.php';

// ตรวจสอบว่าคอลัมน์ PAYMENT_METHOD มีอยู่หรือไม่ ถ้าไม่มีให้เพิ่ม
$checkCol = "SELECT COUNT(*) as COL_COUNT 
             FROM USER_TAB_COLUMNS 
             WHERE TABLE_NAME = 'TRANSACTION' 
             AND COLUMN_NAME = 'PAYMENT_METHOD'";
$checkStid = oci_parse($conn, $checkCol);
oci_execute($checkStid);
$colCheck = oci_fetch_assoc($checkStid);

if ($colCheck['COL_COUNT'] == 0) {
    // เพิ่มคอลัมน์ PAYMENT_METHOD
    $alterSql = "ALTER TABLE TRANSACTION ADD PAYMENT_METHOD VARCHAR2(20)";
    $alterStid = oci_parse($conn, $alterSql);
    @oci_execute($alterStid);
}

// 🔥 แก้ไข: เรียงตามวันที่ล่าสุดก่อน + เพิ่ม ORDERTIME
// แก้เป็น
$sql = "SELECT t.ORDERID, 
               TO_CHAR(t.ORDERDATE, 'DD-MON-YYYY') as ORDERDATE_DISPLAY,
               t.ORDERDATE,
               t.ORDERTIME,
               t.TOTALPRICE, 
               t.CUSTOMERID, 
               t.PAYMENT_METHOD,
               t.SLIP_FILENAME,
               mt.TYPENAME, 
               mc.COURSENAME
        FROM TRANSACTION t
        LEFT JOIN MENU_TYPE mt ON t.MENUTYPEID = mt.MENUTYPEID
        LEFT JOIN MENU_COURSE mc ON t.COURSEID = mc.COURSEID
        ORDER BY t.ORDERDATE DESC, t.ORDERID DESC";
$stid = oci_parse($conn, $sql);
oci_execute($stid);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 0;
            background: #fafafa;
        }
        
        .top-bar {
            width: 100%;
            background-color: rgba(255, 255, 255, 0.95);
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
            display: flex;
            align-items: center;
            justify-content: center;
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

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h3 {
            font-size: 24px;
            margin-bottom: 5px;
            color: #fff;
        }

        .sidebar-header p {
            font-size: 12px;
            color: rgba(255,255,255,0.7);
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

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .message.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .message.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .page-header-left {
            display: flex;
            align-items: center;
        }

        .page-header h3 {
            margin: 0;
            color: #333;
        }

        .page-header .icon {
            font-size: 24px;
            margin-right: 10px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #229954;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        table {
            background: white;
            border-collapse: collapse;
            width: 95%;
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
            background: #3498db;
            color: white;
            font-weight: bold;
        }

        tr:hover {
            background: #f5f5f5;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .action-buttons button {
            padding: 6px 12px;
            font-size: 13px;
        }

        .price {
            font-weight: bold;
            color: #27ae60;
        }

        .type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
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

        .payment-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
        }

        .payment-cash {
            background: #27ae60;
            color: white;
        }

        .payment-transfer {
            background: #3498db;
            color: white;
        }

        .payment-none {
            background: #95a5a6;
            color: white;
        }

        .course-info {
            font-size: 13px;
            color: #555;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card h4 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }

        .stat-card.primary .value {
            color: #3498db;
        }

        .stat-card.success .value {
            color: #27ae60;
        }

        /* 🆕 เพิ่ม style สำหรับวันที่ */
        .date-cell {
            white-space: nowrap;
        }

        .date-today {
            background: #e8f5e9;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="top-bar">
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
    <button class="back-btn" onclick="window.location.href='homepage.php'">←</button>
    <h2>🍱 Order Management</h2>
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

<div class="container">
    
    <!-- Messages -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="message success">
            <?php 
            if ($_GET['msg'] == 'deleted') echo '✅ ลบออเดอร์เรียบร้อยแล้ว (คืนวัตถุดิบเรียบร้อย)';
            ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="message error">
            <?php 
            if ($_GET['error'] == 'no_id') echo '❌ ไม่พบ Order ID';
            elseif ($_GET['error'] == 'not_found') echo '❌ ไม่พบออเดอร์นี้';
            elseif ($_GET['error'] == 'delete_failed') {
                echo '❌ ลบออเดอร์ไม่สำเร็จ';
                if (isset($_GET['detail'])) echo '<br><small>' . htmlspecialchars($_GET['detail']) . '</small>';
            }
            ?>
        </div>
    <?php endif; ?>
    
    <!-- Statistics -->
    <?php
    $countSql = "SELECT COUNT(*) AS TOTAL_ORDERS, 
                        SUM(TOTALPRICE) AS TOTAL_REVENUE
                 FROM TRANSACTION";
    $countStid = oci_parse($conn, $countSql);
    oci_execute($countStid);
    $stats = oci_fetch_assoc($countStid);
    ?>
    
    <div class="stats-container">
        <div class="stat-card primary">
            <h4>Total Orders</h4>
            <div class="value"><?= number_format($stats['TOTAL_ORDERS']) ?></div>
        </div>
        <div class="stat-card success">
            <h4>Total Revenue</h4>
            <div class="value"><?= number_format($stats['TOTAL_REVENUE'], 2) ?> ฿</div>
        </div>
    </div>
    
    <!-- Header -->
    <div class="page-header">
        <div class="page-header-left">
            <span class="icon">🛒</span>
            <h3>Order List (เรียงตามวันที่ล่าสุด)</h3>
        </div>
        <a href="create_order.php" class="btn btn-success">+ Create New Order</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer ID</th>
                <th>📅 Order Date</th>
                <th>🕐 Order Time</th>
                <th>Menu Type</th>
                <th>Course</th>
                <th>Total Price</th>
                <th>วิธีชำระเงิน</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php 
        $count = 0;
        $today = date('Y-m-d');
        oci_execute($stid);
        while($r = oci_fetch_assoc($stid)): 
            $count++;
            
            $typeClass = '';
            $typeName = $r['TYPENAME'] ?? '-';
            if (stripos($typeName, 'carte') !== false) $typeClass = 'type-alacarte';
            elseif (stripos($typeName, 'omakase') !== false) $typeClass = 'type-omakase';
            
            $paymentMethod = $r['PAYMENT_METHOD'] ?? null;
            $paymentDisplay = '';
            $paymentClass = '';
            
            if ($paymentMethod === 'cash') {
                $paymentDisplay = '💵 เงินสด';
                $paymentClass = 'payment-cash';
            } elseif ($paymentMethod === 'transfer') {
                $paymentDisplay = '📱 โอนเงิน';
                $paymentClass = 'payment-transfer';
            } else {
                $paymentDisplay = '⚠️ ไม่ระบุ';
                $paymentClass = 'payment-none';
            }
            
            $orderDateStr = $r['ORDERDATE_DISPLAY'];
            $orderDate = date('Y-m-d', strtotime($orderDateStr));
            $isToday = ($orderDate === $today);
            $rowClass = $isToday ? 'date-today' : '';

            $hasSlip = ($paymentMethod === 'cash') || !empty($r['SLIP_FILENAME']);

            $slipColor = $hasSlip ? '#27ae60' : '#e74c3c';
            $slipBg   = $hasSlip ? '#eafaf1'  : '#fdf0f0';
            $slipIcon = $hasSlip ? '🟢' : '🔴';
        ?>
            <tr class="<?= $rowClass ?>">
                <td><strong><?= htmlspecialchars($r['ORDERID']) ?></strong></td>
                <td>
                    <span style="
                        display: inline-block;
                        padding: 4px 10px;
                        border-radius: 6px;
                        font-weight: bold;
                        color: <?= $slipColor ?>;
                        background: <?= $slipBg ?>;
                        border: 1px solid <?= $slipColor ?>;
                    ">
                        <?= $slipIcon ?> <?= $r['CUSTOMERID'] ? htmlspecialchars($r['CUSTOMERID']) : 'Guest' ?>
                    </span>
                </td>
                <td class="date-cell">
                    <?= $r['ORDERDATE_DISPLAY'] ? date('d M Y', strtotime($r['ORDERDATE_DISPLAY'])) : '-' ?>
                    <?php if ($isToday): ?>
                        <br><small style="color:#27ae60;font-weight:bold;">📍 วันนี้</small>
                    <?php endif; ?>
                </td>
                <td><?= $r['ORDERTIME'] ? htmlspecialchars($r['ORDERTIME']) : '-' ?></td>
                <td>
                    <?php if ($typeName != '-'): ?>
                        <span class="type-badge <?= $typeClass ?>">
                            <?= htmlspecialchars($typeName) ?>
                        </span>
                    <?php else: ?>
                        <em>-</em>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($r['COURSENAME']): ?>
                        <span class="course-info"><?= htmlspecialchars($r['COURSENAME']) ?></span>
                    <?php else: ?>
                        <em>-</em>
                    <?php endif; ?>
                </td>
                <td class="price"><?= number_format($r['TOTALPRICE'], 2) ?> ฿</td>
                <td>
                    <span class="payment-badge <?= $paymentClass ?>">
                        <?= $paymentDisplay ?>
                    </span>
                </td>
                <td>
                    <div class="action-buttons">
                        <a href="order_view.php?id=<?= $r['ORDERID'] ?>">
                            <button class="btn btn-primary">View</button>
                        </a>
                        <a href="order_delete.php?id=<?= $r['ORDERID'] ?>" 
                           onclick="return confirm('ลบออเดอร์นี้? (จะคืนวัตถุดิบให้อัตโนมัติ)')">
                            <button class="btn btn-danger">Delete</button>
                        </a>
                    </div>
                </td>
            </tr>
        <?php endwhile; ?>
        
        <?php if ($count == 0): ?>
            <tr>
                <td colspan="9" style="padding: 30px; color: #999;">
                    <em>No orders found. Create your first order above.</em>
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    
    <p style="margin-top: 20px; color: #666;">
        <strong>Total Orders:</strong> <?= $count ?> orders (เรียงจากวันที่ล่าสุด)
    </p>
</div>

<script>
function toggleMenu() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
}
</script>
<script src="auth_guard.js"></script>
</body>
</html>