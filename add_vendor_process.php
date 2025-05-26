<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message_login'] = "กรุณาเข้าสู่ระบบเพื่อดำเนินการ";
    $_SESSION['message_type_login'] = "error";
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $_SESSION['message_vendor_add'] = "CSRF token ไม่ถูกต้องหรือหมดอายุการใช้งาน";
        $_SESSION['message_type_vendor_add'] = "error";
        header("Location: add_vendor_form.php");
        exit();
    }
    unset($_SESSION['csrf_token']); // Consume token

    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '') ?: null;
    $notes = trim($_POST['notes'] ?? '') ?: null;

    $errors = [];
    if (empty($name)) {
        $errors[] = "กรุณากรอกชื่อร้านค้า";
    }

    if (!empty($errors)) {
        $_SESSION['message_vendor_add'] = implode("<br>", $errors);
        $_SESSION['message_type_vendor_add'] = "error";
        $_SESSION['old_vendor_form_data'] = $_POST;
        header("Location: add_vendor_form.php");
        exit();
    }

    if ($conn) {
        // Check for duplicate name for this user
        $sql_check = "SELECT id FROM vendors WHERE user_id = ? AND name = ?";
        $stmt_check = $conn->prepare($sql_check);
        if ($stmt_check) {
            $stmt_check->bind_param("is", $current_user_id, $name);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows > 0) {
                $_SESSION['message_vendor_add'] = "ชื่อร้านค้า '" . htmlspecialchars($name) . "' นี้มีอยู่ในระบบแล้ว";
                $_SESSION['message_type_vendor_add'] = "error";
                $_SESSION['old_vendor_form_data'] = $_POST;
                header("Location: add_vendor_form.php");
                exit();
            }
            $stmt_check->close();
        }

        $sql = "INSERT INTO vendors (user_id, name, category, notes) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("isss", $current_user_id, $name, $category, $notes);
            if ($stmt->execute()) {
                $_SESSION['message'] = "เพิ่มร้านค้า '" . htmlspecialchars($name) . "' เรียบร้อยแล้ว";
                $_SESSION['message_type'] = "success";
                header("Location: manage_vendors.php");
                exit();
            } else {
                $_SESSION['message_vendor_add'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลร้านค้า: " . $stmt->error;
                $_SESSION['message_type_vendor_add'] = "error";
            }
            $stmt->close();
        } else {
            $_SESSION['message_vendor_add'] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
            $_SESSION['message_type_vendor_add'] = "error";
        }
    } else {
        $_SESSION['message_vendor_add'] = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้";
        $_SESSION['message_type_vendor_add'] = "error";
    }
    $_SESSION['old_vendor_form_data'] = $_POST;
    header("Location: add_vendor_form.php");
    exit();
} else {
    header("Location: add_vendor_form.php");
    exit();
}
?>