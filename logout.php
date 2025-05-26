<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'use_strict_mode' => true,
        'gc_maxlifetime' => 1800
    ]);
}
require_once 'db_connect.php'; // Include for database connection

if (isset($_SESSION['user_id']) && $conn) {
    // Clear remember_me token from database for this user
    $user_id_to_logout = $_SESSION['user_id'];
    $sql_clear_token = "UPDATE users SET remember_token_hash = NULL, remember_token_expires_at = NULL WHERE id = ?";
    $stmt_clear_token = $conn->prepare($sql_clear_token);
    if ($stmt_clear_token) {
        $stmt_clear_token->bind_param("i", $user_id_to_logout);
        $stmt_clear_token->execute();
        $stmt_clear_token->close();
    }
}

// Clear remember_me cookies
if (isset($_COOKIE['remember_me_token'])) {
    setcookie('remember_me_token', '', time() - 3600, "/", "", isset($_SERVER['HTTPS']), true);
}
if (isset($_COOKIE['remember_me_user'])) {
    setcookie('remember_me_user', '', time() - 3600, "/", "", isset($_SERVER['HTTPS']), true);
}

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

if (session_status() == PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'use_strict_mode' => true,
        'gc_maxlifetime' => 1800 
    ]);
}
$_SESSION['message_login'] = "คุณออกจากระบบเรียบร้อยแล้ว";
$_SESSION['message_type_login'] = "info";
header("Location: login.php");
exit();
?>