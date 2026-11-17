<?php
/**
 * 404.php — หน้า "ไม่พบหน้าที่ต้องการ" ที่สวยงาม (มีเมนู ช่องค้นหา หมวดหมู่ และสินค้าแนะนำ)
 * ถูกเรียกอัตโนมัติจาก .htaccess เมื่อเปิดที่อยู่ที่ไม่มีอยู่จริง และตอบรหัส HTTP 404 ให้ถูกต้อง (เสิร์ชเอนจินจะไม่จัดทำดัชนี)
 * ไม่แสดงที่อยู่ที่ผู้ใช้พิมพ์กลับไปบนหน้า เพื่อไม่ให้ถูกใช้แทรกข้อความหลอก
 */
define('IS_404', true);      // ให้ head() ใส่ <base> และ noindex
require __DIR__ . '/includes/config.php';
http_response_code(404);

$parents = array_values(array_filter(categories(), function ($c) { return !$c['parent_id']; }));
$newest  = [];
try {
    $newest = q('SELECT * FROM products ORDER BY id DESC LIMIT 4')->fetchAll();
} catch (Throwable $err) {
    // ฐานข้อมูลมีปัญหา: แสดงหน้า 404 แบบไม่มีสินค้าแนะนำ
}

head('ไม่พบหน้านี้ (404)', 'ไม่พบหน้าที่คุณต้องการ ลองค้นหาสินค้าหรือกลับไปหน้าแรกของร้านสวนสุข');
?>
<section class="e404">
  <div class="e404-art" aria-hidden="true"><span class="n">4</span><span class="pot">🥀</span><span class="n">4</span></div>
  <h1>ไม่พบหน้าที่คุณต้องการ</h1>
  <p class="mut">ลิงก์อาจพิมพ์ผิด หรือหน้านี้ถูกย้าย/ลบไปแล้ว ไม่เป็นไร เราช่วยพาคุณกลับไปเลือกต้นไม้ต่อได้</p>

  <form class="e404-search" action="products.php" role="search">
    <input name="q" placeholder="ค้นหาต้นไม้ ดอกไม้ กระถาง..." aria-label="ค้นหาสินค้า">
    <button>🔍 ค้นหา</button>
  </form>

  <p class="e404-act">
    <a class="btn" href="index.php">🏠 กลับหน้าแรก</a>
    <a class="btn ghost" href="products.php">🪴 ดูสินค้าทั้งหมด</a>
    <a class="btn ghost" href="contact.php">📞 ติดต่อร้าน</a>
  </p>
</section>

<?php if ($parents): ?>
  <div class="sec-title"><h2>หรือเลือกชมตามหมวดหมู่</h2></div>
  <div class="cat-tiles">
    <?php foreach ($parents as $c): ?>
      <a class="tile" href="products.php?c=<?= (int)$c['id'] ?>"><span class="ti"><?= e($c['icon']) ?></span><b><?= e($c['name']) ?></b></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($newest): ?>
  <div class="sec-title"><h2>🆕 สินค้ามาใหม่</h2><a href="products.php">ดูทั้งหมด →</a></div>
  <div class="grid">
    <?php foreach ($newest as $p) { card($p); } ?>
  </div>
<?php endif; ?>

<?php foot();
