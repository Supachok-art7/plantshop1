<?php
/**
 * contact.php — ติดต่อเรา: เบอร์ อีเมล LINE ที่อยู่ เวลาทำการ แผนที่ร้าน และทางลัดไปแชท / แจ้งรายงาน
 * ข้อมูลมาจากตาราง settings (แอดมินแก้ได้ที่หลังบ้าน แท็บ "ข้อมูลร้าน")
 */
require __DIR__ . '/includes/config.php';
$shop = shop_info();

head('ติดต่อเรา', 'ช่องทางติดต่อร้านสวนสุข โทรศัพท์ อีเมล LINE ที่อยู่ร้าน เวลาทำการ และแผนที่');
?>
<p class="crumb"><a href="index.php">หน้าแรก</a> › ติดต่อเรา</p>
<section class="pg-hero">
  <span class="e">📞</span>
  <h1>ติดต่อเรา</h1>
  <p>มีคำถามเรื่องสินค้า การสั่งซื้อ หรือวิธีดูแลต้นไม้ ทักมาได้เลย</p>
</section>

<div class="cols2">
  <div>
    <div class="card cinfo"><span class="em2">📞</span><div><b>โทรศัพท์</b><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $shop['shop_phone'])) ?>"><?= e($shop['shop_phone']) ?></a></div></div>
    <div class="card cinfo"><span class="em2">✉️</span><div><b>อีเมล</b><a href="mailto:<?= e($shop['shop_email']) ?>"><?= e($shop['shop_email']) ?></a></div></div>
    <div class="card cinfo"><span class="em2">💬</span><div><b>LINE</b><?= e($shop['shop_line']) ?></div></div>
    <div class="card cinfo"><span class="em2">🕘</span><div><b>เวลาทำการ</b><?= e($shop['shop_hours']) ?></div></div>
    <div class="card cinfo"><span class="em2">📍</span><div><b>ที่อยู่ร้าน</b><?= nl2br(e($shop['shop_addr'])) ?></div></div>
  </div>
  <div>
    <div class="card sect">
      <h3>ช่องทางที่เร็วที่สุด</h3>
      <p class="sub">เลือกให้ตรงกับเรื่องที่ต้องการ</p>
      <p><a class="btn" href="chat.php">💬 แชทกับร้านตอนนี้</a></p>
      <p><a class="btn ghost" href="report.php">🚩 แจ้งปัญหา / รายงานถึงแอดมิน</a></p>
      <p><a class="btn ghost" href="account.php?t=orders">📦 ติดตามออเดอร์ของฉัน</a></p>
      <p class="mut">ดู <a href="faq.php">คำถามที่พบบ่อย</a> ก่อน อาจมีคำตอบที่ต้องการอยู่แล้ว</p>
    </div>
    <div class="card">
      <b>🗺 แผนที่ร้าน</b>
      <div id="shopmap" style="height:260px;border-radius:12px;margin-top:8px"></div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<script>
var pos = <?= json_encode([(float)$shop['shop_lat'], (float)$shop['shop_lng']]) ?>;
var map = L.map('shopmap').setView(pos, 15);
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '© OpenStreetMap'}).addTo(map);
var pop = document.createElement('div');
pop.textContent = 'ร้านสวนสุข';
L.marker(pos).addTo(map).bindPopup(pop);
</script>
<?php foot();
