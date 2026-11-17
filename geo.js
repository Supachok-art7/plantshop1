/**
 * geo.js — ปักหมุดอัตโนมัติจากที่อยู่ + ปรับเองได้
 * ใช้ในหน้าสั่งซื้อ (cart.php) และหน้าที่อยู่ในโปรไฟล์ (account.php)
 *
 * วิธีทำงาน
 *   • ผู้ใช้พิมพ์ที่อยู่ → รอ 1.2 วินาทีหลังหยุดพิมพ์ → ค้นหาพิกัดด้วย Nominatim (บริการของ OpenStreetMap) → วางหมุดให้เอง
 *   • ถ้าหาไม่เจอ จะลองใหม่โดยตัดรายละเอียดหน้าบ้านออกทีละคำ (เช่นเหลือแค่ ตำบล อำเภอ จังหวัด) แล้วแจ้งว่า "ปักโดยประมาณ"
 *   • ผู้ใช้ปรับเองได้เสมอ: ลากหมุด หรือแตะแผนที่ → ระบบปิดโหมดอัตโนมัติให้ (ไม่ทับตำแหน่งที่ปักเอง)
 *     ติ๊ก "ปักหมุดอัตโนมัติ" อีกครั้งหรือกด "หาพิกัดจากที่อยู่" เพื่อให้ระบบหาใหม่
 *   • ที่อยู่ที่พิมพ์จะถูกส่งไปให้ Nominatim ค้นหา (ระบุไว้ในนโยบายความเป็นส่วนตัวแล้ว) ปิดได้โดยเอาติ๊กออก
 *
 * วิธีเรียกใช้: var pin = AddressPin.init({ map, latEl, lngEl, statusEl, autoEl, findBtn, fields:[ช่องที่อยู่...],
 *                                          getAddress: function(){ return 'ข้อความที่อยู่'; }, initial:[lat,lng]|null, runNow:true|false });
 *   pin.manual(lat, lng, ข้อความ) = ปักเอง (ปิดโหมดอัตโนมัติ)    pin.clear() = ล้างหมุด
 */
var AddressPin = (function () {
  var SEARCH_URL = 'https://nominatim.openstreetmap.org/search';

  function init(cfg) {
    var map = cfg.map;
    var marker = null;       // หมุดบนแผนที่ (ลากได้)
    var timer = null;        // ตัวหน่วงเวลาหลังหยุดพิมพ์
    var ticket = 0;          // เลขรอบการค้นหา ใช้ทิ้งผลของรอบเก่าที่ช้ากว่า
    var lastQuery = '';      // ที่อยู่ที่ค้นหาล่าสุด กันค้นซ้ำ

    function say(text) { if (cfg.statusEl) { cfg.statusEl.textContent = window.__T ? window.__T(text) : text; } }   // __T = ตัวแปลภาษา (มีเมื่อเลือก English)

    // วางหมุด + เก็บพิกัดลงช่องซ่อนที่จะถูกส่งไปกับฟอร์ม
    function place(lat, lng, zoom) {
      cfg.latEl.value = lat.toFixed(6);
      cfg.lngEl.value = lng.toFixed(6);
      if (marker) {
        marker.setLatLng([lat, lng]);
      } else {
        marker = L.marker([lat, lng], {draggable: true}).addTo(map);
        marker.on('dragend', function () {           // ลากหมุด = ปักเอง
          var p = marker.getLatLng();
          cfg.latEl.value = p.lat.toFixed(6);
          cfg.lngEl.value = p.lng.toFixed(6);
          goManual('ย้ายหมุดเองแล้ว');
        });
      }
      if (zoom) { map.setView([lat, lng], zoom); }
    }

    // สลับเป็นโหมดปักเอง (ปิดอัตโนมัติ เพื่อไม่ให้ระบบมาทับตำแหน่งที่ผู้ใช้เลือก)
    function goManual(msg) {
      if (cfg.autoEl) { cfg.autoEl.checked = false; }
      ticket++;                                       // ยกเลิกการค้นหาที่ค้างอยู่
      say('✋ ' + msg + ' · ติ๊ก "ปักหมุดอัตโนมัติ" เพื่อให้ระบบหาตามที่อยู่ใหม่');
    }

    function manual(lat, lng, msg) {
      place(lat, lng, 16);
      goManual(msg || 'ปักหมุดเองแล้ว');
    }

    function search(query) {
      var url = SEARCH_URL + '?format=jsonv2&limit=1&countrycodes=th&accept-language=th&q=' + encodeURIComponent(query);
      return fetch(url, {headers: {'Accept': 'application/json'}}).then(function (r) { return r.json(); });
    }

    // ค้นหาพิกัดจากที่อยู่ทั้งหมด ถ้าไม่เจอตัดคำหน้าสุดออกแล้วลองใหม่ (ไม่เกิน 4 ครั้ง เว้น 1.1 วินาที ตามกติกาของ Nominatim)
    function run() {
      var full = (cfg.getAddress() || '').replace(/\s+/g, ' ').trim();
      if (full.length < 6 || full === lastQuery) { return; }
      lastQuery = full;

      var words = full.split(' ');
      var attempts = [];
      for (var i = 0; i < words.length && attempts.length < 4 && words.length - i >= 2; i++) {
        attempts.push(words.slice(i).join(' '));
      }
      if (!attempts.length) { attempts.push(full); }

      var mine = ++ticket;
      say('🔎 กำลังหาพิกัดจากที่อยู่...');
      (function next(k) {
        if (mine !== ticket) { return; }
        search(attempts[k]).then(function (res) {
          if (mine !== ticket) { return; }
          if (res && res.length) {
            place(parseFloat(res[0].lat), parseFloat(res[0].lon), k === 0 ? 17 : 14);
            say(k === 0 ? '📍 ปักหมุดให้อัตโนมัติแล้ว · ลากหมุดหรือแตะแผนที่เพื่อปรับเองได้'
                        : '📍 ปักหมุดโดยประมาณ (ระดับพื้นที่) · ลากหมุดให้ตรงบ้านได้');
          } else if (k + 1 < attempts.length) {
            setTimeout(function () { next(k + 1); }, 1100);
          } else {
            say('หาพิกัดไม่เจอ ลองเพิ่มตำบล/อำเภอ/จังหวัด หรือแตะแผนที่เพื่อปักเอง');
          }
        }).catch(function () {
          if (mine === ticket) { say('ค้นหาพิกัดอัตโนมัติไม่ได้ตอนนี้ แตะแผนที่เพื่อปักเอง'); }
        });
      })(0);
    }

    function schedule() {
      clearTimeout(timer);
      timer = setTimeout(function () { if (!cfg.autoEl || cfg.autoEl.checked) { run(); } }, 1200);
    }

    // ----- เชื่อมเหตุการณ์ -----
    (cfg.fields || []).forEach(function (f) { if (f) { f.addEventListener('input', schedule); } });
    map.on('click', function (e) { manual(e.latlng.lat, e.latlng.lng, 'ปักหมุดเองแล้ว'); });
    if (cfg.autoEl) {
      cfg.autoEl.addEventListener('change', function () {
        if (cfg.autoEl.checked) { lastQuery = ''; run(); } else { ticket++; say('✋ ปิดการปักอัตโนมัติ · แตะแผนที่เพื่อปักเอง'); }
      });
    }
    if (cfg.findBtn) {
      cfg.findBtn.addEventListener('click', function () {
        if (cfg.autoEl) { cfg.autoEl.checked = true; }
        lastQuery = '';
        run();
      });
    }

    // ----- เริ่มต้น -----
    if (cfg.initial) {
      place(cfg.initial[0], cfg.initial[1], 15);
      say('มีหมุดที่บันทึกไว้แล้ว · ติ๊ก "ปักหมุดอัตโนมัติ" หรือกด "หาพิกัดจากที่อยู่" เพื่อให้ระบบหาใหม่');
    } else if (cfg.runNow && cfg.autoEl && cfg.autoEl.checked) {
      run();
    } else {
      say('พิมพ์ที่อยู่แล้วระบบจะปักหมุดให้เอง · ลากหมุดหรือแตะแผนที่เพื่อปรับเองได้');
    }

    return {
      manual: manual,
      clear: function () {
        ticket++;
        if (marker) { map.removeLayer(marker); marker = null; }
        cfg.latEl.value = '';
        cfg.lngEl.value = '';
        lastQuery = '';
        say('ล้างหมุดแล้ว');
      }
    };
  }

  return {init: init};
})();
