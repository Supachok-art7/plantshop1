<?php
/**
 * account.php — โปรไฟล์ของฉัน (ต้องล็อกอิน) หัวโปรไฟล์ + สถิติ + ความสมบูรณ์ + 4 แท็บ:  account.php?t=...
 *   profile  โปรไฟล์ละเอียด: รูป ชื่อ เบอร์ วันเกิด เพศ ที่อยู่แยกช่อง หมุดบ้านบนแผนที่ (ใช้ตำแหน่งปัจจุบันได้)
 *   verify   ยืนยันตัวตน: ส่งรูปบัตร + รูปเซลฟี่ ให้แอดมินตรวจ (เก็บในโฟลเดอร์ private และลบทิ้งหลังตรวจเสร็จ)
 *   security เปลี่ยนรหัสผ่าน
 *   orders   ประวัติสั่งซื้อ + ลิงก์ติดตามพัสดุ
 */
require __DIR__ . '/includes/config.php';
chk();
$user = need();
$tab  = $_GET['t'] ?? 'profile';

/* ============================================================
 * ส่วน A: ฟังก์ชันรับคำสั่งจากฟอร์ม
 * ============================================================ */

/** save_profile() — บันทึกข้อมูลส่วนตัว + ที่อยู่ (ที่อยู่รวมถูกประกอบอัตโนมัติไว้ใช้ตอนสั่งซื้อ) */
function save_profile($user) {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        flash('กรุณากรอกชื่อ');
        return;
    }
    // วันเกิด: ต้องเป็นวันที่จริงและไม่เกินวันนี้ ไม่ผ่านให้เป็นค่าว่าง
    $birth = trim($_POST['birthdate'] ?? '');
    $date  = DateTime::createFromFormat('Y-m-d', $birth);
    if (!$date || $date->format('Y-m-d') !== $birth || $date > new DateTime()) {
        $birth = null;
    }
    $gender = $_POST['gender'] ?? '';
    if (!in_array($gender, ['male', 'female', 'other'])) {
        $gender = null;
    }

    $line = trim($_POST['addr_line'] ?? '');
    $sub  = trim($_POST['subdistrict'] ?? '');
    $dist = trim($_POST['district'] ?? '');
    $prov = trim($_POST['province'] ?? '');
    $post = trim($_POST['postcode'] ?? '');
    if (!preg_match('/^\d{5}$/', $post)) {
        $post = '';   // รหัสไปรษณีย์ต้องเป็นตัวเลข 5 หลัก
    }
    // ประกอบที่อยู่รวม เช่น "99 ถ.สุขุมวิท ต.คลองเตย อ.คลองเตย จ.กรุงเทพฯ 10110"
    $parts = [$line, $sub ? 'ต.' . $sub : '', $dist ? 'อ.' . $dist : '', $prov ? 'จ.' . $prov : '', $post];
    $addr  = trim(implode(' ', array_filter($parts)));
    if ($addr === '') {
        $addr = $user['addr'];   // ไม่ได้กรอกช่องแยกเลย = คงที่อยู่เดิมไว้
    }

    // หมุดบ้าน (ไม่บังคับ)
    $lat = is_numeric($_POST['lat'] ?? '') ? (float)$_POST['lat'] : null;
    $lng = is_numeric($_POST['lng'] ?? '') ? (float)$_POST['lng'] : null;
    if ($lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180) {
        $lat = null;
        $lng = null;
    }

    q('UPDATE users SET name=?, phone=?, birthdate=?, gender=?, addr_line=?, subdistrict=?, district=?,
         province=?, postcode=?, addr=?, lat=?, lng=? WHERE id=?',
      [$name, trim($_POST['phone'] ?? ''), $birth, $gender, $line, $sub, $dist, $prov, $post, $addr, $lat, $lng, $user['id']]);
    flash('บันทึกโปรไฟล์แล้ว');
}

/** save_avatar() — เปลี่ยนรูปโปรไฟล์ (JPG/PNG/WEBP ไม่เกิน 3MB) และลบรูปเก่า */
function save_avatar($user) {
    $new = save_img($_FILES['avatar'] ?? null, 'av');
    if (!$new) {
        flash('รูปต้องเป็น JPG/PNG/WEBP ขนาดไม่เกิน 3MB');
        return;
    }
    if ($user['avatar']) {
        @unlink(ROOT_DIR . '/uploads/' . basename($user['avatar']));
    }
    q('UPDATE users SET avatar=? WHERE id=?', [$new, $user['id']]);
    flash('เปลี่ยนรูปโปรไฟล์แล้ว');
}

/** delete_avatar() — ลบรูปโปรไฟล์ (กลับไปใช้ตัวอักษรแรกของชื่อ) */
function delete_avatar($user) {
    if ($user['avatar']) {
        @unlink(ROOT_DIR . '/uploads/' . basename($user['avatar']));
    }
    q('UPDATE users SET avatar=NULL WHERE id=?', [$user['id']]);
}

/**
 * submit_verify() — ส่งเอกสารยืนยันตัวตน (รูปบัตร + รูปเซลฟี่)
 * ส่งได้เฉพาะสถานะ none / rejected ไฟล์เก็บใน private/ (เปิดดูได้เฉพาะแอดมินผ่าน file.php)
 */
function submit_verify($user) {
    if (!in_array($user['verify_status'], ['none', 'rejected', '', null])) {
        flash('ส่งเอกสารไม่ได้ในสถานะปัจจุบัน');
        return;
    }
    if (empty($_POST['consent'])) {
        flash('กรุณาติ๊กยินยอมให้ตรวจสอบเอกสาร');
        return;
    }
    $idImg = save_img($_FILES['id_img'] ?? null, 'id', 'private');
    $self  = save_img($_FILES['selfie'] ?? null, 'self', 'private');
    if (!$idImg || !$self) {
        // อัปโหลดไม่ครบ: ลบไฟล์ที่เพิ่งบันทึกทิ้ง ไม่ให้ค้างในเซิร์ฟเวอร์
        foreach ([$idImg, $self] as $f) {
            if ($f) {
                @unlink(ROOT_DIR . '/private/' . $f);
            }
        }
        flash('ต้องแนบรูปบัตรและรูปเซลฟี่ (JPG/PNG/WEBP ไม่เกิน 3MB)');
        return;
    }
    q("UPDATE users SET verify_status='pending', verify_note=NULL, id_img=?, selfie_img=? WHERE id=?", [$idImg, $self, $user['id']]);
    flash('ส่งเอกสารแล้ว รอแอดมินตรวจสอบ');
}

/** change_password() — เปลี่ยนรหัสผ่าน (ต้องใส่รหัสเดิมก่อน) */
function change_password($user) {
    $old = $_POST['old'] ?? '';
    $new = $_POST['new'] ?? '';
    if (!password_verify($old, $user['pass'])) {
        flash('รหัสผ่านเดิมไม่ถูกต้อง');
    } elseif (strlen($new) < 6) {
        flash('รหัสผ่านใหม่ต้องยาวอย่างน้อย 6 ตัว');
    } elseif ($new !== ($_POST['new2'] ?? '')) {
        flash('รหัสผ่านใหม่ทั้งสองช่องไม่ตรงกัน');
    } else {
        q('UPDATE users SET pass=? WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        flash('เปลี่ยนรหัสผ่านแล้ว');
    }
}

// ตัวรับคำสั่ง: ทำงานตามค่า a ของฟอร์ม แล้วพากลับแท็บเดิม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['a'] ?? '') {
        case 'profile':    save_profile($user);    break;
        case 'avatar':     save_avatar($user);     break;
        case 'avatar_del': delete_avatar($user);   break;
        case 'verify':     submit_verify($user);   break;
        case 'password':   change_password($user); break;
    }
    header('Location: account.php?t=' . urlencode($tab));
    exit;
}

/* ============================================================
 * ส่วน B: เตรียมข้อมูลแสดงผล
 * ============================================================ */
$vs   = $user['verify_status'] ?: 'none';
$tabs = ['profile' => ['👤', 'ข้อมูลส่วนตัวและที่อยู่'], 'verify' => ['🛡', 'ยืนยันตัวตน'], 'security' => ['🔑', 'รหัสผ่านและความปลอดภัย'], 'tree' => ['🌳', 'ต้นไม้สะสมแต้ม'], 'orders' => ['📦', 'ประวัติสั่งซื้อ']];
if (!isset($tabs[$tab])) {
    $tab = 'profile';
}

// สถิติบนหัวโปรไฟล์
$summary = q('SELECT COUNT(*) AS cnt, COALESCE(SUM(CASE WHEN status IN (' . PAID_SQL . ') THEN total END),0) AS spent FROM orders WHERE user_id=?', [$user['id']])->fetch();
$reviewCount = (int)q('SELECT COUNT(*) FROM reviews WHERE user_id=?', [$user['id']])->fetchColumn();
$reportCount = 0;
try {
    $reportCount = (int)q('SELECT COUNT(*) FROM reports WHERE user_id=?', [$user['id']])->fetchColumn();
} catch (Throwable $err) {
    // ยังไม่ได้รัน update_v4.sql
}
$badge = badge_counts();

// ความสมบูรณ์ของโปรไฟล์: รายการที่ยังไม่ครบจะแสดงเป็นป้ายเหลืองให้กดไปกรอก
$checks = [
    ['รูปโปรไฟล์',      !empty($user['avatar']),                                                   'profile'],
    ['เบอร์โทร',        trim((string)$user['phone']) !== '',                                      'profile'],
    ['วันเกิด',          !empty($user['birthdate']),                                               'profile'],
    ['เพศ',             !empty($user['gender']),                                                  'profile'],
    ['ที่อยู่จัดส่งครบ', !empty($user['addr_line']) && !empty($user['province']) && !empty($user['postcode']), 'profile'],
    ['หมุดบ้านบนแผนที่', $user['lat'] !== null,                                                    'profile'],
    ['ยืนยันตัวตน',     $vs === 'verified',                                                       'verify'],
];
$doneCount = count(array_filter(array_column($checks, 1)));
$percent   = (int)round($doneCount * 100 / count($checks));

$tree = tree_info($user['id']);
$orders = [];
$itemsByOrder = [];
if ($tab === 'orders') {
    $orders = q('SELECT * FROM orders WHERE user_id=? ORDER BY id DESC', [$user['id']])->fetchAll();
    foreach (q('SELECT i.* FROM order_items i JOIN orders o ON o.id=i.order_id WHERE o.user_id=?', [$user['id']])->fetchAll() as $it) {
        $itemsByOrder[$it['order_id']][] = $it;
    }
}
$stepOf = ['รอชำระเงิน' => 1, 'แจ้งชำระแล้ว' => 1, 'ชำระแล้ว' => 2, 'เก็บเงินปลายทาง' => 2, 'เตรียมสินค้า' => 3, 'จัดส่งแล้ว' => 4, 'ได้รับสินค้าแล้ว' => 5];

head('โปรไฟล์ของฉัน');
?>
<!-- ===== หัวโปรไฟล์: ภาพปก + รูป + ชื่อ + ป้าย + สถิติ ===== -->
<section class="hero">
  <div class="cover"></div>
  <div class="hero-body">
    <div class="avwrap">
      <?= avatar($user, 104) ?>
      <!-- กดไอคอนกล้อง เลือกรูป แล้วอัปโหลดทันที -->
      <form method="post" action="account.php?t=<?= e($tab) ?>" enctype="multipart/form-data" style="margin:0">
        <?= csrf() ?><input type="hidden" name="a" value="avatar">
        <label class="cam" title="เปลี่ยนรูปโปรไฟล์">📷<input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" onchange="this.form.submit()"></label>
      </form>
    </div>
    <div class="hero-info">
      <h1><?= e($user['name']) ?> <?= verified_badge($user) ?></h1>
      <div class="meta">
        <span>✉️ <?= e($user['email']) ?></span>
        <?php if ($user['phone']): ?><span>📞 <?= e($user['phone']) ?></span><?php endif; ?>
        <span>🗓 สมาชิกตั้งแต่ <?= thai_date($user['created']) ?></span>
        <?php if ($user['role'] === 'admin'): ?><span class="tag">ผู้ดูแลร้าน</span><?php endif; ?>
      </div>
    </div>
    <div class="hero-act">
      <?php if ($user['role'] === 'admin'): ?><a class="btn sm" href="admin.php">🛠 หลังบ้าน</a><?php endif; ?>
      <?php if ($user['avatar']): ?>
        <form method="post" action="account.php?t=<?= e($tab) ?>" style="margin:0" onsubmit="return confirm('ลบรูปโปรไฟล์?')">
          <?= csrf() ?><input type="hidden" name="a" value="avatar_del"><button class="ghost sm">ลบรูป</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <div class="kpis">
    <a href="account.php?t=orders"><b><?= (int)$summary['cnt'] ?></b><span>คำสั่งซื้อ</span></a>
    <a href="account.php?t=orders"><b><?= baht($summary['spent']) ?></b><span>ยอดซื้อสะสม</span></a>
    <a href="account.php?t=tree"><b>🌳 <?= e($tree['name']) ?></b><span>ต้นไม้ของฉัน</span></a>
    <a href="index.php"><b><?= $reviewCount ?></b><span>รีวิวของฉัน</span></a>
    <a href="privacy.php"><span>🔏 ความเป็นส่วนตัว (PDPA)</span></a>
    <a href="report.php?t=mine"><b><?= $reportCount ?></b><span>เรื่องที่แจ้งแอดมิน<?= nbadge($badge['my_reports']) ?></span></a>
  </div>
</section>

<div class="acc-layout">
  <!-- ===== เมนูด้านข้าง ===== -->
  <aside class="side" aria-label="เมนูบัญชี">
    <?php foreach ($tabs as $key => $t): ?>
      <a class="<?= $tab === $key ? 'on' : '' ?>" href="account.php?t=<?= $key ?>"><span><?= $t[0] ?> <?= $t[1] ?></span>
        <?php if ($key === 'verify' && $vs === 'verified'): ?>✔<?php elseif ($key === 'verify' && $vs === 'pending'): ?>⏳<?php endif; ?></a>
    <?php endforeach; ?>
    <a href="report.php?t=mine"><span>🚩 รายงานของฉัน</span><?= nbadge($badge['my_reports']) ?></a>
    <a href="chat.php"><span>💬 แชทกับร้าน</span><?= nbadge($badge['my_chat']) ?></a>
    <form method="post" action="logout.php"><?= csrf() ?><button class="linkbtn">↩ ออกจากระบบ</button></form>
  </aside>

  <div>
  <?php if ($tab === 'profile'): ?>

    <?php if ($percent < 100): ?>
      <div class="card sect">
        <b>โปรไฟล์สมบูรณ์ <?= $percent ?>%</b> <span class="mut">— กรอกให้ครบเพื่อสั่งซื้อได้เร็วขึ้นและน่าเชื่อถือขึ้น</span>
        <div class="meter" role="progressbar" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"><i style="width:<?= $percent ?>%"></i></div>
        <div class="todo">
          <?php foreach ($checks as $c): if (!$c[1]): ?><a href="account.php?t=<?= $c[2] ?>">+ <?= e($c[0]) ?></a><?php endif; endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <form method="post" action="account.php?t=profile" class="card sect">
      <?= csrf() ?><input type="hidden" name="a" value="profile">
      <h3>👤 ข้อมูลส่วนตัว</h3>
      <p class="sub">อีเมลใช้เข้าสู่ระบบ เปลี่ยนไม่ได้</p>
      <div class="form">
        <label class="field"><span class="lbl">ชื่อ-นามสกุล</span><input name="name" value="<?= e($user['name']) ?>" placeholder="ชื่อ-นามสกุล" required></label>
        <label class="field"><span class="lbl">อีเมล</span><input value="<?= e($user['email']) ?>" disabled></label>
        <label class="field"><span class="lbl">เบอร์โทร</span><input name="phone" value="<?= e($user['phone']) ?>" placeholder="เช่น 0812345678" inputmode="tel"></label>
        <label class="field"><span class="lbl">วันเกิด</span><input type="date" name="birthdate" value="<?= e($user['birthdate']) ?>" max="<?= date('Y-m-d') ?>"></label>
        <label class="field"><span class="lbl">เพศ</span>
          <select name="gender">
            <option value="">ไม่ระบุ</option>
            <option value="male"<?= $user['gender'] === 'male' ? ' selected' : '' ?>>ชาย</option>
            <option value="female"<?= $user['gender'] === 'female' ? ' selected' : '' ?>>หญิง</option>
            <option value="other"<?= $user['gender'] === 'other' ? ' selected' : '' ?>>อื่นๆ</option>
          </select></label>
      </div>

      <h3 style="margin-top:20px">🏡 ที่อยู่จัดส่ง</h3>
      <p class="sub">ระบบจะใช้ที่อยู่นี้และหมุดบนแผนที่เป็นค่าเริ่มต้นตอนสั่งซื้อ</p>
      <?php if ($user['addr']): ?>
        <div class="addr-card"><span style="font-size:22px">📍</span><div><b>ที่อยู่ปัจจุบัน</b><br><span class="mut"><?= e($user['addr']) ?></span></div></div>
      <?php endif; ?>
      <div class="form">
        <label class="field full"><span class="lbl">บ้านเลขที่ ซอย ถนน หมู่บ้าน</span><input name="addr_line" value="<?= e($user['addr_line']) ?>" placeholder="เช่น 99 ซอยสุขุมวิท 21 ถนนสุขุมวิท"></label>
        <label class="field"><span class="lbl">ตำบล/แขวง</span><input name="subdistrict" value="<?= e($user['subdistrict']) ?>"></label>
        <label class="field"><span class="lbl">อำเภอ/เขต</span><input name="district" value="<?= e($user['district']) ?>"></label>
        <label class="field"><span class="lbl">จังหวัด</span><input name="province" value="<?= e($user['province']) ?>"></label>
        <label class="field"><span class="lbl">รหัสไปรษณีย์ (5 หลัก)</span><input name="postcode" value="<?= e($user['postcode']) ?>" inputmode="numeric" maxlength="5" pattern="\d{5}"></label>
      </div>
      <?php if ($user['addr'] && !$user['addr_line']): ?><p class="mut">ที่อยู่เดิมยังเป็นแบบช่องเดียว กรอกช่องด้านบนเพื่อแยกเป็นช่องละเอียด</p><?php endif; ?>

      <div class="mapbar">
        <b>📍 หมุดบ้านบนแผนที่</b>
        <label class="autopin"><input type="checkbox" id="autopin"> ปักหมุดอัตโนมัติตามที่อยู่</label>
        <button type="button" class="ghost sm" id="findpin">🔎 หาพิกัดจากที่อยู่</button>
        <button type="button" class="ghost sm" id="locate">🎯 ใช้ตำแหน่งปัจจุบัน</button>
        <button type="button" class="ghost sm" id="clearpin">ล้างหมุด</button>
        <span class="mut" id="pinstat"></span>
      </div>
      <div id="homemap" style="height:280px;border-radius:12px;border:1px solid var(--bd)"></div>
      <input type="hidden" name="lat" id="plat" value="<?= e($user['lat']) ?>">
      <input type="hidden" name="lng" id="plng" value="<?= e($user['lng']) ?>">
      <div class="actions"><button>💾 บันทึกโปรไฟล์</button></div>
    </form>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
    <script src="assets/js/geo.js"></script>
    <script>
    // แผนที่ปักหมุดบ้าน: พิมพ์ที่อยู่แล้วระบบหาพิกัดและปักหมุดให้เอง (geo.js) · ลากหมุดหรือแตะแผนที่เพื่อปรับเองได้
    var saved = <?= json_encode($user['lat'] !== null ? [(float)$user['lat'], (float)$user['lng']] : null) ?>;
    var map = L.map('homemap').setView(saved || [13.7563, 100.5018], saved ? 15 : 10);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '© OpenStreetMap'}).addTo(map);
    var fields = ['addr_line', 'subdistrict', 'district', 'province', 'postcode'].map(function (n) { return document.querySelector('[name=' + n + ']'); });
    var autoEl = document.getElementById('autopin');
    autoEl.checked = !saved;      // มีหมุดที่บันทึกไว้แล้ว = ไม่ทับอัตโนมัติ จนกว่าผู้ใช้จะติ๊กเอง
    var pin = AddressPin.init({
      map: map, latEl: document.getElementById('plat'), lngEl: document.getElementById('plng'),
      statusEl: document.getElementById('pinstat'), autoEl: autoEl, findBtn: document.getElementById('findpin'),
      fields: fields, initial: saved, runNow: false,
      getAddress: function () { return fields.map(function (f) { return f.value.trim(); }).filter(Boolean).join(' '); }
    });
    document.getElementById('clearpin').onclick = function () { pin.clear(); };
    document.getElementById('locate').onclick = function () {
      var stat = document.getElementById('pinstat');
      if (!navigator.geolocation) { stat.textContent = 'เบราว์เซอร์นี้ไม่รองรับการระบุตำแหน่ง'; return; }
      stat.textContent = 'กำลังหาตำแหน่ง...';
      navigator.geolocation.getCurrentPosition(function (pos) {
        pin.manual(pos.coords.latitude, pos.coords.longitude, 'ใช้ตำแหน่งปัจจุบันแล้ว');
      }, function () { stat.textContent = 'ใช้ตำแหน่งปัจจุบันไม่ได้'; });
    };
    </script>

  <?php elseif ($tab === 'verify'): ?>
    <div class="card sect">
      <h3>🛡 ยืนยันตัวตน</h3>
      <?php if ($vs === 'verified'): ?>
        <p><span class="tag ok">✔ ยืนยันตัวตนแล้ว</span> ป้ายนี้แสดงข้างชื่อของคุณในรีวิวและโปรไฟล์</p>
      <?php elseif ($vs === 'pending'): ?>
        <p><span class="tag rs-working">⏳ รอแอดมินตรวจสอบ</span> ส่งเอกสารแล้ว โดยปกติตรวจภายใน 1–2 วันทำการ</p>
      <?php else: ?>
        <?php if ($vs === 'rejected'): ?>
          <p><span class="tag bad">ไม่ผ่านการตรวจสอบ</span> <?= e($user['verify_note']) ?></p>
        <?php endif; ?>
        <p class="sub">รับป้าย "ยืนยันตัวตนแล้ว" เพื่อเพิ่มความน่าเชื่อถือ เอกสารเก็บในที่เปิดสาธารณะไม่ได้ แอดมินเท่านั้นที่เห็น
          และจะถูก<b>ลบทิ้งทันทีหลังตรวจเสร็จ</b> ระบบเก็บเฉพาะผลการตรวจ (คุณปิดบังข้อมูลที่ไม่เกี่ยวข้อง เช่น เลขหลังบัตร ได้)</p>
        <form method="post" action="account.php?t=verify" enctype="multipart/form-data">
          <?= csrf() ?><input type="hidden" name="a" value="verify">
          <div class="vstep"><i>1</i><div><b>รูปบัตรประชาชน</b><br><input type="file" name="id_img" accept="image/*" required></div></div>
          <div class="vstep"><i>2</i><div><b>รูปเซลฟี่ถือบัตร</b><br><input type="file" name="selfie" accept="image/*" required></div></div>
          <div class="vstep"><i>3</i><div><label><input type="checkbox" name="consent" value="1" style="width:auto"> ยินยอมให้ร้านตรวจสอบเอกสารเพื่อยืนยันตัวตน</label></div></div>
          <button>ส่งเอกสาร</button>
        </form>
      <?php endif; ?>
    </div>

  <?php elseif ($tab === 'security'): ?>
    <form method="post" action="account.php?t=security" class="card sect" style="max-width:480px">
      <?= csrf() ?><input type="hidden" name="a" value="password">
      <h3>🔑 เปลี่ยนรหัสผ่าน</h3>
      <p class="sub">ใช้รหัสผ่านที่เดายากและไม่ซ้ำกับเว็บอื่น</p>
      <p><input type="password" name="old" placeholder="รหัสผ่านเดิม" autocomplete="current-password" required></p>
      <p><input type="password" name="new" placeholder="รหัสผ่านใหม่ (อย่างน้อย 6 ตัว)" autocomplete="new-password" required></p>
      <p><input type="password" name="new2" placeholder="ยืนยันรหัสผ่านใหม่" autocomplete="new-password" required></p>
      <button>เปลี่ยนรหัสผ่าน</button>
    </form>

  <?php elseif ($tab === 'tree'): ?>
    <section class="card sect">
      <h2>🌳 ต้นไม้สะสมแต้มของฉัน</h2>
      <div class="treebox">
        <div class="tcard"><?= tree_3d($tree['level']) ?></div>
        <div>
          <div class="tpoint"><?= (int)$tree['points'] ?> <small style="font-size:15px;font-weight:600;color:#5b7a66">/ <?= TREE_MAX_POINTS ?> แต้ม</small></div>
          <p style="margin:4px 0"><b><?= e($tree['name']) ?></b> <span class="mut">· <?= e($tree['desc']) ?></span></p>
          <?php if (!$tree['max']): ?>
            <div class="tprog" title="ความคืบหน้าไปสู่ระดับถัดไป"><i style="width:<?= (int)$tree['progress'] ?>%"></i></div>
            <p class="mut" style="margin:0">ซื้ออีก <b><?= baht($tree['to_next_baht']) ?></b> ต้นไม้จะโตเป็น "<?= e($tree['next_name']) ?>"</p>
          <?php else: ?>
            <p style="margin:6px 0"><b>ต้นไม้โตเต็มที่แล้ว 🎉</b> <span class="mut">ขอบคุณที่อุดหนุนร้านเสมอมา</span></p>
          <?php endif; ?>
          <p class="mut" style="font-size:12.5px;margin:10px 0 0">ทุกยอดซื้อที่ชำระแล้ว <?= TREE_BAHT_PER_POINT ?> บาท = 1 แต้ม (ไม่นับค่าส่ง/ภาษี และหักส่วนลดแล้ว) · แต้มสูงสุด <?= TREE_MAX_POINTS ?> แต้ม ไม่สะสมเป็นหลักร้อยหลักพัน · ต้นไม้โตขึ้นตามระดับด้านล่าง</p>
        </div>
      </div>
      <ul class="tlv">
        <?php foreach (TREE_LEVELS as $lv => $d): ?>
          <li class="<?= $lv < $tree['level'] ? 'done' : ($lv === $tree['level'] ? 'cur done' : 'lock') ?>">
            <span><?= $lv < $tree['level'] || $lv === $tree['level'] ? '✅' : '🔒' ?></span>
            <span><b><?= e($d[1]) ?></b> <span class="mut"><?= e($d[2]) ?></span></span>
            <span class="pts"><?= $d[0] ?> แต้ม</span>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php
        $perks = [];
        try {
            $perks = q("SELECT * FROM coupons WHERE active=1 AND min_level>0 AND (expires IS NULL OR expires >= ?) ORDER BY min_level", [date('Y-m-d')])->fetchAll();
        } catch (Throwable $err) {
        }
      ?>
      <?php if ($perks): ?>
        <h3>🎁 สิทธิ์พิเศษตามระดับต้นไม้</h3>
        <?php foreach ($perks as $pk): $ok = $tree['level'] >= (int)$pk['min_level']; ?>
          <div class="card" style="margin:6px 0">
            <?php if ($ok): ?><span class="tag ok"><?= e($pk['code']) ?></span> <?= e(coupon_label($pk)) ?> <a class="btn sm" href="cart.php">ไปใช้ในตะกร้า</a>
            <?php else: ?>🔒 ปลดล็อกเมื่อต้นไม้โตเป็น "<?= e(TREE_LEVELS[(int)$pk['min_level']][1]) ?>" · <?= e(coupon_label($pk)) ?><?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

  <?php else: ?>
    <?php foreach ($orders as $o): $its = $itemsByOrder[$o['id']] ?? []; $step = $stepOf[$o['status']] ?? 0; ?>
      <div class="card ord">
        <div class="ord-top">
          <span><b>#<?= $o['id'] ?></b> <span class="mut"><?= thai_date($o['created']) ?></span></span>
          <?= status_tag($o['status']) ?>
        </div>
        <div class="ord-items"><?php foreach ($its as $it): ?><span><?= e($it['name']) ?> ×<?= (int)$it['qty'] ?></span><?php endforeach; ?></div>
        <?php if ($o['status'] !== 'ยกเลิก'): ?>
          <div class="ord-bar" title="ความคืบหน้า"><?php for ($i = 1; $i <= 5; $i++): ?><i class="<?= $i <= $step ? 'f' : '' ?>"></i><?php endfor; ?></div>
        <?php endif; ?>
        <div class="ord-top">
          <b class="price"><?= baht2($o['total']) ?></b><?php if (!empty($o['coupon'])): ?> <span class="tag ok">🎟 <?= e($o['coupon']) ?></span><?php endif; ?>
          <span>
            <a class="btn sm" href="track.php?id=<?= $o['id'] ?>">📍 ติดตาม</a>
            <a class="btn ghost sm" href="pay.php?id=<?= $o['id'] ?>">ชำระเงิน/รายละเอียด</a>
            <a class="btn ghost sm" href="report.php?type=order&id=<?= $o['id'] ?>">🚩 แจ้งปัญหา</a>
          </span>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$orders): ?>
      <div class="card empty"><div class="big-emoji">🛒</div><p class="mut">ยังไม่มีคำสั่งซื้อ</p><a class="btn" href="products.php">เลือกซื้อสินค้า</a></div>
    <?php endif; ?>
  <?php endif; ?>
  </div>
</div>

<?php foot();
