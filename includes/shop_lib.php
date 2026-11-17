<?php
/**
 * shop_lib.php — คำนวณยอดสั่งซื้อ: ส่วนลด (โค้ด) + ค่าส่งตามระยะทาง + ภาษีมูลค่าเพิ่ม (VAT) + แต้มต้นไม้
 * ใช้ร่วมกันทั้งหน้าตะกร้า (แสดงตัวอย่างยอด) และตอนกดสั่งซื้อจริง เพื่อให้ตัวเลขตรงกันเสมอ
 * (ฝั่งเซิร์ฟเวอร์เป็นตัวตัดสินเสมอ ไม่เชื่อตัวเลขที่ส่งมาจากเบราว์เซอร์)
 */

/* ============================================================
 * 1) ตั้งค่าที่แอดมินแก้ได้ (แท็บ "ภาษี/ค่าส่ง") พร้อมค่าเริ่มต้น
 * ============================================================ */

/** shop_fee_config() — อ่านค่าตั้งค่าภาษี/ค่าส่ง คืน array ที่ตัวเลขถูกบังคับให้อยู่ในช่วงปลอดภัย */
function shop_fee_config() {
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $cfg = [
        'vat_rate'     => min(30.0, max(0.0, (float)setting('vat_rate', '7'))),       // อัตรา VAT % (0 = ไม่คิด)
        'vat_mode'     => setting('vat_mode', 'included') === 'excluded' ? 'excluded' : 'included',   // รวมในราคาแล้ว / บวกเพิ่ม
        'tax_id'       => trim(setting('tax_id', '')),                                  // เลขผู้เสียภาษีของร้าน (แสดงในใบเสร็จ)
        'tax_name'     => trim(setting('tax_name', '')),
        'ship_base'    => max(0.0, (float)setting('ship_base', '30')),                  // ค่าส่งเริ่มต้น (บาท)
        'ship_free_km' => max(0.0, (float)setting('ship_free_km', '5')),                // ระยะแรกที่ไม่คิดเพิ่ม (กม.)
        'ship_per_km'  => max(0.0, (float)setting('ship_per_km', '5')),                 // กม.ละกี่บาท (หลังระยะแรก)
        'ship_max'     => max(0.0, (float)setting('ship_max', '300')),                  // ค่าส่งสูงสุด (บาท) 0 = ไม่จำกัด
        'ship_max_km'  => max(0.0, (float)setting('ship_max_km', '0')),                 // ส่งไกลสุดกี่ กม. 0 = ไม่จำกัด
        'ship_free_over' => max(0.0, (float)setting('ship_free_over', '1500')),         // ซื้อครบเท่านี้ส่งฟรี 0 = ปิด
        'ship_nopin'   => max(0.0, (float)setting('ship_nopin', '60')),                 // ค่าส่งเมื่อลูกค้ายังไม่ปักหมุด
        'shop_lat'     => (float)setting('shop_lat', '13.7563'),
        'shop_lng'     => (float)setting('shop_lng', '100.5018'),
    ];
    return $cfg;
}

/** money() — ปัดเป็นสตางค์ (2 ตำแหน่ง) กันเศษทศนิยมคลาดเคลื่อน */
function money($x) {
    return round((float)$x + 1e-9, 2);
}

/** baht2() — แสดงราคาแบบมีสตางค์เมื่อมีเศษ  ฿1,234 หรือ ฿1,234.50 */
function baht2($amount) {
    $a = (float)$amount;
    return '฿' . number_format($a, fmod(round($a * 100), 100) == 0 ? 0 : 2);
}

/* ============================================================
 * 2) ระยะทาง + ค่าส่ง
 * ============================================================ */

/** distance_km() — ระยะทางเส้นตรงระหว่างสองพิกัด (Haversine) หน่วยกิโลเมตร */
function distance_km($lat1, $lng1, $lat2, $lng2) {
    $r  = 6371.0;
    $dl = deg2rad($lat2 - $lat1);
    $dg = deg2rad($lng2 - $lng1);
    $a  = sin($dl / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dg / 2) ** 2;
    return 2 * $r * asin(min(1.0, sqrt($a)));
}

/**
 * shipping_quote() — คิดค่าส่ง  คืน [ค่าส่ง, ระยะทาง กม. หรือ null, ข้อความอธิบาย, ส่งได้ไหม]
 *   สูตร: ค่าส่งเริ่มต้น + (ระยะทาง − ระยะฟรี) × กม.ละกี่บาท  ปัดขึ้นเป็นหลัก 5 บาท  ไม่เกินค่าส่งสูงสุด
 *   ซื้อครบยอดที่กำหนด = ส่งฟรี · ไม่ปักหมุด = ใช้ค่าส่งมาตรฐาน (ปักหมุดแล้วคิดตามระยะจริง)
 *   ระยะทางเป็นเส้นตรงบนแผนที่ (ไม่ใช่ระยะถนน) ใช้ประมาณค่าส่งเท่านั้น
 */
function shipping_quote($goodsAfterDiscount, $lat, $lng) {
    $c  = shop_fee_config();
    $km = null;
    if ($lat !== null && $lng !== null) {
        $km = round(distance_km($c['shop_lat'], $c['shop_lng'], (float)$lat, (float)$lng), 1);
    }
    if ($km !== null && $c['ship_max_km'] > 0 && $km > $c['ship_max_km']) {
        return [0.0, $km, 'อยู่ไกลเกิน ' . rtrim(rtrim(number_format($c['ship_max_km'], 1), '0'), '.') . ' กม. ร้านยังไม่จัดส่งถึง', false];
    }
    if ($c['ship_free_over'] > 0 && $goodsAfterDiscount >= $c['ship_free_over']) {
        return [0.0, $km, 'ส่งฟรี (ซื้อครบ ' . baht($c['ship_free_over']) . ')', true];
    }
    if ($km === null) {
        return [money($c['ship_nopin']), null, 'ค่าส่งมาตรฐาน — ปักหมุดบนแผนที่เพื่อคิดตามระยะทางจริง', true];
    }
    $fee = $c['ship_base'] + max(0.0, $km - $c['ship_free_km']) * $c['ship_per_km'];
    $fee = ceil($fee / 5) * 5;
    if ($c['ship_max'] > 0) {
        $fee = min($fee, $c['ship_max']);
    }
    return [money($fee), $km, 'ระยะทางประมาณ ' . number_format($km, 1) . ' กม.', true];
}

/* ============================================================
 * 3) โค้ดส่วนลด
 * ============================================================ */

/** coupon_code() — ทำความสะอาดโค้ดที่พิมพ์มา: ตัวพิมพ์ใหญ่ เหลือ A-Z 0-9 และ - _ ไม่เกิน 20 ตัว */
function coupon_code($raw) {
    return substr(preg_replace('/[^A-Z0-9_-]/', '', strtoupper(trim((string)$raw))), 0, 20);
}

/**
 * coupon_check() — ตรวจโค้ดกับตะกร้านี้  คืน [แถวโค้ด หรือ null, ข้อความ, ส่วนลด (บาท)]
 * เงื่อนไข: เปิดใช้อยู่ · ไม่หมดอายุ · ยังไม่เต็มสิทธิ์ · ใช้ได้คนละครั้ง · ยอดขั้นต่ำ · ระดับต้นไม้ขั้นต่ำ
 * ส่วนลดคิดจาก "ราคาสินค้า" ไม่รวมค่าส่ง และไม่เกินราคาสินค้า
 */
function coupon_check($code, $user, $subtotal) {
    $code = coupon_code($code);
    if ($code === '') {
        return [null, 'กรุณาใส่โค้ดส่วนลด', 0.0];
    }
    try {
        $c = q('SELECT * FROM coupons WHERE code=?', [$code])->fetch();
    } catch (Throwable $err) {
        return [null, 'ระบบโค้ดส่วนลดยังไม่พร้อมใช้งาน', 0.0];
    }
    if (!$c || !(int)$c['active']) {
        return [null, 'ไม่พบโค้ดนี้ หรือโค้ดถูกปิดใช้งานแล้ว', 0.0];
    }
    if ($c['starts'] !== null && $c['starts'] > date('Y-m-d')) {
        return [null, 'โค้ดนี้ยังไม่ถึงวันเริ่มใช้ (เริ่ม ' . thai_date($c['starts']) . ')', 0.0];
    }
    if ($c['expires'] !== null && $c['expires'] < date('Y-m-d')) {
        return [null, 'โค้ดนี้หมดอายุแล้ว', 0.0];
    }
    if ((int)$c['max_uses'] > 0 && (int)$c['used'] >= (int)$c['max_uses']) {
        return [null, 'โค้ดนี้ถูกใช้ครบจำนวนสิทธิ์แล้ว', 0.0];
    }
    if ($subtotal < (float)$c['min_total']) {
        return [null, 'ต้องซื้อสินค้าอย่างน้อย ' . baht($c['min_total']) . ' จึงใช้โค้ดนี้ได้', 0.0];
    }
    if ($user) {
        if ((int)$c['once_per_user'] && q('SELECT COUNT(*) FROM coupon_uses WHERE coupon_id=? AND user_id=?', [$c['id'], $user['id']])->fetchColumn() > 0) {
            return [null, 'คุณใช้โค้ดนี้ไปแล้ว (ใช้ได้คนละ 1 ครั้ง)', 0.0];
        }
        $lv = tree_info($user['id'])['level'];
        if ((int)$c['min_level'] > $lv) {
            $names = TREE_LEVELS;
            return [null, 'โค้ดนี้สำหรับสมาชิกที่ต้นไม้โตถึงระดับ "' . $names[(int)$c['min_level']][1] . '" ขึ้นไป', 0.0];
        }
    } elseif ((int)$c['min_level'] > 0 || (int)$c['once_per_user']) {
        return [null, 'โค้ดนี้ใช้ได้เฉพาะสมาชิกที่เข้าสู่ระบบ', 0.0];
    }
    $off = $c['kind'] === 'percent' ? $subtotal * (float)$c['value'] / 100 : (float)$c['value'];
    if ($c['kind'] === 'percent' && (float)$c['max_discount'] > 0) {
        $off = min($off, (float)$c['max_discount']);
    }
    $off = money(min($off, $subtotal));
    return [$c, 'ใช้โค้ด ' . $code . ' ลด ' . baht2($off), $off];
}

/** coupon_label() — ข้อความสั้นอธิบายโค้ด เช่น "ลด 10% (สูงสุด ฿100)" */
function coupon_label($c) {
    $t = $c['kind'] === 'percent' ? 'ลด ' . rtrim(rtrim(number_format((float)$c['value'], 2), '0'), '.') . '%' : 'ลด ' . baht2($c['value']);
    if ($c['kind'] === 'percent' && (float)$c['max_discount'] > 0) {
        $t .= ' (สูงสุด ' . baht($c['max_discount']) . ')';
    }
    if ((float)$c['min_total'] > 0) {
        $t .= ' เมื่อซื้อครบ ' . baht($c['min_total']);
    }
    return $t;
}

/* ============================================================
 * 4) สรุปยอดทั้งหมด (ใช้ทั้งหน้าตะกร้าและตอนสั่งซื้อจริง)
 * ============================================================ */

/**
 * calc_order() — คืน array:
 *   subtotal (ราคาสินค้า) · discount · shipping · km · ship_note · can_ship
 *   vat_rate · vat_mode · vat (ภาษีที่อยู่ในยอด) · total (ยอดที่ต้องจ่ายจริง)
 * VAT คิดจาก (สินค้าหลังหักส่วนลด + ค่าส่ง):
 *   รวมในราคาแล้ว  → ยอดจ่าย = ฐาน, VAT = ฐาน × อัตรา ÷ (100+อัตรา)
 *   บวกเพิ่ม       → VAT = ฐาน × อัตรา ÷ 100, ยอดจ่าย = ฐาน + VAT
 */
function calc_order($subtotal, $discount, $lat, $lng) {
    $c        = shop_fee_config();
    $subtotal = money($subtotal);
    $discount = money(min(max(0, $discount), $subtotal));
    $goods    = money($subtotal - $discount);
    [$ship, $km, $note, $can] = shipping_quote($goods, $lat, $lng);
    $base = money($goods + $ship);
    $rate = $c['vat_rate'];
    if ($rate <= 0) {
        $vat = 0.0;
        $total = $base;
    } elseif ($c['vat_mode'] === 'excluded') {
        $vat = money($base * $rate / 100);
        $total = money($base + $vat);
    } else {
        $vat = money($base * $rate / (100 + $rate));
        $total = $base;
    }
    return [
        'subtotal' => $subtotal, 'discount' => $discount, 'goods' => $goods,
        'shipping' => $ship, 'km' => $km, 'ship_note' => $note, 'can_ship' => $can,
        'vat_rate' => $rate, 'vat_mode' => $c['vat_mode'], 'vat' => $vat, 'total' => $total,
    ];
}

/**
 * order_summary_html() — ตารางสรุปยอด (ใช้ซ้ำในตะกร้า / หน้าชำระเงิน / ติดตามพัสดุ)
 * $o ต้องมีคีย์เดียวกับ calc_order() (ออเดอร์ในฐานข้อมูลแปลงมาด้วย order_calc_from_row)
 */
function order_summary_html($o, $couponCode = '') {
    $h  = '<table class="sumtab">';
    $h .= '<tr><td>ราคาสินค้า</td><td>' . baht2($o['subtotal']) . '</td></tr>';
    if ($o['discount'] > 0) {
        $h .= '<tr class="disc"><td>ส่วนลด' . ($couponCode !== '' ? ' <span class="tag">' . e($couponCode) . '</span>' : '') . '</td><td>−' . baht2($o['discount']) . '</td></tr>';
    }
    $h .= '<tr><td>ค่าจัดส่ง' . ($o['km'] !== null ? ' <span class="mut">(~' . number_format($o['km'], 1) . ' กม.)</span>' : '') . '</td><td>' . ($o['shipping'] > 0 ? baht2($o['shipping']) : '<b class="free">ฟรี</b>') . '</td></tr>';
    if ($o['vat_rate'] > 0) {
        $rate = rtrim(rtrim(number_format($o['vat_rate'], 2), '0'), '.');
        if ($o['vat_mode'] === 'excluded') {
            $h .= '<tr><td>ภาษีมูลค่าเพิ่ม ' . $rate . '%</td><td>' . baht2($o['vat']) . '</td></tr>';
        } else {
            $h .= '<tr class="sub"><td>รวมภาษีมูลค่าเพิ่ม ' . $rate . '% แล้ว</td><td>' . baht2($o['vat']) . '</td></tr>';
        }
    }
    $h .= '<tr class="grand"><td><b>ยอดที่ต้องชำระ</b></td><td><b class="price">' . baht2($o['total']) . '</b></td></tr></table>';
    return $h;
}

/** order_calc_from_row() — แปลงแถวออเดอร์ในฐานข้อมูลให้อยู่ในรูปเดียวกับ calc_order() (ออเดอร์เก่าที่ไม่มีรายละเอียดจะมีแค่ยอดรวม) */
function order_calc_from_row($r) {
    $has = $r['subtotal'] !== null && $r['subtotal'] !== '';
    return [
        'subtotal' => $has ? (float)$r['subtotal'] : (float)$r['total'],
        'discount' => (float)($r['discount'] ?? 0), 'shipping' => (float)($r['shipping'] ?? 0),
        'km' => ($r['distance_km'] ?? null) !== null ? (float)$r['distance_km'] : null,
        'vat_rate' => (float)($r['vat_rate'] ?? 0), 'vat_mode' => ($r['vat_mode'] ?? 'included') === 'excluded' ? 'excluded' : 'included',
        'vat' => (float)($r['vat'] ?? 0), 'total' => (float)$r['total'],
    ];
}

/* ============================================================
 * 5) แต้มสะสม = ต้นไม้ที่โตขึ้น (แต้มน้อยโดยตั้งใจ และมีเพดาน)
 *   ทุกยอดซื้อที่ชำระแล้ว 500 บาท = 1 แต้ม · สะสมสูงสุด 15 แต้ม (ต้นไม้โตเต็มที่แล้ว ไม่เพิ่มต่อ)
 *   แต้มคำนวณจากออเดอร์จริง ไม่เก็บตัวเลขแยก จึงไม่มีทางเพี้ยนหรือถูกแก้ตัวเลขเอง
 * ============================================================ */

const TREE_BAHT_PER_POINT = 500;
const TREE_MAX_POINTS     = 15;
/** ระดับต้นไม้: [แต้มขั้นต่ำ, ชื่อ, คำอธิบาย] */
const TREE_LEVELS = [
    0 => [0,  'เมล็ดพันธุ์', 'เริ่มปลูกด้วยการสั่งซื้อครั้งแรก'],
    1 => [1,  'ต้นกล้า',     'งอกแล้ว! ซื้อต่อเพื่อให้โตขึ้น'],
    2 => [3,  'ต้นอ่อน',     'ใบเริ่มแตกเป็นพุ่ม'],
    3 => [6,  'ต้นกลาง',     'ลำต้นแข็งแรง มีร่มเงา'],
    4 => [10, 'ต้นใหญ่',     'ใบหนาเต็มพุ่ม เริ่มออกดอก'],
    5 => [15, 'ต้นไม้ใหญ่',  'โตเต็มที่แล้ว ออกดอกออกผล 🎉'],
];

/** tree_info() — ระดับต้นไม้ของสมาชิก: points, level, name, ถัดไปต้องใช้อีกกี่บาท */
function tree_info($userId) {
    $spent = 0.0;
    try {
        $spent = (float)q('SELECT COALESCE(SUM(COALESCE(subtotal,total) - COALESCE(discount,0)),0) FROM orders WHERE user_id=? AND status IN (' . PAID_SQL . ')', [$userId])->fetchColumn();
    } catch (Throwable $err) {
        try {   // ฐานข้อมูลยังไม่ได้อัปเดต v7: ใช้ยอดรวมแทน
            $spent = (float)q('SELECT COALESCE(SUM(total),0) FROM orders WHERE user_id=? AND status IN (' . PAID_SQL . ')', [$userId])->fetchColumn();
        } catch (Throwable $err2) {
            $spent = 0.0;
        }
    }
    $pts   = min(TREE_MAX_POINTS, (int)floor($spent / TREE_BAHT_PER_POINT));
    $level = 0;
    foreach (TREE_LEVELS as $lv => $def) {
        if ($pts >= $def[0]) {
            $level = $lv;
        }
    }
    $next = TREE_LEVELS[$level + 1] ?? null;
    return [
        'points' => $pts, 'level' => $level, 'name' => TREE_LEVELS[$level][1], 'desc' => TREE_LEVELS[$level][2],
        'next_name' => $next ? $next[1] : null,
        'next_points' => $next ? $next[0] : null,
        'to_next_baht' => $next ? max(0, (int)ceil($next[0] * TREE_BAHT_PER_POINT - $spent)) : 0,
        'progress' => $next ? (int)round(100 * ($pts - TREE_LEVELS[$level][0]) / max(1, $next[0] - TREE_LEVELS[$level][0])) : 100,
        'max' => $pts >= TREE_MAX_POINTS,
    ];
}

/**
 * tree_3d() — ต้นไม้ 3 มิติ (CSS 3D ล้วน ไม่ต้องโหลดไลบรารีจากเว็บอื่น) หมุนช้าๆ โตตามระดับ 0-5
 * ระดับสูง = ลำต้นสูงขึ้น พุ่มใหญ่ขึ้น พุ่มซ้อนหลายชั้น มีดอกและผล · ผู้ที่ตั้งค่าลดการเคลื่อนไหวไว้จะไม่หมุน
 */
function tree_3d($level) {
    $level = max(0, min(5, (int)$level));
    $h = '<div class="tree3d lv' . $level . '" role="img" aria-label="ต้นไม้ระดับ ' . $level . ' จาก 5"><div class="t3-stage"><div class="t3-spin">';
    $h .= '<div class="t3-ground"></div><div class="t3-pot"><i></i><i></i><i></i><i></i></div>';
    if ($level === 0) {
        $h .= '<div class="t3-seed"></div>';
    } else {
        // ลำต้น = 2 แผ่นตัดกัน (มองเป็นทรงกระบอกเมื่อหมุน)
        $h .= '<div class="t3-trunk"><b></b><b></b></div>';
        // พุ่มใบ = วงกลมไล่สีตัดกัน 3 มุม ซ้อนเป็นชั้น (ยิ่งระดับสูงยิ่งมีชั้นมาก)
        $layers = [1 => 1, 2 => 1, 3 => 2, 4 => 3, 5 => 3][$level];
        for ($i = 0; $i < $layers; $i++) {
            $h .= '<div class="t3-crown c' . $i . '"><b></b><b></b><b></b></div>';
        }
        if ($level >= 4) {
            $h .= '<div class="t3-fruit f1"></div><div class="t3-fruit f2"></div><div class="t3-fruit f3"></div>';
        }
        if ($level >= 5) {
            $h .= '<div class="t3-fruit f4"></div><div class="t3-fruit f5"></div><div class="t3-fruit f6"></div>';
        }
    }
    return $h . '</div></div></div>';
}
