<?php
include 'connect.php';

$menuid = $_GET['id'] ?? null;

if (!$menuid) {
    header("Location: menu_list.php");
    exit();
}

// ตรวจสอบว่ามีเมนูนี้จริงหรือไม่
$checkSql = "SELECT MENUNAME FROM MENU WHERE MENUID = :mid";
$checkStid = oci_parse($conn, $checkSql);
oci_bind_by_name($checkStid, ":mid", $menuid);
oci_execute($checkStid);
$menu = oci_fetch_assoc($checkStid);

if (!$menu) {
    die("Menu not found");
}

// ตรวจสอบว่าเมนูนี้ถูกใช้งานในตารางอื่นหรือไม่
$errors = [];

// 1. ตรวจสอบใน RECIPE
$recipeSql = "SELECT COUNT(*) AS CNT FROM RECIPE WHERE MENUID = :mid";
$recipeStid = oci_parse($conn, $recipeSql);
oci_bind_by_name($recipeStid, ":mid", $menuid);
oci_execute($recipeStid);
$recipeCount = oci_fetch_assoc($recipeStid)['CNT'];

if ($recipeCount > 0) {
    $errors[] = "มีสูตรอาหาร (RECIPE) ที่ใช้เมนูนี้อยู่ {$recipeCount} รายการ";
}

// 2. ตรวจสอบใน ORDER_ITEM
$orderSql = "SELECT COUNT(*) AS CNT FROM ORDER_ITEM WHERE MENUID = :mid";
$orderStid = oci_parse($conn, $orderSql);
oci_bind_by_name($orderStid, ":mid", $menuid);
oci_execute($orderStid);
$orderCount = oci_fetch_assoc($orderStid)['CNT'];

if ($orderCount > 0) {
    $errors[] = "มีออเดอร์ (ORDER_ITEM) ที่สั่งเมนูนี้อยู่ {$orderCount} รายการ";
}

// ถ้ามี error - แสดงหน้าเตือน
if (count($errors) > 0) {
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Cannot Delete Menu</title>
    <style>
        body { 
            font-family: Arial; 
            padding: 50px; 
            background: #fafafa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .error-box { 
            background: white;
            border-left: 5px solid #e74c3c;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .error-box h2 {
            color: #e74c3c;
            margin-top: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .error-list {
            background: #fee;
            padding: 15px 20px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .error-list li {
            margin: 10px 0;
            color: #c0392b;
        }
        .info-box {
            background: #e8f4f8;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            border-left: 4px solid #3498db;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            font-size: 14px;
            transition: all 0.2s;
        }
        .btn-back {
            background: #3498db;
            color: white;
        }
        .btn-back:hover {
            background: #2980b9;
        }
        .btn-force {
            background: #e74c3c;
            color: white;
        }
        .btn-force:hover {
            background: #c0392b;
        }
        .warning-text {
            color: #e67e22;
            font-weight: bold;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class='error-box'>
        <h2>
            <span style="font-size: 32px;">⚠️</span>
            ไม่สามารถลบเมนูได้
        </h2>
        
        <div class="info-box">
            <strong>เมนู:</strong> <?= htmlspecialchars($menu['MENUNAME']) ?> (ID: <?= $menuid ?>)
        </div>
        
        <p><strong>ไม่สามารถลบเมนูนี้ได้เนื่องจาก:</strong></p>
        <div class="error-list">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <p class="warning-text">⚠️ กรุณาลบข้อมูลที่เกี่ยวข้องก่อน หรือใช้ปุ่ม "Force Delete" เพื่อลบทั้งหมด</p>
        
        <div style="margin-top: 30px;">
            <a href='menu_list.php' class='btn btn-back'>← กลับไปรายการเมนู</a>
            <a href='menu_delete.php?id=<?= $menuid ?>&force=1' 
               onclick="return confirm('คำเตือน!\n\nการ Force Delete จะลบ:\n- สูตรอาหาร (RECIPE)\n- รายการออเดอร์ (ORDER_ITEM)\n- เมนู (MENU)\n\nแน่ใจหรือไม่?')" 
               class='btn btn-force'>
                🗑️ Force Delete (ลบทั้งหมด)
            </a>
        </div>
    </div>
</body>
</html>
<?php
    exit();
}

// ถ้าไม่มี error - ลบได้เลย
try {
    // ถ้ามี force parameter - ลบข้อมูลที่เกี่ยวข้องทั้งหมด
    if (isset($_GET['force']) && $_GET['force'] == 1) {
        
        // 1. ลบ RECIPE
        $delRecipe = oci_parse($conn, "DELETE FROM RECIPE WHERE MENUID = :mid");
        oci_bind_by_name($delRecipe, ":mid", $menuid);
        oci_execute($delRecipe);
        
        // 2. ลบ ORDER_ITEM
        $delOrder = oci_parse($conn, "DELETE FROM ORDER_ITEM WHERE MENUID = :mid");
        oci_bind_by_name($delOrder, ":mid", $menuid);
        oci_execute($delOrder);
    }
    
    // 3. ลบ MENU
    $delMenu = oci_parse($conn, "DELETE FROM MENU WHERE MENUID = :mid");
    oci_bind_by_name($delMenu, ":mid", $menuid);
    
    if (!oci_execute($delMenu)) {
        throw new Exception("Failed to delete menu");
    }
    
    // COMMIT
    if (!oci_commit($conn)) {
        throw new Exception("Failed to commit");
    }
    
    // สำเร็จ
    header("Location: menu_list.php?deleted=success");
    exit();
    
} catch (Exception $e) {
    // Error - rollback
    oci_rollback($conn);
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <title>Error</title>
        <style>
            body { font-family: Arial; padding: 50px; text-align: center; }
            .error-box { 
                background: #fee; 
                border: 2px solid #e74c3c; 
                padding: 30px; 
                border-radius: 8px;
                max-width: 500px;
                margin: 0 auto;
            }
            .btn {
                padding: 10px 20px;
                background: #3498db;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class='error-box'>
            <h2>❌ เกิดข้อผิดพลาด</h2>
            <p>" . htmlspecialchars($e->getMessage()) . "</p>
            <a href='menu_list.php' class='btn'>กลับไปรายการเมนู</a>
        </div>
    </body>
    </html>";
}

oci_close($conn);
?>