<?php
include 'config.php';

// ดึงรายการ Prefix
$queryPrefixes = "SELECT PREFIX_ID, PREFIX_CODE, DESCRIPTION, 
                  TO_CHAR(CREATED_DATE, 'DD/MM/YYYY') as CREATED_DATE_FORMATTED
                  FROM PREFIXES 
                  ORDER BY PREFIX_CODE";
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
    <title>จัดการ Prefix</title>
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
            max-width: 900px;
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

        .add-btn {
            background: #27ae60;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Kanit', sans-serif;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .add-btn:hover {
            background: #229954;
            transform: translateY(-2px);
        }

        .search-box {
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            font-family: 'Kanit', sans-serif;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 15px;
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

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-family: 'Kanit', sans-serif;
            margin: 0 3px;
            transition: all 0.3s;
        }

        .btn-edit {
            background: #3498db;
            color: white;
        }

        .btn-edit:hover {
            background: #2980b9;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-delete:hover {
            background: #c0392b;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
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
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: #667eea;
            font-size: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            font-family: 'Kanit', sans-serif;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group input:read-only {
            background: #f5f5f5;
            cursor: not-allowed;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-submit {
            flex: 1;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Kanit', sans-serif;
        }

        .btn-submit:hover {
            background: #5568d3;
        }

        .btn-cancel {
            flex: 1;
            padding: 12px;
            background: #95a5a6;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Kanit', sans-serif;
        }

        .btn-cancel:hover {
            background: #7f8c8d;
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
            <h1>🏷️ จัดการ Prefix</h1>
            <p style="color: #666;">จัดการรหัสคำนำหน้าสำหรับวัตถุดิบ</p>
        </div>

        <div id="successMsg" class="success"></div>
        <div id="errorMsg" class="error"></div>

        <button class="add-btn" onclick="showAddModal()">➕ เพิ่ม Prefix ใหม่</button>

        <div class="search-box">
            <input type="text" id="searchInput" placeholder="🔍 ค้นหา Prefix..." onkeyup="filterTable()">
        </div>

        <table id="prefixTable">
            <thead>
                <tr>
                    <th>รหัส Prefix</th>
                    <th>คำอธิบาย</th>
                    <th>วันที่สร้าง</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($prefixes as $prefix): ?>
                <tr>
                    <td><strong><?=htmlspecialchars($prefix['PREFIX_CODE'])?></strong></td>
                    <td><?=htmlspecialchars($prefix['DESCRIPTION'])?></td>
                    <td><?=$prefix['CREATED_DATE_FORMATTED']?></td>
                    <td>
                        <button class="btn btn-edit" onclick='editPrefix(<?=$prefix['PREFIX_ID']?>, "<?=htmlspecialchars($prefix['PREFIX_CODE'], ENT_QUOTES)?>", "<?=htmlspecialchars($prefix['DESCRIPTION'], ENT_QUOTES)?>")'>
                            ✏️ แก้ไข
                        </button>
                        <button class="btn btn-delete" onclick='deletePrefix(<?=$prefix['PREFIX_ID']?>, "<?=htmlspecialchars($prefix['PREFIX_CODE'], ENT_QUOTES)?>')'>
                            🗑️ ลบ
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal เพิ่ม Prefix -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>➕ เพิ่ม Prefix ใหม่</h2>
            </div>
            <form id="addForm" onsubmit="handleAdd(event)">
                <div class="form-group">
                    <label>รหัส Prefix (2-5 ตัวอักษร)</label>
                    <input type="text" id="addPrefixCode" pattern="[A-Za-z]{2,5}" maxlength="5" style="text-transform: uppercase;" required>
                    <small style="color: #666;">ตัวอย่าง: PORK, BEEF, CHK</small>
                </div>
                <div class="form-group">
                    <label>คำอธิบาย</label>
                    <input type="text" id="addDescription" required>
                </div>
                <div class="modal-buttons">
                    <button type="submit" class="btn-submit">✓ บันทึก</button>
                    <button type="button" class="btn-cancel" onclick="closeAddModal()">✕ ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal แก้ไข Prefix -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ แก้ไข Prefix</h2>
            </div>
            <form id="editForm" onsubmit="handleEdit(event)">
                <input type="hidden" id="editPrefixId">
                <div class="form-group">
                    <label>รหัส Prefix</label>
                    <input type="text" id="editPrefixCode" readonly>
                    <small style="color: #999;">⚠️ ไม่สามารถแก้ไขรหัสได้</small>
                </div>
                <div class="form-group">
                    <label>คำอธิบาย</label>
                    <input type="text" id="editDescription" required>
                </div>
                <div class="modal-buttons">
                    <button type="submit" class="btn-submit">✓ บันทึก</button>
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">✕ ยกเลิก</button>
                </div>
            </form>
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

        function filterTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('prefixTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const tdCode = tr[i].getElementsByTagName('td')[0];
                const tdDesc = tr[i].getElementsByTagName('td')[1];
                
                if (tdCode || tdDesc) {
                    const txtCode = tdCode.textContent || tdCode.innerText;
                    const txtDesc = tdDesc.textContent || tdDesc.innerText;
                    
                    if (txtCode.toUpperCase().indexOf(filter) > -1 || txtDesc.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = '';
                    } else {
                        tr[i].style.display = 'none';
                    }
                }
            }
        }

        function showAddModal() {
            document.getElementById('addModal').classList.add('active');
            document.getElementById('addPrefixCode').focus();
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
            document.getElementById('addForm').reset();
        }

        function handleAdd(event) {
            event.preventDefault();
            
            const formData = new FormData();
            formData.append('prefixCode', document.getElementById('addPrefixCode').value.toUpperCase());
            formData.append('description', document.getElementById('addDescription').value);

            fetch('add_prefix.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('✓ เพิ่ม Prefix สำเร็จ!', 'success');
                    closeAddModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showMessage('✗ เกิดข้อผิดพลาด: ' + data.error, 'error');
                }
            })
            .catch(error => {
                showMessage('✗ ไม่สามารถเชื่อมต่อได้', 'error');
            });
        }

        function editPrefix(id, code, description) {
            document.getElementById('editPrefixId').value = id;
            document.getElementById('editPrefixCode').value = code;
            document.getElementById('editDescription').value = description;
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
            document.getElementById('editForm').reset();
        }

        function handleEdit(event) {
            event.preventDefault();
            
            const formData = new FormData();
            formData.append('prefixId', document.getElementById('editPrefixId').value);
            formData.append('description', document.getElementById('editDescription').value);

            fetch('edit_prefix.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('✓ แก้ไข Prefix สำเร็จ!', 'success');
                    closeEditModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showMessage('✗ เกิดข้อผิดพลาด: ' + data.error, 'error');
                }
            })
            .catch(error => {
                showMessage('✗ ไม่สามารถเชื่อมต่อได้', 'error');
            });
        }

        function deletePrefix(id, code) {
            if (!confirm(`คุณแน่ใจหรือไม่ที่จะลบ Prefix "${code}"?\n\nการลบจะไม่สามารถกู้คืนได้`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('prefixId', id);

            fetch('delete_prefix.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('✓ ลบ Prefix สำเร็จ!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showMessage('✗ เกิดข้อผิดพลาด: ' + data.error, 'error');
                }
            })
            .catch(error => {
                showMessage('✗ ไม่สามารถเชื่อมต่อได้', 'error');
            });
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }

        document.addEventListener('keydown', function(e) {
            if(e.key === 'Escape') {
                closeAddModal();
                closeEditModal();
            }
        });
    </script>
    <script src="auth_guard.js"></script>
</body>
</html>
<?php
oci_close($conn);
?>