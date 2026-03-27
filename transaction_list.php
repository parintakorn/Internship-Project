<?php
include 'connect.php';

// 🔥 แก้ไข: เรียงตามวันที่ล่าสุดก่อน + วันที่ไม่มีเวลา
$sql = "SELECT t.ORDERID, t.CUSTOMERID, 
               TO_CHAR(t.ORDERDATE, 'DD-MON-YYYY') as ORDERDATE_DISPLAY,
               t.ORDERDATE,
               t.TOTALPRICE,
               t.ORDERTIME, t.DISCOUNTMEMBER, t.MENUTYPEID, t.COURSEID,
               mt.TYPENAME, mc.COURSENAME
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
    <title>Transaction List</title>
    <style>
        body {
            font-family: Arial;
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
            margin-top: 0;
        }
        .sidebar-header p {
            font-size: 12px;
            color: rgba(255,255,255,0.7);
            margin: 0;
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
        #sidebar a::before {
            content: '▸';
            margin-right: 12px;
            font-size: 14px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        #sidebar a:hover::before {
            opacity: 1;
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
            margin-top: 120px;
            margin-left: 30px;
            padding-bottom: 50px;
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
        .btn {
            padding: 6px 12px;
            border-radius: 4px;
            border: 1px solid #333;
            background: white;
            cursor: pointer;
            margin: 2px;
            font-size: 13px;
        }
        .btn-view {
            background: #3498db;
            color: white;
            border: none;
        }
        .btn-print {
            background: #27ae60;
            color: white;
            border: none;
        }
        .btn-delete {
            background: #e74c3c;
            color: white;
            border: none;
        }
        .btn-delete:hover { background: darkred; }
        .btn-view:hover { background: #2980b9; }
        .btn-print:hover { background: #229954; }
        .price {
            font-weight: bold;
            color: #27ae60;
        }
        .type-badge {
            display: inline-block;
            padding: 4px 8px;
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
        .course-info {
            font-size: 13px;
            color: #555;
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

<!-- Top bar -->
<div class="top-bar">
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
    <button class="back-btn" onclick="window.location.href='homepage.php'">←</button>
    <h2>Transaction List</h2>
</div>

<div id="overlay" class="overlay" onclick="toggleMenu()"></div>

<!-- Sidebar -->
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
    <a href="course_menu_manage.php">🍱 Course</a>
    <a href="profit_list.php">📊 Profit</a>
    <a href="member_list.php">👥 Members</a>
</div>

<div class="container">
    <h3>📊 รายการธุรกรรมทั้งหมด (เรียงตามวันที่ล่าสุด)</h3>

    <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 'success'): ?>
    <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
        ✅ ลบธุรกรรมเรียบร้อยแล้ว
    </div>
    <?php endif; ?>

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
                <th>Discount</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $count = 0;
        $today = date('Y-m-d');
        while($r = oci_fetch_assoc($stid)):
            $count++;

            // กำหนดสี badge ตาม menu type
            $typeClass = '';
            if ($r['MENUTYPEID'] == 1) $typeClass = 'type-alacarte';
            elseif ($r['MENUTYPEID'] == 2) $typeClass = 'type-omakase';
            elseif ($r['MENUTYPEID'] == 3) $typeClass = 'type-buffet';

            // 🆕 เช็คว่าเป็นวันนี้หรือไม่ (ใช้วันที่จาก Oracle - ไม่มีเวลา)
            $orderDateStr = $r['ORDERDATE_DISPLAY']; // เช่น "14-JAN-2026"
            $orderDate = date('Y-m-d', strtotime($orderDateStr));
            $isToday = ($orderDate === $today);
            $rowClass = $isToday ? 'date-today' : '';
        ?>
            <tr class="<?= $rowClass ?>">
                <td><strong><?= htmlspecialchars($r['ORDERID']) ?></strong></td>
                <td><?= $r['CUSTOMERID'] ? htmlspecialchars($r['CUSTOMERID']) : '<em>Guest</em>' ?></td>
                <td class="date-cell">
                    <?= $r['ORDERDATE_DISPLAY'] ? date('d M Y', strtotime($r['ORDERDATE_DISPLAY'])) : '-' ?>
                    <?php if ($isToday): ?>
                        <br><small style="color:#27ae60;font-weight:bold;">📍 วันนี้</small>
                    <?php endif; ?>
                </td>
                <td><?= $r['ORDERTIME'] ? htmlspecialchars($r['ORDERTIME']) : '-' ?></td>
                <td>
                    <?php if ($r['TYPENAME']): ?>
                        <span class="type-badge <?= $typeClass ?>">
                            <?= htmlspecialchars($r['TYPENAME']) ?>
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
                <td><?= $r['DISCOUNTMEMBER'] ? number_format($r['DISCOUNTMEMBER'], 2) . ' ฿' : '-' ?></td>
                <td>
                    <a href="transaction_view.php?id=<?= htmlspecialchars($r['ORDERID']) ?>">
                        <button class="btn btn-view">View</button>
                    </a>
                    <button class="btn btn-print" onclick="printReceipt('<?= htmlspecialchars($r['ORDERID']) ?>')">🖨️ Print</button>
                    <a href="transaction_delete.php?id=<?= htmlspecialchars($r['ORDERID']) ?>"
                       onclick="return confirm('ลบธุรกรรมนี้? (จะลบออเดอร์ด้วย)')">
                        <button class="btn btn-delete">Delete</button>
                    </a>
                </td>
            </tr>
        <?php endwhile; ?>

        <?php if ($count == 0): ?>
            <tr>
                <td colspan="9" style="padding: 30px; color: #999;">
                    <em>ไม่มีข้อมูลธุรกรรม</em>
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <p style="margin-top: 20px; color: #666;">
        <strong>Total Transactions:</strong> <?= $count ?> รายการ (เรียงจากวันที่ล่าสุด)
    </p>
</div>

<script>
function toggleMenu() {
    let bar = document.getElementById("sidebar");
    let overlay = document.getElementById("overlay");
    if (bar.classList.contains("active")) {
        bar.classList.remove("active");
        overlay.classList.remove("active");
    } else {
        bar.classList.add("active");
        overlay.classList.add("active");
    }
}

function printReceipt(orderId) {
    if (!orderId) {
        alert('ไม่พบ Order ID');
        return;
    }
    console.log('Opening print page for Order ID:', orderId);
    window.open('transaction_print.php?id=' + orderId, '_blank');
}
</script>
<script src="auth_guard.js"></script>
</body>
</html>