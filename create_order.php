    <?php 
    require 'connect.php';

    $sql = "SELECT NVL(MAX(ORDERID), 900000) AS MAXID FROM TRANSACTION";
    $stid = oci_parse($conn, $sql);
    oci_execute($stid);
    $row = oci_fetch_assoc($stid);
    $newOrderID = $row['MAXID'] + 1;

    $sqlType = "SELECT MENUTYPEID, TYPENAME FROM MENU_TYPE ORDER BY MENUTYPEID";
    $stidType = oci_parse($conn, $sqlType);
    oci_execute($stidType);

    $sqlMenu = "SELECT MENUID, MENUNAME, PRICE_ALACARTE, PRICE_OMAKASE, BARCODE, IMAGEPATH FROM MENU ORDER BY MENUID";
    $stidMenu = oci_parse($conn, $sqlMenu);
    oci_execute($stidMenu);

    $sqlCustomer = "SELECT c.CUSTOMERID, c.CUSTOMERNAME, ml.LEVELNAME, ml.DISCOUNT
                    FROM MEMBER c
                    LEFT JOIN MEMBER_LEVEL ml ON c.LEVELID = ml.LEVELID
                    ORDER BY c.CUSTOMERID";
    $stidCustomer = oci_parse($conn, $sqlCustomer);
    oci_execute($stidCustomer);

    // คำนวณวันที่ย้อนหลัง 4 เดือน
    $minDate = date('Y-m-d', strtotime('-4 months'));
    $maxDate = date('Y-m-d');
    $defaultDate = date('Y-m-d');
    $defaultTime = date('H:i');

    if (isset($_POST['create_order'])) {
        $orderid    = $_POST['orderid'];
        $customerid = $_POST['customerid'] ?? null;
        $items      = $_POST['menu_id'] ?? [];
        $qtys       = $_POST['qty'] ?? [];
        $sections   = $_POST['section'] ?? [];
        $menuTypeIds = $_POST['menutypeid'] ?? [];
        $courseIds  = $_POST['courseid'] ?? [];
        $personCounts = $_POST['person_count'] ?? [];
        $paymentMethod = $_POST['payment_method'];
        
        // รับวันที่และเวลาจากฟอร์ม
        $orderDate  = $_POST['order_date'];
        $orderTime  = $_POST['order_time'];

        // คำนวณส่วนลดสมาชิก
        $discountPercent = 0;
        if ($customerid) {
            $discountSql = "SELECT ml.DISCOUNT 
                            FROM MEMBER c
                            LEFT JOIN MEMBER_LEVEL ml ON c.LEVELID = ml.LEVELID
                            WHERE c.CUSTOMERID = :custid";
            $discountStid = oci_parse($conn, $discountSql);
            oci_bind_by_name($discountStid, ":custid", $customerid);
            oci_execute($discountStid);
            $discountRow = oci_fetch_assoc($discountStid);
            
            if ($discountRow && $discountRow['DISCOUNT']) {
                $discountPercent = $discountRow['DISCOUNT'];
            }
        }

        // จัดกลุ่มข้อมูลตาม section
        $sectionData = [];
        foreach ($menuTypeIds as $idx => $typeId) {
            $sectionNum = $idx + 1;
            $sectionData[$sectionNum] = [
                'menutypeid' => $typeId,
                'courseid' => isset($courseIds[$idx]) ? $courseIds[$idx] : null,
                'items' => []
            ];
        }

        // จัดกลุ่มเมนูตาม section
        for ($i = 0; $i < count($items); $i++) {
            if (empty($items[$i])) continue;
            
            $sectionNum = $sections[$i];
            $sectionData[$sectionNum]['items'][] = [
                'menuid' => $items[$i],
                'qty' => $qtys[$i]
            ];
        }

        // หาประเภทเมนูหลัก (ใช้อันแรก หรือถ้ามี Omakase ให้ใช้ Omakase)
        $primaryMenuType = $menuTypeIds[0];
        $primaryCourse = null;
        foreach ($sectionData as $section) {
            if ($section['menutypeid'] == 2) {
                $primaryMenuType = 2;
                $primaryCourse = $section['courseid'];
                break;
            }
        }

        // สร้าง Transaction หลัก
        // สร้าง Transaction หลัก
$sql = "INSERT INTO TRANSACTION 
    (ORDERID, CUSTOMERID, ORDERDATE, ORDERTIME, TOTALPRICE, DISCOUNTMEMBER, MENUTYPEID, COURSEID, PAYMENT_METHOD)
    VALUES 
    (:id, :custid, TO_DATE(:orderdate, 'YYYY-MM-DD'), :ordertime, 0, 0, :type, :course, :payment)";

$stid = oci_parse($conn, $sql);
oci_bind_by_name($stid, ":id", $orderid);
oci_bind_by_name($stid, ":custid", $customerid);
oci_bind_by_name($stid, ":orderdate", $orderDate);
oci_bind_by_name($stid, ":ordertime", $orderTime);
oci_bind_by_name($stid, ":type", $primaryMenuType);
oci_bind_by_name($stid, ":course", $primaryCourse);
oci_bind_by_name($stid, ":payment", $paymentMethod); // เพิ่มบรรทัดนี้
oci_execute($stid);

        $totalPrice = 0;
        $totalCost = 0;

        // ประมวลผลแต่ละ section
        foreach ($sectionData as $sectionNum => $section) {
            $menutypeid = $section['menutypeid'];
            $courseid = $section['courseid'];

            // ✅ บันทึกข้อมูล Section
            // ✅ บันทึกข้อมูล Section พร้อมจำนวนคน
$personCount = isset($personCounts[$sectionNum - 1]) ? intval($personCounts[$sectionNum - 1]) : 1;

$sqlSection = "INSERT INTO ORDER_SECTION (ORDERID, SECTION_NUMBER, MENUTYPEID, COURSEID, PERSON_COUNT)
               VALUES (:oid, :snum, :mtid, :cid, :pcount)";
$stidSection = oci_parse($conn, $sqlSection);
oci_bind_by_name($stidSection, ':oid', $orderid);
oci_bind_by_name($stidSection, ':snum', $sectionNum);
oci_bind_by_name($stidSection, ':mtid', $menutypeid);
oci_bind_by_name($stidSection, ':cid', $courseid);
oci_bind_by_name($stidSection, ':pcount', $personCount);
oci_execute($stidSection);
            $stidSection = oci_parse($conn, $sqlSection);
            oci_bind_by_name($stidSection, ':oid', $orderid);
            oci_bind_by_name($stidSection, ':snum', $sectionNum);
            oci_bind_by_name($stidSection, ':mtid', $menutypeid);
            oci_bind_by_name($stidSection, ':cid', $courseid);
            oci_execute($stidSection);

            // ถ้าเป็น Omakase และมี Course
if ($menutypeid == 2 && $courseid) {
    
    // ✅ ดึง personCount ครั้งเดียว ถูกต้อง
    $personCount = isset($personCounts[$sectionNum - 1]) ? intval($personCounts[$sectionNum - 1]) : 1;
    
    // เพิ่มราคา Course × จำนวนคน
    $cs = oci_parse($conn, "SELECT COURSEPRICE FROM MENU_COURSE WHERE COURSEID = :cid");
    oci_bind_by_name($cs, ":cid", $courseid);
    oci_execute($cs);
    $courseRow = oci_fetch_assoc($cs);
    if ($courseRow) {
        $totalPrice += ($courseRow["COURSEPRICE"] * $personCount);
    }

    // หักวัตถุดิบทุกเมนูใน Course × จำนวนคน
    $sqlCourseMenu = "SELECT cm.MENUID, cm.QUANTITY 
                      FROM COURSE_MENU cm 
                      WHERE cm.COURSEID = :courseid";
    $stidCourseMenu = oci_parse($conn, $sqlCourseMenu);
    oci_bind_by_name($stidCourseMenu, ':courseid', $courseid);
    oci_execute($stidCourseMenu);
    
    while ($courseMenu = oci_fetch_assoc($stidCourseMenu)) {
        $menuid      = $courseMenu['MENUID'];
        $menuQuantity = $courseMenu['QUANTITY'] * $personCount; // ✅ คูณจำนวนคน
        
        $sqlRecipe = "SELECT r.INGREDIENTID, r.QTYUSED, i.COST 
                      FROM RECIPE r 
                      JOIN INGREDIENT i ON r.INGREDIENTID = i.INGREDIENTID
                      WHERE r.MENUID = :menuid";
        $stidRecipe = oci_parse($conn, $sqlRecipe);
        oci_bind_by_name($stidRecipe, ':menuid', $menuid);
        oci_execute($stidRecipe);
        
        while ($recipe = oci_fetch_assoc($stidRecipe)) {
            $totalDeduct = $recipe['QTYUSED'] * $menuQuantity;
            
            // หักวัตถุดิบ
            $sqlUpdate = "UPDATE INGREDIENT 
                          SET QTYONHAND = QTYONHAND - :qty 
                          WHERE INGREDIENTID = :ingid";
            $stidUpdate = oci_parse($conn, $sqlUpdate);
            oci_bind_by_name($stidUpdate, ':qty', $totalDeduct);
            oci_bind_by_name($stidUpdate, ':ingid', $recipe['INGREDIENTID']);
            oci_execute($stidUpdate);
            
            $totalCost += ($recipe['QTYUSED'] * $recipe['COST'] * $menuQuantity);
        }
    }
}

            // ประมวลผลเมนูที่สั่งเพิ่ม
            foreach ($section['items'] as $item) {
                $mid = $item['menuid'];
                $quantity = $item['qty'];

                // กำหนด CHARGE_FLAG ตาม MENUTYPEID
                $chargeFlag = ($menutypeid == 1) ? 'Y' : 'N';

                // ✅ บันทึกใน ORDER_ITEM พร้อม SECTION_NUMBER และ CHARGE_FLAG
                $type = 'extra';
                $isql = "INSERT INTO ORDER_ITEM (ORDERID, MENUID, QUANTITY, TYPE, CHARGE_FLAG, SECTION_NUMBER) 
                        VALUES (:oid, :mid, :q, :type, :cflag, :snum)";
                $ist = oci_parse($conn, $isql);
                oci_bind_by_name($ist, ":oid", $orderid);
                oci_bind_by_name($ist, ":mid", $mid);
                oci_bind_by_name($ist, ":q", $quantity);
                oci_bind_by_name($ist, ":type", $type);
                oci_bind_by_name($ist, ":cflag", $chargeFlag);
                oci_bind_by_name($ist, ":snum", $sectionNum);
                oci_execute($ist);

                // คำนวณราคา (ถ้าเป็น A La Carte)
                // คำนวณราคา (ถ้าเป็น A La Carte)
// คำนวณราคา
if ($menutypeid == 1) {
    // A La Carte - ใช้ราคา PRICE_ALACARTE
    $ps = oci_parse($conn, "SELECT PRICE_ALACARTE FROM MENU WHERE MENUID = :mid");
    oci_bind_by_name($ps, ":mid", $mid);
    oci_execute($ps);
    $priceRow = oci_fetch_assoc($ps);
    $price = $priceRow ? $priceRow["PRICE_ALACARTE"] : 0;
    $totalPrice += ($price * $quantity);
} else if ($menutypeid == 2) {
    // Omakase - ใช้ราคา PRICE_OMAKASE เป็นค่าแลกซื้อ
    $ps = oci_parse($conn, "SELECT PRICE_OMAKASE FROM MENU WHERE MENUID = :mid");
    oci_bind_by_name($ps, ":mid", $mid);
    oci_execute($ps);
    $priceRow = oci_fetch_assoc($ps);
    $price = $priceRow ? $priceRow["PRICE_OMAKASE"] : 0;
    $totalPrice += ($price * $quantity);
}

                // หักวัตถุดิบและคำนวณต้นทุน
                $rsql = "SELECT r.INGREDIENTID, r.QTYUSED, i.COST 
                        FROM RECIPE r
                        JOIN INGREDIENT i ON r.INGREDIENTID = i.INGREDIENTID
                        WHERE r.MENUID = :mid";
                $rst = oci_parse($conn, $rsql);
                oci_bind_by_name($rst, ":mid", $mid);
                oci_execute($rst);

                while ($r = oci_fetch_assoc($rst)) {
                    $iid = $r["INGREDIENTID"];
                    $used = $r["QTYUSED"] * $quantity;
                    $cost = $r["COST"];
                    
                    // หักวัตถุดิบ
                    $usql = "UPDATE INGREDIENT SET QTYONHAND = QTYONHAND - :used WHERE INGREDIENTID = :iid";
                    $ust = oci_parse($conn, $usql);
                    oci_bind_by_name($ust, ":used", $used);
                    oci_bind_by_name($ust, ":iid", $iid);
                    oci_execute($ust);
                    
                    // เพิ่มต้นทุน
                    $totalCost += ($used * $cost);
                }
            }
        }

        // คำนวณส่วนลดสมาชิก
        $discountAmount = 0;
        if ($discountPercent > 0) {
            $discountAmount = $totalPrice * ($discountPercent / 100);
            $totalPrice = $totalPrice - $discountAmount;
        }

        // อัพเดทราคารวมและส่วนลด
        $up = oci_parse($conn, "UPDATE TRANSACTION SET TOTALPRICE = :t, DISCOUNTMEMBER = :disc WHERE ORDERID = :oid");
        oci_bind_by_name($up, ":t", $totalPrice);
        oci_bind_by_name($up, ":disc", $discountAmount);
        oci_bind_by_name($up, ":oid", $orderid);
        oci_execute($up);

        // คำนวณกำไร
        $gpPercent = 0;
        $sqlGP = "SELECT GP FROM MENU_TYPE_PRICE WHERE MENUTYPEID = :mtid";
        $stidGP = oci_parse($conn, $sqlGP);
        oci_bind_by_name($stidGP, ':mtid', $primaryMenuType);
        oci_execute($stidGP);
        $gpRow = oci_fetch_assoc($stidGP);
        if ($gpRow) {
            $gpPercent = $gpRow['GP'];
        }
        
        $profitBefore = $totalPrice - $totalCost;
        $gpAmount = $totalPrice * ($gpPercent / 100);
        $profitAfter = $profitBefore - $gpAmount;
        
        // บันทึกกำไร
        if ($primaryMenuType == 1) {
            $sqlProfit = "INSERT INTO ORDER_PROFIT 
                        (ORDERID, PROFITBEFOREGP, PROFITAFTERGP, PROFITBUFFETBEFOREGP, PROFITBUFFETAFTERGP)
                        VALUES (:oid, :pbefore, :pafter, 0, 0)";
        } else {
            $sqlProfit = "INSERT INTO ORDER_PROFIT 
                        (ORDERID, PROFITBEFOREGP, PROFITAFTERGP, PROFITBUFFETBEFOREGP, PROFITBUFFETAFTERGP)
                        VALUES (:oid, 0, 0, :pbefore, :pafter)";
        }
        
        $stidProfit = oci_parse($conn, $sqlProfit);
        oci_bind_by_name($stidProfit, ':oid', $orderid);
        oci_bind_by_name($stidProfit, ':pbefore', $profitBefore);
        oci_bind_by_name($stidProfit, ':pafter', $profitAfter);
        oci_execute($stidProfit);

        oci_commit($conn);
        header("Location: order_list.php");
        exit();
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างออเดอร์</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
    <style>

    .person-counter {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #ecf0f1;
        padding: 8px 15px;
        border-radius: 8px;
        margin-top: 10px;
    }

    .person-counter label {
        margin: 0;
        font-weight: 600;
        color: #2c3e50;
    }

    .person-btn {
        width: 35px;
        height: 35px;
        background: #3498db;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 18px;
        font-weight: bold;
        transition: all 0.2s;
    }

    .person-btn:hover {
        background: #2980b9;
        transform: scale(1.1);
    }

    .person-btn:disabled {
        background: #95a5a6;
        cursor: not-allowed;
        opacity: 0.5;
    }

    .person-count {
        font-size: 18px;
        font-weight: bold;
        color: #2c3e50;
        min-width: 30px;
        text-align: center;
    }
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
        .form-row-with-clone {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        gap: 15px;
        align-items: end;
    }

    .clone-btn {
        padding: 12px 16px;
        background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 16px;
        font-weight: 600;
        transition: all 0.3s;
        white-space: nowrap;
        height: 46px;
        margin-bottom: 20px;
    }

    .clone-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(243, 156, 18, 0.3);
    }

    .clone-btn:disabled {
        background: #95a5a6;
        cursor: not-allowed;
        opacity: 0.6;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body { 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f0f2f5;
    }

    .top-bar {
        width: 100%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 15px 20px;
        display: flex;
        align-items: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        position: fixed;
        top: 0;
        left: 0;
        z-index: 100;
        color: white;
    }

    .back-btn {
        padding: 8px 15px;
        margin-right: 15px;
        cursor: pointer;
        border-radius: 8px;
        border: none;
        background: rgba(255,255,255,0.2);
        color: white;
        font-size: 18px;
        transition: all 0.3s;
    }

    .back-btn:hover {
        background: rgba(255,255,255,0.3);
        transform: translateY(-2px);
    }

    .top-bar h2 {
        font-size: 22px;
        font-weight: 600;
    }

    .container { 
        max-width: 1200px;
        margin: 90px auto 40px;
        padding: 20px;
    }

    .form-section {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }

    .section-title {
        font-size: 20px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 3px solid #3498db;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    label { 
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #34495e;
        font-size: 14px;
    }

    input, select { 
        width: 100%;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 15px;
        transition: border-color 0.3s;
    }

    input:focus, select:focus {
        outline: none;
        border-color: #3498db;
    }

    input[readonly] {
        background: #f8f9fa;
        color: #666;
    }

    .customer-info {
        background: #e8f4f8;
        padding: 12px;
        border-radius: 8px;
        margin-top: 8px;
        font-size: 14px;
        color: #2c3e50;
        border-left: 4px solid #3498db;
    }

    .warning-box {
        background: #fff3cd;
        border: 2px solid #ffc107;
        padding: 15px;
        border-radius: 8px;
        margin-top: 10px;
        font-size: 14px;
        color: #856404;
    }

    .add-type-btn {
        width: 100%;
        padding: 15px;
        background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 16px;
        font-weight: 600;
        margin-top: 20px;
        transition: all 0.3s;
    }

    .add-type-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(39, 174, 96, 0.3);
    }

    .menu-type-section {
        background: white;
        border: 2px solid #3498db;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 15px;
        position: relative;
    }

    .menu-type-section.omakase {
        border-color: #e74c3c;
    }

    .menu-type-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #ecf0f1;
    }

    .type-badge {
        display: inline-block;
        padding: 6px 12px;
        background: #3498db;
        color: white;
        border-radius: 6px;
        font-weight: 600;
        font-size: 14px;
    }

    .type-badge.omakase {
        background: #e74c3c;
    }

    .remove-type-btn {
        padding: 6px 12px;
        background: #e74c3c;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
    }

    .remove-type-btn:hover {
        background: #c0392b;
    }

    /* Menu Grid */
    .menu-section {
        margin-bottom: 20px;
    }

    .search-scan-bar {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }

    .search-box {
        flex: 1;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 15px;
    }

    .scan-btn {
        padding: 12px 20px;
        background: #e74c3c;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .scan-btn:hover {
        background: #c0392b;
    }

    .menu-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 15px;
        max-height: 400px;
        overflow-y: auto;
        padding: 10px;
        border: 2px solid #ecf0f1;
        border-radius: 8px;
    }

    .menu-card {
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        padding: 12px;
        cursor: pointer;
        transition: all 0.3s;
        text-align: center;
    }

    .menu-card:hover {
        border-color: #3498db;
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    }

    .menu-card.selected {
        border-color: #27ae60;
        background: #d5f4e6;
    }

    .menu-img {
        width: 100%;
        height: 100px;
        object-fit: cover;
        border-radius: 8px;
        margin-bottom: 10px;
        background: #ecf0f1;
    }

    .menu-name {
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 5px;
        color: #2c3e50;
    }

    .menu-price {
        color: #27ae60;
        font-weight: bold;
        font-size: 15px;
    }

    /* Cart */
    .cart-section {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .cart-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 10px;
    }

    .cart-item-info {
        flex: 1;
    }

    .cart-item-name {
        font-weight: 600;
        margin-bottom: 5px;
    }

    .cart-item-price {
        color: #3498db;
        font-size: 14px;
    }

    .cart-controls {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .qty-btn {
        width: 32px;
        height: 32px;
        background: #3498db;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
    }

    .qty-display {
        min-width: 30px;
        text-align: center;
        font-weight: bold;
    }

    .remove-btn {
        width: 32px;
        height: 32px;
        background: #e74c3c;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
    }

    .cart-empty {
        text-align: center;
        padding: 40px;
        color: #95a5a6;
    }

    /* Summary */
    .summary-box {
        background: #ecf0f1;
        padding: 20px;
        border-radius: 8px;
        margin-top: 20px;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        font-size: 16px;
    }

    .summary-row.total {
        font-size: 22px;
        font-weight: bold;
        color: #27ae60;
        padding-top: 10px;
        border-top: 2px solid #bdc3c7;
    }

    /* Payment */
    .payment-section {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-top: 20px;
    }

    .payment-methods {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-bottom: 15px;
    }

    .payment-method-btn {
        padding: 12px;
        background: #ecf0f1;
        border: 2px solid #bdc3c7;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
        font-weight: 600;
    }

    .payment-method-btn:hover {
        background: #3498db;
        color: white;
        border-color: #3498db;
    }

    .payment-method-btn.active {
        background: #27ae60;
        color: white;
        border-color: #27ae60;
    }

    .cash-input {
        width: 100%;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 16px;
        margin-bottom: 10px;
    }

    .change-display {
        text-align: center;
        font-size: 18px;
        color: #27ae60;
        font-weight: bold;
        margin-bottom: 15px;
    }

    .submit-btn {
        width: 100%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 18px;
        font-size: 18px;
        font-weight: 700;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
    }

    .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.2);
    }

    /* Scanner Modal */
    .scanner-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.95);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }

    .scanner-modal.active {
        display: flex;
    }

    .scanner-content {
        background: white;
        padding: 30px;
        border-radius: 12px;
        max-width: 600px;
        width: 90%;
    }

    #scanner-container {
        width: 100%;
        height: 400px;
        background: #000;
        border-radius: 8px;
        overflow: hidden;
    }

    .scanner-result {
        background: #d4edda;
        padding: 15px;
        border-radius: 8px;
        margin-top: 15px;
        display: none;
    }

    .scanner-result.show {
        display: block;
    }

    .close-scanner {
        width: 100%;
        padding: 12px;
        background: #e74c3c;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        margin-top: 15px;
    }

    .info-badge {
        display: inline-block;
        padding: 4px 10px;
        background: #3498db;
        color: white;
        border-radius: 4px;
        font-size: 12px;
        margin-left: 8px;
    }

    .badge-warning {
        background: #f39c12;
    }

    ::-webkit-scrollbar { width: 8px; }
    ::-webkit-scrollbar-track { background: #ecf0f1; }
    ::-webkit-scrollbar-thumb { background: #3498db; border-radius: 4px; }
    </style>
    </head>
    <body>

    <div class="top-bar">
        <button class="back-btn" onclick="window.location.href='order_list.php'">←</button>
        <h2>📝 สร้างออเดอร์ใหม่</h2>
    </div>

    <div class="container">
    <form method="POST" id="order-form">

    <!-- Order Info Section -->
    <div class="form-section">
        <div class="section-title">📋 ข้อมูลออเดอร์</div>
        
        <div class="form-row">
            <div class="form-group">
                <label>เลขออเดอร์:</label>
                <input type="text" name="orderid" value="<?= $newOrderID ?>" readonly>
            </div>
            <div class="form-group">
                <label>สมาชิก: <span class="info-badge">ได้รับส่วนลด</span></label>
                <select name="customerid" id="customerid" onchange="updateCustomerInfo()">
                    <option value="">-- Guest (ไม่ได้ส่วนลด) --</option>
                    <?php 
                    oci_execute($stidCustomer);
                    while($c = oci_fetch_assoc($stidCustomer)) { 
                        $level = $c['LEVELNAME'] ?? 'ไม่มี';
                        $discount = $c['DISCOUNT'] ?? 0;
                    ?>
                        <option value="<?= $c['CUSTOMERID'] ?>" data-level="<?= $level ?>" data-discount="<?= $discount ?>">
                            <?= $c['CUSTOMERNAME'] ?> (ID: <?= $c['CUSTOMERID'] ?>)
                        </option>
                    <?php } ?>
                </select>
                <div id="customer-info" class="customer-info" style="display:none;"></div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>📅 วันที่: <span class="info-badge badge-warning">ย้อนหลัง 4 เดือน</span></label>
                <input type="date" name="order_date" value="<?= $defaultDate ?>" 
                    min="<?= $minDate ?>" max="<?= $maxDate ?>" required>
            </div>
            <div class="form-group">
                <label>🕐 เวลา:</label>
                <input type="time" name="order_time" value="<?= $defaultTime ?>" required>
            </div>
        </div>
    </div>

    <!-- Menu Selection Section -->
    <div class="form-section">
        <div class="section-title">🍽️ เลือกเมนูแยกตามคน</div>
        
        <div id="menu-types-container">
            <!-- Menu Type Sections will be added here dynamically -->
        </div>
        
        
    </div>

    <!-- Cart Section -->
    <div class="cart-section">
        <div class="section-title">🛒 รายการที่เลือก</div>
        <div id="cart-items">
            <div class="cart-empty">
                <p style="font-size: 48px; margin-bottom: 10px;">🛒</p>
                <p>ยังไม่มีรายการ</p>
            </div>
        </div>

        <div class="summary-box" id="summary-box" style="display:none;">
            <div class="summary-row">
                <span>ราคารวม:</span>
                <span id="subtotal">0.00 ฿</span>
            </div>
            <div class="summary-row">
                <span>ส่วนลด:</span>
                <span id="discount">0.00 ฿</span>
            </div>
            <div class="summary-row total">
                <span>ยอดรวมสุทธิ:</span>
                <span id="total">0.00 ฿</span>
            </div>
        </div>
    </div>

    <!-- Payment Section -->
    <div class="payment-section">
        <div class="section-title">💳 วิธีการชำระเงิน</div>
        
        <div class="payment-methods">
            <button type="button" class="payment-method-btn active" data-method="cash" onclick="selectPayment('cash')">
                💵 เงินสด
            </button>
            <button type="button" class="payment-method-btn" data-method="transfer" onclick="selectPayment('transfer')">
                📱 โอนเงิน
            </button>
        </div>

        <input type="number" class="cash-input" id="cash-received" placeholder="เงินที่รับมา (สำหรับเงินสด)" step="0.01" onchange="calculateChange()">
        <div class="change-display" id="change-display">เงินทอน: 0.00 ฿</div>

        <input type="hidden" name="payment_method" id="payment-method" value="cash">
        <div id="hidden-cart-items"></div>
        
        <button type="submit" name="create_order" class="submit-btn">✔️ ยืนยันและสร้างออเดอร์</button>
    </div>

    </form>
    </div>

    <!-- Scanner Modal -->
    <div id="scanner-modal" class="scanner-modal">
        <div class="scanner-content">
            <h3>📷 สแกนบาร์โค้ดเมนู</h3>
            <div id="scanner-container"></div>
            <div id="scanner-result" class="scanner-result">
                <strong>ตรวจพบ:</strong> <span id="barcode-value"></span>
            </div>
            <button class="close-scanner" onclick="closeScanner()">ปิด</button>
        </div>
    </div>

    <script>
    let cart = [];
    let currentPaymentMethod = 'cash';
    let menuTypeCounter = 0;
    let menuTypeSections = [];

    // PHP Menu Types Data
    const menuTypesData = [
        <?php 
        oci_execute($stidType);
        $types = [];
        while($t = oci_fetch_assoc($stidType)) {
            $types[] = "{id: {$t['MENUTYPEID']}, name: '{$t['TYPENAME']}'}";
        }
        echo implode(',', $types);
        ?>
    ];

    // PHP Menu Data
    const menuData = [
        <?php
        oci_execute($stidMenu);
        $menus = [];
        while ($menu = oci_fetch_assoc($stidMenu)) {
            $imagePath = !empty($menu['IMAGEPATH']) && file_exists($menu['IMAGEPATH']) 
                ? htmlspecialchars($menu['IMAGEPATH']) 
                : '';
            $menus[] = sprintf(
                "{id: %d, name: '%s', priceAlacarte: %s, priceOmakase: %s, barcode: '%s', image: '%s'}",
                $menu['MENUID'],
                addslashes(htmlspecialchars($menu['MENUNAME'])),
                $menu['PRICE_ALACARTE'],
                $menu['PRICE_OMAKASE'],
                htmlspecialchars($menu['BARCODE']),
                $imagePath
            );
        }
        echo implode(',', $menus);
        ?>
    ];

    // Initialize first menu type on page load
    window.addEventListener('DOMContentLoaded', function() {
        addMenuType();
    });

    // ==================== ADD MENU TYPE ====================
    function addMenuType() {
        menuTypeCounter++;
        const container = document.getElementById('menu-types-container');
        
        const section = document.createElement('div');
        section.className = 'menu-type-section';
        section.id = `menu-type-${menuTypeCounter}`;
        section.setAttribute('data-type-id', menuTypeCounter);
        
        let menuTypeOptions = '<option value="">-- เลือกประเภท --</option>';
        menuTypesData.forEach(type => {
            menuTypeOptions += `<option value="${type.id}">${type.name}</option>`;
        });
        
        let menuCards = '';
        menuData.forEach(menu => {
            const imgHtml = menu.image 
                ? `<img src="${menu.image}" class="menu-img">`
                : '<div class="menu-img" style="display:flex;align-items:center;justify-content:center;font-size:36px;">🍱</div>';
            
            menuCards += `
                <div class="menu-card" 
                    data-section="${menuTypeCounter}"
                    data-menuid="${menu.id}"
                    data-name="${menu.name}"
                    data-price-alacarte="${menu.priceAlacarte}"
                    data-price-omakase="${menu.priceOmakase}"
                    data-barcode="${menu.barcode}"
                    onclick="addToCart(this, ${menuTypeCounter})">
                    ${imgHtml}
                    <div class="menu-name">${menu.name}</div>
                    <div class="menu-price" data-menuid="${menu.id}">
                        ${parseFloat(menu.priceAlacarte).toFixed(2)} ฿
                    </div>
                </div>
            `;
        });
        
        section.innerHTML = `
        <div class="menu-type-header">
            <div>
                <span class="type-badge">คน #${menuTypeCounter}</span>
                <div class="person-counter">
                    <label>จำนวนคน:</label>
                    <button type="button" class="person-btn" onclick="decreasePersonCount(${menuTypeCounter})">−</button>
                    <span class="person-count" id="person-count-${menuTypeCounter}">1</span>
                    <button type="button" class="person-btn" onclick="increasePersonCount(${menuTypeCounter})">+</button>
                    <input type="hidden" name="person_count[]" id="person-count-input-${menuTypeCounter}" value="1">
                </div>
            </div>
            ${menuTypeCounter > 1 ? `<button type="button" class="remove-type-btn" onclick="removeMenuType(${menuTypeCounter})">🗑️ ลบ</button>` : ''}
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>ประเภทเมนู:</label>
                <select name="menutypeid[]" class="menu-type-select" data-section="${menuTypeCounter}" required onchange="handleTypeChange(${menuTypeCounter}, this.value)">
                    ${menuTypeOptions}
                </select>
            </div>
            <div class="form-group course-box-${menuTypeCounter}" style="display:none;">
                <label>เลือกคอร์ส:</label>
                <select name="courseid[]" class="courseid-select" data-section="${menuTypeCounter}">
                    <option value="">-- เลือกคอร์ส --</option>
                </select>
            </div>
        </div>

        <div class="warning-box course-warning-${menuTypeCounter}" style="display:none;">
            ⚠️ <strong>หมายเหตุ:</strong> เมื่อสั่ง Omakase จะหักวัตถุดิบของทุกเมนูใน Course ทันที (คูณตามจำนวนคน)
        </div>

        <div class="search-scan-bar">
            <input type="text" class="search-box search-box-${menuTypeCounter}" placeholder="🔍 ค้นหาเมนูสำหรับคนนี้...">
            <button type="button" class="scan-btn" onclick="openScanner(${menuTypeCounter})">📷 สแกน</button>
        </div>

        <div class="menu-grid menu-grid-${menuTypeCounter}">
            ${menuCards}
        </div>
    `;
        
        container.appendChild(section);
        
        // Add search functionality for this section
        const searchBox = section.querySelector(`.search-box-${menuTypeCounter}`);
        searchBox.addEventListener('input', function(e) {
            const search = e.target.value.toLowerCase();
            section.querySelectorAll('.menu-card').forEach(card => {
                const name = card.getAttribute('data-name').toLowerCase();
                card.style.display = name.includes(search) ? 'block' : 'none';
            });
        });
        
        menuTypeSections.push(menuTypeCounter);
    }

    // ==================== CLONE MENU TYPE ====================
    function cloneMenuType(sourceSectionId) {
        const sourceSection = document.getElementById(`menu-type-${sourceSectionId}`);
        if (!sourceSection) return;
        
        const sourceTypeSelect = sourceSection.querySelector('.menu-type-select');
        const sourceCourseSelect = sourceSection.querySelector('.courseid-select');
        
        const selectedType = sourceTypeSelect.value;
        const selectedCourse = sourceCourseSelect ? sourceCourseSelect.value : null;
        
        if (!selectedType) {
            alert('กรุณาเลือกประเภทเมนูก่อนทำซ้ำ');
            return;
        }
        
        // Create new section
        addMenuType();
        
        // Get the newly created section
        const newSectionId = menuTypeCounter;
        const newSection = document.getElementById(`menu-type-${newSectionId}`);
        
        if (newSection) {
            const newTypeSelect = newSection.querySelector('.menu-type-select');
            const newCourseSelect = newSection.querySelector('.courseid-select');
            
            // Set the same menu type
            newTypeSelect.value = selectedType;
            
            // Trigger type change to load courses if Omakase
            handleTypeChange(newSectionId, selectedType);
            
            // If it's Omakase and has selected course, set it after a delay
            if (selectedType == '2' && selectedCourse) {
                setTimeout(() => {
                    if (newCourseSelect) {
                        newCourseSelect.value = selectedCourse;
                    }
                }, 500);
            }
            
            // Show notification
            showCloneNotification(newSectionId);
        }
    }

    // Show clone notification
    function showCloneNotification(sectionId) {
        const section = document.getElementById(`menu-type-${sectionId}`);
        if (!section) return;
        
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 10000;
            animation: slideIn 0.3s ease-out;
            font-weight: 600;
        `;
        notification.textContent = `✅ ทำซ้ำสำเร็จ! → คน #${sectionId}`;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease-in';
            setTimeout(() => notification.remove(), 300);
        }, 2000);
        
        // Scroll to new section
        section.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    // ==================== PERSON COUNT MANAGEMENT ====================
    function increasePersonCount(sectionId) {
        const countDisplay = document.getElementById(`person-count-${sectionId}`);
        const countInput = document.getElementById(`person-count-input-${sectionId}`);
        
        let currentCount = parseInt(countDisplay.textContent);
        currentCount++;
        
        countDisplay.textContent = currentCount;
        countInput.value = currentCount;
        
        // Update badge
        updateBadgeText(sectionId);
        
        // Update summary
        updateSummary();
        
        // Show notification
        showPersonCountNotification(sectionId, currentCount);
    }

    function decreasePersonCount(sectionId) {
        const countDisplay = document.getElementById(`person-count-${sectionId}`);
        const countInput = document.getElementById(`person-count-input-${sectionId}`);
        
        let currentCount = parseInt(countDisplay.textContent);
        
        if (currentCount <= 1) {
            return; // ไม่ให้ลดต่ำกว่า 1
        }
        
        currentCount--;
        
        countDisplay.textContent = currentCount;
        countInput.value = currentCount;
        
        // Update badge
        updateBadgeText(sectionId);
        
        // Update summary
        updateSummary();
        
        // Show notification
        showPersonCountNotification(sectionId, currentCount);
    }

    function updateBadgeText(sectionId) {
        const section = document.getElementById(`menu-type-${sectionId}`);
        const badge = section.querySelector('.type-badge');
        const count = parseInt(document.getElementById(`person-count-${sectionId}`).textContent);
        
        if (count > 1) {
            badge.textContent = `คน #${sectionId} (${count} ท่าน)`;
        } else {
            badge.textContent = `คน #${sectionId}`;
        }
    }

    function showPersonCountNotification(sectionId, count) {
        const section = document.getElementById(`menu-type-${sectionId}`);
        if (!section) return;
        
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 10000;
            animation: slideIn 0.3s ease-out;
            font-weight: 600;
        `;
        notification.textContent = `👥 จำนวนคน: ${count} ท่าน`;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease-in';
            setTimeout(() => notification.remove(), 300);
        }, 1500);
    }
    // ==================== REMOVE MENU TYPE ====================
    function removeMenuType(typeId) {
        if (menuTypeSections.length <= 1) {
            alert('ต้องมีอย่างน้อย 1 ประเภทเมนู');
            return;
        }
        
        // Remove from cart
        cart = cart.filter(item => item.section !== typeId);
        
        // Remove section
        const section = document.getElementById(`menu-type-${typeId}`);
        if (section) {
            section.remove();
        }
        
        // Update sections array
        menuTypeSections = menuTypeSections.filter(id => id !== typeId);
        
        renderCart();
    }

    // ==================== HANDLE TYPE CHANGE ====================
    // ==================== HANDLE TYPE CHANGE ====================
function handleTypeChange(sectionId, typeId) {
    const section = document.getElementById(`menu-type-${sectionId}`);
    const courseBox = section.querySelector(`.course-box-${sectionId}`);
    const courseWarning = section.querySelector(`.course-warning-${sectionId}`);
    const courseSelect = section.querySelector('.courseid-select');
    
    // Update section styling
    if (typeId == 2) {
        section.classList.add('omakase');
        const badge = section.querySelector('.type-badge');
        badge.classList.add('omakase');
        courseBox.style.display = 'block';
        courseWarning.style.display = 'block';
        
        // Load courses
        fetch("load_course.php?type=" + typeId)
            .then(res => res.text())
            .then(html => {
                courseSelect.innerHTML = html;
            });
    } else {
        section.classList.remove('omakase');
        const badge = section.querySelector('.type-badge');
        badge.classList.remove('omakase');
        courseBox.style.display = 'none';
        courseWarning.style.display = 'none';
        courseSelect.innerHTML = '<option value="">-- เลือกคอร์ส --</option>';
    }
    
    // Update menu prices for this section
    updateMenuPricesForSection(sectionId, typeId);
}

    // ==================== UPDATE MENU PRICES FOR SECTION ====================
    function updateMenuPricesForSection(sectionId, menuType) {
        if (!menuType) return;
        
        const section = document.getElementById(`menu-type-${sectionId}`);
        section.querySelectorAll('.menu-card').forEach(card => {
            const priceElement = card.querySelector('.menu-price');
            const price = menuType == '1' ? 
                parseFloat(card.getAttribute('data-price-alacarte')) : 
                parseFloat(card.getAttribute('data-price-omakase'));
            
            priceElement.textContent = price.toFixed(2) + ' ฿';
        });
        
        // Update cart prices for this section
        cart.forEach(item => {
            if (item.section === sectionId) {
                const card = section.querySelector(`[data-menuid="${item.menuid}"]`);
                if (card) {
                    item.price = menuType == '1' ? 
                        parseFloat(card.getAttribute('data-price-alacarte')) : 
                        parseFloat(card.getAttribute('data-price-omakase'));
                }
            }
        });
        
        renderCart();
    }

    // ==================== ADD TO CART ====================
    function addToCart(element, sectionId) {
        const section = document.getElementById(`menu-type-${sectionId}`);
        const typeSelect = section.querySelector('.menu-type-select');
        const menuType = typeSelect.value;
        
        if (!menuType) {
            alert('กรุณาเลือกประเภทเมนูก่อน');
            return;
        }
        
        const menuid = element.getAttribute('data-menuid');
        const name = element.getAttribute('data-name');
        const price = menuType == '1' ? 
            parseFloat(element.getAttribute('data-price-alacarte')) : 
            parseFloat(element.getAttribute('data-price-omakase'));
        
        const existingItem = cart.find(item => item.menuid === menuid && item.section === sectionId);
        
        if (existingItem) {
            existingItem.qty++;
        } else {
            cart.push({ 
                menuid: menuid, 
                name: name, 
                price: price, 
                qty: 1,
                section: sectionId,
                menuType: menuType
            });
        }
        
        renderCart();
        element.classList.add('selected');
        setTimeout(() => element.classList.remove('selected'), 300);
    }

    // ==================== RENDER CART ====================
    function renderCart() {
        const cartItems = document.getElementById('cart-items');
        const summaryBox = document.getElementById('summary-box');
        const hiddenCart = document.getElementById('hidden-cart-items');
        
        if (cart.length === 0) {
            cartItems.innerHTML = `
                <div class="cart-empty">
                    <p style="font-size: 48px; margin-bottom: 10px;">🛒</p>
                    <p>ยังไม่มีรายการ</p>
                </div>
            `;
            summaryBox.style.display = 'none';
            hiddenCart.innerHTML = '';
            return;
        }
        
        // Group by section
        const groupedCart = {};
        cart.forEach(item => {
            if (!groupedCart[item.section]) {
                groupedCart[item.section] = [];
            }
            groupedCart[item.section].push(item);
        });
        
        let html = '';
        let hiddenInputs = '';
        
        Object.keys(groupedCart).sort((a, b) => parseInt(a) - parseInt(b)).forEach(sectionId => {
            const items = groupedCart[sectionId];
            const menuType = items[0].menuType;
            const typeName = menuType == '1' ? 'A La Carte' : 'Omakase';
            
            html += `<div style="background: #f8f9fa; padding: 10px; border-radius: 8px; margin-bottom: 15px;">
                        <div style="font-weight: bold; margin-bottom: 10px; color: ${menuType == '1' ? '#3498db' : '#e74c3c'}">
                            คน #${sectionId} - ${typeName}
                        </div>`;
            
            items.forEach(item => {
                const index = cart.indexOf(item);
                html += `
                    <div class="cart-item">
                        <div class="cart-item-info">
                            <div class="cart-item-name">${item.name}</div>
                            <div class="cart-item-price">
    ${item.menuType == '2' ? '🔄 ค่าแลกซื้อ: ' : ''}${item.price.toFixed(2)} ฿ × ${item.qty}
</div>
                        </div>
                        <div class="cart-controls">
                            <button type="button" class="qty-btn" onclick="updateQty(${index}, -1)">-</button>
                            <span class="qty-display">${item.qty}</span>
                            <button type="button" class="qty-btn" onclick="updateQty(${index}, 1)">+</button>
                            <button type="button" class="remove-btn" onclick="removeItem(${index})">×</button>
                        </div>
                    </div>
                `;
                
                hiddenInputs += `
                    <input type="hidden" name="menu_id[]" value="${item.menuid}">
                    <input type="hidden" name="qty[]" value="${item.qty}">
                    <input type="hidden" name="section[]" value="${item.section}">
                `;
            });
            
            html += `</div>`;
        });
        
        cartItems.innerHTML = html;
        hiddenCart.innerHTML = hiddenInputs;
        summaryBox.style.display = 'block';
        updateSummary();
    }

    // ==================== UPDATE QUANTITY ====================
    function updateQty(index, change) {
        cart[index].qty += change;
        if (cart[index].qty <= 0) {
            cart.splice(index, 1);
        }
        renderCart();
    }

    // ==================== REMOVE ITEM ====================
    function removeItem(index) {
        cart.splice(index, 1);
        renderCart();
    }

    // ==================== UPDATE SUMMARY ====================
    // ==================== UPDATE SUMMARY ====================
    // ==================== UPDATE SUMMARY ====================
function updateSummary() {
    let subtotal = 0;
    
    // คำนวณราคาเมนูที่สั่ง
    cart.forEach(item => {
        // ถ้าเป็น A La Carte หรือเป็น Omakase ที่สั่งเพิ่ม ให้คิดราคา
        if (item.menuType == '1') {
            // A La Carte - ใช้ราคา PRICE_ALACARTE
            subtotal += item.price * item.qty;
        } else if (item.menuType == '2') {
            // Omakase - ใช้ราคา PRICE_OMAKASE เป็นค่าแลกซื้อ
            subtotal += item.price * item.qty;
        }
    });
    
    // Add course prices for Omakase sections (คูณตามจำนวนคน)
    menuTypeSections.forEach(sectionId => {
        const section = document.getElementById(`menu-type-${sectionId}`);
        if (section) {
            const typeSelect = section.querySelector('.menu-type-select');
            const courseSelect = section.querySelector('.courseid-select');
            const personCount = parseInt(document.getElementById(`person-count-${sectionId}`).textContent) || 1;
            
            if (typeSelect && typeSelect.value == '2' && courseSelect && courseSelect.value) {
                const courseOption = courseSelect.options[courseSelect.selectedIndex];
                if (courseOption && courseOption.getAttribute('data-price')) {
                    const coursePrice = parseFloat(courseOption.getAttribute('data-price'));
                    subtotal += (coursePrice * personCount); // คูณตามจำนวนคน
                }
            }
        }
    });
    
    document.getElementById('subtotal').textContent = subtotal.toFixed(2) + ' ฿';
    document.getElementById('discount').textContent = '0.00 ฿';
    document.getElementById('total').textContent = subtotal.toFixed(2) + ' ฿';
    
    calculateChange();
}

    // ==================== CUSTOMER INFO ====================
    function updateCustomerInfo() {
        const select = document.getElementById("customerid");
        const infoDiv = document.getElementById("customer-info");
        const selectedOption = select.options[select.selectedIndex];
        
        if (select.value) {
            const level = selectedOption.getAttribute('data-level');
            const discount = selectedOption.getAttribute('data-discount');
            infoDiv.innerHTML = `<strong>ระดับ:</strong> ${level} | <strong>ส่วนลด:</strong> ${discount}%`;
            infoDiv.style.display = 'block';
        } else {
            infoDiv.style.display = 'none';
        }
    }

    // ==================== PAYMENT ====================
    function selectPayment(method) {
        currentPaymentMethod = method;
        document.querySelectorAll('.payment-method-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`[data-method="${method}"]`).classList.add('active');
        document.getElementById('payment-method').value = method;
        
        const cashInput = document.getElementById('cash-received');
        if (method !== 'cash') {
            cashInput.disabled = true;
            cashInput.value = '';
            document.getElementById('change-display').textContent = 'เงินทอน: 0.00 ฿';
        } else {
            cashInput.disabled = false;
        }
    }

    function calculateChange() {
        if (currentPaymentMethod !== 'cash') return;
        
        const total = parseFloat(document.getElementById('total').textContent.replace(/[^\d.]/g, ''));
        const received = parseFloat(document.getElementById('cash-received').value) || 0;
        const change = received - total;
        
        document.getElementById('change-display').textContent = 
            `เงินทอน: ${change >= 0 ? change.toFixed(2) : '0.00'} ฿`;
    }

    // ==================== BARCODE SCANNER ====================
    let currentScannerSection = null;

    function openScanner(sectionId) {
        currentScannerSection = sectionId;
        document.getElementById('scanner-modal').classList.add('active');
        document.getElementById('scanner-result').classList.remove('show');
        
        Quagga.init({
            inputStream: {
                name: "Live",
                type: "LiveStream",
                target: document.querySelector('#scanner-container'),
                constraints: { facingMode: "environment" }
            },
            decoder: {
                readers: ["ean_reader", "code_128_reader", "ean_8_reader", "code_39_reader", "upc_reader"]
            }
        }, function(err) {
            if (err) {
                console.error(err);
                alert('ไม่สามารถเข้าถึงกล้องได้');
                return;
            }
            Quagga.start();
        });
        
        Quagga.onDetected(function(result) {
            const barcode = result.codeResult.code;
            document.getElementById('barcode-value').textContent = barcode;
            document.getElementById('scanner-result').classList.add('show');
            
            const section = document.getElementById(`menu-type-${currentScannerSection}`);
            const menuCard = section.querySelector(`[data-barcode="${barcode}"]`);
            if (menuCard) {
                addToCart(menuCard, currentScannerSection);
                setTimeout(() => closeScanner(), 1000);
            }
        });
    }

    function closeScanner() {
        Quagga.stop();
        document.getElementById('scanner-modal').classList.remove('active');
        currentScannerSection = null;
    }

    // Form validation
    document.getElementById('order-form').addEventListener('submit', function(e) {
        // Check if all menu types are selected
        const allTypesSelected = Array.from(document.querySelectorAll('.menu-type-select')).every(select => select.value);
        
        if (!allTypesSelected) {
            e.preventDefault();
            alert('กรุณาเลือกประเภทเมนูให้ครบทุกคน');
            return;
        }
        
        // Check omakase courses
        const omakaseSections = Array.from(document.querySelectorAll('.menu-type-select'))
            .filter(select => select.value == '2');
        
        for (let select of omakaseSections) {
            const sectionId = select.getAttribute('data-section');
            const section = document.getElementById(`menu-type-${sectionId}`);
            const courseSelect = section.querySelector('.courseid-select');
            
            if (!courseSelect.value) {
                e.preventDefault();
                alert(`กรุณาเลือกคอร์สสำหรับคน #${sectionId}`);
                return;
            }
        }
        
        if (cart.length === 0) {
            // Check if at least one section has Omakase with course selected
            let hasOmakaseCourse = false;
            omakaseSections.forEach(select => {
                const sectionId = select.getAttribute('data-section');
                const section = document.getElementById(`menu-type-${sectionId}`);
                const courseSelect = section.querySelector('.courseid-select');
                if (courseSelect && courseSelect.value) {
                    hasOmakaseCourse = true;
                }
            });
            
            if (!hasOmakaseCourse) {
                e.preventDefault();
                alert('กรุณาเลือกเมนูอย่างน้อย 1 รายการ หรือเลือก Omakase Course');
                return;
            }
        }
        
        if (currentPaymentMethod === 'cash') {
            const total = parseFloat(document.getElementById('total').textContent.replace(/[^\d.]/g, ''));
            const received = parseFloat(document.getElementById('cash-received').value) || 0;
            if (received < total) {
                e.preventDefault();
                alert('เงินที่รับมาไม่เพียงพอ');
                return;
            }
        }
    });
    </script>

    </body>
    </html>