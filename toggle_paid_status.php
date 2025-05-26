<?php
session_start();
require_once 'db_connect.php';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $expense_id = intval($_GET['id']);
    $sql_select = "SELECT is_paid FROM expenses WHERE id = ?";
    $current_status = null;

    if ($stmt_select = $conn->prepare($sql_select)) {
        $stmt_select->bind_param("i", $expense_id);
        $stmt_select->execute();
        $result_select = $stmt_select->get_result();
        if ($result_select->num_rows === 1) {
            $row = $result_select->fetch_assoc();
            $current_status = $row['is_paid'];
        }
        $stmt_select->close();
    } else {
        $_SESSION['message'] = "เกิดข้อผิดพลาดในการเตรียมคำสั่งดึงข้อมูล: " . $conn->error;
        $_SESSION['message_type'] = "error";
        if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
        header("Location: index.php");
        exit();
    }

    if ($current_status !== null) {
        $new_status = ($current_status == 1) ? 0 : 1;
        $sql_update = "UPDATE expenses SET is_paid = ? WHERE id = ?";
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("ii", $new_status, $expense_id);
            if ($stmt_update->execute()) {
                if ($stmt_update->affected_rows > 0) {
                    $status_text = ($new_status == 1) ? "จ่ายแล้ว" : "ยังไม่จ่าย";
                    $_SESSION['message'] = "อัปเดตสถานะรายการ ID: " . $expense_id . " เป็น '" . $status_text . "' เรียบร้อยแล้ว";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "ไม่สามารถอัปเดตสถานะรายการ ID: " . $expense_id . " (อาจไม่มีการเปลี่ยนแปลง)";
                    $_SESSION['message_type'] = "warning";
                }
            } else {
                $_SESSION['message'] = "เกิดข้อผิดพลาดในการอัปเดตสถานะ: " . $stmt_update->error;
                $_SESSION['message_type'] = "error";
            }
            $stmt_update->close();
        } else {
            $_SESSION['message'] = "เกิดข้อผิดพลาดในการเตรียมคำสั่งอัปเดต: " . $conn->error;
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "ไม่พบรายการ ID: " . $expense_id;
        $_SESSION['message_type'] = "error";
    }

} else {
    $_SESSION['message'] = "ID รายการไม่ถูกต้อง";
    $_SESSION['message_type'] = "error";
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

header("Location: index.php");
exit();
?>