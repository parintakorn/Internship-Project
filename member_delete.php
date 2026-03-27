<?php
include 'connect.php';

$id = $_GET['id'];

// ลบข้อมูลสมาชิก
$sql = "DELETE FROM MEMBER WHERE CUSTOMERID = :id";
$stid = oci_parse($conn, $sql);
oci_bind_by_name($stid, ':id', $id);

if (oci_execute($stid, OCI_COMMIT_ON_SUCCESS)) {
    header("Location: member_list.php?msg=deleted");
} else {
    $error = oci_error($stid);
    header("Location: member_list.php?error=delete_failed&msg=" . urlencode($error['message']));
}
exit;
?>