<?php
/**
 * login.php — หน้าเข้าสู่ระบบ (แยกจากหน้าสมัครสมาชิก)
 * ล็อกอินสำเร็จ: แอดมินไป admin.php / ลูกค้าไป index.php
 */
require __DIR__ . '/includes/config.php';
chk();

// ล็อกอินอยู่แล้วไม่ต้องเข้าหน้านี้อีก
if (me()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $user  = q('SELECT * FROM users WHERE email=?', [$email])->fetch();

    // password_verify เทียบรหัสที่พิมพ์กับรหัสที่เข้ารหัสไว้ในฐานข้อมูล
    if ($user && password_verify($_POST['pass'] ?? '', $user['pass'])) {
        session_regenerate_id(true);       // เปลี่ยนรหัส session กันการขโมย
        $_SESSION['uid'] = $user['id'];    // จำว่าใครล็อกอิน
        // ถ้ามาจากหน้าที่ต้องล็อกอินก่อน (เช่น แจ้งรายงาน) พากลับไปหน้านั้น  ไม่งั้นแอดมิน→หลังบ้าน ลูกค้า→หน้าแรก
        $next = safe_next($_GET['next'] ?? '');
        header('Location: ' . ($next !== '' ? $next : ($user['role'] === 'admin' ? 'admin.php' : 'index.php')));
        exit;
    }
    $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
}

head('เข้าสู่ระบบ');
if (!empty($_GET['next'])) {
    echo '<div class="flash">กรุณาเข้าสู่ระบบก่อนใช้งานหน้านี้</div>';
}
if ($error) {
    echo '<div class="flash">' . e($error) . '</div>';
}
?>
<form method="post" class="card" style="max-width:420px;margin:30px auto">
  <h2>🔑 เข้าสู่ระบบ</h2>
  <?= csrf() ?>
  <p><input type="email" name="email" id="f-email" placeholder="อีเมล" required></p>
  <p><input type="password" name="pass" id="f-pass" placeholder="รหัสผ่าน" required></p>
  <button style="width:100%">เข้าสู่ระบบ</button>
  <p class="mut" style="text-align:center">ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a></p>
</form>
<?php if (setting('demo_mode', '0') === '1'):
    // บัญชีทดลองสำหรับครู/ผู้ตรวจ: [อีเมล, รหัสผ่าน, ชื่อ, กรณีตัวอย่างที่ดูได้]
    $demo = [
        ['admin@shop.test',          'Admin@1234',    'ผู้ดูแลร้าน (Admin)',     'เข้าหลังบ้านได้ทุกแท็บ: ออเดอร์ สินค้า สมาชิก ตรวจเอกสาร PDPA'],
        ['customer@shop.test',       'Customer@1234', 'สมชาย ทดสอบ',             'ลูกค้าพื้นฐาน มีออเดอร์ตัวอย่างพร้อมติดตามพัสดุ'],
        ['demo.verified@shop.test',  'Demo@1234',     'สมหญิง ใจดี',             'ยืนยันตัวตนแล้ว (ป้าย ✔) · ออเดอร์ส่งสำเร็จ + เก็บเงินปลายทาง · รีวิว · รายงานที่แอดมินตอบแล้ว'],
        ['demo.pending@shop.test',   'Demo@1234',     'ธนากร รอตรวจสอบ',         'ส่งเอกสารยืนยันตัวตนรอแอดมินตรวจ · ออเดอร์แจ้งโอนแล้วรอตรวจสลิป · ลบบัญชีไม่ได้เพราะมีออเดอร์ค้าง'],
        ['demo.rejected@shop.test',  'Demo@1234',     'มานี ไม่ผ่านการตรวจ',     'ยืนยันตัวตนไม่ผ่าน (มีเหตุผล) · ออเดอร์รอชำระเงิน · รายงานที่ถูกปฏิเสธ'],
        ['demo.new@shop.test',       'Demo@1234',     'ใหม่ ล่าสุด',             'สมาชิกใหม่ ยังไม่รับนโยบายเวอร์ชันล่าสุด (เห็นแถบเตือน PDPA) ไม่มีออเดอร์/ที่อยู่'],
        ['demo.shipping@shop.test',  'Demo@1234',     'วิชัย ส่งของภูเก็ต',      'ออเดอร์ "เตรียมสินค้า" และ "จัดส่งแล้ว" เส้นทางพัสดุกรุงเทพฯ → ภูเก็ต บนแผนที่ติดตาม'],
        ['demo.cancel@shop.test',    'Demo@1234',     'ปราณี ยกเลิกออเดอร์',     'ออเดอร์ที่ยกเลิก (คืนสต็อก) · รายงานที่ดำเนินการเสร็จ'],
        ['demo.chat@shop.test',      'Demo@1234',     'ชัย ถามแชท',              'แชทกับร้านมีข้อความยังไม่อ่าน · รายงานสถานะ "ใหม่" และ "กำลังตรวจสอบ"'],
        ['demo.dsr@shop.test',       'Demo@1234',     'ดวงใจ ใช้สิทธิ PDPA',     'คำขอใช้สิทธิ์ครบทุกสถานะ (ขอลบเลยกำหนด 30 วัน) · ประวัติให้/ถอนความยินยอม'],
        ['demo.vip@shop.test',       'Demo@1234',     'เศรษฐี ลูกค้าประจำ',      'ลูกค้าประจำ ซื้อหลายครั้ง รีวิวหลายสินค้า ยอดซื้อสะสมสูง ยืนยันตัวตนแล้ว'],
    ]; ?>
<div class="card" style="max-width:760px;margin:0 auto 30px">
  <h3>🎓 บัญชีทดลองสำหรับครู/ผู้ตรวจ</h3>
  <p class="mut">กด "ใช้บัญชีนี้" เพื่อเติมอีเมลและรหัสผ่านให้ แล้วกดเข้าสู่ระบบ · ข้อมูลทั้งหมดเป็นข้อมูลสมมติ (แอดมินปิดแผงนี้ได้ที่หลังบ้าน แท็บ "ข้อมูลร้าน")</p>
  <div class="scroll"><table>
    <tr><th>บัญชี</th><th>กรณีตัวอย่างที่ดูได้</th><th></th></tr>
    <?php foreach ($demo as $d): ?>
      <tr>
        <td><b><?= e($d[2]) ?></b><br><span class="mut"><?= e($d[0]) ?><br>รหัสผ่าน: <?= e($d[1]) ?></span></td>
        <td class="mut"><?= e($d[3]) ?></td>
        <td><button type="button" class="ghost sm" onclick="document.getElementById('f-email').value=<?= e(json_encode($d[0])) ?>;document.getElementById('f-pass').value=<?= e(json_encode($d[1])) ?>;window.scrollTo(0,0)">ใช้บัญชีนี้</button></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>
<?php foot();
