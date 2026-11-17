/* app.js — สคริปต์เล็กๆ ที่ใช้ทุกหน้า (เมนูมือถือ, ปิดเมนูสมาชิก, ข้อความแจ้งเตือน) */
(function () {
  // เมนูหลักบนมือถือ: กดปุ่ม ☰ เพื่อเปิด/ปิด
  var burger = document.getElementById('burger');
  var nav = document.getElementById('mainnav');
  if (burger && nav) {
    burger.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // เมนูสมาชิก: กดที่อื่นหรือกด Esc แล้วปิด
  var um = document.querySelector('.umenu');
  if (um) {
    document.addEventListener('click', function (e) { if (!um.contains(e.target)) { um.removeAttribute('open'); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { um.removeAttribute('open'); } });
  }

  // ข้อความแจ้งเตือน (flash) หายเองใน 6 วินาที
  var flash = document.querySelector('.flash');
  if (flash) {
    setTimeout(function () { flash.style.transition = 'opacity .5s'; flash.style.opacity = '0'; }, 6000);
    setTimeout(function () { flash.style.display = 'none'; }, 6600);
  }
})();
