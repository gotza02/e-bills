<?php
$page_title = "ลงทะเบียนเข้าใช้งาน";
$default_app_title = "โปรแกรมบันทึกค่าใช้จ่ายส่วนตัว"; // Ensure this is defined

if (session_status() == PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'use_strict_mode' => true,
        'gc_maxlifetime' => 1800
    ]);
}

// หากผู้ใช้ login อยู่แล้ว ให้ redirect ไปหน้าหลัก
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Generate CSRF token if not in header.php for public pages or handle differently
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
$csrf_token = generate_csrf_token();

// Simulate header.php parts for public pages if header.php forces login
date_default_timezone_set('Asia/Bangkok'); // Ensure timezone is set
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title) . ' | ' . htmlspecialchars($default_app_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <header class="py-3 bg-white shadow-sm sticky-top">
        <nav class="navbar navbar-expand-lg navbar-light bg-white container">
            <a class="navbar-brand text-primary fw-bold h3 mb-0" href="index.php">ระบบบันทึกค่าใช้จ่าย</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavExpenseTracker" aria-controls="navbarNavExpenseTracker" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNavExpenseTracker">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'login.php') ? 'active' : ''; ?>" href="login.php">🔑 เข้าสู่ระบบ</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'register.php') ? 'active' : ''; ?>" href="register.php">📝 ลงทะเบียน</a>
                    </li>
                </ul>
            </div>
        </nav>
    </header>
    <main class="container py-4">

        <div class="row justify-content-center mt-4">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="card-title text-center mb-4">สร้างบัญชีใหม่</h2>

                        <?php
                        if (isset($_SESSION['message_register'])) {
                            $alert_type = $_SESSION['message_type_register'] ?? 'info';
                            $alert_class = 'alert-' . htmlspecialchars($alert_type);
                            echo "<div class='alert " . $alert_class . " text-center small' role='alert'>" . htmlspecialchars($_SESSION['message_register']) . "</div>";
                            unset($_SESSION['message_register']);
                            unset($_SESSION['message_type_register']);
                        }
                        ?>

                        <form action="register_process.php" method="POST" id="registerForm">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="mb-3">
                                <label for="username" class="form-label">ชื่อผู้ใช้งาน:</label>
                                <input type="text" class="form-control form-control-sm" id="username" name="username" value="<?php echo htmlspecialchars($_SESSION['old_form_data']['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">อีเมล:</label>
                                <input type="email" class="form-control form-control-sm" id="email" name="email" value="<?php echo htmlspecialchars($_SESSION['old_form_data']['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">รหัสผ่าน (อย่างน้อย 6 ตัวอักษร):</label>
                                <input type="password" class="form-control form-control-sm" id="password" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">ยืนยันรหัสผ่าน:</label>
                                <input type="password" class="form-control form-control-sm" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-sm">ลงทะเบียน</button>
                            </div>
                        </form>
                        <p class="mt-3 text-center small">
                            มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบที่นี่</a>
                        </p>
                         <?php if (isset($_SESSION['old_form_data'])) unset($_SESSION['old_form_data']); ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="py-4 bg-light text-center text-muted border-top mt-auto">
        <div class="container">
            <p class="mb-0 small">&copy; <?php echo date("Y"); ?> <?php echo htmlspecialchars($default_app_title); ?>. All Rights Reserved.</p>
            <p class="mb-0 small"><em>สร้างสรรค์โดย คุณก๊อต</em></p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>