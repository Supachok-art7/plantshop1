<?php
/**
 * file.php — เปิดดูไฟล์ในโฟลเดอร์ private/ (เอกสารยืนยันตัวตน) — เฉพาะแอดมินเท่านั้น
 * ใช้: file.php?f=ชื่อไฟล์   โฟลเดอร์ private/ ถูกปิดไม่ให้เปิดตรงจากเว็บ ต้องผ่านไฟล์นี้เท่านั้น
 */
require __DIR__ . '/includes/config.php';
need('admin');

$name = basename($_GET['f'] ?? '');                 // basename กันการระบุ path แปลกๆ เช่น ../
$path = ROOT_DIR . '/private/' . $name;
if ($name === '' || !is_file($path)) {
    http_response_code(404);
    exit('ไม่พบไฟล์');
}
audit('view_private_doc', $name);      // PDPA: จดว่าแอดมินเปิดดูเอกสารยืนยันตัวตน
$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
$ext   = strtolower(pathinfo($name, PATHINFO_EXTENSION));
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Cache-Control: private, no-store');
readfile($path);
