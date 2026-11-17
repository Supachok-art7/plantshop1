<?php
/**
 * pdpa_lib.php — ฟังก์ชันกลางของระบบ PDPA (พ.ร.บ.คุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562)
 * ถูกเรียกอัตโนมัติจากท้ายไฟล์ config.php ทุกหน้าจึงใช้ฟังก์ชันเหล่านี้ได้ทันที
 * ครอบคลุม: ความยินยอม (คุกกี้/นโยบาย/การตลาด) · คำขอใช้สิทธิ์ของเจ้าของข้อมูล (DSR)
 *           ดาวน์โหลดข้อมูลของฉัน · ลบ/ทำให้ไม่ระบุตัวตน · บันทึกการเข้าถึง (audit)
 */

/** ประเภทคำขอใช้สิทธิ์ของเจ้าของข้อมูล (ตามมาตรา 30-36) */
const DSR_TYPES = [
    'access'      => 'ขอเข้าถึง/ขอสำเนาข้อมูล',
    'correct'     => 'ขอแก้ไขข้อมูลให้ถูกต้อง',
    'erase'       => 'ขอลบ/ทำลายข้อมูล',
    'restrict'    => 'ขอระงับการใช้ข้อมูล',
    'object'      => 'คัดค้านการประมวลผลข้อมูล',
    'portability' => 'ขอโอนย้ายข้อมูล',
    'withdraw'    => 'ถอนความยินยอม',
];

/** สถานะคำขอ: รอดำเนินการ → กำลังดำเนินการ → เสร็จสิ้น / ปฏิเสธ (ต้องแจ้งเหตุผล) */
const DSR_STATUS = ['new' => 'รอดำเนินการ', 'working' => 'กำลังดำเนินการ', 'done' => 'เสร็จสิ้น', 'rejected' => 'ปฏิเสธ'];

/** สถานะออเดอร์ที่ "จบแล้ว" — ลบบัญชีได้เมื่อไม่มีออเดอร์ที่ยังค้างอยู่ */
const FINAL_STATUS_SQL = "'ได้รับสินค้าแล้ว','ยกเลิก'";

/** กฎหมายกำหนดให้ตอบคำขอภายในกี่วัน */
const DSR_DAYS = 30;


/* ============================================================
 * 1) เวอร์ชันนโยบาย + คุกกี้
 * ============================================================ */

/** pdpa_version() — เวอร์ชันนโยบายความเป็นส่วนตัวปัจจุบัน (แอดมินเพิ่มเลขได้เมื่อแก้นโยบาย → สมาชิกต้องรับทราบใหม่) */
function pdpa_version() {
    return setting('pdpa_version', '1.0');
}

/** is_https() — เว็บนี้เปิดผ่าน https หรือไม่ (ใช้ตั้งค่าคุกกี้ Secure) */
function is_https() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
}

/** cookie_choice() — ผู้เยี่ยมชมเลือกอะไรไว้: 'all' | 'necessary' | null (ยังไม่เลือก) */
function cookie_choice() {
    $v = $_COOKIE['pdpa_ck'] ?? '';
    return in_array($v, ['all', 'necessary'], true) ? $v : null;
}

/** set_cookie_choice() — จำตัวเลือกคุกกี้ไว้ 180 วัน (ตัวคุกกี้นี้เองเป็นคุกกี้ที่จำเป็น) */
function set_cookie_choice($choice) {
    if (!in_array($choice, ['all', 'necessary'], true)) {
        return;
    }
    setcookie('pdpa_ck', $choice, ['expires' => time() + 86400 * 180, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true, 'secure' => is_https()]);
    $_COOKIE['pdpa_ck'] = $choice;
}

/**
 * analytics_id() — รหัส Google Analytics 4 (รูปแบบ G-XXXXXXXXXX) ที่แอดมินตั้งไว้ในหลังบ้าน แท็บ "ข้อมูลร้าน"
 * คืนค่าว่างถ้ายังไม่ตั้ง หรือรูปแบบไม่ถูกต้อง = ร้านไม่ใช้ระบบวิเคราะห์เลย (ไม่แสดงในแบนเนอร์/นโยบายด้วย)
 */
function analytics_id() {
    static $id = null;
    if ($id === null) {
        $id = '';
        try {
            $v = strtoupper(trim(setting('ga4_id', '')));
            $id = preg_match('/^G-[A-Z0-9]{4,14}$/', $v) ? $v : '';
        } catch (Throwable $err) {
            // ยังไม่มีตาราง settings: ถือว่าไม่ใช้
        }
    }
    return $id;
}

/** cookie_optional_text() — ข้อความสั้นๆ บอกว่า "ยอมรับทั้งหมด" ครอบคลุมบริการเสริมอะไรบ้าง (ใช้ในแบนเนอร์/ศูนย์ความเป็นส่วนตัว) */
function cookie_optional_text() {
    return analytics_id() !== '' ? 'ฟอนต์ภาษาไทยจาก Google และสถิติการเข้าชมแบบรวม (Google Analytics)' : 'ฟอนต์ภาษาไทยจาก Google';
}

/** หน้าสาธารณะที่ใช้ GA4 ได้ (หน้าที่มีข้อมูลส่วนบุคคล เช่น บัญชี ชำระเงิน แชท ไม่อยู่ในรายการนี้) */
const ANALYTICS_PAGES = ['index', 'products', 'product', 'about', 'contact', 'faq', 'policy', 'cart', '404'];

/**
 * analytics_decision() — ตัดสินว่า "หน้านี้ จะโหลด GA4 หรือไม่" พร้อมเหตุผล คืน [true/false, เหตุผล]
 * ใช้ทั้งในการพิมพ์สคริปต์จริง (analytics_snippet) และหน้าตรวจเช็ค demo_check.php จึงได้ผลตรงกันเสมอ
 *   $page = ชื่อไฟล์ไม่มี .php  $choice = cookie_choice()  $user = me()  $id = รหัส GA4
 */
function analytics_decision($page, $choice, $user, $id) {
    if ($id === '')                          { return [false, 'ร้านยังไม่ได้ตั้งรหัส GA4']; }
    if ($choice === null)                    { return [false, 'ผู้เยี่ยมชมยังไม่ได้เลือกคุกกี้']; }
    if ($choice !== 'all')                   { return [false, 'ผู้เยี่ยมชมเลือก "เฉพาะที่จำเป็น"']; }
    if ($user && $user['role'] === 'admin')  { return [false, 'ไม่ติดตามแอดมิน']; }
    if (!in_array($page, ANALYTICS_PAGES, true)) { return [false, 'หน้านี้มีข้อมูลส่วนบุคคล จึงไม่วัดสถิติ']; }
    return [true, 'ยินยอมแล้ว และเป็นหน้าสาธารณะ'];
}

/**
 * analytics_snippet() — พิมพ์สคริปต์ Google Analytics 4 "เฉพาะเมื่อ" ครบทุกข้อ (PDPA: ต้องได้รับความยินยอมก่อนเก็บข้อมูล)
 *   1) แอดมินตั้งรหัส GA4 ไว้   2) ผู้เยี่ยมชมกด "ยอมรับทั้งหมด"   3) ไม่ใช่แอดมินที่ล็อกอินอยู่
 *   4) เป็นหน้าสาธารณะเท่านั้น — หน้าบัญชี ชำระเงิน ติดตามพัสดุ แชท ฯลฯ ไม่ส่งข้อมูลไป Google เลย
 * ปิดสัญญาณโฆษณา (Google Signals / ad personalization) และไม่ส่งค่าคุกกี้โฆษณาใดๆ
 */
function analytics_snippet() {
    $id = analytics_id();
    [$ok] = analytics_decision(basename($_SERVER['SCRIPT_NAME'], '.php'), cookie_choice(), me(), $id);
    if (!$ok) {
        return;
    }
    ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($id) ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'granted'});
gtag('js', new Date());
gtag('config', <?= json_encode($id) ?>, {allow_google_signals: false, allow_ad_personalization_signals: false});
</script>
<?php
}

/**
 * clear_analytics_cookies() — ลบคุกกี้ของ Google Analytics (_ga, _ga_XXXX, _gid, _gat) ที่เคยตั้งไว้ตอนยอมรับ
 * เรียกเมื่อผู้ใช้เปลี่ยนเป็น "เฉพาะที่จำเป็น" (การถอนความยินยอมต้องทำได้ง่ายเท่าตอนให้)
 */
function clear_analytics_cookies() {
    $host    = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    $domains = array_unique(['', $host, '.' . $host]);
    foreach (array_keys($_COOKIE) as $name) {
        if (preg_match('/^(_ga|_gid|_gat)/', $name)) {
            foreach ($domains as $d) {
                $opt = ['expires' => time() - 86400, 'path' => '/', 'samesite' => 'Lax'];
                if ($d !== '') { $opt['domain'] = $d; }
                setcookie($name, '', $opt);
            }
            unset($_COOKIE[$name]);
        }
    }
}

/** cookie_banner() — พิมพ์แบนเนอร์ขอความยินยอมคุกกี้ (แสดงเฉพาะเมื่อยังไม่เลือก) เรียกจาก foot() */
function cookie_banner() {
    if (cookie_choice() !== null) {
        return;
    }
    $next = defined('IS_404') ? 'index.php' : basename($_SERVER['SCRIPT_NAME']) . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
    ?>
<div class="ckbar" role="dialog" aria-label="ความยินยอมการใช้คุกกี้">
  <div>
    <b>🍪 เว็บไซต์นี้ใช้คุกกี้</b>
    <p>เราใช้คุกกี้ที่จำเป็นเพื่อให้ระบบเข้าสู่ระบบและตะกร้าสินค้าทำงาน (ปิดไม่ได้) ส่วน<?= e(cookie_optional_text()) ?> เป็นบริการของบุคคลที่สาม
       จะโหลดต่อเมื่อคุณกด "ยอมรับทั้งหมด" เท่านั้น คุณเลือกได้อย่างอิสระและเปลี่ยนใจได้ทุกเมื่อ อ่านรายละเอียดที่ <a href="policy.php?p=cookies">นโยบายคุกกี้</a> และ <a href="policy.php?p=privacy">นโยบายความเป็นส่วนตัว</a></p>
  </div>
  <form method="post" action="consent.php">
    <?= csrf() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <button name="choice" value="necessary" class="ghost">เฉพาะที่จำเป็น</button>
    <button name="choice" value="all" class="ghost">ยอมรับทั้งหมด</button>
  </form>
</div>
<?php
}


/* ============================================================
 * 2) บันทึกความยินยอม
 * ============================================================ */

/**
 * consent_log() — เก็บหลักฐานความยินยอม (ใคร ยินยอมเรื่องอะไร เวอร์ชันไหน เมื่อไร ให้/ถอน)
 *   $purpose: 'policy' (นโยบายความเป็นส่วนตัว+เงื่อนไข) | 'marketing' (ข่าวสารโปรโมชั่น) | 'cookies'
 */
function consent_log($userId, $purpose, $granted, $version = null) {
    q('INSERT INTO consents(user_id,purpose,version,granted) VALUES(?,?,?,?)',
      [$userId, $purpose, $version ?? pdpa_version(), $granted ? 1 : 0]);
}

/** accept_policy() — ผู้ใช้รับทราบนโยบายเวอร์ชันปัจจุบัน */
function accept_policy($userId) {
    q('UPDATE users SET pdpa_version=? WHERE id=?', [pdpa_version(), $userId]);
    consent_log($userId, 'policy', true);
}

/** set_marketing() — ให้/ถอนความยินยอมรับข่าวสารการตลาด (ถอนได้ทุกเมื่อ) */
function set_marketing($userId, $on) {
    q('UPDATE users SET marketing_opt_in=? WHERE id=?', [$on ? 1 : 0, $userId]);
    consent_log($userId, 'marketing', $on);
}

/** consent_history() — ประวัติความยินยอมของสมาชิก เรียงใหม่สุดก่อน */
function consent_history($userId, $limit = 30) {
    return q('SELECT * FROM consents WHERE user_id=? ORDER BY id DESC LIMIT ' . (int)$limit, [$userId])->fetchAll();
}

/** policy_pending() — สมาชิก (ไม่ใช่แอดมิน) ที่ยังไม่ได้รับทราบนโยบายเวอร์ชันล่าสุดหรือไม่ */
function policy_pending($user) {
    return $user && $user['role'] !== 'admin' && ($user['pdpa_version'] ?? null) !== pdpa_version();
}


/* ============================================================
 * 3) บันทึกการเข้าถึงข้อมูล (audit) — หลักฐานความรับผิดชอบของผู้ควบคุมข้อมูล
 * ============================================================ */

/** audit() — จดว่าแอดมินทำอะไรกับข้อมูลส่วนบุคคล เช่น เปิดดูเอกสารยืนยันตัวตน  audit('view_doc', 'file.jpg') */
function audit($action, $target = '') {
    $u = me();
    try {
        q('INSERT INTO audit_log(admin_id,action,target) VALUES(?,?,?)', [$u ? $u['id'] : null, mb_substr($action, 0, 40), mb_substr($target, 0, 80)]);
    } catch (Throwable $err) {
        // ยังไม่ได้อัปเดตฐานข้อมูล: ข้าม ไม่ให้หน้าเว็บล่ม
    }
}


/* ============================================================
 * 4) คำขอใช้สิทธิ์ (DSR)
 * ============================================================ */

/** dsr_due() — วันครบกำหนดตอบคำขอ = วันที่ยื่น + 30 วัน (รูปแบบ Y-m-d) */
function dsr_due($created) {
    return date('Y-m-d', strtotime($created . ' +' . DSR_DAYS . ' days'));
}

/**
 * submit_dsr() — สมาชิกยื่นคำขอใช้สิทธิ์ คืน true/false (ตั้งข้อความแจ้งเตือนให้เอง)
 * จำกัด 5 คำขอ/วัน กันสแปม
 */
function submit_dsr($user, $type, $detail) {
    $detail = mb_substr(trim($detail), 0, 1500);
    if (!isset(DSR_TYPES[$type]) || $detail === '') {
        flash('กรุณาเลือกประเภทคำขอและอธิบายรายละเอียด');
        return false;
    }
    $recent = q('SELECT COUNT(*) FROM dsr_requests WHERE user_id=? AND created > (NOW() - INTERVAL 1 DAY)', [$user['id']])->fetchColumn();
    if ($recent >= 5) {
        flash('ยื่นคำขอบ่อยเกินไป กรุณารอสักครู่');
        return false;
    }
    q('INSERT INTO dsr_requests(user_id,type,detail) VALUES(?,?,?)', [$user['id'], $type, $detail]);
    flash('รับคำขอแล้ว ร้านจะตอบกลับภายใน ' . DSR_DAYS . ' วัน ติดตามสถานะได้ในหน้านี้');
    return true;
}


/* ============================================================
 * 5) ดาวน์โหลดข้อมูล / ลบบัญชี
 * ============================================================ */

/** user_has_active_orders() — มีออเดอร์ที่ยังไม่จบ (ยังไม่ส่งถึง/ไม่ยกเลิก) หรือไม่ — ถ้ามีจะลบบัญชีทันทีไม่ได้ */
function user_has_active_orders($userId) {
    return (bool)q('SELECT 1 FROM orders WHERE user_id=? AND status NOT IN (' . FINAL_STATUS_SQL . ') LIMIT 1', [$userId])->fetch();
}

/**
 * export_user_data() — รวบรวมข้อมูลทั้งหมดของสมาชิกเป็น array (ใช้สร้างไฟล์ JSON ให้ดาวน์โหลด)
 * ไม่รวมรหัสผ่านและเอกสารยืนยันตัวตน (เอกสารถูกลบหลังตรวจอยู่แล้ว)
 */
function export_user_data($userId) {
    $user = q('SELECT * FROM users WHERE id=?', [$userId])->fetch();
    unset($user['pass'], $user['id_img'], $user['selfie_img']);

    $orders = q('SELECT * FROM orders WHERE user_id=? ORDER BY id', [$userId])->fetchAll();
    foreach ($orders as &$o) {
        $o['items']  = q('SELECT name,price,qty FROM order_items WHERE order_id=?', [$o['id']])->fetchAll();
        $o['events'] = q('SELECT status,note,lat,lng,created FROM order_events WHERE order_id=? ORDER BY id', [$o['id']])->fetchAll();
    }
    unset($o);

    return [
        'exported_at'      => date('c'),
        'policy_version'   => pdpa_version(),
        'profile'          => $user,
        'orders'           => $orders,
        'reviews'          => q('SELECT product_id,rating,body,img,created FROM reviews WHERE user_id=?', [$userId])->fetchAll(),
        'chat_messages'    => q('SELECT sender,body,created FROM messages WHERE user_id=? ORDER BY id', [$userId])->fetchAll(),
        'reports'          => q('SELECT type,subject,body,status,admin_reply,created FROM reports WHERE user_id=?', [$userId])->fetchAll(),
        'consents'         => q('SELECT purpose,version,granted,created FROM consents WHERE user_id=? ORDER BY id', [$userId])->fetchAll(),
        'rights_requests'  => q('SELECT type,detail,status,admin_note,created FROM dsr_requests WHERE user_id=?', [$userId])->fetchAll(),
    ];
}

/**
 * anonymize_user() — ลบข้อมูลส่วนบุคคลของสมาชิก (ใช้ทั้งตอนสมาชิกลบเองและตอนแอดมินทำตามคำขอ)
 *   ลบทิ้ง: รูปโปรไฟล์ เอกสารยืนยันตัวตน รีวิว(และรูป) แชท รายงาน(และรูป)
 *   ทำให้ไม่ระบุตัวตน: บัญชี (ชื่อ อีเมล เบอร์ ที่อยู่ พิกัด วันเกิด เพศ) และรหัสผ่านถูกแทนด้วยค่าสุ่ม
 *   เก็บไว้: ออเดอร์และสลิป ตามหน้าที่ทางกฎหมายด้านบัญชี/ภาษี (แอดมินล้างให้ตามระยะเวลาเก็บรักษา) และบันทึกความยินยอม
 * คืน true ถ้าสำเร็จ (บัญชีแอดมินลบแบบนี้ไม่ได้)
 */
function anonymize_user($id) {
    $u = q('SELECT * FROM users WHERE id=?', [$id])->fetch();
    if (!$u || $u['role'] === 'admin') {
        return false;
    }
    // รวบรวมไฟล์ที่ต้องลบ (ลบจริงหลังบันทึกฐานข้อมูลสำเร็จ)
    $files = [['uploads', $u['avatar']], ['private', $u['id_img']], ['private', $u['selfie_img']]];
    foreach (q('SELECT img FROM reviews WHERE user_id=?', [$id])->fetchAll() as $r) { $files[] = ['uploads', $r['img']]; }
    foreach (q('SELECT img FROM reports WHERE user_id=?', [$id])->fetchAll() as $r) { $files[] = ['uploads', $r['img']]; }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('DELETE FROM reviews WHERE user_id=?', [$id]);
        q('DELETE FROM messages WHERE user_id=?', [$id]);
        q('DELETE FROM reports WHERE user_id=?', [$id]);
        q("UPDATE users SET name='ผู้ใช้ที่ลบบัญชีแล้ว', email=?, phone=NULL, addr=NULL, avatar=NULL, birthdate=NULL, gender=NULL,
             addr_line=NULL, subdistrict=NULL, district=NULL, province=NULL, postcode=NULL, lat=NULL, lng=NULL,
             verify_status='none', verify_note=NULL, id_img=NULL, selfie_img=NULL, marketing_opt_in=0, pdpa_version=NULL,
             pass=?, deleted_at=NOW() WHERE id=?",
          ['deleted-' . $id . '@deleted.invalid', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $id]);
        $pdo->commit();
    } catch (Throwable $err) {
        $pdo->rollBack();
        return false;
    }
    foreach ($files as $f) {
        if ($f[1]) {
            @unlink(ROOT_DIR . '/' . $f[0] . '/' . basename($f[1]));
        }
    }
    return true;
}
