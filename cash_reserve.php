<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
include 'connect.php';
date_default_timezone_set('Asia/Bangkok');

// ---------------------- ลบรายการ ----------------------
if (isset($_POST['delete_transaction'])) {
    $reserve_id = $_POST['reserve_id'];
    
    // หารูปภาพก่อนลบ (เพื่อลบไฟล์จริงด้วย)
    $sql_get_img = "SELECT TRANSACTION_IMAGE FROM CASH_RESERVE WHERE RESERVE_ID = :id";
    $stid_get = oci_parse($conn, $sql_get_img);
    oci_bind_by_name($stid_get, ":id", $reserve_id);
    oci_execute($stid_get);
    $row_img = oci_fetch_assoc($stid_get);
    $image_path = $row_img['TRANSACTION_IMAGE'] ?? '';

    // ลบ record ในฐานข้อมูล
    $sql_delete = "DELETE FROM CASH_RESERVE WHERE RESERVE_ID = :id";
    $stid_delete = oci_parse($conn, $sql_delete);
    oci_bind_by_name($stid_delete, ":id", $reserve_id);
    $success = oci_execute($stid_delete);
    oci_commit($conn);

    // ลบไฟล์รูปภาพถ้ามี
    if ($success && !empty($image_path) && file_exists($image_path)) {
        unlink($image_path);
    }

    header("Location: cash_reserve.php?msg=transaction_deleted");
    exit();
}

// ---------------------- RECORD CASH TRANSACTION (IN/OUT) ----------------------
if (isset($_POST['record_transaction'])) {
    $transactionType = $_POST['transaction_type'];
    $amount = floatval($_POST['transaction_amount']);
    $transactionNote = $_POST['transaction_note'];
    $transactionImage = '';
    
    if (isset($_FILES['transaction_image']) && $_FILES['transaction_image']['error'] == 0) {
        $uploadDir = 'cash_reserve_images/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileExtension = strtolower(pathinfo($_FILES['transaction_image']['name'], PATHINFO_EXTENSION));
        $newFileName = 'CASH_' . time() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $newFileName;
        
        if (move_uploaded_file($_FILES['transaction_image']['tmp_name'], $uploadPath)) {
            $transactionImage = $uploadPath;
        }
    }
    
    $sql = "INSERT INTO CASH_RESERVE (RESERVE_ID, TRANSACTION_TYPE, AMOUNT, TRANSACTION_NOTE, TRANSACTION_IMAGE, TRANSACTION_DATE)
            VALUES (RESERVE_SEQ.NEXTVAL, :type, :amt, :note, :img, SYSDATE)";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":type", $transactionType);
    oci_bind_by_name($stid, ":amt", $amount);
    oci_bind_by_name($stid, ":note", $transactionNote);
    oci_bind_by_name($stid, ":img", $transactionImage);
    oci_execute($stid);
    oci_commit($conn);
    
    header("Location: cash_reserve.php?msg=transaction_recorded");
    exit();
}

// ---------------------- คำนวณยอดเงินคงเหลือ ----------------------
$sql_balance = "SELECT 
                    NVL(SUM(CASE WHEN TRANSACTION_TYPE = 'IN' THEN AMOUNT ELSE 0 END), 0) as TOTAL_IN,
                    NVL(SUM(CASE WHEN TRANSACTION_TYPE = 'OUT' THEN AMOUNT ELSE 0 END), 0) as TOTAL_OUT
                FROM CASH_RESERVE";
$stid_balance = oci_parse($conn, $sql_balance);
oci_execute($stid_balance);
$balance_row = oci_fetch_assoc($stid_balance);
$total_in = $balance_row['TOTAL_IN'];
$total_out = $balance_row['TOTAL_OUT'];
$current_balance = $total_in - $total_out;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เงินสำรองหน้าร้าน</title>
<style>
body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #fafafa; }
.top-bar { width: 100%; background-color: rgba(255,255,255,0.95); padding: 15px 20px; display: flex; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.15); position: fixed; top: 0; left: 0; z-index: 20; backdrop-filter: blur(10px); }
.menu-btn, .back-btn { font-size: 24px; margin-right: 15px; cursor: pointer; padding: 8px 12px; border-radius: 8px; border: none; background: #667eea; color: white; transition: all 0.3s; }
.menu-btn:hover, .back-btn:hover { background: #5568d3; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
#sidebar { width: 280px; height: 100vh; position: fixed; top: 0; left: -280px; background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%); color: white; transition: left 0.3s ease; padding-top: 60px; z-index: 1000; box-shadow: 4px 0 15px rgba(0,0,0,0.3); overflow-y: auto; }
#sidebar.active { left: 0; }
#sidebar a { display: flex; align-items: center; padding: 15px 25px; text-decoration: none; color: rgba(255,255,255,0.9); font-size: 16px; transition: all 0.3s; border-left: 3px solid transparent; }
#sidebar a:hover { background: rgba(255,255,255,0.1); border-left-color: #667eea; padding-left: 30px; }
.overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; }
.overlay.active { display: block; }
.container { margin-top: 90px; margin-left: 30px; margin-right: 30px; padding-bottom: 50px; }

/* Balance Summary */
.balance-summary { 
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
    color: white; 
    padding: 30px; 
    border-radius: 12px; 
    margin-bottom: 30px; 
    box-shadow: 0 8px 20px rgba(0,0,0,0.2);
    text-align: center;
}
.balance-amount {
    font-size: 48px;
    font-weight: bold;
    margin: 15px 0;
}
.balance-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-top: 20px;
}
.balance-card {
    background: rgba(255,255,255,0.2);
    padding: 15px;
    border-radius: 8px;
}
.balance-card h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
    opacity: 0.9;
}
.balance-card .amount {
    font-size: 28px;
    font-weight: bold;
}

/* Transaction Section */
.transaction-section {
    background: #e8f5e9;
    border: 2px solid #4caf50;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 30px;
}
.transaction-section h3 {
    margin-top: 0;
    color: #2e7d32;
    display: flex;
    align-items: center;
    gap: 10px;
}
.transaction-type-selector {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}
.type-btn {
    flex: 1;
    padding: 15px;
    border: 3px solid #ddd;
    background: white;
    border-radius: 10px;
    cursor: pointer;
    font-weight: bold;
    font-size: 16px;
    transition: all 0.3s;
}
.type-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
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
    box-shadow: 0 6px 15px rgba(76,175,80,0.4);
}
.type-btn.active.out {
    background: #ff9800;
    color: white;
    box-shadow: 0 6px 15px rgba(255,152,0,0.4);
}

.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
    color: #2c3e50;
}
.form-group input, .form-group textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
}
.form-group input:focus, .form-group textarea:focus {
    outline: none;
    border-color: #4caf50;
}
.form-group textarea {
    min-height: 80px;
    resize: vertical;
}

.btn-submit {
    background: #27ae60;
    color: white;
    border: none;
    padding: 15px 30px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
    transition: all 0.3s;
    width: 100%;
}
.btn-submit:hover {
    background: #229954;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(39,174,96,0.3);
}

/* Transaction History */
.transaction-history {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.transaction-history h3 {
    margin-top: 0;
    color: #2c3e50;
    border-bottom: 3px solid #3498db;
    padding-bottom: 12px;
}
.transaction-item {
    padding: 15px;
    background: #f5f5f5;
    margin-bottom: 12px;
    border-radius: 8px;
    border-left: 5px solid #ddd;
    transition: all 0.2s;
}
.transaction-item:hover {
    background: #e8e8e8;
    transform: translateX(5px);
}
.transaction-item.in {
    border-left-color: #4caf50;
    background: #f1f8f4;
}
.transaction-item.out {
    border-left-color: #ff9800;
    background: #fff8f0;
}
.transaction-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.transaction-amount {
    font-size: 24px;
    font-weight: bold;
}
.transaction-amount.in {
    color: #4caf50;
}
.transaction-amount.out {
    color: #ff9800;
}
.transaction-date {
    font-size: 12px;
    color: #7f8c8d;
}
.transaction-type-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 12px;
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
    font-size: 14px;
    margin: 8px 0;
}
.transaction-img-thumb {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid #ddd;
    cursor: pointer;
    margin-top: 10px;
    transition: transform 0.2s;
}
.transaction-img-thumb:hover {
    transform: scale(1.1);
}
.btn-delete {
    background: #e74c3c;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: bold;
    transition: background 0.2s;
}
.btn-delete:hover {
    background: #c0392b;
}

.success-msg {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-weight: bold;
}

.image-preview {
    max-width: 200px;
    max-height: 200px;
    margin-top: 10px;
    border: 2px solid #ddd;
    border-radius: 8px;
    display: none;
}
.image-preview.show {
    display: block;
}
</style>
</head>
<body>

<div class="top-bar">
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
    <button class="back-btn" onclick="window.location.href='profit_list.php'">←</button>
    <h2>💵 เงินสำรองหน้าร้าน</h2>
</div>

<div id="overlay" class="overlay" onclick="toggleMenu()"></div>

<div id="sidebar">
    <div style="padding:20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)">
        <h3 style="margin:0;margin-bottom:5px">🍱 Menu</h3>
        <p style="font-size:12px;color:rgba(255,255,255,0.7);margin:0">Restaurant Management</p>
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
    
    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'transaction_recorded'): ?>
        <div class="success-msg">✅ บันทึกรายการเรียบร้อยแล้ว!</div>
    <?php endif; ?>

    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'transaction_deleted'): ?>
        <div class="success-msg">🗑️ ลบรายการเรียบร้อยแล้ว!</div>
    <?php endif; ?>

    <!-- Balance Summary -->
    <div class="balance-summary">
        <h2 style="margin:0;">💰 ยอดเงินคงเหลือ</h2>
        <div class="balance-amount">
            <?= number_format($current_balance, 2) ?> ฿
        </div>
        <div class="balance-details">
            <div class="balance-card">
                <h4>📥 รับเข้าทั้งหมด</h4>
                <div class="amount">+<?= number_format($total_in, 2) ?> ฿</div>
            </div>
            <div class="balance-card">
                <h4>📤 จ่ายออกทั้งหมด</h4>
                <div class="amount">-<?= number_format($total_out, 2) ?> ฿</div>
            </div>
        </div>
    </div>

    <!-- Transaction Form -->
    <div class="transaction-section">
        <h3>💸 บันทึกรายการเงิน</h3>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="transaction_type" id="transaction_type" value="IN">
            
            <div class="transaction-type-selector">
                <button type="button" class="type-btn in active" onclick="selectTransactionType('IN')">
                    📥 รับเข้า (IN)
                </button>
                <button type="button" class="type-btn out" onclick="selectTransactionType('OUT')">
                    📤 จ่ายออก (OUT)
                </button>
            </div>
            
            <div class="form-group">
                <label>💰 จำนวนเงิน (บาท)</label>
                <input type="number" step="0.01" name="transaction_amount" required placeholder="0.00">
            </div>
            
            <div class="form-group">
                <label>📝 หมายเหตุ</label>
                <textarea name="transaction_note" placeholder="เช่น เติมเงินทอนประจำวัน, ซื้อของใช้หน้าร้าน"></textarea>
            </div>
            
            <div class="form-group">
                <label>📷 รูปภาพประกอบ (ถ้ามี)</label>
                <input type="file" name="transaction_image" accept="image/*" onchange="previewImage(event, 'preview_transaction')">
                <img id="preview_transaction" class="image-preview">
            </div>
            
            <button type="submit" name="record_transaction" class="btn-submit">💾 บันทึกรายการ</button>
        </form>
    </div>

    <!-- Transaction History -->
    <div class="transaction-history">
        <h3>📋 ประวัติรายการทั้งหมด</h3>
        <?php
        $sql = "SELECT * FROM CASH_RESERVE ORDER BY TRANSACTION_DATE DESC";
        $stid = oci_parse($conn, $sql);
        oci_execute($stid);
        
        $has_transactions = false;
        while ($row = oci_fetch_assoc($stid)) {
            $has_transactions = true;
            $trans_class = strtolower($row['TRANSACTION_TYPE']);
            $trans_date = $row['TRANSACTION_DATE'] ? date('d/m/Y H:i', strtotime($row['TRANSACTION_DATE'])) : '-';
            ?>
            <div class="transaction-item <?= $trans_class ?>">
                <div class="transaction-item-header">
                    <div>
                        <span class="transaction-amount <?= $trans_class ?>">
                            <?= $row['TRANSACTION_TYPE'] == 'IN' ? '+' : '-' ?>
                            <?= number_format($row['AMOUNT'], 2) ?> ฿
                        </span>
                        <span class="transaction-type-badge <?= $trans_class ?>">
                            <?= $row['TRANSACTION_TYPE'] == 'IN' ? '📥 รับเข้า' : '📤 จ่ายออก' ?>
                        </span>
                    </div>
                    <span class="transaction-date">🕐 <?= $trans_date ?></span>
                </div>
                
                <?php if (!empty($row['TRANSACTION_NOTE'])): ?>
                    <div class="transaction-note">📝 <?= htmlspecialchars($row['TRANSACTION_NOTE']) ?></div>
                <?php endif; ?>
                
                <?php if (!empty($row['TRANSACTION_IMAGE']) && file_exists($row['TRANSACTION_IMAGE'])): ?>
                    <img src="<?= $row['TRANSACTION_IMAGE'] ?>" class="transaction-img-thumb"
                         onclick="window.open('<?= $row['TRANSACTION_IMAGE'] ?>', '_blank')">
                <?php endif; ?>

                <!-- ปุ่มลบอยู่ใต้บรรทัดวันที่ ชิดขวา -->
                <div style="margin-top: 8px; text-align: right;">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="reserve_id" value="<?= $row['RESERVE_ID'] ?>">
                        <button type="submit" name="delete_transaction" class="btn-delete"
                                onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบรายการนี้? การลบจะไม่สามารถกู้คืนได้');">
                            🗑️ ลบ
                        </button>
                    </form>
                </div>
            </div>
            <?php
        }
        
        if (!$has_transactions) {
            echo '<p style="text-align:center; color:#999; padding:40px;">ยังไม่มีรายการ</p>';
        }
        ?>
    </div>

</div>

<script>
function toggleMenu() {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('overlay').classList.toggle('active');
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

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('sidebar').classList.remove('active');
        document.getElementById('overlay').classList.remove('active');
    }
});
</script>

</body>
</html>