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

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token_from_form, $session_token_name = 'csrf_token', $redirect_path = 'login.php') {
        if (empty($_SESSION[$session_token_name]) || !hash_equals($_SESSION[$session_token_name], $token_from_form)) {
            $_SESSION['message_login'] = "CSRF token ไม่ถูกต้องหรือหมดอายุการใช้งาน";
            $_SESSION['message_type_login'] = "error";
            if (isset($_SESSION[$session_token_name])) unset($_SESSION[$session_token_name]);
            $_SESSION['old_login_data'] = $_POST;
            header("Location: " . $redirect_path);
            exit();
        }
        if (isset($_SESSION[$session_token_name])) unset($_SESSION[$session_token_name]);
        return true;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $submitted_csrf_token = $_POST['csrf_token'] ?? '';
    verify_csrf_token($submitted_csrf_token, 'csrf_token', 'login.php');

    $username_or_email = trim($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']) && $_POST['remember_me'] == '1';

    $_SESSION['old_login_data'] = $_POST;

    if (empty($username_or_email) || empty($password)) {
        $_SESSION['message_login'] = "กรุณากรอกชื่อผู้ใช้งาน/อีเมล และรหัสผ่าน";
        $_SESSION['message_type_login'] = "error";
        header("Location: login.php");
        exit();
    }

    $sql = "SELECT id, username, password FROM users WHERE username = ? OR email = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ss", $username_or_email, $username_or_email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                unset($_SESSION['old_login_data']);

                if ($remember_me) {
                    $token = bin2hex(random_bytes(32));
                    $token_hash = hash('sha256', $token);
                    $expires_at = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days

                    $sql_update_token = "UPDATE users SET remember_token_hash = ?, remember_token_expires_at = ? WHERE id = ?";
                    $stmt_update_token = $conn->prepare($sql_update_token);
                    if ($stmt_update_token) {
                        $stmt_update_token->bind_param("ssi", $token_hash, $expires_at, $user['id']);
                        $stmt_update_token->execute();
                        $stmt_update_token->close();

                        $cookie_options = [
                            'expires' => time() + (30 * 24 * 60 * 60), // 30 days
                            'path' => '/',
                            'domain' => '', // Accessible to the whole domain
                            'secure' => isset($_SERVER['HTTPS']), // Send only over HTTPS
                            'httponly' => true, // Not accessible via JavaScript
                            'samesite' => 'Lax' // CSRF protection
                        ];
                        setcookie('remember_me_token', $token, $cookie_options);
                        setcookie('remember_me_user', $user['id'], $cookie_options);
                    }
                } else {
                     $sql_clear_remember_token = "UPDATE users SET remember_token_hash = NULL, remember_token_expires_at = NULL WHERE id = ?";
                     $stmt_clear_token = $conn->prepare($sql_clear_remember_token);
                     if($stmt_clear_token){
                        $stmt_clear_token->bind_param("i", $user['id']);
                        $stmt_clear_token->execute();
                        $stmt_clear_token->close();
                     }
                     if (isset($_COOKIE['remember_me_token'])) {
                        setcookie('remember_me_token', '', time() - 3600, "/", "", isset($_SERVER['HTTPS']), true);
                     }
                     if (isset($_COOKIE['remember_me_user'])) {
                        setcookie('remember_me_user', '', time() - 3600, "/", "", isset($_SERVER['HTTPS']), true);
                     }
                }

                $redirect_url = $_SESSION['redirect_after_login'] ?? 'index.php';
                unset($_SESSION['redirect_after_login']);
                
                $_SESSION['message'] = "เข้าสู่ระบบสำเร็จ!";
                $_SESSION['message_type'] = "success";

                header("Location: " . $redirect_url);
                exit();
            } else {
                $_SESSION['message_login'] = "ชื่อผู้ใช้งาน/อีเมล หรือรหัสผ่านไม่ถูกต้อง";
                $_SESSION['message_type_login'] = "error";
            }
        } else {
            $_SESSION['message_login'] = "ชื่อผู้ใช้งาน/อีเมล หรือรหัสผ่านไม่ถูกต้อง";
            $_SESSION['message_type_login'] = "error";
        }
        $stmt->close();
    } else {
        $_SESSION['message_login'] = "เกิดข้อผิดพลาด: " . htmlspecialchars($conn->error);
        $_SESSION['message_type_login'] = "error";
    }
    header("Location: login.php");
    exit();

} else {
    $_SESSION['message_login'] = "Invalid request method.";
    $_SESSION['message_type_login'] = "error";
    header("Location: login.php");
    exit();
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>