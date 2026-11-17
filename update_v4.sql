-- update_v4.sql — อัปเดตฐานข้อมูลจากรุ่น v3 โดยไม่ลบข้อมูลเดิม   ** รันครั้งเดียวพอ **
-- เพิ่ม: ระบบแจ้งรายงานถึงแอดมิน (reports) / ข้อมูลติดต่อร้านสำหรับหน้าติดต่อและท้ายเว็บ
SET NAMES utf8mb4;
USE `plantshop`;

CREATE TABLE IF NOT EXISTS reports(            -- เรื่องที่สมาชิกแจ้งถึงแอดมิน
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
  type VARCHAR(20) NOT NULL,                   -- order / product / payment / review / service / website / other
  target_id INT NULL,                          -- เลขออเดอร์ สินค้า หรือรีวิวที่เกี่ยวข้อง (ถ้ามี)
  subject VARCHAR(150) NOT NULL, body TEXT NOT NULL, img VARCHAR(150) NULL,   -- img = รูปประกอบ (ไฟล์ใน uploads/)
  status VARCHAR(12) NOT NULL DEFAULT 'new',   -- new / working / resolved / rejected
  admin_reply TEXT NULL,                       -- ข้อความตอบกลับจากแอดมิน
  user_seen TINYINT NOT NULL DEFAULT 1,        -- 0 = แอดมินตอบแล้วแต่สมาชิกยังไม่เปิดอ่าน
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated TIMESTAMP NULL,
  KEY(user_id), KEY(status));

-- ข้อมูลติดต่อร้าน (ข้อมูลทดสอบ ให้เปลี่ยนเป็นของร้านจริงในหลังบ้าน แท็บ "ข้อมูลร้าน")
INSERT INTO settings(k,v) VALUES
('shop_phone','081-234-5678'),('shop_email','hello@shop.test'),('shop_line','@suansuk'),
('shop_addr','99 ถนนสุขุมวิท แขวงคลองเตย เขตคลองเตย กรุงเทพฯ 10110'),
('shop_hours','ทุกวัน 09:00 - 18:00 น.')
ON DUPLICATE KEY UPDATE v=v;
