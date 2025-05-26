<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
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
    $_SESSION['message'] = "ID ร้านค้าไม่ถูกต้องสำหรับการลบ";
    $_SESSION['message_type'] = "error";
    header("Location: manage_vendors.php");
    exit();
}
$vendor_id_to_delete = (int)$_GET['id'];

if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf_token'])) {
    $_SESSION['message'] = "CSRF token ไม่ถูกต้องหรือหมดอายุการใช้งาน";
    $_SESSION['message_type'] = "error";
    header("Location: manage_vendors.php");
    exit();
}
// For GET delete actions, consider if token should be single-use or session-bound until logout.
// For now, we won't unset it here to allow retry if user confirms again without page refresh.

if ($conn) {
    // Optional: Check if vendor is in use if ON DELETE RESTRICT is set on bills.vendor_id
    // For ON DELETE SET NULL, this check is not strictly necessary for deletion itself.
    /*
    $sql_check_usage = "SELECT COUNT(*) as count FROM bills WHERE vendor_id = ? AND user_id = ?";
    $stmt_check_usage = $conn->prepare($sql_check_usage);
    if ($stmt_check_usage) {
        $stmt_check_usage->bind_param("ii", $vendor_id_to_delete, $current_user_id);
        $stmt_check_usage->execute();
        $result_usage = $stmt_check_usage->get_result()->fetch_assoc();
        $stmt_check_usage->close();
        if ($result_usage['count'] > 0) {
            $_SESSION['message'] = "ไม่สามารถลบร้านค้านี้ได้ เนื่องจากมีการใช้งานอยู่ในบิลแล้ว (หากต้องการลบ โปรดแก้ไขบิลที่เกี่ยวข้องก่อน)";
            $_SESSION['message_type'] = "error";
            header("Location: manage_vendors.php");
            exit();
        }
    }
    */

    $sql = "DELETE FROM vendors WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $vendor_id_to_delete, $current_user_id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $_SESSION['message'] = "ลบร้านค้าเรียบร้อยแล้ว";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "ไม่พบร้านค้าที่ต้องการลบ หรือคุณไม่มีสิทธิ์";
                $_SESSION['message_type'] = "warning";
            }
        } else {
            $_SESSION['message'] = "เกิดข้อผิดพลาดในการลบร้านค้า: " . $stmt->error;
            $_SESSION['message_type'] = "error";
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
        $_SESSION['message_type'] = "error";
    }
} else {
    $_SESSION['message'] = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้";
    $_SESSION['message_type'] = "error";
}
header("Location: manage_vendors.php");
exit();
?>