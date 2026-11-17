<?php
/**
 * pdpa_admin.php — ส่วน PDPA ของหลังบ้าน (admin.php เรียกใช้ แท็บ "🔏 PDPA")
 *   ส่วน A: ฟังก์ชันรับคำสั่ง do_xxx()   ส่วน B: หน้าแท็บ tab_pdpa()
 * ในแท็บมี: สรุปความยินยอม · คำขอใช้สิทธิ์ (DSR, ครบกำหนด 30 วัน) · ทะเบียนเหตุละเมิดข้อมูล (แจ้ง สคส. ใน 72 ชม.)
 *           ตั้งค่าเวอร์ชันนโยบาย/ระยะเวลาเก็บรักษา · ล้างข้อมูลหมดอายุ · ทะเบียนกิจกรรมประมวลผล (ROPA) · บันทึกการเข้าถึง
 */

/** dsr_open_count() — จำนวนคำขอใช้สิทธิ์ใหม่ที่รอดำเนินการ (แสดงเป็นป้ายเลขแดงที่แท็บ) */
function dsr_open_count() {
    try {
        return (int)q("SELECT COUNT(*) FROM dsr_requests WHERE status='new'")->fetchColumn();
    } catch (Throwable $err) {
        return 0;   // ยังไม่ได้รัน update_v6.sql
    }
}


/* ============================================================
 * ส่วน A: รับคำสั่ง POST
 * ============================================================ */

/** do_dsr() — อัปเดตสถานะคำขอ + ข้อความตอบกลับ (ปฏิเสธต้องระบุเหตุผล ตามหลัก PDPA) */
function do_dsr($id) {
    $status = $_POST['status'] ?? '';
    $note   = trim($_POST['note'] ?? '');
    if (!isset(DSR_STATUS[$status])) {
        return;
    }
    if ($status === 'rejected' && $note === '') {
        flash('การปฏิเสธคำขอต้องระบุเหตุผล');
        return;
    }
    q('UPDATE dsr_requests SET status=?, admin_note=?, updated=NOW() WHERE id=?', [$status, $note !== '' ? $note : null, $id]);
    audit('dsr_update', '#' . $id . ' ' . $status);
    flash('อัปเดตคำขอแล้ว');
}

/** do_dsr_erase() — ดำเนินการลบข้อมูลตามคำขอ "ขอลบ/ทำลายข้อมูล" (ไม่ได้ถ้าลูกค้ายังมีออเดอร์ค้าง) */
function do_dsr_erase($id) {
    $r = q("SELECT * FROM dsr_requests WHERE id=? AND type='erase' AND status IN ('new','working')", [$id])->fetch();
    if (!$r) {
        return;
    }
    if (user_has_active_orders($r['user_id'])) {
        flash('ลบไม่ได้ในขณะนี้: ลูกค้ายังมีออเดอร์ที่ไม่เสร็จสิ้น (ตอบกลับพร้อมเหตุผลและเลื่อนดำเนินการได้)');
        return;
    }
    if (anonymize_user($r['user_id'])) {
        q('UPDATE dsr_requests SET status=?, admin_note=?, updated=NOW() WHERE id=?',
          ['done', 'ลบข้อมูลส่วนบุคคลและทำให้ไม่ระบุตัวตนแล้ว (เก็บข้อมูลออเดอร์ตามหน้าที่ทางกฎหมายบัญชี/ภาษี)', $id]);
        audit('dsr_erase', 'user#' . $r['user_id']);
        flash('ลบข้อมูลตามคำขอแล้ว');
    } else {
        flash('ลบไม่สำเร็จ (อาจเป็นบัญชีแอดมิน)');
    }
}

/** do_breach() — บันทึกเหตุละเมิดข้อมูลใหม่ (นับ 72 ชั่วโมงจากเวลาที่รับรู้เหตุ) */
function do_breach() {
    $desc = trim($_POST['description'] ?? '');
    $ts   = strtotime(str_replace('T', ' ', $_POST['discovered_at'] ?? ''));
    if ($desc === '') {
        flash('กรุณาอธิบายเหตุที่เกิดขึ้น');
        return;
    }
    q('INSERT INTO breach_log(discovered_at,description,affected) VALUES(?,?,?)',
      [date('Y-m-d H:i:s', $ts ?: time()), mb_substr($desc, 0, 2000), max(0, (int)($_POST['affected'] ?? 0))]);
    audit('breach_add', '');
    flash('บันทึกเหตุแล้ว ต้องแจ้ง สคส. ภายใน 72 ชั่วโมง หากมีความเสี่ยงต่อสิทธิเสรีภาพของเจ้าของข้อมูล');
}

/** do_breach_update() — บันทึกว่าแจ้ง สคส. แล้ว / แจ้งผู้ได้รับผลกระทบแล้ว / ปิดเรื่อง */
function do_breach_update($id) {
    switch ($_POST['act'] ?? '') {
        case 'pdpc':  q('UPDATE breach_log SET notified_pdpc=NOW() WHERE id=?', [$id]); break;
        case 'users': q('UPDATE breach_log SET notified_users=1 WHERE id=?', [$id]); break;
        case 'close': q("UPDATE breach_log SET status='closed' WHERE id=?", [$id]); break;
        default: return;
    }
    audit('breach_update', '#' . $id . ' ' . ($_POST['act'] ?? ''));
    flash('อัปเดตทะเบียนเหตุละเมิดแล้ว');
}

/** do_pdpa_settings() — ตั้งเวอร์ชันนโยบาย ผู้ติดต่อด้านข้อมูลส่วนบุคคล และระยะเวลาเก็บรักษา */
function do_pdpa_settings() {
    $ver = trim($_POST['pdpa_version'] ?? '');
    if ($ver !== '' && mb_strlen($ver) <= 20) {
        save_setting('pdpa_version', $ver);   // เปลี่ยนเลข = สมาชิกทุกคนต้องรับทราบนโยบายใหม่อีกครั้ง
    }
    save_setting('pdpa_dpo', mb_substr(trim($_POST['pdpa_dpo'] ?? ''), 0, 150));
    save_setting('ret_chat_days', (string)min(3650, max(30, (int)($_POST['ret_chat_days'] ?? 365))));
    save_setting('ret_order_years', (string)min(15, max(1, (int)($_POST['ret_order_years'] ?? 5))));
    audit('pdpa_settings', 'v' . $ver);
    flash('บันทึกการตั้งค่า PDPA แล้ว');
}

/**
 * do_retention_run() — ล้างข้อมูลที่เกินระยะเวลาเก็บรักษา
 *   แชทเก่ากว่ากำหนด → ลบ   ·   ออเดอร์ที่จบแล้วและเก่ากว่ากำหนด → ลบชื่อ/เบอร์/ที่อยู่/พิกัด/สลิป (เก็บยอดไว้ทำบัญชี)
 */
function do_retention_run() {
    $days  = (int)setting('ret_chat_days', '365');
    $years = (int)setting('ret_order_years', '5');
    $chats = q('DELETE FROM messages WHERE created < (NOW() - INTERVAL ' . $days . ' DAY)')->rowCount();

    $old = q("SELECT id, slip FROM orders WHERE created < (NOW() - INTERVAL " . $years . " YEAR)
              AND status IN (" . FINAL_STATUS_SQL . ") AND name <> '(ลบตามระยะเวลาเก็บรักษา)'")->fetchAll();
    foreach ($old as $o) {
        if ($o['slip']) {
            @unlink(ROOT_DIR . '/uploads/' . basename($o['slip']));
        }
        q("UPDATE orders SET name='(ลบตามระยะเวลาเก็บรักษา)', phone=NULL, addr='-', lat=NULL, lng=NULL, slip=NULL WHERE id=?", [$o['id']]);
    }
    audit('retention_run', 'chat=' . $chats . ' orders=' . count($old));
    flash('ล้างข้อมูลหมดอายุแล้ว: แชท ' . $chats . ' ข้อความ · ออเดอร์เก่า ' . count($old) . ' รายการ');
}


/* ============================================================
 * ส่วน B: หน้าแท็บ PDPA
 * ============================================================ */

/** tab_pdpa() — แสดงทุกส่วนของแท็บ PDPA */
function tab_pdpa() {
    $ver      = pdpa_version();
    $where    = "role='customer' AND deleted_at IS NULL";
    $total    = (int)q("SELECT COUNT(*) FROM users WHERE $where")->fetchColumn();
    $accepted = (int)q("SELECT COUNT(*) FROM users WHERE $where AND pdpa_version=?", [$ver])->fetchColumn();
    $market   = (int)q("SELECT COUNT(*) FROM users WHERE $where AND marketing_opt_in=1")->fetchColumn();

    $open   = q("SELECT d.*, u.name AS uname, u.email AS uemail FROM dsr_requests d LEFT JOIN users u ON u.id=d.user_id
                 WHERE d.status IN ('new','working') ORDER BY d.id")->fetchAll();
    $closed = q("SELECT d.*, u.name AS uname FROM dsr_requests d LEFT JOIN users u ON u.id=d.user_id
                 WHERE d.status IN ('done','rejected') AND d.admin_note NOT LIKE 'ระบบ%' ORDER BY d.id DESC LIMIT 15")->fetchAll();
    $breaches = q('SELECT * FROM breach_log ORDER BY id DESC')->fetchAll();
    $audit    = q('SELECT a.*, u.name FROM audit_log a LEFT JOIN users u ON u.id=a.admin_id ORDER BY a.id DESC LIMIT 40')->fetchAll();
    $chatDays = (int)setting('ret_chat_days', '365');
    $ordYears = (int)setting('ret_order_years', '5');

    // ทะเบียนกิจกรรมการประมวลผล (ROPA): [กิจกรรม, ข้อมูล, ฐานทางกฎหมาย, ระยะเวลาเก็บ, ผู้รับข้อมูล]
    $ropa = [
        ['สมัครสมาชิก/บัญชี', 'ชื่อ อีเมล เบอร์โทร รหัสผ่าน(เข้ารหัส) ข้อมูลโปรไฟล์', 'สัญญา', 'จนกว่าสมาชิกลบบัญชี', '—'],
        ['สั่งซื้อและจัดส่ง', 'ชื่อ เบอร์ ที่อยู่ พิกัดจัดส่ง รายการสินค้า', 'สัญญา / หน้าที่ตามกฎหมายบัญชีภาษี', $ordYears . ' ปี แล้วทำให้ไม่ระบุตัวตน', 'บริษัทขนส่ง'],
        ['ชำระเงิน', 'ยอดเงิน ช่องทาง สลิปโอนเงิน', 'สัญญา / หน้าที่ตามกฎหมาย', 'เท่ากับออเดอร์', 'ธนาคาร/PromptPay'],
        ['ยืนยันตัวตน (ไม่บังคับ)', 'รูปบัตรประชาชน รูปเซลฟี่', 'ความยินยอม', 'ลบทันทีหลังตรวจ', '— (เฉพาะแอดมิน)'],
        ['แชทและแจ้งรายงาน', 'ข้อความ รูปประกอบ', 'สัญญา / ประโยชน์โดยชอบด้วยกฎหมาย', 'แชท ' . $chatDays . ' วัน', '—'],
        ['รีวิวสินค้า', 'คะแนน ข้อความ รูป ชื่อต้น', 'ความยินยอม', 'จนกว่าสมาชิกขอลบ', 'สาธารณะ (ผู้เข้าชมเว็บ)'],
        ['ข่าวสารโปรโมชั่น', 'ชื่อ ช่องทางติดต่อ', 'ความยินยอม', 'จนกว่าถอนความยินยอม', '—'],
        ['ความปลอดภัยและบันทึกการเข้าถึง', 'บันทึกการเปิดดูข้อมูลสำคัญของแอดมิน', 'ประโยชน์โดยชอบด้วยกฎหมาย', 'ตามความจำเป็น', '—'],
    ];
    ?>
    <p class="mut">เครื่องมือช่วยบริหารตาม พ.ร.บ.คุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562 — ข้อความนโยบายและตารางด้านล่างเป็นแม่แบบ เจ้าของร้านควรปรับให้ตรงกับการดำเนินงานจริงและปรึกษาผู้เชี่ยวชาญด้านกฎหมาย</p>

    <div class="stats">
      <div class="card">สมาชิกที่รับทราบนโยบาย v<?= e($ver) ?><b><?= $accepted ?> / <?= $total ?></b></div>
      <div class="card">ยินยอมรับข่าวสาร<b><?= $market ?></b></div>
      <div class="card">คำขอที่รอดำเนินการ<b><?= count($open) ?></b></div>
    </div>

    <!-- ===== คำขอใช้สิทธิ์ ===== -->
    <h2>📨 คำขอใช้สิทธิ์ของเจ้าของข้อมูล</h2>
    <p class="mut">ต้องตอบภายใน <?= DSR_DAYS ?> วันนับจากวันที่ยื่น</p>
    <?php foreach ($open as $r):
        $due     = dsr_due($r['created']);
        $overdue = $due < date('Y-m-d'); ?>
      <div class="card">
        <b><?= e(DSR_TYPES[$r['type']] ?? $r['type']) ?></b>
        <span class="tag"><?= e(DSR_STATUS[$r['status']]) ?></span>
        <span class="mut"><?= e($r['uname'] ?? 'ผู้ใช้ที่ลบแล้ว') ?> · <?= e($r['uemail'] ?? '') ?> · ยื่นเมื่อ <?= e($r['created']) ?></span>
        <span class="tag <?= $overdue ? 'bad' : '' ?>">ครบกำหนด <?= e($due) ?><?= $overdue ? ' (เลยกำหนด!)' : '' ?></span>
        <p><?= nl2br(e($r['detail'])) ?></p>
        <form method="post" action="admin.php?t=pdpa" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          <?= fields('dsr', $r['id']) ?>
          <select name="status" style="width:auto"><?php foreach (DSR_STATUS as $k => $label): ?><option value="<?= $k ?>"<?= $k === $r['status'] ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
          <input name="note" value="<?= e($r['admin_note']) ?>" placeholder="ข้อความตอบกลับ (ปฏิเสธต้องใส่เหตุผล)" style="flex:1;min-width:200px">
          <button class="sm">บันทึก</button>
        </form>
        <?php if ($r['type'] === 'erase'): ?>
          <form method="post" action="admin.php?t=pdpa" onsubmit="return confirm('ลบข้อมูลส่วนบุคคลของสมาชิกรายนี้? ย้อนกลับไม่ได้')" style="margin-top:6px">
            <?= fields('dsr_erase', $r['id']) ?><button class="del sm">🗑 ดำเนินการลบข้อมูลตามคำขอ</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$open): ?><p class="mut">ไม่มีคำขอค้าง</p><?php endif; ?>
    <?php if ($closed): ?>
      <details class="faq"><summary>คำขอที่ดำเนินการแล้ว (ล่าสุด <?= count($closed) ?> รายการ)</summary>
        <?php foreach ($closed as $r): ?>
          <p><b><?= e(DSR_TYPES[$r['type']] ?? $r['type']) ?></b> · <?= e($r['uname'] ?? 'ผู้ใช้ที่ลบแล้ว') ?> · <?= e(DSR_STATUS[$r['status']]) ?> · <span class="mut"><?= e($r['created']) ?></span></p>
        <?php endforeach; ?>
      </details>
    <?php endif; ?>

    <!-- ===== เหตุละเมิดข้อมูล ===== -->
    <h2>🚨 ทะเบียนเหตุละเมิดข้อมูลส่วนบุคคล</h2>
    <p class="mut">เมื่อรับรู้เหตุที่อาจกระทบสิทธิเสรีภาพของเจ้าของข้อมูล ต้องแจ้งสำนักงานคณะกรรมการคุ้มครองข้อมูลส่วนบุคคล (สคส.) ภายใน <b>72 ชั่วโมง</b> และแจ้งผู้ได้รับผลกระทบหากมีความเสี่ยงสูง</p>
    <form method="post" action="admin.php?t=pdpa" class="card">
      <?= fields('breach') ?>
      <div class="form">
        <input type="datetime-local" name="discovered_at" value="<?= date('Y-m-d\TH:i') ?>" required>
        <input type="number" name="affected" min="0" placeholder="จำนวนผู้ได้รับผลกระทบ (โดยประมาณ)">
        <textarea class="full" name="description" rows="3" placeholder="เกิดอะไรขึ้น ข้อมูลอะไรรั่วไหล ทราบเมื่อไร และแก้ไขอย่างไร" required></textarea>
      </div>
      <button>บันทึกเหตุ</button>
    </form>
    <?php foreach ($breaches as $b):
        $deadline = strtotime($b['discovered_at'] . ' +72 hours');
        $hoursLeft = (int)floor(($deadline - time()) / 3600); ?>
      <div class="card">
        <b>เหตุเมื่อ <?= e($b['discovered_at']) ?></b>
        <span class="tag"><?= $b['status'] === 'closed' ? 'ปิดเรื่อง' : 'เปิดอยู่' ?></span>
        <span class="mut">ผู้ได้รับผลกระทบ ~<?= (int)$b['affected'] ?> คน</span>
        <p><?= nl2br(e($b['description'])) ?></p>
        <?php if ($b['notified_pdpc']): ?>
          <span class="tag ok">✔ แจ้ง สคส. แล้ว <?= e($b['notified_pdpc']) ?></span>
        <?php else: ?>
          <span class="tag bad"><?= $hoursLeft >= 0 ? 'เหลือเวลาแจ้ง สคส. ~' . $hoursLeft . ' ชม. (ก่อน ' . date('Y-m-d H:i', $deadline) . ')' : 'เลยกำหนด 72 ชม. แล้ว!' ?></span>
        <?php endif; ?>
        <?= $b['notified_users'] ? '<span class="tag ok">✔ แจ้งผู้ได้รับผลกระทบแล้ว</span>' : '' ?>
        <?php if ($b['status'] !== 'closed'): ?>
          <form method="post" action="admin.php?t=pdpa" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">
            <?= fields('breach_up', $b['id']) ?>
            <?php if (!$b['notified_pdpc']): ?><button name="act" value="pdpc" class="sm">บันทึกว่าแจ้ง สคส. แล้ว</button><?php endif; ?>
            <?php if (!$b['notified_users']): ?><button name="act" value="users" class="ghost sm">แจ้งผู้ได้รับผลกระทบแล้ว</button><?php endif; ?>
            <button name="act" value="close" class="ghost sm">ปิดเรื่อง</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$breaches): ?><p class="mut">ยังไม่มีเหตุที่บันทึกไว้</p><?php endif; ?>

    <!-- ===== ตั้งค่า + ล้างข้อมูลหมดอายุ ===== -->
    <h2>⚙️ ตั้งค่า PDPA และระยะเวลาเก็บรักษา</h2>
    <form method="post" action="admin.php?t=pdpa" class="card">
      <?= fields('pdpa_set') ?>
      <div class="form">
        <label>เวอร์ชันนโยบาย (เปลี่ยนเลข = สมาชิกต้องรับทราบใหม่)<input name="pdpa_version" value="<?= e($ver) ?>" maxlength="20"></label>
        <label>ผู้ติดต่อด้านข้อมูลส่วนบุคคล (อีเมล/เบอร์)<input name="pdpa_dpo" value="<?= e(setting('pdpa_dpo', '')) ?>"></label>
        <label>เก็บแชทกี่วัน<input type="number" name="ret_chat_days" value="<?= $chatDays ?>" min="30" max="3650"></label>
        <label>เก็บข้อมูลออเดอร์กี่ปี (แล้วลบชื่อ/ที่อยู่)<input type="number" name="ret_order_years" value="<?= $ordYears ?>" min="1" max="15"></label>
      </div>
      <button>บันทึกการตั้งค่า</button>
    </form>
    <form method="post" action="admin.php?t=pdpa" class="card" onsubmit="return confirm('ลบแชทและข้อมูลส่วนตัวในออเดอร์ที่เกินระยะเวลาเก็บรักษา? ย้อนกลับไม่ได้')">
      <?= fields('ret_run') ?>
      <b>🧹 ล้างข้อมูลหมดอายุ</b>
      <p class="mut">ลบแชทที่เก่ากว่า <?= $chatDays ?> วัน และลบชื่อ/เบอร์/ที่อยู่/พิกัด/สลิปของออเดอร์ที่จบแล้วและเก่ากว่า <?= $ordYears ?> ปี (เก็บยอดเงินไว้ทำบัญชี)</p>
      <button class="del">ล้างข้อมูลหมดอายุ</button>
    </form>

    <!-- ===== ROPA ===== -->
    <h2>📋 ทะเบียนกิจกรรมการประมวลผล (ROPA)</h2>
    <div class="card scroll"><table>
      <tr><th>กิจกรรม</th><th>ข้อมูล</th><th>ฐานทางกฎหมาย</th><th>ระยะเวลาเก็บ</th><th>ผู้รับข้อมูล</th></tr>
      <?php foreach ($ropa as $row): ?>
        <tr><?php foreach ($row as $cell): ?><td><?= e($cell) ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
    </table></div>

    <!-- ===== audit ===== -->
    <h2>🧾 บันทึกการเข้าถึงข้อมูลของแอดมิน (40 รายการล่าสุด)</h2>
    <div class="card scroll"><table>
      <tr><th>เวลา</th><th>แอดมิน</th><th>การกระทำ</th><th>เป้าหมาย</th></tr>
      <?php foreach ($audit as $a): ?>
        <tr><td><?= e($a['created']) ?></td><td><?= e($a['name'] ?? '-') ?></td><td><?= e($a['action']) ?></td><td><?= e($a['target']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$audit): ?><tr><td colspan="4" class="mut">ยังไม่มีบันทึก</td></tr><?php endif; ?>
    </table></div>
    <?php
}
