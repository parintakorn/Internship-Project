<?php
// ไฟล์: update_inventory_prefix.php
include 'config.php';

// ดึงรายการ Inventory ที่ยังใช้ PREFIX เก่า
$queryInventory = "SELECT 
                    i.INVENTORY_ID,
                    pf.PREFIX_CODE || '-' || LPAD(i.INVENTORY_ID, 6, '0') as INVENTORY_CODE,
                    p.PRODUCT_NAME,
                    p.CATEGORY,
                    i.WEIGHT,
                    TO_CHAR(i.RECEIVE_DATE, 'DD/MM/YYYY') as RECEIVE_DATE,
                    i.PREFIX_ID,
                    pf.PREFIX_CODE,
                    pf.DESCRIPTION
                   FROM INVENTORY i
                   JOIN PRODUCTS p ON i.PRODUCT_ID = p.PRODUCT_ID
                   LEFT JOIN PREFIXES pf ON i.PREFIX_ID = pf.PREFIX_ID
                   WHERE i.STATUS = 'IN_STOCK'
                   ORDER BY i.INVENTORY_ID";
$stidInventory = oci_parse($conn, $queryInventory);
oci_execute($stidInventory);
$inventoryList = [];
while ($row = oci_fetch_assoc($stidInventory)) {
    $inventoryList[] = $row;
}
oci_free_statement($stidInventory);

// ดึงรายการ Prefix ทั้งหมด
$queryPrefixes = "SELECT PREFIX_ID, PREFIX_CODE, DESCRIPTION FROM PREFIXES ORDER BY PREFIX_CODE";
$stidPrefixes = oci_parse($conn, $queryPrefixes);
oci_execute($stidPrefixes);
$prefixes = [];
while ($row = oci_fetch_assoc($stidPrefixes)) {
    $prefixes[] = $row;
}
oci_free_statement($stidPrefixes);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไข Prefix ของข้อมูลเก่า</title>
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
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #667eea;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #95a5a6;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }

        .info-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .info-box h3 {
            color: #856404;
            margin-bottom: 10px;
        }

        .info-box p {
            color: #856404;
            line-height: 1.6;
        }

        .filter-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .filter-box select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            font-family: 'Kanit', sans-serif;
            margin-right: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #34495e;
            color: white;
            font-weight: 600;
        }

        tr:hover {
            background: #f5f5f5;
        }

        .current-prefix {
            background: #fee;
            color: #c33;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: 600;
        }

        select.prefix-select {
            padding: 8px 12px;
            border: 2px solid #667eea;
            border-radius: 5px;
            font-family: 'Kanit', sans-serif;
            font-weight: 600;
        }

        .btn-update {
            background: #27ae60;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Kanit', sans-serif;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-update:hover {
            background: #229954;
            transform: scale(1.05);
        }

        .btn-update-all {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            font-family: 'Kanit', sans-serif;
            font-weight: 600;
            margin-top: 20px;
            transition: all 0.3s;
        }

        .btn-update-all:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .success, .error {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
        }

        .success {
            background: #d4edda;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
        }

        .success.active, .error.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="warehouse_report.php" class="back-btn">← กลับ</a>
        
        <div class="header">
            <h1>🔄 แก้ไข Prefix ของข้อมูลเก่า</h1>
            <p style="color: #666;">เปลี่ยน Prefix จาก INV เป็น PORK, BEEF, CHK ฯลฯ</p>
        </div>

        <div class="info-box">
            <h3>⚠️ คำแนะนำ:</h3>
            <p>
                • เลือก Prefix ใหม่สำหรับแต่ละรายการวัตถุดิบ<br>
                • ควรเลือก Prefix ตามประเภทวัตถุดิบ (เช่น หมู → PORK, เนื้อ → BEEF, ไก่ → CHK)<br>
                • กด "อัพเดท" แต่ละรายการ หรือกด "อัพเดททั้งหมด" เพื่ออัพเดทพร้อมกัน
            </p>
        </div>

        <div id="successMsg" class="success"></div>
        <div id="errorMsg" class="error"></div>

        <div class="filter-box">
            <strong>กรองตามหมวดหมู่:</strong>
            <select onchange="filterByCategory(this.value)">
                <option value="all">ทั้งหมด</option>
                <option value="หมู">หมู</option>
                <option value="เนื้อ">เนื้อ</option>
                <option value="ไก่">ไก่</option>
                <option value="อาหารทะเล">อาหารทะเล</option>
            </select>

            <strong style="margin-left: 20px;">กรองตาม Prefix ปัจจุบัน:</strong>
            <select onchange="filterByPrefix(this.value)">
                <option value="all">ทั้งหมด</option>
                <?php foreach($prefixes as $pf): ?>
                    <option value="<?=$pf['PREFIX_CODE']?>"><?=$pf['PREFIX_CODE']?> - <?=$pf['DESCRIPTION']?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <table id="inventoryTable">
            <thead>
                <tr>
                    <th>รหัสปัจจุบัน</th>
                    <th>ชื่อวัตถุดิบ</th>
                    <th>หมวดหมู่</th>
                    <th>น้ำหนัก (g)</th>
                    <th>Prefix ปัจจุบัน</th>
                    <th>เปลี่ยนเป็น</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($inventoryList as $item): ?>
                <tr data-category="<?=htmlspecialchars($item['CATEGORY'])?>" data-prefix="<?=htmlspecialchars($item['PREFIX_CODE'])?>">
                    <td><strong><?=htmlspecialchars($item['INVENTORY_CODE'])?></strong></td>
                    <td><?=htmlspecialchars($item['PRODUCT_NAME'])?></td>
                    <td><?=htmlspecialchars($item['CATEGORY'])?></td>
                    <td><?=number_format($item['WEIGHT'], 2)?></td>
                    <td>
                        <span class="current-prefix"><?=htmlspecialchars($item['PREFIX_CODE'])?></span>
                    </td>
                    <td>
                        <select class="prefix-select" id="prefix_<?=$item['INVENTORY_ID']?>">
                            <?php foreach($prefixes as $pf): ?>
                                <option value="<?=$pf['PREFIX_ID']?>" <?=$pf['PREFIX_ID']==$item['PREFIX_ID']?'selected':''?>>
                                    <?=$pf['PREFIX_CODE']?> - <?=$pf['DESCRIPTION']?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <button class="btn-update" onclick="updatePrefix(<?=$item['INVENTORY_ID']?>)">
                            ✓ อัพเดท
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="text-align: center; margin-top: 30px;">
            <button class="btn-update-all" onclick="updateAllPrefixes()">
                🔄 อัพเดททั้งหมด
            </button>
        </div>
    </div>

    <script>
        function showMessage(message, type) {
            const successMsg = document.getElementById('successMsg');
            const errorMsg = document.getElementById('errorMsg');
            
            successMsg.classList.remove('active');
            errorMsg.classList.remove('active');
            
            if (type === 'success') {
                successMsg.textContent = message;
                successMsg.classList.add('active');
            } else {
                errorMsg.textContent = message;
                errorMsg.classList.add('active');
            }
            
            setTimeout(() => {
                successMsg.classList.remove('active');
                errorMsg.classList.remove('active');
            }, 5000);
        }

        function filterByCategory(category) {
            const table = document.getElementById('inventoryTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const rowCategory = row.getAttribute('data-category');
                
                if (category === 'all' || rowCategory === category) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        }

        function filterByPrefix(prefix) {
            const table = document.getElementById('inventoryTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const rowPrefix = row.getAttribute('data-prefix');
                
                if (prefix === 'all' || rowPrefix === prefix) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        }

        function updatePrefix(inventoryId) {
            const selectElement = document.getElementById('prefix_' + inventoryId);
            const newPrefixId = selectElement.value;
            
            if (!confirm('คุณแน่ใจหรือไม่ที่จะเปลี่ยน Prefix?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('inventoryId', inventoryId);
            formData.append('newPrefixId', newPrefixId);

            fetch('update_prefix_single.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('✓ อัพเดท Prefix สำเร็จ!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showMessage('✗ เกิดข้อผิดพลาด: ' + data.error, 'error');
                }
            })
            .catch(error => {
                showMessage('✗ ไม่สามารถเชื่อมต่อได้', 'error');
            });
        }

        function updateAllPrefixes() {
            if (!confirm('คุณแน่ใจหรือไม่ที่จะอัพเดท Prefix ทั้งหมด?')) {
                return;
            }
            
            const updates = [];
            const table = document.getElementById('inventoryTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                if (rows[i].style.display === 'none') continue;
                
                const selectElement = rows[i].querySelector('select.prefix-select');
                const inventoryId = selectElement.id.replace('prefix_', '');
                const newPrefixId = selectElement.value;
                
                updates.push({
                    inventoryId: inventoryId,
                    newPrefixId: newPrefixId
                });
            }
            
            fetch('update_prefix_bulk.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ updates: updates })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('✓ อัพเดททั้งหมดสำเร็จ (' + data.count + ' รายการ)', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showMessage('✗ เกิดข้อผิดพลาด: ' + data.error, 'error');
                }
            })
            .catch(error => {
                showMessage('✗ ไม่สามารถเชื่อมต่อได้', 'error');
            });
        }
    </script>
    <script src="auth_guard.js"></script>
</body>
</html>
<?php
oci_close($conn);
?>