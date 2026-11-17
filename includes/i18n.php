<?php
/**
 * i18n.php — สลับภาษาไทย / English
 * หลักการ: โค้ดและฐานข้อมูลยังเป็นภาษาไทยเหมือนเดิม (ค่าสถานะ ชื่อหมวด ฯลฯ ไม่เปลี่ยน)
 * เมื่อผู้ใช้เลือก English ระบบจะ "แปลตอนแสดงผล" ด้วยพจนานุกรม i18n_en.php ก่อนส่งหน้าเว็บให้เบราว์เซอร์
 *   - ข้อความที่ไม่อยู่ในพจนานุกรม (เช่น ชื่อสินค้าที่แอดมินเพิ่มเอง ข้อความแชท) จะแสดงเป็นภาษาเดิม
 *   - ช่องกรอก (value=) และข้อความในกล่องพิมพ์ (textarea) ไม่ถูกแปล เพื่อไม่ให้ข้อมูลที่ส่งกลับเพี้ยน
 *   - หลังบ้านแอดมิน หน้าตรวจเช็ค และไฟล์ดาวน์โหลด ไม่ถูกแปล (ใช้ภาษาไทยเสมอ)
 * เพิ่มคำแปล: เปิด i18n_en.php แล้วเพิ่มบรรทัด 'ข้อความไทย' => 'English text',
 * จำตัวเลือกด้วยคุกกี้ "lang" (เก็บเมื่อผู้ใช้กดสลับภาษาเองเท่านั้น)
 */

/** lang() — ภาษาที่ใช้อยู่: 'th' หรือ 'en' (ค่าเริ่มต้น th) */
function lang() {
    return (($_COOKIE['lang'] ?? '') === 'en') ? 'en' : 'th';
}

/** หน้าที่ไม่แปล (หลังบ้าน/เครื่องมือ/ไฟล์ดาวน์โหลด) */
const I18N_SKIP = ['admin', 'demo_check', 'file', 'export', 'install', 'repair_db', 'set_password', 'lang', 'consent', 'logout'];

/** i18n_map() — โหลดพจนานุกรมครั้งเดียว (strtr จับคู่ข้อความที่ยาวที่สุดก่อนให้เอง จึงไม่ต้องสร้าง regex ขนาดใหญ่) */
function i18n_map() {
    static $dict = null;
    if ($dict === null) {
        $dict = require __DIR__ . '/i18n_en.php';
    }
    return $dict;
}

/** i18n_translate() — แปล HTML ทั้งหน้า (ข้ามค่าในช่องกรอกและ textarea) */
function i18n_translate($html) {
    $dict = i18n_map();
    $keep = [];
    $mask = function ($m) use (&$keep) {
        $keep[] = $m[0];
        return "\x01" . (count($keep) - 1) . "\x02";
    };
    // ป้องกัน: textarea ทั้งก้อน, ค่า value="..." (ฟอร์มต้องส่งค่าไทยเดิมกลับมา)
    $html = preg_replace_callback('/<script>window\.__T=.*?<\/script>|<textarea\b.*?<\/textarea>|\bvalue="[^"]*"|\bvalue=\'[^\']*\'/s', $mask, $html);
    $html = strtr($html, $dict);
    $html = preg_replace_callback('/\x01(\d+)\x02/', function ($m) use (&$keep) { return $keep[(int)$m[1]]; }, $html);
    return str_replace('<html lang="th">', '<html lang="en">', $html);
}

/**
 * i18n_start() — เริ่มดักผลลัพธ์ของหน้า (เรียกตอนโหลด config.php) ทำเฉพาะเมื่อเป็น English และเป็นหน้าเว็บทั่วไป
 */
function i18n_start() {
    if (lang() !== 'en' || PHP_SAPI === 'cli') {
        return;
    }
    $page = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
    if (in_array($page, I18N_SKIP, true) || defined('I18N_OFF')) {
        return;
    }
    ob_start(function ($out) {
        foreach (headers_list() as $h) {
            if (stripos($h, 'content-type:') === 0 && stripos($h, 'text/html') === false) {
                return $out;   // ไม่ใช่หน้า HTML (รูป ไฟล์ JSON ฯลฯ) ไม่แปล
            }
        }
        return i18n_translate($out);
    });
}

/** lang_switch() — ปุ่มสลับภาษาใน header: ไทย | EN (ปุ่มของภาษาที่ใช้อยู่จะถูกเน้น) */
function lang_switch() {
    $self = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: 'index.php');
    $qs   = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
    $next = safe_next($self . ($qs !== '' ? '?' . $qs : ''));
    if ($next === '' || defined('IS_404')) {
        $next = 'index.php';
    }
    $cur = lang();
    $h = '<span class="langsw" role="group" aria-label="ภาษา / Language">🌐';
    foreach (['th' => 'ไทย', 'en' => 'EN'] as $code => $label) {
        $h .= '<a href="lang.php?l=' . $code . '&amp;next=' . rawurlencode($next) . '"' . ($cur === $code ? ' class="on" aria-current="true"' : '')
            . ' hreflang="' . $code . '" lang="' . $code . '">' . $label . '</a>';
    }
    return $h . '</span>';
}

/**
 * i18n_js() — ตัวแปลสำหรับสคริปต์ภายนอก (geo.js ที่ไม่ผ่านการแปลของหน้า): ส่งเฉพาะคำที่ geo.js ใช้ไปให้เบราว์เซอร์
 * แล้ว window.__T(ข้อความ) จะแทนที่ข้อความไทยเป็นอังกฤษ (เฉพาะตอนเลือก English)
 */
function i18n_js() {
    if (lang() !== 'en') {
        return '';
    }
    $src = (string)@file_get_contents(ROOT_DIR . '/assets/js/geo.js');
    $sub = [];
    foreach (i18n_map() as $th => $en) {
        if ($th !== '' && strpos($src, $th) !== false) {
            $sub[$th] = $en;
        }
    }
    uksort($sub, function ($a, $b) { return strlen($b) - strlen($a); });
    return '<script>window.__T=function(s){var d=' . json_encode($sub, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)
        . ';for(var k in d){s=s.split(k).join(d[k]);}return s;};</script>' . "\n";
}
