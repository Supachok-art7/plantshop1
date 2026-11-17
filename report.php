<?php
/**
 * report.php — แจ้งรายงานถึงแอดมิน (ต้องล็อกอิน)
 *   report.php                      ฟอร์มแจ้งเรื่องใหม่
 *   report.php?type=order&id=1001   เปิดฟอร์มพร้อมระบุเรื่อง/สิ่งที่เกี่ยวข้องให้ (ลิงก์มาจากหน้าออเดอร์ สินค้า รีวิว)
 *   report.php?t=mine               รายงานของฉัน + คำตอบจากแอดมิน
 * รายงานเก็บในตาราง reports  แอดมินดูและตอบที่ admin.php?t=reports
 */
require __DIR__ . '/includes/config.php';
chk();
$user = need();
if ($user['role'] === 'admin') {            // แอดมินไม่ต้องแจ้งตัวเอง พาไปหน้าจัดการรายงาน
    header('Location: admin.php?t=reports');
    exit;
}

const REPORT_LIMIT_PER_DAY = 5;             // กันสแปม: แจ้งได้วันละไม่เกินกี่เรื่อง

$view   = (($_GET['t'] ?? '') === 'mine') ? 'mine' : 'new';
$error  = '';
$tableOk = true;
try {
    q('SELECT 1 FROM reports LIMIT 1');
} catch (Throwable $err) {
    $tableOk = false;                       // ยังไม่ได้นำเข้า update_v4.sql
}

/**
 * submit_report() — ตรวจข้อมูลแล้วบันทึกรายงานใหม่ คืนข้อความ error (ว่าง = สำเร็จ)
 * ตรวจ: หัวข้อ/รายละเอียดไม่ว่าง ความยาว, สิ่งที่อ้างถึงต้องมีจริงและเกี่ยวกับผู้แจ้ง, จำกัดจำนวนต่อวัน, รูปประกอบ (ไม่บังคับ)
 */
function submit_report($user) {
    $type    = $_POST['type'] ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $body    = trim($_POST['body'] ?? '');
    if (!isset(REPORT_TYPES[$type])) {
        return 'กรุณาเลือกหัวข้อที่ต้องการแจ้ง';
    }
    if (mb_strlen($subject) < 3 || mb_strlen($subject) > 150) {
        return 'หัวเรื่องต้องยาว 3–150 ตัวอักษร';
    }
    if (mb_strlen($body) < 10 || mb_strlen($body) > 2000) {
        return 'รายละเอียดต้องยาว 10–2000 ตัวอักษร เพื่อให้แอดมินเข้าใจปัญหา';
    }

    // สิ่งที่เกี่ยวข้อง: ออเดอร์ต้องเป็นของผู้แจ้งเอง / สินค้าและรีวิวต้องมีอยู่จริง
    $target = null;
    if ($type === 'order') {
        $target = (int)($_POST['t_order'] ?? 0) ?: null;
        if ($target && !q('SELECT 1 FROM orders WHERE id=? AND user_id=?', [$target, $user['id']])->fetch()) {
            return 'ไม่พบออเดอร์ที่เลือก';
        }
    } elseif ($type === 'product') {
        $target = (int)($_POST['t_product'] ?? 0) ?: null;
        if ($target && !q('SELECT 1 FROM products WHERE id=?', [$target])->fetch()) {
            return 'ไม่พบสินค้าที่เลือก';
        }
    } elseif ($type === 'review') {
        $target = (int)($_POST['t_review'] ?? 0) ?: null;
        $row    = $target ? q('SELECT user_id FROM reviews WHERE id=?', [$target])->fetch() : false;
        if (!$row) {
            return 'กรุณากดปุ่ม "🚩 แจ้งรีวิวนี้" ใต้รีวิวที่ต้องการแจ้งในหน้าสินค้า';
        }
        if ($row['user_id'] == $user['id']) {
            return 'แจ้งรีวิวของตัวเองไม่ได้ (ถ้าต้องการลบ กรุณาแจ้งแอดมินในหัวข้ออื่นๆ)';
        }
    }

    if ((int)q('SELECT COUNT(*) FROM reports WHERE user_id=? AND created > (NOW() - INTERVAL 1 DAY)', [$user['id']])->fetchColumn() >= REPORT_LIMIT_PER_DAY) {
        return 'วันนี้แจ้งครบ ' . REPORT_LIMIT_PER_DAY . ' เรื่องแล้ว กรุณารอแอดมินตอบกลับ หรือแชทกับร้านโดยตรง';
    }
    if (q('SELECT 1 FROM reports WHERE user_id=? AND subject=? AND body=?', [$user['id'], $subject, $body])->fetch()) {
        return 'คุณแจ้งเรื่องนี้ไปแล้ว ดูสถานะได้ที่ "รายงานของฉัน"';
    }

    // รูปประกอบ (ไม่บังคับ)
    $image = null;
    $file  = $_FILES['img'] ?? null;
    if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        $image = save_img($file, 'rep');
        if (!$image) {
            return 'รูปประกอบต้องเป็น JPG/PNG/WEBP ขนาดไม่เกิน 3MB';
        }
    }
    q('INSERT INTO reports(user_id,type,target_id,subject,body,img) VALUES(?,?,?,?,?,?)',
      [$user['id'], $type, $target, $subject, $body, $image]);
    return '';
}

if ($tableOk && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['a'] ?? '') === 'report') {
    $error = submit_report($user);
    if ($error === '') {
        flash('ส่งเรื่องถึงแอดมินแล้ว เราจะตรวจสอบและตอบกลับโดยเร็ว');
        header('Location: report.php?t=mine');
        exit;
    }
}

/* ---------- ข้อมูลแสดงผล ---------- */
$type     = $_POST['type'] ?? ($_GET['type'] ?? '');
$type     = isset(REPORT_TYPES[$type]) ? $type : 'order';
$targetId = (int)($_POST['t_' . $type] ?? ($_GET['id'] ?? 0));
$mine     = [];
$unseen   = [];
if ($tableOk) {
    $mine = q('SELECT * FROM reports WHERE user_id=? ORDER BY id DESC', [$user['id']])->fetchAll();
    if ($view === 'mine') {
        // เปิดอ่านแล้ว: เคลียร์ตัวเลขแจ้งเตือน (จำไว้ว่าอันไหนเพิ่งมีคำตอบใหม่เพื่อติดป้ายในหน้านี้)
        foreach ($mine as $r) {
            if (!$r['user_seen']) { $unseen[$r['id']] = true; }
        }
        q('UPDATE reports SET user_seen=1 WHERE user_id=? AND user_seen=0', [$user['id']]);
    }
}
$myOrders   = $view === 'new' ? q('SELECT id,status,total FROM orders WHERE user_id=? ORDER BY id DESC LIMIT 30', [$user['id']])->fetchAll() : [];
$allProducts = $view === 'new' ? q('SELECT id,name,emoji FROM products ORDER BY name')->fetchAll() : [];
$reviewInfo = null;
if ($view === 'new' && $type === 'review' && $targetId) {
    $reviewInfo = q('SELECT r.id, r.body, r.rating, u.name AS uname, p.name AS pname FROM reviews r
                     JOIN users u ON u.id=r.user_id LEFT JOIN products p ON p.id=r.product_id WHERE r.id=?', [$targetId])->fetch();
}

head('แจ้งรายงานถึงแอดมิน', 'แจ้งปัญหาออเดอร์ สินค้า การชำระเงิน รีวิวที่ไม่เหมาะสม หรือข้อผิดพลาดของเว็บไซต์ ถึงผู้ดูแลร้านสวนสุข');
?>
<p class="crumb"><a href="index.php">หน้าแรก</a> › แจ้งรายงาน</p>
<section class="pg-hero">
  <span class="e">🚩</span>
  <h1>แจ้งรายงานถึงแอดมิน</h1>
  <p>พบปัญหาเรื่องออเดอร์ สินค้า การชำระเงิน หรือรีวิวที่ไม่เหมาะสม แจ้งเราได้ที่นี่ แอดมินจะตรวจสอบและตอบกลับในหน้า "รายงานของฉัน"</p>
</section>

<div class="tabs-s">
  <a class="chip<?= $view === 'new' ? ' on' : '' ?>" href="report.php">✍️ แจ้งเรื่องใหม่</a>
  <a class="chip<?= $view === 'mine' ? ' on' : '' ?>" href="report.php?t=mine">📋 รายงานของฉัน (<?= count($mine) ?>)<?= nbadge(count($unseen) ?: badge_counts()['my_reports']) ?></a>
</div>

<?php if (!$tableOk): ?>
  <div class="card empty"><div class="big-emoji">🛠</div>
    <h3>ระบบแจ้งรายงานยังไม่พร้อมใช้งาน</h3>
    <p class="mut">ผู้ดูแลต้องนำเข้าไฟล์ <code>database/update_v4.sql</code> ในฐานข้อมูลก่อน (ดู README)</p></div>

<?php elseif ($view === 'mine'): ?>
  <?php foreach ($mine as $r): $tg = report_target($r); ?>
    <div class="card rep">
      <div class="rep-head">
        <span><b>#<?= $r['id'] ?></b> <?= e(REPORT_TYPES[$r['type']] ?? $r['type']) ?>
          <?php if (isset($unseen[$r['id']])): ?><span class="nb">ตอบกลับใหม่</span><?php endif; ?></span>
        <?= report_tag($r['status']) ?>
      </div>
      <h3 style="margin:6px 0 2px"><?= e($r['subject']) ?></h3>
      <span class="mut">แจ้งเมื่อ <?= thai_date($r['created']) ?> <?= e(substr($r['created'], 11, 5)) ?> น.
        <?php if ($tg): ?> · เกี่ยวกับ <?php if ($tg[1]): ?><a href="<?= e($tg[1]) ?>"><?= e($tg[0]) ?></a><?php else: echo e($tg[0]); endif; endif; ?></span>
      <p><?= nl2br(e($r['body'])) ?></p>
      <?php if ($r['img']): ?><a href="uploads/<?= e($r['img']) ?>" target="_blank"><img class="pic" src="uploads/<?= e($r['img']) ?>" alt="รูปประกอบ"></a><?php endif; ?>
      <?php if ($r['admin_reply']): ?>
        <div class="reply"><b>💬 แอดมินตอบกลับ</b> <span class="mut"><?= thai_date($r['updated']) ?></span><br><?= nl2br(e($r['admin_reply'])) ?></div>
      <?php else: ?>
        <p class="mut">⏳ ยังไม่มีคำตอบจากแอดมิน</p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$mine): ?>
    <div class="card empty"><div class="big-emoji">📭</div><p class="mut">คุณยังไม่เคยแจ้งเรื่องถึงแอดมิน</p><a class="btn" href="report.php">แจ้งเรื่องใหม่</a></div>
  <?php endif; ?>

<?php else: ?>
  <?php if ($error): ?><div class="flash" role="alert" style="background:var(--warn-bg);border-color:var(--warn)"><?= e($error) ?></div><?php endif; ?>
  <div class="rep-layout">
    <form method="post" action="report.php" enctype="multipart/form-data" class="card sect">
      <?= csrf() ?><input type="hidden" name="a" value="report">
      <h3>1. เลือกหัวข้อ</h3>
      <div class="type-grid">
        <?php foreach (REPORT_TYPES as $key => $label): ?>
          <label><input type="radio" name="type" value="<?= $key ?>" <?= $type === $key ? 'checked' : '' ?> required> <?= e($label) ?></label>
        <?php endforeach; ?>
      </div>

      <!-- สิ่งที่เกี่ยวข้อง: แสดงเฉพาะหัวข้อที่ต้องใช้ -->
      <div class="tgt" data-for="order">
        <label class="lbl" for="t_order">ออเดอร์ที่เกี่ยวข้อง</label>
        <select name="t_order" id="t_order">
          <option value="">— ไม่ระบุ —</option>
          <?php foreach ($myOrders as $o): ?>
            <option value="<?= $o['id'] ?>"<?= ($type === 'order' && $targetId == $o['id']) ? ' selected' : '' ?>>#<?= $o['id'] ?> · <?= e($o['status']) ?> · <?= baht($o['total']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="tgt" data-for="product">
        <label class="lbl" for="t_product">สินค้าที่เกี่ยวข้อง</label>
        <select name="t_product" id="t_product">
          <option value="">— ไม่ระบุ —</option>
          <?php foreach ($allProducts as $p): ?>
            <option value="<?= $p['id'] ?>"<?= ($type === 'product' && $targetId == $p['id']) ? ' selected' : '' ?>><?= e($p['emoji'] . ' ' . $p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="tgt" data-for="review">
        <?php if ($reviewInfo): ?>
          <input type="hidden" name="t_review" value="<?= $reviewInfo['id'] ?>">
          <div class="addr-card"><span style="font-size:22px">⭐</span>
            <div><b>รีวิวที่แจ้ง</b> <span class="mut">ของ <?= e(explode(' ', $reviewInfo['uname'])[0]) ?> · <?= e($reviewInfo['pname']) ?></span><br>
              <span class="stars"><?= stars($reviewInfo['rating']) ?></span> <?= e(mb_substr($reviewInfo['body'], 0, 140)) ?></div></div>
        <?php else: ?>
          <p class="mut">เปิดหน้าสินค้าแล้วกดปุ่ม "🚩 แจ้งรีวิวนี้" ใต้รีวิวที่ต้องการ ระบบจะระบุรีวิวให้อัตโนมัติ</p>
        <?php endif; ?>
      </div>

      <h3 style="margin-top:16px">2. เล่ารายละเอียด</h3>
      <p><input name="subject" value="<?= e($_POST['subject'] ?? '') ?>" placeholder="หัวเรื่อง เช่น ต้นไม้มาถึงแล้วใบเหลือง" maxlength="150" required></p>
      <p><textarea name="body" rows="6" maxlength="2000" placeholder="เกิดอะไรขึ้น เมื่อไหร่ ต้องการให้ร้านช่วยอะไร (อย่างน้อย 10 ตัวอักษร)" required><?= e($_POST['body'] ?? '') ?></textarea></p>
      <p><label class="lbl" for="img">รูปประกอบ (ไม่บังคับ)</label><input type="file" id="img" name="img" accept="image/jpeg,image/png,image/webp">
        <span class="mut">JPG/PNG/WEBP ไม่เกิน 3MB เช่น รูปสินค้าที่เสียหาย หรือภาพหน้าจอที่เกิดข้อผิดพลาด</span></p>
      <div class="actions"><button>🚩 ส่งเรื่องถึงแอดมิน</button><a class="btn ghost" href="index.php">ยกเลิก</a></div>
    </form>

    <aside>
      <div class="card sect">
        <h3>ขั้นตอนหลังแจ้ง</h3>
        <ol style="padding-left:20px;margin:6px 0">
          <li>ร้านได้รับเรื่องของคุณ (เห็นเฉพาะผู้ดูแลร้านเท่านั้น)</li>
          <li>ตรวจสอบและอัปเดตสถานะเรื่องของคุณ</li>
          <li>ตอบกลับพร้อมผลการแก้ไข คุณจะเห็นตัวเลขแจ้งเตือนที่เมนูโปรไฟล์</li>
        </ol>
        <p class="mut">ต้องการคุยด่วน? <a href="chat.php">💬 แชทกับร้าน</a> หรือดู <a href="faq.php">คำถามที่พบบ่อย</a> ก่อนได้</p>
      </div>
      <?php if ($mine): ?>
        <div class="card sect">
          <h3>เรื่องล่าสุดของฉัน</h3>
          <?php foreach (array_slice($mine, 0, 3) as $r): ?>
            <p style="margin:6px 0"><a href="report.php?t=mine">#<?= $r['id'] ?> <?= e(mb_strimwidth($r['subject'], 0, 48, '…')) ?></a> <?= report_tag($r['status']) ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </aside>
  </div>
  <script>
  // แสดงช่อง "สิ่งที่เกี่ยวข้อง" ให้ตรงกับหัวข้อที่เลือก
  (function () {
    var radios = document.querySelectorAll('input[name=type]');
    function sync() {
      var cur = document.querySelector('input[name=type]:checked');
      document.querySelectorAll('.tgt').forEach(function (b) {
        b.style.display = (cur && b.getAttribute('data-for') === cur.value) ? 'block' : 'none';
      });
    }
    radios.forEach(function (r) { r.addEventListener('change', sync); });
    sync();
  })();
  </script>
<?php endif; ?>

<?php foot();
