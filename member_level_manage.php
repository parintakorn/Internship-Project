<?php
include 'connect.php';

// เพิ่มระดับใหม่
if (isset($_POST['add_level'])) {
    $levelname = $_POST['levelname'];
    $discount = $_POST['discount'];
    
    $sql = "INSERT INTO MEMBER_LEVEL (LEVELID, LEVELNAME, DISCOUNT) 
            VALUES ((SELECT NVL(MAX(LEVELID), 0) + 1 FROM MEMBER_LEVEL), :name, :disc)";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":name", $levelname);
    oci_bind_by_name($stid, ":disc", $discount);
    oci_execute($stid);
    oci_commit($conn);
    
    header("Location: member_level_manage.php?msg=added");
    exit();
}

// แก้ไขระดับ
if (isset($_POST['edit_level'])) {
    $levelid = $_POST['levelid'];
    $levelname = $_POST['levelname'];
    $discount = $_POST['discount'];
    
    $sql = "UPDATE MEMBER_LEVEL SET LEVELNAME = :name, DISCOUNT = :disc WHERE LEVELID = :id";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":name", $levelname);
    oci_bind_by_name($stid, ":disc", $discount);
    oci_bind_by_name($stid, ":id", $levelid);
    oci_execute($stid);
    oci_commit($conn);
    
    header("Location: member_level_manage.php?msg=updated");
    exit();
}

// ลบระดับ
if (isset($_GET['delete'])) {
    $levelid = $_GET['delete'];
    
    // เช็คว่ามีสมาชิกใช้ level นี้หรือไม่
    $check = oci_parse($conn, "SELECT COUNT(*) as CNT FROM MEMBER WHERE LEVELID = :id");
    oci_bind_by_name($check, ":id", $levelid);
    oci_execute($check);
    $row = oci_fetch_assoc($check);
    
    if ($row['CNT'] > 0) {
        header("Location: member_level_manage.php?error=inuse");
        exit();
    }
    
    $sql = "DELETE FROM MEMBER_LEVEL WHERE LEVELID = :id";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":id", $levelid);
    oci_execute($stid);
    oci_commit($conn);
    
    header("Location: member_level_manage.php?msg=deleted");
    exit();
}

// ดึงข้อมูลสำหรับแก้ไข
$editLevel = null;
if (isset($_GET['edit'])) {
    $editId = $_GET['edit'];
    $sql = "SELECT * FROM MEMBER_LEVEL WHERE LEVELID = :id";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":id", $editId);
    oci_execute($stid);
    $editLevel = oci_fetch_assoc($stid);
}

// ดึงรายการ Level ทั้งหมด
$sql = "SELECT l.*, 
        (SELECT COUNT(*) FROM MEMBER WHERE LEVELID = l.LEVELID) as MEMBER_COUNT
        FROM MEMBER_LEVEL l 
        ORDER BY l.LEVELID";
$stid = oci_parse($conn, $sql);
oci_execute($stid);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>จัดการระดับสมาชิก</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 40px auto;
            background: white;
            border-radius: 15px;
            padding: 35px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        h2 {
            color: #2c3e50;
            border-bottom: 3px solid #9b59b6;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }
        
        .form-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .form-section h3 {
            color: #34495e;
            margin-top: 0;
        }
        
        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
            font-weight: 600;
            color: #34495e;
        }
        
        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #9b59b6;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            margin-top: 15px;
            margin-right: 10px;
            transition: background 0.3s;
        }
        
        .btn-primary {
            background: #9b59b6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #8e44ad;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .btn-cancel {
            background: #e74c3c;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #c0392b;
        }
        
        table {
            width: 100%;
            background: white;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        table, th, td {
            border: 1px solid #ddd;
        }
        
        th, td {
            padding: 12px;
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
        
        .level-badge {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 15px;
            font-size: 14px;
            font-weight: bold;
        }
        
        .level-1 { background: #95a5a6; color: white; }
        .level-2 { background: #f39c12; color: white; }
        .level-3 { background: #34495e; color: white; }
        
        .discount-badge {
            background: #27ae60;
            color: white;
            padding: 5px 12px;
            border-radius: 12px;
            font-weight: bold;
        }
        
        .member-count {
            color: #3498db;
            font-weight: bold;
        }
        
        .action-btn {
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            margin: 2px;
            display: inline-block;
        }
        
        .btn-edit {
            background: #f39c12;
            color: white;
        }
        
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
        
        .btn-edit:hover { background: #d68910; }
        .btn-delete:hover { background: #c0392b; }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #9b59b6;
            text-decoration: none;
            font-weight: 600;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="container">
    <a href="member_list.php" class="back-link">← กลับไปรายการสมาชิก</a>
    
    <h2>⚙️ จัดการระดับสมาชิก</h2>
    
    <?php if (isset($_GET['msg'])): ?>
        <div class="message success">
            <?php 
            if ($_GET['msg'] == 'added') echo '✅ เพิ่มระดับสมาชิกเรียบร้อยแล้ว';
            elseif ($_GET['msg'] == 'updated') echo '✅ แก้ไขระดับสมาชิกเรียบร้อยแล้ว';
            elseif ($_GET['msg'] == 'deleted') echo '✅ ลบระดับสมาชิกเรียบร้อยแล้ว';
            ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="message error">
            <?php 
            if ($_GET['error'] == 'inuse') echo '❌ ไม่สามารถลบได้ เนื่องจากมีสมาชิกใช้ระดับนี้อยู่';
            ?>
        </div>
    <?php endif; ?>
    
    <!-- ฟอร์มเพิ่ม/แก้ไข -->
    <div class="form-section">
        <h3><?= $editLevel ? '✏️ แก้ไขระดับสมาชิก' : '➕ เพิ่มระดับสมาชิกใหม่' ?></h3>
        
        <form method="POST">
            <?php if ($editLevel): ?>
                <input type="hidden" name="levelid" value="<?= $editLevel['LEVELID'] ?>">
            <?php endif; ?>
            
            <label>ชื่อระดับ:</label>
            <input type="text" name="levelname" 
                   value="<?= $editLevel ? htmlspecialchars($editLevel['LEVELNAME']) : '' ?>" 
                   placeholder="เช่น Silver, Gold, Platinum" required>
            
            <label>ส่วนลด (%):</label>
            <input type="number" name="discount" step="0.01" min="0" max="100"
                   value="<?= $editLevel ? $editLevel['DISCOUNT'] : '' ?>" 
                   placeholder="เช่น 5, 10, 15" required>
            
            <button type="submit" name="<?= $editLevel ? 'edit_level' : 'add_level' ?>" class="btn btn-primary">
                <?= $editLevel ? '💾 บันทึกการแก้ไข' : '➕ เพิ่มระดับ' ?>
            </button>
            
            <?php if ($editLevel): ?>
                <a href="member_level_manage.php" class="btn btn-cancel">❌ ยกเลิก</a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- ตารางแสดงระดับทั้งหมด -->
    <h3>📋 รายการระดับสมาชิกทั้งหมด</h3>
    
    <table>
        <thead>
            <tr>
                <th>Level ID</th>
                <th>ชื่อระดับ</th>
                <th>ส่วนลด</th>
                <th>จำนวนสมาชิก</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php 
        $count = 0;
        while($r = oci_fetch_assoc($stid)): 
            $count++;
            $badgeClass = 'level-' . $r['LEVELID'];
        ?>
            <tr>
                <td><strong><?= $r['LEVELID'] ?></strong></td>
                <td>
                    <span class="level-badge <?= $badgeClass ?>">
                        <?= htmlspecialchars($r['LEVELNAME']) ?>
                    </span>
                </td>
                <td>
                    <span class="discount-badge"><?= $r['DISCOUNT'] ?>%</span>
                </td>
                <td class="member-count"><?= $r['MEMBER_COUNT'] ?> คน</td>
                <td>
                    <a href="member_level_manage.php?edit=<?= $r['LEVELID'] ?>" class="action-btn btn-edit">✏️ Edit</a>
                    <a href="member_level_manage.php?delete=<?= $r['LEVELID'] ?>" 
                       onclick="return confirm('ต้องการลบระดับ <?= htmlspecialchars($r['LEVELNAME']) ?> ใช่หรือไม่?')"
                       class="action-btn btn-delete">🗑️ Delete</a>
                </td>
            </tr>
        <?php endwhile; ?>
        
        <?php if ($count == 0): ?>
            <tr>
                <td colspan="5" style="padding: 30px; color: #999;">
                    <em>ยังไม่มีระดับสมาชิก</em>
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    
    <p style="margin-top: 20px; color: #666;">
        <strong>Total Levels:</strong> <?= $count ?> ระดับ
    </p>
</div>

</body>
</html>