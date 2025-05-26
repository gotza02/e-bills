<?php
if (!defined('APP_TIMEZONE_SET')) {
    date_default_timezone_set('Asia/Bangkok');
    define('APP_TIMEZONE_SET', true);
}

if (session_status() == PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'use_strict_mode' => true,
        'gc_maxlifetime' => 1800
    ]);
}

require_once 'db_connect.php';

$current_page = basename($_SERVER['PHP_SELF']);
$public_pages = ['login.php', 'register.php', 'login_process.php', 'register_process.php', 'test_db.php'];

if (!isset($_SESSION['user_id'])) {
    if (isset($_COOKIE['remember_me_user'], $_COOKIE['remember_me_token'])) {
        $remember_user_id = $_COOKIE['remember_me_user'];
        $remember_token_from_cookie = $_COOKIE['remember_me_token'];

        if ($conn && !empty($remember_user_id) && !empty($remember_token_from_cookie)) {
            $sql_check_token = "SELECT id, username, remember_token_hash, remember_token_expires_at FROM users WHERE id = ?";
            $stmt_check_token = $conn->prepare($sql_check_token);

            if ($stmt_check_token) {
                $stmt_check_token->bind_param("i", $remember_user_id);
                $stmt_check_token->execute();
                $result_token = $stmt_check_token->get_result();

                if ($user_data = $result_token->fetch_assoc()) {
                    if ($user_data['remember_token_hash'] && hash_equals($user_data['remember_token_hash'], hash('sha256', $remember_token_from_cookie))) {
                        if ($user_data['remember_token_expires_at'] && strtotime($user_data['remember_token_expires_at']) > time()) {
                            session_regenerate_id(true);
                            $_SESSION['user_id'] = $user_data['id'];
                            $_SESSION['username'] = $user_data['username'];

                            $new_remember_token = bin2hex(random_bytes(32));
                            $new_remember_token_hash = hash('sha256', $new_remember_token);
                            $new_remember_expiry_date = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));

                            $sql_update_new_token = "UPDATE users SET remember_token_hash = ?, remember_token_expires_at = ? WHERE id = ?";
                            $stmt_update_new_token = $conn->prepare($sql_update_new_token);
                            if ($stmt_update_new_token) {
                                $stmt_update_new_token->bind_param("ssi", $new_remember_token_hash, $new_remember_expiry_date, $user_data['id']);
                                $stmt_update_new_token->execute();
                                $stmt_update_new_token->close();

                                $cookie_options_refresh = [
                                    'expires' => time() + (30 * 24 * 60 * 60),
                                    'path' => '/',
                                    'domain' => '',
                                    'secure' => isset($_SERVER['HTTPS']),
                                    'httponly' => true,
                                    'samesite' => 'Lax'
                                ];
                                setcookie('remember_me_token', $new_remember_token, $cookie_options_refresh);
                            }
                        } else {
                            $sql_clear_expired_token = "UPDATE users SET remember_token_hash = NULL, remember_token_expires_at = NULL WHERE id = ?";
                            $stmt_clear_expired_token = $conn->prepare($sql_clear_expired_token);
                            if($stmt_clear_expired_token){
                                $stmt_clear_expired_token->bind_param("i", $remember_user_id);
                                $stmt_clear_expired_token->execute();
                                $stmt_clear_expired_token->close();
                            }
                            setcookie('remember_me_token', '', time() - 3600, "/", "", isset($_SERVER['HTTPS']), true);
                            setcookie('remember_me_user', '', time() - 3600, "/", "", isset($_SERVER['HTTPS']), true);
                        }
                    } else {
                        setcookie('remember_me_token', '', time() - 3600, "/", "", isset($_SERVER['HTTPS']), true);
                        setcookie('remember_me_user', '', time() - 3600, "/", "", isset($_SERVER['HTTPS']), true);
                    }
                }
                $stmt_check_token->close();
            } else {
                error_log("Remember me DB error (prepare): " . $conn->error);
            }
        }
    }
}

if (!in_array($current_page, $public_pages) && !isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['message_login'] = "กรุณาเข้าสู่ระบบเพื่อใช้งาน";
    $_SESSION['message_type_login'] = "warning";
    header("Location: login.php");
    exit();
}

if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
$csrf_token = generate_csrf_token();

$default_app_title = "โปรแกรมบันทึกค่าใช้จ่ายส่วนตัว";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' | ' . htmlspecialchars($default_app_title) : htmlspecialchars($default_app_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <?php if (isset($custom_css_files) && is_array($custom_css_files)): ?>
        <?php foreach ($custom_css_files as $css_file): ?>
            <link href="<?php echo htmlspecialchars($css_file); ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <header class="py-3 bg-white shadow-sm sticky-top">
        <nav class="navbar navbar-expand-lg navbar-light bg-white container">
            <a class="navbar-brand text-primary fw-bold h3 mb-0" href="index.php">ระบบบันทึกค่าใช้จ่าย</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavExpenseTracker" aria-controls="navbarNavExpenseTracker" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNavExpenseTracker">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <span class="nav-link text-muted small d-none d-lg-inline">ผู้ใช้: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="index.php">🏠 หน้าหลัก</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'add_bill_form.php') ? 'active' : ''; ?>" href="add_bill_form.php">➕ เพิ่มบิลใหม่</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo in_array($current_page, ['manage_vendors.php', 'manage_products.php']) ? 'active' : ''; ?>" href="#" id="navbarManageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                📦 จัดการข้อมูล
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarManageDropdown">
                                <li><a class="dropdown-item <?php echo ($current_page == 'manage_vendors.php') ? 'active' : ''; ?>" href="manage_vendors.php">🏪 จัดการร้านค้า</a></li>
                                <li><a class="dropdown-item <?php echo ($current_page == 'manage_products.php') ? 'active' : ''; ?>" href="manage_products.php">🛍️ จัดการสินค้า</a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="reports.php">📊 ดูรายงาน</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo ($current_page == 'change_password_form.php') ? 'active' : ''; ?>" href="#" id="navbarUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                ⚙️ บัญชีผู้ใช้
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarUserDropdown">
                                <li><a class="dropdown-item <?php echo ($current_page == 'change_password_form.php') ? 'active' : ''; ?>" href="change_password_form.php">🔒 เปลี่ยนรหัสผ่าน</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">🚪 ออกจากระบบ</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'login.php') ? 'active' : ''; ?>" href="login.php">🔑 เข้าสู่ระบบ</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'register.php') ? 'active' : ''; ?>" href="register.php">📝 ลงทะเบียน</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
    </header>
    <main class="container py-4 flex-grow-1">
    <?php
    if (isset($_SESSION['message']) && $current_page !== 'login.php') {
        $message_type_global = $_SESSION['message_type'] ?? 'info';
        $allowed_alert_types_global = ['success', 'danger', 'warning', 'info', 'primary', 'secondary', 'light', 'dark'];
        $alert_class_suffix_global = in_array($message_type_global, $allowed_alert_types_global) ? $message_type_global : 'info';
        if ($message_type_global === 'error') $alert_class_suffix_global = 'danger';

        echo "<div class='alert alert-" . htmlspecialchars($alert_class_suffix_global) . " text-center alert-dismissible fade show' role='alert'>";
        echo htmlspecialchars($_SESSION['message']);
        echo "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>";
        echo "</div>";
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
    ?>