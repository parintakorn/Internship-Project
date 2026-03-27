<?php
include 'connect.php';

// ========================================
// รับค่า Filter
// ========================================
$filterType = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selectedYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
$menuTypeFilter = isset($_GET['menutype']) ? $_GET['menutype'] : 'all';

// สร้าง WHERE clause ตาม filter
$whereClause = "";
if ($filterType == 'daily') {
    $whereClause = "AND TRUNC(t.ORDERDATE) = TO_DATE('$selectedDate', 'YYYY-MM-DD')";
} elseif ($filterType == 'monthly') {
    $whereClause = "AND TO_CHAR(t.ORDERDATE, 'YYYY-MM') = '$selectedMonth'";
} elseif ($filterType == 'yearly') {
    $whereClause = "AND TO_CHAR(t.ORDERDATE, 'YYYY') = '$selectedYear'";
}

if ($menuTypeFilter == '1') {
    $whereClause .= " AND t.MENUTYPEID = 1";
} elseif ($menuTypeFilter == '2') {
    $whereClause .= " AND t.MENUTYPEID IN (2, 3)";
}

// ========================================
// คำนวณต้นทุนสำหรับแต่ละออเดอร์
// ========================================
$orderCosts = [];

// 1. ดึงต้นทุนจาก ORDER_ITEM (A la carte + Extra)
$costSql = "
    SELECT oi.ORDERID, SUM(r.COST * oi.QUANTITY) AS TOTAL_COST
    FROM ORDER_ITEM oi
    JOIN RECIPE r ON oi.MENUID = r.MENUID
    GROUP BY oi.ORDERID
";
$costStid = oci_parse($conn, $costSql);
oci_execute($costStid);
while ($costRow = oci_fetch_assoc($costStid)) {
    $orderCosts[$costRow['ORDERID']] = floatval($costRow['TOTAL_COST']);
}

// 2. บวกต้นทุนจาก COURSE_MENU (สำหรับ Buffet/Omakase)
$courseCostSql = "
    SELECT t.ORDERID, t.COURSEID,
           SUM(r.COST * cm.QUANTITY) AS COURSE_COST,
           NVL(MAX(os.PERSON_COUNT), 1) AS PERSON_COUNT
    FROM TRANSACTION t
    JOIN COURSE_MENU cm    ON t.COURSEID      = cm.COURSEID
    JOIN RECIPE r          ON cm.MENUID       = r.MENUID
    LEFT JOIN ORDER_SECTION os ON t.ORDERID   = os.ORDERID AND os.MENUTYPEID IN (2, 3)
    WHERE t.MENUTYPEID IN (2, 3)
    GROUP BY t.ORDERID, t.COURSEID
";
$courseCostStid = oci_parse($conn, $courseCostSql);
oci_execute($courseCostStid);
while ($courseRow = oci_fetch_assoc($courseCostStid)) {
    $orderid = $courseRow['ORDERID'];
    $courseCostPerPerson = floatval($courseRow['COURSE_COST']);
    $personCount = intval($courseRow['PERSON_COUNT']);
    if ($personCount == 0) $personCount = 1;

    $totalCourseCost = $courseCostPerPerson * $personCount;

    if (isset($orderCosts[$orderid])) {
        $orderCosts[$orderid] += $totalCourseCost;
    } else {
        $orderCosts[$orderid] = $totalCourseCost;
    }
}

// ต้นทุนจาก Course
$courseMenuCosts = [];
$cmSql = "SELECT COURSEID, COST FROM MENU_COURSE";
$cmStid = oci_parse($conn, $cmSql);
@oci_execute($cmStid);
while ($cmRow = oci_fetch_assoc($cmStid)) {
    $courseMenuCosts[$cmRow['COURSEID']] = $cmRow['COST'] ?? 0;
}

// ดึงค่า GP
$gp_alacarte = 0.10;
$gp_omakase  = 0.15;

$gpSql = "SELECT MENUTYPEID, GP FROM MENU_TYPE_PRICE";
$gpStid = oci_parse($conn, $gpSql);
@oci_execute($gpStid);
while ($gpRow = oci_fetch_assoc($gpStid)) {
    if ($gpRow['MENUTYPEID'] == 1) $gp_alacarte = $gpRow['GP'] / 100;
    elseif ($gpRow['MENUTYPEID'] == 2 || $gpRow['MENUTYPEID'] == 3) $gp_omakase = $gpRow['GP'] / 100;
}

// ========================================
// ดึงข้อมูลธุรกรรม
// ========================================
$sql = "SELECT t.ORDERID,
               TO_CHAR(t.ORDERDATE, 'DD-MON-YYYY') as ORDERDATE_DISPLAY,
               t.ORDERDATE,
               t.TOTALPRICE, t.MENUTYPEID, t.COURSEID,
               mt.TYPENAME, t.CUSTOMERID, m.CUSTOMERNAME
        FROM TRANSACTION t
        LEFT JOIN MENU_TYPE mt ON t.MENUTYPEID = mt.MENUTYPEID
        LEFT JOIN MEMBER m ON t.CUSTOMERID = m.CUSTOMERID
        WHERE 1=1 $whereClause
        ORDER BY t.ORDERDATE DESC, t.ORDERID DESC";
$stid = oci_parse($conn, $sql);
oci_execute($stid);

$totalAlacarteBefore = 0;
$totalAlacarteAfter  = 0;
$totalOmakaseBefore  = 0;
$totalOmakaseAfter   = 0;
$totalRevenue        = 0;

$profits = [];
while ($row = oci_fetch_assoc($stid)) {
    $profits[] = $row;
    $totalRevenue += $row['TOTALPRICE'];
}

$grandTotalBefore = 0;
$grandTotalAfter  = 0;

foreach ($profits as $p) {
    $menutypeid  = $p['MENUTYPEID'];
    $orderid     = $p['ORDERID'];
    $cost        = isset($orderCosts[$orderid]) ? $orderCosts[$orderid] : 0;
    $profitBefore = $p['TOTALPRICE'] - $cost;

    if ($menutypeid == 2 || $menutypeid == 3) {
        $profitAfter = $profitBefore * (1 - $gp_omakase);
        $totalOmakaseBefore += $profitBefore;
        $totalOmakaseAfter  += $profitAfter;
    } else {
        $profitAfter = $profitBefore * (1 - $gp_alacarte);
        $totalAlacarteBefore += $profitBefore;
        $totalAlacarteAfter  += $profitAfter;
    }
}

$grandTotalBefore = $totalAlacarteBefore + $totalOmakaseBefore;
$grandTotalAfter  = $totalAlacarteAfter  + $totalOmakaseAfter;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Profit Report</title>
    <style>
        body{font-family:Arial,sans-serif;margin:0;padding:0;background:#fafafa}
        .top-bar{width:100%;background:rgba(255,255,255,0.95);padding:15px 20px;display:flex;align-items:center;box-shadow:0 4px 12px rgba(0,0,0,0.15);position:fixed;top:0;left:0;z-index:20;backdrop-filter:blur(10px)}
        .menu-btn,.back-btn{font-size:24px;margin-right:15px;cursor:pointer;padding:8px 12px;border-radius:8px;border:none;background:#667eea;color:white;transition:all 0.3s}
        .menu-btn:hover,.back-btn:hover{background:#5568d3;transform:translateY(-2px);box-shadow:0 4px 8px rgba(0,0,0,0.2)}
        #sidebar{width:280px;height:100vh;position:fixed;top:0;left:-280px;background:linear-gradient(180deg,#2c3e50 0%,#34495e 100%);color:white;transition:left 0.3s ease;padding-top:60px;z-index:1000;box-shadow:4px 0 15px rgba(0,0,0,0.3);overflow-y:auto}
        #sidebar.active{left:0}
        #sidebar a{display:flex;align-items:center;padding:15px 25px;text-decoration:none;color:rgba(255,255,255,0.9);font-size:16px;transition:all 0.3s;border-left:3px solid transparent}
        #sidebar a:hover{background:rgba(255,255,255,0.1);border-left-color:#667eea;padding-left:30px}
        .overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:999}
        .overlay.active{display:block}
        .container{margin-top:90px;margin-left:30px;margin-right:30px;padding-bottom:50px}
        .filter-box{background:white;padding:25px;border-radius:8px;margin-bottom:20px;box-shadow:0 2px 4px rgba(0,0,0,0.1)}
        .filter-tabs{display:flex;gap:10px;margin-bottom:15px;flex-wrap:wrap}
        .filter-tab{padding:10px 20px;border:2px solid #ddd;background:white;border-radius:6px;cursor:pointer;transition:all 0.3s;font-weight:600}
        .filter-tab:hover{border-color:#3498db;background:#f0f8ff}
        .filter-tab.active{background:#3498db;color:white;border-color:#3498db}
        .date-selector{display:none;padding:15px;background:#f8f9fa;border-radius:6px;margin-top:10px}
        .date-selector.show{display:block}
        .date-selector input,.date-selector select{padding:8px 12px;border:1px solid #ddd;border-radius:4px;margin-right:10px}
        .btn-filter{background:#27ae60;color:white;border:none;padding:8px 20px;border-radius:4px;cursor:pointer;font-weight:600}
        .summary-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:30px}
        .summary-card{background:white;padding:20px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.1);border-left:5px solid}
        .summary-card.revenue{border-color:#3498db}
        .summary-card.alacarte{border-color:#e74c3c}
        .summary-card.omakase{border-color:#f39c12}
        .summary-card.total{border-color:#27ae60;background:linear-gradient(135deg,#d4edda 0%,#c3e6cb 100%)}
        .summary-label{font-size:14px;color:#7f8c8d;margin-bottom:8px;font-weight:600}
        .summary-value{font-size:28px;font-weight:bold;color:#2c3e50}
        .summary-sub{font-size:11px;color:#95a5a6;margin-top:5px}
        .profit-positive{color:#27ae60}
        .profit-negative{color:#e74c3c}
        table{background:white;border-collapse:collapse;width:100%;box-shadow:0 2px 4px rgba(0,0,0,0.1)}
        table,th,td{border:1px solid #ddd}
        th,td{padding:12px 8px;text-align:center}
        th{background:#34495e;color:white;font-weight:bold}
        tr:hover{background:#f5f5f5}
        .type-badge{display:inline-block;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:bold}
        .type-alacarte{background:#e74c3c;color:white}
        .type-omakase{background:#f39c12;color:white}
        .gp-info{font-size:11px;color:#7f8c8d;display:block;margin-top:3px}
        .btn-settings{background:#9b59b6;color:white;border:none;padding:12px 24px;border-radius:6px;cursor:pointer;font-size:14px;font-weight:600;text-decoration:none;display:inline-block}
        .cost-info{font-size:11px;color:#e74c3c;display:block}
    </style>
</head>
<body>

<div class="top-bar">
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
    <button class="back-btn" onclick="window.location.href='homepage.php'">←</button>
    <h2 style="margin:0">💰 Profit Report</h2>
</div>

<div id="overlay" class="overlay" onclick="toggleMenu()"></div>

<div id="sidebar">
    <div style="padding:20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)">
        <h3 style="margin:0;margin-bottom:5px">🍱 Menu</h3>
        <p style="font-size:12px;color:rgba(255,255,255,0.7);margin:0">Restaurant Management</p>
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

<div class="container">
    <h3>📊 สรุปกำไร-ขาดทุน</h3>

    <div class="filter-box">
        <strong style="font-size:16px;display:block;margin-bottom:15px">🔍 กรองตามช่วงเวลา</strong>

        <div class="filter-tabs">
            <div class="filter-tab <?=$filterType=='all'?'active':''?>" onclick="selectFilter('all')">📅 ทั้งหมด</div>
            <div class="filter-tab <?=$filterType=='daily'?'active':''?>" onclick="selectFilter('daily')">📆 รายวัน</div>
            <div class="filter-tab <?=$filterType=='monthly'?'active':''?>" onclick="selectFilter('monthly')">📊 รายเดือน</div>
            <div class="filter-tab <?=$filterType=='yearly'?'active':''?>" onclick="selectFilter('yearly')">📈 รายปี</div>
        </div>

        <div id="daily-selector" class="date-selector <?=$filterType=='daily'?'show':''?>">
            <form method="GET" style="display:inline">
                <input type="hidden" name="filter" value="daily">
                <input type="hidden" name="menutype" value="<?=$menuTypeFilter?>">
                <input type="date" name="date" value="<?=$selectedDate?>" required>
                <button type="submit" class="btn-filter">ดูข้อมูล</button>
            </form>
        </div>

        <div id="monthly-selector" class="date-selector <?=$filterType=='monthly'?'show':''?>">
            <form method="GET" style="display:inline">
                <input type="hidden" name="filter" value="monthly">
                <input type="hidden" name="menutype" value="<?=$menuTypeFilter?>">
                <input type="month" name="month" value="<?=$selectedMonth?>" required>
                <button type="submit" class="btn-filter">ดูข้อมูล</button>
            </form>
        </div>

        <div id="yearly-selector" class="date-selector <?=$filterType=='yearly'?'show':''?>">
            <form method="GET" style="display:inline">
                <input type="hidden" name="filter" value="yearly">
                <input type="hidden" name="menutype" value="<?=$menuTypeFilter?>">
                <select name="year" required>
                    <?php for($y=date('Y');$y>=2020;$y--):?>
                        <option value="<?=$y?>" <?=$y==$selectedYear?'selected':''?>><?=$y+543?></option>
                    <?php endfor;?>
                </select>
                <button type="submit" class="btn-filter">ดูข้อมูล</button>
            </form>
        </div>
    </div>

    <div class="summary-cards">
        <div class="summary-card revenue">
            <div class="summary-label">💵 รายได้รวม</div>
            <div class="summary-value"><?=number_format($totalRevenue,2)?> ฿</div>
            <div class="summary-sub"><?=count($profits)?> ออเดอร์</div>
        </div>
        <div class="summary-card alacarte">
            <div class="summary-label">🍱 A la carte</div>
            <div class="summary-value <?=$totalAlacarteAfter>=0?'profit-positive':'profit-negative'?>">
                <?=number_format($totalAlacarteAfter,2)?> ฿
            </div>
            <div class="summary-sub">ก่อน GP: <?=number_format($totalAlacarteBefore,2)?> ฿</div>
        </div>
        <div class="summary-card omakase">
            <div class="summary-label">🍽️ Omakase / Buffet</div>
            <div class="summary-value <?=$totalOmakaseAfter>=0?'profit-positive':'profit-negative'?>">
                <?=number_format($totalOmakaseAfter,2)?> ฿
            </div>
            <div class="summary-sub">ก่อน GP: <?=number_format($totalOmakaseBefore,2)?> ฿</div>
        </div>
        <div class="summary-card total">
            <div class="summary-label">💰 กำไรสุทธิ (หลัง GP)</div>
            <div class="summary-value <?=$grandTotalAfter>=0?'profit-positive':'profit-negative'?>">
                <?=number_format($grandTotalAfter,2)?> ฿
            </div>
            <div class="summary-sub">ก่อน GP: <?=number_format($grandTotalBefore,2)?> ฿</div>
        </div>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:15px">
        <h3 style="margin:0">📊 รายละเอียดกำไรแต่ละออเดอร์</h3>

        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <select onchange="filterByMenuType(this.value)" style="padding:10px 15px;border:2px solid #3498db;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;background:white;color:#2c3e50">
                <option value="all" <?=$menuTypeFilter=='all'?'selected':''?>>📋 ประเภทเมนู: ทั้งหมด</option>
                <option value="1" <?=$menuTypeFilter=='1'?'selected':''?>>🍱 A la carte</option>
                <option value="2" <?=$menuTypeFilter=='2'?'selected':''?>>🍽️ Omakase / Buffet</option>
            </select>

            <a href="cash_reserve.php" style="text-decoration:none">
                <button style="background:#27ae60;color:white;border:none;padding:12px 24px;border-radius:6px;cursor:pointer">
                    💵 เงินสำรองหน้าร้าน
                </button>
            </a>

            <a href="profit_summary_report_day.php?date=<?=$selectedDate?>&month=<?=$selectedMonth?>" style="text-decoration:none">
                <button style="background:#3498db;color:white;border:none;padding:12px 24px;border-radius:6px;cursor:pointer">
                    📋 รายงานรายวัน
                </button>
            </a>

            <a href="profit_summary_report_month.php?month=<?=$selectedMonth?>" style="text-decoration:none">
                <button style="background:#e67e22;color:white;border:none;padding:12px 24px;border-radius:6px;cursor:pointer">
                    📋 รายงานรายเดือน
                </button>
            </a>
            <a href="gp_cost_setup.php" class="btn-settings">⚙️ ตั้งค่า GP</a>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>📅 วันที่</th>
                <th>ลูกค้า</th>
                <th>ประเภท</th>
                <th>รายได้</th>
                <th>ต้นทุน</th>
                <th>กำไรก่อน GP</th>
                <th>กำไรหลัง GP</th>
                <th>% กำไร</th>
            </tr>
        </thead>
        <tbody>
        <?php $count=0; foreach($profits as $p): $count++;
            $menutypeid  = $p['MENUTYPEID'];
            $orderid     = $p['ORDERID'];
            $cost        = isset($orderCosts[$orderid]) ? $orderCosts[$orderid] : 0;
            $profitBefore = $p['TOTALPRICE'] - $cost;

            if ($menutypeid == 2 || $menutypeid == 3) {
                $profitAfter = $profitBefore * (1 - $gp_omakase);
                $gp_percent  = $gp_omakase * 100;
            } else {
                $profitAfter = $profitBefore * (1 - $gp_alacarte);
                $gp_percent  = $gp_alacarte * 100;
            }

            $profitPercent = ($p['TOTALPRICE'] > 0) ? ($profitAfter / $p['TOTALPRICE']) * 100 : 0;
            $profitClass   = $profitAfter >= 0 ? 'profit-positive' : 'profit-negative';
            $typeClass     = $menutypeid == 1 ? 'type-alacarte' : 'type-omakase';
        ?>
            <tr>
                <td><strong><?=htmlspecialchars($p['ORDERID'])?></strong></td>
                <td><?=$p['ORDERDATE_DISPLAY'] ? date('d M Y', strtotime($p['ORDERDATE_DISPLAY'])) : '-'?></td>
                <td><?=$p['CUSTOMERNAME'] ? htmlspecialchars($p['CUSTOMERNAME']) : '<em>Guest</em>'?></td>
                <td><span class="type-badge <?=$typeClass?>"><?=htmlspecialchars($p['TYPENAME'])?></span></td>
                <td><strong><?=number_format($p['TOTALPRICE'],2)?> ฿</strong></td>
                <td><?=number_format($cost,2)?> ฿<span class="cost-info">ต้นทุนวัตถุดิบ</span></td>
                <td><?=number_format($profitBefore,2)?> ฿<span class="gp-info">ก่อนหัก GP</span></td>
                <td class="<?=$profitClass?>"><strong><?=number_format($profitAfter,2)?> ฿</strong><span class="gp-info">หลังหัก GP <?=number_format($gp_percent,1)?>%</span></td>
                <td class="<?=$profitClass?>"><strong><?=number_format($profitPercent,1)?>%</strong></td>
            </tr>
        <?php endforeach; if($count==0): ?>
            <tr><td colspan="9" style="padding:30px;color:#999"><em>ไม่มีข้อมูลในช่วงเวลาที่เลือก</em></td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <p style="margin-top:20px;color:#666"><strong>Total Records:</strong> <?=$count?> รายการ</p>
</div>

<script>
function toggleMenu(){
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('overlay').classList.toggle('active');
}
function selectFilter(type){
    document.querySelectorAll('.date-selector').forEach(el=>el.classList.remove('show'));
    if(type==='all'){
        window.location.href='profit_list.php?menutype=<?=$menuTypeFilter?>';
    }else{
        document.getElementById(type+'-selector').classList.add('show');
    }
}
function filterByMenuType(type){
    const params=new URLSearchParams(window.location.search);
    params.set('menutype',type);
    window.location.href='profit_list.php?'+params.toString();
}
document.addEventListener('keydown',function(e){
    if(e.key==='Escape'){
        document.getElementById('sidebar').classList.remove('active');
        document.getElementById('overlay').classList.remove('active');
    }
});
</script>
<script src="auth_guard.js"></script>
</body>
</html>