<?php
/**
 * cart.php — ตะกร้าสินค้า + สั่งซื้อ
 * ตะกร้าเก็บใน session:  $_SESSION['cart'] = [รหัสสินค้า => จำนวน, ...]
 * คำสั่งที่รับ (ฟิลด์ a): add (ใส่ตะกร้า) / set (แก้จำนวน) / checkout (ยืนยันสั่งซื้อ)
 */
require __DIR__ . '/includes/config.php';
chk();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

/* ---------- ฟังก์ชันจัดการตะกร้า ---------- */

/**
 * add_to_cart() — เพิ่มสินค้าลงตะกร้า (ไม่เกินสต็อก)
 * คืนค่า URL ที่จะพากลับไป (เฉพาะหน้าที่อนุญาต กัน redirect ไปเว็บอื่น)
 */
function add_to_cart() {
    $id    = (int)($_POST['pid'] ?? 0);
    $qty   = max(1, (int)($_POST['qty'] ?? 1));
    $stock = q('SELECT stock FROM products WHERE id=?', [$id])->fetch();

    $inCart = $_SESSION['cart'][$id] ?? 0;
    if ($stock && $inCart < $stock['stock']) {
        $_SESSION['cart'][$id] = min($stock['stock'], $inCart + $qty);
    } else {
        flash('สต็อกไม่พอ');
    }

    // อนุญาตกลับไปได้เฉพาะ index.php, products.php หรือ product.php?id=ตัวเลข
    $back = $_POST['back'] ?? '';
    if (preg_match('/^(index\.php|products\.php|product\.php\?id=\d+)$/', $back)) {
        return $back;
    }
    return 'cart.php';
}

/** set_qty() — แก้จำนวนสินค้าในตะกร้า (ใส่ 0 = เอาออก) */
function set_qty() {
    $id  = (int)($_POST['pid'] ?? 0);
    $qty = max(0, (int)($_POST['qty'] ?? 0));
    if ($qty > 0) {
        $_SESSION['cart'][$id] = $qty;
    } else {
        unset($_SESSION['cart'][$id]);
    }
}

/** apply_coupon() — ตรวจโค้ดที่พิมพ์ ถ้าใช้ได้จะจำไว้ใน session (ตอนสั่งซื้อจริงจะตรวจซ้ำอีกครั้ง) */
function apply_coupon() {
    $code = coupon_code($_POST['code'] ?? '');
    $sub  = 0;
    foreach ($_SESSION['cart'] as $pid => $qty) {
        $p = q('SELECT price FROM products WHERE id=?', [$pid])->fetch();
        $sub += $p ? $p['price'] * $qty : 0;
    }
    [$c, $msg] = coupon_check($code, me(), $sub);
    if ($c) {
        $_SESSION['coupon'] = $c['code'];
    } else {
        unset($_SESSION['coupon']);
    }
    flash(($c ? '✅ ' : '⚠️ ') . $msg);
}

/**
 * place_order() — ยืนยันสั่งซื้อ
 * ใช้ transaction: ถ้าขั้นตอนไหนพลาด (เช่นสต็อกไม่พอ) จะยกเลิกทั้งหมด ไม่ตัดสต็อกค้าง
 * ขั้นตอน: ล็อกแถวสินค้า → เช็กสต็อก → สร้างออเดอร์ → บันทึกรายการ → ตัดสต็อก → ไปหน้าชำระเงิน
 */
function place_order() {
    $user = need();
    $cart = $_SESSION['cart'];

    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['addr'] ?? '');
    $method  = $_POST['pm'] ?? '';     // pp / bank / cod

    // พิกัดที่ปักหมุด (ไม่บังคับ) ต้องเป็นตัวเลขและอยู่ในช่วงที่เป็นไปได้
    $lat = is_numeric($_POST['lat'] ?? '') ? (float)$_POST['lat'] : null;
    $lng = is_numeric($_POST['lng'] ?? '') ? (float)$_POST['lng'] : null;
    if ($lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180) {
        $lat = null;
        $lng = null;
    }

    $codBlocked = ($method === 'cod' && setting('cod', '1') !== '1');   // ร้านปิดรับปลายทางอยู่
    if (!$cart || $name === '' || $phone === '' || $address === '' || !in_array($method, ['pp', 'bank', 'cod']) || $codBlocked) {
        flash('กรอกข้อมูลให้ครบ');
        return;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // 1) ตรวจสต็อก (FOR UPDATE = ล็อกแถวไว้ กันสองคนซื้อชิ้นสุดท้ายพร้อมกัน)
        $subtotal = 0;
        $lines = [];
        foreach ($cart as $productId => $qty) {
            $p = q('SELECT * FROM products WHERE id=? FOR UPDATE', [$productId])->fetch();
            if (!$p || $p['stock'] < $qty) {
                throw new Exception('สินค้า ' . ($p['name'] ?? '') . ' สต็อกไม่พอ');
            }
            $subtotal += $p['price'] * $qty;
            $lines[] = ['product' => $p, 'qty' => $qty];
        }

        // 2) คิดยอดใหม่ที่ฝั่งเซิร์ฟเวอร์ทั้งหมด: โค้ดส่วนลด → ค่าส่งตามระยะ → VAT (ไม่เชื่อตัวเลขจากเบราว์เซอร์)
        $coupon = null;
        $off    = 0.0;
        if (!empty($_SESSION['coupon'])) {
            [$coupon, $cmsg, $off] = coupon_check($_SESSION['coupon'], $user, $subtotal);
            if (!$coupon) {
                unset($_SESSION['coupon']);
                throw new Exception('โค้ดส่วนลดใช้ไม่ได้แล้ว: ' . $cmsg . ' (ยังไม่ได้สั่งซื้อ ตรวจยอดอีกครั้ง)');
            }
        }
        $calc = calc_order($subtotal, $off, $lat, $lng);
        if (!$calc['can_ship']) {
            throw new Exception($calc['ship_note']);
        }

        // 3) สร้างออเดอร์ (เก็บเงินปลายทางเริ่มที่สถานะ "เก็บเงินปลายทาง" นอกนั้น "รอชำระเงิน")
        $status = ($method === 'cod') ? 'เก็บเงินปลายทาง' : 'รอชำระเงิน';
        q('INSERT INTO orders(user_id,name,phone,addr,lat,lng,total,pm,status,subtotal,discount,shipping,distance_km,vat_rate,vat_mode,vat,coupon)
           VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
          [$user['id'], $name, $phone, $address, $lat, $lng, $calc['total'], $method, $status,
           $calc['subtotal'], $calc['discount'], $calc['shipping'], $calc['km'], $calc['vat_rate'], $calc['vat_mode'], $calc['vat'],
           $coupon ? $coupon['code'] : null]);
        $orderId = $pdo->lastInsertId();
        log_event($orderId, $status, 'สร้างออเดอร์');   // บันทึกจุดเริ่มต้นของไทม์ไลน์ติดตาม

        // 4) ตัดสิทธิ์โค้ด (เงื่อนไข used < max_uses กันสองคนใช้สิทธิ์สุดท้ายพร้อมกัน)
        if ($coupon) {
            $ok = q('UPDATE coupons SET used = used + 1 WHERE id=? AND (max_uses = 0 OR used < max_uses)', [$coupon['id']])->rowCount();
            if (!$ok) {
                throw new Exception('โค้ดส่วนลดถูกใช้ครบสิทธิ์พอดี กรุณาตรวจยอดอีกครั้ง');
            }
            q('INSERT INTO coupon_uses(coupon_id,user_id,order_id) VALUES(?,?,?)', [$coupon['id'], $user['id'], $orderId]);
        }

        // 5) บันทึกรายการสินค้า + ตัดสต็อก
        foreach ($lines as $line) {
            $p = $line['product'];
            q('INSERT INTO order_items(order_id,product_id,name,price,qty) VALUES(?,?,?,?,?)',
              [$orderId, $p['id'], $p['name'], $p['price'], $line['qty']]);
            q('UPDATE products SET stock = stock - ? WHERE id=?', [$line['qty'], $p['id']]);
        }

        $pdo->commit();
        $_SESSION['cart'] = [];                       // ล้างตะกร้า
        unset($_SESSION['coupon']);                   // โค้ดใช้แล้ว
        header('Location: pay.php?id=' . $orderId);   // ไปหน้าชำระเงิน
        exit;
    } catch (Exception $ex) {
        $pdo->rollBack();                             // ยกเลิกทั้งหมด
        flash($ex->getMessage());
    }
}

/* ---------- รับคำสั่ง POST แล้วพากลับมาหน้าตะกร้า (กันกดรีเฟรชแล้วส่งซ้ำ) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['a'] ?? '';
    $back   = 'cart.php';
    if ($action === 'add') {
        $back = add_to_cart();
    } elseif ($action === 'set') {
        set_qty();
    } elseif ($action === 'coupon') {
        apply_coupon();
    } elseif ($action === 'coupon_off') {
        unset($_SESSION['coupon']);
        flash('เอาโค้ดส่วนลดออกแล้ว');
    } elseif ($action === 'checkout') {
        place_order();
    }
    header('Location: ' . $back);
    exit;
}

/* ---------- โหลดสินค้าในตะกร้าเพื่อแสดงผล ---------- */
$cart  = $_SESSION['cart'];
$rows  = [];
if ($cart) {
    $marks = implode(',', array_fill(0, count($cart), '?'));   // สร้าง ?,?,? ตามจำนวนสินค้า
    $rows  = q("SELECT * FROM products WHERE id IN ($marks)", array_keys($cart))->fetchAll();
}
$total = 0;
foreach ($rows as $p) {
    $total += $p['price'] * $cart[$p['id']];   // ราคาสินค้ารวม (ใช้คิดส่วนลด/ค่าส่ง/ภาษีก่อนแสดงผล)
}
$user  = me();
// หมุดบ้านที่บันทึกไว้ในโปรไฟล์ (ถ้ามี) ใช้เป็นจุดเริ่มต้นบนแผนที่
$prefill = ($user && $user['lat'] !== null) ? [(float)$user['lat'], (float)$user['lng']] : null;

// ส่วนลดจากโค้ดที่ใส่ไว้ + ตัวอย่างยอดสุทธิ (ค่าส่งเริ่มต้นคิดจากหมุดบ้านในโปรไฟล์ ถ้ายังไม่มีจะใช้ค่าส่งมาตรฐาน แล้วปรับตามหมุดที่ปักด้านล่างแบบสด)
$coupon = null; $off = 0.0; $couponMsg = '';
if (!empty($_SESSION['coupon'])) {
    [$coupon, $couponMsg, $off] = coupon_check($_SESSION['coupon'], $user, $total);
    if (!$coupon) {
        unset($_SESSION['coupon']);
    }
}
$cfg  = shop_fee_config();
$calc = calc_order($total, $off, $prefill ? $prefill[0] : null, $prefill ? $prefill[1] : null);

head('ตะกร้าสินค้า');
?>
<h2>🛒 ตะกร้าสินค้า</h2>

<?php if (!$rows): ?>
  <p class="mut">ยังไม่มีสินค้า <a href="products.php">เลือกซื้อสินค้า</a></p>
<?php else: ?>

  <!-- รายการสินค้าในตะกร้า -->
  <div class="card scroll">
    <table>
      <?php foreach ($rows as $p): $qty = $cart[$p['id']]; ?>
        <tr>
          <td><div style="display:flex;gap:10px;align-items:center"><?= pthumb($p) ?><span><?= e($p['name']) ?><br><span class="mut"><?= baht($p['price']) ?></span></span></div></td>
          <td>
            <form method="post" style="display:flex;gap:6px">
              <?= csrf() ?>
              <input type="hidden" name="a" value="set">
              <input type="hidden" name="pid" value="<?= $p['id'] ?>">
              <input type="number" name="qty" min="0" max="<?= $p['stock'] ?>" value="<?= $qty ?>" style="width:80px">
              <button class="sm">อัปเดต</button>
            </form>
          </td>
          <td><b><?= baht($p['price'] * $qty) ?></b></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <!-- โค้ดส่วนลด + สรุปยอด (ตัวเลขในสรุปปรับตามหมุดจัดส่งที่ปักด้านล่างแบบสด) -->
  <div class="card cartsum">
    <h3 style="margin-top:0">🎟 โค้ดส่วนลด</h3>
    <?php if ($coupon): ?>
      <form method="post" class="couponbar">
        <?= csrf() ?><input type="hidden" name="a" value="coupon_off">
        <span class="tag ok"><?= e($coupon['code']) ?></span> <span><?= e(coupon_label($coupon)) ?> · ลดให้ <b><?= baht2($off) ?></b></span>
        <button class="ghost sm">เอาออก</button>
      </form>
    <?php else: ?>
      <form method="post" class="couponbar">
        <?= csrf() ?><input type="hidden" name="a" value="coupon">
        <input name="code" maxlength="20" placeholder="พิมพ์โค้ด เช่น WELCOME10" autocomplete="off" style="max-width:260px;text-transform:uppercase">
        <button class="sm">ใช้โค้ด</button>
      </form>
    <?php endif; ?>
    <div id="sumbox"><?= order_summary_html($calc, $coupon ? $coupon['code'] : '') ?></div>
    <p class="mut" id="shipnote" style="font-size:12.5px;margin:6px 0 0"><?= e($calc['ship_note']) ?></p>
  </div>

  <?php if (!$user): ?>
    <!-- ยังไม่ล็อกอิน: ต้องเข้าสู่ระบบก่อนสั่งซื้อ -->
    <div class="card">กรุณาเข้าสู่ระบบก่อนสั่งซื้อ
      <a class="btn" href="login.php">เข้าสู่ระบบ</a> <a class="btn ghost" href="register.php">สมัครสมาชิก</a>
    </div>
  <?php else: ?>
    <!-- ฟอร์มข้อมูลจัดส่ง + แผนที่ปักหมุด + เลือกวิธีชำระเงิน -->
    <form method="post" class="card">
      <h2>ข้อมูลจัดส่ง</h2>
      <?= csrf() ?>
      <input type="hidden" name="a" value="checkout">
      <div class="form">
        <input name="name" value="<?= e($user['name']) ?>" placeholder="ชื่อ-นามสกุล" required>
        <input name="phone" value="<?= e($user['phone']) ?>" placeholder="เบอร์โทร" required>
        <textarea class="full" name="addr" rows="3" placeholder="ที่อยู่จัดส่ง" required><?= e($user['addr']) ?></textarea>

        <div class="full">
          <div class="mapbar"><b>📍 หมุดจุดจัดส่ง</b>
            <label class="autopin"><input type="checkbox" id="autopin"> ปักหมุดอัตโนมัติตามที่อยู่</label>
            <button type="button" class="ghost sm" id="findpin">🔎 หาพิกัดจากที่อยู่</button></div>
          <p class="mut" id="pinstat" style="margin:4px 0"></p>
          <div id="pick" style="height:240px;border-radius:10px"></div>
          <button type="button" class="ghost sm" style="margin-top:6px" onclick="useMyLocation()">ใช้ตำแหน่งปัจจุบัน</button>
        </div>
        <input type="hidden" name="lat" id="lat">
        <input type="hidden" name="lng" id="lng">

        <select class="full" name="pm">
          <option value="pp">PromptPay (สแกน QR)</option>
          <option value="bank">โอนผ่านบัญชีธนาคาร</option>
          <?php if (setting('cod', '1') === '1'): ?><option value="cod">เก็บเงินปลายทาง</option><?php endif; ?>
        </select>
      </div>
      <p class="mut" style="font-size:12px">🔒 ร้านใช้ชื่อ เบอร์โทร ที่อยู่ และพิกัดของคุณเพื่อจัดส่งสินค้าและดูแลหลังการขาย (ส่งให้บริษัทขนส่งเท่าที่จำเป็น) อ่าน <a href="policy.php?p=privacy">นโยบายความเป็นส่วนตัว</a></p>
      <button id="submitbtn">ยืนยันสั่งซื้อ</button>
    </form>

    <!-- แผนที่ปักหมุดด้วย Leaflet + OpenStreetMap (โหลดจากอินเทอร์เน็ต) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
    <script src="assets/js/geo.js"></script>
    <script>
    // แผนที่จุดจัดส่ง: พิมพ์ที่อยู่แล้วระบบปักหมุดให้เอง (geo.js) · ลากหมุดหรือแตะแผนที่เพื่อปรับเองได้ (ไม่บังคับ)
    var map = L.map('pick').setView([13.7563, 100.5018], 10);   // เริ่มที่กรุงเทพฯ
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '© OpenStreetMap'}).addTo(map);
    var addrEl = document.querySelector('[name=addr]');
    var prefill = <?= json_encode($prefill) ?>;                   // หมุดบ้านจากโปรไฟล์ (ถ้ามี)
    var autoEl = document.getElementById('autopin');
    autoEl.checked = !prefill;                                    // มีหมุดจากโปรไฟล์แล้ว = ไม่ทับอัตโนมัติ จนกว่าจะติ๊กเอง
    var pin = AddressPin.init({
      map: map, latEl: document.getElementById('lat'), lngEl: document.getElementById('lng'),
      statusEl: document.getElementById('pinstat'), autoEl: autoEl, findBtn: document.getElementById('findpin'),
      fields: [addrEl], initial: prefill, runNow: !prefill && addrEl.value.trim().length > 5,
      getAddress: function () { return addrEl.value; }
    });

    // useMyLocation() — ขอตำแหน่งจากเบราว์เซอร์ (ผู้ใช้ต้องกดอนุญาต) นับเป็นการปักเอง
    function useMyLocation() {
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function (p) { pin.manual(p.coords.latitude, p.coords.longitude, 'ใช้ตำแหน่งปัจจุบันแล้ว'); });
      }
    }
    </script>
    <script>
    // คำนวณตัวอย่างยอดสดเมื่อปักหมุด (สูตรเดียวกับ shop_lib.php ฝั่งเซิร์ฟเวอร์ — ยอดจริงเซิร์ฟเวอร์คิดซ้ำตอนกดสั่งซื้อ)
    var FEE = <?= json_encode(['goods' => $calc['goods'], 'subtotal' => $calc['subtotal'], 'discount' => $calc['discount'], 'c' => $cfg, 'code' => $coupon ? $coupon['code'] : ''], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    (function () {
      var C = FEE.c, latEl = document.getElementById('lat'), lngEl = document.getElementById('lng'), last = '';
      function rad(x) { return x * Math.PI / 180; }
      function km(a, b, c, d) {
        var h = Math.pow(Math.sin(rad(c - a) / 2), 2) + Math.cos(rad(a)) * Math.cos(rad(c)) * Math.pow(Math.sin(rad(d - b) / 2), 2);
        return 2 * 6371 * Math.asin(Math.min(1, Math.sqrt(h)));
      }
      function money(x) { return Math.round(x * 100 + 1e-6) / 100; }
      function fmt(x) { var f = Math.round(x * 100) % 100 === 0; return '฿' + x.toLocaleString('en-US', {minimumFractionDigits: f ? 0 : 2, maximumFractionDigits: f ? 0 : 2}); }
      function render() {
        var lat = parseFloat(latEl.value), lng = parseFloat(lngEl.value), has = !isNaN(lat) && !isNaN(lng), d = has ? Math.round(km(C.shop_lat, C.shop_lng, lat, lng) * 10) / 10 : null;
        var ship, note, ok = true;
        if (d !== null && C.ship_max_km > 0 && d > C.ship_max_km) { ship = 0; ok = false; note = '⚠️ อยู่ไกลเกิน ' + C.ship_max_km + ' กม. ร้านยังไม่จัดส่งถึง'; }
        else if (C.ship_free_over > 0 && FEE.goods >= C.ship_free_over) { ship = 0; note = 'ส่งฟรี (ซื้อครบ ฿' + C.ship_free_over.toLocaleString('en-US') + ')'; }
        else if (d === null) { ship = C.ship_nopin; note = 'ค่าส่งมาตรฐาน — ปักหมุดบนแผนที่เพื่อคิดตามระยะทางจริง'; }
        else {
          ship = C.ship_base + Math.max(0, d - C.ship_free_km) * C.ship_per_km; ship = Math.ceil(ship / 5) * 5;
          if (C.ship_max > 0) ship = Math.min(ship, C.ship_max);
          note = 'ระยะทางประมาณ ' + d.toFixed(1) + ' กม.';
        }
        var base = money(FEE.goods + ship), r = C.vat_rate, vat = 0, total = base;
        if (r > 0 && C.vat_mode === 'excluded') { vat = money(base * r / 100); total = money(base + vat); }
        else if (r > 0) { vat = money(base * r / (100 + r)); }
        var rate = String(parseFloat(r.toFixed(2)));
        var h = '<table class="sumtab"><tr><td>ราคาสินค้า</td><td>' + fmt(FEE.subtotal) + '</td></tr>';
        if (FEE.discount > 0) h += '<tr class="disc"><td>ส่วนลด <span class="tag">' + FEE.code + '</span></td><td>−' + fmt(FEE.discount) + '</td></tr>';
        h += '<tr><td>ค่าจัดส่ง' + (d !== null ? ' <span class="mut">(~' + d.toFixed(1) + ' กม.)</span>' : '') + '</td><td>' + (ship > 0 ? fmt(ship) : '<b class="free">ฟรี</b>') + '</td></tr>';
        if (r > 0) h += '<tr class="' + (C.vat_mode === 'excluded' ? '' : 'sub') + '"><td>' + (C.vat_mode === 'excluded' ? 'ภาษีมูลค่าเพิ่ม ' : 'รวมภาษีมูลค่าเพิ่ม ') + rate + '%' + (C.vat_mode === 'excluded' ? '' : ' แล้ว') + '</td><td>' + fmt(vat) + '</td></tr>';
        h += '<tr class="grand"><td><b>ยอดที่ต้องชำระ</b></td><td><b class="price">' + fmt(total) + '</b></td></tr></table>';
        document.getElementById('sumbox').innerHTML = h;
        document.getElementById('shipnote').textContent = note;
        document.getElementById('submitbtn').disabled = !ok;
      }
      setInterval(function () { var k = latEl.value + ',' + lngEl.value; if (k !== last) { last = k; render(); } }, 500);
      render();
    })();
    </script>
  <?php endif; ?>
<?php endif; ?>

<?php foot();
