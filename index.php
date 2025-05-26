<?php
$page_title = "รายการบิล";
require_once 'header.php'; // header.php จะจัดการ session_start() และ auth check แล้ว

// ตรวจสอบว่า user_id มีใน session หรือไม่ (header.php ควรจะจัดการ redirect ถ้าไม่มี)
if (!isset($_SESSION['user_id'])) {
    // แม้ว่า header.php ควรจะ redirect ไปแล้ว แต่ใส่ไว้อีกชั้นเพื่อความปลอดภัย
    $_SESSION['message_login'] = "กรุณาเข้าสู่ระบบ";
    $_SESSION['message_type_login'] = "warning";
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];

$filter_period = $_GET['filter_period'] ?? 'today';
$current_date_formatted = date('Y-m-d');
$first_day_of_month = date('Y-m-01');

$filter_specific_date = $_GET['filter_specific_date'] ?? $current_date_formatted;
$filter_start_date = $_GET['filter_start_date'] ?? $first_day_of_month;
$filter_end_date = $_GET['filter_end_date'] ?? $current_date_formatted;

$sql_date_condition = "";
$params_date_filter = [];
$param_types_date_filter = "";
$filter_active_title_segment = "";

switch ($filter_period) {
    case 'specific_date':
        if (strtotime($filter_specific_date) === false) {
            $filter_specific_date = $current_date_formatted;
            // Use global message system from header.php
            $_SESSION['message'] = 'รูปแบบวันที่ที่เลือกไม่ถูกต้อง, แสดงข้อมูลของวันนี้แทน';
            $_SESSION['message_type'] = 'warning';
            $sql_date_condition = "DATE(b.bill_date) = CURDATE()";
            $filter_active_title_segment = "ของวันนี้ (" . date("d/m/Y") . ")";
        } else {
            $sql_date_condition = "DATE(b.bill_date) = ?";
            $params_date_filter[] = $filter_specific_date;
            $param_types_date_filter .= "s";
            $filter_active_title_segment = "ของวันที่ " . date("d/m/Y", strtotime($filter_specific_date));
        }
        break;
    case 'date_range':
        if (strtotime($filter_start_date) === false || strtotime($filter_end_date) === false) {
            $_SESSION['message'] = 'รูปแบบช่วงวันที่ไม่ถูกต้อง, แสดงข้อมูลของวันนี้แทน';
            $_SESSION['message_type'] = 'warning';
            $sql_date_condition = "DATE(b.bill_date) = CURDATE()";
            $filter_period = 'today'; 
            $filter_active_title_segment = "ของวันนี้ (" . date("d/m/Y") . ") (ใช้ค่าเริ่มต้น)";
        } elseif (strtotime($filter_start_date) > strtotime($filter_end_date)) {
            $_SESSION['message'] = 'ข้อผิดพลาดตัวกรอง: วันที่เริ่มต้นต้องมาก่อนหรือตรงกับวันที่สิ้นสุด, แสดงข้อมูลของวันนี้แทน';
            $_SESSION['message_type'] = 'error';
            $sql_date_condition = "DATE(b.bill_date) = CURDATE()";
            $filter_period = 'today'; 
            $filter_active_title_segment = "ของวันนี้ (" . date("d/m/Y") . ") (ใช้ค่าเริ่มต้น)";
        } else {
            $sql_date_condition = "DATE(b.bill_date) BETWEEN ? AND ?";
            $params_date_filter[] = $filter_start_date;
            $params_date_filter[] = $filter_end_date;
            $param_types_date_filter .= "ss";
            $filter_active_title_segment = "ระหว่างวันที่ " . date("d/m/Y", strtotime($filter_start_date)) . " ถึง " . date("d/m/Y", strtotime($filter_end_date));
        }
        break;
    case 'all':
        $sql_date_condition = "1"; // True condition to fetch all for this user
        $filter_active_title_segment = "ทั้งหมด";
        break;
    case 'today':
    default:
        $sql_date_condition = "DATE(b.bill_date) = CURDATE()";
        $filter_period = 'today';
        $filter_active_title_segment = "ของวันนี้ (" . date("d/m/Y") . ")";
        break;
}

$bills_data = [];
$sql_bills_base = "
    SELECT
        b.id AS bill_id, b.bill_date, b.vendor, b.notes AS bill_notes,
        b.is_paid AS bill_is_paid, b.created_at AS bill_created_at,
        COALESCE(SUM(ei.sub_total), 0) AS bill_total_amount,
        COUNT(ei.id) AS bill_item_count
    FROM bills b
    LEFT JOIN expense_items ei ON b.id = ei.bill_id";

// <<<< MODIFIED HERE >>>>
$sql_where_clause = " WHERE b.user_id = ? AND (" . $sql_date_condition . ")"; // Added b.user_id = ?
$sql_group_order = " GROUP BY b.id ORDER BY b.bill_date DESC, b.id DESC";
$sql_bills = $sql_bills_base . $sql_where_clause . $sql_group_order;

$final_params_for_bills = array_merge([$current_user_id], $params_date_filter);
$final_param_types_for_bills = "i" . $param_types_date_filter; // 'i' for user_id

if ($conn) {
    $stmt_bills = $conn->prepare($sql_bills);
    if ($stmt_bills) {
        // Only bind if there are parameters to bind
        if (!empty(trim($final_param_types_for_bills))) { // Check if string is not empty after trim (in case only user_id is there)
             if (count($final_params_for_bills) > 0) { // Ensure there are params
                $stmt_bills->bind_param($final_param_types_for_bills, ...$final_params_for_bills);
            }
        }

        if ($stmt_bills->execute()) {
            $result_bills = $stmt_bills->get_result();
            if ($result_bills) {
                while ($row = $result_bills->fetch_assoc()) {
                    $bills_data[] = $row;
                }
                $result_bills->free();
            }
        } else {
            $_SESSION['message'] = "เกิดข้อผิดพลาดในการ query ข้อมูลบิล: " . htmlspecialchars($stmt_bills->error);
            $_SESSION['message_type'] = 'error';
        }
        $stmt_bills->close();
    } else {
        $_SESSION['message'] = "เกิดข้อผิดพลาดในการเตรียม query ข้อมูลบิล: " . htmlspecialchars($conn->error);
        $_SESSION['message_type'] = 'error';
    }
} else {
    $_SESSION['message'] = "ไม่สามารถเชื่อมต่อฐานข้อมูลเพื่อดึงข้อมูลบิลได้";
    $_SESSION['message_type'] = 'error';
}

$summary_total_unpaid = 0;
$summary_total_current_month = 0;

if ($conn) {
    // Calculate total unpaid FOR THIS USER
    // <<<< MODIFIED HERE >>>>
    $sql_total_unpaid = "SELECT COALESCE(SUM(ei.sub_total), 0) AS total
                         FROM bills b
                         JOIN expense_items ei ON b.id = ei.bill_id
                         WHERE b.is_paid = 0 AND b.user_id = ?"; // Added b.user_id = ?
    $stmt_unpaid = $conn->prepare($sql_total_unpaid);
    if ($stmt_unpaid) {
        $stmt_unpaid->bind_param("i", $current_user_id);
        if ($stmt_unpaid->execute()) {
            $result_unpaid = $stmt_unpaid->get_result();
            if ($result_unpaid && $row_unpaid = $result_unpaid->fetch_assoc()) {
                $summary_total_unpaid = (float)$row_unpaid['total'];
                $result_unpaid->free();
            }
        } else {
             error_log("Error executing unpaid summary query: " . $stmt_unpaid->error); // Log error
        }
        $stmt_unpaid->close();
    } else {
        error_log("Error preparing unpaid summary query: " . $conn->error); // Log error
    }


    // Calculate total for the current month FOR THIS USER
    $current_month_year_db = date('Y-m');
    // <<<< MODIFIED HERE >>>>
    $sql_current_month = "SELECT COALESCE(SUM(ei.sub_total), 0) AS total
                          FROM bills b
                          JOIN expense_items ei ON b.id = ei.bill_id
                          WHERE DATE_FORMAT(b.bill_date, '%Y-%m') = ? AND b.user_id = ?"; // Added b.user_id = ?
    $stmt_current_month = $conn->prepare($sql_current_month);
    if ($stmt_current_month) {
        $stmt_current_month->bind_param("si", $current_month_year_db, $current_user_id);
        if ($stmt_current_month->execute()) {
            $result_current_month = $stmt_current_month->get_result();
            if ($result_current_month && $row_current_month = $result_current_month->fetch_assoc()) {
                $summary_total_current_month = (float)$row_current_month['total'];
                $result_current_month->free();
            }
        } else {
            error_log("Error executing current month summary query: " . $stmt_current_month->error); // Log error
        }
        $stmt_current_month->close();
    } else {
         error_log("Error preparing current month summary query: " . $conn->error); // Log error
    }
}
?>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <h5 class="card-title text-muted">ยอดค้างจ่ายทั้งหมด (ของคุณ)</h5>
                <p class="card-text fs-3 fw-bold text-danger"><?php echo number_format($summary_total_unpaid, 2); ?> บาท</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <h5 class="card-title text-muted">ยอดใช้จ่ายเดือนนี้ (<?php echo date('F Y'); ?>) (ของคุณ)</h5>
                <p class="card-text fs-3 fw-bold text-primary"><?php echo number_format($summary_total_current_month, 2); ?> บาท</p>
            </div>
        </div>
    </div>
</div>

<?php
// Global messages are now handled in header.php
// if (isset($_SESSION['message'])) {
// $alert_type = $_SESSION['message_type'] ?? 'info';
// $alert_class = 'alert-' . htmlspecialchars($alert_type);
// echo "<div class='alert " . $alert_class . " text-center' role='alert'>" . htmlspecialchars($_SESSION['message']) . "</div>";
// unset($_SESSION['message'], $_SESSION['message_type']);
// }
?>

<div class="card card-body bg-light mb-4 shadow-sm">
    <form action="index.php" method="GET" id="billFilterForm">
        <h3 class="mb-3 h5">ตัวกรองรายการบิล</h3>
        <div class="row g-3 align-items-end">
            <div class="col-md col-lg-3">
                <label for="filter_period" class="form-label">แสดงผล:</label>
                <select name="filter_period" id="filter_period" class="form-select form-select-sm">
                    <option value="today" <?php echo ($filter_period == 'today') ? 'selected' : ''; ?>>เฉพาะวันนี้</option>
                    <option value="specific_date" <?php echo ($filter_period == 'specific_date') ? 'selected' : ''; ?>>เลือกวันที่</option>
                    <option value="date_range" <?php echo ($filter_period == 'date_range') ? 'selected' : ''; ?>>เลือกช่วงวันที่</option>
                    <option value="all" <?php echo ($filter_period == 'all') ? 'selected' : ''; ?>>ทั้งหมด</option>
                </select>
            </div>
            <div class="col-md col-lg-3" id="specific_date_option_container" style="display:none;">
                <label for="filter_specific_date" class="form-label">วันที่:</label>
                <input type="date" name="filter_specific_date" id="filter_specific_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_specific_date); ?>">
            </div>
            <div class="col-lg-4" id="date_range_option_container" style="display:none;">
               <div class="row g-2">
                    <div class="col-md">
                        <label for="filter_start_date" class="form-label">ตั้งแต่:</label>
                        <input type="date" name="filter_start_date" id="filter_start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_start_date); ?>">
                    </div>
                    <div class="col-md">
                        <label for="filter_end_date" class="form-label">ถึง:</label>
                        <input type="date" name="filter_end_date" id="filter_end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_end_date); ?>">
                    </div>
                </div>
            </div>
            <div class="col-md-auto col-lg-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">ใช้ตัวกรอง</button>
            </div>
        </div>
    </form>
</div>

<h2 class="h4 mb-3">รายการบิล (<?php echo htmlspecialchars($filter_active_title_segment); ?>)</h2>
<div class="table-responsive table-responsive-cards">
    <table class="table table-striped table-hover table-bordered align-middle">
        <thead class="table-light">
            <tr>
                <th scope="col" class="text-center">ID</th>
                <th scope="col">วันที่บิล</th>
                <th scope="col">ร้านค้า</th>
                <th scope="col">หมายเหตุ</th>
                <th scope="col" class="text-center">รายการ</th>
                <th scope="col" class="text-end">ยอดรวม (บาท)</th>
                <th scope="col" class="text-center">สถานะ</th>
                <th scope="col" class="text-center" style="min-width: 140px;">จัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($bills_data)): ?>
                <?php foreach ($bills_data as $bill): ?>
                    <tr>
                        <td data-label="ID:" class="text-center"><?php echo htmlspecialchars($bill['bill_id']); ?></td>
                        <td data-label="วันที่บิล:"><?php echo date("d/m/Y", strtotime($bill['bill_date'])); ?></td>
                        <td data-label="ร้านค้า:"><?php echo htmlspecialchars($bill['vendor'] ?: '-'); ?></td>
                        <td data-label="หมายเหตุ:">
                            <span class="notes-column-truncate" title="<?php echo htmlspecialchars($bill['bill_notes'] ?? ''); ?>">
                                <?php echo htmlspecialchars(mb_strimwidth($bill['bill_notes'] ?? '', 0, 40, "...")); ?>
                            </span>
                        </td>
                        <td data-label="จำนวนรายการ:" class="text-center"><?php echo htmlspecialchars($bill['bill_item_count']); ?></td>
                        <td data-label="ยอดรวม:" class="text-end fw-bold"><?php echo number_format((float)$bill['bill_total_amount'], 2); ?></td>
                        <td data-label="สถานะ:" class="text-center">
                            <?php if ($bill['bill_is_paid'] == 1): ?>
                                <span class="badge bg-success text-light-emphasis">จ่ายแล้ว</span>
                            <?php else: ?>
                                <span class="badge bg-danger text-light-emphasis">ยังไม่จ่าย</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="จัดการ:" class="action-column">
                             <div class="d-grid gap-1 d-md-flex flex-md-column"> <a href="view_bill_details.php?bill_id=<?php echo $bill['bill_id']; ?>" class="btn btn-info btn-sm">👁️ ดู</a>
                                <?php
                                    $toggle_paid_text = ($bill['bill_is_paid'] == 1) ? "✖️ ยกเลิกจ่าย" : "✔️ จ่ายแล้ว";
                                    $toggle_paid_btn_class = "btn-sm " . (($bill['bill_is_paid'] == 1) ? "btn-outline-secondary" : "btn-warning text-dark");
                                ?>
                                <a href="toggle_bill_paid.php?bill_id=<?php echo $bill['bill_id']; ?>&csrf_token=<?php echo htmlspecialchars($csrf_token); ?>"
                                   class="btn <?php echo $toggle_paid_btn_class; ?>"
                                   onclick="return confirm('คุณต้องการเปลี่ยนสถานะการจ่ายของบิล ID: <?php echo $bill['bill_id']; ?> หรือไม่?');"><?php echo $toggle_paid_text; ?></a>

                                <a href="edit_bill_form.php?bill_id=<?php echo $bill['bill_id']; ?>" class="btn btn-outline-primary btn-sm">✏️ แก้ไข</a>
                                <a href="delete_bill_process.php?bill_id=<?php echo $bill['bill_id']; ?>&csrf_token=<?php echo htmlspecialchars($csrf_token); ?>"
                                   class="btn btn-outline-danger btn-sm"
                                   onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบบิล ID: <?php echo $bill['bill_id']; ?> และรายการสินค้าทั้งหมดในบิลนี้? การกระทำนี้ไม่สามารถย้อนกลับได้');">🗑️ ลบ</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center p-4">ไม่พบรายการบิล<?php echo ($filter_period !== 'all' && isset($filter_active_title_segment) && $filter_active_title_segment !== "ทั้งหมด") ? 'สำหรับเงื่อนไข "' . htmlspecialchars($filter_active_title_segment) . '"' : (($filter_active_title_segment === "ทั้งหมด") ? 'ใดๆ ของคุณ' : ''); ?>. <a href="add_bill_form.php">เพิ่มบิลใหม่?</a></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function toggleDateFilterOptions() {
    const period = document.getElementById('filter_period').value;
    const specificDateOption = document.getElementById('specific_date_option_container');
    const dateRangeOption = document.getElementById('date_range_option_container');

    specificDateOption.style.display = (period === 'specific_date') ? 'block' : 'none';
    dateRangeOption.style.display = (period === 'date_range') ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    toggleDateFilterOptions(); 
    const filterPeriodSelect = document.getElementById('filter_period');
    if (filterPeriodSelect) {
        filterPeriodSelect.addEventListener('change', toggleDateFilterOptions);
    }
});
</script>

<?php
require_once 'footer.php';
?>