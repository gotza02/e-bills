<?php
// เปิดใช้งานการรายงานข้อผิดพลาดของ MySQLi เพื่อให้โยน Exceptions
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// กำหนดค่าการเชื่อมต่อฐานข้อมูล
// *** โปรดเปลี่ยนค่าเหล่านี้ให้ตรงกับการตั้งค่าจริงของคุณ ***
// *** และพิจารณาวิธีการจัดเก็บข้อมูลสำคัญเหล่านี้ให้ปลอดภัยยิ่งขึ้นในสภาพแวดล้อม Production ***
// *** เช่น การใช้ Environment Variables หรือไฟล์ Config ที่อยู่นอก Web Root ***
define('DB_HOST', 'localhost');
define('DB_USER', 'gotza02');
define('DB_PASS', '016593160Qq'); // **คำเตือน:** รหัสผ่านไม่ควร Hardcode โดยตรงในโค้ดที่แชร์สาธารณะ
define('DB_NAME', 'my_expenses');
define('DB_CHARSET', 'utf8mb4');

try {
    // สร้างการเชื่อมต่อใหม่ด้วย mysqli (Object-Oriented Style)
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // ตั้งค่าชุดอักขระ (Charset) สำหรับการเชื่อมต่อ
    // การตั้งค่า charset ที่นี่หลังจากเชื่อมต่อสำเร็จเป็นวิธีที่แนะนำ
    if (!$conn->set_charset(DB_CHARSET)) {
        // หากการตั้งค่า charset ล้มเหลว ให้โยน Exception
        // ปกติแล้ว mysqli_report จะจัดการเรื่องนี้ แต่การตรวจสอบซ้ำก็ไม่เสียหาย
        throw new Exception("Error loading character set " . DB_CHARSET . ": " . $conn->error);
    }

} catch (mysqli_sql_exception $e) {
    // บันทึกข้อความแสดงข้อผิดพลาดลง error log ของเซิร์ฟเวอร์
    // การแสดง error code และ message ของ SQL โดยตรงให้ผู้ใช้เห็นอาจไม่ปลอดภัย
    error_log("Database Connection Failed: (Code: " . $e->getCode() . ") " . $e->getMessage());

    // แสดงข้อความทั่วไปให้ผู้ใช้ทราบ
    // **ข้อควรระวัง:** ในสภาพแวดล้อม Production จริง อาจไม่ต้องการแสดง die() ที่นี่
    // แต่อาจจะมีการจัดการ error ที่ซับซ้อนกว่านี้ เช่น redirect ไปหน้า error page
    die("ระบบฐานข้อมูลขัดข้อง โปรดติดต่อผู้ดูแลระบบ (Error Code: DBCONN_FAIL)");

} catch (Exception $e) {
    // จัดการกับ Exceptions อื่นๆ ที่อาจเกิดขึ้น (เช่น จากการตั้งค่า charset)
    error_log("Database Charset/General Setup Error: " . $e->getMessage());

    // ตรวจสอบว่าการเชื่อมต่อถูกสร้างขึ้นหรือไม่ก่อนที่จะพยายามปิด
    if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
        $conn->close();
    }
    die("ระบบขัดข้อง โปรดติดต่อผู้ดูแลระบบ (Error Code: DBSETUP_FAIL)");
}

// หากโค้ดทำงานมาถึงตรงนี้ได้ แสดงว่าการเชื่อมต่อสำเร็จ
// ตัวแปร $conn พร้อมใช้งานสำหรับไฟล์อื่นๆ ที่ include ไฟล์นี้
// ไม่จำเป็นต้องมีคำสั่ง echo หรือ output ใดๆ ในไฟล์นี้
?>