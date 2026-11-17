<?php
/**
 * logout.php — ออกจากระบบ (รับเฉพาะการกดปุ่มจากฟอร์มในหน้า account.php)
 * ล้างข้อมูล session ทั้งหมดแล้วกลับหน้าแรก
 */
require __DIR__ . '/includes/config.php';
chk();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION = [];
    session_destroy();
}
header('Location: index.php');
