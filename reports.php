<?php
$page_title = "รายงานค่าใช้จ่าย (สรุปรายบิล)";
require_once 'header.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message_login'] = "กรุณาเข้าสู่ระบบเพื่อดำเนินการ";
    $_SESSION['message_type_login'] = "warning";
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];

$report_data_bills = [];
$grand_total_bills_amount = 0;
$grand_total_bills_paid = 0;
$grand_total_bills_unpaid = 0;
$grand_total_bills_count = 0;

$report_title_display = "กรุณาเลือกประเภทรายงานและช่วงเวลา";
$errors = [];

$current_date = date('Y-m-d');
$first_day_current_month = date('Y-m-01');
$current_month_year = date('Y-m');

$report_type = $_POST['report_type'] ?? '';
$selected_day = $_POST['selected_day'] ?? $current_date;
$start_date = $_POST['start_date'] ?? $first_day_current_month;
$end_date = $_POST['end_date'] ?? $current_date;
$selected_month = $_POST['selected_month'] ?? $current_month_year;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['generate_report'])) {
    if (!function_exists('verify_csrf_token_for_reports_v4')) {
        function verify_csrf_token_for_reports_v4($token_from_form, &$errors_ref) {
            if (empty($_SESSION['csrf_token'])) {
                $errors_ref[] = "CSRF token ใน session ว่างเปล่า กรุณารีเฟรชหน้าและลองอีกครั้ง";
                return false;
            }
            if (empty($token_from_form)) {
                $errors_ref[] = "ไม่พบ CSRF token จากฟอร์มที่ส่งมา";
                return false;
            }
            if (!hash_equals($_SESSION['csrf_token'], $token_from_form)) {
                $errors_ref[] = "CSRF token ไม่ถูกต้องหรือไม่ตรงกัน กรุณาลองรีเฟรชหน้าและส่งฟอร์มใหม่อีกครั้ง";
                return false;
            }
            return true;
        }
    }

    $submitted_csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token_for_reports_v4($submitted_csrf_token, $errors)) {
    } else {
    }

    $sql_where_conditions = ["b.user_id = ?"];
    $params = [$current_user_id];
    $param_types = "i";

    $base_sql_select_bills = "SELECT
                                b.id as bill_id,
                                b.bill_date,
                                b.vendor,
                                b.notes as bill_notes,
                                b.is_paid,
                                b.created_at as bill_created_at,
                                SUM(ei.sub_total) as bill_total_amount,
                                COUNT(ei.id) as bill_item_count
                            FROM bills b
                            JOIN expense_items ei ON b.id = ei.bill_id";
    
    $group_by_sql = " GROUP BY b.id, b.bill_date, b.vendor, b.notes, b.is_paid, b.created_at";
    $order_by_sql = " ORDER BY b.vendor ASC, b.bill_date ASC, b.id ASC";

    if (empty($errors)) {
        switch ($report_type) {
            case 'daily':
                if (empty($selected_day) || DateTime::createFromFormat('Y-m-d', $selected_day) === false) {
                    $errors[] = "กรุณาเลือกวันที่ที่ถูกต้องสำหรับรายงานรายวัน";
                    $selected_day = $current_date;
                } else {
                    $report_title_display = "รายงานสรุปบิลรายวัน (จัดกลุ่มตามร้านค้า) ประจำวันที่ " . date("d/m/Y", strtotime($selected_day));
                    $sql_where_conditions[] = "DATE(b.bill_date) = ?";
                    $params[] = $selected_day;
                    $param_types .= "s";
                }
                break;
            case 'date_range':
                if (empty($start_date) || empty($end_date) ||
                    DateTime::createFromFormat('Y-m-d', $start_date) === false ||
                    DateTime::createFromFormat('Y-m-d', $end_date) === false) {
                    $errors[] = "กรุณาเลือกวันที่เริ่มต้นและสิ้นสุดที่ถูกต้องสำหรับรายงานตามช่วงวันที่";
                    $start_date = $first_day_current_month;
                    $end_date = $current_date;
                } elseif (strtotime($start_date) > strtotime($end_date)) {
                    $errors[] = "วันที่เริ่มต้นต้องมาก่อนหรือตรงกับวันที่สิ้นสุด";
                } else {
                    $report_title_display = "รายงานสรุปบิลตามช่วงวันที่ (จัดกลุ่มตามร้านค้า) ตั้งแต่ " . date("d/m/Y", strtotime($start_date)) . " ถึง " . date("d/m/Y", strtotime($end_date));
                    $sql_where_conditions[] = "DATE(b.bill_date) BETWEEN ? AND ?";
                    $params[] = $start_date;
                    $params[] = $end_date;
                    $param_types .= "ss";
                }
                break;
            case 'monthly':
                if (empty($selected_month) || !preg_match('/^\d{4}-\d{2}$/', $selected_month)) {
                    $errors[] = "กรุณาเลือกเดือนและปีที่ถูกต้องสำหรับรายงานรายเดือน (YYYY-MM)";
                    $selected_month = $current_month_year;
                } else {
                    list($year, $month) = explode('-', $selected_month);
                    $month_name_thai = ['01'=>'มกราคม','02'=>'กุมภาพันธ์','03'=>'มีนาคม','04'=>'เมษายน','05'=>'พฤษภาคม','06'=>'มิถุนายน','07'=>'กรกฎาคม','08'=>'สิงหาคม','09'=>'กันยายน','10'=>'ตุลาคม','11'=>'พฤศจิกายน','12'=>'ธันวาคม'];
                    $report_title_display = "รายงานสรุปบิลรายเดือน (จัดกลุ่มตามร้านค้า) ประจำเดือน " . ($month_name_thai[$month] ?? 'N/A') . " ปี " . ($year + 543);
                    $sql_where_conditions[] = "YEAR(b.bill_date) = ?";
                    $sql_where_conditions[] = "MONTH(b.bill_date) = ?";
                    $params[] = (int)$year;
                    $params[] = (int)$month;
                    $param_types .= "ii";
                }
                break;
            default:
                if (!empty($report_type)){
                    $errors[] = "กรุณาเลือกประเภทรายงานที่ถูกต้อง";
                } else if (empty($report_type) && isset($_POST['generate_report'])){
                     $errors[] = "กรุณาเลือกประเภทรายงาน";
                }
        }
    }

    if (empty($errors) && !empty($report_type) && count($sql_where_conditions) > 0 && $conn) {
        $sql_final_where = implode(" AND ", $sql_where_conditions);
        $sql = $base_sql_select_bills . " WHERE " . $sql_final_where . $group_by_sql . $order_by_sql;

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if (!empty($param_types) && count($params) > 0) {
                $stmt->bind_param($param_types, ...$params);
            }
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $report_data_bills[] = $row;
                        $bill_total = (float)$row['bill_total_amount'];
                        $grand_total_bills_amount += $bill_total;
                        if ($row['is_paid'] == 1) {
                            $grand_total_bills_paid += $bill_total;
                        } else {
                            $grand_total_bills_unpaid += $bill_total;
                        }
                    }
                    $grand_total_bills_count = count($report_data_bills);
                     if (empty($errors)) { // Only unset if primary operation was successful and no prior errors
                        unset($_SESSION['csrf_token']); // Consume the token after successful use in this request
                    }
                } else {
                    $report_title_display .= " (ไม่พบข้อมูลบิลสำหรับคุณ)";
                }
            } else {
                $errors[] = "เกิดข้อผิดพลาดในการ query ข้อมูลรายงาน: " . htmlspecialchars($stmt->error);
            }
            $stmt->close();
        } else {
            $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . htmlspecialchars($conn->error);
        }
    } elseif (isset($_POST['generate_report']) && empty($report_type) && empty($errors)) {
        $errors[] = "กรุณาเลือกประเภทรายงาน";
    }  elseif (!$conn && empty($errors)){
        $errors[] = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้";
    }
}
$csrf_token = generate_csrf_token();

?>
<style>
    .report-form { margin-bottom: 30px; padding: 20px; border: 1px solid #eee; border-radius: 0.375rem; background-color: #f9f9f9;}
    .report-form .form-group { margin-bottom: 1rem; }
    .report-summary { margin-top: 20px; padding: 15px; background-color: #e9f5ff; border: 1px solid #b3d7f0; border-radius: 5px; }
    .report-summary h3 { margin-top: 0; margin-bottom:15px; text-align:left; font-size: 1.2em;}
    .report-summary p { margin: 8px 0; font-size: 1.1em; }
    .date-options { margin-top: 10px; border: 1px dashed #ccc; padding: 10px; border-radius: 4px; background-color: #fff; }
    .date-options div { margin-bottom: 10px;}
    .hidden { display: none !important; }
    .number-cell { text-align: right; }
    .vendor-group-header td {
        background-color: #cfe2ff !important;
        font-weight: bolder;
        padding-top: 0.75rem !important;
        padding-bottom: 0.75rem !important;
    }
    .vendor-subtotal-row td {
        background-color: #e9ecef !important;
        font-weight: bold;
    }
    .notes-column-report {
        display: inline-block;
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        vertical-align: middle;
    }
    .notes-column-report:hover {
        max-width: 400px;
        white-space: normal;
        overflow: visible;
        position: absolute;
        background-color: white;
        border: 1px solid #ccc;
        padding: 5px;
        z-index: 10;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        border-radius: 0.25rem;
    }
</style>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger text-center"> 
        <h4 class="alert-heading small mb-2">เกิดข้อผิดพลาดในการสร้างรายงาน:</h4>
        <?php foreach ($errors as $error): ?>
            <p class="mb-1 small"><?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm report-form">
    <div class="card-body">
        <h3 class="card-title h5 mb-3">เลือกเงื่อนไขรายงาน (สำหรับข้อมูลของคุณ)</h3>
        <form action="reports.php" method="POST" id="reportFilterForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div class="form-group mb-3">
                <label for="report_type" class="form-label">ประเภทรายงาน:</label>
                <select name="report_type" id="report_type" class="form-select form-select-sm" onchange="toggleDateOptions()" required>
                    <option value="">-- กรุณาเลือก --</option>
                    <option value="daily" <?php echo ($report_type == 'daily') ? 'selected' : ''; ?>>รายวัน</option>
                    <option value="date_range" <?php echo ($report_type == 'date_range') ? 'selected' : ''; ?>>ตามช่วงวันที่</option>
                    <option value="monthly" <?php echo ($report_type == 'monthly') ? 'selected' : ''; ?>>รายเดือน</option>
                </select>
            </div>
            <div id="daily_options" class="date-options <?php echo ($report_type === 'daily') ? '' : 'hidden'; ?>">
                <label for="selected_day" class="form-label">เลือกวันที่:</label>
                <input type="date" name="selected_day" id="selected_day" class="form-control form-control-sm" value="<?php echo htmlspecialchars($selected_day); ?>">
            </div>
            <div id="range_options" class="date-options <?php echo ($report_type === 'date_range') ? '' : 'hidden'; ?>">
                <div class="mb-2">
                    <label for="start_date" class="form-label">วันที่เริ่มต้น:</label>
                    <input type="date" name="start_date" id="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div>
                    <label for="end_date" class="form-label">วันที่สิ้นสุด:</label>
                    <input type="date" name="end_date" id="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
            </div>
            <div id="monthly_options" class="date-options <?php echo ($report_type === 'monthly') ? '' : 'hidden'; ?>">
                <label for="selected_month" class="form-label">เลือกเดือนและปี (YYYY-MM):</label>
                <input type="month" name="selected_month" id="selected_month" class="form-control form-control-sm" value="<?php echo htmlspecialchars($selected_month); ?>">
            </div>
            <div class="form-group mt-3">
                <button type="submit" name="generate_report" class="btn btn-primary btn-sm">สร้างรายงาน</button>
                <a href="index.php" class="btn btn-secondary btn-sm ms-2">กลับหน้าหลัก</a>
            </div>
        </form>
    </div>
</div>

<hr>
<h2 class="h4 mt-4 mb-3"><?php echo htmlspecialchars($report_title_display); ?></h2>

<?php if (!empty($report_data_bills)): ?>
    <div class="table-responsive table-responsive-cards mb-3">
        <table class="table table-bordered table-striped table-hover table-sm">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="text-center" style="width:5%;">#</th>
                    <th scope="col" class="text-center">ID บิล</th>
                    <th scope="col">วันที่บิล</th>
                    <th scope="col">ร้านค้า/ผู้ขาย</th>
                    <th scope="col">หมายเหตุบิล</th>
                    <th scope="col" class="text-center">จำนวนรายการ</th>
                    <th scope="col" class="number-cell">ยอดรวมบิล (บาท)</th>
                    <th scope="col" class="text-center">สถานะบิล</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $count_bill_row = 1;
                $current_vendor_group = null;
                $vendor_bill_subtotal = 0;
                $vendor_bill_count = 0;
                ?>
                <?php foreach ($report_data_bills as $bill): ?>
                    <?php
                    $vendor_display_name_bill = htmlspecialchars($bill['vendor'] ?: 'ไม่ระบุร้านค้า');
                    if ($bill['vendor'] !== $current_vendor_group) {
                        if ($current_vendor_group !== null) {
                            echo '<tr class="vendor-subtotal-row">';
                            echo '<td colspan="6" class="text-end">ยอดรวมสำหรับร้านค้า "' . htmlspecialchars($current_vendor_group ?: 'ไม่ระบุร้านค้า') . '" (' . $vendor_bill_count . ' บิล):</td>';
                            echo '<td class="number-cell">' . number_format($vendor_bill_subtotal, 2) . '</td>';
                            echo '<td></td>'; 
                            echo '</tr>';
                        }
                        $current_vendor_group = $bill['vendor'];
                        $vendor_bill_subtotal = 0; 
                        $vendor_bill_count = 0;
                        echo '<tr class="vendor-group-header">';
                        echo '<td colspan="8">';
                        echo 'ร้านค้า: ' . $vendor_display_name_bill;
                        echo '</td>';
                        echo '</tr>';
                    }
                    
                    $vendor_bill_subtotal += (float)$bill['bill_total_amount'];
                    $vendor_bill_count++;
                    ?>
                    <tr>
                        <td data-label="#" class="text-center"><?php echo $count_bill_row++; ?></td>
                        <td data-label="ID บิล:" class="text-center">
                            <a href="view_bill_details.php?bill_id=<?php echo htmlspecialchars($bill['bill_id']); ?>"><?php echo htmlspecialchars($bill['bill_id']); ?></a>
                        </td>
                        <td data-label="วันที่บิล:"><?php echo date("d/m/Y", strtotime($bill['bill_date'])); ?></td>
                        <td data-label="ร้านค้า:"><?php echo $vendor_display_name_bill; ?></td>
                        <td data-label="หมายเหตุบิล:">
                             <span class="notes-column-report" title="<?php echo htmlspecialchars($bill['bill_notes'] ?? ''); ?>">
                                <?php echo htmlspecialchars(mb_strimwidth($bill['bill_notes'] ?? '', 0, 30, "...")); ?>
                            </span>
                        </td>
                        <td data-label="จำนวนรายการ:" class="text-center"><?php echo htmlspecialchars($bill['bill_item_count']); ?></td>
                        <td data-label="ยอดรวมบิล:" class="number-cell fw-bold"><?php echo number_format((float)$bill['bill_total_amount'], 2); ?></td>
                        <td data-label="สถานะบิล:" class="text-center"><?php echo ($bill['is_paid'] == 1) ? "<span class='badge bg-success text-light-emphasis'>จ่ายแล้ว</span>" : "<span class='badge bg-danger text-light-emphasis'>ยังไม่จ่าย</span>"; ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php
                if ($current_vendor_group !== null) {
                    echo '<tr class="vendor-subtotal-row">';
                     echo '<td colspan="6" class="text-end">ยอดรวมสำหรับร้านค้า "' . htmlspecialchars($current_vendor_group ?: 'ไม่ระบุร้านค้า') . '" (' . $vendor_bill_count . ' บิล):</td>';
                    echo '<td class="number-cell">' . number_format($vendor_bill_subtotal, 2) . '</td>';
                    echo '<td></td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
            <tfoot>
                <tr class="table-light">
                    <th colspan="6" style="text-align: right;">ยอดรวมค่าใช้จ่ายทั้งสิ้น (จากบิลที่แสดง):</th>
                    <th class="number-cell"><?php echo number_format($grand_total_bills_amount, 2); ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="report-summary card shadow-sm">
        <div class="card-body">
            <h3 class="card-title h5">สรุปภาพรวมรายงาน (สำหรับข้อมูลของคุณ)</h3>
            <p><strong>จำนวนบิลทั้งหมดที่แสดง:</strong> <?php echo $grand_total_bills_count; ?> บิล</p>
            <hr style="border:0; border-top: 1px dashed #ccc; margin: 10px 0;">
            <p><strong>ยอดรวม (จ่ายแล้ว):</strong> <span class="text-success fw-bold"><?php echo number_format($grand_total_bills_paid, 2); ?></span> บาท</p>
            <p><strong>ยอดรวม (ยังไม่จ่าย):</strong> <span class="text-danger fw-bold"><?php echo number_format($grand_total_bills_unpaid, 2); ?></span> บาท</p>
            <hr style="border:0; border-top: 1px dashed #ccc; margin: 10px 0;">
            <p><strong>ยอดรวมค่าใช้จ่ายทั้งหมด (จากบิลที่แสดง):</strong> <span class="fw-bold"><?php echo number_format($grand_total_bills_amount, 2); ?></span> บาท</p>
        </div>
    </div>
<?php elseif ($_SERVER["REQUEST_METHOD"] == "POST" && empty($errors) && isset($_POST['generate_report']) && !empty($report_type)): ?>
    <div class="alert alert-info text-center">ไม่พบข้อมูลบิลสำหรับเงื่อนไขที่คุณเลือก</div>
<?php endif; ?>

<script>
    function toggleDateOptions() {
        const reportType = document.getElementById('report_type').value;
        const dailyOptions = document.getElementById('daily_options');
        const rangeOptions = document.getElementById('range_options');
        const monthlyOptions = document.getElementById('monthly_options');

        if(dailyOptions) dailyOptions.classList.add('hidden');
        if(rangeOptions) rangeOptions.classList.add('hidden');
        if(monthlyOptions) monthlyOptions.classList.add('hidden');

        if (reportType === 'daily' && dailyOptions) {
            dailyOptions.classList.remove('hidden');
        } else if (reportType === 'date_range' && rangeOptions) {
            rangeOptions.classList.remove('hidden');
        } else if (reportType === 'monthly' && monthlyOptions) {
            monthlyOptions.classList.remove('hidden');
        }
    }
    document.addEventListener('DOMContentLoaded', toggleDateOptions);
</script>
<?php
require_once 'footer.php';
?>