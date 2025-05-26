<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'use_strict_mode' => true,
        'gc_maxlifetime' => 1800
    ]);
}
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message_login'] = "กรุณาเข้าสู่ระบบเพื่อดำเนินการ";
    $_SESSION['message_type_login'] = "error";
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];

if (!function_exists('verify_csrf_token_update')) {
    function verify_csrf_token_update($token_from_form, $bill_id_for_redirect) {
        $redirect_path = $bill_id_for_redirect ? "edit_bill_form.php?bill_id=" . $bill_id_for_redirect : "index.php";
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token_from_form)) {
            $_SESSION['message'] = "CSRF token ไม่ถูกต้องหรือหมดอายุการใช้งาน กรุณาลองอีกครั้ง";
            $_SESSION['message_type'] = "error";
            if ($bill_id_for_redirect) {
                 $_SESSION['old_form_data_edit'][$bill_id_for_redirect] = $_POST;
            }
            if (isset($_SESSION['csrf_token'])) unset($_SESSION['csrf_token']);
            header("Location: " . $redirect_path);
            exit();
        }
        if (isset($_SESSION['csrf_token'])) unset($_SESSION['csrf_token']);
        return true;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $bill_id = isset($_POST['bill_id']) ? (int)$_POST['bill_id'] : null;
    $submitted_csrf_token = $_POST['csrf_token'] ?? '';
    verify_csrf_token_update($submitted_csrf_token, $bill_id);

    $bill_date = $_POST['bill_date'] ?? '';
    $selected_vendor_from_dropdown = $_POST['vendor'] ?? ''; // This is `name="vendor"` from select
    $new_vendor_typed = isset($_POST['vendor_new']) && trim($_POST['vendor_new']) !== '' ? trim($_POST['vendor_new']) : null;
    $vendor_to_save = $new_vendor_typed ?? $selected_vendor_from_dropdown;
     if (empty(trim($vendor_to_save ?? ''))) {
        $vendor_to_save = null;
    }

    $notes = isset($_POST['notes']) && trim($_POST['notes']) !== '' ? trim($_POST['notes']) : null;
    $is_paid = isset($_POST['is_paid']) ? (int)$_POST['is_paid'] : 0;
    $items_posted = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];

    $errors = [];

    if (empty($bill_id)) {
        $errors[] = "ไม่พบ ID ของบิลที่ต้องการอัปเดต";
    }
    if (empty($bill_date)) {
        $errors[] = "กรุณาระบุวันที่ในบิล";
    } elseif (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$bill_date)) {
        $errors[] = "รูปแบบวันที่ในบิลไม่ถูกต้อง (YYYY-MM-DD)";
    }
    if (!in_array($is_paid, [0, 1])) {
        $errors[] = "สถานะการจ่ายเงินไม่ถูกต้อง";
    }
    if (empty($items_posted)) {
        $errors[] = "กรุณาเพิ่มรายการสินค้าอย่างน้อย 1 รายการ";
    }

    $valid_items_for_update_db = [];
    $items_for_session_repopulation_edit = [];

    if (!empty($items_posted)) {
        foreach ($items_posted as $key => $item) {
            $item_name_from_select = trim($item['name_select'] ?? '');
            $item_name_from_new_input = trim($item['name_new'] ?? '');
            $item_name_to_process = $item_name_from_new_input ?: $item_name_from_select;

            $quantity_str = trim($item['quantity'] ?? '');
            $price_str = trim($item['price'] ?? '');
            $db_item_id = isset($item['db_item_id']) && is_numeric($item['db_item_id']) ? (int)$item['db_item_id'] : null; // For existing items

            $items_for_session_repopulation_edit[] = [
                'db_item_id' => $db_item_id, // Keep for repopulation
                'name' => $item_name_to_process,
                'quantity' => $quantity_str,
                'price' => $price_str
            ];

            $current_item_errors = [];
            if (empty($item_name_to_process)) {
                $current_item_errors[] = "ชื่อสินค้าสำหรับรายการที่ " . ($key + 1);
            }
            if ($quantity_str === '' || !is_numeric($quantity_str) || floatval($quantity_str) <= 0) {
                $current_item_errors[] = "จำนวนไม่ถูกต้องสำหรับ '" . htmlspecialchars($item_name_to_process ?: 'N/A') . "'";
            }
            if ($price_str === '' || !is_numeric($price_str) || floatval($price_str) < 0) {
                $current_item_errors[] = "ราคาต่อหน่วยไม่ถูกต้องสำหรับ '" . htmlspecialchars($item_name_to_process ?: 'N/A') . "'";
            }

            if (!empty($current_item_errors)) {
                 $errors[] = "ข้อมูลไม่ถูกต้องสำหรับรายการสินค้า: " . implode(", ", $current_item_errors);
            } else {
                $valid_items_for_update_db[] = [
                    'db_item_id' => $db_item_id, 
                    'name' => $item_name_to_process,
                    'quantity' => floatval($quantity_str),
                    'price' => floatval($price_str)
                ];
            }
        }
    }

    if (!empty($errors)) {
        $_SESSION['message'] = "ข้อมูลไม่ถูกต้อง:<br>" . implode("<br>", $errors);
        $_SESSION['message_type'] = "error";
        if ($bill_id) {
            $old_data_for_session = $_POST; // Base with all POST data
            $old_data_for_session['items'] = $items_for_session_repopulation_edit; // Override items with processed structure
            $old_data_for_session['vendor'] = $new_vendor_typed ? $new_vendor_typed : $selected_vendor_from_dropdown; // Ensure correct vendor value is passed back

            $_SESSION['old_form_data_edit'][$bill_id] = $old_data_for_session;
            header("Location: edit_bill_form.php?bill_id=" . $bill_id);
        } else {
            header("Location: index.php");
        }
        exit();
    }

    $conn->begin_transaction();

    try {
        $sql_update_bill = "UPDATE bills SET bill_date = ?, vendor = ?, notes = ?, is_paid = ?
                            WHERE id = ? AND user_id = ?";
        $stmt_update_bill = $conn->prepare($sql_update_bill);
        if (!$stmt_update_bill) {
            throw new Exception("เกิดข้อผิดพลาดในการเตรียม SQL อัปเดตบิล: " . $conn->error);
        }
        $stmt_update_bill->bind_param("sssiii", $bill_date, $vendor_to_save, $notes, $is_paid, $bill_id, $current_user_id);
        
        if (!$stmt_update_bill->execute()) {
            throw new Exception("เกิดข้อผิดพลาดในการอัปเดตข้อมูลบิล: " . $stmt_update_bill->error);
        }
        if ($stmt_update_bill->affected_rows === 0 && $bill_id) {
            // Check if bill exists and belongs to user, if not, it's an issue. If it exists but no change, it's not an error.
             $check_bill_exists_stmt = $conn->prepare("SELECT id FROM bills WHERE id = ? AND user_id = ?");
             if ($check_bill_exists_stmt) {
                $check_bill_exists_stmt->bind_param("ii", $bill_id, $current_user_id);
                $check_bill_exists_stmt->execute();
                $check_bill_exists_result = $check_bill_exists_stmt->get_result();
                $check_bill_exists_stmt->close();
                if($check_bill_exists_result->num_rows === 0){
                     throw new Exception("ไม่พบบิล ID: " . $bill_id . " หรือคุณไม่มีสิทธิ์อัปเดต");
                }
                // If it exists, it means no actual data changed for the bill itself. Not an error.
             }
        }
        $stmt_update_bill->close();

        // For simplicity, delete all existing items and re-insert.
        // A more advanced approach would be to update existing, delete removed, add new.
        $sql_delete_items = "DELETE FROM expense_items WHERE bill_id = ?";
        $stmt_delete_items = $conn->prepare($sql_delete_items);
        if (!$stmt_delete_items) {
            throw new Exception("เกิดข้อผิดพลาดในการเตรียม SQL ลบรายการสินค้าเดิม: " . $conn->error);
        }
        // We've already confirmed bill_id belongs to user when updating the bill itself.
        // No need to check user_id again for deleting items of that bill_id.
        $stmt_delete_items->bind_param("i", $bill_id); 
        if (!$stmt_delete_items->execute()) {
            throw new Exception("เกิดข้อผิดพลาดในการลบรายการสินค้าเดิม: " . $stmt_delete_items->error);
        }
        $stmt_delete_items->close();

        $sql_insert_item = "INSERT INTO expense_items (bill_id, item_name, quantity, price_per_unit, sub_total)
                            VALUES (?, ?, ?, ?, ?)";
        $stmt_insert_item = $conn->prepare($sql_insert_item);
        if (!$stmt_insert_item) {
            throw new Exception("เกิดข้อผิดพลาดในการเตรียม SQL เพิ่มรายการสินค้าใหม่: " . $conn->error);
        }

        foreach ($valid_items_for_update_db as $item) {
            $item_name_to_save_db = $item['name'];
            $quantity_to_save = $item['quantity'];
            $price_per_unit_to_save = $item['price'];
            $sub_total = $quantity_to_save * $price_per_unit_to_save;
            
            $stmt_insert_item->bind_param("isidd", $bill_id, $item_name_to_save_db, $quantity_to_save, $price_per_unit_to_save, $sub_total);
            if (!$stmt_insert_item->execute()) {
                throw new Exception("เกิดข้อผิดพลาดในการบันทึกรายการสินค้าใหม่: " . $stmt_insert_item->error . " (สำหรับสินค้า: " . htmlspecialchars($item_name_to_save_db) . ")");
            }
        }
        $stmt_insert_item->close();
        
        $conn->commit();
        $_SESSION['message'] = "อัปเดตบิล ID: " . $bill_id . " เรียบร้อยแล้ว!";
        $_SESSION['message_type'] = "success";
        if (isset($_SESSION['old_form_data_edit'][$bill_id])) {
            unset($_SESSION['old_form_data_edit'][$bill_id]);
        }
        header("Location: view_bill_details.php?bill_id=" . $bill_id);
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = "เกิดข้อผิดพลาดในการอัปเดต: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
        if ($bill_id) {
            $old_data_for_session = $_POST;
            $old_data_for_session['items'] = $items_for_session_repopulation_edit;
            $old_data_for_session['vendor'] = $new_vendor_typed ? $new_vendor_typed : $selected_vendor_from_dropdown;

            $_SESSION['old_form_data_edit'][$bill_id] = $old_data_for_session;
            header("Location: edit_bill_form.php?bill_id=" . $bill_id);
        } else {
            header("Location: index.php");
        }
        exit();
    }

} else {
    $_SESSION['message'] = "Invalid request method.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php"); // Or redirect to specific edit page if bill_id is known
    exit();
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>