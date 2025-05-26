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

// Ensure CSRF token function is available
if (!function_exists('verify_csrf_token_get_toggle')) { // Use a unique name
    function verify_csrf_token_get_toggle($token_from_get) {
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token_from_get)) {
            $_SESSION['message'] = "CSRF token ไม่ถูกต้องหรือหมดอายุการใช้งาน กรุณาลองอีกครั้ง";
            $_SESSION['message_type'] = "error";
            header("Location: index.php");
            exit();
        }
        // As with delete, consider implications of unsetting CSRF for GET here.
        return true;
    }
}

if (!isset($_GET['bill_id']) || !is_numeric($_GET['bill_id'])) {
    $_SESSION['message'] = "ID บิลไม่ถูกต้องสำหรับการอัปเดตสถานะ";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}
$bill_id = (int)$_GET['bill_id'];

$submitted_csrf_token = $_GET['csrf_token'] ?? '';
verify_csrf_token_get_toggle($submitted_csrf_token);

$current_status = null;

if ($conn) {
    // Fetch current status of the bill, ensuring it belongs to the current user
    // <<<< MODIFIED HERE >>>>
    $sql_select = "SELECT is_paid FROM bills WHERE id = ? AND user_id = ?"; // Added user_id = ?
    $stmt_select = $conn->prepare($sql_select);

    if ($stmt_select) {
        $stmt_select->bind_param("ii", $bill_id, $current_user_id);
        $stmt_select->execute();
        $result_select = $stmt_select->get_result();
        if ($result_select->num_rows === 1) {
            $row = $result_select->fetch_assoc();
            $current_status = (int)$row['is_paid'];
        } else {
            $_SESSION['message'] = "ไม่พบบิล ID: " . htmlspecialchars($bill_id) . " หรือคุณไม่มีสิทธิ์แก้ไขสถานะบิลนี้";
            $_SESSION['message_type'] = "error";
            $stmt_select->close();
            if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
            header("Location: index.php");
            exit();
        }
        $stmt_select->close();
    } else {
        $_SESSION['message'] = "เกิดข้อผิดพลาดในการเตรียมคำสั่งดึงข้อมูลสถานะบิล: " . htmlspecialchars($conn->error);
        $_SESSION['message_type'] = "error";
        if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
        header("Location: index.php");
        exit();
    }

    // Determine the new status
    $new_status = ($current_status == 1) ? 0 : 1;

    // Update the bill status, ensuring it belongs to the current user
    // <<<< MODIFIED HERE >>>>
    $sql_update = "UPDATE bills SET is_paid = ? WHERE id = ? AND user_id = ?"; // Added user_id = ?
    $stmt_update = $conn->prepare($sql_update);

    if ($stmt_update) {
        $stmt_update->bind_param("iii", $new_status, $bill_id, $current_user_id);
        if ($stmt_update->execute()) {
            if ($stmt_update->affected_rows > 0) {
                $status_text = ($new_status == 1) ? "จ่ายแล้ว" : "ยังไม่จ่าย";
                $_SESSION['message'] = "อัปเดตสถานะบิล ID: " . htmlspecialchars($bill_id) . " เป็น '" . $status_text . "' เรียบร้อยแล้ว";
                $_SESSION['message_type'] = "success";
            } else {
                // This could happen if the status was already the new_status, or user_id mismatch (though caught earlier)
                $_SESSION['message'] = "สถานะบิล ID: " . htmlspecialchars($bill_id) . " ไม่มีการเปลี่ยนแปลง (อาจเป็นสถานะเดิมอยู่แล้ว)";
                $_SESSION['message_type'] = "warning";
            }
        } else {
            $_SESSION['message'] = "เกิดข้อผิดพลาดในการอัปเดตสถานะบิล: " . htmlspecialchars($stmt_update->error);
            $_SESSION['message_type'] = "error";
        }
        $stmt_update->close();
    } else {
        $_SESSION['message'] = "เกิดข้อผิดพลาดในการเตรียมคำสั่งอัปเดตสถานะบิล: " . htmlspecialchars($conn->error);
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