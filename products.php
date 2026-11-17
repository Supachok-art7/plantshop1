<?php
/**
 * products.php — หน้าสินค้า (ทุกคนเข้าได้ ไม่ต้องล็อกอิน)
 * ฝั่งซ้าย = แถบหมวดหมู่ยาวลงมา (หมวดหลัก + หมวดย่อย) / ฝั่งขวา = รายการสินค้า
 * URL: products.php?c=รหัสหมวด&q=คำค้น&sort=เรียงตาม   (เลือกหมวดหลัก = รวมสินค้าในหมวดย่อยทั้งหมด)
 */
require __DIR__ . '/includes/config.php';

/* ---------- 1) รับค่าจาก URL ---------- */
$catId   = (int)($_GET['c'] ?? 0);
$keyword = trim($_GET['q'] ?? '');
$sort    = $_GET['sort'] ?? 'new';
$orders  = [
    'new'   => 'p.id DESC',
    'low'   => 'p.price ASC, p.id DESC',
    'high'  => 'p.price DESC, p.id DESC',
    'name'  => 'p.name ASC',
];
if (!isset($orders[$sort])) { $sort = 'new'; }

$where  = '1=1';
$params = [];
if ($catId > 0) {
    // สินค้าที่อยู่ในหมวดนี้ หรือในหมวดย่อยของหมวดนี้
    $where   .= ' AND p.category_id IN (SELECT id FROM categories WHERE id=? OR parent_id=?)';
    $params[] = $catId;
    $params[] = $catId;
}
if ($keyword !== '') {
    $where   .= ' AND (p.name LIKE ? OR p.descr LIKE ?)';
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}
$products = q("SELECT p.* FROM products p WHERE $where ORDER BY {$orders[$sort]}", $params)->fetchAll();

/* ---------- 2) จัดกลุ่มหมวดหมู่ + นับจำนวนสินค้าในแต่ละหมวด ---------- */
$counts = q('SELECT category_id, COUNT(*) FROM products WHERE category_id IS NOT NULL GROUP BY category_id')->fetchAll(PDO::FETCH_KEY_PAIR);
$parents = [];
$kids    = [];
foreach (categories() as $c) {
    if ($c['parent_id']) { $kids[$c['parent_id']][] = $c; } else { $parents[] = $c; }
}
$total = (int)q('SELECT COUNT(*) FROM products')->fetchColumn();
function cat_total($id, $counts, $kids) {   // จำนวนสินค้าของหมวดหลัก = ของมันเอง + หมวดย่อยทั้งหมด
    $n = (int)($counts[$id] ?? 0);
    foreach ($kids[$id] ?? [] as $k) { $n += (int)($counts[$k['id']] ?? 0); }
    return $n;
}
$current      = category_path($catId);                 // [หมวดหลัก, หมวดย่อย]
$activeParent = $current ? (int)$current[0]['id'] : 0;

/** keep() — สร้างลิงก์ products.php โดยคงคำค้น/การเรียงไว้ (ใช้ในแถบข้างและตัวเลือกเรียง) */
function keep($extra = []) {
    $qs = array_filter(array_merge(['q' => $_GET['q'] ?? '', 'sort' => $_GET['sort'] ?? ''], $extra), fn($v) => $v !== '' && $v !== null && $v !== 0);
    return 'products.php' . ($qs ? '?' . http_build_query($qs) : '');
}

$title = $keyword !== '' ? 'ผลการค้นหา "' . $keyword . '"' : ($current ? end($current)['icon'] . ' ' . end($current)['name'] : '🪴 สินค้าทั้งหมด');
head($keyword !== '' ? 'ค้นหา ' . $keyword : ($current ? end($current)['name'] : 'สินค้าทั้งหมด'));
?>

<p class="crumb"><a href="index.php">หน้าแรก</a> › <a href="products.php">สินค้า</a>
  <?php foreach ($current as $i => $c): ?> › <?php if ($i < count($current) - 1): ?><a href="products.php?c=<?= $c['id'] ?>"><?= e($c['name']) ?></a><?php else: ?><?= e($c['name']) ?><?php endif; ?><?php endforeach; ?>
</p>

<div class="shop-layout">

  <!-- ===== แถบหมวดหมู่ฝั่งซ้าย ===== -->
  <aside class="catside" aria-label="หมวดหมู่สินค้า">
    <details class="catbox" <?= '' /* มือถือพับได้ */ ?> open>
      <summary>☰ หมวดหมู่สินค้า</summary>
      <ul>
        <li><a class="all<?= $catId === 0 ? ' on' : '' ?>" href="<?= e(keep()) ?>">🪴 สินค้าทั้งหมด <em><?= $total ?></em></a></li>
        <?php foreach ($parents as $pc): $open = ($activeParent === (int)$pc['id']); ?>
          <li class="grp<?= $open ? ' open' : '' ?>">
            <a class="top<?= $catId == $pc['id'] ? ' on' : '' ?>" href="<?= e(keep(['c' => $pc['id']])) ?>">
              <span><?= e($pc['icon'] . ' ' . $pc['name']) ?></span>
              <em><?= cat_total($pc['id'], $counts, $kids) ?></em>
            </a>
            <?php if (!empty($kids[$pc['id']])): ?>
              <ul class="sub">
                <?php foreach ($kids[$pc['id']] as $kc): ?>
                  <li><a class="<?= $catId == $kc['id'] ? 'on' : '' ?>" href="<?= e(keep(['c' => $kc['id']])) ?>">
                    <span><?= e($kc['icon'] . ' ' . $kc['name']) ?></span><em><?= (int)($counts[$kc['id']] ?? 0) ?></em></a></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </details>
  </aside>

  <!-- ===== รายการสินค้าฝั่งขวา ===== -->
  <section class="shop-main">
    <div class="shop-bar">
      <div>
        <h1><?= e($title) ?></h1>
        <span class="mut"><?= count($products) ?> รายการ<?= $keyword !== '' ? ' · <a href="' . e(keep(['q' => ''])) . '">ล้างคำค้น</a>' : '' ?></span>
      </div>
      <form method="get" action="products.php" class="sortf">
        <?php if ($catId): ?><input type="hidden" name="c" value="<?= $catId ?>"><?php endif; ?>
        <?php if ($keyword !== ''): ?><input type="hidden" name="q" value="<?= e($keyword) ?>"><?php endif; ?>
        <label for="sort" class="mut">เรียงตาม</label>
        <select id="sort" name="sort" onchange="this.form.submit()">
          <option value="new"  <?= $sort === 'new'  ? 'selected' : '' ?>>มาใหม่ล่าสุด</option>
          <option value="low"  <?= $sort === 'low'  ? 'selected' : '' ?>>ราคา: ต่ำ → สูง</option>
          <option value="high" <?= $sort === 'high' ? 'selected' : '' ?>>ราคา: สูง → ต่ำ</option>
          <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>ชื่อ ก → ฮ</option>
        </select>
        <noscript><button>ตกลง</button></noscript>
      </form>
    </div>

    <div class="grid">
      <?php foreach ($products as $p) { card($p, 'products.php'); } ?>
    </div>
    <?php if (!$products): ?>
      <div class="card empty"><div class="big-emoji">🔎</div><p class="mut">ไม่พบสินค้าในหมวดนี้</p><a class="btn" href="products.php">ดูสินค้าทั้งหมด</a></div>
    <?php endif; ?>
  </section>
</div>

<?php foot();
