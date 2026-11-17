<?php
/**
 * register.php — หน้าสมัครสมาชิก (แยกจากหน้าเข้าสู่ระบบ)
 * สมัครสำเร็จจะล็อกอินให้ทันทีแล้วไปหน้าแรก
 */
require __DIR__ . '/includes/config.php';
chk();

if (me()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $pass  = $_POST['pass'] ?? '';

    // ตรวจข้อมูลทีละข้อ พบข้อผิดพลาดข้อแรกแล้วหยุด
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($phone) < 9 || strlen($pass) < 6) {
        $error = 'กรอกข้อมูลให้ถูกต้อง (รหัสผ่านอย่างน้อย 6 ตัว)';
    } elseif ($pass !== ($_POST['pass2'] ?? '')) {
        $error = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
    } elseif (q('SELECT 1 FROM users WHERE email=?', [$email])->fetch()) {
        $error = 'อีเมลนี้สมัครแล้ว';
    } elseif (empty($_POST['accept'])) {
        $error = 'กรุณาติ๊กยอมรับนโยบายความเป็นส่วนตัวและเงื่อนไขการใช้งาน';
    } else {
        // เก็บรหัสผ่านแบบเข้ารหัส (ไม่เก็บรหัสจริง)
        q('INSERT INTO users(name,email,phone,pass) VALUES(?,?,?,?)',
          [$name, $email, $phone, password_hash($pass, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        $newId = db()->lastInsertId();
        accept_policy($newId);                                   // PDPA: บันทึกหลักฐานการยอมรับนโยบาย (เวอร์ชัน + เวลา)
        if (!empty($_POST['marketing'])) { set_marketing($newId, true); }   // ข่าวสารโปรโมชั่น = ไม่บังคับ
        $_SESSION['uid'] = $newId;                               // ล็อกอินให้เลย
        header('Location: index.php');
        exit;
    }
}

head('สมัครสมาชิก');
if ($error) {
    echo '<div class="flash">' . e($error) . '</div>';
}
?>
<form method="post" class="card" style="max-width:420px;margin:30px auto">
  <h2>🌱 สมัครสมาชิก</h2>
  <?= csrf() ?>
  <p><input name="name" value="<?= e($_POST['name'] ?? '') ?>" placeholder="ชื่อ-นามสกุล" required></p>
  <p><input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="อีเมล" required></p>
  <p><input name="phone" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="เบอร์โทร" required></p>
  <p><input type="password" name="pass" placeholder="รหัสผ่าน (อย่างน้อย 6 ตัว)" required></p>
  <p><input type="password" name="pass2" placeholder="ยืนยันรหัสผ่าน" required></p>
  <p class="consent"><label><input type="checkbox" name="accept" value="1" required>
    ฉันได้อ่านและยอมรับ <a href="policy.php?p=privacy" target="_blank">นโยบายความเป็นส่วนตัว</a> และ <a href="policy.php?p=terms" target="_blank">เงื่อนไขการใช้งาน</a></label></p>
  <p class="consent"><label><input type="checkbox" name="marketing" value="1">
    ฉันยินยอมรับข่าวสารและโปรโมชั่นจากร้าน <span class="mut">(ไม่บังคับ ถอนได้ทุกเมื่อ)</span></label></p>
  <button style="width:100%">สมัครสมาชิก</button>
  <p class="mut" style="text-align:center">มีบัญชีแล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
</form>
<?php foot();
