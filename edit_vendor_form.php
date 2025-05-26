<?php
$page_title = "แก้ไขข้อมูลร้านค้า";
require_once 'header.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message_login'] = "กรุณาเข้าสู่ระบบเพื่อดำเนินการ";
    $_SESSION['message_type_login'] = "warning";
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = "ID ร้านค้าไม่ถูกต้อง";
    $_SESSION['message_type'] = "error";
    header("Location: manage_vendors.php");
    exit();
}
$vendor_id_to_edit = (int)$_GET['id'];

$vendor_data = null;
if ($conn) {
    $sql = "SELECT id, name, category, notes FROM vendors WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $vendor_id_to_edit, $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $vendor_data = $result->fetch_assoc();
        } else {
            $_SESSION['message'] = "ไม่พบร้านค้าที่ต้องการแก้ไข หรือคุณไม่มีสิทธิ์";
            $_SESSION['message_type'] = "error";
            header("Location: manage_vendors.php");
            exit();
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = "เกิดข้อผิดพลาดในการดึงข้อมูลร้านค้า: " . $conn->error;
        $_SESSION['message_type'] = "error";
        header("Location: manage_vendors.php");
        exit();
    }
}

// Retrieve old form data if validation failed during an update attempt
$old_data = $_SESSION['old_vendor_form_data'][$vendor_id_to_edit] ?? [];
if (isset($_SESSION['old_vendor_form_data'][$vendor_id_to_edit])) {
    unset($_SESSION['old_vendor_form_data'][$vendor_id_to_edit]);
}

$name_val = $old_data['name'] ?? ($vendor_data['name'] ?? '');
$category_val = $old_data['category'] ?? ($vendor_data['category'] ?? '');
$notes_val = $old_data['notes'] ?? ($vendor_data['notes'] ?? '');

?>

<h2 class="h4 mb-4"><?php echo htmlspecialchars($page_title); ?>: <?php echo htmlspecialchars($vendor_data['name']); ?></h2>

<?php
if (isset($_SESSION['message_vendor_edit'])) {
    $alert_type = $_SESSION['message_type_vendor_edit'] ?? 'info';
    $alert_class = 'alert-' . htmlspecialchars($alert_type);
    echo "<div class='alert " . $alert_class . " text-center alert-dismissible fade show' role='alert'>" . htmlspecialchars($_SESSION['message_vendor_edit']);
    echo "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    unset($_SESSION['message_vendor_edit']);
    unset($_SESSION['message_type_vendor_edit']);
}
?>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="edit_vendor_process.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="vendor_id" value="<?php echo htmlspecialchars($vendor_id_to_edit); ?>">
            
            <div class="mb-3">
                <label for="name" class="form-label">ชื่อร้านค้า <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" id="name" name="name" value="<?php echo htmlspecialchars($name_val); ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="category" class="form-label">หมวดหมู่</label>
                <input type="text" class="form-control form-control-sm" id="category" name="category" value="<?php echo htmlspecialchars($category_val); ?>">
            </div>
            
            <div class="mb-3">
                <label for="notes" class="form-label">หมายเหตุ</label>
                <textarea class="form-control form-control-sm" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($notes_val); ?></textarea>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-sm">💾 อัปเดตข้อมูลร้านค้า</button>
                <a href="manage_vendors.php" class="btn btn-outline-secondary btn-sm ms-2">ยกเลิก</a>
            </div>
        </form>
    </div>
</div>

<?php
require_once 'footer.php';
?>