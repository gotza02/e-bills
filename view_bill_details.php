<?php
// $page_title is set after fetching bill details
require_once 'header.php'; // Handles session, auth, CSRF, db_connect

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message_login'] = "กรุณาเข้าสู่ระบบเพื่อดำเนินการ";
    $_SESSION['message_type_login'] = "warning";
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];

$bill_details = null;
$expense_items_in_bill = [];
$bill_total_calculated = 0;

if (!isset($_GET['bill_id']) || !is_numeric($_GET['bill_id'])) {
    $_SESSION['message'] = "ID บิลไม่ถูกต้อง หรือไม่พบ ID บิล";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}
$bill_id = (int)$_GET['bill_id'];

if ($conn) {
    // Fetch Bill Details, ensuring it belongs to the current user
    // <<<< MODIFIED HERE >>>>
    $sql_bill = "SELECT id, bill_date, vendor, notes, is_paid, created_at
                 FROM bills
                 WHERE id = ? AND user_id = ?"; // Added user_id = ?
    $stmt_bill = $conn->prepare($sql_bill);

    if ($stmt_bill) {
        $stmt_bill->bind_param("ii", $bill_id, $current_user_id);
        $stmt_bill->execute();
        $result_bill = $stmt_bill->get_result();
        if ($result_bill->num_rows === 1) {
            $bill_details = $result_bill->fetch_assoc();
            $page_title = "รายละเอียดบิล #" . htmlspecialchars($bill_details['id']); // Set page title here
            $result_bill->free();

            // Fetch Expense Items for this Bill (already user-specific due to bill_id check above)
            $sql_items = "SELECT id, item_name, quantity, price_per_unit, sub_total
                          FROM expense_items
                          WHERE bill_id = ? ORDER BY id ASC";
            $stmt_items = $conn->prepare($sql_items);
            if ($stmt_items) {
                $stmt_items->bind_param("i", $bill_id); // Use $bill_id which is confirmed to be of the user
                $stmt_items->execute();
                $result_items = $stmt_items->get_result();
                while ($row_item = $result_items->fetch_assoc()) {
                    $expense_items_in_bill[] = $row_item;
                    $bill_total_calculated += (float)$row_item['sub_total'];
                }
                $result_items->free();
                $stmt_items->close();
            } else {
                error_log("Error preparing statement to fetch items for bill_id " . $bill_id . ": " . $conn->error);
                $_SESSION['message'] = "คำเตือน: เกิดข้อผิดพลาดในการดึงรายการสินค้าบางส่วนของบิล (SIEP)";
                $_SESSION['message_type'] = "warning";
            }

        } else {
            $_SESSION['message'] = "ไม่พบบิล ID: " . htmlspecialchars($bill_id) . " หรือคุณไม่มีสิทธิ์เข้าดูบิลนี้";
            $_SESSION['message_type'] = "error";
            if($stmt_bill) $stmt_bill->close(); // Close if open
            header("Location: index.php");
            exit();
        }
        if($stmt_bill) $stmt_bill->close(); // Close if open and successful
    } else {
        $_SESSION['message'] = "เกิดข้อผิดพลาดในการดึงข้อมูลบิล: " . htmlspecialchars($conn->error);
        $_SESSION['message_type'] = "error";
        header("Location: index.php");
        exit();
    }
} else {
    $_SESSION['message'] = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

?>
<style>
    /* Styles from the original file - can be moved to a central CSS if preferred */
    /* body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; color: #333; } */
    /* .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 800px; margin: 20px auto; } */
    .bill-info { margin-bottom: 20px; padding: 15px; border: 1px solid #e0e0e0; border-radius: 5px; background-color: #f9f9f9; }
    .bill-info p { margin: 8px 0; font-size: 1em; } /* Adjusted font size */
    .bill-info strong { font-weight: bold; min-width: 120px; display: inline-block;}

    /* Table styles are mostly handled by Bootstrap, but can add specifics if needed */
    /* th, td { border: 1px solid #ddd; padding: 10px; text-align: left; } */
    /* th { background-color: #e9e9e9; } */
    /* td.number { text-align: right; } */
    /* .total-row th, .total-row td { font-weight: bold; background-color: #f0f0f0; } */

    /* .paid-status-yes { color: green; font-weight: bold; } */ /* Handled by Bootstrap badges */
    /* .paid-status-no { color: red; font-weight: bold; } */   /* Handled by Bootstrap badges */

    .actions { margin-top: 20px; text-align: center; }
    /* .btn styling is largely handled by Bootstrap */
</style>

<?php
// Global messages are handled in header.php
// if (isset($_SESSION['message'])) {
// $message_type = $_SESSION['message_type'] ?? 'info';
// $allowed_alert_types = ['success', 'danger', 'warning', 'info'];
// $alert_class_suffix = in_array($message_type, $allowed_alert_types) ? $message_type : 'info';
// if ($message_type === 'error') $alert_class_suffix = 'danger';
// echo "<div class='alert alert-" . htmlspecialchars($alert_class_suffix) . " text-center' role='alert'>" . htmlspecialchars($_SESSION['message']) . "</div>";
// unset($_SESSION['message']);
// unset($_SESSION['message_type']);
// }
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4 mb-0">รายละเอียดบิล #<?php echo htmlspecialchars($bill_details['id']); ?></h2>
    <a href="index.php" class="btn btn-sm btn-outline-secondary">⬅️ กลับไปหน้ารายการบิล</a>
</div>


<div class="card shadow-sm mb-4">
    <div class="card-header">
        ข้อมูลทั่วไป
    </div>
    <div class="card-body bill-info">
        <p><strong>วันที่บิล:</strong> <?php echo date("d F Y", strtotime($bill_details['bill_date'])); ?></p>
        <p><strong>ร้านค้า/ผู้ขาย:</strong> <?php echo htmlspecialchars($bill_details['vendor'] ?: '-'); ?></p>
        <p><strong>หมายเหตุ:</strong> <?php echo nl2br(htmlspecialchars($bill_details['notes'] ?: '-')); ?></p>
        <p><strong>สถานะการจ่าย:</strong>
            <?php if ($bill_details['is_paid'] == 1): ?>
                <span class="badge bg-success">จ่ายแล้ว</span>
            <?php else: ?>
                <span class="badge bg-danger">ยังไม่จ่าย</span>
            <?php endif; ?>
        </p>
        <p><strong>วันที่บันทึกบิล:</strong> <?php echo date("d/m/Y เวลา H:i น.", strtotime($bill_details['created_at'])); ?></p>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header">
        รายการสินค้า/บริการในบิล
    </div>
    <div class="card-body p-0"> <?php if (!empty($expense_items_in_bill)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover mb-0"> <thead class="table-light">
                        <tr>
                            <th scope="col" class="text-center" style="width:5%;">#</th>
                            <th scope="col">ชื่อสินค้า/บริการ</th>
                            <th scope="col" class="text-center" style="width:15%;">จำนวน</th>
                            <th scope="col" class="text-end" style="width:20%;">ราคา/หน่วย (บาท)</th>
                            <th scope="col" class="text-end" style="width:20%;">รวมย่อย (บาท)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $count = 1; ?>
                        <?php foreach ($expense_items_in_bill as $item): ?>
                        <tr>
                            <td class="text-center"><?php echo $count++; ?></td>
                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars(number_format((float)$item['quantity'], 2, '.', '')); ?></td>
                            <td class="text-end"><?php echo number_format((float)$item['price_per_unit'], 2); ?></td>
                            <td class="text-end fw-bold"><?php echo number_format((float)$item['sub_total'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light total-row">
                            <td colspan="4" class="text-end"><strong>ยอดรวมทั้งสิ้น:</strong></td>
                            <td class="text-end"><strong><?php echo number_format($bill_total_calculated, 2); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php elseif (isset($_GET['bill_id'])): ?>
            <div class="alert alert-info text-center m-3">ไม่พบรายการสินค้าในบิลนี้</div>
        <?php endif; ?>
    </div>
     <div class="card-footer text-center actions">
        <a href="edit_bill_form.php?bill_id=<?php echo htmlspecialchars($bill_details['id']); ?>" class="btn btn-warning">✏️ แก้ไขบิลนี้</a>
        <a href="index.php" class="btn btn-secondary">⬅️ กลับไปหน้ารายการบิล</a>
    </div>
</div>
<?php
require_once 'footer.php'; //
?>