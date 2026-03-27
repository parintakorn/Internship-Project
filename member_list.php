<?php
include 'connect.php';

$sql = "SELECT m.CUSTOMERID, m.CUSTOMERNAME, m.ADDRESS, m.TEL,
               m.LEVELID, l.LEVELNAME, l.DISCOUNT,
               m.LINEID
        FROM MEMBER m
        LEFT JOIN MEMBER_LEVEL l ON m.LEVELID = l.LEVELID
        ORDER BY m.CUSTOMERID DESC";

$stid = oci_parse($conn, $sql);

if (!$stid) {
    $e = oci_error($conn);
    echo "<div style='background: #f8d7da; padding: 20px; margin: 20px;'>Parse Error: " . htmlspecialchars($e['message']) . "</div>";
    exit;
}

$result = oci_execute($stid);

if (!$result) {
    $e = oci_error($stid);
    echo "<div style='background: #f8d7da; padding: 20px; margin: 20px;'>Execute Error: " . htmlspecialchars($e['message']) . "</div>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member List</title>
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

        .btn-group {
            margin-bottom: 20px;
        }

        .btn-add, .btn-level {
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }

        .btn-add {
            background: #27ae60;
        }

        .btn-add:hover {
            background: #229954;
        }

        .btn-level {
            background: #9b59b6;
        }

        .btn-level:hover {
            background: #8e44ad;
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
            background: #9b59b6;
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
            text-decoration: none;
            display: inline-block;
        }

        .btn-edit {
            background: #f39c12;
            color: white;
            border: none;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
            border: none;
        }

        .btn-delete:hover { background: darkred; }
        .btn-edit:hover { background: #d68910; }

        .level-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .level-silver {
            background: #95a5a6;
            color: white;
        }

        .level-gold {
            background: #f39c12;
            color: white;
        }

        .level-platinum {
            background: #34495e;
            color: white;
        }

        .discount-info {
            color: #27ae60;
            font-weight: bold;
        }

        .success-msg {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<!-- Top bar -->
<div class="top-bar">
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
    <button class="back-btn" onclick="window.location.href='homepage.php'">←</button>
    <h2>Member Management</h2>
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
    <a href="menutype_list.php">📋 Menu Type</a>
    <a href="menutypeprice_list.php">💰 Menu Type Price</a>
    <a href="recipe_list.php">📝 Recipe</a>
    <a href="order_list.php">🛒 Order</a>
    <a href="transaction_list.php">💳 Transaction</a>
    <a href="profit_list.php">📊 Profit</a>
    <a href="member_list.php">👥 Members</a>
</div>

<div class="container">
    <h3>👥 รายการสมาชิกทั้งหมด</h3>

    <?php if (isset($_GET['msg'])): ?>
        <div class="success-msg">
            <?php
            if ($_GET['msg'] == 'added') echo '✅ เพิ่มสมาชิกเรียบร้อยแล้ว';
            elseif ($_GET['msg'] == 'updated') echo '✅ แก้ไขข้อมูลสมาชิกเรียบร้อยแล้ว';
            elseif ($_GET['msg'] == 'deleted') echo '✅ ลบสมาชิกเรียบร้อยแล้ว';
            ?>
        </div>
    <?php endif; ?>

    <div class="btn-group">
        <a href="member_add.php" class="btn-add">+ เพิ่มสมาชิกใหม่</a>
        <a href="member_level_manage.php" class="btn-level">⚙️ จัดการระดับสมาชิก</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Customer ID</th>
                <th>ชื่อ-นามสกุล</th>
                <th>ที่อยู่</th>
                <th>เบอร์โทร</th>
                <th>Line ID</th>
                <th>Level</th>
                <th>ส่วนลด</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $count = 0;
        while($r = oci_fetch_assoc($stid)):
            $count++;

            // กำหนดสี badge ตาม level
            $levelClass = 'level-silver';
            if (isset($r['LEVELID'])) {
                if ($r['LEVELID'] == 2) $levelClass = 'level-gold';
                elseif ($r['LEVELID'] == 3) $levelClass = 'level-platinum';
            }
        ?>
            <tr>
                <td><strong><?= htmlspecialchars($r['CUSTOMERID']) ?></strong></td>
                <td><?= htmlspecialchars($r['CUSTOMERNAME']) ?></td>
                <td><?= htmlspecialchars($r['ADDRESS']) ?></td>
                <td><?= htmlspecialchars($r['TEL']) ?></td>
                <td><?= htmlspecialchars($r['LINEID']) ?></td>
                <td>
                    <?php if (isset($r['LEVELNAME']) && $r['LEVELNAME']): ?>
                        <span class="level-badge <?= $levelClass ?>">
                            <?= htmlspecialchars($r['LEVELNAME']) ?>
                        </span>
                    <?php else: ?>
                        <em>-</em>
                    <?php endif; ?>
                </td>
                <td class="discount-info">
                    <?= (isset($r['DISCOUNT']) && $r['DISCOUNT']) ? $r['DISCOUNT'] . '%' : '0%' ?>
                </td>
                <td>
                    <a href="member_edit.php?id=<?= $r['CUSTOMERID'] ?>" class="btn btn-edit">Edit</a>
                    <a href="member_delete.php?id=<?= $r['CUSTOMERID'] ?>"
                       onclick="return confirm('ต้องการลบสมาชิก <?= htmlspecialchars($r['CUSTOMERNAME']) ?> ใช่หรือไม่?')"
                       class="btn btn-delete">Delete</a>
                </td>
            </tr>
        <?php endwhile; ?>

        <?php if ($count == 0): ?>
            <tr>
                <td colspan="8" style="padding: 30px; color: #999;">
                    <em>ไม่มีข้อมูลสมาชิก</em>
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <p style="margin-top: 20px; color: #666;">
        <strong>Total Members:</strong> <?= $count ?> คน
    </p>
</div>

<script>
function toggleMenu() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
}

// ปิด sidebar เมื่อกด Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    }
});
</script>
<script src="auth_guard.js"></script>
</body>
</html>