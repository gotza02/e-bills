<?php
$page_title = "เปลี่ยนรหัสผ่าน";
require_once 'header.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message_login'] = "กรุณาเข้าสู่ระบบเพื่อดำเนินการ";
    $_SESSION['message_type_login'] = "warning";
    header("Location: login.php");
    exit();
}
?>

<div class="row justify-content-center mt-4">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="card-title text-center mb-4">เปลี่ยนรหัสผ่าน</h2>

                <?php
                if (isset($_SESSION['message_change_password'])) {
                    $alert_type = $_SESSION['message_type_change_password'] ?? 'info';
                    $alert_class = 'alert-' . htmlspecialchars($alert_type);
                    if ($alert_type === 'error') $alert_class = 'alert-danger';
                    if ($alert_type === 'success') $alert_class = 'alert-success';
                    echo "<div class='alert " . $alert_class . " text-center small' role='alert'>" . htmlspecialchars($_SESSION['message_change_password']) . "</div>";
                    unset($_SESSION['message_change_password']);
                    unset($_SESSION['message_type_change_password']);
                }
                ?>

                <form action="change_password_process.php" method="POST" id="changePasswordForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">รหัสผ่านปัจจุบัน:</label>
                        <input type="password" class="form-control form-control-sm" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">รหัสผ่านใหม่ (อย่างน้อย 6 ตัวอักษร):</label>
                        <input type="password" class="form-control form-control-sm" id="new_password" name="new_password" required minlength="6">
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_new_password" class="form-label">ยืนยันรหัสผ่านใหม่:</label>
                        <input type="password" class="form-control form-control-sm" id="confirm_new_password" name="confirm_new_password" required minlength="6">
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">เปลี่ยนรหัสผ่าน</button>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm">ยกเลิก</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>