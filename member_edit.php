<?php
include 'connect.php';

if (!isset($_GET['id'])) {
    header('Location: member_list.php');
    exit;
}

$customerId = $_GET['id'];

// ดึงข้อมูลสมาชิก
$sql = "SELECT * FROM MEMBER WHERE CUSTOMERID = :id";
$stid = oci_parse($conn, $sql);
oci_bind_by_name($stid, ":id", $customerId);
oci_execute($stid);
$member = oci_fetch_assoc($stid);

if (!$member) {
    header('Location: member_list.php?error=not_found');
    exit;
}

// ดึงระดับสมาชิก
$levelSql = "SELECT LEVELID, LEVELNAME FROM MEMBER_LEVEL ORDER BY LEVELID";
$levelStid = oci_parse($conn, $levelSql);
oci_execute($levelStid);

// บันทึกการแก้ไข
if (isset($_POST['update'])) {
    $name = $_POST['name'];
    $address = $_POST['address'];
    $tel = $_POST['tel'];
    $levelid = $_POST['levelid'];
    $lineid = $_POST['lineid'];

    $updateSql = "UPDATE MEMBER SET
                    CUSTOMERNAME = :name,
                    ADDRESS = :addr,
                    TEL = :tel,
                    LEVELID = :lvl,
                    LINEID = :lineid
                  WHERE CUSTOMERID = :id";

    $updateStid = oci_parse($conn, $updateSql);
    oci_bind_by_name($updateStid, ":name", $name);
    oci_bind_by_name($updateStid, ":addr", $address);
    oci_bind_by_name($updateStid, ":tel", $tel);
    oci_bind_by_name($updateStid, ":lvl", $levelid);
    oci_bind_by_name($updateStid, ":lineid", $lineid);
    oci_bind_by_name($updateStid, ":id", $customerId);

    if (oci_execute($updateStid)) {
        oci_commit($conn);
        header('Location: member_list.php?msg=updated');
        exit;
    } else {
        $error = "เกิดข้อผิดพลาดในการแก้ไข";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Edit Member</title>
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

        .back-btn {
            font-size: 24px;
            margin-right: 15px;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 8px;
            border: none;
            background: #667eea;
            color: white;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .container {
            max-width: 700px;
            margin: 90px auto 40px;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        h2 {
            color: #2c3e50;
            margin-bottom: 25px;
            border-bottom: 3px solid #9b59b6;
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #34495e;
        }

        input, select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 15px;
            box-sizing: border-box;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #9b59b6;
        }

        input[readonly] {
            background: #f8f9fa;
            color: #666;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            margin-right: 10px;
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

        .error-msg {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="top-bar">
    <button class="back-btn" onclick="window.location.href='member_list.php'">←</button>
    <h2 style="margin: 0;">แก้ไขข้อมูลสมาชิก</h2>
</div>

<div class="container">
    <h2>✏️ แก้ไขข้อมูลสมาชิก</h2>

    <?php if (isset($error)): ?>
        <div class="error-msg"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Customer ID</label>
            <input type="text" value="<?= htmlspecialchars($member['CUSTOMERID']) ?>" readonly>
        </div>

        <div class="form-group">
            <label>ชื่อ-นามสกุล *</label>
            <input type="text" name="name" value="<?= htmlspecialchars($member['CUSTOMERNAME']) ?>" required>
        </div>

        <div class="form-group">
            <label>ที่อยู่ *</label>
            <input type="text" name="address" value="<?= htmlspecialchars($member['ADDRESS']) ?>" required>
        </div>

        <div class="form-group">
            <label>เบอร์โทร *</label>
            <input type="tel" name="tel" value="<?= htmlspecialchars($member['TEL']) ?>" required>
        </div>

        <div class="form-group">
            <label>Line ID</label>
            <input type="text" name="lineid" value="<?= htmlspecialchars($member['LINEID']) ?>">
        </div>

        <div class="form-group">
            <label>ระดับสมาชิก</label>
            <select name="levelid">
                <option value="">-- ไม่มี --</option>
                <?php
                oci_execute($levelStid);
                while ($level = oci_fetch_assoc($levelStid)): ?>
                    <option value="<?= $level['LEVELID'] ?>"
                            <?= ($member['LEVELID'] == $level['LEVELID']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($level['LEVELNAME']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div style="margin-top: 30px;">
            <button type="submit" name="update" class="btn btn-primary">💾 บันทึกการแก้ไข</button>
            <a href="member_list.php"><button type="button" class="btn btn-secondary">ยกเลิก</button></a>
        </div>
    </form>
</div>

</body>
</html>
