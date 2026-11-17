<?php
/**
 * set_password.php — ตั้งรหัสผ่านใหม่ให้สมาชิกคนไหนก็ได้ (ตั้งรหัสง่ายๆ ได้ ไม่บังคับความยาว)
 * ใช้เมื่อเข้าระบบไม่ได้ เช่น เผลอพิมพ์รหัสธรรมดาลงฐานข้อมูลตรงๆ (เว็บเก็บรหัสแบบเข้ารหัส จึงต้องตั้งผ่านหน้านี้)
 * วิธีใช้: เปิด http://localhost/plantshop/tools/set_password.php เลือกอีเมล พิมพ์รหัสใหม่ แล้วกดบันทึก  ใช้เสร็จ "ลบไฟล์นี้ทิ้ง"
 */
require __DIR__ . '/../includes/config.php';
chk();

// กันคนนอก: ใช้ได้เฉพาะเปิดจากเครื่องตัวเอง
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('ใช้ได้เฉพาะเปิดจากเครื่องตัวเอง (localhost) เท่านั้น');
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['uid'] ?? 0);
    $pw = (string)($_POST['pw'] ?? '');
    if ($id < 1 || $pw === '') {
        $msg = '❌ เลือกสมาชิกและพิมพ์รหัสผ่านใหม่ก่อน';
    } else {
        q('UPDATE users SET pass=? WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
        // อีเมลต้องเป็นตัวพิมพ์เล็กไม่มีช่องว่าง ไม่งั้นหน้าล็อกอินหาไม่เจอ
        q('UPDATE users SET email=LOWER(TRIM(email)) WHERE id=?', [$id]);
        $msg = '✅ ตั้งรหัสผ่านใหม่แล้ว ลองเข้าสู่ระบบได้เลย';
    }
}
$users = q('SELECT id,name,email,role FROM users ORDER BY role, id')->fetchAll();
?>
<!DOCTYPE html>
<html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ตั้งรหัสผ่านใหม่</title>
<style>body{font:15px/1.6 'Noto Sans Thai',system-ui,sans-serif;max-width:520px;margin:24px auto;padding:0 14px;background:#f3faf5;color:#17301f}
.c{background:#fff;border:1px solid #d2eadb;border-radius:14px;padding:16px}select,input,button{font:inherit;width:100%;padding:9px;margin:6px 0;border-radius:10px;border:1px solid #d2eadb}
button{background:#1f9d55;color:#fff;border:0;font-weight:700;cursor:pointer}.w{background:#fde2df;padding:10px 14px;border-radius:10px}</style></head><body>
<h1>🔑 ตั้งรหัสผ่านใหม่</h1>
<?php if ($msg): ?><p class="c"><?= e($msg) ?> <a href="../login.php">ไปหน้าเข้าสู่ระบบ</a></p><?php endif; ?>
<form method="post" class="c">
  <?= csrf() ?>
  <label>สมาชิก
    <select name="uid" required>
      <option value="">— เลือกอีเมล —</option>
      <?php foreach ($users as $u): ?>
        <option value="<?= $u['id'] ?>"><?= e(($u['role'] === 'admin' ? '[แอดมิน] ' : '') . $u['email'] . ' (' . $u['name'] . ')') ?></option>
      <?php endforeach; ?>
    </select></label>
  <label>รหัสผ่านใหม่ <input name="pw" placeholder="พิมพ์รหัสที่ต้องการ" required></label>
  <button>บันทึกรหัสผ่านใหม่</button>
</form>
<p class="w">⚠️ ใช้เสร็จแล้วให้ลบไฟล์ <b>set_password.php</b> ทิ้ง</p>
</body></html>
