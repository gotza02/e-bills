<?php
$page_title = "จัดการสินค้า";
require_once 'header.php'; // Handles session, auth, CSRF, db_connect

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message_login'] = "กรุณาเข้าสู่ระบบเพื่อดำเนินการ";
    $_SESSION['message_type_login'] = "warning";
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];

$products = [];
if ($conn) {
    $sql = "SELECT id, name, category, default_price, notes FROM products WHERE user_id = ? ORDER BY name ASC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        $stmt->close();
    } else {
        // Store error message in session to display on the page via header.php or specific logic
        $_SESSION['message'] = "เกิดข้อผิดพลาดในการดึงข้อมูลสินค้า: " . htmlspecialchars($conn->error);
        $_SESSION['message_type'] = "error";
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4 mb-0"><?php echo htmlspecialchars($page_title); ?> (ของคุณ)</h2>
    <a href="add_product_form.php" class="btn btn-success btn-sm">➕ เพิ่มสินค้าใหม่</a>
</div>

<?php
// Display messages if any (from header.php or set in other process pages)
// This global message display is now part of header.php
// if (isset($_SESSION['message'])) {
//     $alert_type = $_SESSION['message_type'] ?? 'info';
//     $alert_class = 'alert-' . htmlspecialchars($alert_type);
//     echo "<div class='alert " . $alert_class . " text-center alert-dismissible fade show' role='alert'>" . htmlspecialchars($_SESSION['message']);
//     echo "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
//     unset($_SESSION['message']);
//     unset($_SESSION['message_type']);
// }
?>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if (!empty($products)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">ชื่อสินค้า</th>
                            <th scope="col">หมวดหมู่</th>
                            <th scope="col" class="text-end">ราคาตั้งต้น (บาท)</th>
                            <th scope="col">หมายเหตุ</th>
                            <th scope="col" class="text-center" style="width: 150px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['category'] ?: '-'); ?></td>
                                <td class="text-end"><?php echo ($product['default_price'] !== null) ? number_format($product['default_price'], 2) : '-'; ?></td>
                                <td>
                                    <span class="notes-column-truncate" title="<?php echo htmlspecialchars($product['notes'] ?? ''); ?>">
                                        <?php echo htmlspecialchars(mb_strimwidth($product['notes'] ?? '', 0, 40, "...")); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="edit_product_form.php?id=<?php echo $product['id']; ?>" class="btn btn-outline-primary btn-sm">✏️ แก้ไข</a>
                                    <a href="delete_product_process.php?id=<?php echo $product['id']; ?>&csrf_token=<?php echo htmlspecialchars($csrf_token); ?>"
                                       class="btn btn-outline-danger btn-sm"
                                       onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบสินค้า: <?php echo htmlspecialchars(addslashes($product['name'])); ?>? การกระทำนี้ไม่สามารถย้อนกลับได้ และอาจส่งผลกระทบต่อรายการบิลเก่าหากตั้งค่า Foreign Key เป็น RESTRICT');">
                                       🗑️ ลบ
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center">คุณยังไม่มีข้อมูลสินค้าในคลัง <a href="add_product_form.php">เพิ่มสินค้าใหม่?</a></div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once 'footer.php';
?>