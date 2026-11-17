<?php
/**
 * config.php — ไฟล์กลางของทั้งเว็บ
 * ------------------------------------------------------------------
 * ทุกหน้า (index.php, cart.php, admin.php ฯลฯ) จะเรียกไฟล์นี้เป็นอันดับแรกด้วย
 *     require 'config.php';
 * ไฟล์นี้ทำหน้าที่ 5 อย่าง:
 *   1) ตั้งค่าฐานข้อมูล   2) เริ่มระบบ session (จำว่าใครล็อกอิน / ตะกร้าสินค้า)
 *   3) ฟังก์ชันเรื่องความปลอดภัย   4) ฟังก์ชันผู้ใช้ / การตั้งค่าร้าน
 *   5) ฟังก์ชันสร้างส่วนหัว-ท้ายหน้าเว็บและการ์ดสินค้า
 */

/* ============================================================
 * 1) ค่าตั้งต้น — แก้ตรงนี้ถ้า MySQL ของคุณตั้งรหัสผ่านไว้
 * ============================================================ */
const DB_HOST = 'localhost';   // เครื่องที่รันฐานข้อมูล (XAMPP = localhost)
const DB_NAME = 'plantshop';   // ชื่อฐานข้อมูล
const DB_USER = 'root';        // ผู้ใช้ MySQL (XAMPP ค่าเริ่มต้น = root)
const DB_PASS = '';            // รหัสผ่าน MySQL (XAMPP ค่าเริ่มต้น = ว่าง)

/** สถานะออเดอร์ทั้งหมด ใช้เป็นตัวเลือกใน dropdown หลังบ้าน */
const ORDER_STATUS = ['รอชำระเงิน', 'แจ้งชำระแล้ว', 'ชำระแล้ว', 'เก็บเงินปลายทาง', 'เตรียมสินค้า', 'จัดส่งแล้ว', 'ได้รับสินค้าแล้ว', 'ยกเลิก'];

/** สถานะที่ถือว่า "ได้รับเงินแล้ว" (ใช้คำนวณยอดขาย / ป้ายซื้อแล้ว) — แบบ array และแบบข้อความ SQL */
const PAID_STATUS = ['ชำระแล้ว', 'เตรียมสินค้า', 'จัดส่งแล้ว', 'ได้รับสินค้าแล้ว'];
const PAID_SQL    = "'ชำระแล้ว','เตรียมสินค้า','จัดส่งแล้ว','ได้รับสินค้าแล้ว'";

/** หัวข้อที่สมาชิกแจ้งรายงานถึงแอดมินได้ (report.php) */
const REPORT_TYPES = [
    'order'   => '📦 ปัญหาออเดอร์ / การจัดส่ง',
    'product' => '🪴 ปัญหาสินค้า / คุณภาพ',
    'payment' => '💳 ปัญหาการชำระเงิน',
    'review'  => '⭐ รีวิวไม่เหมาะสม',
    'service' => '🙋 การให้บริการ / พฤติกรรมผู้ใช้',
    'website' => '🐞 เว็บไซต์ทำงานผิดพลาด',
    'other'   => '💬 อื่นๆ',
];

/** สถานะของรายงาน: ใหม่ → กำลังดำเนินการ → แก้ไขแล้ว / ปิดเรื่อง */
const REPORT_STATUS = ['new' => 'รอตรวจสอบ', 'working' => 'กำลังดำเนินการ', 'resolved' => 'แก้ไขแล้ว', 'rejected' => 'ปิดเรื่อง'];

// เริ่ม session — ต้องทำก่อนส่งข้อมูลใดๆ ออกไปที่เบราว์เซอร์
// คุกกี้ session: HttpOnly (JavaScript อ่านไม่ได้) + SameSite=Lax (กัน CSRF) + Secure เมื่อใช้ https
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
                           'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')]);
session_start();
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');


/* ============================================================
 * 2) ฐานข้อมูล
 * ============================================================ */

/**
 * db() — ขอการเชื่อมต่อฐานข้อมูล (เชื่อมครั้งเดียวต่อคำขอ แล้วใช้ซ้ำ)
 * ปกติไม่ต้องเรียกตรงๆ ให้ใช้ q() แทน ยกเว้นต้องการ transaction หรือ lastInsertId()
 */
function db() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // ผิดพลาดให้โยน error ชัดเจน
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // ผลลัพธ์เป็น array ชื่อคอลัมน์
        ]);
    }
    return $pdo;
}

/**
 * q() — รันคำสั่ง SQL แบบปลอดภัย (prepared statement กัน SQL injection)
 * วิธีใช้:
 *   $user = q('SELECT * FROM users WHERE id=?', [5])->fetch();      // แถวเดียว
 *   $all  = q('SELECT * FROM products')->fetchAll();                 // ทุกแถว
 *   q('UPDATE products SET stock=? WHERE id=?', [10, 3]);            // คำสั่งแก้ข้อมูล
 * เครื่องหมาย ? คือช่องใส่ค่า ค่าจริงใส่ใน array ตัวที่สอง "ห้ามเอาตัวแปรมาต่อใน SQL ตรงๆ"
 */
function q($sql, $params = []) {
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}


/* ============================================================
 * 3) ความปลอดภัย
 * ============================================================ */

/**
 * e() — แปลงข้อความให้ปลอดภัยก่อนแสดงบนหน้าเว็บ (กัน XSS)
 * ใช้ทุกครั้งที่แสดงข้อมูลที่ผู้ใช้พิมพ์ เช่น  <?= e($user['name']) ?>
 */
function e($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

/**
 * csrf() — สร้างช่องซ่อน (token) ใส่ในทุก <form method="post">
 * เพื่อกันเว็บอื่นแอบส่งฟอร์มแทนผู้ใช้  วิธีใช้:  <form method="post"><?= csrf() ?> ... </form>
 */
function csrf() {
    if (empty($_SESSION['t'])) {
        $_SESSION['t'] = bin2hex(random_bytes(16));
    }
    return '<input type="hidden" name="t" value="' . $_SESSION['t'] . '">';
}

/**
 * chk() — ตรวจ token ของฟอร์ม POST ถ้าไม่ตรงให้หยุดทันที
 * วิธีใช้: เรียกบรรทัดบนๆ ของทุกหน้าที่รับฟอร์ม  chk();
 */
function chk() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ok = hash_equals($_SESSION['t'] ?? '', $_POST['t'] ?? '');
        if (!$ok) {
            http_response_code(400);
            exit('คำขอไม่ถูกต้อง (CSRF) กรุณากลับไปโหลดหน้าใหม่');
        }
    }
}


/* ============================================================
 * 4) ผู้ใช้ และการตั้งค่าร้าน
 * ============================================================ */

/**
 * me() — คืนข้อมูลผู้ใช้ที่ล็อกอินอยู่ (array) หรือ null ถ้ายังไม่ล็อกอิน
 * วิธีใช้:  $u = me();  if ($u) { echo $u['name']; }
 */
function me() {
    static $user = false;           // false = ยังไม่เคยโหลด
    if ($user === false) {
        $user = null;
        if (isset($_SESSION['uid'])) {
            $row  = q('SELECT * FROM users WHERE id=?', [$_SESSION['uid']])->fetch();
            $user = $row ? $row : null;
        }
    }
    return $user;
}

/**
 * need() — บังคับให้ล็อกอิน (ถ้ายังไม่ล็อกอินส่งไปหน้า login)
 *   need();          // ต้องล็อกอิน (ใครก็ได้)
 *   need('admin');   // ต้องเป็นแอดมินเท่านั้น (ไม่ใช่จะขึ้น 403)
 * คืนข้อมูลผู้ใช้กลับมาด้วย
 */
function need($role = null) {
    $user = me();
    if (!$user) {
        // จำหน้าที่ผู้ใช้ตั้งใจจะเข้า ล็อกอินเสร็จแล้วพากลับไปหน้านั้น (login.php ตรวจความปลอดภัยของ next อีกชั้น)
        $next = basename($_SERVER['SCRIPT_NAME']) . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
        header('Location: login.php?next=' . urlencode($next));
        exit;
    }
    if ($role !== null && $user['role'] !== $role) {
        fail(403, 'ไม่มีสิทธิ์เข้าถึงหน้านี้');
    }
    return $user;
}

/**
 * setting() — อ่านค่าตั้งค่าร้านจากตาราง settings เช่น เลข PromptPay
 * วิธีใช้:  setting('pp')  /  setting('cod', '1')  (ค่าที่ 2 คือค่าเริ่มต้นถ้ายังไม่เคยตั้ง)
 */
function setting($key, $default = '') {
    $row = q('SELECT v FROM settings WHERE k=?', [$key])->fetch();
    return $row ? $row['v'] : $default;
}

/**
 * app_base() — ที่อยู่ฐานของเว็บ เช่น /plantshop/ (หาจากตำแหน่งไฟล์จริงเทียบกับโฟลเดอร์เว็บ ไม่พึ่งที่อยู่ที่ผู้ใช้พิมพ์)
 * ใช้กับหน้า 404 ที่ถูกเรียกจากที่อยู่อะไรก็ได้ เพื่อให้ CSS/JS/ลิงก์ชี้ถูกที่เสมอ
 */
function app_base() {
    $root = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
    $root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/');
    $dir  = str_replace('\\', '/', realpath(__DIR__) ?: __DIR__);
    if ($root !== '' && stripos($dir, $root) === 0) {
        return rtrim(substr($dir, strlen($root)), '/') . '/';
    }
    return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/') . '/';
}

/** flash() — เก็บข้อความแจ้งเตือนไว้แสดงในหน้าถัดไป เช่น flash('บันทึกแล้ว'); */
function flash($message) {
    $_SESSION['f'] = $message;
}

/** baht() — จัดรูปแบบราคา  baht(1500) => ฿1,500 */
function baht($amount) {
    return '฿' . number_format((float)$amount, 0);
}

/** stars() — แปลงคะแนน 1-5 เป็นดาว  stars(4) => ★★★★☆ */
function stars($score) {
    $n = (int)round($score);
    return str_repeat('★', $n) . str_repeat('☆', 5 - $n);
}


/* ============================================================
 * 5) PromptPay QR (มาตรฐาน EMVCo ของธนาคารแห่งประเทศไทย)
 * ============================================================ */

/** tlv() — ประกอบข้อมูลแบบ "รหัส + ความยาว + ค่า" ตามมาตรฐาน QR */
function tlv($tag, $value) {
    return $tag . str_pad(strlen($value), 2, '0', STR_PAD_LEFT) . $value;
}

/** crc16() — คำนวณรหัสตรวจสอบท้าย QR (CRC-16/CCITT) ให้แอปธนาคารยอมรับ */
function crc16($text) {
    $crc = 0xFFFF;
    for ($i = 0; $i < strlen($text); $i++) {
        $crc ^= ord($text[$i]) << 8;
        for ($bit = 0; $bit < 8; $bit++) {
            if ($crc & 0x8000) {
                $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
            } else {
                $crc = ($crc << 1) & 0xFFFF;
            }
        }
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

/**
 * ppay() — สร้างข้อความสำหรับทำ QR PromptPay พร้อมยอดเงิน
 *   $id  = เบอร์โทร 10 หลัก หรือเลขบัตรประชาชน 13 หลัก
 *   $amount = ยอดที่ต้องจ่าย
 * วิธีใช้:  $payload = ppay('0812345678', 490);  แล้วเอา $payload ไปสร้างภาพ QR
 */
function ppay($id, $amount) {
    $digits = preg_replace('/\D/', '', $id);
    // เบอร์โทร: ตัด 0 หน้าออกแล้วใส่ 0066 / เลขบัตร 13 หลัก / e-wallet 15 หลัก
    $subTag = '01';
    $value  = '0066' . preg_replace('/^0/', '', $digits);
    if (strlen($digits) >= 15) {
        $subTag = '03'; $value = $digits;
    } elseif (strlen($digits) >= 13) {
        $subTag = '02'; $value = $digits;
    }
    $data = tlv('00', '01')                                   // เวอร์ชัน
          . tlv('01', '12')                                   // 12 = QR ใช้ครั้งเดียว (มียอด)
          . tlv('29', tlv('00', 'A000000677010111') . tlv($subTag, $value))
          . tlv('53', '764')                                  // สกุลเงินบาท
          . tlv('54', number_format((float)$amount, 2, '.', ''))
          . tlv('58', 'TH')                                   // ประเทศไทย
          . '6304';                                           // ตำแหน่งใส่ CRC
    return $data . crc16($data);
}


/* ============================================================
 * 6) อัปโหลดรูป
 * ============================================================ */

/**
 * save_img() — บันทึกรูปที่ผู้ใช้อัปโหลดลงโฟลเดอร์ uploads/
 *   ตรวจว่าเป็นรูปจริง (JPG/PNG/WEBP) และไม่เกิน 3MB ตั้งชื่อใหม่แบบสุ่ม
 *   $file   = ค่าจาก $_FILES['ชื่อช่อง']
 *   $prefix = คำนำหน้าชื่อไฟล์ เช่น 'rev' (รีวิว) , 'slip' (สลิป) , 'av' (รูปโปรไฟล์)
 *   $dir    = โฟลเดอร์ปลายทาง: 'uploads' (เปิดดูได้) หรือ 'private' (เอกสารลับ เปิดผ่าน file.php เฉพาะแอดมิน)
 * คืนชื่อไฟล์ใหม่ ถ้าไม่ผ่านคืน null
 */
function save_img($file, $prefix, $dir = 'uploads') {
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$file || $file['error'] !== 0 || $file['size'] > 3 * 1024 * 1024) {
        return null;
    }
    $info = @getimagesize($file['tmp_name']);      // ถ้าไม่ใช่รูปจริงจะได้ false
    if (!$info || !isset($allowed[$info['mime']])) {
        return null;
    }
    $name = $prefix . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$info['mime']];
    return move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $dir . '/' . $name) ? $name : null;
}


/**
 * pbox() — กล่องรูปสินค้า: ถ้าแอดมินอัปโหลดรูปไว้ แสดงรูปจริง ไม่มีรูปก็แสดงอีโมจิแทน
 *   $class = 'em' (การ์ดสินค้า) หรือ 'big' (หน้ารายละเอียดสินค้า)
 */
function pbox($p, $class = 'em') {
    if (!empty($p['image']) && preg_match('/^[\w.\-]+$/', $p['image'])) {
        return '<div class="' . e($class) . ' has-img"><img src="uploads/' . e($p['image']) . '" alt="' . e($p['name']) . '" loading="lazy"></div>';
    }
    return '<div class="' . e($class) . '">' . e($p['emoji']) . '</div>';
}

/** pthumb() — รูปเล็กสี่เหลี่ยมสำหรับตะกร้า/หลังบ้าน (รูปจริงหรืออีโมจิ) */
function pthumb($p, $size = 44) {
    if (!empty($p['image']) && preg_match('/^[\w.\-]+$/', $p['image'])) {
        return '<img class="thumb" src="uploads/' . e($p['image']) . '" alt="" width="' . (int)$size . '" height="' . (int)$size . '">';
    }
    return '<span class="thumb em-s">' . e($p['emoji']) . '</span>';
}

/** del_product_img() — ลบไฟล์รูปสินค้าออกจาก uploads/ (รับเฉพาะชื่อไฟล์ prod_xxx.jpg/png/webp กันลบผิดไฟล์) */
function del_product_img($file) {
    if ($file && preg_match('/^prod_[0-9a-f]+\.(jpg|png|webp)$/', $file)) {
        @unlink(__DIR__ . '/uploads/' . $file);
    }
}


/* ============================================================
 * 6.5) ติดตามออเดอร์ / โปรไฟล์ / หมวดหมู่
 * ============================================================ */

/**
 * log_event() — บันทึกความคืบหน้าของออเดอร์ลงตาราง order_events (ใช้แสดงไทม์ไลน์ + แผนที่ติดตาม)
 *   $status   สถานะ ณ ตอนนั้น   $note ข้อความอธิบาย
 *   $lat,$lng ตำแหน่งพัสดุ ณ ตอนนั้น (ไม่บังคับ ใช้วาดเส้นทางบนแผนที่)
 */
function log_event($orderId, $status, $note = '', $lat = null, $lng = null) {
    q('INSERT INTO order_events(order_id,status,note,lat,lng) VALUES(?,?,?,?,?)', [$orderId, $status, $note, $lat, $lng]);
}

/** avatar() — แสดงรูปโปรไฟล์วงกลม (ถ้าไม่มีรูปแสดงตัวอักษรแรกของชื่อ)  avatar($user, 40) */
function avatar($user, $size = 40) {
    $s = (int)$size;
    if (!empty($user['avatar'])) {
        return '<img class="av" src="uploads/' . e($user['avatar']) . '" alt="" style="width:' . $s . 'px;height:' . $s . 'px">';
    }
    $initial = mb_substr($user['name'] ?? '?', 0, 1);
    return '<span class="av" style="width:' . $s . 'px;height:' . $s . 'px;line-height:' . $s . 'px">' . e($initial) . '</span>';
}

/** verified_badge() — ป้าย "ยืนยันตัวตนแล้ว" (แสดงเฉพาะคนที่แอดมินอนุมัติแล้ว) */
function verified_badge($user) {
    return (($user['verify_status'] ?? '') === 'verified') ? '<span class="tag ok">✔ ยืนยันตัวตนแล้ว</span>' : '';
}

/** categories() — หมวดหมู่ทั้งหมด เรียงตามลำดับ (parent_id = NULL คือหมวดหลัก, มีค่า = หมวดย่อย) */
function categories() {
    return q('SELECT * FROM categories ORDER BY sort, id')->fetchAll();
}

/** category_path() — เส้นทางหมวดของสินค้า เช่น [หมวดหลัก, หมวดย่อย] ใช้ทำ breadcrumb */
function category_path($categoryId) {
    $path = [];
    $row  = $categoryId ? q('SELECT * FROM categories WHERE id=?', [$categoryId])->fetch() : false;
    if ($row && $row['parent_id']) {
        $parent = q('SELECT * FROM categories WHERE id=?', [$row['parent_id']])->fetch();
        if ($parent) {
            $path[] = $parent;
        }
    }
    if ($row) {
        $path[] = $row;
    }
    return $path;
}


/* ============================================================
 * 6.8) ตัวช่วยใหม่: ข้อมูลร้าน / ตัวเลขแจ้งเตือน / ป้ายสถานะ / หน้า error
 * ============================================================ */

/**
 * shop_info() — ข้อมูลร้านทั้งหมดจากตาราง settings (โหลดครั้งเดียวต่อคำขอ) ใช้ในหน้าติดต่อและท้ายเว็บ
 * ถ้ายังไม่ได้รัน update_v4.sql หรือยังไม่เคยตั้งค่า จะใช้ค่าเริ่มต้นแทน
 */
function shop_info() {
    static $info = null;
    if ($info === null) {
        $info = [
            'shop_phone' => '081-234-5678', 'shop_email' => 'hello@shop.test', 'shop_line' => '@suansuk',
            'shop_addr'  => 'กรุงเทพมหานคร', 'shop_hours' => 'ทุกวัน 09:00 - 18:00 น.',
            'shop_lat'   => '13.7563', 'shop_lng' => '100.5018',
        ];
        try {
            foreach (q('SELECT k,v FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
                if ($v !== '' && $v !== null) {
                    $info[$k] = $v;
                }
            }
        } catch (Throwable $err) {
            // ตาราง settings ยังไม่พร้อม: ใช้ค่าเริ่มต้นไปก่อน
        }
    }
    return $info;
}

/**
 * badge_counts() — ตัวเลขแจ้งเตือนบนแถบเมนู (ครอบ try/catch: ถ้ายังไม่ได้รัน update_v4.sql เว็บต้องไม่พัง)
 *   แอดมิน: admin_reports = รายงานใหม่ที่ยังไม่ตรวจ, admin_chat = แชทที่ยังไม่อ่าน
 *   สมาชิก: my_reports = รายงานที่แอดมินตอบแล้วแต่ยังไม่เปิดอ่าน, my_chat = ข้อความจากร้านที่ยังไม่อ่าน
 */
function badge_counts() {
    static $count = null;
    if ($count !== null) {
        return $count;
    }
    $count = ['admin_reports' => 0, 'admin_chat' => 0, 'my_reports' => 0, 'my_chat' => 0];
    $user  = me();
    if (!$user) {
        return $count;
    }
    try {
        if ($user['role'] === 'admin') {
            $count['admin_reports'] = (int)q("SELECT COUNT(*) FROM reports WHERE status='new'")->fetchColumn();
            $count['admin_chat']    = (int)q("SELECT COUNT(*) FROM messages WHERE sender='c' AND is_read=0")->fetchColumn();
        } else {
            $count['my_reports'] = (int)q('SELECT COUNT(*) FROM reports WHERE user_id=? AND user_seen=0', [$user['id']])->fetchColumn();
            $count['my_chat']    = (int)q("SELECT COUNT(*) FROM messages WHERE user_id=? AND sender='s' AND is_read=0", [$user['id']])->fetchColumn();
        }
    } catch (Throwable $err) {
        // ยังไม่มีตาราง reports: ข้ามไป
    }
    return $count;
}

/** nbadge() — ป้ายตัวเลขสีแดงเล็กๆ ข้างเมนู (ไม่แสดงถ้าเป็น 0) */
function nbadge($n) {
    return $n > 0 ? '<span class="nb">' . ($n > 99 ? '99+' : (int)$n) . '</span>' : '';
}

/** status_tag() — ป้ายสถานะออเดอร์พร้อมสี (รอชำระ=เหลือง, ส่งแล้ว=ฟ้า, สำเร็จ=เขียว, ยกเลิก=แดง) */
function status_tag($status) {
    $class = [
        'รอชำระเงิน' => 'wait', 'แจ้งชำระแล้ว' => 'wait', 'ชำระแล้ว' => 'paid', 'เก็บเงินปลายทาง' => 'paid',
        'เตรียมสินค้า' => 'paid', 'จัดส่งแล้ว' => 'ship', 'ได้รับสินค้าแล้ว' => 'done', 'ยกเลิก' => 'cancel',
    ][$status] ?? 'wait';
    return '<span class="tag st-' . $class . '">' . e($status) . '</span>';
}

/** report_tag() — ป้ายสถานะของรายงาน (รอตรวจสอบ / กำลังดำเนินการ / แก้ไขแล้ว / ปิดเรื่อง) */
function report_tag($status) {
    $label = REPORT_STATUS[$status] ?? $status;
    return '<span class="tag rs-' . e($status) . '">' . e($label) . '</span>';
}

/** thai_date() — วันที่แบบไทยสั้นๆ เช่น 28 ต.ค. 2569  (รับข้อความวันที่จากฐานข้อมูล) */
function thai_date($datetime) {
    $time = $datetime ? strtotime($datetime) : false;
    if (!$time) {
        return '-';
    }
    if (function_exists('lang') && lang() === 'en') {
        return date('j M Y', $time);   // English: 9 Nov 2026 (ปีคริสต์ศักราช)
    }
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    return date('j', $time) . ' ' . $months[(int)date('n', $time) - 1] . ' ' . (date('Y', $time) + 543);
}

/** safe_next() — ตรวจค่า next หลังล็อกอิน: อนุญาตเฉพาะชื่อไฟล์ .php ในเว็บนี้ ไม่ให้พาไปเว็บอื่น */
function safe_next($next) {
    return preg_match('/^[a-z0-9_]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$/i', (string)$next) ? $next : '';
}

/**
 * report_target() — ลิงก์ไปยังสิ่งที่รายงานอ้างถึง คืน [ข้อความ, URL] หรือ null (ใช้ทั้งหน้าสมาชิกและหลังบ้าน)
 *   $admin = true → ออเดอร์จะลิงก์ไปหน้าอัปเดตพัสดุของแอดมิน
 */
function report_target($report, $admin = false) {
    $id = (int)$report['target_id'];
    if ($id < 1) {
        return null;
    }
    switch ($report['type']) {
        case 'order':
            return ['ออเดอร์ #' . $id, $admin ? 'admin.php?t=track&id=' . $id : 'track.php?id=' . $id];
        case 'product':
            $p = q('SELECT name FROM products WHERE id=?', [$id])->fetch();
            return $p ? ['สินค้า: ' . $p['name'], 'product.php?id=' . $id] : ['สินค้า #' . $id . ' (ถูกลบแล้ว)', ''];
        case 'review':
            $r = q('SELECT r.product_id, p.name FROM reviews r LEFT JOIN products p ON p.id=r.product_id WHERE r.id=?', [$id])->fetch();
            return $r ? ['รีวิว #' . $id . ' ของ ' . ($r['name'] ?? 'สินค้า'), 'product.php?id=' . (int)$r['product_id'] . '#reviews'] : ['รีวิว #' . $id . ' (ถูกลบแล้ว)', ''];
    }
    return null;
}

/**
 * fail() — แสดงหน้า error สวยๆ (มีเมนู/ท้ายเว็บครบ) แล้วหยุดทำงาน
 *   fail(404, 'ไม่พบสินค้า');   fail(403, 'ไม่มีสิทธิ์เข้าถึงหน้านี้');
 */
function fail($code, $message) {
    http_response_code($code);
    head($code === 404 ? 'ไม่พบหน้านี้' : 'เข้าถึงไม่ได้');
    echo '<div class="empty"><div class="big-emoji">' . ($code === 404 ? '🥀' : '🔒') . '</div>'
       . '<h2>' . e($message) . '</h2>'
       . '<p class="mut">รหัส ' . (int)$code . '</p>'
       . '<p><a class="btn" href="index.php">กลับหน้าแรก</a> <a class="btn ghost" href="contact.php">ติดต่อร้าน</a></p></div>';
    foot();
    exit;
}


/* ============================================================
 * 7) ส่วนแสดงผลที่ใช้ร่วมกันทุกหน้า
 * ============================================================ */

/**
 * head() — พิมพ์ส่วนหัวของหน้าเว็บ: <html>, แถบเมนูบนสุด (โลโก้ ค้นหา ตะกร้า เมนูสมาชิก), เมนูหลัก, ข้อความแจ้งเตือน
 * วิธีใช้: เรียก head('ชื่อหน้า'); ก่อนเนื้อหา และเรียก foot(); หลังเนื้อหา
 *   $desc = คำอธิบายหน้า (meta description สำหรับ SEO) ไม่ใส่ก็ได้
 */
function head($title, $desc = '') {
    $user      = me();
    $cartCount = array_sum($_SESSION['cart'] ?? []);   // จำนวนชิ้นในตะกร้า
    $page      = basename($_SERVER['SCRIPT_NAME'], '.php');
    $badge     = badge_counts();
    $isShop    = ($page === 'product') || ($page === 'products');   // หน้าสินค้า (รวมหน้ารายละเอียดสินค้า)

    // หมวดหมู่สำหรับเมนูแบบดรอปดาวน์ (หมวดหลัก + หมวดย่อย)
    $parents = [];
    $kids    = [];
    foreach (categories() as $c) {
        if ($c['parent_id']) { $kids[$c['parent_id']][] = $c; } else { $parents[] = $c; }
    }
    $fullTitle = ($title === 'ร้านสวนสุข') ? 'ร้านสวนสุข · ต้นไม้และดอกไม้ส่งตรงถึงบ้าน' : $title . ' · ร้านสวนสุข';
    $desc      = $desc !== '' ? $desc : 'ร้านสวนสุข จำหน่ายต้นไม้ ดอกไม้ ช่อดอกไม้ กระถางและอุปกรณ์ปลูกต้นไม้ คัดสดใหม่ทุกวัน ติดตามพัสดุได้';
    // ลิงก์เมนูหลัก: [ไฟล์, ลิงก์, ข้อความ, หน้านี้ active ไหม]
    $links = [
        ['index',   'index.php',   '🏠 หน้าแรก',         $page === 'index'],
        ['about',   'about.php',   'ℹ️ เกี่ยวกับเรา',    $page === 'about'],
        ['faq',     'faq.php',     '❓ คำถามที่พบบ่อย',  $page === 'faq' || $page === 'policy'],
        ['contact', 'contact.php', '📞 ติดต่อเรา',       $page === 'contact'],
        ['report',  'report.php',  '🚩 แจ้งรายงาน',      $page === 'report'],
    ];
    ?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="#1f9d55">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%8C%BF%3C/text%3E%3C/svg%3E">
<?php if (cookie_choice() === 'all'): ?><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;600;700&display=swap"><?php endif; /* ฟอนต์ Google = บริการบุคคลที่สาม โหลดเมื่อผู้ใช้ยินยอมเท่านั้น (PDPA) */ ?>
<?php if (defined('IS_404')): /* หน้า 404 ถูกเรียกจากที่อยู่อะไรก็ได้ จึงต้องบอกที่อยู่ฐานให้ลิงก์/ไฟล์ CSS-JS ทำงานถูก */ ?>
<base href="<?= e(app_base()) ?>">
<meta name="robots" content="noindex">
<style>body{font:16px/1.6 system-ui,'Noto Sans Thai',Tahoma,sans-serif}h1{font-size:28px}</style><?php /* สำรอง: ถ้า style.css โหลดไม่ได้ ตัวอักษรก็ไม่เล็ก (style.css จะทับค่านี้เมื่อโหลดสำเร็จ) */ ?>
<?php endif; ?>
<link rel="stylesheet" href="style.css">
</head>
<body>
<a class="skip" href="#content">ข้ามไปยังเนื้อหา</a>
<header class="top">
  <div class="bar">
    <button class="burger" id="burger" aria-label="เปิดเมนู" aria-expanded="false" aria-controls="mainnav">☰</button>
    <a class="brand" href="index.php">🌿 <span>ร้านสวนสุข</span></a>

    <form class="hsearch" action="products.php" role="search">
      <input name="q" value="<?= e($page === 'products' ? ($_GET['q'] ?? '') : '') ?>" placeholder="ค้นหาต้นไม้ ดอกไม้ กระถาง..." aria-label="ค้นหาสินค้า">
      <button aria-label="ค้นหา">🔍</button>
    </form>

    <div class="hact">
      <?= lang_switch() ?>
      <a class="ico" href="chat.php" title="แชทกับร้าน" aria-label="แชทกับร้าน">💬<?= nbadge($badge['my_chat'] + $badge['admin_chat']) ?></a>
      <a class="ico" href="cart.php" title="ตะกร้าสินค้า" aria-label="ตะกร้าสินค้า">🛒<?= nbadge($cartCount) ?></a>
      <?php if ($user): ?>
        <details class="umenu">
          <summary><?= avatar($user, 30) ?><span class="uname"><?= e(explode(' ', $user['name'])[0]) ?></span>
            <?= nbadge($badge['my_reports'] + $badge['admin_reports']) ?></summary>
          <div class="upanel">
            <div class="uhead"><b><?= e($user['name']) ?></b><span class="mut"><?= e($user['email']) ?></span></div>
            <?php if ($user['role'] === 'admin'): ?>
              <a href="admin.php">🛠 หลังบ้านแอดมิน</a>
              <a href="admin.php?t=reports">🚩 รายงานจากสมาชิก <?= nbadge($badge['admin_reports']) ?></a>
            <?php endif; ?>
            <a href="account.php">👤 โปรไฟล์ของฉัน</a>
            <a href="account.php?t=orders">📦 ประวัติสั่งซื้อ</a>
            <a href="report.php?t=mine">🚩 รายงานของฉัน <?= nbadge($badge['my_reports']) ?></a>
            <form method="post" action="logout.php"><?= csrf() ?><button class="linkbtn">↩ ออกจากระบบ</button></form>
          </div>
        </details>
      <?php else: ?>
        <a class="btn ghost sm" href="login.php">เข้าสู่ระบบ</a><a class="btn sm" href="register.php">สมัครสมาชิก</a>
      <?php endif; ?>
    </div>
  </div>

  <nav class="mainnav" id="mainnav" aria-label="เมนูหลัก">
    <ul>
      <li><a href="index.php"<?= $links[0][3] ? ' class="on" aria-current="page"' : '' ?>><?= $links[0][2] ?></a></li>
      <li class="dd">
        <a href="products.php"<?= $isShop ? ' class="on"' : '' ?>>🪴 สินค้าทั้งหมด ▾</a>
        <div class="panel">
          <?php foreach ($parents as $pc): ?>
            <div class="col">
              <a class="h" href="products.php?c=<?= $pc['id'] ?>"><?= e($pc['icon'] . ' ' . $pc['name']) ?></a>
              <?php foreach ($kids[$pc['id']] ?? [] as $kc): ?>
                <a href="products.php?c=<?= $kc['id'] ?>"><?= e($kc['icon'] . ' ' . $kc['name']) ?></a>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </li>
      <?php foreach (array_slice($links, 1) as $l): if ($l[0] === 'report' && $user && $user['role'] === 'admin') { continue; }   // แอดมินไม่ต้องแจ้งตัวเอง ?>
        <li><a href="<?= $l[1] ?>"<?= $l[3] ? ' class="on" aria-current="page"' : '' ?>><?= $l[2] ?></a></li>
      <?php endforeach; ?>
    </ul>
  </nav>
</header>
<main id="content">
<?php
    // สมาชิกที่ยังไม่รับทราบนโยบายความเป็นส่วนตัวเวอร์ชันล่าสุด: แสดงแถบเตือน (PDPA)
    if (policy_pending($user) && !in_array($page, ['privacy', 'policy'], true)) {
        echo '<div class="pdpa-bar" role="status">🔏 นโยบายความเป็นส่วนตัวมีการปรับปรุง (เวอร์ชัน ' . e(pdpa_version()) . ') '
           . '<a href="privacy.php">อ่านและรับทราบ</a></div>';
    }
    // แสดงข้อความแจ้งเตือนที่ตั้งไว้ด้วย flash() แล้วลบทิ้ง
    if (!empty($_SESSION['f'])) {
        echo '<div class="flash" role="status">' . e($_SESSION['f']) . '</div>';
        unset($_SESSION['f']);
    }
}

/** demo_on() — เปิดโหมดทดลองสำหรับครู/ผู้ตรวจอยู่หรือไม่ (แอดมินเปิด/ปิดที่แท็บ "ข้อมูลร้าน") */
function demo_on() {
    try {
        return setting('demo_mode', '0') === '1';
    } catch (Throwable $err) {
        return false;
    }
}

/** foot() — พิมพ์ส่วนท้ายของหน้าเว็บ: ข้อมูลร้าน ลิงก์ลัด นโยบาย ช่องทางติดต่อ และสคริปต์เมนู */
function foot() {
    $shop = shop_info();
    ?>
</main>
<footer class="foot">
  <div class="fgrid">
    <div>
      <div class="brand-f">🌿 ร้านสวนสุข</div>
      <p class="mut">ต้นไม้และดอกไม้คัดสดใหม่ทุกวัน แพ็กอย่างดี ส่งตรงถึงบ้าน ติดตามพัสดุได้ตลอดเส้นทาง</p>
    </div>
    <div>
      <h4>เมนูลัด</h4>
      <a href="index.php">หน้าแรก</a>
      <a href="products.php">สินค้าทั้งหมด</a>
      <a href="cart.php">ตะกร้าสินค้า</a>
      <a href="account.php?t=orders">ติดตามออเดอร์</a>
      <a href="about.php">เกี่ยวกับเรา</a>
    </div>
    <div>
      <h4>ช่วยเหลือ</h4>
      <a href="faq.php">คำถามที่พบบ่อย</a>
      <a href="policy.php?p=shipping">การจัดส่ง</a>
      <a href="policy.php?p=returns">การคืนสินค้า / เคลมต้นไม้</a>
      <a href="policy.php?p=privacy">นโยบายความเป็นส่วนตัว</a>
      <a href="policy.php?p=cookies">นโยบายคุกกี้</a>
      <a href="privacy.php">🔏 ศูนย์ความเป็นส่วนตัว (PDPA)</a>
      <a href="policy.php?p=terms">เงื่อนไขการใช้งาน</a>
      <?php if (!(me() && me()['role'] === 'admin')): ?><a href="report.php">🚩 แจ้งรายงานถึงแอดมิน</a><?php endif; ?>
    </div>
    <div>
      <h4>ติดต่อเรา</h4>
      <p>📞 <?= e($shop['shop_phone']) ?></p>
      <p>✉️ <?= e($shop['shop_email']) ?></p>
      <p>💬 LINE <?= e($shop['shop_line']) ?></p>
      <p>🕘 <?= e($shop['shop_hours']) ?></p>
      <a href="contact.php">ดูแผนที่และช่องทางทั้งหมด →</a>
    </div>
  </div>
  <div class="fbot">© <?= date('Y') + 543 ?> ร้านสวนสุข · ชำระเงินผ่าน PromptPay / โอนบัญชี / เก็บเงินปลายทาง
    <?php if (demo_on()): ?> · <a href="demo_check.php" style="display:inline;color:#fde68a">🎓 หน้าตรวจเช็ค (โหมดทดลอง)</a><?php endif; ?></div>
</footer>
<?php cookie_banner(); ?>
<?php analytics_snippet(); /* GA4: ทำงานเฉพาะเมื่อผู้ใช้กด "ยอมรับทั้งหมด" (ดู pdpa_lib.php) */ ?>
<?= i18n_js() ?><script src="app.js"></script>
</body>
</html>
<?php
}

/**
 * card() — พิมพ์การ์ดสินค้า 1 ใบ (รูป ชื่อ ราคา ดาวรีวิว ปุ่มใส่ตะกร้า)
 * ใช้ในหน้าแรก หน้าสินค้า และช่อง "สินค้าที่เกี่ยวข้อง"  วิธีใช้: card($product); หรือ card($product, 'products.php'); (ค่าที่สองคือหน้าที่จะกลับไปหลังกดใส่ตะกร้า)
 */
function card($p, $back = 'index.php') {
    $review = q('SELECT COUNT(*) AS cnt, COALESCE(AVG(rating),0) AS average FROM reviews WHERE product_id=?', [$p['id']])->fetch();
    ?>
<div class="card">
  <a class="plink" href="product.php?id=<?= $p['id'] ?>">
    <?= pbox($p, 'em') ?>
    <b><?= e($p['name']) ?></b>
  </a>
  <span class="mut"><?= e($p['descr']) ?></span>
  <?php if ($review['cnt'] > 0): ?>
    <span class="stars">★ <?= number_format($review['average'], 1) ?> <span class="mut">(<?= $review['cnt'] ?>)</span></span>
  <?php endif; ?>
  <span class="price"><?= baht($p['price']) ?></span>
  <span class="mut<?= $p['stock'] <= 5 ? ' low' : '' ?>"><?= $p['stock'] > 0 ? 'คงเหลือ ' . $p['stock'] : 'สินค้าหมด' ?></span>
  <form method="post" action="cart.php">
    <?= csrf() ?>
    <input type="hidden" name="a" value="add">
    <input type="hidden" name="pid" value="<?= $p['id'] ?>">
    <input type="hidden" name="back" value="<?= e($back) ?>">
    <button <?= $p['stock'] < 1 ? 'disabled' : '' ?>>หยิบใส่ตะกร้า</button>
  </form>
</div>
<?php
}

// ระบบ PDPA (ความยินยอม คำขอใช้สิทธิ์ ดาวน์โหลด/ลบข้อมูล บันทึกการเข้าถึง)
require __DIR__ . '/pdpa_lib.php';
require __DIR__ . '/shop_lib.php';
require __DIR__ . '/i18n.php';       // สลับภาษา ไทย / English
i18n_start();   // ภาษี VAT / ค่าส่งตามระยะทาง / โค้ดส่วนลด / ต้นไม้สะสมแต้ม
