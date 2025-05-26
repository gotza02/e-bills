<?php
// ini_set('display_errors', 1); // ควรปิดใน production
// ini_set('display_startup_errors', 1); // ควรปิดใน production
// error_reporting(E_ALL); // ควรปิดใน production และใช้ error logging แทน

if (session_status() == PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'use_strict_mode' => true,
        'gc_maxlifetime' => 1800
    ]);
}
require_once 'db_connect.php'; //

$errors = [];

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message_login'] = "กรุณาเข้าสู่ระบบเพื่อดำเนินการ (Process Error)";
    $_SESSION['message_type_login'] = "error";
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];

if (!$conn) {
    $errors[] = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้ (จาก add_bill_process.php). กรุณาตรวจสอบไฟล์ db_connect.php";
    $_SESSION['message'] = "ข้อมูลไม่ถูกต้อง:<br>" . implode("<br>", $errors);
    $_SESSION['message_type'] = "danger";
    $_SESSION['old_form_data'] = $_POST;
    header("Location: add_bill_form.php"); //
    exit();
}

if (!function_exists('verify_csrf_token_add_bill_v3')) {
    function verify_csrf_token_add_bill_v3($token_from_form, &$errors_ref) {
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token_from_form)) {
            $errors_ref[] = "CSRF token ไม่ถูกต้องหรือหมดอายุการใช้งาน กรุณาลองอีกครั้ง";
            return false;
        }
        unset($_SESSION['csrf_token']);
        return true;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $submitted_csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token_add_bill_v3($submitted_csrf_token, $errors)) {
        // Error is added to $errors by the function
    }

    $bill_date = $_POST['bill_date'] ?? '';
    $vendor_id_from_form = isset($_POST['vendor_id']) && is_numeric($_POST['vendor_id']) && $_POST['vendor_id'] > 0 ? (int)$_POST['vendor_id'] : null;
    $notes = isset($_POST['notes']) && trim($_POST['notes']) !== '' ? trim($_POST['notes']) : null;
    $items_posted = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];

    if (empty($bill_date)) {
        $errors[] = "กรุณาระบุวันที่ในบิล";
    } elseif (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $bill_date)) {
        $errors[] = "รูปแบบวันที่ในบิลไม่ถูกต้อง (YYYY-MM-DD)";
    }

    if ($vendor_id_from_form === null) {
        $errors[] = "กรุณาเลือกผู้ขาย/ร้านค้า";
    }

    if (empty($items_posted)) {
        $errors[] = "กรุณาเพิ่มรายการสินค้าอย่างน้อย 1 รายการ";
    }

    $valid_items_to_save = [];
    $items_for_session_repopulation = [];

    if (!empty($items_posted)) {
        foreach ($items_posted as $key_item_form => $item) {
            $product_id_from_select = isset($item['product_id']) && is_numeric($item['product_id']) && $item['product_id'] > 0 ? (int)$item['product_id'] : null;
            $quantity_str = trim($item['quantity'] ?? '');
            $price_str = trim($item['price'] ?? '');

            $items_for_session_repopulation[] = [
                'product_id' => $product_id_from_select,
                'quantity' => $quantity_str,
                'price' => $price_str
            ];

            $current_item_errors = [];
            if ($product_id_from_select === null) {
                $current_item_errors[] = "กรุณาเลือกสินค้า";
            }
            if ($quantity_str === '' || !is_numeric($quantity_str) || floatval($quantity_str) <= 0) {
                $current_item_errors[] = "จำนวนไม่ถูกต้อง (ต้องเป็นตัวเลข > 0)";
            }
            if ($price_str === '' || !is_numeric($price_str) || floatval($price_str) < 0) {
                $current_item_errors[] = "ราคาต่อหน่วยไม่ถูกต้อง (ต้องเป็นตัวเลข >= 0)";
            }

            if (!empty($current_item_errors)) {
                $errors[] = "รายการสินค้าที่ " . ($key_item_form + 1) . ": " . implode(", ", $current_item_errors);
            } else {
                $valid_items_to_save[] = [
                    'product_id' => $product_id_from_select,
                    'quantity' => floatval($quantity_str),
                    'price' => floatval($price_str)
                ];
            }
        }
    }
    
    if (empty($errors)) { // Only proceed if initial validations passed
        $transaction_active = false;
        try {
            $conn->begin_transaction();
            $transaction_active = true;
            $new_bill_id = null;

            // Assuming bills table has 'vendor_id' (INT) column.
            $sql_bill = "INSERT INTO bills (user_id, bill_date, vendor_id, notes, is_paid) VALUES (?, ?, ?, ?, 0)";
            $stmt_bill = $conn->prepare($sql_bill);
            if (!$stmt_bill) {
                throw new Exception("เกิดข้อผิดพลาดในการเตรียม SQL สำหรับบิล (prepare failed): " . $conn->error);
            }
            $stmt_bill->bind_param("isis", $current_user_id, $bill_date, $vendor_id_from_form, $notes);
            
            if (!$stmt_bill->execute()) {
                throw new Exception("เกิดข้อผิดพลาดในการบันทึกข้อมูลบิล (execute failed): " . $stmt_bill->error . " (Code: " . $stmt_bill->errno . ")");
            }
            $new_bill_id = $conn->insert_id;
            if ($new_bill_id == 0) {
                throw new Exception("ไม่สามารถดึง ID ของบิลที่สร้างใหม่ได้หลังจากการ INSERT");
            }
            $stmt_bill->close();

            // SQL for expense_items now includes item_name
            $sql_item = "INSERT INTO expense_items (bill_id, product_id, item_name, quantity, price_per_unit, sub_total) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_item = $conn->prepare($sql_item);
            if (!$stmt_item) {
                throw new Exception("เกิดข้อผิดพลาดในการเตรียม SQL สำหรับรายการสินค้า (prepare failed): " . $conn->error);
            }

            foreach ($valid_items_to_save as $item_data) {
                $product_id_to_save_db = $item_data['product_id'];
                $product_name_for_item = ""; // Initialize

                // Fetch product name using product_id
                if ($product_id_to_save_db !== null) {
                    $sql_get_product_name = "SELECT name FROM products WHERE id = ? AND user_id = ?";
                    $stmt_get_product_name = $conn->prepare($sql_get_product_name);
                    if (!$stmt_get_product_name) {
                        throw new Exception("การเตรียม SQL ดึงชื่อสินค้าล้มเหลว (product_id: {$product_id_to_save_db}): " . $conn->error);
                    }
                    $stmt_get_product_name->bind_param("ii", $product_id_to_save_db, $current_user_id);
                    if (!$stmt_get_product_name->execute()) {
                        throw new Exception("การดึงชื่อสินค้าล้มเหลว (execute for product_id: {$product_id_to_save_db}): " . $stmt_get_product_name->error);
                    }
                    $result_product_name = $stmt_get_product_name->get_result();
                    if ($row_product_name = $result_product_name->fetch_assoc()) {
                        $product_name_for_item = $row_product_name['name'];
                    } else {
                        // This case should ideally be prevented by form validation or if product was deleted between form load and submit
                        throw new Exception("ไม่พบชื่อสินค้าสำหรับ Product ID: {$product_id_to_save_db} ของคุณ");
                    }
                    $stmt_get_product_name->close();
                } else {
                     // This should not happen if validation for product_id_from_select was correct
                    throw new Exception("Product ID เป็นค่าว่าง ไม่สามารถดึงชื่อสินค้าได้");
                }

                if (empty(trim($product_name_for_item))) {
                    throw new Exception("ชื่อสินค้าที่ดึงมาสำหรับ Product ID: {$product_id_to_save_db} เป็นค่าว่าง");
                }

                $quantity_to_save = $item_data['quantity'];
                $price_per_unit_to_save = $item_data['price'];
                $sub_total = round($quantity_to_save * $price_per_unit_to_save, 2);

                // bill_id (i), product_id (i), item_name (s), quantity (d), price_per_unit (d), sub_total (d)
                $stmt_item->bind_param("iisddd", $new_bill_id, $product_id_to_save_db, $product_name_for_item, $quantity_to_save, $price_per_unit_to_save, $sub_total);
                if (!$stmt_item->execute()) {
                    throw new Exception("เกิดข้อผิดพลาดในการบันทึกรายการสินค้า (execute failed for product_id: {$product_id_to_save_db}, item_name: {$product_name_for_item}): " . $stmt_item->error . " (Code: " . $stmt_item->errno . ")");
                }
            }
            $stmt_item->close();
            
            $conn->commit();
            $transaction_active = false;

            $_SESSION['message'] = "บันทึกบิล ID: " . $new_bill_id . " เรียบร้อยแล้ว!";
            $_SESSION['message_type'] = "success";
            if (isset($_SESSION['old_form_data'])) {
                unset($_SESSION['old_form_data']);
            }
            header("Location: view_bill_details.php?bill_id=" . $new_bill_id); //
            exit();

        } catch (Exception $e) {
            if ($transaction_active) {
                $conn->rollback();
            }
            error_log("Add Bill Process Exception: " . $e->getMessage() . " - Bill Data: bill_date=" . $bill_date . ", vendor_id=" . $vendor_id_from_form);
            // Add the specific exception message to the $errors array to be displayed
            $errors[] = "เกิดข้อผิดพลาดภายในระบบขณะบันทึกข้อมูล: " . htmlspecialchars($e->getMessage());
            // Fall through to the general error handling below
        }
    }
    
    // General error handling (if $errors array is populated from validation or catch block)
    if (!empty($errors)) {
        $_SESSION['message'] = "<strong>การบันทึกข้อมูลบิลไม่สำเร็จ:</strong><br>" . implode("<br>", array_map('htmlspecialchars', $errors));
        $_SESSION['message_type'] = "danger";
        
        $_SESSION['old_form_data'] = [
            'bill_date' => $bill_date,
            'vendor_id' => $vendor_id_from_form,
            'notes' => $notes,
            'items' => $items_for_session_repopulation
        ];
        header("Location: add_bill_form.php"); //
        exit();
    }

} else {
    $_SESSION['message'] = "Invalid request method for add_bill_process.";
    $_SESSION['message_type'] = "warning";
    header("Location: add_bill_form.php"); //
    exit();
}

if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
    $conn->close();
}
?>