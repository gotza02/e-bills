<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'use_strict_mode' => true,
        'gc_maxlifetime' => 1800
    ]);
}
require_once 'db_connect.php';

if (!function_exists('verify_csrf_token')) { // Function should be globally available
    function verify_csrf_token($token_from_form, $session_token_name = 'csrf_token', $redirect_path = 'register.php') {
        if (empty($_SESSION[$session_token_name]) || !hash_equals($_SESSION[$session_token_name], $token_from_form)) {
            $_SESSION['message_register'] = "CSRF token ไม่ถูกต้องหรือหมดอายุการใช้งาน";
            $_SESSION['message_type_register'] = "error";
            if (isset($_SESSION[$session_token_name])) unset($_SESSION[$session_token_name]);
            header("Location: " . $redirect_path);
            exit();
        }
        if (isset($_SESSION[$session_token_name])) unset($_SESSION[$session_token_name]);
        return true;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $submitted_csrf_token = $_POST['csrf_token'] ?? '';
    verify_csrf_token($submitted_csrf_token, 'csrf_token', 'register.php');

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $errors = [];

    $_SESSION['old_form_data'] = $_POST;

    if (empty($username)) {
        $errors[] = "กรุณากรอกชื่อผู้ใช้งาน";
    } elseif (strlen($username) < 3) {
         $errors[] = "ชื่อผู้ใช้งานต้องมีอย่างน้อย 3 ตัวอักษร";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "ชื่อผู้ใช้งานสามารถมีได้เฉพาะตัวอักษรภาษาอังกฤษ, ตัวเลข และเครื่องหมาย _ เท่านั้น";
    }

    if (empty($email)) {
        $errors[] = "กรุณากรอกอีเมล";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "รูปแบบอีเมลไม่ถูกต้อง";
    }

    if (empty($password)) {
        $errors[] = "กรุณากรอกรหัสผ่าน";
    } elseif (strlen($password) < 6) {
        $errors[] = "รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร";
    }

    if ($password !== $confirm_password) {
        $errors[] = "รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน";
    }

    if (empty($errors)) {
        $sql_check = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt_check = $conn->prepare($sql_check);
        if ($stmt_check) {
            $stmt_check->bind_param("ss", $username, $email);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows > 0) {
                $existing_user = $result_check->fetch_assoc(); // Not used yet, but good for debugging
                // More specific error messages
                $stmt_check_username = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $stmt_check_username->bind_param("s", $username);
                $stmt_check_username->execute();
                if ($stmt_check_username->get_result()->num_rows > 0) {
                    $errors[] = "ชื่อผู้ใช้งานนี้มีอยู่ในระบบแล้ว";
                }
                $stmt_check_username->close();

                $stmt_check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt_check_email->bind_param("s", $email);
                $stmt_check_email->execute();
                if ($stmt_check_email->get_result()->num_rows > 0) {
                     $errors[] = "อีเมลนี้มีอยู่ในระบบแล้ว";
                }
                $stmt_check_email->close();
            }
            $stmt_check->close();
        } else {
            $errors[] = "เกิดข้อผิดพลาดในการตรวจสอบข้อมูล: " . htmlspecialchars($conn->error);
        }
    }

    if (!empty($errors)) {
        $_SESSION['message_register'] = implode("<br>", $errors);
        $_SESSION['message_type_register'] = "error";
        header("Location: register.php");
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql_insert = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    if ($stmt_insert) {
        $stmt_insert->bind_param("sss", $username, $email, $hashed_password);
        if ($stmt_insert->execute()) {
            unset($_SESSION['old_form_data']);
            $_SESSION['message_login'] = "ลงทะเบียนสำเร็จ! กรุณาเข้าสู่ระบบ";
            $_SESSION['message_type_login'] = "success";
            header("Location: login.php");
            exit();
        } else {
            $_SESSION['message_register'] = "เกิดข้อผิดพลาดในการลงทะเบียน: " . htmlspecialchars($stmt_insert->error);
            $_SESSION['message_type_register'] = "error";
        }
        $stmt_insert->close();
    } else {
        $_SESSION['message_register'] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . htmlspecialchars($conn->error);
        $_SESSION['message_type_register'] = "error";
    }
    header("Location: register.php");
    exit();

} else {
    $_SESSION['message_register'] = "Invalid request method.";
    $_SESSION['message_type_register'] = "error";
    header("Location: register.php");
    exit();
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>