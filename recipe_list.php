<?php 
include 'connect.php';

// ---------------------- DELETE ----------------------
if (isset($_GET['delete_menu']) && isset($_GET['delete_ing'])) {
    $sql = "DELETE FROM RECIPE WHERE MENUID = :mid AND INGREDIENTID = :iid";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":mid", $_GET['delete_menu']);
    oci_bind_by_name($stid, ":iid", $_GET['delete_ing']);
    oci_execute($stid);
    oci_commit($conn);
    header("Location: recipe_list.php");
    exit();
}

// ---------------------- ADD RECIPE ----------------------
if (isset($_POST['add_recipe'])) {
    $menuid = $_POST['menuid'];
    $ingredients = $_POST['ingredientid'];
    $qtys = $_POST['qtyused'];
    
    for ($i = 0; $i < count($ingredients); $i++) {
        if (!empty($ingredients[$i]) && !empty($qtys[$i])) {
            $sql = "INSERT INTO RECIPE (MENUID, INGREDIENTID, QTYUSED)
                    VALUES (:mid, :iid, :qty)";
            $stid = oci_parse($conn, $sql);
            oci_bind_by_name($stid, ":mid", $menuid);
            oci_bind_by_name($stid, ":iid", $ingredients[$i]);
            oci_bind_by_name($stid, ":qty", $qtys[$i]);
            oci_execute($stid);
        }
    }
    oci_commit($conn);
    header("Location: recipe_list.php");
    exit();
}

// ---------------------- UPDATE RECIPE ----------------------
if (isset($_POST['update_recipe'])) {
    $menuid = $_POST['menuid'];
    $ingredientid = $_POST['ingredientid'];
    $qtyused = $_POST['qtyused'];
    
    $sql = "UPDATE RECIPE SET QTYUSED = :qty 
            WHERE MENUID = :mid AND INGREDIENTID = :iid";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":qty", $qtyused);
    oci_bind_by_name($stid, ":mid", $menuid);
    oci_bind_by_name($stid, ":iid", $ingredientid);
    oci_execute($stid);
    oci_commit($conn);
    header("Location: recipe_list.php");
    exit();
}

// ---------------------- ADD MORE INGREDIENTS TO EXISTING RECIPE ----------------------
if (isset($_POST['add_more_ingredients'])) {
    $menuid = $_POST['menuid'];
    $ingredients = $_POST['ingredientid'];
    $qtys = $_POST['qtyused'];
    
    for ($i = 0; $i < count($ingredients); $i++) {
        if (!empty($ingredients[$i]) && !empty($qtys[$i])) {
            $checkSql = "SELECT COUNT(*) AS CNT FROM RECIPE WHERE MENUID = :mid AND INGREDIENTID = :iid";
            $checkStid = oci_parse($conn, $checkSql);
            oci_bind_by_name($checkStid, ":mid", $menuid);
            oci_bind_by_name($checkStid, ":iid", $ingredients[$i]);
            oci_execute($checkStid);
            $result = oci_fetch_assoc($checkStid);
            
            if ($result['CNT'] == 0) {
                $sql = "INSERT INTO RECIPE (MENUID, INGREDIENTID, QTYUSED)
                        VALUES (:mid, :iid, :qty)";
                $stid = oci_parse($conn, $sql);
                oci_bind_by_name($stid, ":mid", $menuid);
                oci_bind_by_name($stid, ":iid", $ingredients[$i]);
                oci_bind_by_name($stid, ":qty", $qtys[$i]);
                oci_execute($stid);
            }
        }
    }
    oci_commit($conn);
    header("Location: recipe_list.php");
    exit();
}

// ดึงข้อมูลเมนู
$menuList = oci_parse($conn, "SELECT MENUID, MENUNAME FROM MENU ORDER BY MENUID");
oci_execute($menuList);

// ดึงข้อมูลวัตถุดิบ
$ingredientList = oci_parse($conn, "SELECT INGREDIENTID, INGREDIENTNAME, UNIT FROM INGREDIENT ORDER BY INGREDIENTID");
oci_execute($ingredientList);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Recipe Management</title>
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

#sidebar a::before {
    content: '▸';
    margin-right: 12px;
    font-size: 14px;
    opacity: 0;
    transition: opacity 0.3s;
}

#sidebar a:hover::before {
    opacity: 1;
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

.form-section {
    background: white;
    padding: 25px;
    border-radius: 8px;
    margin-bottom: 30px;
    max-width: 900px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-section h3 {
    margin-top: 0;
    color: #333;
    border-bottom: 2px solid #3498db;
    padding-bottom: 10px;
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

.form-group select, 
.form-group input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
}

.ingredient-row {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
    align-items: center;
}

.ingredient-row select,
.ingredient-row input {
    padding: 10px;
    flex: 1;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.ingredient-row button {
    padding: 10px 15px;
    background: #e74c3c;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.2s;
}

.ingredient-row button:hover {
    background: #c0392b;
}

.btn {
    padding: 10px 20px;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
    margin-top: 10px;
    margin-right: 5px;
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

table {
    background: white;
    border-collapse: collapse;
    width: 95%;
    margin-top: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

table, th, td {
    border: 1px solid #ddd;
}

th, td {
    padding: 12px;
    text-align: center;
}

th {
    background: #3498db;
    color: white;
    font-weight: bold;
}

tr:hover {
    background: #f5f5f5;
}

.recipe-group {
    background: #e8f4f8;
    font-weight: bold;
}

.recipe-group td {
    padding: 15px;
}

.action-buttons {
    display: flex;
    gap: 5px;
    justify-content: center;
}

.action-buttons button {
    padding: 6px 12px;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    font-size: 13px;
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
</style>
</head>
<body>

<!-- Top bar -->
<div class="top-bar">
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
    <button class="back-btn" onclick="window.location.href='homepage.php'">←</button>
    <h2>Recipe Management</h2>
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
</div>

<div class="container">

<?php if (!isset($_GET['edit_menu']) && !isset($_GET['add_to_menu'])): ?>
<!-- ADD RECIPE FORM -->
<div class="form-section">
    <h3>➕ Create New Recipe</h3>
    <form method="POST">
        <div class="form-group">
            <label>Select Menu</label>
            <select name="menuid" required>
                <option value="">-- Choose Menu --</option>
                <?php 
                oci_execute($menuList);
                while ($m = oci_fetch_assoc($menuList)): ?>
                    <option value="<?= $m['MENUID'] ?>"><?= $m['MENUNAME'] ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <label style="font-weight: bold; color: #555; margin-bottom: 10px; display: block;">Ingredients Used</label>
        <div id="ingredient-list">
            <div class="ingredient-row">
                <select name="ingredientid[]" required>
                    <option value="">-- Choose Ingredient --</option>
                    <?php 
                    oci_execute($ingredientList);
                    while ($i = oci_fetch_assoc($ingredientList)): ?>
                        <option value="<?= $i['INGREDIENTID'] ?>">
                            <?= $i['INGREDIENTNAME'] ?> (<?= $i['UNIT'] ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
                <input type="number" name="qtyused[]" placeholder="Quantity" step="0.01" required>
                <button type="button" onclick="removeRow(this)">Remove</button>
            </div>
        </div>

        <button type="button" class="btn btn-success" onclick="addIngredientRow()">+ Add Ingredient</button>
        <button type="submit" name="add_recipe" class="btn btn-primary">Save Recipe</button>
    </form>
</div>
<?php endif; ?>

<?php if (isset($_GET['edit_menu']) && isset($_GET['edit_ing'])): ?>
<!-- EDIT RECIPE FORM -->
<?php
$editSql = "SELECT r.*, m.MENUNAME, i.INGREDIENTNAME, i.UNIT 
            FROM RECIPE r
            JOIN MENU m ON r.MENUID = m.MENUID
            JOIN INGREDIENT i ON r.INGREDIENTID = i.INGREDIENTID
            WHERE r.MENUID = :mid AND r.INGREDIENTID = :iid";
$editStid = oci_parse($conn, $editSql);
oci_bind_by_name($editStid, ":mid", $_GET['edit_menu']);
oci_bind_by_name($editStid, ":iid", $_GET['edit_ing']);
oci_execute($editStid);
$editData = oci_fetch_assoc($editStid);
?>
<div class="form-section">
    <h3>✏️ Edit Recipe</h3>
    <form method="POST">
        <input type="hidden" name="menuid" value="<?= $editData['MENUID'] ?>">
        <input type="hidden" name="ingredientid" value="<?= $editData['INGREDIENTID'] ?>">
        
        <div class="form-group">
            <label>Menu</label>
            <input type="text" value="<?= htmlspecialchars($editData['MENUNAME']) ?>" readonly style="background: #f0f0f0;">
        </div>
        
        <div class="form-group">
            <label>Ingredient</label>
            <input type="text" value="<?= htmlspecialchars($editData['INGREDIENTNAME']) ?> (<?= $editData['UNIT'] ?>)" readonly style="background: #f0f0f0;">
        </div>
        
        <div class="form-group">
            <label>Quantity Used</label>
            <input type="number" name="qtyused" value="<?= $editData['QTYUSED'] ?>" step="0.01" required>
        </div>
        
        <button type="submit" name="update_recipe" class="btn btn-primary">Update</button>
        <a href="recipe_list.php"><button type="button" class="btn btn-secondary">Cancel</button></a>
    </form>
</div>
<?php endif; ?>

<?php if (isset($_GET['add_to_menu'])): ?>
<!-- ADD MORE INGREDIENTS TO EXISTING MENU -->
<?php
$menuId = $_GET['add_to_menu'];
$menuSql = "SELECT MENUNAME FROM MENU WHERE MENUID = :mid";
$menuStid = oci_parse($conn, $menuSql);
oci_bind_by_name($menuStid, ":mid", $menuId);
oci_execute($menuStid);
$menuData = oci_fetch_assoc($menuStid);
?>
<div class="form-section">
    <h3>➕ Add Ingredients to: <?= htmlspecialchars($menuData['MENUNAME']) ?></h3>
    <form method="POST">
        <input type="hidden" name="menuid" value="<?= $menuId ?>">
        
        <label style="font-weight: bold; color: #555; margin-bottom: 10px; display: block;">Additional Ingredients</label>
        <div id="add-ingredient-list">
            <div class="ingredient-row">
                <select name="ingredientid[]" required>
                    <option value="">-- Choose Ingredient --</option>
                    <?php 
                    oci_execute($ingredientList);
                    while ($i = oci_fetch_assoc($ingredientList)): ?>
                        <option value="<?= $i['INGREDIENTID'] ?>">
                            <?= $i['INGREDIENTNAME'] ?> (<?= $i['UNIT'] ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
                <input type="number" name="qtyused[]" placeholder="Quantity" step="0.01" required>
                <button type="button" onclick="removeAddRow(this)">Remove</button>
            </div>
        </div>

        <button type="button" class="btn btn-success" onclick="addMoreIngredientRow()">+ Add More</button>
        <button type="submit" name="add_more_ingredients" class="btn btn-primary">Save</button>
        <a href="recipe_list.php"><button type="button" class="btn btn-secondary">Cancel</button></a>
    </form>
</div>
<?php endif; ?>

<!-- RECIPE LIST -->
<div class="page-header">
    <span class="icon">📖</span>
    <h3>Recipe List</h3>
</div>

<table>
    <thead>
        <tr>
            <th>Menu ID</th>
            <th>Menu Name</th>
            <th>Ingredient</th>
            <th>Quantity</th>
            <th>Unit</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
<?php
$sql = "SELECT r.MENUID, r.INGREDIENTID, r.QTYUSED, 
               m.MENUNAME, i.INGREDIENTNAME, i.UNIT
        FROM RECIPE r
        JOIN MENU m ON r.MENUID = m.MENUID
        JOIN INGREDIENT i ON r.INGREDIENTID = i.INGREDIENTID
        ORDER BY r.MENUID, r.INGREDIENTID";
$stid = oci_parse($conn, $sql);
oci_execute($stid);

$currentMenu = null;
$count = 0;
while ($row = oci_fetch_assoc($stid)):
    $count++;
    if ($currentMenu != $row['MENUID']):
        $currentMenu = $row['MENUID'];
?>
        <tr class="recipe-group">
            <td colspan="5" style="text-align: left;">
                📋 <strong><?= htmlspecialchars($row['MENUNAME']) ?></strong> (Menu ID: <?= $row['MENUID'] ?>)
            </td>
            <td style="text-align: center;">
                <a href="recipe_list.php?add_to_menu=<?= $row['MENUID'] ?>">
                    <button class="btn btn-success" style="padding: 6px 12px; font-size: 13px;">+ Add Ingredient</button>
                </a>
            </td>
        </tr>
<?php endif; ?>
        <tr>
            <td><?= $row['MENUID'] ?></td>
            <td style="text-align: left;"><?= htmlspecialchars($row['MENUNAME']) ?></td>
            <td style="text-align: left;"><?= htmlspecialchars($row['INGREDIENTNAME']) ?></td>
            <td><strong><?= number_format($row['QTYUSED'], 2) ?></strong></td>
            <td><?= htmlspecialchars($row['UNIT']) ?></td>
            <td>
                <div class="action-buttons">
                    <a href="recipe_list.php?edit_menu=<?= $row['MENUID'] ?>&edit_ing=<?= $row['INGREDIENTID'] ?>">
                        <button class="btn-warning">Edit</button>
                    </a>
                    <a href="recipe_list.php?delete_menu=<?= $row['MENUID'] ?>&delete_ing=<?= $row['INGREDIENTID'] ?>"
                       onclick="return confirm('Remove this ingredient from recipe?')">
                        <button class="btn-danger">Delete</button>
                    </a>
                </div>
            </td>
        </tr>
<?php endwhile; ?>

<?php if ($count == 0): ?>
    <tr>
        <td colspan="6" style="padding: 30px; color: #999;">
            <em>No recipes found. Create your first recipe above.</em>
        </td>
    </tr>
<?php endif; ?>
    </tbody>
</table>

<p style="margin-top: 20px; color: #666;">
    <strong>Total Recipe Items:</strong> <?= $count ?> items
</p>

</div>

<script>
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

function addIngredientRow() {
    const list = document.getElementById('ingredient-list');
    const firstRow = document.querySelector('.ingredient-row');
    const newRow = firstRow.cloneNode(true);
    
    newRow.querySelectorAll('select, input').forEach(el => el.value = '');
    
    list.appendChild(newRow);
}

function removeRow(btn) {
    const rows = document.querySelectorAll('#ingredient-list .ingredient-row');
    if (rows.length > 1) {
        btn.parentElement.remove();
    } else {
        alert('Recipe must have at least 1 ingredient');
    }
}

function addMoreIngredientRow() {
    const list = document.getElementById('add-ingredient-list');
    const firstRow = list.querySelector('.ingredient-row');
    const newRow = firstRow.cloneNode(true);
    
    newRow.querySelectorAll('select, input').forEach(el => el.value = '');
    
    list.appendChild(newRow);
}

function removeAddRow(btn) {
    const rows = document.querySelectorAll('#add-ingredient-list .ingredient-row');
    if (rows.length > 1) {
        btn.parentElement.remove();
    } else {
        alert('Must add at least 1 ingredient');
    }
}
</script>
<script src="auth_guard.js"></script>
</body>
</html>