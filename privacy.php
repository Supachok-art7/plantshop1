<?php
/**
 * privacy.php — ศูนย์ความเป็นส่วนตัว (PDPA) ของสมาชิก
 * ใช้สิทธิ์ของเจ้าของข้อมูลได้ที่นี่: ดูและเปลี่ยนความยินยอม · ดาวน์โหลดข้อมูล · แก้ไขข้อมูล · ลบบัญชี · ยื่นคำขออื่นๆ
 * ผู้เยี่ยมชมที่ยังไม่ล็อกอินเห็นเฉพาะข้อมูลทั่วไปและตัวเลือกคุกกี้
 */
require __DIR__ . '/includes/config.php';
chk();
$user = me();

/** do_delete_account() — ลบบัญชีด้วยตนเอง: ต้องยืนยันรหัสผ่าน + พิมพ์คำว่า "ลบบัญชี" + ไม่มีออเดอร์ค้างอยู่ */
function do_delete_account($user) {
    if (!password_verify($_POST['password'] ?? '', $user['pass'])) {
        flash('รหัสผ่านไม่ถูกต้อง');
    } elseif (trim($_POST['confirm'] ?? '') !== 'ลบบัญชี') {
        flash('กรุณาพิมพ์คำว่า ลบบัญชี เพื่อยืนยัน');
    } elseif ($user['role'] === 'admin') {
        flash('บัญชีแอดมินลบด้วยวิธีนี้ไม่ได้');
    } elseif (user_has_active_orders($user['id'])) {
        flash('ยังมีออเดอร์ที่ไม่เสร็จสิ้น ลบบัญชีไม่ได้ในขณะนี้ กรุณารอให้ออเดอร์เสร็จหรือยื่นคำขอลบข้อมูลแทน');
    } elseif (anonymize_user($user['id'])) {
        q('INSERT INTO dsr_requests(user_id,type,detail,status,admin_note) VALUES(?,?,?,?,?)',
          [$user['id'], 'erase', 'ลบบัญชีด้วยตนเอง', 'done', 'ระบบลบข้อมูลส่วนบุคคลและทำให้ไม่ระบุตัวตนแล้ว']);
        $_SESSION = [];
        session_destroy();
        session_start();
        flash('ลบบัญชีและข้อมูลส่วนบุคคลของคุณแล้ว');
        header('Location: index.php');
        exit;
    } else {
        flash('ลบบัญชีไม่สำเร็จ กรุณาลองใหม่หรือติดต่อร้าน');
    }
}

// ตัวรับคำสั่ง
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = need();    // ทุกคำสั่งในหน้านี้ต้องล็อกอิน (ตัวเลือกคุกกี้ไปที่ consent.php)
    switch ($_POST['a'] ?? '') {
        case 'accept':    accept_policy($user['id']); flash('บันทึกการรับทราบนโยบายแล้ว'); break;
        case 'marketing': set_marketing($user['id'], ($_POST['on'] ?? '') === '1'); flash('บันทึกการตั้งค่าข่าวสารแล้ว'); break;
        case 'dsr':       submit_dsr($user, $_POST['type'] ?? '', $_POST['detail'] ?? ''); break;
        case 'delete':    do_delete_account($user); break;
    }
    header('Location: privacy.php');
    exit;
}

$choice = cookie_choice();
if ($user) {
    $history = consent_history($user['id']);
    $myDsr   = q('SELECT * FROM dsr_requests WHERE user_id=? ORDER BY id DESC', [$user['id']])->fetchAll();
    $purposes = ['policy' => 'นโยบายความเป็นส่วนตัวและเงื่อนไข', 'marketing' => 'ข่าวสารโปรโมชั่น', 'cookies' => 'คุกกี้เสริม (' . (analytics_id() !== '' ? 'ฟอนต์ Google และสถิติการเข้าชม' : 'ฟอนต์ Google') . ')'];
}

head('ศูนย์ความเป็นส่วนตัว (PDPA)');
?>
<p class="crumb"><a href="index.php">หน้าแรก</a> › ศูนย์ความเป็นส่วนตัว (PDPA)</p>
<section class="pg-hero"><h1>🔏 ศูนย์ความเป็นส่วนตัว (PDPA)</h1>
  <p>ดูแลความยินยอมและใช้สิทธิ์เกี่ยวกับข้อมูลส่วนบุคคลของคุณได้ที่นี่ · นโยบายเวอร์ชัน <?= e(pdpa_version()) ?></p></section>

<!-- คุกกี้: ผู้เยี่ยมชมทุกคนเปลี่ยนได้ -->
<div class="card sect">
  <h3>🍪 การตั้งค่าคุกกี้</h3>
  <p class="sub">คุกกี้ที่จำเป็น (ล็อกอิน ตะกร้า ความปลอดภัย) ปิดไม่ได้ · <?= e(cookie_optional_text()) ?> เป็นตัวเลือก ตอนนี้คุณเลือก:
    <b><?= $choice === 'all' ? 'ยอมรับทั้งหมด' : ($choice === 'necessary' ? 'เฉพาะที่จำเป็น' : 'ยังไม่ได้เลือก') ?></b></p>
  <form method="post" action="consent.php" style="display:flex;gap:8px;flex-wrap:wrap">
    <?= csrf() ?><input type="hidden" name="next" value="privacy.php">
    <button name="choice" value="necessary" class="ghost">เฉพาะที่จำเป็น</button>
    <button name="choice" value="all" class="ghost">ยอมรับทั้งหมด</button>
  </form>
</div>

<?php if (!$user): ?>
  <div class="card sect">
    <h3>สิทธิของเจ้าของข้อมูล</h3>
    <p>คุณมีสิทธิขอเข้าถึง แก้ไข ลบ ระงับ คัดค้าน โอนย้ายข้อมูล และถอนความยินยอม เข้าสู่ระบบเพื่อใช้สิทธิ์ได้ทันที</p>
    <p><a class="btn" href="login.php?next=privacy.php">เข้าสู่ระบบ</a> <a class="btn ghost" href="policy.php?p=privacy">อ่านนโยบายความเป็นส่วนตัว</a></p>
  </div>
<?php else: ?>

  <!-- ความยินยอม -->
  <div class="card sect">
    <h3>✅ ความยินยอมของฉัน</h3>
    <?php if (policy_pending($user)): ?>
      <p>นโยบายความเป็นส่วนตัวมีการปรับปรุง (เวอร์ชัน <?= e(pdpa_version()) ?>) กรุณาอ่านและรับทราบ</p>
      <form method="post"><?= csrf() ?><input type="hidden" name="a" value="accept">
        <a href="policy.php?p=privacy" target="_blank">อ่านนโยบายความเป็นส่วนตัว</a> ·
        <a href="policy.php?p=terms" target="_blank">เงื่อนไขการใช้งาน</a><br>
        <button style="margin-top:8px">ฉันรับทราบและยอมรับ</button></form>
    <?php else: ?>
      <p>✔ รับทราบนโยบายความเป็นส่วนตัวและเงื่อนไขเวอร์ชัน <b><?= e($user['pdpa_version']) ?></b> แล้ว</p>
    <?php endif; ?>
    <hr style="border:0;border-top:1px solid var(--bd);margin:12px 0">
    <form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <?= csrf() ?><input type="hidden" name="a" value="marketing">
      <span>รับข่าวสารโปรโมชั่นจากร้าน: <b><?= !empty($user['marketing_opt_in']) ? 'ยินยอม' : 'ไม่ยินยอม' ?></b> <span class="mut">(ถอนได้ทุกเมื่อ ไม่กระทบการใช้บริการ)</span></span>
      <?php if (!empty($user['marketing_opt_in'])): ?>
        <button name="on" value="0" class="ghost sm">ถอนความยินยอม</button>
      <?php else: ?>
        <button name="on" value="1" class="ghost sm">ให้ความยินยอม</button>
      <?php endif; ?>
    </form>
  </div>

  <!-- ใช้สิทธิ์ -->
  <div class="card sect">
    <h3>📥 ดาวน์โหลดข้อมูลของฉัน</h3>
    <p class="sub">สำเนาข้อมูลที่ร้านเก็บเกี่ยวกับคุณ (โปรไฟล์ ออเดอร์ รีวิว แชท รายงาน ความยินยอม) ในรูปแบบไฟล์ JSON</p>
    <form method="post" action="export.php"><?= csrf() ?><button>ดาวน์โหลดข้อมูล (.json)</button></form>
  </div>

  <div class="card sect">
    <h3>✏️ แก้ไขข้อมูลให้ถูกต้อง</h3>
    <p class="sub">แก้ข้อมูลส่วนตัวและที่อยู่ได้เองทันที <a href="account.php?t=profile">ไปที่หน้าโปรไฟล์</a> · ข้อมูลในออเดอร์เก่าที่ต้องแก้ ให้ยื่นคำขอด้านล่าง</p>
  </div>

  <form method="post" class="card sect">
    <h3>📨 ยื่นคำขอใช้สิทธิ์</h3>
    <p class="sub">ร้านจะตอบกลับภายใน <?= DSR_DAYS ?> วัน (หากปฏิเสธจะแจ้งเหตุผล)</p>
    <?= csrf() ?><input type="hidden" name="a" value="dsr">
    <div class="form">
      <select name="type" required class="full">
        <option value="">— เลือกประเภทคำขอ —</option>
        <?php foreach (DSR_TYPES as $k => $label): if ($k === 'access') { continue; } ?>
          <option value="<?= $k ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <textarea class="full" name="detail" rows="4" maxlength="1500" placeholder="อธิบายสิ่งที่ต้องการ" required></textarea>
    </div>
    <button>ส่งคำขอ</button>
  </form>

  <?php if ($myDsr): ?>
    <h3>คำขอของฉัน</h3>
    <?php foreach ($myDsr as $r): ?>
      <div class="card">
        <b><?= e(DSR_TYPES[$r['type']] ?? $r['type']) ?></b>
        <span class="tag"><?= e(DSR_STATUS[$r['status']] ?? $r['status']) ?></span>
        <span class="mut"><?= e($r['created']) ?><?= in_array($r['status'], ['new', 'working']) ? ' · ครบกำหนด ' . e(dsr_due($r['created'])) : '' ?></span>
        <p><?= nl2br(e($r['detail'])) ?></p>
        <?php if ($r['admin_note']): ?><div class="reply"><b>ตอบกลับจากร้าน:</b> <?= nl2br(e($r['admin_note'])) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- ประวัติความยินยอม -->
  <div class="card sect">
    <h3>🕘 ประวัติความยินยอมของฉัน</h3>
    <div class="scroll"><table>
      <tr><th>วันที่</th><th>เรื่อง</th><th>เวอร์ชัน</th><th>ผล</th></tr>
      <?php foreach ($history as $h): ?>
        <tr><td><?= e($h['created']) ?></td><td><?= e($purposes[$h['purpose']] ?? $h['purpose']) ?></td><td><?= e($h['version']) ?></td>
            <td><?= $h['granted'] ? '✔ ให้ความยินยอม' : '✖ ถอน/ไม่ยินยอม' ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$history): ?><tr><td colspan="4" class="mut">ยังไม่มีบันทึก</td></tr><?php endif; ?>
    </table></div>
  </div>

  <!-- ลบบัญชี -->
  <?php if ($user['role'] !== 'admin'): ?>
  <form method="post" class="card sect danger-zone" onsubmit="return confirm('ลบบัญชีและข้อมูลส่วนบุคคลของคุณ การกระทำนี้ย้อนกลับไม่ได้ ยืนยันหรือไม่?')">
    <h3>🗑 ลบบัญชีและข้อมูลส่วนบุคคล</h3>
    <p class="sub">จะลบ: รูปโปรไฟล์ เอกสารยืนยันตัวตน รีวิว แชท รายงาน และทำให้ชื่อ อีเมล เบอร์ ที่อยู่ไม่สามารถระบุตัวคุณได้<br>
      <b>ที่ยังต้องเก็บ:</b> ข้อมูลออเดอร์และหลักฐานการชำระเงินตามกฎหมายบัญชีและภาษี เก็บไว้ตามระยะเวลาที่กฎหมายกำหนดแล้วจึงลบหรือทำให้ไม่ระบุตัวตน</p>
    <?= csrf() ?><input type="hidden" name="a" value="delete">
    <div class="form">
      <input type="password" name="password" placeholder="รหัสผ่านปัจจุบัน" autocomplete="current-password" required>
      <input name="confirm" placeholder="พิมพ์คำว่า ลบบัญชี" required>
    </div>
    <button class="del">ลบบัญชีของฉัน</button>
  </form>
  <?php endif; ?>

<?php endif; ?>
<?php foot();
