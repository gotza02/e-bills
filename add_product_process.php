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

$source_page_redirect = (isset($_POST['source_page']) && $_POST['source_page'] === 'add_bill') ? null : 'manage_products.php';
$form_page_redirect = (isset($_POST['source_page']) && $_POST['source_page'] === 'add_bill') ? 'add_product_form.php?source=add_bill' : 'add_product_form.php';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $_SESSION['message_product_add'] = "CSRF token ไม่ถูกต้องหรือหมดอายุการใช้งาน";
        $_SESSION['message_type_product_add'] = "error";
        header("Location: " . $form_page_redirect);
        exit();
    }
    unset($_SESSION['csrf_token']); // Consume token

    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '') ?: null;
    // Handle default_price: ensure it's a valid decimal or null
    $default_price_str = trim($_POST['default_price'] ?? '');
    $default_price = null;
    if ($default_price_str !== '') {
        if (is_numeric($default_price_str) && floatval($default_price_str) >= 0) {
            $default_price = floatval($default_price_str);
        } else {
            $errors[] = "ราคาตั้งต้นไม่ถูกต้อง (ต้องเป็นตัวเลข >= 0)";
        }
    }
    
    $notes = trim($_POST['notes'] ?? '') ?: null;

    $errors = $errors ?? []; // Initialize if not set by price check
    if (empty($name)) {
        $errors[] = "กรุณากรอกชื่อสินค้า";
    }
    // Additional validation for name length, characters, etc. can be added here

    if (!empty($errors)) {
        $_SESSION['message_product_add'] = implode("<br>", $errors);
        $_SESSION['message_type_product_add'] = "error";
        $_SESSION['old_product_form_data'] = $_POST;
        header("Location: " . $form_page_redirect);
        exit();
    }

    if ($conn) {
        // Check for duplicate product name for this user
        $sql_check = "SELECT id FROM products WHERE user_id = ? AND name = ?";
        $stmt_check = $conn->prepare($sql_check);
        if ($stmt_check) {
            $stmt_check->bind_param("is", $current_user_id, $name);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows > 0) {
                $_SESSION['message_product_add'] = "ชื่อสินค้า '" . htmlspecialchars($name) . "' นี้มีอยู่ในคลังแล้ว";
                $_SESSION['message_type_product_add'] = "error";
                $_SESSION['old_product_form_data'] = $_POST;
                header("Location: " . $form_page_redirect);
                exit();
            }
            $stmt_check->close();
        } else {
            $_SESSION['message_product_add'] = "เกิดข้อผิดพลาดในการตรวจสอบชื่อสินค้าซ้ำ: " . $conn->error;
            $_SESSION['message_type_product_add'] = "error";
            $_SESSION['old_product_form_data'] = $_POST;
            header("Location: " . $form_page_redirect);
            exit();
        }

        // Insert new product
        $sql = "INSERT INTO products (user_id, name, category, default_price, notes) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            // For default_price, use 'd' for decimal if $default_price is float, or 's' if you pass it as string, or check if null
            // If $default_price is null, mysqli needs special handling or ensure DB column allows NULL
            // Binding types: i (user_id), s (name), s (category), d (default_price), s (notes)
            // If default_price can be null, the bind_param logic needs to handle it carefully.
            // One way: $stmt->bind_param("issds", $current_user_id, $name, $category, $default_price, $notes);
            // For robust NULL handling with bind_param:
            if ($default_price === null) {
                 $stmt->bind_param("issss", $current_user_id, $name, $category, $default_price, $notes); // Bind NULL as string 's' for notes and category, default_price as 's' too if it's NULL to avoid type error
            } else {
                 $stmt->bind_param("issds", $current_user_id, $name, $category, $default_price, $notes); // default_price as double 'd'
            }


            if ($stmt->execute()) {
                if ($source_page_redirect) { // Redirect to manage_products.php
                    $_SESSION['message'] = "เพิ่มสินค้า '" . htmlspecialchars($name) . "' เข้าคลังเรียบร้อยแล้ว";
                    $_SESSION['message_type'] = "success";
                    header("Location: " . $source_page_redirect);
                } else { // Came from add_bill (source=add_bill), show message on the add_product_form itself
                    $_SESSION['message_product_add'] = "เพิ่มสินค้า '" . htmlspecialchars($name) . "' เรียบร้อยแล้ว! คุณสามารถปิดหน้าต่างนี้และรีเฟรชหน้าเพิ่มบิล หรือเพิ่มสินค้าอื่นต่อได้";
                    $_SESSION['message_type_product_add'] = "success";
                    if(isset($_SESSION['old_product_form_data'])) unset($_SESSION['old_product_form_data']); // Clear form data on success
                    header("Location: " . $form_page_redirect); // Redirect back to form to show success and allow adding more
                }
                exit();
            } else {
                $_SESSION['message_product_add'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลสินค้า: " . $stmt->error;
                $_SESSION['message_type_product_add'] = "error";
            }
            $stmt->close();
        } else {
            $_SESSION['message_product_add'] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
            $_SESSION['message_type_product_add'] = "error";
        }
    } else {
        $_SESSION['message_product_add'] = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้";
        $_SESSION['message_type_product_add'] = "error";
    }
    $_SESSION['old_product_form_data'] = $_POST; // Keep data on error
    header("Location: " . $form_page_redirect);
    exit();
} else {
    // Not a POST request
    header("Location: " . $form_page_redirect);
    exit();
}
?>