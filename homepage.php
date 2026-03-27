<?php
session_start();
$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            width: 100%;
        }

        /* Auth Status Bar */
        .auth-status {
            background: white;
            border-radius: 15px;
            padding: 15px 25px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            animation: fadeInDown 0.8s ease;
        }

        .status-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-icon {
            font-size: 24px;
        }

        .status-text {
            font-weight: 600;
            font-size: 16px;
        }

        .status-locked {
            color: #e74c3c;
        }

        .status-unlocked {
            color: #27ae60;
        }

        .auth-btn {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-unlock {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-unlock:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-logout {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);
        }

        .header {
            text-align: center;
            margin-bottom: 50px;
            animation: fadeInDown 0.8s ease;
        }

        .header h1 {
            color: white;
            font-size: 48px;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            margin-bottom: 10px;
        }

        .header p {
            color: rgba(255,255,255,0.9);
            font-size: 18px;
            font-weight: 300;
        }

        /* Header Logo - ขยาย 5 เท่า */
        .header .logo-container {
            margin-bottom: 20px;
        }

        .header .logo-container img {
            width: 320px;
            height: 320px;
            object-fit: contain;
            filter: drop-shadow(0 10px 20px rgba(0,0,0,0.3));
            animation: logoFloat 3s ease-in-out infinite;
        }

        @keyframes logoFloat {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            animation: fadeInUp 0.8s ease;
        }

        .menu-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            position: relative;
            overflow: hidden;
        }

        .menu-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }

        .menu-card:hover::before {
            left: 100%;
        }

        .menu-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.3);
        }

        .menu-icon {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }

        .menu-icon img {
            width: 64px;
            height: 64px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }

        .menu-card.warehouse { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .menu-card.report { background: linear-gradient(135deg, #a8c0ff 0%, #3f2b96 100%); color: white; }
        .menu-card.ingredient { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; }
        .menu-card.menu { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
        .menu-card.recipe { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; }
        .menu-card.member { background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%); color: white; }
        .menu-card.course { background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); color: white; }
        .menu-card.order { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; }
        .menu-card.transaction { background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); color: #333; }
        .menu-card.profit { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); color: #333; }

        .menu-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .menu-desc {
            font-size: 14px;
            opacity: 0.9;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 32px;
            }

            .header .logo-container img {
                width: 200px;
                height: 200px;
            }
            
            .menu-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 15px;
            }

            .auth-status {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .header .logo-container img {
                width: 150px;
                height: 150px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Auth Status Bar -->
        <div class="auth-status">
            <div class="status-info">
                <span class="status-icon"><?php echo $isLoggedIn ? '🔓' : '🔒'; ?></span>
                <span class="status-text <?php echo $isLoggedIn ? 'status-unlocked' : 'status-locked'; ?>">
                    <?php 
                    if ($isLoggedIn) {
                        echo 'ระบบปลดล็อคแล้ว - สามารถแก้ไขข้อมูลได้';
                    } else {
                        echo 'ระบบถูกล็อก - ดูข้อมูลได้อย่างเดียว';
                    }
                    ?>
                </span>
            </div>
            <?php if ($isLoggedIn): ?>
                <a href="logout.php" class="auth-btn btn-logout" 
                   onclick="return confirm('ต้องการล็อกระบบกลับ?')">
                    🔒 ล็อกระบบ
                </a>
            <?php else: ?>
                <a href="login.php" class="auth-btn btn-unlock">🔑 ปลดล็อคระบบ</a>
            <?php endif; ?>
        </div>

        <div class="header">
            <div class="logo-container">
                <img src="img/1212.jpg" alt="Restaurant Logo">
            </div>
            <p>ระบบจัดการร้านอาหาร</p>
        </div>

        <div class="menu-grid">
            <a href="ingredient_warehouse.php" class="menu-card warehouse">
                <span class="menu-icon">
                    <img src="img/4671.jpg" alt="คลังวัตถุดิบ">
                </span>
                <div class="menu-title">คลังวัตถุดิบ</div>
                <div class="menu-desc">จัดการสต๊อกและสร้าง QR</div>
            </a>

            <a href="warehouse_report.php" class="menu-card report">
                <span class="menu-icon">
                    <img src="img/4671.jpg" alt="รายงานคลัง">
                </span>
                <div class="menu-title">รายงานคลังวัตถุดิบ</div>
                <div class="menu-desc">สรุปรายงานและสถิติ</div>
            </a>

            <a href="ingredient.php" class="menu-card ingredient">
                <span class="menu-icon">🥬</span>
                <div class="menu-title">วัตถุดิบ</div>
                <div class="menu-desc">จัดการข้อมูลวัตถุดิบ</div>
            </a>

            <a href="menu_list.php" class="menu-card menu">
                <span class="menu-icon">🍱</span>
                <div class="menu-title">เมนูอาหาร</div>
                <div class="menu-desc">จัดการรายการเมนู</div>
            </a>

            <a href="recipe_list.php" class="menu-card recipe">
                <span class="menu-icon">📖</span>
                <div class="menu-title">สูตรอาหาร</div>
                <div class="menu-desc">สูตรและส่วนผสม</div>
            </a>

            <a href="member_list.php" class="menu-card member">
                <span class="menu-icon">👥</span>
                <div class="menu-title">สมาชิก</div>
                <div class="menu-desc">จัดการข้อมูลสมาชิก</div>
            </a>

            <a href="course_menu_manage.php" class="menu-card course">
                <span class="menu-icon">🍽️</span>
                <div class="menu-title">คอร์ส</div>
                <div class="menu-desc">เมนูภายในคอร์ส</div>
            </a>

            <a href="order_list.php" class="menu-card order">
                <span class="menu-icon">📋</span>
                <div class="menu-title">ออเดอร์</div>
                <div class="menu-desc">จัดการออเดอร์</div>
            </a>

            <a href="transaction_list.php" class="menu-card transaction">
                <span class="menu-icon">💳</span>
                <div class="menu-title">ธุรกรรม</div>
                <div class="menu-desc">ประวัติการทำรายการ</div>
            </a>

            <a href="profit_list.php" class="menu-card profit">
                <span class="menu-icon">💰</span>
                <div class="menu-title">กำไร</div>
                <div class="menu-desc">สรุปกำไร-ขาดทุน</div>
            </a>

            <a href="export/export_ingredients.php" class="menu-card report">
    <span class="menu-icon">📤</span>
    <div class="menu-title">Export วัตถุดิบ</div>
    <div class="menu-desc">สำรองข้อมูลวัตถุดิบ (CSV / Excel)</div>
</a>

<a href="export/export_recipes.php" class="menu-card report">
    <span class="menu-icon">📤</span>
    <div class="menu-title">Export สูตรอาหาร</div>
    <div class="menu-desc">สำรองข้อมูลสูตรอาหาร</div>
</a>

<a href="export/export_orders.php" class="menu-card report">
    <span class="menu-icon">📤</span>
    <div class="menu-title">Export ออเดอร์</div>
    <div class="menu-desc">สำรองข้อมูลออเดอร์</div>
</a>

<a href="export/export_order_items.php" class="menu-card report">
    <span class="menu-icon">📤</span>
    <div class="menu-title">Export รายการในออเดอร์</div>
    <div class="menu-desc">เมนูที่ถูกสั่งในแต่ละออเดอร์</div>
</a>


<a href="export/export_transactions.php" class="menu-card report">
    <span class="menu-icon">📤</span>
    <div class="menu-title">Export ธุรกรรม</div>
    <div class="menu-desc">ประวัติการเงินทั้งหมด</div>
</a>

<a href="export/export_courses.php" class="menu-card report">
    <span class="menu-icon">📤</span>
    <div class="menu-title">Export คอร์ส</div>
    <div class="menu-desc">ข้อมูลคอร์สอาหาร</div>
</a>

<a href="export/export_profit.php" class="menu-card report">
    <span class="menu-icon">📤</span>
    <div class="menu-title">Export กำไร</div>
    <div class="menu-desc">สรุปกำไร–ขาดทุน</div>
</a>


        </div>
    </div>
    
    <script src="auth_guard.js"></script>
</body>
</html>