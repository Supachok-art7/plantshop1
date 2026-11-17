<?php
/**
 * index.php — หน้าแรก (landing): สไลด์โฆษณา + จุดเด่นร้าน + หมวดหมู่ + สินค้ามาใหม่ + แนะนำ/ขายดี
 * ไม่มีรายการสินค้าทั้งหมดแล้ว → กดหมวดหมู่หรือ "ดูทั้งหมด" จะไปที่ products.php (มีแถบหมวดซ้ายมือ)
 */
require __DIR__ . '/includes/config.php';

/* เปิดลิงก์เก่า index.php?c=..&q=.. → ส่งต่อไปหน้าสินค้าให้อัตโนมัติ */
if (isset($_GET['c']) || isset($_GET['q'])) {
    header('Location: products.php?' . http_build_query(array_filter(['c' => $_GET['c'] ?? '', 'q' => $_GET['q'] ?? ''])));
    exit;
}

/* ---------- ข้อมูลหน้าแรก ---------- */
$parents = [];
$counts  = q('SELECT category_id, COUNT(*) FROM products WHERE category_id IS NOT NULL GROUP BY category_id')->fetchAll(PDO::FETCH_KEY_PAIR);
$sum     = [];   // จำนวนสินค้าของหมวดหลัก (รวมหมวดย่อย)
foreach (categories() as $c) {
    if (!$c['parent_id']) { $parents[] = $c; }
    $top = $c['parent_id'] ?: $c['id'];
    $sum[$top] = ($sum[$top] ?? 0) + (int)($counts[$c['id']] ?? 0);
}
$newest = q('SELECT * FROM products ORDER BY id DESC LIMIT 8')->fetchAll();
// ขายดี: เรียงตามจำนวนที่ขายได้ ถ้ายังไม่มียอดขายให้แสดงสินค้าที่มีรีวิวดี/ล่าสุดแทน
$best = q('SELECT p.* FROM products p JOIN order_items i ON i.product_id = p.id
           GROUP BY p.id ORDER BY SUM(i.qty) DESC, p.id DESC LIMIT 4')->fetchAll();
if (count($best) < 4) {
    $ids  = array_column($best, 'id') ?: [0];
    $mark = implode(',', array_fill(0, count($ids), '?'));
    $best = array_merge($best, q("SELECT * FROM products WHERE id NOT IN ($mark) AND stock>0 ORDER BY id LIMIT " . (4 - count($best)), $ids)->fetchAll());
}

/* ---------- สไลด์โฆษณา: ต้อนรับ + โปรโมชั่นจากแอดมิน + สินค้ามาใหม่ 2 ชิ้น ---------- */
// แต่ละสไลด์ = [หัวข้อ, ข้อความ, อีโมจิ, สีพื้น, ลิงก์ปลายทาง, ข้อความป้ายเล็กๆ] — กดที่ไหนก็ได้บนสไลด์เพื่อไปลิงก์
$slides = [
    ['ต้นไม้และดอกไม้ ส่งตรงถึงบ้านคุณ', 'คัดสดใหม่ทุกวัน ดูแลง่าย ติดตามพัสดุได้', '🌿', '#1f9d55,#6cc48a',
     me() ? 'products.php' : 'register.php', me() ? 'เลือกซื้อสินค้า' : 'สมัครสมาชิกฟรี'],
];
foreach (q('SELECT * FROM slides')->fetchAll() as $s) {
    $slides[] = [$s['title'], $s['body'], $s['emoji'], $s['bg'], 'products.php', 'ช้อปเลย'];
}
foreach (array_slice($newest, 0, 2) as $p) {
    $slides[] = ['🆕 มาใหม่: ' . $p['name'], $p['descr'] . ' · ' . baht($p['price']), $p['emoji'], '#166534,#22c55e',
                 'product.php?id=' . $p['id'], 'ดูสินค้า'];
}

head('ร้านสวนสุข');
?>

<!-- สไลด์โฆษณา: เลื่อนอัตโนมัติทุก 4.5 วินาที / ปัดนิ้วหรือลากเมาส์เพื่อเลื่อน / กดที่สไลด์เพื่อไปหน้านั้น (ไม่มีปุ่มลูกศร) -->
<div class="sl" id="sl">
  <div class="track" id="track">
    <?php foreach ($slides as $s): ?>
      <a class="sd" href="<?= e($s[4]) ?>" style="background:linear-gradient(135deg,<?= e($s[3]) ?>)" draggable="false">
        <span class="e"><?= e($s[2]) ?></span>
        <h1><?= e($s[0]) ?></h1>
        <p><?= e($s[1]) ?></p>
        <span class="pill"><?= e($s[5]) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<script>
var index = 0, total = <?= count($slides) ?>, timer;
var box = document.getElementById('sl'), track = document.getElementById('track');
var startX = null, moved = false;

function go(step) { index = (index + step + total) % total; track.style.transform = 'translateX(-' + (index * 100) + '%)'; }
function auto() { clearInterval(timer); timer = setInterval(function () { go(1); }, 4500); }
auto();

// ปัดนิ้ว (มือถือ) หรือลากเมาส์ (คอม): ซ้าย = ถัดไป, ขวา = ย้อนกลับ
box.addEventListener('pointerdown', function (e) { startX = e.clientX; moved = false; clearInterval(timer); });
box.addEventListener('pointerup', function (e) {
  if (startX === null) { return; }
  var d = e.clientX - startX;
  if (Math.abs(d) > 40) { moved = true; go(d < 0 ? 1 : -1); }
  startX = null; auto();
});
box.addEventListener('pointercancel', function () { startX = null; auto(); });
// ถ้าเพิ่งลากสไลด์ ไม่ให้นับเป็นการกดลิงก์
box.addEventListener('click', function (e) { if (moved) { e.preventDefault(); moved = false; } });
</script>

<!-- จุดเด่นของร้าน -->
<div class="usp">
  <div><span class="em2">🌱</span><span><b>คัดสดใหม่</b><span class="mut">ตรวจก่อนส่งทุกชิ้น</span></span></div>
  <div><span class="em2">📦</span><span><b>แพ็กอย่างดี</b><span class="mut">ต้นไม้ถึงมือสภาพสวย</span></span></div>
  <div><span class="em2">📍</span><span><b>ติดตามพัสดุได้</b><span class="mut">ดูตำแหน่งบนแผนที่</span></span></div>
  <div><span class="em2">💬</span><span><b>มีแชทช่วยเหลือ</b><span class="mut">ถามวิธีดูแลได้</span></span></div>
</div>

<!-- ช้อปตามหมวดหมู่: กดแล้วไปหน้าสินค้า (แถบหมวดอยู่ซ้ายมือ) -->
<div class="sec-title"><h2>🛍️ ช้อปตามหมวดหมู่</h2><a href="products.php">ดูสินค้าทั้งหมด →</a></div>
<div class="cattiles">
  <?php foreach ($parents as $c): ?>
    <a href="products.php?c=<?= $c['id'] ?>">
      <span class="ci"><?= e($c['icon']) ?></span>
      <b><?= e($c['name']) ?></b>
      <span class="mut"><?= (int)($sum[$c['id']] ?? 0) ?> รายการ</span>
    </a>
  <?php endforeach; ?>
</div>

<!-- สินค้ามาใหม่ -->
<div class="sec-title"><h2>🆕 สินค้ามาใหม่</h2><a href="products.php?sort=new">ดูทั้งหมด →</a></div>
<div class="grid">
  <?php foreach ($newest as $p) { card($p); } ?>
</div>

<!-- แบนเนอร์โปรโมต -->
<div class="promo2">
  <div><span class="pe">🎁</span><div><b>ของขวัญต้นไม้มงคล</b><span>กระเช้าและชุดของขวัญ จัดพร้อมการ์ดอวยพร</span></div></div>
  <a class="btn" href="products.php?c=32">เลือกชุดของขวัญ</a>
</div>

<!-- ขายดี / แนะนำ -->
<?php if ($best): ?>
  <div class="sec-title"><h2>🔥 ขายดี · ร้านแนะนำ</h2><a href="products.php">ดูสินค้าทั้งหมด →</a></div>
  <div class="grid">
    <?php foreach ($best as $p) { card($p); } ?>
  </div>
<?php endif; ?>

<!-- ชวนสมัคร / เริ่มช้อป -->
<div class="cta">
  <h2>พร้อมมีมุมสีเขียวของคุณแล้วหรือยัง?</h2>
  <p>เลือกต้นไม้ที่ใช่ แล้วให้เราส่งถึงหน้าบ้าน</p>
  <a class="btn" href="products.php">🪴 เริ่มเลือกซื้อสินค้า</a>
  <?php if (!me()): ?><a class="btn ghost" href="register.php">สมัครสมาชิกฟรี</a><?php endif; ?>
</div>

<?php foot();
