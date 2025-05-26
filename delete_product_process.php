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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = "ID สินค้าไม่ถูกต้องสำหรับการลบ"; // Global message for manage_products.php
    $_SESSION['message_type'] = "error";
    header("Location: manage_products.php");
    exit();
}
$product_id_to_delete = (int)$_GET['id'];

if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf_token'])) {
    $_SESSION['message'] = "CSRF token ไม่ถูกต้องหรือหมดอายุการใช้งาน";
    $_SESSION['message_type'] = "error";
    header("Location: manage_products.php");
    exit();
}
// For GET delete actions, consider token handling for retries. No unsetting here for now.

if ($conn) {
    // Before deleting, check if the product is in use in expense_items,
    // especially if your foreign key is ON DELETE RESTRICT (which is a good default).
    $sql_check_usage = "SELECT COUNT(*) as count FROM expense_items WHERE product_id = ?";
    // Note: user_id check on expense_items is indirect via bill_id -> bills.user_id.
    // For this specific check, we only care if *any* expense_item uses this product_id.
    // The delete itself will be restricted to user's product.
    $stmt_check_usage = $conn->prepare($sql_check_usage);
    $product_in_use = false;
    if ($stmt_check_usage) {
        $stmt_check_usage->bind_param("i", $product_id_to_delete);
        $stmt_check_usage->execute();
        $result_usage = $stmt_check_usage->get_result()->fetch_assoc();
        if ($result_usage && $result_usage['count'] > 0) {
            $product_in_use = true;
        }
        $stmt_check_usage->close();
    } else {
        error_log("Delete Product - SQL Check Usage Prepare Error: " . $conn->error);
        $_SESSION['message'] = "เกิดข้อผิดพลาดในการตรวจสอบการใช้งานสินค้า";
        $_SESSION['message_type'] = "error";
        header("Location: manage_products.php");
        exit();
    }

    if ($product_in_use) {
        // Fetch product name for a more descriptive message
        $product_name_for_msg = "ID: " . $product_id_to_delete;
        $stmt_get_name = $conn->prepare("SELECT name FROM products WHERE id = ? AND user_id = ?");
        if($stmt_get_name){
            $stmt_get_name->bind_param("ii", $product_id_to_delete, $current_user_id);
            $stmt_get_name->execute();
            $res_name = $stmt_get_name->get_result();
            if($p_row = $res_name->fetch_assoc()){
                $product_name_for_msg = htmlspecialchars($p_row['name']);
            }
            $stmt_get_name->close();
        }
        $_SESSION['message'] = "ไม่สามารถลบสินค้า '" . $product_name_for_msg . "' ได้ เนื่องจากมีการใช้งานอยู่ในรายการบิลแล้ว หากต้องการลบ โปรดลบรายการสินค้าออกจากบิล หรือลบบิลที่เกี่ยวข้องก่อน";
        $_SESSION['message_type'] = "error";
        header("Location: manage_products.php");
        exit();
    }

    // If not in use (or FK allows cascade/set null and you want to proceed), attempt delete.
    $sql = "DELETE FROM products WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $product_id_to_delete, $current_user_id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $_SESSION['message'] = "ลบสินค้าเรียบร้อยแล้ว";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "ไม่พบสินค้าที่ต้องการลบ หรือคุณไม่มีสิทธิ์";
                $_SESSION['message_type'] = "warning";
            }
        } else {
            $_SESSION['message'] = "เกิดข้อผิดพลาดในการลบสินค้า: " . $stmt->error;
            $_SESSION['message_type'] = "error";
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL (delete): " . $conn->error;
        $_SESSION['message_type'] = "error";
    }
} else {
    $_SESSION['message'] = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้";
    $_SESSION['message_type'] = "error";
}
header("Location: manage_products.php");
exit();
?>