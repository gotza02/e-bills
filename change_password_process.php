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

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message_login'] = "กรุณาเข้าสู่ระบบเพื่อดำเนินการ";
    $_SESSION['message_type_login'] = "error";
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];

if (!function_exists('verify_csrf_token_change_password')) {
    function verify_csrf_token_change_password($token_from_form) {
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token_from_form)) {
            $_SESSION['message_change_password'] = "CSRF token ไม่ถูกต้องหรือหมดอายุการใช้งาน กรุณาลองอีกครั้ง";
            $_SESSION['message_type_change_password'] = "error";
            if (isset($_SESSION['csrf_token'])) unset($_SESSION['csrf_token']);
            header("Location: change_password_form.php");
            exit();
        }
        if (isset($_SESSION['csrf_token'])) unset($_SESSION['csrf_token']);
        return true;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $submitted_csrf_token = $_POST['csrf_token'] ?? '';
    verify_csrf_token_change_password($submitted_csrf_token);

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';
    $errors = [];

    if (empty($current_password)) {
        $errors[] = "กรุณากรอกรหัสผ่านปัจจุบัน";
    }
    if (empty($new_password)) {
        $errors[] = "กรุณากรอกรหัสผ่านใหม่";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร";
    }
    if ($new_password !== $confirm_new_password) {
        $errors[] = "รหัสผ่านใหม่และการยืนยันรหัสผ่านใหม่ไม่ตรงกัน";
    }

    if (!empty($errors)) {
        $_SESSION['message_change_password'] = implode("<br>", $errors);
        $_SESSION['message_type_change_password'] = "error";
        header("Location: change_password_form.php");
        exit();
    }

    if ($conn) {
        $sql_get_user = "SELECT password FROM users WHERE id = ?";
        $stmt_get_user = $conn->prepare($sql_get_user);
        if ($stmt_get_user) {
            $stmt_get_user->bind_param("i", $current_user_id);
            $stmt_get_user->execute();
            $result_user = $stmt_get_user->get_result();

            if ($result_user->num_rows === 1) {
                $user = $result_user->fetch_assoc();
                if (password_verify($current_password, $user['password'])) {
                    $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $sql_update_pass = "UPDATE users SET password = ?, remember_token_hash = NULL, remember_token_expires_at = NULL WHERE id = ?";
                    $stmt_update_pass = $conn->prepare($sql_update_pass);
                    if ($stmt_update_pass) {
                        $stmt_update_pass->bind_param("si", $hashed_new_password, $current_user_id);
                        if ($stmt_update_pass->execute()) {
                            $_SESSION['message'] = "เปลี่ยนรหัสผ่านสำเร็จแล้ว";
                            $_SESSION['message_type'] = "success";
                            
                            if (isset($_COOKIE['remember_me_token'])) {
                                setcookie('remember_me_token', '', time() - 3600, "/", "", isset($_SERVER['HTTPS']), true);
                            }
                            if (isset($_COOKIE['remember_me_user'])) {
                                setcookie('remember_me_user', '', time() - 3600, "/", "", isset($_SERVER['HTTPS']), true);
                            }

                            header("Location: index.php");
                            exit();
                        } else {
                            $_SESSION['message_change_password'] = "เกิดข้อผิดพลาดในการอัปเดตรหัสผ่าน: " . htmlspecialchars($stmt_update_pass->error);
                            $_SESSION['message_type_change_password'] = "error";
                        }
                        $stmt_update_pass->close();
                    } else {
                        $_SESSION['message_change_password'] = "เกิดข้อผิดพลาดในการเตรียม SQL (update): " . htmlspecialchars($conn->error);
                        $_SESSION['message_type_change_password'] = "error";
                    }
                } else {
                    $_SESSION['message_change_password'] = "รหัสผ่านปัจจุบันไม่ถูกต้อง";
                    $_SESSION['message_type_change_password'] = "error";
                }
            } else {
                $_SESSION['message_change_password'] = "ไม่พบข้อมูลผู้ใช้";
                $_SESSION['message_type_change_password'] = "error";
            }
            $stmt_get_user->close();
        } else {
            $_SESSION['message_change_password'] = "เกิดข้อผิดพลาดในการเตรียม SQL (select): " . htmlspecialchars($conn->error);
            $_SESSION['message_type_change_password'] = "error";
        }
    } else {
        $_SESSION['message_change_password'] = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้";
        $_SESSION['message_type_change_password'] = "error";
    }

    header("Location: change_password_form.php");
    exit();

} else {
    $_SESSION['message_change_password'] = "Invalid request method.";
    $_SESSION['message_type_change_password'] = "error";
    header("Location: change_password_form.php");
    exit();
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>