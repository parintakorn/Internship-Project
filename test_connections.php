
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing Oracle Connection</h2>";

$host = 'localhost';
$port = '1521';
$service_name = 'XE'; // เปลี่ยนตามของคุณ
$username = 'your_username';
$password = 'your_password';

echo "<p>Host: $host</p>";
echo "<p>Port: $port</p>";
echo "<p>Service Name: $service_name</p>";
echo "<p>Username: $username</p>";

$connection_string = "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=$host)(PORT=$port))(CONNECT_DATA=(SERVICE_NAME=$service_name)))";

echo "<p>Connection String: $connection_string</p>";

// ทดสอบเชื่อมต่อ
$conn = @oci_connect($username, $password, $connection_string);

if (!$conn) {
    $error = oci_error();
    echo "<p style='color: red;'><strong>Connection Failed!</strong></p>";
    echo "<pre>";
    print_r($error);
    echo "</pre>";
    
    echo "<h3>แนวทางแก้ไข:</h3>";
    echo "<ol>";
    echo "<li>ตรวจสอบว่า Oracle Database เปิดอยู่หรือไม่</li>";
    echo "<li>ตรวจสอบ username/password</li>";
    echo "<li>ตรวจสอบ service_name (ลอง: XE, ORCL, XEPDB1)</li>";
    echo "<li>ตรวจสอบว่า PHP มี OCI8 extension หรือไม่ (ดูด้านล่าง)</li>";
    echo "</ol>";
    
} else {
    echo "<p style='color: green;'><strong>✓ Connection Successful!</strong></p>";
    
    // ทดสอบ Query
    echo "<h3>Testing Query...</h3>";
    
    $sql = "SELECT * FROM PRODUCTS";
    $stmt = oci_parse($conn, $sql);
    
    if (!$stmt) {
        $error = oci_error($conn);
        echo "<p style='color: red;'>Parse Error: " . $error['message'] . "</p>";
    } else {
        $result = oci_execute($stmt);
        
        if (!$result) {
            $error = oci_error($stmt);
            echo "<p style='color: red;'>Execute Error: " . $error['message'] . "</p>";
        } else {
            echo "<p style='color: green;'>✓ Query Successful!</p>";
            
            echo "<h3>Products:</h3>";
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Name</th><th>Category</th><th>Created</th></tr>";
            
            $count = 0;
            while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
                echo "<tr>";
                echo "<td>" . $row['PRODUCT_ID'] . "</td>";
                echo "<td>" . $row['PRODUCT_NAME'] . "</td>";
                echo "<td>" . $row['CATEGORY'] . "</td>";
                echo "<td>" . $row['CREATED_DATE'] . "</td>";
                echo "</tr>";
                $count++;
            }
            
            echo "</table>";
            echo "<p>Total: $count products</p>";
        }
    }
    
    oci_close($conn);
}

// ตรวจสอบ OCI8 Extension
echo "<h3>PHP Configuration:</h3>";
echo "<p>PHP Version: " . phpversion() . "</p>";

if (extension_loaded('oci8')) {