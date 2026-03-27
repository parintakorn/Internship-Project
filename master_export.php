<?php
/**
 * Master Export Page
 * Export ข้อมูลทั้งหมดเป็น Excel ทันที
 */

require 'connect.php';
require 'export_helper.php';

// ตรวจสอบการ Export
if (isset($_POST['export_all'])) {
    $exporter = new ExportHelper($conn);
    $filepath = $exporter->exportAll();
    
    if ($filepath && file_exists($filepath)) {
        // ดาวน์โหลดไฟล์
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . basename($filepath) . '"');
        header('Cache-Control: max-age=0');
        readfile($filepath);
        exit;
    } else {
        $error = "ไม่สามารถสร้างไฟล์ Export ได้";
    }
}

// นับจำนวนข้อมูล
$counts = [];

$tables = [
    'INGREDIENT' => 'วัตถุดิบ',
    'TRANSACTION' => 'ธุรกรรม',
    'ORDER_ITEM' => 'รายละเอียดออเดอร์',
    'RECIPE' => 'สูตรอาหาร',
    'ORDER_PROFIT' => 'กำไร',
    'MENU_COURSE' => 'คอร์ส'
];

foreach ($tables as $table => $name) {
    $sql = "SELECT COUNT(*) AS CNT FROM $table";
    $stid = oci_parse($conn, $sql);
    oci_execute($stid);
    $row = oci_fetch_assoc($stid);
    $counts[$name] = $row['CNT'];
}

// ดึงรายการไฟล์ที่เคย Export
$exportFiles = [];
if (file_exists('exports/')) {
    $files = scandir('exports/', SCANDIR_SORT_DESCENDING);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'xlsx') {
            $exportFiles[] = [
                'name' => $file,
                'size' => filesize('exports/' . $file),
                'date' => filemtime('exports/' . $file)
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>📊 Export ข้อมูลเป็น Excel</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 20px;
}

.container {
    max-width: 1000px;
    margin: 0 auto;
}

.header {
    text-align: center;
    color: white;
    margin-bottom: 30px;
}

.header h1 {
    font-size: 42px;
    margin-bottom: 10px;
}

.header p {
    font-size: 18px;
    opacity: 0.9;
}

.card {
    background: white;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    margin-bottom: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.stat-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 12px;
    text-align: center;
}

.stat-box h3 {
    font-size: 14px;
    opacity: 0.9;
    margin-bottom: 10px;
}

.stat-box .number {
    font-size: 32px;
    font-weight: bold;
}

.export-btn {
    width: 100%;
    padding: 20px;
    background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 20px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.export-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(39, 174, 96, 0.3);
}

.info-box {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
    padding: 15px;
    border-radius: 8px;
    margin-top: 20px;
}

.info-box h4 {
    color: #1976d2;
    margin-bottom: 10px;
}

.info-box ul {
    margin-left: 20px;
    color: #0d47a1;
}

.info-box ul li {
    margin-bottom: 5px;
}

.files-section {
    margin-top: 30px;
}

.files-section h3 {
    color: #2c3e50;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #ecf0f1;
}

.file-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 10px;
    transition: all 0.3s;
}

.file-item:hover {
    background: #e9ecef;
    transform: translateX(5px);
}

.file-info {
    flex: 1;
}

.file-name {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 5px;
}

.file-meta {
    font-size: 13px;
    color: #7f8c8d;
}

.download-btn {
    padding: 8px 16px;
    background: #3498db;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}

.download-btn:hover {
    background: #2980b9;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-error {
    background: #fee;
    color: #c33;
    border: 1px solid #fcc;
}

.back-btn {
    display: inline-block;
    padding: 10px 20px;
    background: rgba(255,255,255,0.2);
    color: white;
    border-radius: 8px;
    text-decoration: none;
    margin-bottom: 20px;
    transition: all 0.3s;
}

.back-btn:hover {
    background: rgba(255,255,255,0.3);
}
</style>
</head>
<body>

<div class="container">
    <a href="order_list.php" class="back-btn">← กลับไปหน้าออเดอร์</a>
    
    <div class="header">
        <h1>📊 Export ข้อมูล Real-time</h1>
        <p>สำรองข้อมูลทั้งหมดเป็น Excel ทันที</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>

    <div class="card">
        <h2 style="margin-bottom: 20px; color: #2c3e50;">📈 สถิติข้อมูลปัจจุบัน</h2>
        
        <div class="stats-grid">
            <?php foreach ($counts as $name => $count): ?>
                <div class="stat-box">
                    <h3><?= $name ?></h3>
                    <div class="number"><?= number_format($count) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="POST">
            <button type="submit" name="export_all" class="export-btn">
                <span style="font-size: 24px;">📥</span>
                <span>Export ทั้งหมดเป็น Excel</span>
            </button>
        </form>

        <div class="info-box">
            <h4>📋 ไฟล์ที่จะได้:</h4>
            <ul>
                <li><strong>วัตถุดิบ</strong> - รายการวัตถุดิบทั้งหมด + จำนวนคงเหลือ + มูลค่า</li>
                <li><strong>ธุรกรรม</strong> - ข้อมูลออเดอร์ทั้งหมด + ลูกค้า + ยอดขาย</li>
                <li><strong>รายละเอียดออเดอร์</strong> - เมนูในแต่ละออเดอร์</li>
                <li><strong>สูตรอาหาร</strong> - ส่วนผสมและต้นทุนของแต่ละเมนู</li>
                <li><strong>กำไร</strong> - รายงานกำไรแต่ละออเดอร์</li>
                <li><strong>คอร์ส</strong> - รายการคอร์ส Omakase + ราคา + จำนวนเมนู</li>
                <li><strong>เมนูในคอร์ส</strong> - รายละเอียดเมนูในแต่ละคอร์ส</li>
            </ul>
        </div>
    </div>

    <?php if (!empty($exportFiles)): ?>
    <div class="card files-section">
        <h3>📁 ไฟล์ที่เคย Export (<?= count($exportFiles) ?> ไฟล์)</h3>
        
        <?php foreach ($exportFiles as $file): ?>
        <div class="file-item">
            <div class="file-info">
                <div class="file-name">📄 <?= htmlspecialchars($file['name']) ?></div>
                <div class="file-meta">
                    <?= number_format($file['size'] / 1024, 2) ?> KB · 
                    <?= date('d/m/Y H:i:s', $file['date']) ?>
                </div>
            </div>
            <a href="exports/<?= htmlspecialchars($file['name']) ?>" class="download-btn" download>
                ⬇️ ดาวน์โหลด
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>