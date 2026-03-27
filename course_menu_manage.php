<?php
include 'connect.php';

// ---------------------- สร้างตาราง COURSE_MENU อัตโนมัติ ----------------------
$createTable = "
BEGIN 
    EXECUTE IMMEDIATE '
        CREATE TABLE COURSE_MENU (
            COURSEID NUMBER NOT NULL,
            MENUID NUMBER NOT NULL,
            QUANTITY NUMBER DEFAULT 1,
            PRIMARY KEY (COURSEID, MENUID),
            CONSTRAINT fk_course_menu_course FOREIGN KEY (COURSEID) REFERENCES MENU_COURSE(COURSEID) ON DELETE CASCADE,
            CONSTRAINT fk_course_menu_menu FOREIGN KEY (MENUID) REFERENCES MENU(MENUID) ON DELETE CASCADE
        )
    '; 
EXCEPTION 
    WHEN OTHERS THEN 
        IF SQLCODE != -955 THEN 
            RAISE; 
        END IF; 
END;";
$stid = oci_parse($conn, $createTable);
@oci_execute($stid);

// ---------------------- เพิ่มคอลัมน์ SORT_ORDER อัตโนมัติถ้ายังไม่มี ----------------------
$addSortOrder = "
BEGIN
    EXECUTE IMMEDIATE 'ALTER TABLE MENU_COURSE ADD SORT_ORDER NUMBER DEFAULT 0';
EXCEPTION
    WHEN OTHERS THEN
        IF SQLCODE != -1430 THEN  -- -1430 = column already exists
            RAISE;
        END IF;
END;";
$stid = oci_parse($conn, $addSortOrder);
@oci_execute($stid);

// ---------------------- REORDER COURSES (Drag & Drop) ----------------------
if (isset($_GET['action']) && $_GET['action'] == 'reorder') {
    $input = json_decode(file_get_contents('php://input'), true);
    $order = $input['order'] ?? [];

    if (!empty($order)) {
        $sql = "UPDATE MENU_COURSE SET SORT_ORDER = :ord WHERE COURSEID = :id";
        $stid = oci_parse($conn, $sql);

        foreach ($order as $i => $courseId) {
            $sortOrder = $i + 1;
            $cid = (int)$courseId;
            oci_bind_by_name($stid, ":ord", $sortOrder);
            oci_bind_by_name($stid, ":id", $cid);
            oci_execute($stid, OCI_NO_AUTO_COMMIT);
        }

        oci_commit($conn);
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit();
}

// ---------------------- ADD COURSE ----------------------
if (isset($_POST['add_course'])) {
    $sql = "INSERT INTO MENU_COURSE (COURSEID, MENUTYPEID, COURSENAME, COURSEPRICE, SORT_ORDER)
            VALUES (
                (SELECT NVL(MAX(COURSEID), 0) + 1 FROM MENU_COURSE),
                :mtid, :cname, :price,
                (SELECT NVL(MAX(SORT_ORDER), 0) + 1 FROM MENU_COURSE)
            )";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":mtid", $_POST['menutypeid']);
    oci_bind_by_name($stid, ":cname", $_POST['coursename']);
    oci_bind_by_name($stid, ":price", $_POST['courseprice']);
    oci_execute($stid);
    oci_commit($conn);
    header("Location: course_menu_manage.php?msg=course_added");
    exit();
}

// ---------------------- UPDATE COURSE ----------------------
if (isset($_POST['edit_course'])) {
    $sql = "UPDATE MENU_COURSE 
            SET COURSENAME = :cname, MENUTYPEID = :mtid, COURSEPRICE = :price
            WHERE COURSEID = :cid";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":cname", $_POST['coursename']);
    oci_bind_by_name($stid, ":mtid", $_POST['menutypeid']);
    oci_bind_by_name($stid, ":price", $_POST['courseprice']);
    oci_bind_by_name($stid, ":cid", $_POST['courseid']);
    oci_execute($stid);
    oci_commit($conn);
    header("Location: course_menu_manage.php?msg=course_updated");
    exit();
}

// ---------------------- DELETE COURSE ----------------------
if (isset($_GET['delete_course_id'])) {
    $courseid = (int)$_GET['delete_course_id'];

    $deleteSteps = [
        "DELETE FROM ORDER_ITEM WHERE ORDERID IN (SELECT ORDERID FROM TRANSACTION WHERE COURSEID = :id)",
        "DELETE FROM ORDER_PROFIT WHERE ORDERID IN (SELECT ORDERID FROM TRANSACTION WHERE COURSEID = :id)",
        "DELETE FROM ORDER_SECTION WHERE COURSEID = :id",
        "UPDATE TRANSACTION SET COURSEID = NULL WHERE COURSEID = :id",
        "DELETE FROM COURSE_MENU WHERE COURSEID = :id",
        "DELETE FROM MENU_COURSE WHERE COURSEID = :id",
    ];

    foreach ($deleteSteps as $sql) {
        $stid = oci_parse($conn, $sql);
        oci_bind_by_name($stid, ":id", $courseid);
        $r = oci_execute($stid, OCI_NO_AUTO_COMMIT);
        if (!$r) {
            $e = oci_error($stid);
            oci_rollback($conn);
            die("❌ Error: " . $e['message'] . "<br>SQL: " . $sql);
        }
    }

    oci_commit($conn);
    header("Location: course_menu_manage.php?msg=course_deleted");
    exit();
}

// ---------------------- ADD MENU TO COURSE ----------------------
if (isset($_POST['add_menu'])) {
    $checkSql = "SELECT COUNT(*) AS CNT FROM COURSE_MENU 
                 WHERE COURSEID = :cid AND MENUID = :mid";
    $checkStid = oci_parse($conn, $checkSql);
    oci_bind_by_name($checkStid, ":cid", $_POST['courseid']);
    oci_bind_by_name($checkStid, ":mid", $_POST['menuid']);
    oci_execute($checkStid);
    $checkData = oci_fetch_assoc($checkStid);

    if ($checkData['CNT'] == 0) {
        $sql = "INSERT INTO COURSE_MENU (COURSEID, MENUID, QUANTITY) 
                VALUES (:cid, :mid, :qty)";
        $stid = oci_parse($conn, $sql);
        oci_bind_by_name($stid, ":cid", $_POST['courseid']);
        oci_bind_by_name($stid, ":mid", $_POST['menuid']);
        oci_bind_by_name($stid, ":qty", $_POST['quantity']);
        oci_execute($stid);
        oci_commit($conn);
        header("Location: course_menu_manage.php?course=" . $_POST['courseid'] . "&msg=added");
    } else {
        header("Location: course_menu_manage.php?course=" . $_POST['courseid'] . "&msg=exists");
    }
    exit();
}

// ---------------------- UPDATE QUANTITY ----------------------
if (isset($_POST['edit_quantity'])) {
    $sql = "UPDATE COURSE_MENU SET QUANTITY = :qty 
            WHERE COURSEID = :cid AND MENUID = :mid";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":qty", $_POST['quantity']);
    oci_bind_by_name($stid, ":cid", $_POST['courseid']);
    oci_bind_by_name($stid, ":mid", $_POST['menuid']);
    oci_execute($stid);
    oci_commit($conn);
    header("Location: course_menu_manage.php?course=" . $_POST['courseid'] . "&msg=updated");
    exit();
}

// ---------------------- DELETE MENU FROM COURSE ----------------------
if (isset($_GET['delete_course']) && isset($_GET['delete_menu'])) {
    $sql = "DELETE FROM COURSE_MENU WHERE COURSEID = :cid AND MENUID = :mid";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":cid", $_GET['delete_course']);
    oci_bind_by_name($stid, ":mid", $_GET['delete_menu']);
    oci_execute($stid);
    oci_commit($conn);
    header("Location: course_menu_manage.php?course=" . $_GET['delete_course'] . "&msg=deleted");
    exit();
}

// ---------------------- ดึงข้อมูล ----------------------
$sqlType = "SELECT * FROM MENU_TYPE ORDER BY MENUTYPEID";
$stidType = oci_parse($conn, $sqlType);
oci_execute($stidType);

// แก้ไข: ใช้ NVL(mc.SORT_ORDER, mc.COURSEID) เพื่อ fallback กรณีคอลัมน์เป็น NULL
$sqlCourse = "SELECT mc.*, mt.TYPENAME 
              FROM MENU_COURSE mc 
              LEFT JOIN MENU_TYPE mt ON mc.MENUTYPEID = mt.MENUTYPEID 
              ORDER BY NVL(mc.SORT_ORDER, mc.COURSEID), mc.COURSEID";
$stidCourse = oci_parse($conn, $sqlCourse);
oci_execute($stidCourse);

$sqlMenu = "SELECT * FROM MENU ORDER BY MENUID";
$stidMenu = oci_parse($conn, $sqlMenu);
oci_execute($stidMenu);

$editCourse = null;
if (isset($_GET['edit_course'])) {
    $sql = "SELECT * FROM MENU_COURSE WHERE COURSEID = :id";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":id", $_GET['edit_course']);
    oci_execute($stid);
    $editCourse = oci_fetch_assoc($stid);
}

$selectedCourse = $_GET['course'] ?? null;
$courseMenus    = [];
$courseInfo     = null;

if ($selectedCourse) {
    $sql = "SELECT mc.*, mt.TYPENAME 
            FROM MENU_COURSE mc 
            LEFT JOIN MENU_TYPE mt ON mc.MENUTYPEID = mt.MENUTYPEID 
            WHERE mc.COURSEID = :cid";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":cid", $selectedCourse);
    oci_execute($stid);
    $courseInfo = oci_fetch_assoc($stid);

    $sql2 = "SELECT cm.*, m.MENUNAME 
             FROM COURSE_MENU cm 
             JOIN MENU m ON cm.MENUID = m.MENUID 
             WHERE cm.COURSEID = :cid 
             ORDER BY cm.MENUID";
    $stid2 = oci_parse($conn, $sql2);
    oci_bind_by_name($stid2, ":cid", $selectedCourse);
    oci_execute($stid2);

    while ($row = oci_fetch_assoc($stid2)) {
        $sql3 = "SELECT r.QTYUSED, i.INGREDIENTNAME, i.UNIT 
                 FROM RECIPE r 
                 JOIN INGREDIENT i ON r.INGREDIENTID = i.INGREDIENTID 
                 WHERE r.MENUID = :mid";
        $stid3 = oci_parse($conn, $sql3);
        oci_bind_by_name($stid3, ":mid", $row['MENUID']);
        oci_execute($stid3);

        $ingredients = [];
        while ($ing = oci_fetch_assoc($stid3)) {
            $totalQty      = $ing['QTYUSED'] * $row['QUANTITY'];
            $ingredients[] = $ing['INGREDIENTNAME'] . ' (' . number_format($totalQty, 2) . ' ' . $ing['UNIT'] . ')';
        }
        $row['INGREDIENTS'] = $ingredients;
        $courseMenus[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการ Course และเมนู</title>
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
            box-sizing: border-box;
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

        #sidebar.active { left: 0; }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h3 { font-size: 24px; margin-bottom: 5px; color: #fff; margin-top: 0; }
        .sidebar-header p  { font-size: 12px; color: rgba(255,255,255,0.7); margin: 0; }

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
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }

        .overlay.active { display: block; }

        .container {
            margin-top: 90px;
            margin-left: 30px;
            margin-right: 30px;
            padding-bottom: 50px;
        }

        .message { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .success  { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }

        .two-column {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }

        .left-panel, .right-panel {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        h3 { color: #333; margin-top: 0; border-bottom: 2px solid #3498db; padding-bottom: 10px; }

        .course-form { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }

        .form-group { margin-bottom: 15px; }

        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus { outline: none; border-color: #3498db; }

        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.2s; margin-right: 5px; }
        .btn-primary   { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-success   { background: #27ae60; color: white; }
        .btn-success:hover { background: #229954; }
        .btn-secondary { background: #95a5a6; color: white; }
        .btn-secondary:hover { background: #7f8c8d; }

        .drag-hint { font-size: 12px; color: #999; margin-bottom: 8px; text-align: center; }

        .course-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 2px solid #ddd;
            transition: all 0.2s;
            position: relative;
            user-select: none;
        }

        .course-item:hover  { border-color: #3498db; box-shadow: 0 2px 8px rgba(52,152,219,0.2); }
        .course-item.active { border-color: #3498db; background: #e3f2fd; }

        .drag-handle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            background: #e0e0e0;
            border-radius: 6px;
            cursor: grab;
            color: #888;
            font-size: 16px;
            margin-right: 8px;
            flex-shrink: 0;
            transition: background 0.2s, color 0.2s;
        }

        .drag-handle:hover  { background: #667eea; color: white; }
        .drag-handle:active { cursor: grabbing; }

        .course-item.dragging {
            opacity: 0.35;
            border: 2px dashed #667eea;
            background: #f0f0ff;
            transform: scale(0.98);
        }

        .course-item.drag-over { border-color: #27ae60; background: #eafaf1; box-shadow: 0 0 0 3px rgba(39,174,96,0.3); }

        .course-name  { font-weight: bold; font-size: 16px; color: #2c3e50; }
        .course-price { color: #27ae60; font-weight: bold; font-size: 16px; }
        .course-type  { font-size: 12px; color: #7f8c8d; margin-top: 5px; }

        .course-header { display: flex; align-items: center; }
        .course-info   { flex: 1; }

        .course-actions { display: flex; gap: 5px; margin-top: 10px; }

        .btn-small  { padding: 5px 10px; font-size: 12px; border-radius: 4px; border: none; cursor: pointer; transition: all 0.2s; }
        .btn-edit   { background: #f39c12; color: white; }
        .btn-edit:hover   { background: #e67e22; }
        .btn-delete { background: #e74c3c; color: white; }
        .btn-delete:hover { background: #c0392b; }
        .btn-view   { background: #3498db; color: white; flex: 1; }
        .btn-view:hover   { background: #2980b9; }

        #toast {
            position: fixed;
            bottom: 30px; right: 30px;
            background: #2ecc71;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            display: none;
            z-index: 9999;
            transition: opacity 0.3s;
        }

        .summary-box { background: #fff3cd; border: 2px solid #ffc107; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .summary-box h3 { margin-top: 0; border: none; }
        .highlight { color: #e74c3c; font-weight: bold; }

        .form-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }

        .form-row { display: grid; grid-template-columns: 2fr 1fr auto; gap: 15px; align-items: end; }

        table { width: 100%; background: white; border-collapse: collapse; margin-top: 20px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 12px; text-align: left; }
        th { background: #3498db; color: white; font-weight: bold; }
        tr:hover { background: #f5f5f5; }

        .ingredient-list { font-size: 13px; color: #7f8c8d; margin-top: 5px; }
        .ingredient-item { display: inline-block; background: #ecf0f1; padding: 3px 8px; border-radius: 4px; margin: 2px; }
    </style>
</head>
<body>

<div class="top-bar">
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
    <button class="back-btn" onclick="window.location.href='homepage.php'">←</button>
    <h2>🍱 Course Management</h2>
</div>

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

<div id="toast">✅ บันทึกลำดับเรียบร้อย</div>

<div class="container">

    <?php if (isset($_GET['msg'])): ?>
        <div class="message success">
            <?php
            if     ($_GET['msg'] == 'course_added')   echo '✅ เพิ่ม Course เรียบร้อย';
            elseif ($_GET['msg'] == 'course_updated') echo '✅ แก้ไข Course เรียบร้อย';
            elseif ($_GET['msg'] == 'course_deleted') echo '✅ ลบ Course เรียบร้อย';
            elseif ($_GET['msg'] == 'added')          echo '✅ เพิ่มเมนูเรียบร้อย';
            elseif ($_GET['msg'] == 'updated')        echo '✅ แก้ไขจำนวนเรียบร้อย';
            elseif ($_GET['msg'] == 'deleted')        echo '✅ ลบเมนูเรียบร้อย';
            elseif ($_GET['msg'] == 'exists')         echo '⚠️ เมนูนี้มีอยู่ใน Course แล้ว';
            ?>
        </div>
    <?php endif; ?>

    <div class="two-column">

        <!-- ===== LEFT PANEL ===== -->
        <div class="left-panel">
            <h3>📋 จัดการ Course</h3>

            <div class="course-form">
                <form method="POST">
                    <?php if ($editCourse): ?>
                        <input type="hidden" name="courseid" value="<?= $editCourse['COURSEID'] ?>">
                        <h4 style="margin-top:0">✏️ แก้ไข Course</h4>
                    <?php else: ?>
                        <h4 style="margin-top:0">➕ เพิ่ม Course</h4>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>ชื่อ Course:</label>
                        <input type="text" name="coursename"
                               value="<?= $editCourse ? htmlspecialchars($editCourse['COURSENAME']) : '' ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label>ประเภท:</label>
                        <select name="menutypeid" required>
                            <option value="">-- เลือกประเภท --</option>
                            <?php
                            oci_execute($stidType);
                            while ($t = oci_fetch_assoc($stidType)):
                                $sel = ($editCourse && $editCourse['MENUTYPEID'] == $t['MENUTYPEID']) ? 'selected' : '';
                            ?>
                                <option value="<?= $t['MENUTYPEID'] ?>" <?= $sel ?>>
                                    <?= htmlspecialchars($t['TYPENAME']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>ราคา (฿):</label>
                        <input type="number" name="courseprice" step="0.01"
                               value="<?= $editCourse ? $editCourse['COURSEPRICE'] : '' ?>"
                               required>
                    </div>

                    <button type="submit" name="<?= $editCourse ? 'edit_course' : 'add_course' ?>"
                            class="btn btn-primary">
                        <?= $editCourse ? '💾 บันทึก' : '➕ เพิ่ม' ?>
                    </button>

                    <?php if ($editCourse): ?>
                        <a href="course_menu_manage.php">
                            <button type="button" class="btn btn-secondary">❌ ยกเลิก</button>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="course-list">
                <h4>รายการ Course</h4>
                <div class="drag-hint">☰ ลากเพื่อเรียงลำดับ</div>

                <div id="sortable-list">
                <?php
                oci_execute($stidCourse);
                $cnt = 0;
                while ($c = oci_fetch_assoc($stidCourse)):
                    $cnt++;
                    $act = ($selectedCourse == $c['COURSEID']) ? 'active' : '';
                ?>
                    <div class="course-item <?= $act ?>" data-id="<?= $c['COURSEID'] ?>">

                        <div class="course-header">
                            <span class="drag-handle" title="ลากเพื่อเรียง">⠿</span>
                            <div class="course-info">
                                <div style="display:flex; justify-content:space-between; align-items:center">
                                    <div>
                                        <div class="course-name"><?= htmlspecialchars($c['COURSENAME']) ?></div>
                                        <div class="course-type"><?= htmlspecialchars($c['TYPENAME']) ?></div>
                                    </div>
                                    <div class="course-price"><?= number_format($c['COURSEPRICE'], 2) ?> ฿</div>
                                </div>
                            </div>
                        </div>

                        <div class="course-actions">
                            <a href="?course=<?= $c['COURSEID'] ?>">
                                <button class="btn-small btn-view">👁️ ดูเมนู</button>
                            </a>
                            <a href="?edit_course=<?= $c['COURSEID'] ?>">
                                <button class="btn-small btn-edit">✏️ แก้ไข</button>
                            </a>
                            <a href="?delete_course_id=<?= $c['COURSEID'] ?>"
                               onclick="return confirm('ลบ Course นี้? จะลบเมนูทั้งหมดใน Course ด้วย')">
                                <button class="btn-small btn-delete">🗑️ ลบ</button>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
                </div>

                <?php if ($cnt == 0): ?>
                    <div style="text-align:center; padding:20px; color:#999">
                        <em>ยังไม่มี Course</em>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== RIGHT PANEL ===== -->
        <div class="right-panel">
            <?php if ($selectedCourse && $courseInfo): ?>

                <div class="summary-box">
                    <h3 style="margin-top:0; border:none">
                        📌 <?= htmlspecialchars($courseInfo['COURSENAME']) ?>
                    </h3>
                    <div><strong>ประเภท:</strong> <?= htmlspecialchars($courseInfo['TYPENAME']) ?></div>
                    <div><strong>ราคา:</strong>
                        <span class="highlight"><?= number_format($courseInfo['COURSEPRICE'], 2) ?> ฿</span>
                    </div>
                    <div><strong>จำนวนเมนู:</strong>
                        <span class="highlight"><?= count($courseMenus) ?> เมนู</span>
                    </div>
                    <div style="font-size:13px; color:#856404; margin-top:10px">
                        💡 ลูกค้าสั่ง Course นี้จะหักวัตถุดิบทันที (จำนวน × QUANTITY)
                    </div>
                </div>

                <div class="form-section">
                    <h3>➕ เพิ่มเมนู</h3>
                    <form method="POST">
                        <input type="hidden" name="courseid" value="<?= $selectedCourse ?>">
                        <div class="form-row">
                            <div>
                                <label>เลือกเมนู:</label>
                                <select name="menuid" required>
                                    <option value="">-- เลือกเมนู --</option>
                                    <?php
                                    oci_execute($stidMenu);
                                    while ($m = oci_fetch_assoc($stidMenu)):
                                    ?>
                                        <option value="<?= $m['MENUID'] ?>">
                                            <?= htmlspecialchars($m['MENUNAME']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div>
                                <label>จำนวน:</label>
                                <input type="number" name="quantity" min="1" value="1" required>
                            </div>
                            <button type="submit" name="add_menu" class="btn btn-success">เพิ่ม</button>
                        </div>
                    </form>
                </div>

                <h3>🍣 เมนูใน Course</h3>

                <?php if (count($courseMenus) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th style="width:50px">#</th>
                                <th>เมนู</th>
                                <th style="width:100px">จำนวน</th>
                                <th>วัตถุดิบที่หัก</th>
                                <th style="width:80px">ลบ</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $no = 1; foreach ($courseMenus as $menu): ?>
                            <tr>
                                <td style="text-align:center"><?= $no++ ?></td>
                                <td><strong><?= htmlspecialchars($menu['MENUNAME']) ?></strong></td>
                                <td style="text-align:center">
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="courseid" value="<?= $selectedCourse ?>">
                                        <input type="hidden" name="menuid"   value="<?= $menu['MENUID'] ?>">
                                        <input type="number" name="quantity" value="<?= $menu['QUANTITY'] ?>"
                                               min="1" style="width:60px; padding:5px"
                                               onchange="this.form.submit()">
                                        <input type="hidden" name="edit_quantity" value="1">
                                    </form>
                                </td>
                                <td>
                                    <div class="ingredient-list">
                                        <?php if (count($menu['INGREDIENTS']) > 0): ?>
                                            <?php foreach ($menu['INGREDIENTS'] as $ing): ?>
                                                <span class="ingredient-item"><?= htmlspecialchars($ing) ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <em style="color:#999">ไม่มีสูตร</em>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="text-align:center">
                                    <a href="?course=<?= $selectedCourse ?>&delete_course=<?= $selectedCourse ?>&delete_menu=<?= $menu['MENUID'] ?>"
                                       onclick="return confirm('ลบเมนูนี้ออกจาก Course?')">
                                        <button class="btn-small btn-delete">🗑️</button>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align:center; padding:40px; color:#999">
                        <em>ยังไม่มีเมนูใน Course นี้</em>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div style="text-align:center; padding:60px; color:#999">
                    <h3>👆 กรุณาเลือก Course ด้านซ้าย</h3>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
function toggleMenu() {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('overlay').classList.toggle('active');
}

function showToast(msg, success = true) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.background = success ? '#2ecc71' : '#e74c3c';
    t.style.display = 'block';
    t.style.opacity = '1';
    setTimeout(() => {
        t.style.opacity = '0';
        setTimeout(() => t.style.display = 'none', 300);
    }, 2000);
}

(function () {
    const list = document.getElementById('sortable-list');
    if (!list) return;

    let dragEl = null;
    let placeholder = null;

    function createPlaceholder(height) {
        const ph = document.createElement('div');
        ph.style.height = height + 'px';
        ph.style.background = 'rgba(102,126,234,0.1)';
        ph.style.border = '2px dashed #667eea';
        ph.style.borderRadius = '8px';
        ph.style.margin = '0 0 10px 0';
        ph.style.transition = 'height 0.15s';
        ph.id = 'drag-placeholder';
        return ph;
    }

    function getItems() {
        return [...list.querySelectorAll('.course-item')];
    }

    list.querySelectorAll('.course-item').forEach(item => {
        const handle = item.querySelector('.drag-handle');
        if (!handle) return;

        handle.addEventListener('mousedown', () => {
            item.draggable = true;
        });

        item.addEventListener('dragstart', e => {
            dragEl = item;
            placeholder = createPlaceholder(item.offsetHeight);
            setTimeout(() => {
                item.classList.add('dragging');
                item.draggable = false;
            }, 0);
            e.dataTransfer.effectAllowed = 'move';
        });

        item.addEventListener('dragend', () => {
            item.classList.remove('dragging');
            if (placeholder && placeholder.parentNode) {
                placeholder.parentNode.insertBefore(dragEl, placeholder);
                placeholder.remove();
            }
            placeholder = null;

            const order = getItems().map(el => el.dataset.id);
            fetch('course_menu_manage.php?action=reorder', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order })
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) showToast('✅ บันทึกลำดับเรียบร้อย');
                else showToast('❌ บันทึกล้มเหลว', false);
            })
            .catch(() => showToast('❌ เกิดข้อผิดพลาด', false));

            dragEl = null;
        });

        item.addEventListener('dragover', e => {
            e.preventDefault();
            if (!dragEl || item === dragEl || item.id === 'drag-placeholder') return;

            const rect = item.getBoundingClientRect();
            const mid  = rect.top + rect.height / 2;

            if (!placeholder) placeholder = createPlaceholder(dragEl.offsetHeight);

            if (e.clientY < mid) {
                list.insertBefore(placeholder, item);
            } else {
                list.insertBefore(placeholder, item.nextSibling);
            }
        });
    });

    list.addEventListener('dragover', e => e.preventDefault());
    list.addEventListener('drop',     e => e.preventDefault());
})();
</script>
<script src="auth_guard.js"></script>
</body>
</html>