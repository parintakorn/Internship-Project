<?php
$conn = oci_connect("restaurant", "1234", "localhost/XEPDB1");

if (!$conn) {
    $e = oci_error();
    echo "Connection FAILED<br>";
    echo htmlentities($e['message']);
} else {
    echo "Connection SUCCESS<br>";
}
?>
