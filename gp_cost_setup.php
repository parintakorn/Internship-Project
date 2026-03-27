<?php
include 'connect.php';

// --- สร้างตาราง/คอลัมน์อัตโนมัติ ---
$createTables = "
BEGIN
    -- ORDER_PROFIT
    BEGIN
        EXECUTE IMMEDIATE '
            CREATE TABLE ORDER_PROFIT (
                ORDERID NUMBER PRIMARY KEY,
                PROFITBEFOREGP NUMBER(10,2) DEFAULT 0,
                PROFITAFTERGP NUMBER(10,2) DEFAULT 0,
                PROFITBUFFETBEFOREGP NUMBER(10,2) DEFAULT 0,
                PROFITBUFFETAFTERGP NUMBER(10,2) DEFAULT 0,
                CONSTRAINT FK_ORDER_PROFIT_ORDER FOREIGN KEY (ORDERID)
                    REFERENCES TRANSACTION(ORDERID) ON DELETE CASCADE
            )
        ';
    EXCEPTION
        WHEN OTHERS THEN
            IF SQLCODE != -955 THEN RAISE; END IF;
    END;

    -- MENU_TYPE_PRICE
    BEGIN
        EXECUTE IMMEDIATE '
            CREATE TABLE MENU_TYPE_PRICE (
                MENUTYPEID NUMBER PRIMARY KEY,
                GP NUMBER(5,2) DEFAULT 0,
                CONSTRAINT FK_MENU_TYPE_PRICE FOREIGN KEY (MENUTYPEID)
                    REFERENCES MENU_TYPE(MENUTYPEID) ON DELETE CASCADE
            )
        ';
    EXCEPTION
        WHEN OTHERS THEN
            IF SQLCODE != -955 THEN RAISE; END IF;
    END;

    -- เพิ่มคอลัมน์ COST ใน INGREDIENT (ต้นทุนต่อชิ้น)
    BEGIN
        EXECUTE IMMEDIATE 'ALTER TABLE INGREDIENT ADD COST NUMBER(10,2) DEFAULT 0';
    EXCEPTION
        WHEN OTHERS THEN
            IF SQLCODE != -1430 THEN RAISE; END IF;
    END;

    -- เพิ่มคอลัมน์ PURCHASE_COST ใน INGREDIENT (ต้นทุนที่ซื้อมาทั้งหมด)
    BEGIN
        EXECUTE IMMEDIATE 'ALTER TABLE INGREDIENT ADD PURCHASE_COST NUMBER(10,2) DEFAULT 0';
    EXCEPTION
        WHEN OTHERS THEN
            IF SQLCODE != -1430 THEN RAISE; END IF;
    END;

    -- เพิ่มคอลัมน์ COST ใน RECIPE (ต้นทุนเฉพาะของแต่ละเมนู) ***NEW***
    BEGIN
        EXECUTE IMMEDIATE 'ALTER TABLE RECIPE ADD COST NUMBER(10,2) DEFAULT 0';
    EXCEPTION
        WHEN OTHERS THEN
            IF SQLCODE != -1430 THEN RAISE; END IF;
    END;

    -- เพิ่มคอลัมน์ COST ใน MENU_COURSE (สำหรับต้นทุนคอร์ส)
    BEGIN
        EXECUTE IMMEDIATE 'ALTER TABLE MENU_COURSE ADD COST NUMBER(10,2) DEFAULT 0';
    EXCEPTION
        WHEN OTHERS THEN
            IF SQLCODE != -1430 THEN RAISE; END IF;
    END;
END;
";
$stid = oci_parse($conn, $createTables);
@oci_execute($stid);

// --- เพิ่มข้อมูล GP เริ่มต้นถ้ายังไม่มี ---
$sqlCheckGP = "SELECT COUNT(*) as CNT FROM MENU_TYPE_PRICE";
$stidCheck = oci_parse($conn, $sqlCheckGP);
oci_execute($stidCheck);
$checkRow = oci_fetch_assoc($stidCheck);

if ($checkRow['CNT'] == 0) {
    $sqlTypes = "SELECT MENUTYPEID FROM MENU_TYPE";
    $stidTypes = oci_parse($conn, $sqlTypes);
    oci_execute($stidTypes);

    $priceId = 1;
    while ($type = oci_fetch_assoc($stidTypes)) {
        $gp = ($type['MENUTYPEID'] == 1) ? 10 : 15; // A la carte 10%, Omakase 15%
        $sqlInsert = "INSERT INTO MENU_TYPE_PRICE (MENUTYPEID, GP) VALUES (:id, :gp)";
        $stidInsert = oci_parse($conn, $sqlInsert);
        oci_bind_by_name($stidInsert, ':id', $type['MENUTYPEID']);
        oci_bind_by_name($stidInsert, ':gp', $gp);
        oci_execute($stidInsert);
    }
    oci_commit($conn);
}

// --- บันทึก GP ---
if (isset($_POST['save_gp'])) {
    $menutypeid = $_POST['menutypeid'];
    $gp = $_POST['gp'];

    $sqlUpdate = "UPDATE MENU_TYPE_PRICE SET GP = :gp WHERE MENUTYPEID = :id";
    $stid = oci_parse($conn, $sqlUpdate);
    oci_bind_by_name($stid, ':gp', $gp);
    oci_bind_by_name($stid, ':id', $menutypeid);
    oci_execute($stid);
    oci_commit($conn);

    header("Location: gp_cost_setup.php?msg=gp_updated");
    exit();
}

// --- บันทึก Cost วัตถุดิบ (ต้นทุนต่อชิ้นเฉพาะเมนู) ---
if (isset($_POST['save_cost'])) {
    $menuid = $_POST['menuid'];
    $ingredientids = $_POST['ingredientid'];
    $costs = $_POST['cost'];

    for ($i = 0; $i < count($ingredientids); $i++) {
        $sqlUpdate = "UPDATE RECIPE SET COST = :cost 
                      WHERE MENUID = :mid AND INGREDIENTID = :iid";
        $stid = oci_parse($conn, $sqlUpdate);
        oci_bind_by_name($stid, ':cost', $costs[$i]);
        oci_bind_by_name($stid, ':mid', $menuid);
        oci_bind_by_name($stid, ':iid', $ingredientids[$i]);
        oci_execute($stid);
    }
    oci_commit($conn);

    header("Location: gp_cost_setup.php?msg=cost_updated");
    exit();
}

// --- บันทึก Purchase Cost (ต้นทุนที่ซื้อมา) ---
if (isset($_POST['save_purchase_cost'])) {
    $ingredientid = $_POST['ingredientid'];
    $purchase_cost = $_POST['purchase_cost'];

    $sqlUpdate = "UPDATE INGREDIENT SET PURCHASE_COST = :pcost WHERE INGREDIENTID = :id";
    $stid = oci_parse($conn, $sqlUpdate);
    oci_bind_by_name($stid, ':pcost', $purchase_cost);
    oci_bind_by_name($stid, ':id', $ingredientid);
    oci_execute($stid);
    oci_commit($conn);

    header("Location: gp_cost_setup.php?msg=purchase_cost_updated");
    exit();
}

// --- บันทึก Cost คอร์ส ---
if (isset($_POST['save_course_cost'])) {
    $courseid = $_POST['courseid'];
    $cost = $_POST['course_cost'];

    $sqlUpdate = "UPDATE MENU_COURSE SET COST = :cost WHERE COURSEID = :id";
    $stid = oci_parse($conn, $sqlUpdate);
    oci_bind_by_name($stid, ':cost', $cost);
    oci_bind_by_name($stid, ':id', $courseid);
    oci_execute($stid);
    oci_commit($conn);

    header("Location: gp_cost_setup.php?msg=course_cost_updated");
    exit();
}

// --- ดึงข้อมูล Menu Types พร้อม GP ---
$sqlTypes = "SELECT mt.MENUTYPEID, mt.TYPENAME, mtp.GP
             FROM MENU_TYPE mt
             LEFT JOIN MENU_TYPE_PRICE mtp ON mt.MENUTYPEID = mtp.MENUTYPEID
             ORDER BY mt.MENUTYPEID";
$stidTypes = oci_parse($conn, $sqlTypes);
oci_execute($stidTypes);

// --- ดึงข้อมูล Ingredients พร้อม Cost ---
$sqlIngredients = "SELECT INGREDIENTID, INGREDIENTNAME, COST, UNIT
                   FROM INGREDIENT
                   ORDER BY INGREDIENTID";
$stidIngredients = oci_parse($conn, $sqlIngredients);
oci_execute($stidIngredients);

// --- ดึงข้อมูล Ingredients สำหรับ Purchase Cost ---
$sqlPurchase = "SELECT INGREDIENTID, INGREDIENTNAME, PURCHASE_COST, UNIT
                FROM INGREDIENT
                ORDER BY INGREDIENTID";
$stidPurchase = oci_parse($conn, $sqlPurchase);
oci_execute($stidPurchase);

// --- ดึงข้อมูลคอร์สพร้อม Cost ---
$sqlCourses = "SELECT COURSEID, COURSENAME, COST FROM MENU_COURSE ORDER BY COURSEID";
$stidCourses = oci_parse($conn, $sqlCourses);
oci_execute($stidCourses);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>GP & Cost Setup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #fafafa;
        }

        .top-bar {
            width: 100%;
            background-color: #f5f5f5;
            padding: 12px 18px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 20;
        }

        .back-btn {
            font-size: 22px;
            margin-right: 15px;
            cursor: pointer;
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid #ccc;
            background: white;
        }

        .container {
            max-width: 1200px;
            margin: 90px auto 40px;
            padding: 30px;
        }

        .success-msg {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        h2 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
            margin-top: 0;
        }

        h3 {
            color: #34495e;
            margin-top: 0;
        }

        .info-box {
            background: #e8f4f8;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table, th, td {
            border: 1px solid #ddd;
        }

        th, td {
            padding: 12px;
            text-align: left;
        }

        th {
            background: #3498db;
            color: white;
            font-weight: bold;
        }

        tr:hover {
            background: #f5f5f5;
        }

        input[type="number"] {
            width: 100px;
            padding: 8px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        input[type="number"]:focus {
            outline: none;
            border-color: #3498db;
        }

        .btn-save {
            background: #27ae60;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }

        .btn-save:hover {
            background: #229954;
        }

        .type-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .type-alacarte {
            background: #e74c3c;
            color: white;
        }

        .type-omakase {
            background: #f39c12;
            color: white;
        }

        .cost-info {
            font-size: 13px;
            color: #7f8c8d;
            margin-top: 5px;
        }

        .total-row {
            background: #ffe6e6 !important;
            font-weight: bold;
            font-size: 16px;
        }

        .total-row td {
            padding: 15px 12px;
        }

        .highlight-total {
            color: #e74c3c;
            font-size: 18px;
        }
    </style>
</head>
<body>

<div class="top-bar">
    <button class="back-btn" onclick="window.location.href='homepage.php'">←</button>
    <h2 style="margin: 0;">⚙️ ตั้งค่า GP และต้นทุน</h2>
</div>

<div class="container">

    <?php if (isset($_GET['msg'])): ?>
        <div class="success-msg">
            <?php
            if ($_GET['msg'] == 'gp_updated') echo '✅ อัพเดท GP เรียบร้อยแล้ว';
            elseif ($_GET['msg'] == 'cost_updated') echo '✅ อัพเดทต้นทุนต่อชิ้นเรียบร้อยแล้ว';
            elseif ($_GET['msg'] == 'purchase_cost_updated') echo '✅ อัพเดทต้นทุนที่ซื้อมาเรียบร้อยแล้ว';
            elseif ($_GET['msg'] == 'course_cost_updated') echo '✅ อัพเดทต้นทุนคอร์สเรียบร้อยแล้ว';
            ?>
        </div>
    <?php endif; ?>

    <!-- GP Setting Section -->
    <div class="section">
        <h2>📊 ตั้งค่า GP (Gross Profit) แต่ละประเภท</h2>

        <div class="info-box">
            💡 <strong>GP (Gross Profit)</strong> คือเปอร์เซ็นต์ที่หักจากยอดขายเพื่อคำนวณกำไรสุทธิ<br>
            <strong>ตัวอย่าง:</strong> ยอดขาย 100 บาท, ต้นทุน 60 บาท, กำไรก่อน GP = 40 บาท<br>
            ถ้า GP 10% → หัก 10 บาท → กำไรหลัง GP = 30 บาท
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 100px">Type ID</th>
                    <th>ประเภท</th>
                    <th style="width: 200px">GP (%)</th>
                    <th style="width: 150px">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($type = oci_fetch_assoc($stidTypes)): ?>
                <tr>
                    <td><strong><?= $type['MENUTYPEID'] ?></strong></td>
                    <td>
                        <span class="type-badge <?= $type['MENUTYPEID'] == 1 ? 'type-alacarte' : 'type-omakase' ?>">
                            <?= htmlspecialchars($type['TYPENAME']) ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" style="display: inline-flex; align-items: center; gap: 10px;">
                            <input type="hidden" name="menutypeid" value="<?= $type['MENUTYPEID'] ?>">
                            <input type="number" name="gp" value="<?= $type['GP'] ?? 0 ?>"
                                   step="0.01" min="0" max="100" required> %
                            <button type="submit" name="save_gp" class="btn-save">บันทึก</button>
                        </form>
                    </td>
                    <td>
                        <div class="cost-info">
                            หักจากยอดขาย <?= number_format($type['GP'] ?? 0, 2) ?>%
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Cost Setting Section -->
    <div class="section">
        <h2>💵 ตั้งค่าต้นทุนวัตถุดิบต่อชิ้น</h2>

        <div class="info-box">
            💡 <strong>ต้นทุนวัตถุดิบ</strong> คือราคาของวัตถุดิบ 1 ชิ้น/1 หน่วย (ใช้คำนวณเมนู)<br>
            <strong>ตัวอย่าง:</strong> ปลาแซลมอน 1 ชิ้น = 50 บาท → ใช้ 2 ชิ้น = ต้นทุน 100 บาท
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 100px">Menu ID</th>
                    <th>Menu Name</th>
                    <th>วัตถุดิบ</th>
                    <th style="width: 100px">จำนวนใช้</th>
                    <th style="width: 100px">หน่วย</th>
                    <th style="width: 200px">ต้นทุนต่อชิ้น (฿)</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // ดึงข้อมูล Recipe พร้อมต้นทุนจาก RECIPE.COST
            $sqlRecipe = "SELECT r.MENUID, r.INGREDIENTID, r.QTYUSED, r.COST,
                                 m.MENUNAME, i.INGREDIENTNAME, i.UNIT
                          FROM RECIPE r
                          JOIN MENU m ON r.MENUID = m.MENUID
                          JOIN INGREDIENT i ON r.INGREDIENTID = i.INGREDIENTID
                          ORDER BY r.MENUID, r.INGREDIENTID";
            $stidRecipe = oci_parse($conn, $sqlRecipe);
            oci_execute($stidRecipe);
            
            $currentMenu = null;
            $menuTotal = 0;
            $recipeCount = 0;
            $grandTotal = 0;
            $formOpen = false;
            
            while ($recipe = oci_fetch_assoc($stidRecipe)):
                $recipeCount++;
                $cost = $recipe['COST'] ?? 0;
                $qty = $recipe['QTYUSED'] ?? 0;
                
                // แสดง header เมนูใหม่
                if ($currentMenu != $recipe['MENUID']):
                    // ปิด form เมนูก่อนหน้า
                    if ($formOpen):
            ?>
                        <tr style="background: #fff;">
                            <td colspan="6" style="text-align: right; padding: 12px;">
                                <button type="submit" name="save_cost" class="btn-save" style="font-size: 14px; padding: 10px 20px;">
                                    💾 บันทึกเมนูนี้
                                </button>
                            </td>
                        </tr>
                    </form>
                        <tr style="background: #e8f8f5; font-weight: bold;">
                            <td colspan="5" style="text-align: right; padding-right: 20px;">
                                💰 ต้นทุนรวมของเมนู:
                            </td>
                            <td style="color: #27ae60; font-size: 15px; padding: 12px;">
                                <?= number_format($menuTotal, 2) ?> ฿
                            </td>
                        </tr>
            <?php
                        $menuTotal = 0;
                    endif;
                    
                    $currentMenu = $recipe['MENUID'];
            ?>
                    <tr style="background: #e8f4f8; font-weight: bold;">
                        <td colspan="6" style="text-align: left; padding: 15px;">
                            🍽️ <strong><?= htmlspecialchars($recipe['MENUNAME']) ?></strong> (Menu ID: <?= $recipe['MENUID'] ?>)
                        </td>
                    </tr>
                    <form method="POST">
                        <input type="hidden" name="menuid" value="<?= $recipe['MENUID'] ?>">
            <?php
                    $formOpen = true;
                endif;
                
                // บวกเฉพาะราคาที่กรอก ไม่คูณกับจำนวนใช้
                $menuTotal += $cost;
                $grandTotal += $cost;
            ?>
                <tr>
                    <td><?= $recipe['MENUID'] ?></td>
                    <td style="text-align: left;"><?= htmlspecialchars($recipe['MENUNAME']) ?></td>
                    <td style="text-align: left;"><?= htmlspecialchars($recipe['INGREDIENTNAME']) ?></td>
                    <td><strong><?= number_format($qty, 2) ?></strong></td>
                    <td><?= htmlspecialchars($recipe['UNIT']) ?></td>
                    <td>
                        <input type="hidden" name="ingredientid[]" value="<?= $recipe['INGREDIENTID'] ?>">
                        <input type="number" name="cost[]" value="<?= $cost ?>"
                               step="0.01" min="0" required style="width: 100px; padding: 8px; border: 2px solid #ddd; border-radius: 4px;"> ฿
                    </td>
                </tr>
            <?php endwhile; ?>
            
            <?php if ($formOpen): ?>
                <!-- ปิด form เมนูสุดท้าย -->
                <tr style="background: #fff;">
                    <td colspan="6" style="text-align: right; padding: 12px;">
                        <button type="submit" name="save_cost" class="btn-save" style="font-size: 14px; padding: 10px 20px;">
                            💾 บันทึกเมนูนี้
                        </button>
                    </td>
                </tr>
            </form>
                <tr style="background: #e8f8f5; font-weight: bold;">
                    <td colspan="5" style="text-align: right; padding-right: 20px;">
                        💰 ต้นทุนรวมของเมนู:
                    </td>
                    <td style="color: #27ae60; font-size: 15px; padding: 12px;">
                        <?= number_format($menuTotal, 2) ?> ฿
                    </td>
                </tr>
            <?php endif; ?>

            <?php if ($recipeCount == 0): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 30px; color: #999;">
                        <em>ไม่มีข้อมูล Recipe กรุณาสร้าง Recipe ก่อน</em>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Purchase Cost Setting Section (NEW) -->
    <div class="section">
        <h2>🛒 ตั้งค่าต้นทุนที่ซื้อมา (฿/kg)</h2>

        <div class="info-box">
            💡 <strong>ต้นทุนที่ซื้อมา</strong> คือราคาต่อกิโลกรัม (฿/kg)<br>
            <strong>ตัวอย่าง:</strong> ปลาแซลมอน 390 ฿/kg → มี 8,000g → มูลค่า = (390 × 8000) / 1000 = 3,120 ฿
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 80px">ID</th>
                    <th>ชื่อวัตถุดิบ</th>
                    <th style="width: 100px">หน่วย</th>
                    <th style="width: 200px">ต้นทุนที่ซื้อมา (฿/kg)</th>
                    <th style="width: 150px">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $count = 0;
            while ($ing = oci_fetch_assoc($stidPurchase)):
                $count++;
            ?>
                <tr>
                    <td><strong><?= $ing['INGREDIENTID'] ?></strong></td>
                    <td><?= htmlspecialchars($ing['INGREDIENTNAME']) ?></td>
                    <td><?= htmlspecialchars($ing['UNIT']) ?></td>
                    <td>
                        <form method="POST" style="display: inline-flex; align-items: center; gap: 10px;">
                            <input type="hidden" name="ingredientid" value="<?= $ing['INGREDIENTID'] ?>">
                            <input type="number" name="purchase_cost" value="<?= $ing['PURCHASE_COST'] ?? 0 ?>"
                                   step="0.01" min="0" required> ฿/kg
                            <button type="submit" name="save_purchase_cost" class="btn-save">บันทึก</button>
                        </form>
                    </td>
                    <td>
                        <div class="cost-info">
                            <?= number_format($ing['PURCHASE_COST'] ?? 0, 2) ?> บาท/kg
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>

            <?php if ($count == 0): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 30px; color: #999;">
                        <em>ไม่มีข้อมูลวัตถุดิบ</em>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Stock Value Table (NEW) -->
    <div class="section">
        <h2>📦 มูลค่าสต็อกในร้าน (คำนวณจากกรัม)</h2>

        <div class="info-box">
            💡 <strong>มูลค่าสต็อก</strong> = (ต้นทุน ฿/kg × จำนวนกรัม) ÷ 1000<br>
            <strong>ตัวอย่าง:</strong> ต้นทุน 390 ฿/kg, มี 8,000g → มูลค่า = (390 × 8000) ÷ 1000 = 3,120 ฿
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 80px">ID</th>
                    <th>ชื่อวัตถุดิบ</th>
                    <th style="width: 120px">ต้นทุน (฿/kg)</th>
                    <th style="width: 120px">จำนวนในร้าน (g)</th>
                    <th style="width: 100px">หน่วย</th>
                    <th style="width: 150px; background: #e3f2fd;">มูลค่าเมื่อวาน (฿)</th>
                    <th style="width: 150px; background: #fff3e0;">มูลค่าที่ใช้วันนี้ (฿)</th>
                    <th style="width: 150px; background: #e8f5e9;">มูลค่าคงเหลือ (฿)</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // ดึงข้อมูลสำหรับคำนวณมูลค่าสต็อก
            $sqlStock = "SELECT INGREDIENTID, INGREDIENTNAME, PURCHASE_COST, QTYONHAND, UNIT
                         FROM INGREDIENT
                         ORDER BY INGREDIENTID";
            $stidStock = oci_parse($conn, $sqlStock);
            oci_execute($stidStock);
            
            // คำนวณจำนวนที่ใช้วันนี้ (จาก ORDER_ITEM ที่สั่งวันนี้)
            $usedToday = [];
            $usedSql = "
SELECT r.INGREDIENTID,
       SUM(r.COST * oi.QUANTITY) AS USED_VALUE
FROM ORDER_ITEM oi
JOIN RECIPE r ON oi.MENUID = r.MENUID
JOIN TRANSACTION t ON oi.ORDERID = t.ORDERID
WHERE TRUNC(t.ORDERDATE) = TRUNC(SYSDATE)
GROUP BY r.INGREDIENTID
";

            $usedStid = oci_parse($conn, $usedSql);
            oci_execute($usedStid);
            while ($used = oci_fetch_assoc($usedStid)) {
                $usedToday[$used['INGREDIENTID']] = $used['USED_VALUE'];
            }
            
            $totalYesterday = 0;
            $totalUsedToday = 0;
            $totalRemaining = 0;
            $stockCount = 0;
            
            while ($stock = oci_fetch_assoc($stidStock)):
    $stockCount++;
    $purchaseCostPerKg = $stock['PURCHASE_COST'] ?? 0; // ฿/kg
    $qtyNow = $stock['QTYONHAND'] ?? 0; // กรัมปัจจุบัน
    $qtyUsed = $usedToday[$stock['INGREDIENTID']] ?? 0; // กรัมที่ใช้วันนี้
    
    // จำนวนเมื่อวาน = ปัจจุบัน + ที่ใช้ไป
    $qtyYesterday = $qtyNow + $qtyUsed;
    
    // คำนวณมูลค่า: (฿/kg × กรัม) / 1000
    $valueYesterday = ($purchaseCostPerKg * $qtyYesterday) / 1000;
    $valueUsed = $usedToday[$stock['INGREDIENTID']] ?? 0;
    
    // ✅ แก้ไขตรงนี้: มูลค่าคงเหลือ = มูลค่าเมื่อวาน - มูลค่าที่ใช้วันนี้
    $valueRemaining = $valueYesterday - $valueUsed;
    
    $totalYesterday += $valueYesterday;
    $totalUsedToday += $valueUsed;
    $totalRemaining += $valueRemaining;
?>
                <tr>
                    <td><strong><?= $stock['INGREDIENTID'] ?></strong></td>
                    <td><?= htmlspecialchars($stock['INGREDIENTNAME']) ?></td>
                    <td><?= number_format($purchaseCostPerKg, 2) ?></td>
                    <td><strong><?= number_format($qtyNow, 2) ?></strong></td>
                    <td><?= htmlspecialchars($stock['UNIT']) ?></td>
                    <td style="background: #f0f8ff;">
                        <strong><?= number_format($valueYesterday, 2) ?></strong>
                        <div style="font-size: 11px; color: #999;">
                            (<?= number_format($qtyYesterday, 0) ?>g)
                        </div>
                    </td>
                    <td style="background: #fff9e6;">
                        <strong style="color: #f57c00;"><?= number_format($valueUsed, 2) ?></strong>
                        <div style="font-size: 11px; color: #999;">
                            (<?= number_format($qtyUsed, 0) ?>g)
                        </div>
                    </td>
                    <td style="background: #f1f8e9;">
                        <strong style="color: #2e7d32;"><?= number_format($valueRemaining, 2) ?></strong>
                        <div style="font-size: 11px; color: #999;">
                            (<?= number_format($qtyNow, 0) ?>g)
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>

            <?php if ($stockCount == 0): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 30px; color: #999;">
                        <em>ไม่มีข้อมูลสต็อก</em>
                    </td>
                </tr>
            <?php endif; ?>

            <!-- แถวสรุปรวมทั้งหมด -->
            <tr class="total-row">
                <td colspan="5" style="text-align: right; padding-right: 20px;">
                    💰 รวมทั้งหมด:
                </td>
                <td style="background: #e3f2fd; font-weight: bold; font-size: 16px;">
                    <?= number_format($totalYesterday, 2) ?> ฿
                    <div style="font-size: 12px; color: #666; font-weight: normal;">มูลค่าเมื่อวาน</div>
                </td>
                <td style="background: #fff3e0; font-weight: bold; font-size: 16px;">
                    <span style="color: #f57c00;"><?= number_format($totalUsedToday, 2) ?> ฿</span>
                    <div style="font-size: 12px; color: #666; font-weight: normal;">ใช้ไปวันนี้</div>
                </td>
                <td style="background: #e8f5e9; font-weight: bold; font-size: 16px;">
                    <span style="color: #2e7d32;"><?= number_format($totalRemaining, 2) ?> ฿</span>
                    <div style="font-size: 12px; color: #666; font-weight: normal;">คงเหลือ</div>
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    <!-- Course Cost Setting Section -->
    <div class="section">
        <h2>🍱 ตั้งค่าต้นทุนคอร์ส</h2>
        <div class="info-box">
            💡 <strong>ต้นทุนคอร์ส</strong> คือราคาต้นทุนรวมของแต่ละคอร์ส (Course) <br>
            สามารถกำหนดเองหรือคำนวณจากเมนูในคอร์สก็ได้
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width: 80px">ID</th>
                    <th>ชื่อคอร์ส</th>
                    <th style="width: 200px">ต้นทุนคอร์ส (฿)</th>
                    <th style="width: 150px">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $count = 0;
            while ($course = oci_fetch_assoc($stidCourses)):
                $count++;
            ?>
                <tr>
                    <td><strong><?= $course['COURSEID'] ?></strong></td>
                    <td><?= htmlspecialchars($course['COURSENAME']) ?></td>
                    <td>
                        <form method="POST" style="display: inline-flex; align-items: center; gap: 10px;">
                            <input type="hidden" name="courseid" value="<?= $course['COURSEID'] ?>">
                            <input type="number" name="course_cost" value="<?= $course['COST'] ?? 0 ?>"
                                   step="0.01" min="0" required> ฿
                            <button type="submit" name="save_course_cost" class="btn-save">บันทึก</button>
                        </form>
                    </td>
                    <td>
                        <div class="cost-info">
                            <?= number_format($course['COST'] ?? 0, 2) ?> บาท/คอร์ส
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
            <?php if ($count == 0): ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 30px; color: #999;">
                        <em>ไม่มีข้อมูลคอร์ส</em>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
                
    <div class="section" style="background: #fff3cd; border-left: 4px solid #ffc107;">
        
        <h3 style="color: #856404; margin-bottom: 15px;">📝 หมายเหตุ</h3>
        <ul style="color: #856404; line-height: 1.8;">
            <li>ตารางถูกสร้างอัตโนมัติเมื่อเปิดหน้านี้ครั้งแรก</li>
            <li>GP ค่าเริ่มต้น: A la carte = 10%, Omakase = 15%</li>
            <li>ต้นทุนวัตถุดิบคิดเป็น <strong>ราคาต่อชิ้น</strong> (ไม่ใช่ต่อกรัม)</li>
            <li>สามารถแก้ไขค่า GP และต้นทุนได้ทุกเมื่อ</li>
            <li>เมื่อสร้างออเดอร์ใหม่ ระบบจะคำนวณกำไรอัตโนมัติ</li>
            <li>ดูรายงานกำไรได้ที่หน้า <a href="profit_list.php" style="color: #3498db; font-weight: bold;">Profit Report</a></li>
        </ul>
    </div>
</div>
<script src="auth_guard.js"></script>
</body>
</html>