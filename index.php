<?php
session_start();
header('Content-Type: application/json');

$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

echo json_encode([
    'logged_in' => $isLoggedIn,
    'session_id' => session_id()
]);
?>