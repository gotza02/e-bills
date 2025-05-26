<?php
$page_title = "เพิ่มสินค้าใหม่เข้าคลัง";
require_once 'header.php'; // Handles session, auth, CSRF, db_connect

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message_login'] = "กรุณาเข้าสู่ระบบเพื่อดำเนินการ";
    $_SESSION['message_type_login'] = "warning";
    header("Location: login.php");
    exit();
}

// Retrieve old form data if validation failed
$old_data = $_SESSION['old_product_form_data'] ?? [];
if (isset($_SESSION['old_product_form_data'])) {
    unset($_SESSION['old_product_form_data']);
}

$source_page = $_GET['source'] ?? 'manage_products'; // To redirect back appropriately
?>

<h2 class="h4 mb-4"><?php echo htmlspecialchars($page_title); ?></h2>

<?php
// Display messages specific to product addition (from add_product_process.php)
if (isset($_SESSION['message_product_add'])) {
    $alert_type = $_SESSION['message_type_product_add'] ?? 'info';
    $allowed_alert_types = ['success', 'danger', 'warning', 'info'];
    $alert_class_suffix = in_array($alert_type, $allowed_alert_types) ? $alert_type : 'info';
     if ($alert_type === 'error') $alert_class_suffix = 'danger';


    echo "<div class='alert alert-" . htmlspecialchars($alert_class_suffix) . " text-center alert-dismissible fade show' role='alert'>";
    echo htmlspecialchars($_SESSION['message_product_add']);
    echo "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>";
    echo "</div>";
    unset($_SESSION['message_product_add']);
    unset($_SESSION['message_type_product_add']);
}
?>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="add_product_process.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="source_page" value="<?php echo htmlspecialchars($source_page); ?>">
            
            <div class="mb-3">
                <label for="name" class="form-label">ชื่อสินค้า <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" id="name" name="name" value="<?php echo htmlspecialchars($old_data['name'] ?? ''); ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="category" class="form-label">หมวดหมู่สินค้า (เช่น เครื่องดื่ม, ขนม, เครื่องใช้ไฟฟ้า)</label>
                <input type="text" class="form-control form-control-sm" id="category" name="category" value="<?php echo htmlspecialchars($old_data['category'] ?? ''); ?>">
            </div>

            <div class="mb-3">
                <label for="default_price" class="form-label">ราคาตั้งต้นต่อหน่วย (ถ้ามี)</label>
                <input type="number" class="form-control form-control-sm" id="default_price" name="default_price" value="<?php echo htmlspecialchars($old_data['default_price'] ?? ''); ?>" min="0" step="0.01" placeholder="เช่น 150.00">
            </div>
            
            <div class="mb-3">
                <label for="notes" class="form-label">หมายเหตุเกี่ยวกับสินค้า</label>
                <textarea class="form-control form-control-sm" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($old_data['notes'] ?? ''); ?></textarea>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-sm">💾 บันทึกสินค้า</button>
                <?php if ($source_page === 'add_bill'): ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm ms-2" onclick="window.close();">ปิดหน้าต่างนี้</button>
                    <small class="d-block mt-2 text-muted">หลังจากบันทึก กรุณารีเฟรชหน้าเพิ่มบิลเพื่อดูสินค้าใหม่ในรายการ (หรือปิดหน้าต่างนี้)</small>
                <?php else: ?>
                    <a href="manage_products.php" class="btn btn-outline-secondary btn-sm ms-2">ยกเลิก</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php
require_once 'footer.php';
?>