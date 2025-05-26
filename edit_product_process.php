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

$form_page_redirect_base = 'edit_product_form.php'; // Base for redirecting back to edit form

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $form_page_redirect = $form_page_redirect_base . ($product_id ? "?id=" . $product_id : "");

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $_SESSION['message_product_edit'] = "CSRF token ไม่ถูกต้องหรือหมดอายุการใช้งาน";
        $_SESSION['message_type_product_edit'] = "error";
        header("Location: " . $form_page_redirect);
        exit();
    }
    unset($_SESSION['csrf_token']); // Consume token

    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '') ?: null;
    
    $default_price_str = trim($_POST['default_price'] ?? '');
    $default_price = null; // Default to null
    $errors = [];

    if ($default_price_str !== '') { // Only validate if not empty
        if (is_numeric($default_price_str) && floatval($default_price_str) >= 0) {
            $default_price = floatval($default_price_str);
        } else {
            $errors[] = "ราคาตั้งต้นไม่ถูกต้อง (ต้องเป็นตัวเลข >= 0 หรือเว้นว่างไว้)";
        }
    }
    
    $notes = trim($_POST['notes'] ?? '') ?: null;

    if (empty($product_id)) {
        $errors[] = "ID สินค้าไม่ถูกต้อง";
    }
    if (empty($name)) {
        $errors[] = "กรุณากรอกชื่อสินค้า";
    }
    // Add more validation for name, category, notes if needed (e.g., length)

    if (!empty($errors)) {
        $_SESSION['message_product_edit'] = implode("<br>", $errors);
        $_SESSION['message_type_product_edit'] = "error";
        if ($product_id) {
             $_SESSION['old_product_form_data_edit'][$product_id] = $_POST;
        }
        header("Location: " . $form_page_redirect);
        exit();
    }

    if ($conn) {
        // Check for duplicate product name for this user (excluding the current product being edited)
        $sql_check = "SELECT id FROM products WHERE user_id = ? AND name = ? AND id != ?";
        $stmt_check = $conn->prepare($sql_check);
        if ($stmt_check) {
            $stmt_check->bind_param("isi", $current_user_id, $name, $product_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows > 0) {
                $_SESSION['message_product_edit'] = "ชื่อสินค้า '" . htmlspecialchars($name) . "' นี้มีอยู่ในคลังแล้วสำหรับสินค้าอื่น";
                $_SESSION['message_type_product_edit'] = "error";
                if ($product_id) {
                    $_SESSION['old_product_form_data_edit'][$product_id] = $_POST;
                }
                header("Location: " . $form_page_redirect);
                exit();
            }
            $stmt_check->close();
        } else {
             // Log this error for admin, show generic message to user
            error_log("Edit Product - SQL Check Prepare Error: " . $conn->error);
            $_SESSION['message_product_edit'] = "เกิดข้อผิดพลาดในการตรวจสอบข้อมูลสินค้า";
            $_SESSION['message_type_product_edit'] = "error";
            if ($product_id) {
                 $_SESSION['old_product_form_data_edit'][$product_id] = $_POST;
            }
            header("Location: " . $form_page_redirect);
            exit();
        }

        // Update product
        $sql = "UPDATE products SET name = ?, category = ?, default_price = ?, notes = ? WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            // For default_price: if it's null, SQL should handle it. If it's a value, 'd' type.
            // Binding: s (name), s (category), d (default_price), s (notes), i (id), i (user_id)
            // Need to handle NULL for default_price carefully in bind_param
            if ($default_price === null) {
                // If default_price is intended to be NULL, this is tricky with bind_param strictly typed.
                // One approach is to prepare two SQLs or use a query builder.
                // Simpler for now: if default_price is set to NULL in DB by default, and we don't update it unless a value is provided.
                // Or, if your DB column for default_price is `DECIMAL(10,2) NULL`, sending NULL via bind_param works with 'd' if variable is null.
                // Let's assume direct binding works if $default_price is PHP null.
                 $stmt->bind_param("ssd sii", $name, $category, $default_price, $notes, $product_id, $current_user_id);
            } else {
                 $stmt->bind_param("ssd sii", $name, $category, $default_price, $notes, $product_id, $current_user_id);
            }


            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $_SESSION['message'] = "อัปเดตข้อมูลสินค้า '" . htmlspecialchars($name) . "' เรียบร้อยแล้ว"; // Global message
                    $_SESSION['message_type'] = "success";
                } else {
                    // Check if product actually exists and belongs to user, or no data was changed
                    $check_exists_sql = "SELECT id FROM products WHERE id = ? AND user_id = ?";
                    $stmt_exists = $conn->prepare($check_exists_sql);
                    $stmt_exists->bind_param("ii", $product_id, $current_user_id);
                    $stmt_exists->execute();
                    $result_exists = $stmt_exists->get_result();
                    $stmt_exists->close();
                    if ($result_exists->num_rows == 0) {
                         $_SESSION['message'] = "ไม่พบสินค้าที่ต้องการแก้ไข หรือคุณไม่มีสิทธิ์ (อาจถูกลบไปแล้ว)";
                         $_SESSION['message_type'] = "error"; // More severe if it disappeared
                    } else {
                        $_SESSION['message'] = "ไม่มีข้อมูลที่เปลี่ยนแปลงสำหรับสินค้า '" . htmlspecialchars($name) . "'";
                        $_SESSION['message_type'] = "warning";
                    }
                }
                header("Location: manage_products.php");
                exit();
            } else {
                $_SESSION['message_product_edit'] = "เกิดข้อผิดพลาดในการอัปเดตข้อมูลสินค้า: " . $stmt->error;
                $_SESSION['message_type_product_edit'] = "error";
            }
            $stmt->close();
        } else {
            $_SESSION['message_product_edit'] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL (update): " . $conn->error;
            $_SESSION['message_type_product_edit'] = "error";
        }
    } else {
        $_SESSION['message_product_edit'] = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้";
        $_SESSION['message_type_product_edit'] = "error";
    }
    // If any error occurred before header redirect
    if ($product_id) {
        $_SESSION['old_product_form_data_edit'][$product_id] = $_POST;
    }
    header("Location: " . $form_page_redirect);
    exit();
} else {
    // Not a POST request
    header("Location: manage_products.php");
    exit();
}
?>