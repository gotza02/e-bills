<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost"; // หรือ IP ของ DB server
$username = "gotza02";    // แก้ไขเป็น username ของคุณ
$password = "016593160Qq"; // แก้ไขเป็น password ของคุณ
$dbname = "my_expenses";  // แก้ไขเป็นชื่อ database ของคุณ

// สร้างการเชื่อมต่อ
$conn_test = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn_test->connect_error) {
    die("Connection failed: " . $conn_test->connect_error);
}
echo "Connected successfully to database: " . $dbname;

$conn_test->close();
?>