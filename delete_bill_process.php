<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'use_strict_mode' => true,
        'gc_maxlifetime' => 1800
    ]);
}
require_once 'db_connect.php'; //

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message_login'] = "กรุณาเข้าสู่ระบบเพื่อดำเนินการ";
    $_SESSION['message_type_login'] = "error";
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];

// Ensure CSRF token function is available (or include from a central functions file)
if (!function_exists('verify_csrf_token_get_delete')) { // Use a unique name
    function verify_csrf_token_get_delete($token_from_get) {
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token_from_get)) {
            $_SESSION['message'] = "CSRF token ไม่ถูกต้องหรือหมดอายุการใช้งาน กรุณาลองอีกครั้ง"; // Global message for index.php
            $_SESSION['message_type'] = "error";
            // For GET actions, redirecting back to origin or index is common.
            // Consider if unsetting the token here is always the best.
            // If the token was meant for single use, it should be regenerated on the source page.
            // For now, we just report error and redirect.
            header("Location: index.php");
            exit();
        }
        // For GET-based CSRF, unsetting the token immediately can be problematic
        // if the user uses the back button and tries again without a page refresh.
        // However, for a delete action, it's often a good idea to ensure it's single-use.
        // For simplicity now, we will not unset it here but rely on page reload to get a new one.
        // A better approach for critical GET actions might involve a confirmation POST form.
        return true;
    }
}

if (!isset($_GET['bill_id']) || !is_numeric($_GET['bill_id'])) {
    $_SESSION['message'] = "ID บิลไม่ถูกต้องสำหรับการลบ";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}
$bill_id_to_delete = (int)$_GET['bill_id'];

$submitted_csrf_token = $_GET['csrf_token'] ?? '';
verify_csrf_token_get_delete($submitted_csrf_token);


if ($conn) {
    $conn->begin_transaction();
    try {
        // First, verify ownership and existence of the bill
        // <<<< MODIFIED HERE >>>>
        $sql_check_owner = "SELECT id FROM bills WHERE id = ? AND user_id = ?";
        $stmt_check_owner = $conn->prepare($sql_check_owner);
        if (!$stmt_check_owner) {
            throw new Exception("เกิดข้อผิดพลาดในการเตรียม SQL ตรวจสอบเจ้าของบิล: " . $conn->error);
        }
        $stmt_check_owner->bind_param("ii", $bill_id_to_delete, $current_user_id);
        $stmt_check_owner->execute();
        $result_check_owner = $stmt_check_owner->get_result();

        if ($result_check_owner->num_rows === 0) {
            $stmt_check_owner->close();
            throw new Exception("ไม่พบบิล ID: " . htmlspecialchars($bill_id_to_delete) . " หรือคุณไม่มีสิทธิ์ลบบิลนี้");
        }
        $stmt_check_owner->close();

        // If ownership is confirmed, proceed to delete items and then the bill.

        // 1. Delete related expense items first
        $sql_delete_items = "DELETE FROM expense_items WHERE bill_id = ?";
        $stmt_delete_items = $conn->prepare($sql_delete_items);
        if (!$stmt_delete_items) {
            throw new Exception("เกิดข้อผิดพลาดในการเตรียม SQL สำหรับลบรายการสินค้า: " . $conn->error);
        }
        $stmt_delete_items->bind_param("i", $bill_id_to_delete); // bill_id is confirmed to be of the user
        if (!$stmt_delete_items->execute()) {
            throw new Exception("เกิดข้อผิดพลาดในการลบรายการสินค้าของบิล: " . $stmt_delete_items->error);
        }
        $stmt_delete_items->close();

        // 2. Delete the main bill (user_id check is implicitly handled by the ownership check above)
        // but adding it here provides an additional layer of safety, though redundant if the first check passes.
        // <<<< MODIFIED HERE (Redundant but safe) >>>>
        $sql_delete_bill = "DELETE FROM bills WHERE id = ? AND user_id = ?";
        $stmt_delete_bill = $conn->prepare($sql_delete_bill);
        if (!$stmt_delete_bill) {
            throw new Exception("เกิดข้อผิดพลาดในการเตรียม SQL สำหรับลบบิลหลัก: " . $conn->error);
        }
        $stmt_delete_bill->bind_param("ii", $bill_id_to_delete, $current_user_id);
        if (!$stmt_delete_bill->execute()) {
            throw new Exception("เกิดข้อผิดพลาดในการลบบิลหลัก: " . $stmt_delete_bill->error);
        }

        if ($stmt_delete_bill->affected_rows > 0) {
            $conn->commit();
            $_SESSION['message'] = "ลบบิล ID: " . htmlspecialchars($bill_id_to_delete) . " และรายการสินค้าที่เกี่ยวข้องเรียบร้อยแล้ว";
            $_SESSION['message_type'] = "success";
        } else {
            // This case should ideally be caught by the ownership check earlier.
            // If reached, it means the bill existed (passed ownership check) but wasn't deleted by this specific user_id query.
            $conn->rollback(); // Rollback if the final delete didn't affect rows as expected
            $_SESSION['message'] = "ไม่สามารถลบบิล ID: " . htmlspecialchars($bill_id_to_delete) . " ได้ (อาจถูกลบไปแล้ว หรือเกิดข้อผิดพลาด)";
            $_SESSION['message_type'] = "warning";
        }
        $stmt_delete_bill->close();

    } catch (Exception $e) {
        if ($conn->inTransaction()) { // Check if transaction is active before rollback
            $conn->rollback();
        }
        $_SESSION['message'] = "การลบบิลล้มเหลว: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
} else {
    $_SESSION['message'] = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้";
    $_SESSION['message_type'] = "error";
}


if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

header("Location: index.php");
exit();
?>