<?php
/**
 * export.php — ดาวน์โหลดข้อมูลของฉัน (สิทธิขอเข้าถึง/ขอสำเนา/โอนย้ายข้อมูล มาตรา 30 และ 31)
 * รับ POST จากปุ่มในหน้า privacy.php เท่านั้น ส่งไฟล์ JSON ให้ดาวน์โหลดทันที และบันทึกเป็นคำขอที่ "เสร็จสิ้น"
 */
require __DIR__ . '/includes/config.php';
chk();
$user = need();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: privacy.php');
    exit;
}
q("INSERT INTO dsr_requests(user_id,type,detail,status,admin_note) VALUES(?,?,?,?,?)",
  [$user['id'], 'access', 'ดาวน์โหลดข้อมูลของฉันด้วยตนเอง', 'done', 'ระบบสร้างไฟล์ให้ดาวน์โหลดอัตโนมัติ']);

$json = json_encode(export_user_data($user['id']), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="my-data-' . date('Ymd') . '.json"');
header('Cache-Control: private, no-store');
echo $json;
