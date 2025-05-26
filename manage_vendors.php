<?php
$page_title = "จัดการร้านค้า";
require_once 'header.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message_login'] = "กรุณาเข้าสู่ระบบเพื่อดำเนินการ";
    $_SESSION['message_type_login'] = "warning";
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];

$vendors = [];
if ($conn) {
    $sql = "SELECT id, name, category, notes FROM vendors WHERE user_id = ? ORDER BY name ASC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $vendors[] = $row;
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = "เกิดข้อผิดพลาดในการดึงข้อมูลร้านค้า: " . $conn->error;
        $_SESSION['message_type'] = "error";
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4 mb-0"><?php echo htmlspecialchars($page_title); ?> (ของคุณ)</h2>
    <a href="add_vendor_form.php" class="btn btn-success btn-sm">➕ เพิ่มร้านค้าใหม่</a>
</div>

<?php
// Display messages if any (from header.php or set in this page)
if (isset($_SESSION['message'])) {
    $alert_type = $_SESSION['message_type'] ?? 'info';
    $alert_class = 'alert-' . htmlspecialchars($alert_type);
    echo "<div class='alert " . $alert_class . " text-center alert-dismissible fade show' role='alert'>" . htmlspecialchars($_SESSION['message']);
    echo "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if (!empty($vendors)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">ชื่อร้านค้า</th>
                            <th scope="col">หมวดหมู่</th>
                            <th scope="col">หมายเหตุ</th>
                            <th scope="col" class="text-center" style="width: 150px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vendors as $vendor): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($vendor['name']); ?></td>
                                <td><?php echo htmlspecialchars($vendor['category'] ?: '-'); ?></td>
                                <td>
                                    <span class="notes-column-truncate" title="<?php echo htmlspecialchars($vendor['notes'] ?? ''); ?>">
                                        <?php echo htmlspecialchars(mb_strimwidth($vendor['notes'] ?? '', 0, 40, "...")); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="edit_vendor_form.php?id=<?php echo $vendor['id']; ?>" class="btn btn-outline-primary btn-sm">✏️ แก้ไข</a>
                                    <a href="delete_vendor_process.php?id=<?php echo $vendor['id']; ?>&csrf_token=<?php echo htmlspecialchars($csrf_token); ?>" 
                                       class="btn btn-outline-danger btn-sm" 
                                       onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบร้านค้า: <?php echo htmlspecialchars(addslashes($vendor['name'])); ?>? การกระทำนี้ไม่สามารถย้อนกลับได้');">
                                       🗑️ ลบ
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center">คุณยังไม่มีข้อมูลร้านค้า <a href="add_vendor_form.php">เพิ่มร้านค้าใหม่?</a></div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once 'footer.php';
?>