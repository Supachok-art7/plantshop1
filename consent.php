<?php
/**
 * consent.php — รับตัวเลือกคุกกี้จากแบนเนอร์ (POST เท่านั้น) แล้วพากลับหน้าเดิม
 * จำตัวเลือกไว้ในคุกกี้ 180 วัน ถ้าล็อกอินอยู่จะบันทึกลงประวัติความยินยอมด้วย
 */
require __DIR__ . '/includes/config.php';
chk();

$choice = $_POST['choice'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $choice === 'reset') {
    // ล้างตัวเลือกคุกกี้ของผู้เยี่ยมชมคนนี้ เพื่อให้แบนเนอร์ขึ้นใหม่ (ใช้ทดสอบจากหน้าตรวจเช็ค)
    setcookie('pdpa_ck', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true, 'secure' => is_https()]);
    unset($_COOKIE['pdpa_ck']);
    clear_analytics_cookies();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($choice, ['all', 'necessary'], true)) {
    set_cookie_choice($choice);
    if ($choice === 'necessary') {
        clear_analytics_cookies();   // ถอนความยินยอม: ลบคุกกี้ GA ที่เคยตั้งไว้
    }
    $u = me();
    if ($u) {
        consent_log($u['id'], 'cookies', $choice === 'all');
    }
}
$next = safe_next($_POST['next'] ?? '');
header('Location: ' . ($next !== '' ? $next : 'index.php'));
exit;
