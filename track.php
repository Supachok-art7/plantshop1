<?php
/**
 * track.php — ติดตามออเดอร์  เปิดด้วย track.php?id=เลขออเดอร์  (เจ้าของออเดอร์หรือแอดมินเท่านั้น)
 * แสดง: ไทม์ไลน์ 5 ขั้นตอน, ขนส่ง/เลขพัสดุ, แผนที่ (ร้าน → ตำแหน่งพัสดุ → จุดจัดส่ง), ประวัติทุกครั้งที่สถานะเปลี่ยน
 * ลูกค้ากด "ยืนยันได้รับสินค้า" ได้เมื่อสถานะเป็น "จัดส่งแล้ว"
 */
require __DIR__ . '/includes/config.php';
chk();
$user = need();

$id    = (int)($_GET['id'] ?? 0);
$order = q('SELECT * FROM orders WHERE id=?', [$id])->fetch();
if (!$order || ($order['user_id'] != $user['id'] && $user['role'] !== 'admin')) {
    fail(404, 'ไม่พบออเดอร์นี้');
}

/* ---------- ลูกค้ายืนยันว่าได้รับสินค้าแล้ว ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['a'] ?? '') === 'received' && $order['user_id'] == $user['id'] && $order['status'] === 'จัดส่งแล้ว') {
        q('UPDATE orders SET status=? WHERE id=?', ['ได้รับสินค้าแล้ว', $id]);
        log_event($id, 'ได้รับสินค้าแล้ว', 'ลูกค้ายืนยันรับสินค้า');
        flash('ขอบคุณครับ ยืนยันรับสินค้าแล้ว');
    }
    header('Location: track.php?id=' . $id);
    exit;
}

$events = q('SELECT * FROM order_events WHERE order_id=? ORDER BY id', [$id])->fetchAll();
$items  = q('SELECT * FROM order_items WHERE order_id=?', [$id])->fetchAll();

/* ---------- ไทม์ไลน์: สถานะ → จำนวนขั้นตอนที่เสร็จแล้ว ---------- */
$steps    = ['สั่งซื้อ', 'ชำระเงิน', 'เตรียมสินค้า', 'จัดส่ง', 'ได้รับสินค้า'];
$progress = ['รอชำระเงิน' => 1, 'แจ้งชำระแล้ว' => 1, 'ชำระแล้ว' => 2, 'เก็บเงินปลายทาง' => 2,
             'เตรียมสินค้า' => 3, 'จัดส่งแล้ว' => 4, 'ได้รับสินค้าแล้ว' => 5];
$done      = $progress[$order['status']] ?? 0;
$cancelled = ($order['status'] === 'ยกเลิก');

/* ---------- ข้อมูลแผนที่ ---------- */
$shop = [(float)setting('shop_lat', '13.7563'), (float)setting('shop_lng', '100.5018')];
$dest = ($order['lat'] !== null) ? [(float)$order['lat'], (float)$order['lng']] : null;
$path = [];   // ตำแหน่งพัสดุที่แอดมินอัปเดตไว้ตามลำดับเวลา
foreach ($events as $ev) {
    if ($ev['lat'] !== null) {
        $path[] = ['lat' => (float)$ev['lat'], 'lng' => (float)$ev['lng'], 'text' => $ev['status'] . ' ' . $ev['note'] . ' (' . $ev['created'] . ')'];
    }
}
$showMap = ($dest || $path);

head('ติดตามออเดอร์ #' . $id);
?>
<h2>📍 ติดตามออเดอร์ #<?= $id ?></h2>

<div class="card">
  <span class="tag"><?= e($order['status']) ?></span> ยอดรวม <b class="price"><?= baht2($order['total']) ?></b> <span class="mut">(รวมค่าส่ง<?= (float)($order['vat'] ?? 0) > 0 ? ' และภาษี' : '' ?>แล้ว)</span><br>
  <span class="mut"><?= e($order['name'] . ' · ' . $order['phone']) ?><br><?= e($order['addr']) ?></span><br>
  <?php foreach ($items as $i): ?><?= e($i['name']) ?> ×<?= $i['qty'] ?>, <?php endforeach; ?>

  <?php if ($cancelled): ?>
    <p class="low"><b>ออเดอร์นี้ถูกยกเลิก</b></p>
  <?php else: ?>
    <ol class="steps">
      <?php foreach ($steps as $i => $label): ?>
        <li class="<?= $i < $done ? 'done' : ($i === $done ? 'now' : '') ?>"><?= e($label) ?></li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>

  <?php if ($order['tracking_no']): ?>
    <p>🚚 <?= e($order['carrier'] ?: 'ขนส่ง') ?> · เลขพัสดุ <b><?= e($order['tracking_no']) ?></b></p>
  <?php endif; ?>

  <?php if ($order['status'] === 'จัดส่งแล้ว' && $order['user_id'] == $user['id']): ?>
    <form method="post"><?= csrf() ?><input type="hidden" name="a" value="received"><button>✔ ยืนยันได้รับสินค้า</button></form>
  <?php endif; ?>
</div>

<?php if ($showMap): ?>
  <div class="card">
    <b>แผนที่</b> <span class="mut">🏪 ร้าน · 📦 ตำแหน่งพัสดุล่าสุด · 🏠 จุดจัดส่ง</span>
    <div id="trackmap" style="height:340px;border-radius:12px;margin-top:8px"></div>
  </div>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
  <script>
  var data = <?= json_encode(['shop' => $shop, 'dest' => $dest, 'path' => $path, 'moving' => $order['status'] === 'จัดส่งแล้ว'],
                             JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

  // icon() — หมุดแบบอีโมจิ    note() — กล่องข้อความ popup (ใช้ textContent กันโค้ดแปลกปลอม)
  function icon(emoji) {
    return L.divIcon({html: '<div style="font-size:26px;line-height:1">' + emoji + '</div>', className: '', iconSize: [30, 30], iconAnchor: [15, 15]});
  }
  function note(text) { var d = document.createElement('div'); d.textContent = text; return d; }

  var map = L.map('trackmap');
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '© OpenStreetMap'}).addTo(map);

  var line = [data.shop], bounds = [data.shop];
  L.marker(data.shop, {icon: icon('🏪')}).addTo(map).bindPopup(note('ร้านสวนสุข'));

  // จุดที่พัสดุผ่านมา (เรียงตามเวลา)
  data.path.forEach(function (p) {
    var pt = [p.lat, p.lng];
    line.push(pt); bounds.push(pt);
    L.circleMarker(pt, {radius: 5, color: '#1f9d55'}).addTo(map).bindPopup(note(p.text));
  });

  // จุดจัดส่งปลายทาง
  if (data.dest) {
    line.push(data.dest); bounds.push(data.dest);
    L.marker(data.dest, {icon: icon('🏠')}).addTo(map).bindPopup(note('จุดจัดส่ง'));
  }
  // พัสดุกำลังเดินทาง: แสดงหมุด 📦 ที่ตำแหน่งล่าสุด
  if (data.moving && data.path.length) {
    var last = data.path[data.path.length - 1];
    L.marker([last.lat, last.lng], {icon: icon('📦')}).addTo(map).bindPopup(note('ตำแหน่งพัสดุล่าสุด'));
  }
  L.polyline(line, {color: '#1f9d55', dashArray: '6 8'}).addTo(map);
  map.fitBounds(bounds, {padding: [30, 30], maxZoom: 15});
  </script>
<?php endif; ?>

<div class="card">
  <b>ประวัติความคืบหน้า</b>
  <ul class="history">
    <?php foreach (array_reverse($events) as $ev): ?>
      <li><b><?= e($ev['status']) ?></b> <span class="mut"><?= e($ev['created']) ?></span>
        <?php if ($ev['note']): ?><br><?= e($ev['note']) ?><?php endif; ?></li>
    <?php endforeach; ?>
    <?php if (!$events): ?><li class="mut">ยังไม่มีประวัติ</li><?php endif; ?>
  </ul>
</div>

<p><a href="account.php?t=orders">← ประวัติการสั่งซื้อ</a>
  <?php if ($order['user_id'] == $user['id']): ?> · <a href="report.php?type=order&id=<?= $id ?>">🚩 แจ้งปัญหาออเดอร์นี้ถึงแอดมิน</a><?php endif; ?></p>
<?php foot();
