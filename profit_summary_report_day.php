<?php
include 'connect.php';

$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$today = $selectedDate;
$displayDate = date('d/m/Y', strtotime($selectedDate));
$yesterday = date('Y-m-d', strtotime($selectedDate . ' -1 day'));
$yesterdayDisplay = date('d/m/Y', strtotime($yesterday));

// ===============================
// ดึง GP จาก MENU_TYPE_PRICE
// ===============================
$gp_alacarte = 0.10;
$gp_omakase = 0.15;

$gpSql = "SELECT MENUTYPEID, GP FROM MENU_TYPE_PRICE";
$gpStid = oci_parse($conn, $gpSql);
@oci_execute($gpStid);
while ($gpRow = oci_fetch_assoc($gpStid)) {
    if ($gpRow['MENUTYPEID'] == 1) {
        $gp_alacarte = $gpRow['GP'] / 100;
    } elseif ($gpRow['MENUTYPEID'] == 2 || $gpRow['MENUTYPEID'] == 3) {
        $gp_omakase = $gpRow['GP'] / 100;
    }
}

// ===============================
// คำนวณต้นทุนวัตถุดิบสำหรับแต่ละออเดอร์
// ===============================
$orderCosts = [];

// 1. ดึงต้นทุนจาก ORDER_ITEM (A la carte + Extra)
$costSql = "
    SELECT oi.ORDERID, SUM(r.COST * oi.QUANTITY) AS TOTAL_COST
    FROM ORDER_ITEM oi
    JOIN RECIPE r ON oi.MENUID = r.MENUID
    JOIN TRANSACTION t ON oi.ORDERID = t.ORDERID
    WHERE t.ORDERDATE >= TO_DATE(:d,'YYYY-MM-DD')
      AND t.ORDERDATE < TO_DATE(:d,'YYYY-MM-DD') + 1
    GROUP BY oi.ORDERID
";
$costStid = oci_parse($conn, $costSql);
oci_bind_by_name($costStid, ':d', $selectedDate);
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
    JOIN COURSE_MENU cm ON t.COURSEID = cm.COURSEID
    JOIN RECIPE r ON cm.MENUID = r.MENUID
    LEFT JOIN ORDER_SECTION os ON t.ORDERID = os.ORDERID AND os.MENUTYPEID IN (2, 3)
    WHERE t.ORDERDATE >= TO_DATE(:d,'YYYY-MM-DD')
      AND t.ORDERDATE < TO_DATE(:d,'YYYY-MM-DD') + 1
      AND t.MENUTYPEID IN (2, 3)
    GROUP BY t.ORDERID, t.COURSEID
";
$courseCostStid = oci_parse($conn, $courseCostSql);
oci_bind_by_name($courseCostStid, ':d', $selectedDate);
oci_execute($courseCostStid);
while ($courseRow = oci_fetch_assoc($courseCostStid)) {
    $orderid = $courseRow['ORDERID'];
    $courseCostPerPerson = floatval($courseRow['COURSE_COST']);
    $personCount = intval($courseRow['PERSON_COUNT']);
    if ($personCount == 0) $personCount = 1;
    
    $totalCourseCost = $courseCostPerPerson * $personCount;
    
    // บวกเข้ากับต้นทุน Extra (ถ้ามี)
    if (isset($orderCosts[$orderid])) {
        $orderCosts[$orderid] += $totalCourseCost;
    } else {
        $orderCosts[$orderid] = $totalCourseCost;
    }
}
// ===============================
// ดึงต้นทุนคอร์สจาก MENU_COURSE
// ===============================
$courseMenuCosts = [];
$cmSql = "SELECT COURSEID, COST FROM MENU_COURSE";
$cmStid = oci_parse($conn, $cmSql);
@oci_execute($cmStid);
while ($cmRow = oci_fetch_assoc($cmStid)) {
    $courseMenuCosts[$cmRow['COURSEID']] = floatval($cmRow['COST'] ?? 0);
}

// ===============================
// คำนวณรายได้รวม
// ===============================
$sqlTotalSales = "
    SELECT COALESCE(SUM(TOTALPRICE), 0) AS TOTAL_SALES
    FROM TRANSACTION
    WHERE ORDERDATE >= TO_DATE(:d,'YYYY-MM-DD')
      AND ORDERDATE < TO_DATE(:d,'YYYY-MM-DD') + 1
";
$stidTotalSales = oci_parse($conn, $sqlTotalSales);
oci_bind_by_name($stidTotalSales, ':d', $selectedDate);
oci_execute($stidTotalSales);
$rowTotalSales = oci_fetch_assoc($stidTotalSales);
$salesToday = floatval($rowTotalSales['TOTAL_SALES'] ?? 0);
oci_free_statement($stidTotalSales);

// ===============================
// คำนวณกำไรหลัง GP แยกตาม MENUTYPEID
// ===============================
$profitAlacarteAfterGP = 0;
$profitOmakaseAfterGP = 0;

$sqlTransactions = "
    SELECT t.ORDERID, t.TOTALPRICE, t.MENUTYPEID, t.COURSEID
    FROM TRANSACTION t
    WHERE t.ORDERDATE >= TO_DATE(:d,'YYYY-MM-DD')
      AND t.ORDERDATE < TO_DATE(:d,'YYYY-MM-DD') + 1
";
$stidTrans = oci_parse($conn, $sqlTransactions);
oci_bind_by_name($stidTrans, ':d', $selectedDate);
oci_execute($stidTrans);

while ($trans = oci_fetch_assoc($stidTrans)) {
    $menutypeid = $trans['MENUTYPEID'];
    $courseid = $trans['COURSEID'];
    $orderid = $trans['ORDERID'];
    $totalprice = floatval($trans['TOTALPRICE']);
    
    // ใช้ต้นทุนวัตถุดิบจริงสำหรับทุกประเภท
    $cost = isset($orderCosts[$orderid]) ? $orderCosts[$orderid] : 0;
    $profitBefore = $totalprice - $cost;
    
    if ($menutypeid == 2 || $menutypeid == 3) { // Omakase / Buffet
        $profitAfter = $profitBefore * (1 - $gp_omakase);
        $profitOmakaseAfterGP += $profitAfter;
        
    } else { // A la carte
        $profitAfter = $profitBefore * (1 - $gp_alacarte);
        $profitAlacarteAfterGP += $profitAfter;
    }
}

// กำไรหลัง GP รวมทั้งหมด
$profitNetAfterGP = $profitAlacarteAfterGP + $profitOmakaseAfterGP;

echo "<!-- 
DEBUG PROFIT CALCULATION (NEW CODE):
GP_Omakase: $gp_omakase
ProfitOmakaseAfterGP: $profitOmakaseAfterGP
ProfitAlacarteAfterGP: $profitAlacarteAfterGP
ProfitNetAfterGP: $profitNetAfterGP
-->";

// ===============================
// ยอดขายเมนู
// ===============================
$sqlMenuThisDay = "
    SELECT m.MENUNAME, mt.TYPENAME, t.MENUTYPEID, t.COURSEID, oi.TYPE,
        SUM(oi.QUANTITY) as TOTAL_QTY,
        SUM(oi.QUANTITY * m.PRICE_ALACARTE) as TOTAL_PRICE
    FROM ORDER_ITEM oi
    JOIN MENU m ON oi.MENUID = m.MENUID
    JOIN TRANSACTION t ON oi.ORDERID = t.ORDERID
    LEFT JOIN MENU_TYPE mt ON t.MENUTYPEID = mt.MENUTYPEID
    WHERE t.ORDERDATE >= TO_DATE(:d,'YYYY-MM-DD')
      AND t.ORDERDATE < TO_DATE(:d,'YYYY-MM-DD') + 1
    GROUP BY m.MENUNAME, mt.TYPENAME, t.MENUTYPEID, t.COURSEID, oi.TYPE
    ORDER BY TOTAL_PRICE DESC
";

$stidMenuThisDay = oci_parse($conn, $sqlMenuThisDay);
oci_bind_by_name($stidMenuThisDay, ':d', $selectedDate);
oci_execute($stidMenuThisDay);

$menuThisDay = [];
$totalMenuToday = 0;
$menuAlacarte = [];

while ($row = oci_fetch_assoc($stidMenuThisDay)) {
    $menuThisDay[] = $row;
    $totalMenuToday += $row['TOTAL_QTY'];
    
    if ($row['MENUTYPEID'] == 1) {
        $menuAlacarte[] = $row;
    }
}

// ===============================
// ดึงยอดขายแยกตาม MENUTYPEID
// ===============================
$sqlSalesAlacarte = "
    SELECT COALESCE(SUM(TOTALPRICE), 0) as TOTAL 
    FROM TRANSACTION 
    WHERE ORDERDATE >= TO_DATE(:d,'YYYY-MM-DD')
      AND ORDERDATE < TO_DATE(:d,'YYYY-MM-DD') + 1
    AND MENUTYPEID = 1
";
$stidAlacarte = oci_parse($conn, $sqlSalesAlacarte);
oci_bind_by_name($stidAlacarte, ':d', $selectedDate);
oci_execute($stidAlacarte);
$salesAlacarteRow = oci_fetch_assoc($stidAlacarte);
$salesAlacarteTotal = floatval($salesAlacarteRow['TOTAL'] ?? 0);
oci_free_statement($stidAlacarte);

$sqlSalesOmakase = "
    SELECT COALESCE(SUM(TOTALPRICE), 0) as TOTAL 
    FROM TRANSACTION 
    WHERE ORDERDATE >= TO_DATE(:d,'YYYY-MM-DD')
      AND ORDERDATE < TO_DATE(:d,'YYYY-MM-DD') + 1
    AND MENUTYPEID IN (2, 3)
";
$stidOmakase = oci_parse($conn, $sqlSalesOmakase);
oci_bind_by_name($stidOmakase, ':d', $selectedDate);
oci_execute($stidOmakase);
$salesOmakaseRow = oci_fetch_assoc($stidOmakase);
$salesOmakaseTotal = floatval($salesOmakaseRow['TOTAL'] ?? 0);
oci_free_statement($stidOmakase);

// ===============================
// Omakase Orders พร้อม Extra
// ===============================
$sqlOmakaseOrders = "
    SELECT t.ORDERID, mc.COURSENAME, mc.COURSEPRICE, mc.COST AS COURSE_COST, t.TOTALPRICE
    FROM TRANSACTION t
    JOIN MENU_COURSE mc ON t.COURSEID = mc.COURSEID
    WHERE t.ORDERDATE >= TO_DATE(:d,'YYYY-MM-DD')
      AND t.ORDERDATE < TO_DATE(:d,'YYYY-MM-DD') + 1
    AND t.MENUTYPEID = 2
    ORDER BY t.ORDERID
";

$stidOmakaseOrders = oci_parse($conn, $sqlOmakaseOrders);
oci_bind_by_name($stidOmakaseOrders, ':d', $selectedDate);
oci_execute($stidOmakaseOrders);

$omakaseOrders = [];
while ($row = oci_fetch_assoc($stidOmakaseOrders)) {
    $orderid = $row['ORDERID'];
    
    $sqlExtra = "
        SELECT m.MENUNAME, oi.QUANTITY
        FROM ORDER_ITEM oi
        JOIN MENU m ON oi.MENUID = m.MENUID
        WHERE oi.ORDERID = :oid AND oi.TYPE = 'extra'
        ORDER BY oi.MENUID
    ";
    $stidExtra = oci_parse($conn, $sqlExtra);
    oci_bind_by_name($stidExtra, ':oid', $orderid);
    oci_execute($stidExtra);
    
    $extraMenus = [];
    while ($extra = oci_fetch_assoc($stidExtra)) {
        $extraMenus[] = $extra;
    }
    
    $row['EXTRA_MENUS'] = $extraMenus;
    $omakaseOrders[] = $row;
}

// เมนูเมื่อวาน
$sqlMenuYesterday = "
    SELECT SUM(oi.QUANTITY) AS TOTAL_QTY
    FROM ORDER_ITEM oi
    JOIN TRANSACTION t ON oi.ORDERID = t.ORDERID
    WHERE t.ORDERDATE >= TO_DATE(:d,'YYYY-MM-DD')
      AND t.ORDERDATE < TO_DATE(:d,'YYYY-MM-DD') + 1
";
$stidMenuYesterday = oci_parse($conn, $sqlMenuYesterday);
oci_bind_by_name($stidMenuYesterday, ':d', $yesterday);
oci_execute($stidMenuYesterday);
$totalMenuYesterday = oci_fetch_assoc($stidMenuYesterday)['TOTAL_QTY'] ?? 0;
$menuChange = $totalMenuYesterday > 0 ? (($totalMenuToday - $totalMenuYesterday) / $totalMenuYesterday) * 100 : 0;

// ยอดขายเมื่อวาน
$sqlSales = "
    SELECT SUM(TOTALPRICE) AS TOTAL
    FROM TRANSACTION
    WHERE ORDERDATE >= TO_DATE(:d,'YYYY-MM-DD')
      AND ORDERDATE < TO_DATE(:d,'YYYY-MM-DD') + 1
";

$stidSalesYesterday = oci_parse($conn, $sqlSales);
oci_bind_by_name($stidSalesYesterday, ':d', $yesterday);
oci_execute($stidSalesYesterday);
$salesYesterday = oci_fetch_assoc($stidSalesYesterday)['TOTAL'] ?? 0;
$salesChange = $salesYesterday > 0 ? (($salesToday - $salesYesterday) / $salesYesterday) * 100 : 0;

// ลูกค้าที่มาใช้บริการ
$sqlMember = "
    SELECT SUM(CASE WHEN CUSTOMERID IS NOT NULL THEN 1 ELSE 0 END) AS MEMBERS,
           SUM(CASE WHEN CUSTOMERID IS NULL THEN 1 ELSE 0 END) AS GUESTS
    FROM TRANSACTION
    WHERE ORDERDATE >= TO_DATE(:d,'YYYY-MM-DD')
      AND ORDERDATE < TO_DATE(:d,'YYYY-MM-DD') + 1
";
$stidMember = oci_parse($conn, $sqlMember);
oci_bind_by_name($stidMember, ':d', $selectedDate);
oci_execute($stidMember);
$rowMember = oci_fetch_assoc($stidMember);
$totalMembers = $rowMember['MEMBERS'] ?? 0;
$totalGuests = $rowMember['GUESTS'] ?? 0;

// ===============================
// วัตถุดิบที่ใช้ไป
// ===============================
$ingredientMap = [];

// 1. วัตถุดิบจาก ORDER_ITEM
$sqlIngredientsFromOrders = "
    SELECT i.INGREDIENTID, i.INGREDIENTNAME,
           SUM(r.COST * oi.QUANTITY) AS TOTAL_PRICE,
           SUM(r.QTYUSED * oi.QUANTITY) AS USED_QTY,
           i.UNIT,
           AVG(r.COST) AS AVG_COST_PER_USE
    FROM ORDER_ITEM oi
    JOIN RECIPE r ON oi.MENUID = r.MENUID
    JOIN INGREDIENT i ON r.INGREDIENTID = i.INGREDIENTID
    JOIN TRANSACTION t ON oi.ORDERID = t.ORDERID
    WHERE t.ORDERDATE >= TO_DATE(:d,'YYYY-MM-DD')
      AND t.ORDERDATE < TO_DATE(:d,'YYYY-MM-DD') + 1
    GROUP BY i.INGREDIENTID, i.INGREDIENTNAME, i.UNIT
";

$stidIngOrders = oci_parse($conn, $sqlIngredientsFromOrders);
oci_bind_by_name($stidIngOrders, ':d', $selectedDate);
oci_execute($stidIngOrders);

while ($row = oci_fetch_assoc($stidIngOrders)) {
    $ingId = $row['INGREDIENTID'];
    $ingredientMap[$ingId] = [
        'INGREDIENTNAME' => $row['INGREDIENTNAME'],
        'TOTAL_PRICE' => floatval($row['TOTAL_PRICE'] ?? 0),
        'USED_QTY' => floatval($row['USED_QTY'] ?? 0),
        'UNIT' => $row['UNIT'],
        'AVG_COST_PER_USE' => floatval($row['AVG_COST_PER_USE'] ?? 0),
        'SOURCE' => 'order'
    ];
}

// 2. วัตถุดิบจากคอร์ส
$sqlIngFromCourses = "
    SELECT i.INGREDIENTID, i.INGREDIENTNAME, i.UNIT,
           r.COST, r.QTYUSED,
           cm.QUANTITY AS MENU_QTY_IN_COURSE,
           t.COURSEID
    FROM TRANSACTION t
    JOIN COURSE_MENU cm ON t.COURSEID = cm.COURSEID
    JOIN RECIPE r ON cm.MENUID = r.MENUID
    JOIN INGREDIENT i ON r.INGREDIENTID = i.INGREDIENTID
    WHERE t.ORDERDATE >= TO_DATE(:d,'YYYY-MM-DD')
      AND t.ORDERDATE < TO_DATE(:d,'YYYY-MM-DD') + 1
      AND t.MENUTYPEID IN (2, 3)
";

$stidIngCourses = oci_parse($conn, $sqlIngFromCourses);
oci_bind_by_name($stidIngCourses, ':d', $selectedDate);
oci_execute($stidIngCourses);

while ($row = oci_fetch_assoc($stidIngCourses)) {
    $ingId = $row['INGREDIENTID'];
    $courseid = $row['COURSEID'];
    $cost = floatval($row['COST'] ?? 0);
    $qtyUsed = floatval($row['QTYUSED'] ?? 0);
    $menuQtyInCourse = intval($row['MENU_QTY_IN_COURSE'] ?? 1);
    
    // นับจำนวนคนที่สั่งคอร์สนี้
    $personSql = "SELECT NVL(SUM(os.PERSON_COUNT), 1) AS TOTAL_PERSONS 
                  FROM TRANSACTION t
                  JOIN ORDER_SECTION os ON t.ORDERID = os.ORDERID
                  WHERE t.ORDERDATE >= TO_DATE(:d2,'YYYY-MM-DD')
                    AND t.ORDERDATE < TO_DATE(:d2,'YYYY-MM-DD') + 1
                    AND t.COURSEID = :cid
                    AND t.MENUTYPEID IN (2, 3)
                    AND os.MENUTYPEID IN (2, 3)";
    $personStid = oci_parse($conn, $personSql);
    oci_bind_by_name($personStid, ':d2', $selectedDate);
    oci_bind_by_name($personStid, ':cid', $courseid);
    oci_execute($personStid);
    $personRow = oci_fetch_assoc($personStid);
    $totalPersonsInCourse = intval($personRow['TOTAL_PERSONS'] ?? 1);
    oci_free_statement($personStid);
    
    if ($totalPersonsInCourse == 0) $totalPersonsInCourse = 1;
    
    $totalPrice = $cost * $menuQtyInCourse * $totalPersonsInCourse;
    $totalQty = $qtyUsed * $menuQtyInCourse * $totalPersonsInCourse;
    
    if (isset($ingredientMap[$ingId])) {
        $ingredientMap[$ingId]['TOTAL_PRICE'] += $totalPrice;
        $ingredientMap[$ingId]['USED_QTY'] += $totalQty;
        $ingredientMap[$ingId]['SOURCE'] = 'both';
    } else {
        $ingredientMap[$ingId] = [
            'INGREDIENTNAME' => $row['INGREDIENTNAME'],
            'TOTAL_PRICE' => $totalPrice,
            'USED_QTY' => $totalQty,
            'UNIT' => $row['UNIT'],
            'AVG_COST_PER_USE' => $cost,
            'SOURCE' => 'course'
        ];
    }
}

$ingredients = array_values($ingredientMap);
usort($ingredients, function($a, $b) {
    return $b['TOTAL_PRICE'] <=> $a['TOTAL_PRICE'];
});

// ต้นทุนคอร์ส Omakase
$totalCourseCost = 0;
$courseItems = [];
foreach ($omakaseOrders as $order) {
    $courseCost = floatval($order['COURSE_COST'] ?? 0);
    $totalCourseCost += $courseCost;
    if ($courseCost > 0) {
        $courseItems[] = [
            'NAME' => $order['COURSENAME'],
            'COST' => $courseCost,
            'QTY' => 1
        ];
    }
}

// นับจำนวนประเภทคอร์ส
$uniqueCourseNames = [];
foreach ($omakaseOrders as $order) {
    $uniqueCourseNames[$order['COURSENAME']] = true;
}
$uniqueCourseCount = count($uniqueCourseNames);

// รวมต้นทุนสำหรับแสดงในส่วนล่าง
$totalIngredientCostHeader = 0;
foreach ($ingredients as $ing) {
    $cost = floatval($ing['TOTAL_PRICE'] ?? 0);
    if ($cost > 0) {
        $totalIngredientCostHeader += $cost;
    }
}

$totalAllCostHeader = $totalIngredientCostHeader + $totalCourseCost;
$totalSales = $salesAlacarteTotal + $salesOmakaseTotal;
$totalAllCost = $totalAllCostHeader; // ใช้ค่าจากการคำนวณวัตถุดิบจริง
$profitNetBeforeGP = $salesToday - $totalAllCost;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>รายงานสรุปรายวัน</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        .summary-table-wrapper {
            margin: 15px 0 25px 0;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .summary-table td {
            border: 1px solid #000;
            padding: 10px 12px;
            vertical-align: middle;
        }

        .summary-label {
            font-size: 13px;
            color: #333;
        }

        .summary-value {
            font-size: 18px;
            font-weight: 600;
            margin-top: 4px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Sarabun', Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .report-container { max-width: 1500px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 1px rgba(0,0,0,0.1); }
        
        .doc-header {
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-family: "Sarabun", Arial, sans-serif;
        }

        .header-top {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo img {
            width: 80px;
            height: auto;
        }

        .company-info {
            flex: 1;
        }

        .company-name {
            font-size: 20px;
            font-weight: bold;
        }

        .company-detail {
            font-size: 12px;
            line-height: 1.4;
        }

        .doc-title-box {
            text-align: right;
            min-width: 220px;
        }

        .doc-title {
            font-size: 18px;
            font-weight: bold;
        }

        .doc-subtitle {
            font-size: 12px;
            color: #555;
        }

        .doc-row {
            margin-top: 5px;
            font-size: 13px;
        }

        .doc-row span {
            margin-right: 6px;
        }

        .header-bottom {
            margin-top: 10px;
            display: flex;
            gap: 40px;
            font-size: 13px;
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .card-title { font-size: 18px; font-weight: bold; color: #2c3e50; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .chart-container-small { position: relative; height: 200px; margin-top: 20px; }
        .comparison-new { display: flex; justify-content: space-between; align-items: center; padding: 20px 10px; }
        .comparison-left { flex: 1; }
        .comparison-right { flex: 1; text-align: right; }
        .big-number { font-size: 48px; font-weight: bold; margin-bottom: 10px; }
        .big-number.up { color: #27ae60; }
        .big-number.down { color: #e74c3c; }
        .change-badge-new { display: inline-block; padding: 6px 16px; border-radius: 20px; font-size: 16px; font-weight: bold; }
        .change-badge-new.up { background: #d4edda; color: #27ae60; }
        .change-badge-new.down { background: #f8d7da; color: #e74c3c; }
        .mini-chart { height: 60px; margin-bottom: 10px; }
        .comparison-text { font-size: 14px; color: #7f8c8d; }
        .comparison-text strong { color: #2c3e50; }
        .member-chart {
            grid-column: span 1;
        }
        .list-container { max-height: 600px; overflow-y: auto; }
        .list-item { display: flex; justify-content: space-between; padding: 12px; border-bottom: 1px solid #ecf0f1; align-items: center; transition: background 0.2s; }
        .list-item:hover { background: #f8f9fa; }
        .item-rank { font-weight: bold; color: #3498db; margin-right: 12px; min-width: 30px; font-size: 18px; }
        .item-name { flex: 1; font-size: 15px; }
        .item-value { background: #3498db; color: white; padding: 6px 14px; border-radius: 12px; font-size: 14px; font-weight: bold; margin-left: 10px; }
        .ingredient-value { background: #e67e22; }
        .course-value { background: #9b59b6; }
        .btn-container { margin-top: 30px; display: flex; justify-content: flex-end; gap: 15px; }
        .btn { padding: 12px 30px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-print { background: #3498db; color: white; }
        .btn-back { background: #95a5a6; color: white; }
        .empty-state { text-align: center; padding: 40px; color: #95a5a6; font-style: italic; }
        .count-badge { background: #3498db; color: white; padding: 4px 12px; border-radius: 12px; font-size: 14px; font-weight: bold; margin-left: 8px; }
        
        .source-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 8px;
        }
        .source-order { background: #e3f2fd; color: #1976d2; }
        .source-course { background: #f3e5f5; color: #7b1fa2; }
        .source-both { background: #fff3e0; color: #f57c00; }
        
        @media print {
            @page {
                size: A4;
                margin: 12mm;
            }

            html, body {
                width: 210mm;
                margin: 0;
                padding: 0;
            }

            .report-container {
                transform: scale(0.8);
                transform-origin: top left;
                width: 125%;
            }

            canvas {
                max-width: 100% !important;
                height: auto !important;
            }

            .chart-container-small,
            .mini-chart {
                width: 100% !important;
                overflow: hidden;
            }

            .list-container {
                max-height: none !important;
                overflow: visible !important;
            }

            .btn-container {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="report-container">
    
    <div class="doc-header">
        <div class="header-top">
            <div class="logo">
                <img src="img/3769.jpg" alt="logo">
            </div>

            <div class="company-info">
                <div class="company-name">BIGURI Omakase & Buffet</div>
                <div class="company-detail">
                    ที่อยู่: 52 ซอยเสือใหญ่อุทิศ(รัชดาภิเษก36 แยก11) แขวงจันทรเกษม เขตจตุจักร กรุงเทพมหานคร 10900 <br>
                    โทร: 062-953-2761 &nbsp;|&nbsp; ทะเบียนพาณิชย์เลขที่: 1549900139970
                </div>
            </div>

            <div class="doc-title-box">
                <div class="doc-title">สรุปยอดขายประจำวัน</div>
                <div class="doc-subtitle">Daily Report</div>
                <div class="doc-row">
                    <span>วันที่:</span>
                    <strong><?= htmlspecialchars($displayDate) ?></strong>
                </div>
            </div>  
        </div>
    </div>

    <div class="header-bottom">
        <div class="summary-table-wrapper">
            <table class="summary-table">
                <tr>
                    <td>
                        <div class="summary-label">จำนวนลูกค้าสมาชิก</div>
                        <div class="summary-value">
                            <?= number_format($totalMembers) ?> คน
                        </div>
                    </td>
                    <td>
                        <div class="summary-label">ยอดรวม A la carte</div>
                        <div class="summary-value">
                            <?= number_format($salesAlacarteTotal, 2) ?> ฿
                        </div>
                        <small style="color:#95a5a6">กำไรหลัง GP: <?= number_format($profitAlacarteAfterGP, 2) ?> ฿</small>
                    </td>
                    <td>
                        <div class="summary-label">รายได้รวม</div>
                        <div class="summary-value">
                            <?= number_format($salesToday, 2) ?> ฿
                        </div>
                    </td>
                    <td>
                        <div class="summary-label">กำไรก่อนหัก GP</div>
                        <div class="summary-value">
                            <?= number_format($profitNetBeforeGP, 2) ?> ฿
                        </div>
                        <small style="color:#95a5a6">(<?= number_format($salesToday, 2) ?> - <?= number_format($totalAllCost, 2) ?>)</small>
                    </td>
                </tr>

                <tr>
                    <td>
                        <div class="summary-label">จำนวนลูกค้าไม่ใช่สมาชิก</div>
                        <div class="summary-value">
                            <?= number_format($totalGuests) ?> คน
                        </div>
                    </td>
                    <td>
                        <div class="summary-label">ยอดรวม Buffet</div>
                        <div class="summary-value">
                            <?= number_format($salesOmakaseTotal, 2) ?> ฿
                        </div>
                        <small style="color:#95a5a6">กำไรหลัง GP: <?= number_format($profitOmakaseAfterGP, 2) ?> ฿</small>
                    </td>
                    <td>
                        <div class="summary-label">ต้นทุนทั้งหมด</div>
                        <div class="summary-value">
                            <?= number_format($totalAllCost, 2) ?> ฿
                        </div>
                    </td>
                    <td>
                        <div class="summary-label">กำไรหลังหัก GP</div>
                        <div class="summary-value">
                           <?= number_format($profitNetAfterGP, 2) ?> ฿
                           <?php 
                           $profitPercentage = ($salesToday > 0) ? ($profitNetAfterGP / $salesToday) * 100 : 0;
                           ?>
                           <span style="font-size: 14px; color: #27ae60; margin-left: 8px;">
                               (<?= number_format($profitPercentage, 1) ?>%)
                           </span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="grid-container">
        <!-- ยอดขายเมนู -->
        <div class="card">
            <div class="card-title">🍽️ ยอดขายเมนู (จำนวนชิ้น)</div>
            <div class="comparison-new">
                <div class="comparison-left">
                    <div class="big-number <?= $menuChange >= 0 ? 'up' : 'down' ?>">
                        <?= number_format($totalMenuToday) ?>
                    </div>
                    <div class="change-badge-new <?= $menuChange >= 0 ? 'up' : 'down' ?>">
                        <?= $menuChange >= 0 ? '↑' : '↓' ?> <?= number_format(abs($menuChange), 1) ?>%
                    </div>
                </div>
                <div class="comparison-right">
                    <div class="mini-chart"><canvas id="menuSparkline"></canvas></div>
                    <div class="comparison-text">เมื่อวาน: <strong><?= number_format($totalMenuYesterday) ?></strong> ชิ้น</div>
                </div>
            </div>
            <div class="chart-container-small"><canvas id="menuComparisonChart"></canvas></div>
        </div>
        
        <!-- ยอดขายรวม -->
        <div class="card">
            <div class="card-title">💰 ยอดขายรวม (บาท)</div>
            <div class="comparison-new">
                <div class="comparison-left">
                    <div class="big-number <?= $salesChange >= 0 ? 'up' : 'down' ?>">
                        <?= number_format($salesToday, 0) ?>
                    </div>
                    <div class="change-badge-new <?= $salesChange >= 0 ? 'up' : 'down' ?>">
                        <?= $salesChange >= 0 ? '↑' : '↓' ?> <?= number_format(abs($salesChange), 1) ?>%
                    </div>
                </div>
                <div class="comparison-right">
                    <div class="mini-chart"><canvas id="salesSparkline"></canvas></div>
                    <div class="comparison-text">เมื่อวาน: <strong><?= number_format($salesYesterday, 0) ?></strong> ฿</div>
                </div>
            </div>
            <div class="chart-container-small"><canvas id="salesComparisonChart"></canvas></div>
        </div>
        
        <!-- สัดส่วนลูกค้า -->
        <div class="card member-chart">
            <div class="card-title">👥 สัดส่วนลูกค้า</div>
            <div class="chart-container-small"><canvas id="memberPieChart"></canvas></div>
            <div style="display:flex;gap:30px;margin-top:20px;justify-content:center">
                <div style="display:flex;align-items:center;gap:8px">
                    <div style="width:20px;height:20px;background:#3498db;border-radius:4px"></div>
                    <span>สมาชิก: <strong><?= number_format($totalMembers) ?></strong> คน</span>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    <div style="width:20px;height:20px;background:#95a5a6;border-radius:4px"></div>
                    <span>Guest: <strong><?= number_format($totalGuests) ?></strong> คน</span>
                </div>
            </div>
        </div>
        
        <!-- เมนู A la carte -->
        <div class="card" style="grid-column: span 3;">
            <div class="card-title">🍽️ เมนู A la carte<span class="count-badge"><?= count($menuAlacarte) ?> รายการ</span></div>
            <div class="list-container">
                <?php if (count($menuAlacarte) > 0): ?>
                    <?php foreach ($menuAlacarte as $index => $menu): ?>
                        <div class="list-item">
                            <span class="item-rank">#<?= $index + 1 ?></span>
                            <span class="item-name">
                                <?= htmlspecialchars($menu['MENUNAME']) ?>
                                <small style="color:#95a5a6;margin-left:8px">(<?= number_format($menu['TOTAL_QTY']) ?> ชิ้น)</small>
                            </span>
                            <span class="item-value"><?= number_format(floatval($menu['TOTAL_PRICE']), 2) ?> ฿</span>
                        </div>
                    <?php endforeach; ?>
                    <div style="background:#e3f2fd;padding:15px;border-radius:8px;margin-top:15px;text-align:center;border:2px solid #3498db">
                        <div style="font-size:14px;color:#3498db;margin-bottom:5px">✅ ยอดรวม A la carte</div>
                        <div style="font-size:28px;font-weight:bold;color:#3498db"><?= number_format($salesAlacarteTotal, 2) ?> ฿</div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">ไม่มีเมนู A la carte วันนี้</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- เมนู Omakase -->
        <div class="card" style="grid-column: span 3;">
            <div class="card-title">🍱 เมนู Omakase Buffet<span class="count-badge"><?= $uniqueCourseCount ?> ประเภท (<?= count($omakaseOrders) ?> คอร์ส)</span></div>
            <div class="list-container">
                <?php if (count($omakaseOrders) > 0): 
                    $groupedCourses = [];
                    $totalExtraCount = 0;
                    
                    foreach ($omakaseOrders as $order) {
                        $courseName = $order['COURSENAME'];
                        $coursePrice = floatval($order['COURSEPRICE']);
                        
                        if (!isset($groupedCourses[$courseName])) {
                            $groupedCourses[$courseName] = [
                                'NAME' => $courseName,
                                'COUNT' => 0,
                                'TOTAL_PRICE' => 0,
                                'EXTRAS' => []
                            ];
                        }
                        
                        $groupedCourses[$courseName]['COUNT']++;
                        $groupedCourses[$courseName]['TOTAL_PRICE'] += $coursePrice;
                        
                        foreach ($order['EXTRA_MENUS'] as $extra) {
                            $extraName = $extra['MENUNAME'];
                            $extraQty = intval($extra['QUANTITY']);
                            
                            if (!isset($groupedCourses[$courseName]['EXTRAS'][$extraName])) {
                                $groupedCourses[$courseName]['EXTRAS'][$extraName] = 0;
                            }
                            $groupedCourses[$courseName]['EXTRAS'][$extraName] += $extraQty;
                            $totalExtraCount++;
                        }
                    }
                    
                    $index = 1;
                    foreach ($groupedCourses as $course):
                ?>
                    <div class="list-item">
                        <span class="item-rank">#<?= $index++ ?></span>
                        <span class="item-name">
                            <?= htmlspecialchars($course['NAME']) ?>
                            <small style="color:#95a5a6;margin-left:8px">(<?= $course['COUNT'] ?> คอร์ส)</small>
                        </span>
                        <span class="item-value" style="background:#f39c12"><?= number_format($course['TOTAL_PRICE'], 2) ?> ฿</span>
                    </div>
                    <?php foreach ($course['EXTRAS'] as $extraName => $extraQty): ?>
                        <div class="list-item" style="background:#fff9e6;padding-left:60px;border-left:3px solid #f39c12">
                            <span style="color:#f39c12;margin-right:8px">└</span>
                            <span class="item-name">
                                <?= htmlspecialchars($extraName) ?>
                                <small style="color:#95a5a6;margin-left:8px">(<?= $extraQty ?> ชิ้น)</small>
                            </span>
                            <span class="item-value" style="background:#9b59b6;font-size:12px">Free</span>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                    <div style="background:#fff3e0;padding:15px;border-radius:8px;margin-top:15px;text-align:center;border:2px solid #f39c12">
                        <div style="font-size:14px;color:#f39c12;margin-bottom:5px">✅ ยอดรวม Omakase Buffet</div>
                        <div style="font-size:28px;font-weight:bold;color:#f39c12"><?= number_format($salesOmakaseTotal, 2) ?> ฿</div>
                        <div style="font-size:12px;color:#95a5a6;margin-top:5px">
                            (<?= count($omakaseOrders) ?> คอร์สทั้งหมด<?= $totalExtraCount > 0 ? ', รวมเมนู Extra ' . $totalExtraCount . ' รายการที่ไม่คิดเงิน' : '' ?>)
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">ไม่มี Omakase/Buffet วันนี้</div>
                <?php endif; ?>
            </div>
        </div>
    
    <!-- ต้นทุนวัตถุดิบและคอร์ส -->
    <div class="card" style="grid-column: span 3;">
        <div class="card-title">📦 ต้นทุนวัตถุดิบและคอร์สที่ใช้ไปวันนี้<span class="count-badge"><?= count($ingredients) + count($courseItems) ?> รายการ</span></div>
        <div class="list-container" style="max-height:none">
            <?php 
            $totalIngredientPrice = 0;
            $missingCostCount = 0;
            $missingCostItems = [];
            
            $allCostItems = [];
            
            foreach ($ingredients as $ing) {
                $totalPrice = floatval($ing['TOTAL_PRICE'] ?? 0);
                $usedQty = floatval($ing['USED_QTY'] ?? 0);
                $avgCost = floatval($ing['AVG_COST_PER_USE'] ?? 0);
                
                if ($totalPrice > 0 && $avgCost > 0) {
                    $totalIngredientPrice += $totalPrice;
                    $allCostItems[] = [
                        'TYPE' => 'ingredient',
                        'NAME' => $ing['INGREDIENTNAME'],
                        'PRICE' => $totalPrice,
                        'QTY' => $usedQty,
                        'UNIT' => $ing['UNIT'],
                        'SOURCE' => $ing['SOURCE'] ?? 'order'
                    ];
                } else {
                    $missingCostCount++;
                    $missingCostItems[] = $ing['INGREDIENTNAME'];
                    $allCostItems[] = [
                        'TYPE' => 'ingredient',
                        'NAME' => $ing['INGREDIENTNAME'],
                        'PRICE' => 0,
                        'QTY' => $usedQty,
                        'UNIT' => $ing['UNIT'],
                        'NO_COST' => true,
                        'SOURCE' => $ing['SOURCE'] ?? 'order'
                    ];
                }
            }
            
            foreach ($courseItems as $course) {
                $allCostItems[] = [
                    'TYPE' => 'course',
                    'NAME' => $course['NAME'],
                    'PRICE' => $course['COST'],
                    'QTY' => $course['QTY'],
                    'UNIT' => 'คอร์ส'
                ];
            }
            
            $totalAllCostDisplay = $totalIngredientPrice + $totalCourseCost;
            
            if (count($allCostItems) > 0):
                foreach ($allCostItems as $index => $item):
                    if ($item['TYPE'] == 'ingredient'):
                        $sourceLabel = '';
                        $sourceClass = '';
                        if (isset($item['SOURCE'])) {
                            switch ($item['SOURCE']) {
                                case 'order':
                                    $sourceLabel = 'A la carte/Extra';
                                    $sourceClass = 'source-order';
                                    break;
                                case 'course':
                                    $sourceLabel = 'ในคอร์ส';
                                    $sourceClass = 'source-course';
                                    break;
                                case 'both':
                                    $sourceLabel = 'ทั้งสองประเภท';
                                    $sourceClass = 'source-both';
                                    break;
                            }
                        }
            ?>
                <div class="list-item" style="<?= isset($item['NO_COST']) ? 'background:#ffebee' : '' ?>">
                    <span class="item-rank">#<?= $index + 1 ?></span>
                    <span class="item-name">
                        🥬 <?= htmlspecialchars($item['NAME']) ?>
                        <?php if ($sourceLabel): ?>
                            <span class="source-badge <?= $sourceClass ?>"><?= $sourceLabel ?></span>
                        <?php endif; ?>
                        <small style="color:#95a5a6;margin-left:5px">(<?= number_format($item['QTY'], 2) ?> <?= htmlspecialchars($item['UNIT']) ?>)</small>
                    </span>
                    <span class="item-value <?= isset($item['NO_COST']) ? '' : 'ingredient-value' ?>">
                        <?= isset($item['NO_COST']) ? '<span style="color:#e74c3c;font-weight:bold">ยังไม่ตั้งราคา</span>' : number_format($item['PRICE'], 2) . ' ฿' ?>
                    </span>
                </div>
            <?php else: ?>
                <div class="list-item" style="background:#f3e5f5">
                    <span class="item-rank">#<?= $index + 1 ?></span>
                    <span class="item-name">
                        🍱 <?= htmlspecialchars($item['NAME']) ?>
                        <small style="color:#95a5a6;margin-left:5px">(<?= intval($item['QTY']) ?> <?= htmlspecialchars($item['UNIT']) ?>)</small>
                    </span>
                    <span class="item-value course-value"><?= number_format($item['PRICE'], 2) ?> ฿</span>
                </div>
            <?php 
                    endif;
                endforeach;
                
                if ($totalAllCostDisplay > 0):
            ?>
                <div style="background:#e8f5e9;padding:20px;border-radius:8px;margin-top:15px;text-align:center;border:3px solid #4caf50">
                    <div style="font-size:16px;color:#2e7d32;margin-bottom:8px;font-weight:600">💰 ต้นทุนรวมทั้งหมด</div>
                    <div style="font-size:36px;font-weight:bold;color:#2e7d32"><?= number_format($totalAllCostDisplay, 2) ?> ฿</div>
                    <div style="font-size:13px;color:#666;margin-top:8px">
                        วัตถุดิบ: <?= number_format($totalIngredientPrice, 2) ?> ฿ + 
                        คอร์ส: <?= number_format($totalCourseCost, 2) ?> ฿
                    </div>
                    <?php if ($salesToday > 0): 
                        $profitMargin = (($salesToday - $totalAllCostDisplay) / $salesToday) * 100;
                    ?>
                    <div style="font-size:14px;color:#2e7d32;margin-top:10px;padding-top:10px;border-top:1px solid rgba(46,125,50,0.3)">
                        💵 กำไรขั้นต้น: <?= number_format($salesToday - $totalAllCostDisplay, 2) ?> ฿ (<?= number_format($profitMargin, 1) ?>%)
                    </div>
                    <?php endif; ?>
                </div>
            <?php 
                endif;
                
                if ($missingCostCount > 0):
            ?>
                <div style="background:#fff9c4;padding:15px;border-radius:8px;margin-top:15px;border:2px dashed #fbc02d">
                    <div style="font-size:14px;color:#f57f17;margin-bottom:8px;font-weight:bold">⚠️ วัตถุดิบที่ยังไม่ได้ตั้งราคาต้นทุน (<?= $missingCostCount ?> รายการ)</div>
                    <div style="font-size:13px;color:#666;margin-bottom:10px">
                        <?php foreach ($missingCostItems as $name): ?>
                            <span style="background:#ffebee;padding:4px 8px;border-radius:4px;margin:2px;display:inline-block"><?= htmlspecialchars($name) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div style="font-size:12px;color:#95a5a6">กรุณาไปที่หน้า <a href="gp_cost_setup.php" style="color:#3498db;font-weight:bold">GP & Cost Setup</a> เพื่อตั้งค่าต้นทุน</div>
                </div>
            <?php 
                endif;
            else: 
            ?>
                <div class="empty-state">ไม่มีข้อมูลต้นทุนวันนี้</div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ยอดรวมทั้งหมด -->
    <div class="card" style="grid-column: span 3; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:white">
        <div style="text-align:center;padding:20px">
            <div style="font-size:18px;margin-bottom:10px;opacity:0.9">💎 ยอดขายเมนูรวมทั้งหมด</div>
            <div style="font-size:48px;font-weight:bold;margin-bottom:10px"><?= number_format($totalSales, 2) ?> ฿</div>
            <div style="font-size:14px;opacity:0.8">
                A la carte: <?= number_format($salesAlacarteTotal, 2) ?> ฿ + 
                Omakase Buffet: <?= number_format($salesOmakaseTotal, 2) ?> ฿
            </div>
            <div style="font-size:12px;opacity:0.7;margin-top:5px">
                รวม <?= count($menuAlacarte) + $uniqueCourseCount ?> รายการที่คิดเงิน 
                (A la carte: <?= count($menuAlacarte) ?> | Omakase: <?= $uniqueCourseCount ?> ประเภท, <?= count($omakaseOrders) ?> คอร์ส)
            </div>
        </div>
    </div>
</div>

<div class="btn-container">
    <a href="profit_list.php" class="btn btn-back">← กลับ</a>
    <button onclick="window.print()" class="btn btn-print">🖨️ พิมพ์รายงาน</button>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const data = {
        menuYesterday: <?= $totalMenuYesterday ?>,
        menuToday: <?= $totalMenuToday ?>,
        salesYesterday: <?= $salesYesterday ?>,
        salesToday: <?= $salesToday ?>,
        members: <?= $totalMembers ?>,
        guests: <?= $totalGuests ?>,
        menuChange: <?= $menuChange ?>,
        salesChange: <?= $salesChange ?>
    };

    new Chart(document.getElementById('menuComparisonChart'), {
        type: 'bar',
        data: {
            labels: ['เมื่อวาน', 'วันนี้'],
            datasets: [{
                data: [data.menuYesterday, data.menuToday],
                backgroundColor: ['#95a5a6', data.menuChange >= 0 ? '#27ae60' : '#e74c3c'],
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    new Chart(document.getElementById('salesComparisonChart'), {
        type: 'bar',
        data: {
            labels: ['เมื่อวาน', 'วันนี้'],
            datasets: [{
                data: [data.salesYesterday, data.salesToday],
                backgroundColor: ['#95a5a6', data.salesChange >= 0 ? '#27ae60' : '#e74c3c'],
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    new Chart(document.getElementById('menuSparkline'), {
        type: 'line',
        data: {
            labels: ['', ''],
            datasets: [{
                data: [data.menuYesterday, data.menuToday],
                borderColor: data.menuChange >= 0 ? '#27ae60' : '#e74c3c',
                backgroundColor: data.menuChange >= 0 ? 'rgba(39,174,96,0.1)' : 'rgba(231,76,60,0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            scales: { x: { display: false }, y: { display: false } }
        }
    });

    new Chart(document.getElementById('salesSparkline'), {
        type: 'line',
        data: {
            labels: ['', ''],
            datasets: [{
                data: [data.salesYesterday, data.salesToday],
                borderColor: data.salesChange >= 0 ? '#27ae60' : '#e74c3c',
                backgroundColor: data.salesChange >= 0 ? 'rgba(39,174,96,0.1)' : 'rgba(231,76,60,0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            scales: { x: { display: false }, y: { display: false } }
        }
    });

    new Chart(document.getElementById('memberPieChart'), {
        type: 'doughnut',
        data: {
            labels: ['สมาชิก', 'Guest'],
            datasets: [{
                data: [data.members, data.guests],
                backgroundColor: ['#3498db', '#95a5a6']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });
});
</script>

</body>
</html>