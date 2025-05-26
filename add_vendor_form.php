<?php
$page_title = "เพิ่มร้านค้าใหม่";
require_once 'header.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message_login'] = "กรุณาเข้าสู่ระบบเพื่อดำเนินการ";
    $_SESSION['message_type_login'] = "warning";
    header("Location: login.php");
    exit();
}

$old_data = $_SESSION['old_vendor_form_data'] ?? [];
if (isset($_SESSION['old_vendor_form_data'])) {
    unset($_SESSION['old_vendor_form_data']);
}
?>

<h2 class="h4 mb-4"><?php echo htmlspecialchars($page_title); ?></h2>

<?php
if (isset($_SESSION['message_vendor_add'])) {
    $alert_type = $_SESSION['message_type_vendor_add'] ?? 'info';
    $alert_class = 'alert-' . htmlspecialchars($alert_type);
    echo "<div class='alert " . $alert_class . " text-center alert-dismissible fade show' role='alert'>" . htmlspecialchars($_SESSION['message_vendor_add']);
    echo "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    unset($_SESSION['message_vendor_add']);
    unset($_SESSION['message_type_vendor_add']);
}
?>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="add_vendor_process.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            
            <div class="mb-3">
                <label for="name" class="form-label">ชื่อร้านค้า <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" id="name" name="name" value="<?php echo htmlspecialchars($old_data['name'] ?? ''); ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="category" class="form-label">หมวดหมู่ (เช่น ร้านอาหาร, ซุปเปอร์มาร์เก็ต)</label>
                <input type="text" class="form-control form-control-sm" id="category" name="category" value="<?php echo htmlspecialchars($old_data['category'] ?? ''); ?>">
            </div>
            
            <div class="mb-3">
                <label for="notes" class="form-label">หมายเหตุ</label>
                <textarea class="form-control form-control-sm" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($old_data['notes'] ?? ''); ?></textarea>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-sm">💾 บันทึกร้านค้า</button>
                <a href="manage_vendors.php" class="btn btn-outline-secondary btn-sm ms-2">ยกเลิก</a>
            </div>
        </form>
    </div>
</div>

<?php
require_once 'footer.php';
?>