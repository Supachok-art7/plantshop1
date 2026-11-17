<?php
/**
 * admin.php — หลังบ้านสำหรับแอดมิน (เข้าได้เฉพาะบัญชีที่ role = admin)
 * โครงสร้างไฟล์:
 *   ส่วน A: ตรวจสิทธิ์ + รับคำสั่ง POST (บันทึก/ลบ/เปลี่ยนสถานะ)  →  ฟังก์ชัน do_xxx()
 *   ส่วน B: แสดงผลแต่ละแท็บ  →  ฟังก์ชัน tab_xxx()
 * แท็บเลือกด้วย URL เช่น admin.php?t=orders
 *   t = dash, orders, track(&id=), products, categories, members, verify, chat, reports(&s=สถานะ), map, promo, pay, shop
 */
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/pdpa_admin.php';   // ระบบ PDPA ของหลังบ้าน (แท็บ PDPA)
chk();
need('admin');                       // ไม่ใช่แอดมิน = เข้าไม่ได้

$tab = $_GET['t'] ?? 'dash';         // แท็บที่เปิดอยู่
$uid = (int)($_GET['uid'] ?? 0);     // ใช้ในแท็บแชท: ลูกค้าที่เลือกคุยอยู่

/** สีพื้นสไลด์ที่ระบบสุ่มให้ตอนเพิ่มโปรโมท (ผู้ใช้ไม่ต้องเลือกเอง กันใส่ค่าแปลก) */
const SLIDE_COLORS = ['#15803d,#4ade80', '#0f766e,#5eead4', '#4d7c0f,#bef264'];

/**
 * fields() — สร้างช่องซ่อนมาตรฐานของทุกฟอร์มในหลังบ้าน: token + ชื่อคำสั่ง (a) + รหัสแถว (id)
 * วิธีใช้:  <form method="post"><?= fields('prod', 5) ?> ... </form>
 */
function fields($action, $id = 0) {
    return csrf()
         . '<input type="hidden" name="a" value="' . e($action) . '">'
         . '<input type="hidden" name="id" value="' . (int)$id . '">';
}


/* ============================================================
 * ส่วน A: รับคำสั่ง POST
 * ============================================================ */

/**
 * do_product() — เพิ่มสินค้าใหม่ (id = 0) หรือแก้สินค้าเดิม (id > 0)
 *   แก้ได้ทุกอย่าง: ชื่อ หมวด ราคา สต็อก อีโมจิ คำอธิบายสั้น/เต็ม และรูปสินค้า
 *   รูป: เลือกไฟล์ใหม่ = เปลี่ยนรูป (ไฟล์เก่าถูกลบ) / ติ๊ก "ลบรูปเดิม" = กลับไปใช้อีโมจิ
 */
function do_product($id) {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        flash('กรุณาใส่ชื่อสินค้า');
        return;
    }
    $emoji = trim($_POST['emoji'] ?? '');

    // ---- จัดการรูป ----
    $old   = $id > 0 ? q('SELECT image FROM products WHERE id=?', [$id])->fetchColumn() : null;
    $image = $old ?: null;
    $warn  = '';
    $file  = $_FILES['image'] ?? null;
    if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        $new = save_img($file, 'prod');            // รับ JPG / PNG / WEBP ไม่เกิน 3MB
        if ($new) {
            del_product_img($old);
            $image = $new;
        } else {
            $warn = ' (แต่รูปไม่ผ่าน: ต้องเป็น JPG/PNG/WEBP ขนาดไม่เกิน 3MB)';
        }
    } elseif (!empty($_POST['remove_image'])) {
        del_product_img($old);
        $image = null;
    }

    $data = [
        $name,
        (int)($_POST['category_id'] ?? 0) ?: null,   // หมวดหมู่ (รหัสจากตาราง categories)
        (float)($_POST['price'] ?? 0),
        (int)($_POST['stock'] ?? 0),
        $emoji !== '' ? $emoji : '🌼',
        trim($_POST['descr'] ?? ''),
        trim($_POST['detail'] ?? ''),
        $image,
    ];
    if ($id > 0) {
        $data[] = $id;
        q('UPDATE products SET name=?, category_id=?, price=?, stock=?, emoji=?, descr=?, detail=?, image=? WHERE id=?', $data);
    } else {
        q('INSERT INTO products(name,category_id,price,stock,emoji,descr,detail,image) VALUES(?,?,?,?,?,?,?,?)', $data);
    }
    flash('บันทึกสินค้าแล้ว' . $warn);
}

/** do_product_delete() — ลบสินค้า + ไฟล์รูป (ออเดอร์เก่ายังเก็บชื่อ/ราคาไว้เอง จึงไม่กระทบประวัติ) */
function do_product_delete($id) {
    $image = q('SELECT image FROM products WHERE id=?', [$id])->fetchColumn();
    q('DELETE FROM products WHERE id=?', [$id]);
    del_product_img($image);
    flash('ลบสินค้าแล้ว');
}

/**
 * set_order_status() — เปลี่ยนสถานะออเดอร์ + บันทึกลงไทม์ไลน์ติดตาม (order_events)
 *   ถ้าเปลี่ยนเป็น "ยกเลิก" จะคืนสต็อกให้อัตโนมัติ และออเดอร์ที่ยกเลิกแล้วแก้ไม่ได้อีก
 *   $note = หมายเหตุ   $lat,$lng = ตำแหน่งพัสดุตอนนี้ (ไม่บังคับ)
 * คืน true ถ้าทำสำเร็จ
 */
function set_order_status($id, $status, $note = '', $lat = null, $lng = null) {
    $order = q('SELECT status FROM orders WHERE id=?', [$id])->fetch();
    if (!$order || $order['status'] === 'ยกเลิก' || !in_array($status, ORDER_STATUS)) {
        return false;
    }
    $changed = ($order['status'] !== $status);
    if ($changed) {
        q('UPDATE orders SET status=? WHERE id=?', [$status, $id]);
        if ($status === 'ยกเลิก') {
            q('UPDATE products p JOIN order_items i ON i.product_id = p.id
               SET p.stock = p.stock + i.qty WHERE i.order_id = ?', [$id]);
        }
    }
    if ($changed || $note !== '' || $lat !== null) {
        log_event($id, $status, $note, $lat, $lng);
    }
    return true;
}

/** do_order_status() — เปลี่ยนสถานะจาก dropdown ในรายการออเดอร์ */
function do_order_status($id) {
    if (set_order_status($id, $_POST['status'] ?? '')) {
        flash('อัปเดตสถานะแล้ว');
    }
}

/** do_track() — บันทึกความคืบหน้า: สถานะ + หมายเหตุ + ขนส่ง/เลขพัสดุ + ตำแหน่งพัสดุบนแผนที่ */
function do_track($id) {
    $carrier  = trim($_POST['carrier'] ?? '');
    $tracking = trim($_POST['tracking_no'] ?? '');
    q('UPDATE orders SET carrier=?, tracking_no=? WHERE id=?', [$carrier ?: null, $tracking ?: null, $id]);

    $lat = is_numeric($_POST['lat'] ?? '') ? (float)$_POST['lat'] : null;
    $lng = is_numeric($_POST['lng'] ?? '') ? (float)$_POST['lng'] : null;
    if ($lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180) {
        $lat = null;
        $lng = null;
    }
    if (set_order_status($id, $_POST['status'] ?? '', trim($_POST['note'] ?? ''), $lat, $lng)) {
        flash('บันทึกความคืบหน้าแล้ว');
    }
}

/** do_category() — เพิ่ม/แก้หมวดหมู่ (จำกัด 2 ระดับ: หมวดหลัก → หมวดย่อย) */
function do_category($id) {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        return;
    }
    $parent = (int)($_POST['parent_id'] ?? 0) ?: null;
    if ($parent) {
        $p = q('SELECT parent_id FROM categories WHERE id=?', [$parent])->fetch();
        if (!$p || $p['parent_id'] || $parent == $id) {
            $parent = null;   // ต้องเป็นหมวดหลักเท่านั้น และห้ามเป็นลูกของตัวเอง
        }
    }
    if ($id && $parent && q('SELECT 1 FROM categories WHERE parent_id=?', [$id])->fetch()) {
        flash('หมวดที่มีหมวดย่อยอยู่ ย้ายไปเป็นหมวดย่อยของหมวดอื่นไม่ได้');
        return;
    }
    $icon = trim($_POST['icon'] ?? '');
    $data = [$parent, $name, $icon !== '' ? $icon : '📁', (int)($_POST['sort'] ?? 0)];
    if ($id) {
        $data[] = $id;
        q('UPDATE categories SET parent_id=?, name=?, icon=?, sort=? WHERE id=?', $data);
    } else {
        q('INSERT INTO categories(parent_id,name,icon,sort) VALUES(?,?,?,?)', $data);
    }
    flash('บันทึกหมวดหมู่แล้ว');
}

/** do_category_delete() — ลบหมวดหมู่ (ลบไม่ได้ถ้ายังมีหมวดย่อยหรือสินค้าอยู่ในหมวด) */
function do_category_delete($id) {
    $busy = q('SELECT 1 FROM categories WHERE parent_id=?', [$id])->fetch()
         || q('SELECT 1 FROM products WHERE category_id=?', [$id])->fetch();
    if ($busy) {
        flash('ลบไม่ได้: ยังมีหมวดย่อยหรือสินค้าอยู่ในหมวดนี้ กรุณาย้ายออกก่อน');
        return;
    }
    q('DELETE FROM categories WHERE id=?', [$id]);
    flash('ลบหมวดหมู่แล้ว');
}

/**
 * do_verify() — อนุมัติ/ปฏิเสธการยืนยันตัวตนของสมาชิก
 * เอกสาร (รูปบัตร/เซลฟี่) จะถูกลบทิ้งทันทีไม่ว่าผลจะเป็นอย่างไร เก็บเฉพาะผลการตรวจ
 */
function do_verify($id) {
    audit('verify_review', 'user#' . $id);   // PDPA: จดว่าแอดมินตรวจเอกสารยืนยันตัวตนของใคร
    $u = q("SELECT * FROM users WHERE id=? AND verify_status='pending'", [$id])->fetch();
    if (!$u) {
        return;
    }
    $approve = (($_POST['decision'] ?? '') === 'approve');
    foreach ([$u['id_img'], $u['selfie_img']] as $file) {
        if ($file) {
            @unlink(ROOT_DIR . '/private/' . basename($file));
        }
    }
    $note = trim($_POST['note'] ?? '');
    q('UPDATE users SET verify_status=?, verify_note=?, id_img=NULL, selfie_img=NULL WHERE id=?',
      [$approve ? 'verified' : 'rejected', $approve ? null : ($note !== '' ? $note : 'เอกสารไม่ชัดเจน กรุณาส่งใหม่'), $id]);
    flash($approve ? 'อนุมัติการยืนยันตัวตนแล้ว' : 'ปฏิเสธแล้ว');
}

/** do_slide_add() — เพิ่มสไลด์โฆษณา/โปรโมทหน้าแรก */
function do_slide_add() {
    $title = trim($_POST['title'] ?? '');
    if ($title === '') {
        return;
    }
    $emoji = trim($_POST['emoji'] ?? '');
    q('INSERT INTO slides(title,body,emoji,bg) VALUES(?,?,?,?)',
      [$title, trim($_POST['body'] ?? ''), $emoji !== '' ? $emoji : '🎉', SLIDE_COLORS[array_rand(SLIDE_COLORS)]]);
    flash('เพิ่มสไลด์แล้ว');
}

/** do_slide_delete() — ลบสไลด์โฆษณา */
function do_slide_delete($id) {
    q('DELETE FROM slides WHERE id=?', [$id]);
}

/** save_setting() — บันทึก/อัปเดตค่าตั้งค่า 1 รายการ (ไม่มี = เพิ่ม, มีแล้ว = แก้) */
function save_setting($key, $value) {
    q('INSERT INTO settings(k,v) VALUES(?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)', [$key, $value]);
}

/** do_pay_settings() — บันทึกการตั้งค่าชำระเงิน: PromptPay, บัญชีธนาคาร, เปิด/ปิดเก็บเงินปลายทาง */
function do_pay_settings() {
    foreach (['pp', 'bank', 'acc', 'aname', 'shop_lat', 'shop_lng'] as $key) {
        save_setting($key, trim($_POST[$key] ?? ''));
    }
    save_setting('cod', isset($_POST['cod']) ? '1' : '0');   // checkbox: ติ๊ก = มีค่า
    flash('บันทึกการชำระเงินแล้ว');
}

/**
 * do_report() — แอดมินตอบรายงาน + เปลี่ยนสถานะ (new → working → resolved/rejected)
 * ถ้าตอบหรือเปลี่ยนสถานะ จะตั้ง user_seen=0 เพื่อให้สมาชิกเห็นตัวเลขแจ้งเตือนที่เมนู
 */
function do_report($id) {
    $r = q('SELECT * FROM reports WHERE id=?', [$id])->fetch();
    if (!$r) {
        return;
    }
    $status = $_POST['status'] ?? '';
    if (!isset(REPORT_STATUS[$status])) {
        $status = $r['status'];
    }
    $reply = mb_substr(trim($_POST['reply'] ?? ''), 0, 2000);
    if ($reply === '') {
        $reply = (string)$r['admin_reply'];          // ไม่ได้พิมพ์ใหม่ = คงคำตอบเดิม
    }
    if ($status === $r['status'] && $reply === (string)$r['admin_reply']) {
        flash('ไม่มีการเปลี่ยนแปลง');
        return;
    }
    q('UPDATE reports SET status=?, admin_reply=?, user_seen=0, updated=NOW() WHERE id=?', [$status, $reply !== '' ? $reply : null, $id]);
    flash('บันทึกรายงาน #' . $id . ' แล้ว');
}

/** do_report_delete() — ลบรายงาน (เฉพาะที่ปิดแล้ว) พร้อมลบรูปประกอบ */
function do_report_delete($id) {
    $r = q('SELECT * FROM reports WHERE id=?', [$id])->fetch();
    if (!$r || in_array($r['status'], ['new', 'working'])) {
        flash('ลบได้เฉพาะรายงานที่แก้ไขแล้วหรือปิดเรื่องแล้ว');
        return;
    }
    if ($r['img']) {
        @unlink(ROOT_DIR . '/uploads/' . basename($r['img']));
    }
    q('DELETE FROM reports WHERE id=?', [$id]);
    flash('ลบรายงานแล้ว');
}

/** do_shop_settings() — บันทึกข้อมูลติดต่อร้าน (แสดงในหน้าติดต่อเรา และท้ายเว็บ) */
function do_shop_settings() {
    foreach (['shop_phone', 'shop_email', 'shop_line', 'shop_addr', 'shop_hours'] as $key) {
        save_setting($key, mb_substr(trim($_POST[$key] ?? ''), 0, 250));
    }
    save_setting('demo_mode', isset($_POST['demo_mode']) ? '1' : '0');   // แผงบัญชีทดลองในหน้าเข้าสู่ระบบ

    // รหัส Google Analytics 4: ว่าง = ไม่ใช้  ถ้าใส่ต้องเป็นรูปแบบ G-XXXXXXXXXX (โหลดเฉพาะเมื่อผู้เยี่ยมชมกด "ยอมรับทั้งหมด")
    $ga = strtoupper(trim($_POST['ga4_id'] ?? ''));
    if ($ga !== '' && !preg_match('/^G-[A-Z0-9]{4,14}$/', $ga)) {
        flash('บันทึกข้อมูลร้านแล้ว แต่รหัส Google Analytics ไม่ถูกต้อง (ต้องเป็นรูปแบบ G-XXXXXXXXXX) จึงไม่ได้บันทึกรหัสนี้');
        return;
    }
    save_setting('ga4_id', $ga);
    flash('บันทึกข้อมูลร้านแล้ว');
}

/** do_fees() — บันทึกตั้งค่าภาษี VAT + ค่าส่งตามระยะทาง (ตัวเลขถูกบังคับให้อยู่ในช่วงที่เหมาะสม) */
function do_fees() {
    $num = function ($key, $min, $max) {
        return (string)min($max, max($min, round((float)($_POST[$key] ?? 0), 2)));
    };
    save_setting('vat_rate', $num('vat_rate', 0, 30));
    save_setting('vat_mode', ($_POST['vat_mode'] ?? '') === 'excluded' ? 'excluded' : 'included');
    save_setting('tax_name', mb_substr(trim($_POST['tax_name'] ?? ''), 0, 120));
    $tax = preg_replace('/\D/', '', $_POST['tax_id'] ?? '');
    save_setting('tax_id', strlen($tax) === 13 ? $tax : '');
    foreach (['ship_base' => [0, 5000], 'ship_free_km' => [0, 500], 'ship_per_km' => [0, 500], 'ship_max' => [0, 20000],
              'ship_max_km' => [0, 3000], 'ship_free_over' => [0, 1000000], 'ship_nopin' => [0, 5000]] as $key => $r) {
        save_setting($key, $num($key, $r[0], $r[1]));
    }
    flash('บันทึกภาษีและค่าส่งแล้ว' . (trim($_POST['tax_id'] ?? '') !== '' && strlen($tax) !== 13 ? ' (เลขผู้เสียภาษีต้องมี 13 หลัก จึงยังไม่ได้บันทึกเลขนี้)' : ''));
}

/** do_coupon_add() — สร้างโค้ดส่วนลดใหม่ */
function do_coupon_add() {
    $code = coupon_code($_POST['code'] ?? '');
    $kind = ($_POST['kind'] ?? '') === 'fixed' ? 'fixed' : 'percent';
    $val  = round((float)($_POST['value'] ?? 0), 2);
    if (strlen($code) < 3) {
        flash('โค้ดต้องยาว 3-20 ตัว (A-Z ตัวเลข - _)');
        return;
    }
    if ($val <= 0 || ($kind === 'percent' && $val > 100)) {
        flash($kind === 'percent' ? 'เปอร์เซ็นต์ต้องอยู่ระหว่าง 1-100' : 'จำนวนเงินที่ลดต้องมากกว่า 0');
        return;
    }
    $date = function ($key) {
        $d = trim($_POST[$key] ?? '');
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
    };
    try {
        q('INSERT INTO coupons(code,kind,value,max_discount,min_total,max_uses,once_per_user,min_level,starts,expires,note) VALUES(?,?,?,?,?,?,?,?,?,?,?)',
          [$code, $kind, $val, max(0, (float)($_POST['max_discount'] ?? 0)), max(0, (float)($_POST['min_total'] ?? 0)),
           max(0, (int)($_POST['max_uses'] ?? 0)), isset($_POST['once']) ? 1 : 0, min(5, max(0, (int)($_POST['min_level'] ?? 0))),
           $date('starts'), $date('expires'), mb_substr(trim($_POST['note'] ?? ''), 0, 120)]);
        flash('สร้างโค้ด ' . $code . ' แล้ว');
    } catch (Throwable $err) {
        flash('มีโค้ด ' . $code . ' อยู่แล้ว');
    }
}

/** do_coupon_toggle() / do_coupon_delete() — เปิด-ปิด / ลบโค้ด (โค้ดที่เคยถูกใช้ ประวัติในออเดอร์ยังอยู่) */
function do_coupon_toggle($id) {
    q('UPDATE coupons SET active = 1 - active WHERE id=?', [$id]);
    flash('เปลี่ยนสถานะโค้ดแล้ว');
}
function do_coupon_delete($id) {
    q('DELETE FROM coupons WHERE id=?', [$id]);
    flash('ลบโค้ดแล้ว');
}

/** do_chat_reply() — แอดมินตอบแชทลูกค้า (sender = 's') */
function do_chat_reply($customerId) {
    $body = trim($_POST['body'] ?? '');
    if ($body !== '' && $customerId > 0) {
        q('INSERT INTO messages(user_id,sender,body) VALUES(?,?,?)', [$customerId, 's', mb_substr($body, 0, 1000)]);
    }
}

// ตัวรับคำสั่ง: ดูค่า a ที่ฟอร์มส่งมา แล้วเรียกฟังก์ชันที่ตรงกัน จากนั้นพากลับหน้าเดิม
$action = $_POST['a'] ?? '';
if ($action !== '') {
    $id = (int)($_POST['id'] ?? 0);
    switch ($action) {
        case 'prod':   do_product($id);           break;
        case 'pdel':   do_product_delete($id);    break;
        case 'status': do_order_status($id);      break;
        case 'track':  do_track($id);             break;
        case 'cat':    do_category($id);          break;
        case 'catdel': do_category_delete($id);   break;
        case 'verify': do_verify($id);            break;
        case 'sadd':   do_slide_add();            break;
        case 'sdel':   do_slide_delete($id);      break;
        case 'pay':    do_pay_settings();         break;
        case 'reply':  do_chat_reply($uid);       break;
        case 'rep':    do_report($id);            break;
        case 'repdel': do_report_delete($id);     break;
        case 'shop':   do_shop_settings();        break;
        case 'fees':   do_fees();                 break;
        case 'cpadd':  do_coupon_add();           break;
        case 'cptog':  do_coupon_toggle($id);     break;
        case 'cpdel':  do_coupon_delete($id);     break;
        case 'dsr':        do_dsr($id);            break;
        case 'dsr_erase':  do_dsr_erase($id);      break;
        case 'breach':     do_breach();            break;
        case 'breach_up':  do_breach_update($id);  break;
        case 'pdpa_set':   do_pdpa_settings();     break;
        case 'ret_run':    do_retention_run();     break;
    }
    header('Location: admin.php?t=' . urlencode($tab) . ($uid ? '&uid=' . $uid : '') . (isset($_GET['id']) ? '&id=' . (int)$_GET['id'] : '') . (isset($_GET['s']) ? '&s=' . urlencode($_GET['s']) : ''));
    exit;
}


/* ============================================================
 * ส่วน B: แสดงผลแต่ละแท็บ
 * ============================================================ */

/** tab_dash() — ภาพรวม: ยอดขาย จำนวนออเดอร์ สมาชิก และสินค้าใกล้หมด */
function tab_dash() {
    $sales   = q('SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN (' . PAID_SQL . ')')->fetchColumn();
    $orders  = q('SELECT COUNT(*) FROM orders')->fetchColumn();
    $pending = q("SELECT COUNT(*) FROM orders WHERE status IN ('รอชำระเงิน','แจ้งชำระแล้ว')")->fetchColumn();
    $members = q("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
    $low     = q('SELECT * FROM products WHERE stock <= 5 ORDER BY stock')->fetchAll();
    $newReports = [];
    try {
        $newReports = q("SELECT r.*, u.name FROM reports r JOIN users u ON u.id=r.user_id WHERE r.status='new' ORDER BY r.id DESC LIMIT 5")->fetchAll();
    } catch (Throwable $err) {
        // ยังไม่ได้รัน update_v4.sql
    }
    ?>
    <div class="stats">
      <a class="card" href="admin.php?t=sales" style="text-decoration:none;color:inherit">ยอดขาย (ชำระแล้ว) · ดูสรุป →<b><?= baht($sales) ?></b></a>
      <div class="card">ออเดอร์ทั้งหมด<b><?= $orders ?></b></div>
      <div class="card">รอตรวจ/ชำระ<b><?= $pending ?></b></div>
      <div class="card">สมาชิก<b><?= $members ?></b></div>
      <a class="card" href="admin.php?t=reports&s=new" style="text-decoration:none;color:inherit">🚩 รายงานใหม่<b class="<?= $newReports ? 'low' : '' ?>"><?= badge_counts()['admin_reports'] ?></b></a>
    </div>
    <?php if ($newReports): ?>
      <h2>🚩 รายงานล่าสุดที่รอตรวจ</h2>
      <?php foreach ($newReports as $r): ?>
        <div class="card"><a href="admin.php?t=reports&s=new#r<?= $r['id'] ?>"><b>#<?= $r['id'] ?> <?= e($r['subject']) ?></b></a>
          <span class="mut">· <?= e($r['name']) ?> · <?= e(REPORT_TYPES[$r['type']] ?? $r['type']) ?> · <?= thai_date($r['created']) ?></span></div>
      <?php endforeach; ?>
    <?php endif; ?>
    <h2>สินค้าใกล้หมด</h2>
    <?php foreach ($low as $p): ?>
      <div class="card"><?= e($p['emoji'] . ' ' . $p['name']) ?> <b class="low">เหลือ <?= $p['stock'] ?></b></div>
    <?php endforeach; ?>
    <?php if (!$low): ?><p class="mut">ไม่มี</p><?php endif; ?>
    <?php
}

/** tab_orders() — รายการออเดอร์ทั้งหมด: ดูสลิป และเปลี่ยนสถานะ */
function tab_orders() {
    $methods = ['pp' => 'PromptPay', 'bank' => 'โอนบัญชี', 'cod' => 'เก็บเงินปลายทาง'];
    foreach (q('SELECT * FROM orders ORDER BY id DESC')->fetchAll() as $o) {
        $items = q('SELECT * FROM order_items WHERE order_id=?', [$o['id']])->fetchAll();
        ?>
        <div class="card">
          <b>#<?= $o['id'] ?></b> <span class="mut"><?= e($o['created']) ?></span><br>
          <?= e($o['name'] . ' · ' . $o['phone']) ?><br>
          <span class="mut"><?= e($o['addr']) ?></span><br>
          <?php foreach ($items as $i): ?><?= e($i['name']) ?> ×<?= $i['qty'] ?>, <?php endforeach; ?>
          <br><span class="mut">ช่องทาง: <?= e($methods[$o['pm']] ?? '-') ?></span>
          <?php if ($o['slip']): ?>
            <br><a href="uploads/<?= e($o['slip']) ?>" target="_blank">
              <img src="uploads/<?= e($o['slip']) ?>" alt="สลิป" style="max-width:160px;border-radius:8px;border:1px solid var(--bd)">
            </a>
          <?php endif; ?>
          <form method="post" action="admin.php?t=orders" style="display:flex;gap:8px;margin-top:8px;align-items:center">
            <?= fields('status', $o['id']) ?>
            <b class="price"><?= baht2($o['total']) ?></b><?php if ((float)($o['discount'] ?? 0) > 0 || (float)($o['shipping'] ?? 0) > 0 || (float)($o['vat'] ?? 0) > 0): ?> <span class="mut" style="font-size:12px">(สินค้า <?= baht2($o['subtotal'] ?? $o['total']) ?><?= (float)$o['discount'] > 0 ? ' · ลด ' . baht2($o['discount']) . ' ' . e((string)$o['coupon']) : '' ?> · ส่ง <?= baht2($o['shipping']) ?><?= $o['distance_km'] !== null ? ' ~' . e($o['distance_km']) . 'กม.' : '' ?> · VAT <?= baht2($o['vat']) ?>)</span><?php endif; ?>
            <select name="status">
              <?php foreach (ORDER_STATUS as $s): ?>
                <option<?= $s === $o['status'] ? ' selected' : '' ?>><?= e($s) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="sm">อัปเดต</button>
            <a class="btn ghost sm" href="admin.php?t=track&id=<?= $o['id'] ?>">📍 ติดตาม</a>
          </form>
        </div>
        <?php
    }
}

/** tab_products() — ฟอร์มเพิ่ม/แก้สินค้า + รายการสินค้า (กดแก้ไข = admin.php?t=products&edit=เลข) */
function tab_products() {
    $blank = ['id' => 0, 'name' => '', 'category_id' => null, 'price' => '', 'stock' => '', 'emoji' => '🌼', 'descr' => '', 'detail' => '', 'image' => null];
    $edit  = q('SELECT * FROM products WHERE id=?', [(int)($_GET['edit'] ?? 0)])->fetch();
    if (!$edit) {
        $edit = $blank;
    }
    $products = q('SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.id')->fetchAll();
    // จัดหมวดหลัก/หมวดย่อยไว้ทำ dropdown
    $parents = [];
    $kids    = [];
    foreach (categories() as $c) {
        if ($c['parent_id']) { $kids[$c['parent_id']][] = $c; } else { $parents[] = $c; }
    }
    ?>
    <form method="post" action="admin.php?t=products" class="card" enctype="multipart/form-data">
      <h2><?= $edit['id'] ? 'แก้ไขสินค้า' : 'เพิ่มสินค้า' ?></h2>
      <?= fields('prod', $edit['id']) ?>
      <div class="form">
        <input class="full" name="name" value="<?= e($edit['name']) ?>" placeholder="ชื่อสินค้า" required>
        <select name="category_id">
          <option value="">— ไม่ระบุหมวด —</option>
          <?php foreach ($parents as $pc): ?>
            <optgroup label="<?= e($pc['icon'] . ' ' . $pc['name']) ?>">
              <option value="<?= $pc['id'] ?>"<?= $edit['category_id'] == $pc['id'] ? ' selected' : '' ?>>ทั่วไปใน <?= e($pc['name']) ?></option>
              <?php foreach ($kids[$pc['id']] ?? [] as $kc): ?>
                <option value="<?= $kc['id'] ?>"<?= $edit['category_id'] == $kc['id'] ? ' selected' : '' ?>><?= e($kc['icon'] . ' ' . $kc['name']) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
        <input name="emoji" value="<?= e($edit['emoji']) ?>" placeholder="อีโมจิ (ใช้เมื่อไม่มีรูป)">
        <input type="number" step="0.01" min="0" name="price" value="<?= e($edit['price']) ?>" placeholder="ราคา" required>
        <input type="number" min="0" name="stock" value="<?= e($edit['stock']) ?>" placeholder="สต็อก" required>
        <div class="full imgup">
          <?php if (!empty($edit['image'])): ?>
            <?= pthumb($edit, 80) ?>
            <label><input type="checkbox" name="remove_image" value="1"> ลบรูปเดิม (กลับไปใช้อีโมจิ)</label>
          <?php endif; ?>
          <label>🖼 <?= !empty($edit['image']) ? 'เปลี่ยนรูปสินค้า' : 'รูปสินค้า' ?> (JPG / PNG / WEBP ไม่เกิน 3MB)
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
        </div>
        <input class="full" name="descr" value="<?= e($edit['descr']) ?>" placeholder="รายละเอียดสั้นๆ (แสดงบนการ์ด)">
        <textarea class="full" name="detail" rows="5" placeholder="รายละเอียดสินค้าแบบเต็ม (วิธีดูแล ขนาด ฯลฯ)"><?= e($edit['detail'] ?? '') ?></textarea>
      </div>
      <button>บันทึก</button>
      <?php if ($edit['id']): ?><a class="btn ghost" href="admin.php?t=products">ยกเลิก</a><?php endif; ?>
    </form>

    <div class="card scroll">
      <table>
        <?php foreach ($products as $p): ?>
          <tr>
            <td><div style="display:flex;gap:10px;align-items:center"><?= pthumb($p) ?><span><b><?= e($p['name']) ?></b><br>
              <span class="mut"><?= e($p['cat_name'] ?? 'ไม่ระบุหมวด') ?> · <?= baht($p['price']) ?> · สต็อก <?= $p['stock'] ?></span></span></div></td>
            <td style="white-space:nowrap">
              <a class="btn ghost sm" href="product.php?id=<?= $p['id'] ?>" target="_blank">ดู</a>
              <a class="btn ghost sm" href="admin.php?t=products&edit=<?= $p['id'] ?>">แก้ไข</a>
              <form method="post" action="admin.php?t=products" style="display:inline" onsubmit="return confirm('ลบสินค้านี้ถาวร? (รูปจะถูกลบด้วย)')">
                <?= fields('pdel', $p['id']) ?>
                <button class="del sm">ลบ</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php
}

/** tab_members() — รายชื่อสมาชิก จำนวนออเดอร์ และยอดซื้อที่ชำระแล้ว */
function tab_members() {
    $members = q('SELECT u.*, COUNT(o.id) AS orders_count,
                    COALESCE(SUM(CASE WHEN o.status IN (' . PAID_SQL . ') THEN o.total END), 0) AS spent
                  FROM users u LEFT JOIN orders o ON o.user_id = u.id
                  GROUP BY u.id ORDER BY u.id')->fetchAll();
    ?>
    <div class="card scroll">
      <table>
        <tr><th>ชื่อ</th><th>อีเมล</th><th>เบอร์</th><th>ออเดอร์</th><th>ยอดซื้อ</th><th>ยืนยันตัวตน</th></tr>
        <?php foreach ($members as $m): ?>
          <tr>
            <td><?= avatar($m, 22) ?> <?= e($m['name']) ?><?php if ($m['role'] === 'admin'): ?> <span class="tag">admin</span><?php endif; ?></td>
            <td><?= e($m['email']) ?></td>
            <td><?= e($m['phone']) ?></td>
            <td><?= $m['orders_count'] ?></td>
            <td><?= baht($m['spent']) ?></td>
            <td><?= $m['verify_status'] === 'verified' ? '✔' : e($m['verify_status'] ?: '-') ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php
}

/** tab_chat() — รายชื่อลูกค้าที่ทักมา (มีเลขข้อความที่ยังไม่อ่าน) + ห้องแชทของคนที่เลือก */
function tab_chat($uid) {
    $threads = q("SELECT u.id, u.name,
                    SUM(m.sender='c' AND m.is_read=0) AS unread, MAX(m.id) AS last_id
                  FROM messages m JOIN users u ON u.id = m.user_id
                  GROUP BY u.id, u.name ORDER BY last_id DESC")->fetchAll();
    ?>
    <div class="chips">
      <?php foreach ($threads as $t): ?>
        <a class="chip<?= $uid == $t['id'] ? ' on' : '' ?>" href="admin.php?t=chat&uid=<?= $t['id'] ?>">
          <?= e($t['name']) ?><?= $t['unread'] ? ' (' . $t['unread'] . ')' : '' ?>
        </a>
      <?php endforeach; ?>
      <?php if (!$threads): ?><span class="mut">ยังไม่มีข้อความ</span><?php endif; ?>
    </div>
    <?php
    if ($uid > 0) {
        // เปิดอ่านแล้ว = ทำเครื่องหมายว่าอ่านข้อความของลูกค้าคนนี้
        q("UPDATE messages SET is_read=1 WHERE user_id=? AND sender='c'", [$uid]);
        $messages = q('SELECT * FROM messages WHERE user_id=? ORDER BY id', [$uid])->fetchAll();
        ?>
        <div class="card">
          <div class="msgs">
            <?php foreach ($messages as $m): ?>
              <div class="bub<?= $m['sender'] === 's' ? ' me' : '' ?>">
                <?= nl2br(e($m['body'])) ?><br><small><?= e($m['created']) ?></small>
              </div>
            <?php endforeach; ?>
          </div>
          <form method="post" action="admin.php?t=chat&uid=<?= $uid ?>" style="display:flex;gap:8px;margin-top:8px">
            <?= fields('reply') ?>
            <input name="body" placeholder="ตอบลูกค้า..." required autocomplete="off">
            <button>ส่ง</button>
          </form>
        </div>
        <?php
    }
}

/**
 * tab_reports() — รายงานที่สมาชิกแจ้งมา: กรองตามสถานะ (&s=new|working|resolved|rejected|all) ตอบกลับ และเปลี่ยนสถานะ
 */
function tab_reports() {
    $filter = $_GET['s'] ?? 'new';
    if ($filter !== 'all' && !isset(REPORT_STATUS[$filter])) {
        $filter = 'new';
    }
    try {
        $counts = q('SELECT status, COUNT(*) FROM reports GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Throwable $err) {
        echo '<div class="card empty"><div class="big-emoji">🛠</div><p>ยังไม่มีตาราง reports กรุณานำเข้า <code>database/update_v4.sql</code> ก่อน</p></div>';
        return;
    }
    $where  = $filter === 'all' ? '1=1' : 'r.status=?';
    $params = $filter === 'all' ? [] : [$filter];
    $rows   = q("SELECT r.*, u.name, u.email, u.phone, u.avatar, u.verify_status FROM reports r
                 JOIN users u ON u.id=r.user_id WHERE $where ORDER BY (r.status='new') DESC, r.id DESC", $params)->fetchAll();
    ?>
    <div class="chips">
      <a class="chip<?= $filter === 'all' ? ' on' : '' ?>" href="admin.php?t=reports&s=all">ทั้งหมด (<?= array_sum($counts) ?>)</a>
      <?php foreach (REPORT_STATUS as $key => $label): ?>
        <a class="chip<?= $filter === $key ? ' on' : '' ?>" href="admin.php?t=reports&s=<?= $key ?>"><?= e($label) ?> (<?= (int)($counts[$key] ?? 0) ?>)</a>
      <?php endforeach; ?>
    </div>

    <?php foreach ($rows as $r): $tg = report_target($r, true); ?>
      <div class="card rep" id="r<?= $r['id'] ?>">
        <div class="rep-head">
          <span><b>#<?= $r['id'] ?></b> <?= e(REPORT_TYPES[$r['type']] ?? $r['type']) ?></span>
          <span><?= report_tag($r['status']) ?> <span class="mut"><?= thai_date($r['created']) ?> <?= e(substr($r['created'], 11, 5)) ?> น.</span></span>
        </div>
        <p style="margin:6px 0"><?= avatar($r, 26) ?> <b><?= e($r['name']) ?></b> <?= verified_badge($r) ?>
          <span class="mut"><?= e($r['email']) ?><?= $r['phone'] ? ' · ' . e($r['phone']) : '' ?></span>
          <a class="btn ghost sm" href="admin.php?t=chat&uid=<?= $r['user_id'] ?>">💬 แชท</a></p>
        <h3 style="margin:4px 0"><?= e($r['subject']) ?></h3>
        <?php if ($tg): ?>
          <p class="mut">เกี่ยวกับ: <?php if ($tg[1]): ?><a href="<?= e($tg[1]) ?>"><?= e($tg[0]) ?></a><?php else: echo e($tg[0]); endif; ?></p>
        <?php endif; ?>
        <p><?= nl2br(e($r['body'])) ?></p>
        <?php if ($r['img']): ?><a href="uploads/<?= e($r['img']) ?>" target="_blank"><img class="pic" src="uploads/<?= e($r['img']) ?>" alt="รูปประกอบ"></a><?php endif; ?>

        <form method="post" action="admin.php?t=reports&s=<?= e($filter) ?>" style="margin-top:10px">
          <?= fields('rep', $r['id']) ?>
          <textarea name="reply" rows="3" maxlength="2000" placeholder="ข้อความตอบกลับสมาชิก (สมาชิกจะเห็นในหน้า 'รายงานของฉัน')"><?= e($r['admin_reply']) ?></textarea>
          <div class="actions">
            <select name="status" style="width:auto">
              <?php foreach (REPORT_STATUS as $key => $label): ?>
                <option value="<?= $key ?>"<?= $r['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="sm">💾 บันทึก / ตอบกลับ</button>
            <span class="mut">อัปเดตล่าสุด: <?= $r['updated'] ? thai_date($r['updated']) : '-' ?></span>
          </div>
        </form>
        <?php if (in_array($r['status'], ['resolved', 'rejected'])): ?>
          <form method="post" action="admin.php?t=reports&s=<?= e($filter) ?>" style="margin-top:6px" onsubmit="return confirm('ลบรายงานนี้ถาวร?')">
            <?= fields('repdel', $r['id']) ?><button class="del sm">ลบรายงาน</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="card empty"><div class="big-emoji">🎉</div><p class="mut">ไม่มีรายงานในสถานะนี้</p></div><?php endif; ?>
    <?php
}

/** tab_shop() — ข้อมูลติดต่อร้าน (โทร อีเมล LINE ที่อยู่ เวลาเปิด) ใช้แสดงในหน้าติดต่อเรา และท้ายเว็บ */
function tab_shop() {
    $info = shop_info();
    ?>
    <form method="post" action="admin.php?t=shop" class="card">
      <h2>ข้อมูลร้าน (หน้า "ติดต่อเรา" และท้ายเว็บ)</h2>
      <?= fields('shop') ?>
      <div class="form">
        <input name="shop_phone" value="<?= e($info['shop_phone']) ?>" placeholder="เบอร์โทรร้าน">
        <input name="shop_email" type="email" value="<?= e($info['shop_email']) ?>" placeholder="อีเมลร้าน">
        <input name="shop_line" value="<?= e($info['shop_line']) ?>" placeholder="LINE ID เช่น @suansuk">
        <input name="shop_hours" value="<?= e($info['shop_hours']) ?>" placeholder="เวลาทำการ">
        <textarea class="full" name="shop_addr" rows="2" placeholder="ที่อยู่ร้าน"><?= e($info['shop_addr']) ?></textarea>
      </div>
      <h3 style="margin-bottom:2px">📈 สถิติการเข้าชม (Google Analytics 4)</h3>
      <input name="ga4_id" value="<?= e(setting('ga4_id', '')) ?>" placeholder="รหัสวัดผล เช่น G-ABCD123456 (เว้นว่าง = ไม่ใช้)" maxlength="16" style="max-width:340px">
      <p class="mut">ระบบจะโหลด GA4 <b>เฉพาะผู้เยี่ยมชมที่กด "ยอมรับทั้งหมด"</b> ในแบนเนอร์คุกกี้ (PDPA) และเฉพาะหน้าสาธารณะ ไม่ทำงานในหน้าบัญชี ชำระเงิน แชท ติดตามพัสดุ และหลังบ้าน
        ปิดสัญญาณโฆษณาให้แล้ว · ข้อความในแบนเนอร์และนโยบายคุกกี้จะปรับตามอัตโนมัติ · หา "รหัสวัดผล" ได้ที่ Google Analytics &gt; Admin &gt; Data streams</p>
      <label class="consent"><input type="checkbox" name="demo_mode" value="1"<?= setting('demo_mode', '0') === '1' ? ' checked' : '' ?>>
        🎓 แสดงรายการบัญชีทดลองในหน้าเข้าสู่ระบบ (สำหรับครู/ผู้ตรวจ — <b>ปิดก่อนเปิดใช้งานจริง</b>)</label>
      <button>บันทึก</button>
      <p class="mut">ตำแหน่งร้านบนแผนที่ตั้งได้ที่แท็บ "ชำระเงิน" (ช่องละติจูด/ลองจิจูดร้าน) ค่าเริ่มต้นเป็นข้อมูลทดสอบ โปรดเปลี่ยนเป็นของร้านจริง</p>
    </form>
    <?php
}

/** tab_map() — แผนที่จริง (Leaflet/OpenStreetMap) แสดงหมุดจุดจัดส่งของออเดอร์ที่ไม่ถูกยกเลิก */
function tab_map() {
    $points = q("SELECT id, name, addr, lat, lng, status FROM orders WHERE lat IS NOT NULL AND status <> 'ยกเลิก'")->fetchAll();
    ?>
    <p class="mut">หมุดคือจุดจัดส่งที่ลูกค้าปักไว้ตอนสั่งซื้อ (<?= count($points) ?> จุด)</p>
    <div id="map" style="height:430px;border-radius:12px"></div>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
    <script>
    var points = <?= json_encode($points, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var map = L.map('map');
    var bounds = [];
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '© OpenStreetMap'}).addTo(map);

    // วางหมุดทีละจุด กดหมุดแล้วขึ้นชื่อ/ที่อยู่ (ใช้ textContent กันโค้ดแปลกปลอม)
    points.forEach(function (p) {
      var info = document.createElement('div');
      info.textContent = '#' + p.id + ' ' + p.name + ' (' + p.status + ') — ' + p.addr;
      L.marker([p.lat, p.lng]).addTo(map).bindPopup(info);
      bounds.push([p.lat, p.lng]);
    });

    // ซูมให้เห็นทุกหมุด ถ้าไม่มีหมุดเลยให้แสดงภาพรวมประเทศไทย
    if (bounds.length) { map.fitBounds(bounds, {maxZoom: 15, padding: [30, 30]}); }
    else { map.setView([13.7563, 100.5018], 6); }
    </script>
    <?php
}

/** tab_promo() — จัดการสไลด์โฆษณา/โปรโมทหน้าแรก (สินค้ามาใหม่ 2 ชิ้นล่าสุดขึ้นสไลด์เองอัตโนมัติ) */
function tab_promo() {
    ?>
    <form method="post" action="admin.php?t=promo" class="card">
      <h2>เพิ่มโฆษณา/โปรโมท (สไลด์หน้าร้าน)</h2>
      <?= fields('sadd') ?>
      <div class="form">
        <input class="full" name="title" placeholder="หัวข้อ" required>
        <input class="full" name="body" placeholder="รายละเอียด">
        <input name="emoji" value="🎉">
      </div>
      <button>เพิ่มสไลด์</button>
      <p class="mut">สินค้ามาใหม่ 2 ชิ้นล่าสุดจะขึ้นสไลด์อัตโนมัติ</p>
    </form>
    <?php foreach (q('SELECT * FROM slides')->fetchAll() as $s): ?>
      <div class="card" style="display:flex;justify-content:space-between;gap:8px">
        <span><?= e($s['emoji'] . ' ' . $s['title']) ?><br><span class="mut"><?= e($s['body']) ?></span></span>
        <form method="post" action="admin.php?t=promo"><?= fields('sdel', $s['id']) ?><button class="del sm">ลบ</button></form>
      </div>
    <?php endforeach;
}

/** tab_pay() — ตั้งค่า PromptPay / บัญชีธนาคาร / เก็บเงินปลายทาง */
function tab_pay() {
    ?>
    <form method="post" action="admin.php?t=pay" class="card">
      <h2>ตั้งค่าการชำระเงิน</h2>
      <?= fields('pay') ?>
      <div class="form">
        <input class="full" name="pp" value="<?= e(setting('pp')) ?>" placeholder="PromptPay (เบอร์โทร 10 หลัก หรือเลขบัตร ปชช.)">
        <input name="bank" value="<?= e(setting('bank')) ?>" placeholder="ธนาคาร">
        <input name="acc" value="<?= e(setting('acc')) ?>" placeholder="เลขบัญชี">
        <input class="full" name="aname" value="<?= e(setting('aname')) ?>" placeholder="ชื่อบัญชี">
        <input name="shop_lat" value="<?= e(setting('shop_lat', '13.7563')) ?>" placeholder="ละติจูดร้าน">
        <input name="shop_lng" value="<?= e(setting('shop_lng', '100.5018')) ?>" placeholder="ลองจิจูดร้าน">
        <span class="mut full">พิกัดร้านใช้เป็นจุดเริ่มต้นของเส้นทางบนแผนที่ติดตามพัสดุ (คลิกขวาที่ Google Maps แล้วคัดลอกพิกัดมาวางได้)</span>
        <label class="full"><input type="checkbox" name="cod" style="width:auto" <?= setting('cod', '1') === '1' ? 'checked' : '' ?>> เปิดรับเก็บเงินปลายทาง</label>
      </div>
      <button>บันทึก</button>
      <p class="mut">ค่าเริ่มต้นเป็นข้อมูลทดสอบ โปรดเปลี่ยนเป็นของร้านจริง</p>
    </form>
    <?php
}


/** tab_fees() — ภาษี VAT, ค่าส่งตามระยะทาง และโค้ดส่วนลด */
function tab_fees() {
    $c = shop_fee_config();
    $coupons = [];
    try {
        $coupons = q('SELECT * FROM coupons ORDER BY active DESC, id DESC')->fetchAll();
    } catch (Throwable $err) {
        echo '<div class="card">ยังไม่มีตารางโค้ดส่วนลด กรุณารัน <code>database/update_v7.sql</code> หรือเปิด <code>repair_db.php</code></div>';
    }
    $n = function ($v) { return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.'); };
    ?>
    <form method="post" action="admin.php?t=fees" class="card">
      <h2>🧾 ภาษีมูลค่าเพิ่ม (VAT)</h2>
      <?= fields('fees') ?>
      <div class="form">
        <label>อัตรา VAT (%) <input name="vat_rate" type="number" step="0.01" min="0" max="30" value="<?= e($n($c['vat_rate'])) ?>"></label>
        <label>วิธีคิด
          <select name="vat_mode">
            <option value="included"<?= $c['vat_mode'] === 'included' ? ' selected' : '' ?>>ราคาสินค้า "รวม VAT แล้ว" (แยกยอดภาษีให้ดูในใบสรุป)</option>
            <option value="excluded"<?= $c['vat_mode'] === 'excluded' ? ' selected' : '' ?>>ราคาสินค้า "ยังไม่รวม VAT" (บวกภาษีเพิ่มตอนชำระ)</option>
          </select></label>
        <input name="tax_name" value="<?= e($c['tax_name']) ?>" placeholder="ชื่อผู้ประกอบการ (แสดงบนใบสรุปยอด)">
        <input name="tax_id" value="<?= e($c['tax_id']) ?>" placeholder="เลขประจำตัวผู้เสียภาษี 13 หลัก" inputmode="numeric" maxlength="17">
        <span class="mut full">ใส่ 0 = ไม่คิดภาษี · VAT คิดจาก (ราคาสินค้าหลังหักส่วนลด + ค่าส่ง) · ใช้ได้ต่อเมื่อร้านจดทะเบียนภาษีมูลค่าเพิ่มจริง ปรึกษาสรรพากร/นักบัญชีก่อนเปิดใช้</span>
      </div>
      <h2 style="margin-top:14px">🚚 ค่าส่งตามระยะทาง</h2>
      <div class="form">
        <label>ค่าส่งเริ่มต้น (บาท) <input name="ship_base" type="number" step="1" min="0" value="<?= e($n($c['ship_base'])) ?>"></label>
        <label>ระยะแรกที่ไม่คิดเพิ่ม (กม.) <input name="ship_free_km" type="number" step="0.5" min="0" value="<?= e($n($c['ship_free_km'])) ?>"></label>
        <label>กม.ละกี่บาท (หลังระยะแรก) <input name="ship_per_km" type="number" step="0.5" min="0" value="<?= e($n($c['ship_per_km'])) ?>"></label>
        <label>ค่าส่งสูงสุด (บาท, 0 = ไม่จำกัด) <input name="ship_max" type="number" step="1" min="0" value="<?= e($n($c['ship_max'])) ?>"></label>
        <label>ส่งไกลสุด (กม., 0 = ไม่จำกัด) <input name="ship_max_km" type="number" step="1" min="0" value="<?= e($n($c['ship_max_km'])) ?>"></label>
        <label>ซื้อครบกี่บาทส่งฟรี (0 = ปิด) <input name="ship_free_over" type="number" step="1" min="0" value="<?= e($n($c['ship_free_over'])) ?>"></label>
        <label>ค่าส่งเมื่อยังไม่ปักหมุด (บาท) <input name="ship_nopin" type="number" step="1" min="0" value="<?= e($n($c['ship_nopin'])) ?>"></label>
        <span class="mut full">สูตร: ค่าส่งเริ่มต้น + (ระยะทาง − ระยะแรก) × กม.ละกี่บาท ปัดขึ้นเป็นหลัก 5 บาท · ระยะทางคือเส้นตรงจากพิกัดร้าน (ตั้งที่แท็บ "ชำระเงิน") ถึงหมุดของลูกค้า ไม่ใช่ระยะถนน</span>
      </div>
      <?php $ex = shipping_quote(0, (float)$c['shop_lat'] + 0.09, (float)$c['shop_lng']); ?>
      <p class="mut">ตัวอย่าง: ลูกค้าอยู่ห่าง ~10 กม. (ยอดสินค้ายังไม่ถึงเกณฑ์ส่งฟรี) → ค่าส่ง <b><?= baht2(shipping_quote(0, (float)$c['shop_lat'] + 0.09, (float)$c['shop_lng'])[0]) ?></b></p>
      <button>บันทึกภาษีและค่าส่ง</button>
    </form>

    <form method="post" action="admin.php?t=fees" class="card">
      <h2>🎟 สร้างโค้ดส่วนลด</h2>
      <?= fields('cpadd') ?>
      <div class="form">
        <input name="code" placeholder="โค้ด เช่น SONGKRAN" maxlength="20" style="text-transform:uppercase" required>
        <select name="kind"><option value="percent">ลดเป็น %</option><option value="fixed">ลดเป็นบาท</option></select>
        <input name="value" type="number" step="0.01" min="0.01" placeholder="ลดเท่าไร (เช่น 10 หรือ 50)" required>
        <input name="max_discount" type="number" step="1" min="0" placeholder="ลดสูงสุดกี่บาท (เฉพาะแบบ %, ว่าง = ไม่จำกัด)">
        <input name="min_total" type="number" step="1" min="0" placeholder="ซื้อขั้นต่ำกี่บาท (ว่าง = ไม่มี)">
        <input name="max_uses" type="number" step="1" min="0" placeholder="จำนวนสิทธิ์ทั้งหมด (ว่าง = ไม่จำกัด)">
        <label>เริ่มใช้ได้ <input name="starts" type="date"></label>
        <label>หมดอายุ <input name="expires" type="date"></label>
        <label>ต้นไม้ระดับขั้นต่ำ
          <select name="min_level"><?php foreach (TREE_LEVELS as $lv => $d): ?><option value="<?= $lv ?>"><?= $lv === 0 ? 'ทุกคน' : $lv . ' · ' . e($d[1]) ?></option><?php endforeach; ?></select></label>
        <input name="note" placeholder="บันทึกช่วยจำ (ลูกค้าไม่เห็น)" maxlength="120">
        <label class="full"><input type="checkbox" name="once" value="1" checked style="width:auto"> สมาชิก 1 คนใช้ได้ 1 ครั้ง</label>
      </div>
      <button>สร้างโค้ด</button>
    </form>

    <div class="card scroll">
      <h2>รายการโค้ด (<?= count($coupons) ?>)</h2>
      <table>
        <tr><th>โค้ด</th><th>ส่วนลด</th><th>เงื่อนไข</th><th>ใช้แล้ว</th><th></th></tr>
        <?php foreach ($coupons as $cp): $expired = $cp['expires'] !== null && $cp['expires'] < date('Y-m-d'); ?>
          <tr>
            <td><b><?= e($cp['code']) ?></b> <?= $cp['active'] && !$expired ? '<span class="tag ok">เปิด</span>' : '<span class="tag bad">' . ($expired ? 'หมดอายุ' : 'ปิด') . '</span>' ?>
              <?php if ($cp['note']): ?><br><span class="mut"><?= e($cp['note']) ?></span><?php endif; ?></td>
            <td><?= e(coupon_label($cp)) ?></td>
            <td class="mut"><?= $cp['once_per_user'] ? 'คนละ 1 ครั้ง' : 'ใช้ซ้ำได้' ?>
              <?= $cp['min_level'] ? ' · ต้นไม้ ≥ ' . e(TREE_LEVELS[(int)$cp['min_level']][1]) : '' ?>
              <?= $cp['starts'] ? ' · เริ่ม ' . e($cp['starts']) : '' ?><?= $cp['expires'] ? ' · ถึง ' . e($cp['expires']) : '' ?></td>
            <td><?= (int)$cp['used'] ?><?= $cp['max_uses'] ? ' / ' . (int)$cp['max_uses'] : '' ?></td>
            <td style="white-space:nowrap">
              <form method="post" action="admin.php?t=fees" style="display:inline"><?= fields('cptog', $cp['id']) ?><button class="ghost sm"><?= $cp['active'] ? 'ปิด' : 'เปิด' ?></button></form>
              <form method="post" action="admin.php?t=fees" style="display:inline" onsubmit="return confirm('ลบโค้ดนี้?')"><?= fields('cpdel', $cp['id']) ?><button class="del sm">ลบ</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$coupons): ?><tr><td colspan="5" class="mut">ยังไม่มีโค้ด</td></tr><?php endif; ?>
      </table>
    </div>
    <?php
}

/** tab_track() — อัปเดตความคืบหน้า/พัสดุของออเดอร์ 1 ใบ  admin.php?t=track&id=เลขออเดอร์ */
function tab_track() {
    $id    = (int)($_GET['id'] ?? 0);
    $order = q('SELECT * FROM orders WHERE id=?', [$id])->fetch();
    if (!$order) {
        echo '<p class="mut">ไม่พบออเดอร์</p>';
        return;
    }
    $events = q('SELECT * FROM order_events WHERE order_id=? ORDER BY id DESC', [$id])->fetchAll();
    // แผนที่เริ่มที่จุดจัดส่งของลูกค้า (ถ้าไม่มีใช้ตำแหน่งร้าน)
    $center = ($order['lat'] !== null) ? [(float)$order['lat'], (float)$order['lng']] : [(float)setting('shop_lat', '13.7563'), (float)setting('shop_lng', '100.5018')];
    ?>
    <p><a href="admin.php?t=orders">← รายการออเดอร์</a> · <a href="track.php?id=<?= $id ?>">ดูหน้าที่ลูกค้าเห็น</a></p>
    <form method="post" action="admin.php?t=track&id=<?= $id ?>" class="card">
      <h2>อัปเดตออเดอร์ #<?= $id ?> <span class="tag"><?= e($order['status']) ?></span></h2>
      <p class="mut"><?= e($order['name'] . ' · ' . $order['phone']) ?><br><?= e($order['addr']) ?></p>
      <?= fields('track', $id) ?>
      <div class="form">
        <select name="status">
          <?php foreach (ORDER_STATUS as $st): ?><option<?= $st === $order['status'] ? ' selected' : '' ?>><?= e($st) ?></option><?php endforeach; ?>
        </select>
        <input name="note" placeholder="หมายเหตุ เช่น ถึงศูนย์คัดแยกบางนา">
        <input name="carrier" value="<?= e($order['carrier']) ?>" placeholder="ขนส่ง เช่น Flash Express">
        <input name="tracking_no" value="<?= e($order['tracking_no']) ?>" placeholder="เลขพัสดุ">
        <div class="full">
          <p class="mut">📍 แตะแผนที่เพื่อระบุตำแหน่งพัสดุตอนนี้ (ไม่บังคับ) · 🏠 = จุดจัดส่งของลูกค้า</p>
          <div id="amap" style="height:300px;border-radius:12px"></div>
        </div>
        <input type="hidden" name="lat" id="alat">
        <input type="hidden" name="lng" id="alng">
      </div>
      <button>บันทึกความคืบหน้า</button>
    </form>

    <div class="card">
      <b>ประวัติ</b>
      <ul class="history">
        <?php foreach ($events as $ev): ?>
          <li><b><?= e($ev['status']) ?></b> <span class="mut"><?= e($ev['created']) ?></span>
            <?= $ev['lat'] !== null ? '📍' : '' ?><?php if ($ev['note']): ?><br><?= e($ev['note']) ?><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
    <script>
    var center = <?= json_encode($center) ?>;
    var hasDest = <?= json_encode($order['lat'] !== null) ?>;
    var map = L.map('amap').setView(center, 12);
    var parcel = null;
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '© OpenStreetMap'}).addTo(map);
    if (hasDest) {
      L.marker(center, {icon: L.divIcon({html: '<div style="font-size:26px">🏠</div>', className: '', iconSize: [30, 30], iconAnchor: [15, 15]})}).addTo(map);
    }
    // แตะแผนที่ = วางหมุดตำแหน่งพัสดุ และเก็บพิกัดลงช่องซ่อน
    map.on('click', function (e) {
      if (parcel) { parcel.setLatLng(e.latlng); } else { parcel = L.marker(e.latlng).addTo(map); }
      document.getElementById('alat').value = e.latlng.lat.toFixed(6);
      document.getElementById('alng').value = e.latlng.lng.toFixed(6);
    });
    </script>
    <?php
}

/** tab_categories() — จัดการหมวดหมู่ 2 ระดับ: เพิ่ม/แก้/ลบ (ลบได้เฉพาะหมวดที่ว่างเปล่า) */
function tab_categories() {
    $all    = categories();
    $counts = q('SELECT category_id, COUNT(*) FROM products WHERE category_id IS NOT NULL GROUP BY category_id')->fetchAll(PDO::FETCH_KEY_PAIR);
    $edit   = q('SELECT * FROM categories WHERE id=?', [(int)($_GET['edit'] ?? 0)])->fetch();
    if (!$edit) {
        $edit = ['id' => 0, 'parent_id' => null, 'name' => '', 'icon' => '📁', 'sort' => 0];
    }
    $parents = [];
    $kids    = [];
    foreach ($all as $c) {
        if ($c['parent_id']) { $kids[$c['parent_id']][] = $c; } else { $parents[] = $c; }
    }
    ?>
    <form method="post" action="admin.php?t=categories" class="card">
      <h2><?= $edit['id'] ? 'แก้ไขหมวดหมู่' : 'เพิ่มหมวดหมู่' ?></h2>
      <?= fields('cat', $edit['id']) ?>
      <div class="form">
        <input name="name" value="<?= e($edit['name']) ?>" placeholder="ชื่อหมวด" required>
        <input name="icon" value="<?= e($edit['icon']) ?>" placeholder="อีโมจิ">
        <select name="parent_id">
          <option value="">— เป็นหมวดหลัก —</option>
          <?php foreach ($parents as $pc): if ($pc['id'] == $edit['id']) { continue; } ?>
            <option value="<?= $pc['id'] ?>"<?= $edit['parent_id'] == $pc['id'] ? ' selected' : '' ?>>อยู่ใต้: <?= e($pc['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="number" name="sort" value="<?= (int)$edit['sort'] ?>" placeholder="ลำดับ (เลขน้อยขึ้นก่อน)">
      </div>
      <button>บันทึก</button>
      <?php if ($edit['id']): ?><a class="btn ghost" href="admin.php?t=categories">ยกเลิก</a><?php endif; ?>
    </form>

    <div class="card">
      <?php foreach ($parents as $pc): ?>
        <div class="row-cat"><b><?= e($pc['icon'] . ' ' . $pc['name']) ?></b>
          <span class="mut"><?= (int)($counts[$pc['id']] ?? 0) ?> ชิ้น</span>
          <?= cat_buttons($pc['id']) ?></div>
        <?php foreach ($kids[$pc['id']] ?? [] as $kc): ?>
          <div class="row-cat" style="margin-left:24px"><?= e($kc['icon'] . ' ' . $kc['name']) ?>
            <span class="mut"><?= (int)($counts[$kc['id']] ?? 0) ?> ชิ้น</span>
            <?= cat_buttons($kc['id']) ?></div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
    <?php
}

/** cat_buttons() — ปุ่มแก้ไข/ลบของหมวดหมู่ 1 แถว (ใช้ในหน้าจัดการหมวด) */
function cat_buttons($id) {
    return '<a class="btn ghost sm" href="admin.php?t=categories&edit=' . (int)$id . '">แก้ไข</a> '
         . '<form method="post" action="admin.php?t=categories" style="display:inline" onsubmit="return confirm(\'ลบหมวดนี้?\')">'
         . fields('catdel', $id) . '<button class="del sm">ลบ</button></form>';
}

/** tab_verify() — รายการสมาชิกที่ส่งเอกสารยืนยันตัวตนรออนุมัติ (ดูรูปผ่าน file.php เฉพาะแอดมิน) */
function tab_verify() {
    $pending = q("SELECT * FROM users WHERE verify_status='pending' ORDER BY id")->fetchAll();
    ?>
    <p class="mut">เอกสารจะถูกลบทิ้งทันทีเมื่อกดอนุมัติหรือปฏิเสธ ตรวจให้เสร็จแล้วอย่าเก็บสำเนาไว้ที่อื่น</p>
    <?php foreach ($pending as $u): ?>
      <div class="card">
        <?= avatar($u, 36) ?> <b><?= e($u['name']) ?></b> <span class="mut"><?= e($u['email'] . ' · ' . $u['phone']) ?></span><br>
        <?php if ($u['id_img']): ?><a href="file.php?f=<?= urlencode($u['id_img']) ?>" target="_blank"><img src="file.php?f=<?= urlencode($u['id_img']) ?>" alt="บัตร" style="max-width:240px;border-radius:8px"></a><?php endif; ?>
        <?php if ($u['selfie_img']): ?><a href="file.php?f=<?= urlencode($u['selfie_img']) ?>" target="_blank"><img src="file.php?f=<?= urlencode($u['selfie_img']) ?>" alt="เซลฟี่" style="max-width:240px;border-radius:8px"></a><?php endif; ?>
        <form method="post" action="admin.php?t=verify" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
          <?= fields('verify', $u['id']) ?>
          <input name="note" placeholder="เหตุผล (กรณีปฏิเสธ)" style="flex:1;min-width:180px">
          <button name="decision" value="approve">✔ อนุมัติ</button>
          <button name="decision" value="reject" class="del">ปฏิเสธ</button>
        </form>
      </div>
    <?php endforeach; ?>
    <?php if (!$pending): ?><p class="mut">ไม่มีรายการรอตรวจ</p><?php endif; ?>
    <?php
}

/**
 * tab_sales() — สรุปยอดขาย (เฉพาะออเดอร์ที่ชำระแล้ว ตาม PAID_STATUS)
 *   เลือกช่วงเวลาได้ (7/30/90 วัน, ปีนี้, ทั้งหมด) เทียบกับช่วงก่อนหน้า
 *   แสดง: ตัวเลขสรุป + กราฟยอดขาย + สถานะออเดอร์ + ช่องทางชำระ + สัดส่วนตามหมวด + สินค้าขายดี/ขายไม่ดี + ยอดนิยมจากรีวิว
 */
function tab_sales() {
    $periods = ['7' => '7 วัน', '30' => '30 วัน', '90' => '90 วัน', 'year' => 'ปีนี้', 'all' => 'ทั้งหมด'];
    $p = (string)($_GET['p'] ?? '30');
    if (!isset($periods[$p])) {
        $p = '30';
    }

    /* ---------- ช่วงเวลา: [since, until) และช่วงก่อนหน้าไว้เทียบ ---------- */
    $until = date('Y-m-d', strtotime('+1 day')) . ' 00:00:00';
    $days  = 0;
    if (ctype_digit($p)) {
        $days   = (int)$p;
        $since  = date('Y-m-d', strtotime('-' . ($days - 1) . ' days')) . ' 00:00:00';
        $pSince = date('Y-m-d', strtotime($since . ' -' . $days . ' days')) . ' 00:00:00';
        $pUntil = $since;
    } elseif ($p === 'year') {
        $since  = date('Y') . '-01-01 00:00:00';
        $pSince = (date('Y') - 1) . '-01-01 00:00:00';
        $pUntil = date('Y-m-d', strtotime('-1 year +1 day')) . ' 00:00:00';   // ช่วงเดียวกันของปีก่อน
    } else {
        $since  = '2000-01-01 00:00:00';
        $pSince = null;
        $pUntil = null;
    }

    $paidWhere = 'o.status IN (' . PAID_SQL . ') AND o.created >= ? AND o.created < ?';
    $sum = function ($from, $to) use ($paidWhere) {
        $r     = q("SELECT COUNT(*) AS c, COALESCE(SUM(o.total),0) AS s FROM orders o WHERE $paidWhere", [$from, $to])->fetch();
        $items = q("SELECT COALESCE(SUM(i.qty),0) FROM order_items i JOIN orders o ON o.id=i.order_id WHERE $paidWhere", [$from, $to])->fetchColumn();
        return ['orders' => (int)$r['c'], 'sales' => (float)$r['s'], 'items' => (int)$items];
    };
    $cur  = $sum($since, $until);
    $prev = $pSince ? $sum($pSince, $pUntil) : null;
    $aov     = $cur['orders'] > 0 ? $cur['sales'] / $cur['orders'] : 0;
    $prevAov = ($prev && $prev['orders'] > 0) ? $prev['sales'] / $prev['orders'] : 0;

    // ตัวช่วยแสดงผล
    $pct   = function ($a, $b) { return $b > 0 ? $a / $b * 100 : 0; };
    $fp    = function ($v) { return number_format($v, 1) . '%'; };
    $bar   = function ($v) { return '<div class="dbar"><i style="width:' . min(100, max(0, round($v, 1))) . '%"></i></div>'; };
    $delta = function ($now, $before) use ($prev) {
        if (!$prev) { return ''; }
        if ($before <= 0) { return $now > 0 ? '<small class="up">▲ ใหม่</small>' : '<small class="mut">–</small>'; }
        $d = ($now - $before) / $before * 100;
        return '<small class="' . ($d >= 0 ? 'up' : 'down') . '">' . ($d >= 0 ? '▲ ' : '▼ ') . number_format(abs($d), 1) . '%</small>';
    };

    /* ---------- สถานะออเดอร์ทั้งหมดในช่วงนี้ ---------- */
    $byStatus = [];
    foreach (q('SELECT status, COUNT(*) AS c, COALESCE(SUM(total),0) AS s FROM orders WHERE created >= ? AND created < ? GROUP BY status', [$since, $until])->fetchAll() as $r) {
        $byStatus[$r['status']] = $r;
    }
    $allOrders = 0;
    foreach ($byStatus as $r) { $allOrders += (int)$r['c']; }
    $cancelled = (int)($byStatus['ยกเลิก']['c'] ?? 0);

    /* ---------- ช่องทางชำระเงิน ---------- */
    $pmName = ['pp' => 'PromptPay', 'bank' => 'โอนบัญชีธนาคาร', 'cod' => 'เก็บเงินปลายทาง'];
    $byPm = q("SELECT o.pm, COUNT(*) AS c, COALESCE(SUM(o.total),0) AS s FROM orders o WHERE $paidWhere GROUP BY o.pm ORDER BY s DESC", [$since, $until])->fetchAll();

    /* ---------- กราฟยอดขายรายวัน / รายเดือน ---------- */
    $thMonth = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $trend = [];   // คีย์ => [ยอดขาย, จำนวนออเดอร์, ป้ายชื่อ]
    if ($days > 0) {
        $map = [];
        foreach (q("SELECT DATE(o.created) AS d, SUM(o.total) AS s, COUNT(*) AS c FROM orders o WHERE $paidWhere GROUP BY DATE(o.created)", [$since, $until])->fetchAll() as $r) {
            $map[$r['d']] = [(float)$r['s'], (int)$r['c']];
        }
        for ($i = $days - 1; $i >= 0; $i--) {
            $ts = strtotime('-' . $i . ' days');
            $k  = date('Y-m-d', $ts);
            $trend[$k] = [$map[$k][0] ?? 0, $map[$k][1] ?? 0, date('j', $ts) . '/' . date('n', $ts)];
        }
    } else {
        $map = [];
        foreach (q("SELECT DATE_FORMAT(o.created,'%Y-%m') AS d, SUM(o.total) AS s, COUNT(*) AS c FROM orders o WHERE $paidWhere GROUP BY d ORDER BY d", [$since, $until])->fetchAll() as $r) {
            $map[$r['d']] = [(float)$r['s'], (int)$r['c']];
        }
        $endTs = strtotime(date('Y-m-01'));
        if ($p === 'year') {
            $startTs = strtotime(date('Y') . '-01-01');
        } else {
            $startTs = $map ? strtotime(array_keys($map)[0] . '-01') : $endTs;
            if ($endTs - $startTs > 3 * 365 * 86400) {          // แสดงย้อนหลังไม่เกิน 36 เดือน
                $startTs = strtotime('-35 months', $endTs);
            }
        }
        for ($ts = $startTs; $ts <= $endTs; $ts = strtotime('+1 month', $ts)) {
            $k = date('Y-m', $ts);
            $trend[$k] = [$map[$k][0] ?? 0, $map[$k][1] ?? 0, $thMonth[(int)date('n', $ts)] . ' ' . sprintf('%02d', ((int)date('Y', $ts) + 543) % 100)];
        }
    }
    $maxS  = 0;
    foreach ($trend as $v) { $maxS = max($maxS, $v[0]); }
    $every = max(1, (int)ceil(count($trend) / 12));

    /* ---------- ยอดขายรายสินค้า ---------- */
    $products = q("SELECT p.*, COALESCE(x.qty,0) AS qty, COALESCE(x.rev,0) AS rev
                   FROM products p
                   LEFT JOIN (SELECT i.product_id, SUM(i.qty) AS qty, SUM(i.price*i.qty) AS rev
                              FROM order_items i JOIN orders o ON o.id=i.order_id
                              WHERE $paidWhere GROUP BY i.product_id) x ON x.product_id = p.id
                   ORDER BY rev DESC, qty DESC, p.id", [$since, $until])->fetchAll();
    $deleted = q("SELECT COALESCE(SUM(i.qty),0) AS qty, COALESCE(SUM(i.price*i.qty),0) AS rev
                  FROM order_items i JOIN orders o ON o.id=i.order_id LEFT JOIN products p ON p.id=i.product_id
                  WHERE p.id IS NULL AND $paidWhere", [$since, $until])->fetch();
    $totRev = (float)$deleted['rev'];
    $totQty = (int)$deleted['qty'];
    foreach ($products as $row) { $totRev += $row['rev']; $totQty += $row['qty']; }

    // รีวิว (ทุกช่วงเวลา) ใช้ดูความนิยม
    $rev = [];
    foreach (q('SELECT product_id, COUNT(*) AS c, AVG(rating) AS a FROM reviews GROUP BY product_id')->fetchAll() as $r) {
        $rev[$r['product_id']] = $r;
    }

    /* ---------- สัดส่วนตามหมวดหมู่ (รวมหมวดย่อยเข้าหมวดหลัก) ---------- */
    $topOf = [];
    $top   = [];
    foreach (categories() as $c) {
        if (!$c['parent_id']) {
            $topOf[$c['id']] = (int)$c['id'];
            $top[$c['id']] = ['name' => $c['name'], 'icon' => $c['icon'], 'qty' => 0, 'rev' => 0.0, 'n' => 0, 'subs' => []];
        }
    }
    foreach (categories() as $c) {
        if ($c['parent_id'] && isset($top[$c['parent_id']])) {
            $topOf[$c['id']] = (int)$c['parent_id'];
            $top[$c['parent_id']]['subs'][$c['id']] = ['name' => $c['name'], 'icon' => $c['icon'], 'qty' => 0, 'rev' => 0.0, 'n' => 0];
        }
    }
    $top[0] = ['name' => 'ไม่ระบุหมวด', 'icon' => '📦', 'qty' => 0, 'rev' => 0.0, 'n' => 0, 'subs' => []];
    foreach ($products as $row) {
        $cid = (int)$row['category_id'];
        $t   = $topOf[$cid] ?? 0;
        $top[$t]['qty'] += $row['qty'];
        $top[$t]['rev'] += $row['rev'];
        $top[$t]['n']++;
        if ($cid && isset($top[$t]['subs'][$cid])) {
            $top[$t]['subs'][$cid]['qty'] += $row['qty'];
            $top[$t]['subs'][$cid]['rev'] += $row['rev'];
            $top[$t]['subs'][$cid]['n']++;
        }
    }
    if ($top[0]['n'] === 0) { unset($top[0]); }
    $catCount = 0;
    foreach ($top as $t) { if ($t['n'] > 0) { $catCount++; } }
    $avgShare = $catCount > 0 ? 100 / $catCount : 0;       // สัดส่วนเฉลี่ยต่อหมวด ใช้ตัดสิน "ขายดี / ขายน้อย"
    uasort($top, function ($a, $b) { return $b['rev'] <=> $a['rev']; });
    if ($deleted['rev'] > 0 || $deleted['qty'] > 0) {
        $top[-1] = ['name' => 'สินค้าที่ถูกลบแล้ว', 'icon' => '🗑', 'qty' => (int)$deleted['qty'], 'rev' => (float)$deleted['rev'], 'n' => 0, 'subs' => []];
    }
    $catTag = function ($share, $rev, $n) use ($avgShare) {
        if ($n === 0) { return ''; }
        if ($rev <= 0) { return '<span class="tag bad">ไม่มียอดขาย</span>'; }
        return $share >= $avgShare ? '<span class="tag ok">🔥 ขายดี</span>' : '<span class="tag">📉 ขายน้อย</span>';
    };

    /* ---------- สินค้าขายดี / ขายไม่ดี ---------- */
    $best  = array_slice(array_filter($products, function ($r) { return $r['rev'] > 0; }), 0, 10);
    $bestIds = array_column($best, 'id');
    $worst = array_filter($products, function ($r) use ($bestIds) { return !in_array($r['id'], $bestIds); });
    usort($worst, function ($a, $b) { return [$a['rev'], $a['qty'], $b['stock']] <=> [$b['rev'], $b['qty'], $a['stock']]; });
    $worst = array_slice($worst, 0, 10);

    // ยอดนิยมจากรีวิว
    $popular = array_filter($products, function ($r) use ($rev) { return isset($rev[$r['id']]); });
    usort($popular, function ($a, $b) use ($rev) {
        return [$rev[$b['id']]['c'], $rev[$b['id']]['a']] <=> [$rev[$a['id']]['c'], $rev[$a['id']]['a']];
    });
    $popular = array_slice($popular, 0, 5);
    ?>
    <div class="chips">
      <?php foreach ($periods as $k => $label): ?>
        <a class="chip<?= (string)$k === $p ? ' on' : '' ?>" href="admin.php?t=sales&p=<?= e($k) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
    <p class="mut">นับเฉพาะออเดอร์ที่ชำระแล้ว (<?= e(implode(' / ', PAID_STATUS)) ?>)<?= $prev ? ' · % ลูกศร = เทียบกับช่วงก่อนหน้าที่ยาวเท่ากัน' : '' ?></p>

    <div class="stats">
      <div class="card">ยอดขายรวม<b><?= baht($cur['sales']) ?></b><?= $delta($cur['sales'], $prev['sales'] ?? 0) ?></div>
      <div class="card">ออเดอร์ที่ชำระแล้ว<b><?= number_format($cur['orders']) ?></b><?= $delta($cur['orders'], $prev['orders'] ?? 0) ?></div>
      <div class="card">จำนวนชิ้นที่ขาย<b><?= number_format($cur['items']) ?></b><?= $delta($cur['items'], $prev['items'] ?? 0) ?></div>
      <div class="card">เฉลี่ยต่อออเดอร์<b><?= baht($aov) ?></b><?= $delta($aov, $prevAov) ?></div>
      <div class="card">ชำระสำเร็จ<b><?= $fp($pct($cur['orders'], $allOrders)) ?></b><small class="mut"><?= $cur['orders'] ?> จาก <?= $allOrders ?> ออเดอร์</small></div>
      <div class="card">ยกเลิก<b class="<?= $cancelled ? 'low' : '' ?>"><?= $fp($pct($cancelled, $allOrders)) ?></b><small class="mut"><?= $cancelled ?> ออเดอร์</small></div>
    </div>

    <h2>📈 ยอดขาย<?= $days > 0 ? 'รายวัน' : 'รายเดือน' ?></h2>
    <div class="card trend scroll">
      <?php if ($maxS <= 0): ?>
        <p class="mut">ยังไม่มียอดขายในช่วงนี้</p>
      <?php else: ?>
        <div class="bars" style="min-width:<?= max(260, count($trend) * ($days >= 60 ? 9 : 28)) ?>px">
          <?php $n = 0; foreach ($trend as $v): $h = $v[0] > 0 ? max(3, round($v[0] / $maxS * 100)) : 0; ?>
            <div class="b" title="<?= e($v[2] . ' : ' . baht($v[0]) . ' (' . $v[1] . ' ออเดอร์)') ?>">
              <i style="height:<?= $h ?>%"></i><?php if ($n % $every === 0): ?><span><?= e($v[2]) ?></span><?php endif; ?>
            </div>
          <?php $n++; endforeach; ?>
        </div>
        <p class="mut" style="margin:22px 0 0">สูงสุด <?= baht($maxS) ?> ต่อ<?= $days > 0 ? 'วัน' : 'เดือน' ?> · แตะ/วางเมาส์บนแท่งเพื่อดูตัวเลข</p>
      <?php endif; ?>
    </div>

    <div class="cols2">
      <div>
        <h2>📦 สถานะออเดอร์</h2>
        <div class="card scroll"><table>
          <tr><th>สถานะ</th><th>ออเดอร์</th><th>สัดส่วน</th><th>ยอดเงิน</th></tr>
          <?php
          $order = array_merge(ORDER_STATUS, array_diff(array_keys($byStatus), ORDER_STATUS));
          foreach ($order as $st): if (empty($byStatus[$st])) { continue; } $r = $byStatus[$st]; $share = $pct($r['c'], $allOrders); ?>
            <tr><td><?= status_tag($st) ?></td><td><?= (int)$r['c'] ?></td>
              <td><?= $fp($share) ?><?= $bar($share) ?></td><td><?= baht($r['s']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$byStatus): ?><tr><td colspan="4" class="mut">ไม่มีออเดอร์ในช่วงนี้</td></tr><?php endif; ?>
        </table></div>
      </div>
      <div>
        <h2>💳 ช่องทางชำระเงิน</h2>
        <div class="card scroll"><table>
          <tr><th>ช่องทาง</th><th>ออเดอร์</th><th>สัดส่วนยอด</th><th>ยอดเงิน</th></tr>
          <?php foreach ($byPm as $r): $share = $pct($r['s'], $cur['sales']); ?>
            <tr><td><?= e($pmName[$r['pm']] ?? $r['pm']) ?></td><td><?= (int)$r['c'] ?></td>
              <td><?= $fp($share) ?><?= $bar($share) ?></td><td><?= baht($r['s']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$byPm): ?><tr><td colspan="4" class="mut">ยังไม่มียอดขาย</td></tr><?php endif; ?>
        </table></div>
      </div>
    </div>

    <h2>🗂 สัดส่วนยอดขายตามหมวดหมู่</h2>
    <div class="card scroll"><table>
      <tr><th>หมวดหมู่</th><th>สินค้า</th><th>ขายได้ (ชิ้น)</th><th>สัดส่วนชิ้น</th><th>ยอดขาย</th><th style="min-width:130px">สัดส่วนยอด</th><th>ผล</th></tr>
      <?php foreach ($top as $tid => $t): $sr = $pct($t['rev'], $totRev); $sq = $pct($t['qty'], $totQty); ?>
        <tr>
          <td><b><?= e($t['icon'] . ' ' . $t['name']) ?></b></td>
          <td><?= $t['n'] ?: '–' ?></td><td><?= number_format($t['qty']) ?></td><td><?= $fp($sq) ?></td>
          <td><?= baht($t['rev']) ?></td><td><?= $fp($sr) ?><?= $bar($sr) ?></td>
          <td><?= $catTag($sr, $t['rev'], $t['n']) ?></td>
        </tr>
        <?php uasort($t['subs'], function ($a, $b) { return $b['rev'] <=> $a['rev']; });
        foreach ($t['subs'] as $s): $sr2 = $pct($s['rev'], $totRev); $sq2 = $pct($s['qty'], $totQty); ?>
          <tr class="subrow">
            <td>&nbsp;&nbsp;↳ <?= e($s['icon'] . ' ' . $s['name']) ?></td>
            <td><?= $s['n'] ?: '–' ?></td><td><?= number_format($s['qty']) ?></td><td><?= $fp($sq2) ?></td>
            <td><?= baht($s['rev']) ?></td><td><?= $fp($sr2) ?><?= $bar($sr2) ?></td><td></td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <tr><td><b>รวม</b></td><td></td><td><b><?= number_format($totQty) ?></b></td><td></td><td><b><?= baht($totRev) ?></b></td><td></td><td></td></tr>
    </table></div>
    <p class="mut">"ขายดี" = สัดส่วนยอดสูงกว่าค่าเฉลี่ยของทุกหมวด (<?= $fp($avgShare) ?>) · "ขายน้อย" = ต่ำกว่าค่าเฉลี่ย</p>

    <h2>🔥 สินค้าขายดี Top 10</h2>
    <div class="card scroll"><table>
      <tr><th>#</th><th>สินค้า</th><th>ชิ้น</th><th>ยอดขาย</th><th style="min-width:130px">สัดส่วนยอด</th><th>สต็อก</th></tr>
      <?php foreach ($best as $i => $r): $sr = $pct($r['rev'], $totRev); ?>
        <tr><td><?= $i + 1 ?></td>
          <td><div style="display:flex;gap:8px;align-items:center"><?= pthumb($r, 32) ?><span><?= e($r['name']) ?></span></div></td>
          <td><?= number_format($r['qty']) ?></td><td><?= baht($r['rev']) ?></td>
          <td><?= $fp($sr) ?><?= $bar($sr) ?></td>
          <td class="<?= $r['stock'] <= 5 ? 'low' : '' ?>"><?= $r['stock'] ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$best): ?><tr><td colspan="6" class="mut">ยังไม่มียอดขายในช่วงนี้</td></tr><?php endif; ?>
    </table></div>

    <h2>❄️ สินค้าขายไม่ดี / ยังไม่เคยขาย</h2>
    <div class="card scroll"><table>
      <tr><th>สินค้า</th><th>ชิ้น</th><th>ยอดขาย</th><th>สต็อกค้าง</th><th>สถานะ</th></tr>
      <?php foreach ($worst as $r): ?>
        <tr>
          <td><div style="display:flex;gap:8px;align-items:center"><?= pthumb($r, 32) ?><span><?= e($r['name']) ?></span></div></td>
          <td><?= number_format($r['qty']) ?></td><td><?= baht($r['rev']) ?></td><td><?= $r['stock'] ?></td>
          <td><?= $r['rev'] > 0 ? '<span class="tag">📉 ขายน้อย</span>' : '<span class="tag bad">ไม่มียอดขาย</span>' ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$worst): ?><tr><td colspan="5" class="mut">สินค้าทุกชิ้นอยู่ในรายการขายดีแล้ว 🎉</td></tr><?php endif; ?>
    </table></div>
    <p class="mut">เคล็ดลับ: สินค้าที่ไม่มียอดแต่สต็อกค้างเยอะ ลองทำโปรโมชั่นที่แท็บ "โปรโมท" หรือปรับรูป/คำอธิบาย</p>

    <h2>⭐ สินค้ายอดนิยมจากรีวิว (ทุกช่วงเวลา)</h2>
    <div class="card scroll"><table>
      <tr><th>สินค้า</th><th>จำนวนรีวิว</th><th>คะแนนเฉลี่ย</th></tr>
      <?php foreach ($popular as $r): ?>
        <tr><td><div style="display:flex;gap:8px;align-items:center"><?= pthumb($r, 32) ?><span><?= e($r['name']) ?></span></div></td>
          <td><?= (int)$rev[$r['id']]['c'] ?></td>
          <td class="stars"><?= stars($rev[$r['id']]['a']) ?> <?= number_format($rev[$r['id']]['a'], 1) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$popular): ?><tr><td colspan="3" class="mut">ยังไม่มีรีวิว</td></tr><?php endif; ?>
    </table></div>
    <?php
}

/* ============================================================
 * ส่วน C: วาดหน้า — แถบแท็บ + เรียกฟังก์ชันของแท็บที่เลือก
 * ============================================================ */
$tabs = [
    'dash'     => 'ภาพรวม',
    'sales'    => '📈 ยอดขาย',
    'orders'   => 'ออเดอร์',
    'products' => 'สินค้า',
    'members'  => 'สมาชิก',
    'chat'     => 'แชท',
    'reports'  => 'รายงาน',
    'map'      => 'แผนที่จัดส่ง',
    'categories' => 'หมวดหมู่',
    'verify'   => 'ยืนยันตัวตน',
    'promo'    => 'โปรโมท',
    'pay'      => 'ชำระเงิน',
    'fees'     => '🧾 ภาษี/ค่าส่ง/โค้ด',
    'shop'     => 'ข้อมูลร้าน',
    'pdpa'     => '🔏 PDPA',
];
$badges = ['reports' => badge_counts()['admin_reports'], 'chat' => badge_counts()['admin_chat'], 'pdpa' => dsr_open_count()];   // ตัวเลขแจ้งเตือนบนแท็บ

head('หลังบ้าน');
?>
<h2>🛠 หลังบ้านแอดมิน</h2>
<div class="chips">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="chip<?= $tab === $key ? ' on' : '' ?>" href="admin.php?t=<?= $key ?>"><?= $label ?> <?= nbadge($badges[$key] ?? 0) ?></a>
  <?php endforeach; ?>
</div>
<?php
switch ($tab) {
    case 'sales':    tab_sales();      break;
    case 'orders':   tab_orders();     break;
    case 'products': tab_products();   break;
    case 'members':  tab_members();    break;
    case 'chat':     tab_chat($uid);   break;
    case 'reports':  tab_reports();    break;
    case 'map':      tab_map();        break;
    case 'shop':     tab_shop();       break;
    case 'pdpa':     tab_pdpa();       break;
    case 'track':    tab_track();      break;
    case 'categories': tab_categories(); break;
    case 'verify':   tab_verify();     break;
    case 'promo':    tab_promo();      break;
    case 'pay':      tab_pay();        break;
    case 'fees':     tab_fees();       break;
    default:         tab_dash();
}
foot();
