<?php
$uname = "RESTAURANT";
$pass = "1234";
$service = "XEPDB1";

$conn = oci_connect($uname, $pass, "localhost/$service", 'UTF8');

if(!$conn){
    $e = oci_error();
    echo "Connection Failed: " . $e['message'];
    exit;
}
?>