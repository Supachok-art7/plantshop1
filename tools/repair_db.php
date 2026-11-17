<?php
/**
 * repair_db.php — ตัวซ่อมฐานข้อมูล (รันซ้ำกี่ครั้งก็ได้ ไม่ลบข้อมูลเดิม)
 * วิธีใช้: เปิด http://localhost/plantshop/tools/repair_db.php แล้วอ่านผลที่แสดง  ใช้เสร็จ "ลบไฟล์นี้ทิ้ง"
 * ตรวจและซ่อม: ฐานข้อมูลหาย / ตารางหาย / คอลัมน์หาย / ลืมติ๊ก AUTO_INCREMENT หรือ PRIMARY KEY
 *   / หมวดหมู่และตั้งค่าร้านที่หายไป / สินค้าที่อ้างหมวดที่ไม่มี / ไม่มีแอดมินเลย
 * เพิ่มท้าย URL ?reset_pw=1 เพื่อรีเซ็ตรหัสผ่านบัญชีทดลอง (admin@shop.test / customer@shop.test)
 */
require __DIR__ . '/../includes/config.php';

// กันคนนอก: ใช้ได้เฉพาะเปิดจากเครื่องตัวเอง
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('ใช้ได้เฉพาะเปิดจากเครื่องตัวเอง (localhost) เท่านั้น');
}

/* ---------- โครงสร้างที่ถูกต้อง (ต้องตรงกับ database.sql รุ่นล่าสุด) ---------- */
$SCHEMA = <<<'SQL'
CREATE TABLE IF NOT EXISTS users(
  id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, email VARCHAR(120) NOT NULL UNIQUE,
  phone VARCHAR(20), addr TEXT, pass VARCHAR(255) NOT NULL,
  role ENUM('admin','customer') DEFAULT 'customer', created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  avatar VARCHAR(150) NULL,
  birthdate DATE NULL, gender VARCHAR(10) NULL,
  addr_line VARCHAR(150) NULL, subdistrict VARCHAR(60) NULL, district VARCHAR(60) NULL,
  province VARCHAR(60) NULL, postcode VARCHAR(10) NULL,
  lat DOUBLE NULL, lng DOUBLE NULL,
  verify_status VARCHAR(10) DEFAULT 'none',
  verify_note VARCHAR(255) NULL, id_img VARCHAR(150) NULL, selfie_img VARCHAR(150) NULL,
  pdpa_version VARCHAR(20) NULL, marketing_opt_in TINYINT NOT NULL DEFAULT 0, deleted_at DATETIME NULL);
CREATE TABLE IF NOT EXISTS categories(
  id INT AUTO_INCREMENT PRIMARY KEY, parent_id INT NULL, name VARCHAR(80) NOT NULL,
  icon VARCHAR(16), sort INT DEFAULT 0);
CREATE TABLE IF NOT EXISTS products(
  id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, category_id INT NULL,
  price DECIMAL(10,2) NOT NULL, stock INT NOT NULL DEFAULT 0, emoji VARCHAR(16), image VARCHAR(150) NULL,
  descr VARCHAR(255), detail TEXT,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS orders(
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, name VARCHAR(100), phone VARCHAR(20), addr TEXT,
  lat DOUBLE NULL, lng DOUBLE NULL,
  total DECIMAL(10,2), pm VARCHAR(10),
  status VARCHAR(30), slip VARCHAR(150) NULL,
  carrier VARCHAR(40) NULL, tracking_no VARCHAR(60) NULL,
  subtotal DECIMAL(10,2) NULL, discount DECIMAL(10,2) NOT NULL DEFAULT 0, shipping DECIMAL(10,2) NOT NULL DEFAULT 0,
  distance_km DECIMAL(7,1) NULL, vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0, vat_mode VARCHAR(10) NOT NULL DEFAULT 'included',
  vat DECIMAL(10,2) NOT NULL DEFAULT 0, coupon VARCHAR(20) NULL,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP) AUTO_INCREMENT=1001;
CREATE TABLE IF NOT EXISTS order_events(
  id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, status VARCHAR(30), note VARCHAR(255),
  lat DOUBLE NULL, lng DOUBLE NULL,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY(order_id));
CREATE TABLE IF NOT EXISTS order_items(
  id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, product_id INT,
  name VARCHAR(150), price DECIMAL(10,2), qty INT);
CREATE TABLE IF NOT EXISTS messages(
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, sender CHAR(1) NOT NULL, body TEXT,
  is_read TINYINT DEFAULT 0, created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS slides(
  id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(150), body VARCHAR(255), emoji VARCHAR(16), bg VARCHAR(40));
CREATE TABLE IF NOT EXISTS settings(k VARCHAR(40) PRIMARY KEY, v TEXT);
CREATE TABLE IF NOT EXISTS reviews(
  id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_id INT NOT NULL,
  rating TINYINT NOT NULL, body TEXT, img VARCHAR(150) NULL,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY(product_id));
CREATE TABLE IF NOT EXISTS reports(
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
  type VARCHAR(20) NOT NULL,
  target_id INT NULL,
  subject VARCHAR(150) NOT NULL, body TEXT NOT NULL, img VARCHAR(150) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'new',
  admin_reply TEXT NULL,
  user_seen TINYINT NOT NULL DEFAULT 1,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated TIMESTAMP NULL,
  KEY(user_id), KEY(status))
CREATE TABLE IF NOT EXISTS consents(
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, purpose VARCHAR(20) NOT NULL,
  version VARCHAR(20), granted TINYINT NOT NULL DEFAULT 1,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY(user_id));
CREATE TABLE IF NOT EXISTS dsr_requests(
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, type VARCHAR(20) NOT NULL,
  detail TEXT, status VARCHAR(10) NOT NULL DEFAULT 'new',
  admin_note TEXT NULL, created TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated TIMESTAMP NULL,
  KEY(user_id), KEY(status));
CREATE TABLE IF NOT EXISTS audit_log(
  id INT AUTO_INCREMENT PRIMARY KEY, admin_id INT NULL, action VARCHAR(40) NOT NULL, target VARCHAR(80),
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY(created));
CREATE TABLE IF NOT EXISTS breach_log(
  id INT AUTO_INCREMENT PRIMARY KEY, discovered_at DATETIME NOT NULL, description TEXT NOT NULL,
  affected INT NOT NULL DEFAULT 0, notified_pdpc DATETIME NULL, notified_users TINYINT NOT NULL DEFAULT 0,
  status VARCHAR(10) NOT NULL DEFAULT 'open', created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS coupons(
  id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(20) NOT NULL UNIQUE,
  kind VARCHAR(10) NOT NULL DEFAULT 'percent',
  value DECIMAL(10,2) NOT NULL, max_discount DECIMAL(10,2) NOT NULL DEFAULT 0,
  min_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  max_uses INT NOT NULL DEFAULT 0, used INT NOT NULL DEFAULT 0,
  once_per_user TINYINT NOT NULL DEFAULT 1, min_level TINYINT NOT NULL DEFAULT 0,
  starts DATE NULL, expires DATE NULL, active TINYINT NOT NULL DEFAULT 1,
  note VARCHAR(120) NULL, created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS coupon_uses(
  id INT AUTO_INCREMENT PRIMARY KEY, coupon_id INT NOT NULL, user_id INT NOT NULL, order_id INT NOT NULL,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY(coupon_id), KEY(user_id));

SQL;

/* หมวดหมู่และตั้งค่าร้านมาตรฐาน — ใส่เฉพาะแถวที่หายไป (INSERT IGNORE) ไม่ทับของเดิม */
$CATS = [
    [1,null,'ไม้ประดับ','🪴',1],[2,null,'ไม้ดอก','🌸',2],[3,null,'ช่อดอกไม้และของขวัญ','💐',3],
    [4,null,'ไม้ผลและสมุนไพร','🍋',4],[5,null,'กระถางและอุปกรณ์','🏺',5],
    [11,1,'ไม้ใบ','🌿',1],[12,1,'กระบองเพชรและไม้อวบน้ำ','🌵',2],[13,1,'ไม้ฟอกอากาศ','🍃',3],
    [21,2,'ไม้ดอกกระถาง','🌺',1],[22,2,'ไม้ดอกตามฤดูกาล','🌼',2],
    [31,3,'ช่อดอกไม้สด','💐',1],[32,3,'กระเช้าและชุดของขวัญ','🎁',2],
    [41,4,'ไม้ผลกระถาง','🍋',1],[42,4,'สมุนไพรและผักสวนครัว','🌱',2],
    [51,5,'กระถาง','🏺',1],[52,5,'ดินและปุ๋ย','🧪',2],[53,5,'อุปกรณ์ดูแลต้นไม้','🧰',3],
];
$SETTINGS = [
    'pp' => '0812345678', 'bank' => 'กสิกรไทย', 'acc' => '123-4-56789-0', 'aname' => 'ร้านสวนสุข', 'cod' => '1',
    'shop_lat' => '13.7563', 'shop_lng' => '100.5018', 'shop_phone' => '081-234-5678', 'shop_email' => 'hello@shop.test',
    'shop_line' => '@suansuk', 'shop_addr' => '99 ถนนสุขุมวิท แขวงคลองเตย เขตคลองเตย กรุงเทพฯ 10110',
    'shop_hours' => 'ทุกวัน 09:00 - 18:00 น.',
    'vat_rate' => '7', 'vat_mode' => 'included', 'ship_base' => '30', 'ship_free_km' => '5', 'ship_per_km' => '5',
    'ship_max' => '300', 'ship_max_km' => '0', 'ship_free_over' => '1500', 'ship_nopin' => '60',
];

/* ---------- ตัวช่วย ---------- */
$log = [];
function say($icon, $text) { global $log; $log[] = [$icon, $text]; }

/** split_top() — แยกส่วนในวงเล็บของ CREATE TABLE ด้วยเครื่องหมายจุลภาคระดับนอกสุด */
function split_top($body) {
    $parts = []; $depth = 0; $cur = '';
    for ($i = 0, $n = strlen($body); $i < $n; $i++) {
        $ch = $body[$i];
        if ($ch === '(') { $depth++; } elseif ($ch === ')') { $depth--; }
        if ($ch === ',' && $depth === 0) { $parts[] = trim($cur); $cur = ''; } else { $cur .= $ch; }
    }
    if (trim($cur) !== '') { $parts[] = trim($cur); }
    return $parts;
}

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $err) {
    exit('<meta charset="utf-8"><p>❌ เชื่อมต่อ MySQL ไม่ได้: ' . e($err->getMessage()) . '</p><p>เช็กว่าเปิด MySQL ใน XAMPP แล้ว และ DB_USER / DB_PASS ใน config.php ถูกต้อง</p>');
}
$one = function ($sql, $params = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($params); return $s; };

/* 1) ฐานข้อมูล */
$hasDb = $one('SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?', [DB_NAME])->fetch();
if (!$hasDb) {
    $pdo->exec('CREATE DATABASE `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    say('➕', 'สร้างฐานข้อมูล ' . DB_NAME . ' ใหม่ (ของเดิมหายไป)');
} else {
    say('✅', 'พบฐานข้อมูล ' . DB_NAME);
}
$pdo->exec('USE `' . DB_NAME . '`');

/* 2) ตารางและคอลัมน์ */
foreach (array_filter(array_map('trim', explode(';', $SCHEMA))) as $create) {
    if (!preg_match('/CREATE TABLE IF NOT EXISTS (\w+)\s*\((.*)\)[^)]*$/s', $create, $m)) { continue; }
    $table = $m[1];
    $exists = $one('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?', [DB_NAME, $table])->fetch();
    if (!$exists) {
        try { $pdo->exec($create); say('➕', "สร้างตาราง $table (ไม่พบ)"); }
        catch (Throwable $err) { say('❌', "สร้างตาราง $table ไม่สำเร็จ: " . $err->getMessage()); }
        continue;
    }
    $have = array_map('strtolower', array_column($one('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?', [DB_NAME, $table])->fetchAll(), 'COLUMN_NAME'));
    $fixed = 0;
    foreach (split_top($m[2]) as $part) {
        if (!preg_match('/^(\w+)\s+(.+)$/s', $part, $c)) { continue; }          // ข้าม KEY(...)
        [$col, $def] = [$c[1], $c[2]];
        if (in_array(strtoupper($col), ['KEY', 'PRIMARY', 'UNIQUE', 'INDEX'], true)) { continue; }

        if (!in_array(strtolower($col), $have, true)) {                          // คอลัมน์หาย → เพิ่ม
            $plain = trim(preg_replace('/\b(AUTO_INCREMENT|PRIMARY KEY|UNIQUE)\b/i', '', $def));
            try { $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $plain"); say('➕', "เพิ่มคอลัมน์ $table.$col"); $fixed++; }
            catch (Throwable $err) { say('❌', "เพิ่มคอลัมน์ $table.$col ไม่สำเร็จ: " . $err->getMessage()); }
            continue;
        }
        // PRIMARY KEY หาย
        if (stripos($def, 'PRIMARY KEY') !== false) {
            $pk = $one("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME='PRIMARY'", [DB_NAME, $table])->fetch();
            if (!$pk) {
                try { $pdo->exec("ALTER TABLE `$table` ADD PRIMARY KEY(`$col`)"); say('🔧', "ใส่ PRIMARY KEY ให้ $table.$col"); $fixed++; }
                catch (Throwable $err) { say('❌', "ใส่ PRIMARY KEY ให้ $table.$col ไม่ได้ (อาจมีค่าซ้ำ): " . $err->getMessage()); }
            }
        }
        // AUTO_INCREMENT หาย (เช่น เผลอเอาติ๊กออกใน phpMyAdmin)
        if (stripos($def, 'AUTO_INCREMENT') !== false) {
            $extra = $one('SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?', [DB_NAME, $table, $col])->fetchColumn();
            if (stripos((string)$extra, 'auto_increment') === false) {
                try { $pdo->exec("ALTER TABLE `$table` MODIFY `$col` INT NOT NULL AUTO_INCREMENT"); say('🔧', "เปิด AUTO_INCREMENT ให้ $table.$col"); $fixed++; }
                catch (Throwable $err) { say('❌', "เปิด AUTO_INCREMENT ให้ $table.$col ไม่ได้: " . $err->getMessage()); }
            }
        }
        // UNIQUE หาย (อีเมลสมาชิกต้องไม่ซ้ำ)
        if (stripos($def, 'UNIQUE') !== false) {
            $uq = $one('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? AND NON_UNIQUE=0', [DB_NAME, $table, $col])->fetch();
            if (!$uq) {
                try { $pdo->exec("ALTER TABLE `$table` ADD UNIQUE(`$col`)"); say('🔧', "ใส่ UNIQUE ให้ $table.$col"); $fixed++; }
                catch (Throwable $err) { say('⚠️', "ใส่ UNIQUE ให้ $table.$col ไม่ได้ (มีค่าซ้ำในตาราง ต้องลบแถวซ้ำก่อน)"); }
            }
        }
    }
    if ($fixed === 0) { say('✅', "ตาราง $table ปกติ"); }
}

/* 3) หมวดหมู่ / ตั้งค่าร้าน ที่หายไป */
$added = 0;
$ins = $pdo->prepare('INSERT IGNORE INTO categories(id,parent_id,name,icon,sort) VALUES(?,?,?,?,?)');
foreach ($CATS as $c) { $ins->execute($c); $added += $ins->rowCount(); }
say($added ? '➕' : '✅', $added ? "เติมหมวดหมู่ที่หายไป $added หมวด" : 'หมวดหมู่ครบ');

$added = 0;
$ins = $pdo->prepare('INSERT IGNORE INTO settings(k,v) VALUES(?,?)');
foreach ($SETTINGS as $k => $v) { $ins->execute([$k, $v]); $added += $ins->rowCount(); }
say($added ? '➕' : '✅', $added ? "เติมค่าตั้งค่าร้านที่หายไป $added ค่า" : 'ค่าตั้งค่าร้านครบ');

/* 4) ข้อมูลที่เชื่อมกันผิด */
$n = $pdo->exec('UPDATE categories c LEFT JOIN categories p ON p.id=c.parent_id SET c.parent_id=NULL WHERE c.parent_id IS NOT NULL AND p.id IS NULL');
if ($n) { say('🔧', "หมวดย่อย $n หมวดอ้างหมวดหลักที่ไม่มี → ย้ายขึ้นเป็นหมวดหลัก"); }

$n = $pdo->exec('UPDATE products p LEFT JOIN categories c ON c.id=p.category_id SET p.category_id=NULL WHERE p.category_id IS NOT NULL AND c.id IS NULL');
if ($n) { say('🔧', "สินค้า $n ชิ้นอ้างหมวดที่ไม่มี → ล้างค่าหมวด"); }

$hasOldCat = $one("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='products' AND COLUMN_NAME='cat'", [DB_NAME])->fetch();
if ($hasOldCat) {   // รุ่นเก่ามีคอลัมน์ cat (ชื่อหมวดเป็นข้อความ) → จับเข้าหมวดหลักตามชื่อ
    $n  = $pdo->exec("UPDATE products SET category_id=1 WHERE category_id IS NULL AND cat='ไม้ประดับ'");
    $n += $pdo->exec("UPDATE products SET category_id=2 WHERE category_id IS NULL AND cat='ไม้ดอก'");
    $n += $pdo->exec("UPDATE products SET category_id=3 WHERE category_id IS NULL AND cat='ช่อดอกไม้'");
    $n += $pdo->exec("UPDATE products SET category_id=5 WHERE category_id IS NULL AND cat LIKE 'กระถาง%'");
    if ($n) { say('🔧', "จับสินค้า $n ชิ้นเข้าหมวดตามชื่อหมวดเดิม"); }
}
$nocat = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE category_id IS NULL')->fetchColumn();
if ($nocat) { say('⚠️', "มีสินค้า $nocat ชิ้นยังไม่มีหมวด (จะไม่โผล่ในหมวดใด) → ไปตั้งหมวดที่หลังบ้าน แท็บ สินค้า"); }

/* 5) บัญชีแอดมิน / รีเซ็ตรหัสผ่านบัญชีทดลอง */
$demo = [
    ['ผู้ดูแลร้าน', 'admin@shop.test', '0800000000', 'Admin@1234', 'admin'],
    ['สมชาย ทดสอบ', 'customer@shop.test', '0812345678', 'Customer@1234', 'customer'],
];
if (isset($_GET['reset_pw'])) {
    foreach ($demo as [$name, $email, $phone, $pw, $role]) {
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        if ($one('SELECT id FROM users WHERE email=?', [$email])->fetch()) {
            $one('UPDATE users SET pass=?, role=? WHERE email=?', [$hash, $role, $email]);
            say('🔑', "รีเซ็ตรหัสผ่าน $email เป็นค่าเริ่มต้นแล้ว");
        } else {
            $one('INSERT INTO users(name,email,phone,addr,pass,role) VALUES(?,?,?,?,?,?)', [$name, $email, $phone, '', $hash, $role]);
            say('➕', "สร้างบัญชี $email ใหม่");
        }
    }
} else {
    $admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    if ($admins === 0) {
        $hash = password_hash('Admin@1234', PASSWORD_DEFAULT);
        if ($one('SELECT id FROM users WHERE email=?', ['admin@shop.test'])->fetch()) {
            $one("UPDATE users SET role='admin', pass=? WHERE email=?", [$hash, 'admin@shop.test']);
        } else {
            $one("INSERT INTO users(name,email,phone,addr,pass,role) VALUES('ผู้ดูแลร้าน','admin@shop.test','0800000000','',?,'admin')", [$hash]);
        }
        say('➕', 'ไม่มีแอดมินเลย → สร้าง/คืนสิทธิ์ admin@shop.test (รหัส Admin@1234)');
    } else {
        say('✅', "มีบัญชีแอดมิน $admins บัญชี");
    }
}

/* 6) ตรวจชุดอักขระ (ถ้าไม่ใช่ utf8mb4 ภาษาไทยอาจเพี้ยน) */
$bad = $one("SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_COLLATION NOT LIKE 'utf8mb4%'", [DB_NAME])->fetchAll();
foreach ($bad as $b) { say('⚠️', "ตาราง {$b['TABLE_NAME']} ใช้ชุดอักขระ {$b['TABLE_COLLATION']} (ควรเป็น utf8mb4)"); }

/* 7) สรุปจำนวนแถวของแต่ละตาราง */
$counts = [];
foreach (['users','categories','products','orders','order_items','order_events','reviews','messages','slides','settings','reports'] as $t) {
    try { $counts[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn(); } catch (Throwable $err) { $counts[$t] = '—'; }
}
?>
<!DOCTYPE html>
<html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ซ่อมฐานข้อมูล · ร้านสวนสุข</title>
<style>body{font:15px/1.6 'Noto Sans Thai',system-ui,sans-serif;max-width:720px;margin:20px auto;padding:0 14px;background:#f3faf5;color:#17301f}
li{list-style:none;padding:6px 10px;border-bottom:1px solid #d2eadb}ul{padding:0;background:#fff;border-radius:12px;border:1px solid #d2eadb}
table{border-collapse:collapse}td{padding:3px 14px 3px 0}a.b{display:inline-block;background:#1f9d55;color:#fff;padding:8px 14px;border-radius:10px;text-decoration:none;margin:4px 6px 4px 0}
.w{background:#fde2df;padding:10px 14px;border-radius:10px}</style></head><body>
<h1>🛠 ซ่อมฐานข้อมูล</h1>
<ul><?php foreach ($log as [$i, $t]): ?><li><?= $i ?> <?= e($t) ?></li><?php endforeach; ?></ul>
<h3>จำนวนข้อมูลในแต่ละตาราง</h3>
<table><?php foreach ($counts as $t => $c): ?><tr><td><?= e($t) ?></td><td><b><?= e($c) ?></b></td></tr><?php endforeach; ?></table>
<p>
  <a class="b" href="../index.php">ไปหน้าร้าน</a>
  <a class="b" href="../login.php">เข้าสู่ระบบ</a>
  <a class="b" href="repair_db.php?reset_pw=1" onclick="return confirm('รีเซ็ตรหัสผ่านบัญชีทดลอง 2 บัญชีเป็นค่าเริ่มต้น?')">🔑 รีเซ็ตรหัสผ่านบัญชีทดลอง</a>
</p>
<p class="w">⚠️ ใช้เสร็จแล้วให้ลบไฟล์ <b>repair_db.php</b> ทิ้ง</p>
</body></html>
