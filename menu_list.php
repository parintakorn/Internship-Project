<?php
include 'connect.php';

/* ---------------- สร้าง MENUID อัตโนมัติ ---------------- */
$sqlMaxId = "SELECT NVL(MAX(MENUID), 20000) AS MAXID FROM MENU";
$stidMaxId = oci_parse($conn, $sqlMaxId);
oci_execute($stidMaxId);
$rowMaxId = oci_fetch_assoc($stidMaxId);
$newMenuID = $rowMaxId['MAXID'] + 1;

/* ---------------- ADD MENU ---------------- */
if (isset($_POST['add'])) {
    $imagePath = '';
    
    // จัดการอัพโหลดรูปภาพ
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $uploadDir = 'menu_images/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $newFileName = 'MENU_' . $_POST['menuid'] . '_' . time() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $newFileName;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
            $imagePath = $uploadPath;
        }
    }
    
    $sql = "INSERT INTO MENU (MENUID, MENUNAME, PRICE_ALACARTE, PRICE_OMAKASE, QRCODE, BARCODE, IMAGEPATH)
            VALUES (:id, :name, :a, :o, :qr, :bar, :img)";

    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":id", $_POST['menuid']);
    oci_bind_by_name($stid, ":name", $_POST['menuname']);
    oci_bind_by_name($stid, ":a", $_POST['price_a']);
    oci_bind_by_name($stid, ":o", $_POST['price_o']);
    oci_bind_by_name($stid, ":qr", $_POST['qrcode']);
    oci_bind_by_name($stid, ":bar", $_POST['barcode']);
    oci_bind_by_name($stid, ":img", $imagePath);

    oci_execute($stid);
    oci_commit($conn);
    header("Location: menu_list.php?msg=added");
    exit();
}

/* ---------------- UPDATE MENU ---------------- */
if (isset($_POST['edit'])) {
    $imagePath = $_POST['existing_image'];
    
    // ถ้ามีรูปใหม่อัพโหลด
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $uploadDir = 'menu_images/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // ลบรูปเดิม
        if (!empty($imagePath) && file_exists($imagePath)) {
            unlink($imagePath);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $newFileName = 'MENU_' . $_POST['menuid'] . '_' . time() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $newFileName;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
            $imagePath = $uploadPath;
        }
    }
    
    $sql = "UPDATE MENU
            SET MENUNAME = :name,
                PRICE_ALACARTE = :a,
                PRICE_OMAKASE = :o,
                QRCODE = :qr,
                BARCODE = :bar,
                IMAGEPATH = :img
            WHERE MENUID = :id";

    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":name", $_POST['name']);
    oci_bind_by_name($stid, ":a", $_POST['price_a']);
    oci_bind_by_name($stid, ":o", $_POST['price_o']);
    oci_bind_by_name($stid, ":qr", $_POST['qrcode']);
    oci_bind_by_name($stid, ":bar", $_POST['barcode']);
    oci_bind_by_name($stid, ":img", $imagePath);
    oci_bind_by_name($stid, ":id", $_POST['menuid']);

    oci_execute($stid);
    oci_commit($conn);
    header("Location: menu_list.php?msg=updated");
    exit();
}

/* ---------------- FETCH ALL MENU ---------------- */
$sql = "SELECT * FROM MENU ORDER BY MENUID";
$stid = oci_parse($conn, $sql);
oci_execute($stid);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Menu Management</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>

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

.alert {
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.form-section {
    background: white;
    padding: 25px;
    border-radius: 8px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    max-width: 900px;
}

.form-section h3 {
    margin-top: 0;
    color: #333;
    border-bottom: 2px solid #3498db;
    padding-bottom: 10px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #555;
}

.form-group input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
}

.form-group input:focus {
    outline: none;
    border-color: #3498db;
}

.code-hint {
    font-size: 12px;
    color: #7f8c8d;
    margin-top: 5px;
}

.generate-btn {
    background: #9b59b6;
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    margin-top: 5px;
    margin-right: 5px;
}

.generate-btn:hover {
    background: #8e44ad;
}

.scan-btn {
    background: #e74c3c;
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    margin-top: 5px;
}

.scan-btn:hover {
    background: #c0392b;
}

.image-preview {
    max-width: 200px;
    max-height: 200px;
    margin-top: 10px;
    border: 2px solid #ddd;
    border-radius: 4px;
    display: none;
}

.image-preview.show {
    display: block;
}

.btn-group {
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
}

.btn-course {
    background: #e74c3c;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    text-decoration: none;
    display: inline-block;
}

.btn-course:hover {
    background: #c0392b;
}

table {
    background: white;
    border-collapse: collapse;
    width: 98%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

table, th, td {
    border: 1px solid #ddd;
}

th, td {
    padding: 12px 8px;
    text-align: center;
    font-size: 13px;
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
    padding: 8px 15px;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
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

.btn-warning {
    background: #f39c12;
    color: white;
}

.btn-warning:hover {
    background: #e67e22;
}

.btn-danger {
    background: #e74c3c;
    color: white;
}

.btn-danger:hover {
    background: #c0392b;
}

.btn-secondary {
    background: #95a5a6;
    color: white;
}

.btn-secondary:hover {
    background: #7f8c8d;
}

.action-buttons {
    display: flex;
    gap: 5px;
    justify-content: center;
    flex-wrap: wrap;
}

.page-header {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
}

.page-header h3 {
    margin: 0;
    color: #333;
}

.page-header .icon {
    font-size: 24px;
    margin-right: 10px;
}

.price-column {
    text-align: right !important;
    font-weight: bold;
    color: #27ae60;
}

.product-img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 4px;
    border: 2px solid #ddd;
    cursor: pointer;
    transition: transform 0.2s;
}

.product-img:hover {
    transform: scale(1.1);
}

.no-image {
    width: 60px;
    height: 60px;
    background: #ecf0f1;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #95a5a6;
    font-size: 24px;
}

/* Barcode Scanner Modal */
.scanner-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.9);
    z-index: 9999;
}

.scanner-modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.scanner-content {
    background: white;
    padding: 20px;
    border-radius: 8px;
    max-width: 600px;
    width: 90%;
}

.scanner-content h3 {
    margin-top: 0;
}

#scanner-container {
    width: 100%;
    height: 400px;
    background: #000;
    border-radius: 4px;
    overflow: hidden;
    position: relative;
}

.scanner-result {
    background: #d4edda;
    padding: 15px;
    border-radius: 4px;
    margin-top: 15px;
    display: none;
}

.scanner-result.show {
    display: block;
}

.close-scanner {
    background: #e74c3c;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    margin-top: 15px;
}

.close-scanner:hover {
    background: #c0392b;
}
</style>
</head>

<body>

<!-- Top bar -->
<div class="top-bar">
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
    <button class="back-btn" onclick="window.location.href='homepage.php'">←</button>
    <h2>Menu Management</h2>
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
    <a href="profit_list.php">📊 Profit</a>
    <a href="member_list.php">👥 Members</a>
    <a href="course_menu_manage.php">🍱 Course</a>
</div>

<!-- Barcode Scanner Modal -->
<div id="scanner-modal" class="scanner-modal">
    <div class="scanner-content">
        <h3>📷 Scan Barcode</h3>
        <div id="scanner-container"></div>
        <div id="scanner-result" class="scanner-result">
            <strong>Detected Barcode:</strong> <span id="barcode-value"></span>
        </div>
        <button class="close-scanner" onclick="closeScanner()">Close Scanner</button>
    </div>
</div>

<div class="container">

<!-- Success Alert -->
<?php if (isset($_GET['msg'])): ?>
<div class="alert alert-success">
    <?php 
    if ($_GET['msg'] == 'added') echo '✅ เพิ่มเมนูเรียบร้อยแล้ว';
    elseif ($_GET['msg'] == 'updated') echo '✅ แก้ไขเมนูเรียบร้อยแล้ว';
    elseif ($_GET['msg'] == 'deleted') echo '✅ ลบเมนูเรียบร้อยแล้ว';
    ?>
</div>
<?php endif; ?>

<?php if (!isset($_GET['edit_id'])): ?>
<!-- ADD MENU FORM -->
<div class="form-section">
    <h3>➕ Add New Menu</h3>
    <form method="POST" enctype="multipart/form-data">
        <div class="form-row">
            <div class="form-group">
                <label>Menu ID</label>
                <input type="number" name="menuid" id="new_id" value="<?= $newMenuID ?>" readonly style="background: #f0f0f0;">
            </div>
            <div class="form-group">
                <label>Menu Name</label>
                <input type="text" name="menuname" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Price À la carte (฿)</label>
                <input type="number" name="price_a" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Price Omakase (฿)</label>
                <input type="number" name="price_o" step="0.01" required>
            </div>
        </div>
        
        <div class="form-group">
            <label>📷 Menu Image</label>
            <input type="file" name="image" accept="image/*" onchange="previewImage(event, 'preview_new')">
            <img id="preview_new" class="image-preview">
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>📱 QR Code <small style="color: #27ae60;">(Auto from Barcode)</small></label>
                <input type="text" name="qrcode" id="qrcode_input" placeholder="จะสร้างจากบาร์โค้ดอัตโนมัติ" readonly style="background: #f0f0f0;">
                <div class="code-hint">QR Code จะถูกสร้างจากรหัสบาร์โค้ดอัตโนมัติ</div>
            </div>
            <div class="form-group">
                <label>📊 Barcode (EAN-13)</label>
                <input type="text" name="barcode" id="barcode_input" placeholder="13 digits (EAN-13)" maxlength="13">
                <button type="button" class="generate-btn" onclick="generateBarcode()">🔄 Auto</button>
                <button type="button" class="scan-btn" onclick="openScanner('barcode_input')">📷 Scan</button>
                <div class="code-hint">เช่น: 8850123456789</div>
            </div>
        </div>
        
        <button type="submit" name="add" class="btn btn-success">Add Menu</button>
    </form>
</div>
<?php endif; ?>

<?php if (isset($_GET['edit_id'])): ?>
<!-- EDIT MENU FORM -->
<?php
    $id = $_GET['edit_id'];
    $st2 = oci_parse($conn, "SELECT * FROM MENU WHERE MENUID = :id");
    oci_bind_by_name($st2, ":id", $id);
    oci_execute($st2);
    $data = oci_fetch_assoc($st2);
?>
<div class="form-section">
    <h3>✏️ Edit Menu</h3>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="existing_image" value="<?= htmlspecialchars($data['IMAGEPATH'] ?? '') ?>">
        <div class="form-row">
            <div class="form-group">
                <label>Menu ID</label>
                <input type="number" name="menuid" id="edit_id" value="<?= $data['MENUID'] ?>" readonly style="background: #f0f0f0;">
            </div>
            <div class="form-group">
                <label>Menu Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($data['MENUNAME']) ?>" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Price À la carte (฿)</label>
                <input type="number" name="price_a" value="<?= $data['PRICE_ALACARTE'] ?>" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Price Omakase (฿)</label>
                <input type="number" name="price_o" value="<?= $data['PRICE_OMAKASE'] ?>" step="0.01" required>
            </div>
        </div>
        
        <div class="form-group">
            <label>📷 Menu Image</label>
            <?php if (!empty($data['IMAGEPATH']) && file_exists($data['IMAGEPATH'])): ?>
                <img src="<?= htmlspecialchars($data['IMAGEPATH']) ?>" class="image-preview show" id="preview_edit">
            <?php else: ?>
                <img id="preview_edit" class="image-preview">
            <?php endif; ?>
            <input type="file" name="image" accept="image/*" onchange="previewImage(event, 'preview_edit')">
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>📱 QR Code <small style="color: #27ae60;">(Auto from Barcode)</small></label>
                <input type="text" name="qrcode" id="qrcode_edit" value="<?= htmlspecialchars($data['QRCODE'] ?? '') ?>" readonly style="background: #f0f0f0;">
                <div class="code-hint">QR Code จะถูกสร้างจากรหัสบาร์โค้ดอัตโนมัติ</div>
            </div>
            <div class="form-group">
                <label>📊 Barcode</label>
                <input type="text" name="barcode" id="barcode_edit" value="<?= htmlspecialchars($data['BARCODE'] ?? '') ?>" placeholder="13 digits" maxlength="13">
                <button type="button" class="generate-btn" onclick="generateBarcodeEdit()">🔄 Auto</button>
                <button type="button" class="scan-btn" onclick="openScanner('barcode_edit')">📷 Scan</button>
            </div>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" name="edit" class="btn btn-primary">Update</button>
            <a href="menu_list.php"><button type="button" class="btn btn-secondary">Cancel</button></a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- MENU LIST -->
<div class="page-header">
    <span class="icon">🍽️</span>
    <h3>Menu List</h3>
</div>

<div class="btn-group">
    <a href="course_menu_manage.php" class="btn-course">🍱 จัดการเมนูใน Course</a>
</div>

<table>
    <thead>
        <tr>
            <th>Image</th>
            <th>ID</th>
            <th>Menu Name</th>
            <th>QR Code</th>
            <th>Barcode</th>
            <th>À la carte</th>
            <th>Omakase</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
<?php 
$count = 0;
oci_execute($stid);
while ($row = oci_fetch_assoc($stid)): 
    $count++;
?>
    <tr>
        <td>
            <?php if (!empty($row['IMAGEPATH']) && file_exists($row['IMAGEPATH'])): ?>
                <img src="<?= htmlspecialchars($row['IMAGEPATH']) ?>" 
                     class="product-img" 
                     onclick="window.open('<?= htmlspecialchars($row['IMAGEPATH']) ?>', '_blank')" 
                     title="Click to enlarge">
            <?php else: ?>
                <div class="no-image">🍽️</div>
            <?php endif; ?>
        </td>
        <td><strong><?= $row['MENUID'] ?></strong></td>
        <td style="text-align: left; font-weight: bold;"><?= htmlspecialchars($row['MENUNAME']) ?></td>
        <td>
            <?php if (!empty($row['QRCODE'])): ?>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=<?= urlencode($row['QRCODE']) ?>" 
                     alt="QR Code" 
                     style="width: 60px; height: 60px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;"
                     onclick="window.open('https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?= urlencode($row['QRCODE']) ?>', '_blank')"
                     title="<?= htmlspecialchars($row['QRCODE']) ?> - Click to enlarge">
                <div style="font-size: 10px; color: #7f8c8d; margin-top: 3px;"><?= htmlspecialchars($row['QRCODE']) ?></div>
                <a href="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?= urlencode($row['QRCODE']) ?>" download="QR_MENU_<?= $row['MENUID'] ?>.png" style="font-size: 10px; color: #3498db; text-decoration: none;">📥 Download</a>
            <?php else: ?>
                <span style="color:#95a5a6;">-</span>
            <?php endif; ?>
        </td>
        <td>
            <?php if (!empty($row['BARCODE'])): ?>
                <img src="https://barcode.tec-it.com/barcode.ashx?data=<?= urlencode($row['BARCODE']) ?>&code=EAN13&translate-esc=on" 
                     alt="Barcode" 
                     style="max-width: 100px; height: 40px; cursor: pointer;"
                     onclick="window.open('https://barcode.tec-it.com/barcode.ashx?data=<?= urlencode($row['BARCODE']) ?>&code=EAN13&translate-esc=on&dpi=300', '_blank')"
                     title="<?= htmlspecialchars($row['BARCODE']) ?> - Click to enlarge">
                <div style="font-size: 10px; color: #7f8c8d;"><?= htmlspecialchars($row['BARCODE']) ?></div>
                <a href="https://barcode.tec-it.com/barcode.ashx?data=<?= urlencode($row['BARCODE']) ?>&code=EAN13&translate-esc=on&dpi=300" download="Barcode_MENU_<?= $row['MENUID'] ?>.png" style="font-size: 10px; color: #3498db; text-decoration: none;">📥 Download</a>
            <?php else: ?>
                <span style="color:#95a5a6;">-</span>
            <?php endif; ?>
        </td>
        <td class="price-column"><?= number_format($row['PRICE_ALACARTE'], 2) ?> ฿</td>
        <td class="price-column"><?= number_format($row['PRICE_OMAKASE'], 2) ?> ฿</td>
        <td>
            <div class="action-buttons">
                <a href="menu_list.php?edit_id=<?= $row['MENUID'] ?>">
                    <button class="btn btn-warning">✏️ Edit</button>
                </a>
                <a href="menu_delete.php?id=<?= $row['MENUID'] ?>"
                   onclick="return confirm('Delete this menu?');">
                    <button class="btn btn-danger">🗑️ Delete</button>
                </a>
            </div>
        </td>
    </tr>
<?php endwhile; ?>

<?php if ($count == 0): ?>
    <tr>
        <td colspan="8" style="padding: 30px; color: #999;">
            <em>No menu items found. Add your first menu above.</em>
        </td>
    </tr>
<?php endif; ?>
    </tbody>
</table>

<p style="margin-top: 20px; color: #666;">
    <strong>Total Menu Items:</strong> <?= $count ?> items
</p>

</div>

<script>
let currentInputId = null;

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

// ==================== IMAGE PREVIEW ====================
function previewImage(event, previewId) {
    const preview = document.getElementById(previewId);
    const file = event.target.files[0];
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.add('show');
        };
        reader.readAsDataURL(file);
    }
}

// ==================== AUTO GENERATE QR CODE & BARCODE ====================

// สำหรับฟอร์ม Add
function generateBarcode() {
    const id = document.getElementById('new_id').value;
    const paddedId = id.toString().padStart(9, '0');
    const barcode = '885' + paddedId;
    const checksum = calculateEAN13Checksum(barcode);
    const fullBarcode = barcode + checksum;
    
    document.getElementById('barcode_input').value = fullBarcode;
    document.getElementById('qrcode_input').value = fullBarcode;
}

// สำหรับฟอร์ม Edit
function generateBarcodeEdit() {
    const id = document.getElementById('edit_id').value;
    const paddedId = id.toString().padStart(9, '0');
    const barcode = '885' + paddedId;
    const checksum = calculateEAN13Checksum(barcode);
    const fullBarcode = barcode + checksum;
    
    document.getElementById('barcode_edit').value = fullBarcode;
    document.getElementById('qrcode_edit').value = fullBarcode;
}

// เมื่อพิมพ์บาร์โค้ดเอง ให้สร้าง QR Code อัตโนมัติ
document.addEventListener('DOMContentLoaded', function() {
    const barcodeInput = document.getElementById('barcode_input');
    if (barcodeInput) {
        barcodeInput.addEventListener('input', function() {
            if (this.value.length >= 13) {
                document.getElementById('qrcode_input').value = this.value;
            }
        });
    }
    
    const barcodeEditInput = document.getElementById('barcode_edit');
    if (barcodeEditInput) {
        barcodeEditInput.addEventListener('input', function() {
            if (this.value.length >= 13) {
                document.getElementById('qrcode_edit').value = this.value;
            }
        });
    }
});

function calculateEAN13Checksum(barcode12) {
    let sum = 0;
    for (let i = 0; i < 12; i++) {
        const digit = parseInt(barcode12[i]);
        sum += (i % 2 === 0) ? digit : digit * 3;
    }
    const checksum = (10 - (sum % 10)) % 10;
    return checksum;
}

// ==================== BARCODE SCANNER ====================

function openScanner(inputId) {
    currentInputId = inputId;
    document.getElementById('scanner-modal').classList.add('active');
    document.getElementById('scanner-result').classList.remove('show');
    
    Quagga.init({
        inputStream: {
            name: "Live",
            type: "LiveStream",
            target: document.querySelector('#scanner-container'),
            constraints: {
                facingMode: "environment"
            }
        },
        decoder: {
            readers: ["ean_reader", "code_128_reader", "ean_8_reader", "code_39_reader", "upc_reader"]
        }
    }, function(err) {
        if (err) {
            console.error(err);
            alert('Cannot access camera. Please check permissions.');
            return;
        }
        Quagga.start();
    });
    
    Quagga.onDetected(function(result) {
        const code = result.codeResult.code;
        document.getElementById('barcode-value').textContent = code;
        document.getElementById('scanner-result').classList.add('show');
        document.getElementById(currentInputId).value = code;
        
        // Auto update QR Code
        if (currentInputId === 'barcode_input') {
            document.getElementById('qrcode_input').value = code;
        } else if (currentInputId === 'barcode_edit') {
            document.getElementById('qrcode_edit').value = code;
        }
        
        setTimeout(function() {
            closeScanner();
        }, 1500);
    });
}

function closeScanner() {
    Quagga.stop();
    document.getElementById('scanner-modal').classList.remove('active');
}
</script>
<script src="auth_guard.js"></script>
</body>
</html>