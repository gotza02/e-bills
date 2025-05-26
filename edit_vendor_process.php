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
        $_SESSION['message_vendor_edit'] = "CSRF token ไม่ถูกต้องหรือหมดอายุการใช้งาน";
        $_SESSION['message_type_vendor_edit'] = "error";
        if (isset($_POST['vendor_id'])) {
            header("Location: edit_vendor_form.php?id=" . $_POST['vendor_id']);
        } else {
            header("Location: manage_vendors.php");
        }
        exit();
    }
    unset($_SESSION['csrf_token']);

    $vendor_id = isset($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : 0;
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '') ?: null;
    $notes = trim($_POST['notes'] ?? '') ?: null;

    $errors = [];
    if (empty($vendor_id)) {
        $errors[] = "ID ร้านค้าไม่ถูกต้อง";
    }
    if (empty($name)) {
        $errors[] = "กรุณากรอกชื่อร้านค้า";
    }

    if (!empty($errors)) {
        $_SESSION['message_vendor_edit'] = implode("<br>", $errors);
        $_SESSION['message_type_vendor_edit'] = "error";
        $_SESSION['old_vendor_form_data'][$vendor_id] = $_POST;
        header("Location: edit_vendor_form.php?id=" . $vendor_id);
        exit();
    }

    if ($conn) {
        // Check for duplicate name for this user (excluding the current vendor being edited)
        $sql_check = "SELECT id FROM vendors WHERE user_id = ? AND name = ? AND id != ?";
        $stmt_check = $conn->prepare($sql_check);
        if ($stmt_check) {
            $stmt_check->bind_param("isi", $current_user_id, $name, $vendor_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows > 0) {
                $_SESSION['message_vendor_edit'] = "ชื่อร้านค้า '" . htmlspecialchars($name) . "' นี้มีอยู่ในระบบแล้วสำหรับร้านค้าอื่น";
                $_SESSION['message_type_vendor_edit'] = "error";
                $_SESSION['old_vendor_form_data'][$vendor_id] = $_POST;
                header("Location: edit_vendor_form.php?id=" . $vendor_id);
                exit();
            }
            $stmt_check->close();
        }


        $sql = "UPDATE vendors SET name = ?, category = ?, notes = ? WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sssii", $name, $category, $notes, $vendor_id, $current_user_id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $_SESSION['message'] = "อัปเดตข้อมูลร้านค้า '" . htmlspecialchars($name) . "' เรียบร้อยแล้ว";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "ไม่มีข้อมูลที่เปลี่ยนแปลง หรือคุณไม่มีสิทธิ์แก้ไขร้านค้านี้";
                    $_SESSION['message_type'] = "warning";
                }
                header("Location: manage_vendors.php");
                exit();
            } else {
                $_SESSION['message_vendor_edit'] = "เกิดข้อผิดพลาดในการอัปเดตข้อมูลร้านค้า: " . $stmt->error;
                $_SESSION['message_type_vendor_edit'] = "error";
            }
            $stmt->close();
        } else {
            $_SESSION['message_vendor_edit'] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
            $_SESSION['message_type_vendor_edit'] = "error";
        }
    } else {
        $_SESSION['message_vendor_edit'] = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้";
        $_SESSION['message_type_vendor_edit'] = "error";
    }
    $_SESSION['old_vendor_form_data'][$vendor_id] = $_POST;
    header("Location: edit_vendor_form.php?id=" . $vendor_id);
    exit();
} else {
    header("Location: manage_vendors.php");
    exit();
}
?>