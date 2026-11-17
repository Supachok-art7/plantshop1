<?php
/**
 * lang.php — สลับภาษา (ลิงก์จากปุ่ม ไทย | EN) แล้วพากลับหน้าเดิม
 * ตั้งคุกกี้ "lang" (th/en) เก็บ 1 ปี · ไม่ใช้ข้อมูลส่วนบุคคล · ตั้งเมื่อผู้ใช้กดเลือกเองเท่านั้น
 */
require __DIR__ . '/includes/config.php';
$l = ($_GET['l'] ?? '') === 'en' ? 'en' : 'th';
setcookie('lang', $l, ['expires' => time() + 86400 * 365, 'path' => '/', 'samesite' => 'Lax', 'secure' => is_https()]);
$next = safe_next($_GET['next'] ?? '');
header('Location: ' . ($next !== '' ? $next : 'index.php'));
exit;
