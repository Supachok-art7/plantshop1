<?php
/**
 * product.php — หน้ารายละเอียดสินค้า  เปิดด้วย product.php?id=เลขสินค้า
 * แสดง: รูปใหญ่ ราคา/สต็อก ปุ่มใส่ตะกร้า รายละเอียดเต็ม รีวิว (แนบรูปได้) และสินค้าที่เกี่ยวข้อง
 * ผู้ใช้ที่ล็อกอินเขียนรีวิวได้ / แอดมินลบรีวิวได้
 */
require __DIR__ . '/includes/config.php';
chk();

/* ---------- 1) โหลดสินค้า (ไม่พบ = 404) ---------- */
$id = (int)($_GET['id'] ?? 0);
$product = q('SELECT * FROM products WHERE id=?', [$id])->fetch();
if (!$product) {
    fail(404, 'ไม่พบสินค้านี้');
}

/* ---------- 2) รับคำสั่งจากฟอร์ม (เขียนรีวิว / แอดมินลบรีวิว) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['a'] ?? '';
    if ($action === 'review') {
        submit_review($id);
    } elseif ($action === 'rdel') {
        delete_review();
    }
    header('Location: product.php?id=' . $id . '#reviews');   // กลับมาหน้าเดิม เลื่อนไปส่วนรีวิว
    exit;
}

/** submit_review() — บันทึกรีวิวใหม่ (ต้องล็อกอิน, 1 คนรีวิว 1 ครั้งต่อสินค้า, แนบรูปได้ 1 รูป) */
function submit_review($productId) {
    $user   = need();
    $rating = (int)($_POST['rating'] ?? 0);
    $body   = trim($_POST['body'] ?? '');

    if ($rating < 1 || $rating > 5 || $body === '') {
        flash('กรุณาให้คะแนน 1–5 ดาว และเขียนรีวิว');
        return;
    }
    $already = q('SELECT 1 FROM reviews WHERE product_id=? AND user_id=?', [$productId, $user['id']])->fetch();
    if ($already) {
        flash('คุณรีวิวสินค้านี้แล้ว');
        return;
    }

    // รูปแนบ (ไม่บังคับ)
    $image = null;
    $file  = $_FILES['img'] ?? null;
    if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        $image = save_img($file, 'rev');
        if (!$image) {
            flash('รูปต้องเป็น JPG/PNG/WEBP ขนาดไม่เกิน 3MB');
            return;
        }
    }
    q('INSERT INTO reviews(product_id,user_id,rating,body,img) VALUES(?,?,?,?,?)',
      [$productId, $user['id'], $rating, mb_substr($body, 0, 1000), $image]);
    flash('ขอบคุณสำหรับรีวิว');
}

/** delete_review() — แอดมินลบรีวิว (พร้อมลบไฟล์รูปที่แนบ) */
function delete_review() {
    need('admin');
    $reviewId = (int)($_POST['rid'] ?? 0);
    $row = q('SELECT img FROM reviews WHERE id=?', [$reviewId])->fetch();
    if ($row) {
        if ($row['img']) {
            @unlink(ROOT_DIR . '/uploads/' . basename($row['img']));
        }
        q('DELETE FROM reviews WHERE id=?', [$reviewId]);
    }
}

/* ---------- 3) ข้อมูลสำหรับแสดงผล ---------- */
$user  = me();
$stats = q('SELECT COUNT(*) AS cnt, COALESCE(AVG(rating),0) AS average FROM reviews WHERE product_id=?', [$id])->fetch();

// รีวิวทั้งหมด + ตรวจว่าผู้รีวิวเคยซื้อสินค้านี้และชำระแล้วหรือไม่ (bought = 1 → ติดป้าย "ซื้อสินค้าแล้ว")
$reviews = q('SELECT r.*, u.name, u.avatar, u.verify_status,
        EXISTS(SELECT 1 FROM order_items i JOIN orders o ON o.id = i.order_id
               WHERE o.user_id = r.user_id AND i.product_id = r.product_id
               AND o.status IN (' . PAID_SQL . ')) AS bought
    FROM reviews r JOIN users u ON u.id = r.user_id
    WHERE r.product_id = ? ORDER BY r.id DESC', [$id])->fetchAll();

// สินค้าที่เกี่ยวข้อง 4 ชิ้น: หมวดเดียวกันก่อน → หมวดหลักเดียวกัน → ที่เหลือเรียงใหม่สุด
$path   = category_path($product['category_id']);   // [หมวดหลัก, หมวดย่อย] ใช้ทำ breadcrumb ด้วย
$topId  = $path ? (int)$path[0]['id'] : 0;
$related = q('SELECT * FROM products WHERE id<>?
    ORDER BY (category_id=?) DESC,
             (category_id IN (SELECT id FROM categories WHERE id=? OR parent_id=?)) DESC, id DESC LIMIT 4',
    [$id, (int)$product['category_id'], $topId, $topId])->fetchAll();

head($product['name']);
?>
<p><a href="products.php<?= $topId ? '?c=' . (int)$product['category_id'] : '' ?>">← กลับไปเลือกสินค้า</a></p>

<!-- ส่วนบน: รูปใหญ่ + ข้อมูลหลัก + ปุ่มใส่ตะกร้า -->
<div class="pd card">
  <?= pbox($product, 'big') ?>
  <div>
    <?php foreach ($path as $c): ?>
      <a class="tag" href="products.php?c=<?= $c['id'] ?>" style="text-decoration:none"><?= e($c['icon'] . ' ' . $c['name']) ?></a>
    <?php endforeach; ?>
    <h1 style="margin:8px 0"><?= e($product['name']) ?></h1>
    <p class="stars">
      <?php if ($stats['cnt'] > 0): ?>
        <?= stars($stats['average']) ?> <?= number_format($stats['average'], 1) ?> <span class="mut">(<?= $stats['cnt'] ?> รีวิว)</span>
      <?php else: ?>
        <span class="mut">ยังไม่มีรีวิว</span>
      <?php endif; ?>
    </p>
    <p class="price" style="font-size:30px;margin:6px 0"><?= baht($product['price']) ?></p>
    <p><?= e($product['descr']) ?></p>
    <p class="mut<?= $product['stock'] <= 5 ? ' low' : '' ?>"><?= $product['stock'] > 0 ? 'คงเหลือ ' . $product['stock'] . ' ชิ้น' : 'สินค้าหมด' ?></p>
    <form method="post" action="cart.php" style="display:flex;gap:8px;max-width:320px">
      <?= csrf() ?>
      <input type="hidden" name="a" value="add">
      <input type="hidden" name="pid" value="<?= $product['id'] ?>">
      <input type="hidden" name="back" value="product.php?id=<?= $product['id'] ?>">
      <input type="number" name="qty" value="1" min="1" max="<?= max(1, $product['stock']) ?>" style="width:80px">
      <button style="flex:1" <?= $product['stock'] < 1 ? 'disabled' : '' ?>>🛒 หยิบใส่ตะกร้า</button>
    </form>
    <p class="mut" style="margin-top:10px"><a href="report.php?type=product&id=<?= $product['id'] ?>">🚩 แจ้งปัญหาสินค้านี้</a></p>
  </div>
</div>

<!-- รายละเอียดเต็ม (ถ้าแอดมินยังไม่กรอก จะแสดงคำอธิบายสั้นแทน) -->
<div class="card">
  <h2>รายละเอียดสินค้า</h2>
  <p><?= nl2br(e($product['detail'] ? $product['detail'] : $product['descr'])) ?></p>
</div>

<!-- รีวิว -->
<div class="card" id="reviews">
  <h2>รีวิวจากลูกค้า (<?= $stats['cnt'] ?>)</h2>

  <?php foreach ($reviews as $r): ?>
    <div class="rv">
      <?= avatar($r, 28) ?> <b><?= e(explode(' ', $r['name'])[0]) ?></b> <?= verified_badge($r) ?>
      <?php if ($r['bought']): ?><span class="tag">✔ ซื้อสินค้าแล้ว</span><?php endif; ?>
      <span class="stars"><?= stars($r['rating']) ?></span>
      <span class="mut"><?= e($r['created']) ?></span>
      <p><?= nl2br(e($r['body'])) ?></p>
      <?php if ($r['img']): ?>
        <a href="uploads/<?= e($r['img']) ?>" target="_blank"><img src="uploads/<?= e($r['img']) ?>" alt="รูปรีวิว"></a>
      <?php endif; ?>
      <?php if ($user && $user['role'] !== 'admin' && $r['user_id'] != $user['id']): ?>
        <a class="mut" href="report.php?type=review&id=<?= $r['id'] ?>">🚩 แจ้งรีวิวนี้</a>
      <?php endif; ?>
      <?php if ($user && $user['role'] === 'admin'): ?>
        <form method="post" onsubmit="return confirm('ลบรีวิวนี้?')">
          <?= csrf() ?>
          <input type="hidden" name="a" value="rdel">
          <input type="hidden" name="rid" value="<?= $r['id'] ?>">
          <button class="del sm">ลบรีวิว</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$reviews): ?><p class="mut">ยังไม่มีรีวิว เป็นคนแรกที่รีวิวสินค้านี้</p><?php endif; ?>

  <?php if ($user): ?>
    <!-- ฟอร์มเขียนรีวิว (enctype จำเป็นสำหรับอัปโหลดรูป) -->
    <form method="post" enctype="multipart/form-data" style="margin-top:14px">
      <h3>เขียนรีวิว</h3>
      <?= csrf() ?>
      <input type="hidden" name="a" value="review">
      <p>
        <select name="rating" required>
          <option value="">ให้คะแนน</option>
          <?php for ($i = 5; $i >= 1; $i--): ?>
            <option value="<?= $i ?>"><?= stars($i) ?> (<?= $i ?>)</option>
          <?php endfor; ?>
        </select>
      </p>
      <p><textarea name="body" rows="3" placeholder="บอกเล่าประสบการณ์การใช้สินค้า" required></textarea></p>
      <p><input type="file" name="img" accept="image/*"> <span class="mut">แนบรูปได้ 1 รูป (JPG/PNG/WEBP ไม่เกิน 3MB)</span></p>
      <button>ส่งรีวิว</button>
    </form>
  <?php else: ?>
    <p><a href="login.php">เข้าสู่ระบบ</a> เพื่อเขียนรีวิว</p>
  <?php endif; ?>
</div>

<!-- สินค้าที่เกี่ยวข้อง -->
<h2>สินค้าที่เกี่ยวข้อง</h2>
<div class="grid">
  <?php foreach ($related as $p) { card($p); } ?>
</div>

<?php foot();
