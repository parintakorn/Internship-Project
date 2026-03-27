<?php
$uname = "RESTAURANT";   // หรือ user ใหม่ที่คุณสร้างเอง
$pass = "1234";      // รหัสจริงของ Oracle User
$service = "XEPDB1"; // service name จาก SQL Developer

$conn = oci_connect($uname, $pass, "localhost/XEPDB1", 'UTF8');

date_default_timezone_set('Asia/Bangkok');

// และเพิ่มหลัง oci_connect()
$timezone_sql = "ALTER SESSION SET TIME_ZONE = 'Asia/Bangkok'";
$tz_stid = oci_parse($conn, $timezone_sql);
oci_execute($tz_stid);

if(!$conn){
    $e = oci_error();
    echo "Connection Failed: " . $e['message'];
} else {
    // echo "Connected!";  // ปิดไว้ก็ได้
}
?>
