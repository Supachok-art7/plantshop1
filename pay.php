<?php
/**
 * pay.php — หน้าชำระเงินของออเดอร์  เปิดด้วย pay.php?id=เลขออเดอร์
 * เจ้าของออเดอร์ (หรือแอดมิน) เท่านั้นที่เข้าได้
 * แสดง: ยอดเงิน, QR PromptPay, เลขบัญชี, ช่องแนบสลิป
 * เมื่อแนบสลิป สถานะจะเปลี่ยนเป็น "แจ้งชำระแล้ว" แล้วรอแอดมินตรวจและกดยืนยัน
 */
require __DIR__ . '/includes/config.php';
chk();
$user = need();

/* ---------- 1) โหลดออเดอร์ และตรวจสิทธิ์ ---------- */
$id    = (int)($_GET['id'] ?? 0);
$order = q('SELECT * FROM orders WHERE id=?', [$id])->fetch();
if (!$order || ($order['user_id'] != $user['id'] && $user['role'] !== 'admin')) {
    fail(404, 'ไม่พบออเดอร์นี้');
}

$isCod    = ($order['pm'] === 'cod');
$isPaid   = in_array($order['status'], PAID_STATUS);   // ชำระแล้ว / เตรียมสินค้า / จัดส่งแล้ว / ได้รับสินค้าแล้ว
$canUpload = !$isCod && in_array($order['status'], ['รอชำระเงิน', 'แจ้งชำระแล้ว']);

/* ---------- 2) รับสลิปที่ลูกค้าอัปโหลด ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canUpload) {
    $slip = save_img($_FILES['slip'] ?? null, 'slip');   // ตรวจว่าเป็นรูปจริง ไม่เกิน 3MB
    if ($slip) {
        q('UPDATE orders SET slip=?, status=? WHERE id=?', [$slip, 'แจ้งชำระแล้ว', $id]);
        log_event($id, 'แจ้งชำระแล้ว', 'ลูกค้าแนบสลิป');   // บันทึกลงไทม์ไลน์ติดตาม
        flash('ส่งสลิปแล้ว รอร้านตรวจสอบ');
    } else {
        flash('ไฟล์ต้องเป็นรูป JPG/PNG/WEBP ขนาดไม่เกิน 3MB');
    }
    header('Location: pay.php?id=' . $id);
    exit;
}

head('ชำระเงิน #' . $id);
?>
<h2>ชำระเงินออเดอร์ #<?= $id ?></h2>
<div class="card">
  ยอดที่ต้องชำระ <b class="price"><?= baht2($order['total']) ?></b>
  <span class="tag"><?= e($order['status']) ?></span>
  <?= order_summary_html(order_calc_from_row($order), (string)($order['coupon'] ?? '')) ?>
  <?php $tf = shop_fee_config(); if ($tf['vat_rate'] > 0 && $tf['tax_id'] !== ''): ?>
    <p class="mut" style="font-size:12.5px;margin:6px 0 0"><?= e($tf['tax_name'] !== '' ? $tf['tax_name'] : 'ร้านสวนสุข') ?> · เลขประจำตัวผู้เสียภาษี <?= e($tf['tax_id']) ?></p>
  <?php endif; ?>
</div>

<?php if ($isCod): ?>
  <div class="card">ชำระเงินสดเมื่อได้รับสินค้า ร้านจะติดต่อเพื่อจัดส่ง</div>

<?php elseif ($isPaid): ?>
  <div class="card">✅ ชำระเงินเรียบร้อยแล้ว ขอบคุณที่สั่งซื้อ</div>

<?php elseif ($order['status'] === 'ยกเลิก'): ?>
  <div class="card">ออเดอร์นี้ถูกยกเลิก</div>

<?php else: ?>
  <?php if ($order['pm'] === 'pp' && setting('pp')): ?>
    <!-- QR PromptPay: PHP สร้างข้อความ (ppay) แล้ว JavaScript วาดเป็นภาพ QR -->
    <div class="card" style="text-align:center">
      <div id="qr" style="background:#fff;display:inline-block;padding:8px;border-radius:10px"></div>
      <p class="mut">สแกนด้วยแอปธนาคารเพื่อชำระ <?= baht2($order['total']) ?></p>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
    <script>
      var qr = qrcode(0, 'M');
      qr.addData(<?= json_encode(ppay(setting('pp'), $order['total'])) ?>);
      qr.make();
      document.getElementById('qr').innerHTML = qr.createSvgTag(5, 2);
    </script>
  <?php endif; ?>

  <?php if (setting('acc')): ?>
    <div class="card">
      <b>โอนเข้าบัญชี</b><br>
      <?= e(setting('bank') . ' ' . setting('acc')) ?><br>
      <?= e(setting('aname')) ?>
    </div>
  <?php endif; ?>

  <!-- ฟอร์มแนบสลิป (enctype จำเป็นสำหรับอัปโหลดไฟล์) -->
  <form method="post" enctype="multipart/form-data" class="card">
    <h2>แนบสลิปโอนเงิน</h2>
    <?= csrf() ?>
    <?php if ($order['slip']): ?>
      <img src="uploads/<?= e($order['slip']) ?>" alt="สลิป" style="max-width:100%;max-height:300px;border-radius:8px">
      <p class="mut">ส่งสลิปแล้ว รอร้านตรวจสอบ (แนบใหม่ได้)</p>
    <?php endif; ?>
    <p><input type="file" name="slip" accept="image/*" required></p>
    <button>ส่งสลิป</button>
  </form>
<?php endif; ?>

<p><a href="track.php?id=<?= $id ?>">📍 ติดตามสถานะ/พัสดุ</a> · <a href="account.php?t=orders">← ประวัติการสั่งซื้อ</a></p>
<?php foot();
