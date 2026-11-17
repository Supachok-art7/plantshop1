<?php
/**
 * demo_check.php — หน้าตัวอย่างสำหรับครู/ผู้ตรวจ: ลองและเห็นผลของ "GA4 ตามความยินยอมคุกกี้" และ "หน้า 404" ได้ในหน้าเดียว
 * เปิดได้เฉพาะเมื่อโหมดทดลองเปิดอยู่ (หลังบ้าน > ข้อมูลร้าน) ปิดโหมดทดลองแล้วหน้านี้จะเป็น 404
 * ไม่ส่งข้อมูลไป Google: ถ้าร้านยังไม่ตั้งรหัส GA4 หน้านี้ "จำลอง" ด้วยรหัสสมมติ (เพิ่ม ?sim=1) โดยไม่โหลดสคริปต์จริง
 */
require __DIR__ . '/includes/config.php';
if (!demo_on() && !(analytics_id() === '' && !empty($_GET['sim']))) {   // ?sim=1 เปิดดูได้เลย (ไม่มีข้อมูลจริงเกี่ยวข้อง)
    fail(404, 'ไม่พบหน้านี้');
}

$user   = me();
$choice = cookie_choice();
$realId = analytics_id();
$sim    = ($realId === '' && !empty($_GET['sim']));      // โหมดจำลอง
$id     = $realId !== '' ? $realId : ($sim ? 'G-DEMO000000' : '');
$self   = 'demo_check.php' . ($sim ? '?sim=1' : '');

// หน้าตัวอย่างที่จะทดสอบ [ชื่อไฟล์(ไม่มี .php), ลิงก์, คำอธิบาย]
$pages = [
    ['index', 'index.php', 'หน้าแรก'], ['products', 'products.php', 'รายการสินค้า'], ['product', 'product.php?id=1', 'รายละเอียดสินค้า'],
    ['about', 'about.php', 'เกี่ยวกับเรา'], ['contact', 'contact.php', 'ติดต่อเรา'], ['faq', 'faq.php', 'คำถามที่พบบ่อย'],
    ['policy', 'policy.php?p=cookies', 'นโยบายคุกกี้'], ['cart', 'cart.php', 'ตะกร้าสินค้า'],
    ['login', 'login.php', 'เข้าสู่ระบบ'], ['account', 'account.php', 'โปรไฟล์ของฉัน'], ['pay', 'pay.php?id=1001', 'ชำระเงิน'],
    ['track', 'track.php?id=1001', 'ติดตามพัสดุ'], ['chat', 'chat.php', 'แชทกับร้าน'], ['report', 'report.php', 'แจ้งรายงาน'],
    ['privacy', 'privacy.php', 'ศูนย์ความเป็นส่วนตัว (PDPA)'], ['admin', 'admin.php', 'หลังบ้านแอดมิน'],
];
$gaCookies = array_values(array_filter(array_keys($_COOKIE), function ($n) { return preg_match('/^(_ga|_gid|_gat)/', $n); }));

head('หน้าตรวจเช็ค (โหมดทดลอง)', 'หน้าตัวอย่างสำหรับตรวจ GA4 ตามความยินยอมคุกกี้ และหน้า 404');
?>
<p class="crumb"><a href="index.php">หน้าแรก</a> › หน้าตรวจเช็ค</p>
<section class="pg-hero"><span class="e">🎓</span><h1>หน้าตรวจเช็คสำหรับครู/ผู้ตรวจ</h1>
  <p>ลองกดเลือกคุกกี้แล้วดูผลทันที · ตรวจข้อ 8 (GA4 ตามความยินยอม) และข้อ 9 (หน้า 404)</p></section>

<!-- ===== สถานะตอนนี้ ===== -->
<div class="card sect">
  <h3>1. สถานะของคุณตอนนี้</h3>
  <table class="dtab">
    <tr><th>ตัวเลือกคุกกี้</th><td><?= $choice === 'all' ? '<span class="tag ok">ยอมรับทั้งหมด</span>' : ($choice === 'necessary' ? '<span class="tag">เฉพาะที่จำเป็น</span>' : '<span class="tag st-wait">ยังไม่ได้เลือก (แบนเนอร์ด้านล่างจอ)</span>') ?></td></tr>
    <tr><th>ผู้ใช้</th><td><?= $user ? e($user['name']) . ' (' . ($user['role'] === 'admin' ? 'แอดมิน — ไม่ถูกติดตาม' : 'สมาชิก') . ')' : 'ผู้เยี่ยมชมทั่วไป (ยังไม่ล็อกอิน)' ?></td></tr>
    <tr><th>รหัส GA4 ของร้าน</th><td><?php if ($realId !== ''): ?><code><?= e($realId) ?></code> <span class="mut">(ตั้งไว้จริงในหลังบ้าน)</span>
      <?php elseif ($sim): ?><code>G-DEMO000000</code> <span class="tag st-wait">จำลอง — ไม่ได้โหลดสคริปต์จริง</span>
      <?php else: ?><span class="mut">ยังไม่ได้ตั้ง</span> · <a href="demo_check.php?sim=1">ดูแบบจำลองด้วยรหัสสมมติ</a> หรือใส่รหัสจริงที่ หลังบ้าน &gt; ข้อมูลร้าน<?php endif; ?></td></tr>
    <tr><th>คุกกี้ของ Google ในเบราว์เซอร์</th><td><?= $gaCookies ? '<span class="tag bad">พบ: ' . e(implode(', ', $gaCookies)) . '</span>' : '<span class="tag ok">ไม่พบ _ga / _gid</span>' ?></td></tr>
  </table>
  <div class="actions">
    <form method="post" action="consent.php" style="display:flex;gap:8px;flex-wrap:wrap;margin:0">
      <?= csrf() ?><input type="hidden" name="next" value="<?= e($self) ?>">
      <button name="choice" value="all">✅ ยอมรับทั้งหมด</button>
      <button name="choice" value="necessary" class="ghost">⛔ เฉพาะที่จำเป็น</button>
      <button name="choice" value="reset" class="ghost">🔄 ล้างตัวเลือก (ให้แบนเนอร์ขึ้นใหม่)</button>
    </form>
    <a class="btn ghost" href="privacy.php">เปลี่ยนที่ศูนย์ PDPA</a>
  </div>
  <p class="mut">กดปุ่ม "ล้างตัวเลือก" เพื่อเริ่มทดสอบใหม่ แบนเนอร์คุกกี้จะกลับมาที่ท้ายหน้า และคุกกี้ GA จะถูกลบ</p>
</div>

<!-- ===== ตารางผลรายหน้า ===== -->
<div class="card sect">
  <h3>2. หน้าไหนจะโหลด GA4 บ้าง (ตามสถานะข้างบน)</h3>
  <p class="sub">คำนวณด้วยฟังก์ชันเดียวกับที่ใช้จริงบนเว็บ (<code>analytics_decision</code>) กดเปลี่ยนตัวเลือกด้านบนแล้วตารางจะเปลี่ยนตาม</p>
  <table class="dtab">
    <tr><th>หน้า</th><th>ผล</th><th>เหตุผล</th></tr>
    <?php foreach ($pages as $p): [$ok, $why] = analytics_decision($p[0], $choice, $user, $id); ?>
      <tr><td><a href="<?= e($p[1]) ?>"><?= e($p[2]) ?></a> <span class="mut"><?= e($p[1]) ?></span></td>
        <td><?= $ok ? '<span class="tag ok">✅ โหลด GA4</span>' : '<span class="tag">⛔ ไม่โหลด</span>' ?></td><td class="mut"><?= e($why) ?></td></tr>
    <?php endforeach; ?>
  </table>
</div>

<!-- ===== ตรวจจริงจากหน้าเว็บ ===== -->
<div class="card sect">
  <h3>3. ตรวจของจริง (ไม่ใช่แค่ตารางจำลอง)</h3>
  <p class="sub">กดปุ่ม ระบบจะโหลดหน้าจริงแล้วค้นหาสคริปต์ <code>googletagmanager.com</code> ในหน้านั้น (ต้องตั้งรหัส GA4 จริงจึงจะเห็นว่าโหลด)</p>
  <div class="actions">
    <button type="button" class="sm" data-probe="index.php">ตรวจหน้าแรก</button>
    <button type="button" class="sm ghost" data-probe="account.php">ตรวจหน้าโปรไฟล์</button>
    <button type="button" class="sm ghost" data-probe="__no_such_page__">ตรวจ 404</button>
  </div>
  <pre id="probe" class="probe">ผลจะแสดงที่นี่</pre>
</div>

<!-- ===== 404 ===== -->
<div class="card sect">
  <h3>4. ทดสอบหน้า 404</h3>
  <p class="sub">เปิดที่อยู่ที่ไม่มีอยู่จริง ควรเห็นหน้า 404 สวยงามพร้อมเมนู (กดค้นหา/กลับหน้าแรกได้) ไม่ใช่หน้าเปล่าของ Apache</p>
  <p><a class="btn ghost sm" href="หน้าที่ไม่มีอยู่จริง" target="_blank">/หน้าที่ไม่มีอยู่จริง</a>
     <a class="btn ghost sm" href="a/b/c/d" target="_blank">/a/b/c/d (ซ้อนหลายชั้น)</a>
     <a class="btn ghost sm" href="product.php?id=99999" target="_blank">สินค้าที่ไม่มีรหัสนี้</a>
     <a class="btn ghost sm" href="404.php" target="_blank">404.php โดยตรง</a></p>
  <p class="mut">ถ้ารูปแบบหน้าเป็นหน้า Apache ธรรมดา แปลว่า <code>.htaccess</code> ไม่ได้ทำงาน: ตรวจว่าไฟล์ <code>.htaccess</code> ถูกคัดลอกมาด้วย และ XAMPP เปิด mod_rewrite + AllowOverride All</p>
</div>

<!-- ===== ขั้นตอนตรวจ ===== -->
<div class="card sect">
  <h3>5. ขั้นตอนตรวจ (ทำตามลำดับ)</h3>
  <ol class="steps2">
    <li>ล้างคุกกี้ <code>pdpa_ck</code> แล้วรีเฟรช → ต้องเห็นแบนเนอร์ มีปุ่ม <b>เฉพาะที่จำเป็น</b> และ <b>ยอมรับทั้งหมด</b> ขนาดเท่ากัน และ<b>ยังไม่มี</b>คุกกี้ _ga</li>
    <li>กด <b>เฉพาะที่จำเป็น</b> → ตารางข้อ 2 ทุกหน้า ⛔ ไม่โหลด · Network (F12) ไม่มีคำขอไป googletagmanager</li>
    <li>กด <b>ยอมรับทั้งหมด</b> → หน้าสาธารณะ ✅ โหลด · หน้าบัญชี/ชำระเงิน/แชท/PDPA ⛔ ไม่โหลด</li>
    <li>ล็อกอินเป็นแอดมิน (จากหน้าเข้าสู่ระบบ) → ทุกหน้า ⛔ ไม่ติดตามแอดมิน</li>
    <li>กลับมากด <b>เฉพาะที่จำเป็น</b> อีกครั้ง → คุกกี้ _ga ถูกลบ และสมาชิกจะมีบันทึกการถอนความยินยอมที่ ศูนย์ PDPA</li>
    <li>เปิดลิงก์ในข้อ 4 → เห็นหน้า 404 และรหัสสถานะเป็น 404 (ดูที่ ข้อ 3 ปุ่ม "ตรวจ 404")</li>
  </ol>
</div>

<style>
.dtab{width:100%;border-collapse:collapse;margin:6px 0}
.dtab th,.dtab td{border-bottom:1px solid var(--bd);padding:7px 8px;text-align:left;vertical-align:top}
.dtab th{width:34%;font-weight:600;color:var(--dark)}
.probe{background:#0f172a;color:#d1fae5;border-radius:10px;padding:12px;white-space:pre-wrap;min-height:48px;font-size:13px}
.steps2 li{margin-bottom:6px}
</style>
<script>
// ตรวจหน้าจริงด้วย fetch (ส่งคุกกี้ของคุณไปด้วย) แล้วดูว่ามีสคริปต์ GA และรหัสสถานะเป็นอะไร
document.querySelectorAll('[data-probe]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var url = btn.getAttribute('data-probe'), out = document.getElementById('probe');
    out.textContent = 'กำลังตรวจ ' + url + ' ...';
    fetch(url, {credentials: 'same-origin', redirect: 'follow'}).then(function (r) {
      return r.text().then(function (html) {
        var ga = html.indexOf('googletagmanager.com/gtag/js') !== -1;
        out.textContent = 'หน้า: ' + url + '\nรหัสสถานะ HTTP: ' + r.status + (r.status === 404 ? '  ✔ ถูกต้อง (หน้าไม่มีอยู่)' : '') +
          '\nมีสคริปต์ GA4 ในหน้า: ' + (ga ? 'มี (โหลด)' : 'ไม่มี (ไม่โหลด)') +
          '\nเป็นหน้า 404 ที่ออกแบบไว้: ' + (html.indexOf('ไม่พบหน้าที่คุณต้องการ') !== -1 ? 'ใช่' : 'ไม่ใช่') +
          '\nมีแบนเนอร์ขอคุกกี้: ' + (html.indexOf('ckbar') !== -1 ? 'มี (ยังไม่ได้เลือก)' : 'ไม่มี (เลือกแล้ว)');
      });
    }).catch(function (e) { out.textContent = 'ตรวจไม่ได้: ' + e; });
  });
});
</script>
<?php foot();
