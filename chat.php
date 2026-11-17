<?php
/**
 * chat.php — แชทระหว่างลูกค้ากับร้านค้า (ต้องล็อกอิน)
 * ข้อความเก็บในตาราง messages: sender 'c' = ลูกค้า, 's' = ร้าน
 * หน้านี้โหลดใหม่เองทุก 10 วินาที (ถ้าไม่ได้กำลังพิมพ์อยู่) เพื่อดูข้อความใหม่
 */
require __DIR__ . '/includes/config.php';
chk();
$user = need();

// ลูกค้าส่งข้อความ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = trim($_POST['body'] ?? '');
    if ($body !== '') {
        q('INSERT INTO messages(user_id,sender,body) VALUES(?,?,?)', [$user['id'], 'c', mb_substr($body, 0, 1000)]);
    }
    header('Location: chat.php');
    exit;
}

// ทำเครื่องหมายว่าอ่านข้อความจากร้านแล้ว แล้วโหลดบทสนทนา
q("UPDATE messages SET is_read=1 WHERE user_id=? AND sender='s'", [$user['id']]);
$messages = q('SELECT * FROM messages WHERE user_id=? ORDER BY id', [$user['id']])->fetchAll();

head('แชทกับร้านค้า');
?>
<h2>💬 แชทกับร้านค้า</h2>
<div class="card">
  <div class="msgs">
    <?php foreach ($messages as $m): ?>
      <div class="bub<?= $m['sender'] === 'c' ? ' me' : '' ?>">
        <?= nl2br(e($m['body'])) ?><br><small><?= e($m['created']) ?></small>
      </div>
    <?php endforeach; ?>
    <?php if (!$messages): ?><p class="mut">เริ่มพิมพ์ข้อความถึงร้านได้เลย</p><?php endif; ?>
  </div>
  <form method="post" style="display:flex;gap:8px;margin-top:8px">
    <?= csrf() ?>
    <input name="body" placeholder="พิมพ์ข้อความ..." required autocomplete="off">
    <button>ส่ง</button>
  </form>
</div>
<script>
// โหลดหน้าใหม่ทุก 10 วินาที เฉพาะตอนที่ช่องพิมพ์ว่าง (กันข้อความที่กำลังพิมพ์หาย)
setTimeout(function () {
  if (!document.querySelector('input[name=body]').value) { location.reload(); }
}, 10000);
</script>
<?php foot();
