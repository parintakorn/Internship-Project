<?php
include 'connect.php';

// สร้างเลข CUSTOMERID อัตโนมัติ
$sql_maxid = "SELECT NVL(MAX(CUSTOMERID), 100000) AS MAXID FROM MEMBER";
$stid_maxid = oci_parse($conn, $sql_maxid);
oci_execute($stid_maxid);
$row_maxid = oci_fetch_assoc($stid_maxid);
$newCustomerID = $row_maxid['MAXID'] + 1;

// ดึงข้อมูล Level ทั้งหมด
$sql_level = "SELECT * FROM MEMBER_LEVEL ORDER BY LEVELID";
$stid_level = oci_parse($conn, $sql_level);
oci_execute($stid_level);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customerid = $_POST['customerid'];
    $customername = $_POST['customername'];
    $address = $_POST['address'];
    $tel = $_POST['tel'];
    $levelid = $_POST['levelid'];
    $lineid = $_POST['lineid'];
    
    $sql = "INSERT INTO MEMBER (CUSTOMERID, CUSTOMERNAME, ADDRESS, TEL, LEVELID, LINEID) 
            VALUES (:customerid, :customername, :address, :tel, :levelid, :lineid)";
    
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':customerid', $customerid);
    oci_bind_by_name($stid, ':customername', $customername);
    oci_bind_by_name($stid, ':address', $address);
    oci_bind_by_name($stid, ':tel', $tel);
    oci_bind_by_name($stid, ':levelid', $levelid);
    oci_bind_by_name($stid, ':lineid', $lineid);
    
    if (oci_execute($stid, OCI_COMMIT_ON_SUCCESS)) {
        header("Location: member_list.php?msg=added");
        exit;
    } else {
        $error = oci_error($stid);
        $errorMsg = "เกิดข้อผิดพลาด: " . htmlspecialchars($error['message']);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Member</title>
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
            padding: 20px;
        }
        
        .top-bar {
            max-width: 600px;
            margin: 0 auto 20px;
            background-color: rgba(255, 255, 255, 0.95);
            padding: 15px 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-radius: 10px;
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
        
        .top-bar h2 {
            color: #2c3e50;
            font-size: 20px;
            font-weight: 600;
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
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        h2 {
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 28px;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #34495e;
            font-size: 14px;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        input[readonly] {
            background: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }
        
        .btn-submit {
            flex: 1;
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(39, 174, 96, 0.4);
        }
        
        .btn-cancel {
            flex: 1;
            background: #95a5a6;
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .btn-cancel:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }
        
        .error-msg {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .required {
            color: #e74c3c;
            margin-left: 3px;
        }
        
        .level-info {
            display: inline-block;
            background: #e8f5e9;
            color: #27ae60;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-left: 5px;
        }
    </style>
</head>
<body>

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
</div>

<div class="top-bar">
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
    <button class="back-btn" onclick="window.location.href='member_list.php'">←</button>
    <h2>เพิ่มสมาชิกใหม่</h2>
</div>

<div class="container">
    <?php if (isset($errorMsg)): ?>
        <div class="error-msg">⚠️ <?= $errorMsg ?></div>
    <?php endif; ?>
    
    <h2>👤 เพิ่มสมาชิก</h2>
    
    <form method="POST">
        <div class="form-group">
            <label>Customer ID <span class="required">*</span></label>
            <input type="number" name="customerid" value="<?= $newCustomerID ?>" readonly>
        </div>
        
        <div class="form-group">
            <label>ชื่อ-นามสกุล <span class="required">*</span></label>
            <input type="text" name="customername" required placeholder="กรุณากรอกชื่อ-นามสกุล">
        </div>
        
        <div class="form-group">
            <label>ที่อยู่ <span class="required">*</span></label>
            <textarea name="address" required placeholder="กรุณากรอกที่อยู่"></textarea>
        </div>
        
        <div class="form-group">
            <label>เบอร์โทรศัพท์ <span class="required">*</span></label>
            <input type="text" name="tel" required maxlength="10" placeholder="0851234567" pattern="[0-9]{10}">
        </div>
        
        <div class="form-group">
            <label>Member Level <span class="required">*</span></label>
            <select name="levelid" required>
                <option value="">-- เลือกระดับสมาชิก --</option>
                <?php 
                oci_execute($stid_level);
                while($level = oci_fetch_assoc($stid_level)): 
                ?>
                    <option value="<?= $level['LEVELID'] ?>">
                        <?= htmlspecialchars($level['LEVELNAME']) ?> 
                        <span class="level-info">(ส่วนลด <?= $level['DISCOUNT'] ?>%)</span>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>LINE ID</label>
            <input type="text" name="lineid" placeholder="ไม่บังคับ (ถ้ามี)">
        </div>
        
        <div class="btn-group">
            <button type="submit" class="btn-submit">✓ บันทึก</button>
            <a href="member_list.php" class="btn-cancel">✕ ยกเลิก</a>
        </div>
    </form>
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

</body>
</html>