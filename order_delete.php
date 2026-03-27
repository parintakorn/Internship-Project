<?php
include 'connect.php';

$orderid = $_GET['id'] ?? null;

if (!$orderid) {
    header("Location: order_list.php?error=no_id");
    exit();
}

try {
    // ❗ ปิด auto-commit
    oci_execute(oci_parse($conn, "SET TRANSACTION READ WRITE"));

    /* =========================
       1) ดึงข้อมูล TRANSACTION
    ========================== */
    $sqlTransaction = "
        SELECT MENUTYPEID, COURSEID 
        FROM TRANSACTION 
        WHERE ORDERID = :orderid
        FOR UPDATE
    ";
    $stidTransaction = oci_parse($conn, $sqlTransaction);
    oci_bind_by_name($stidTransaction, ':orderid', $orderid);
    oci_execute($stidTransaction);

    $transaction = oci_fetch_assoc($stidTransaction);
    if (!$transaction) {
        throw new Exception('Order not found');
    }

    // ส่วนที่ 2: คืนวัตถุดิบ Course
if ($transaction['MENUTYPEID'] == 2 && $transaction['COURSEID']) {
    $courseid = $transaction['COURSEID'];

    // ✅ ดึง PERSON_COUNT จาก ORDER_SECTION
    $sqlSection = "SELECT PERSON_COUNT FROM ORDER_SECTION 
                   WHERE ORDERID = :orderid AND MENUTYPEID = 2";
    $stidSection = oci_parse($conn, $sqlSection);
    oci_bind_by_name($stidSection, ':orderid', $orderid);
    oci_execute($stidSection);
    $sectionRow = oci_fetch_assoc($stidSection);
    $personCount = $sectionRow ? intval($sectionRow['PERSON_COUNT']) : 1;

    $sqlCourseMenu = "SELECT MENUID, QUANTITY FROM COURSE_MENU WHERE COURSEID = :courseid";
    $stidCourseMenu = oci_parse($conn, $sqlCourseMenu);
    oci_bind_by_name($stidCourseMenu, ':courseid', $courseid);
    oci_execute($stidCourseMenu);

    while ($cm = oci_fetch_assoc($stidCourseMenu)) {
        $menuQuantity = $cm['QUANTITY'] * $personCount; // ✅ คูณจำนวนคน
        
        $sqlRecipe = "SELECT INGREDIENTID, QTYUSED FROM RECIPE WHERE MENUID = :menuid";
        $stidRecipe = oci_parse($conn, $sqlRecipe);
        oci_bind_by_name($stidRecipe, ':menuid', $cm['MENUID']);
        oci_execute($stidRecipe);

        while ($r = oci_fetch_assoc($stidRecipe)) {
            $qty = $r['QTYUSED'] * $menuQuantity; // ✅ คูณครบทั้ง QTYUSED × QUANTITY × PERSON_COUNT

            $sqlUpdate = "UPDATE INGREDIENT 
                          SET QTYONHAND = QTYONHAND + :qty
                          WHERE INGREDIENTID = :ingid";
            $stidUpdate = oci_parse($conn, $sqlUpdate);
            oci_bind_by_name($stidUpdate, ':qty', $qty);
            oci_bind_by_name($stidUpdate, ':ingid', $r['INGREDIENTID']);
            oci_execute($stidUpdate, OCI_NO_AUTO_COMMIT);
        }
    }
}

    /* =========================
       3) คืนวัตถุดิบ ORDER_ITEM
    ========================== */
    $sqlItems = "
        SELECT MENUID, QUANTITY 
        FROM ORDER_ITEM 
        WHERE ORDERID = :orderid
    ";
    $stidItems = oci_parse($conn, $sqlItems);
    oci_bind_by_name($stidItems, ':orderid', $orderid);
    oci_execute($stidItems);

    while ($item = oci_fetch_assoc($stidItems)) {
        $sqlRecipe = "
            SELECT INGREDIENTID, QTYUSED 
            FROM RECIPE 
            WHERE MENUID = :menuid
        ";
        $stidRecipe = oci_parse($conn, $sqlRecipe);
        oci_bind_by_name($stidRecipe, ':menuid', $item['MENUID']);
        oci_execute($stidRecipe);

        while ($r = oci_fetch_assoc($stidRecipe)) {
            $qty = $r['QTYUSED'] * $item['QUANTITY'];

            $sqlUpdate = "
                UPDATE INGREDIENT 
                SET QTYONHAND = QTYONHAND + :qty
                WHERE INGREDIENTID = :ingid
            ";
            $stidUpdate = oci_parse($conn, $sqlUpdate);
            oci_bind_by_name($stidUpdate, ':qty', $qty);
            oci_bind_by_name($stidUpdate, ':ingid', $r['INGREDIENTID']);
            oci_execute($stidUpdate, OCI_NO_AUTO_COMMIT);
        }
    }

    /* =========================
       4) ลบข้อมูล Order
    ========================== */
    foreach ([
        "DELETE FROM ORDER_PROFIT WHERE ORDERID = :orderid",
        "DELETE FROM ORDER_ITEM WHERE ORDERID = :orderid",
        "DELETE FROM TRANSACTION WHERE ORDERID = :orderid"
    ] as $sql) {
        $stid = oci_parse($conn, $sql);
        oci_bind_by_name($stid, ':orderid', $orderid);
        oci_execute($stid, OCI_NO_AUTO_COMMIT);
    }

    // ✅ ทุกอย่างผ่าน → COMMIT
    oci_commit($conn);

    header("Location: order_list.php?msg=deleted");
    exit();

} catch (Exception $e) {
    // ❌ มีอะไรพัง → rollback ทั้งหมด
    oci_rollback($conn);
    header("Location: order_list.php?error=delete_failed&detail=" . urlencode($e->getMessage()));
    exit();
}
