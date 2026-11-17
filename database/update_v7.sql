-- update_v7.sql — เพิ่ม ภาษีมูลค่าเพิ่ม (VAT) + ค่าส่งตามระยะทาง + โค้ดส่วนลด + แต้มต้นไม้   ** รันครั้งเดียวพอ **
-- ใช้ MariaDB (XAMPP) รันซ้ำได้ไม่พัง ถ้าใช้ MySQL ธรรมดาให้ลบคำว่า IF NOT EXISTS ในบรรทัด ALTER ออก
SET NAMES utf8mb4;
USE `plantshop`;

ALTER TABLE orders ADD COLUMN IF NOT EXISTS subtotal DECIMAL(10,2) NULL,
  ADD COLUMN IF NOT EXISTS discount DECIMAL(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS shipping DECIMAL(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS distance_km DECIMAL(7,1) NULL,
  ADD COLUMN IF NOT EXISTS vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS vat_mode VARCHAR(10) NOT NULL DEFAULT 'included',
  ADD COLUMN IF NOT EXISTS vat DECIMAL(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS coupon VARCHAR(20) NULL;

CREATE TABLE IF NOT EXISTS coupons(            -- โค้ดส่วนลด (แอดมินสร้างที่แท็บ "ภาษี/ค่าส่ง/โค้ด")
  id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(20) NOT NULL UNIQUE,
  kind VARCHAR(10) NOT NULL DEFAULT 'percent',   -- percent = ลดเป็น % / fixed = ลดเป็นบาท
  value DECIMAL(10,2) NOT NULL, max_discount DECIMAL(10,2) NOT NULL DEFAULT 0,   -- ลดสูงสุด (เฉพาะ %) 0 = ไม่จำกัด
  min_total DECIMAL(10,2) NOT NULL DEFAULT 0,    -- ยอดสินค้าขั้นต่ำ
  max_uses INT NOT NULL DEFAULT 0, used INT NOT NULL DEFAULT 0,   -- จำนวนสิทธิ์ทั้งหมด 0 = ไม่จำกัด / ใช้ไปแล้ว
  once_per_user TINYINT NOT NULL DEFAULT 1, min_level TINYINT NOT NULL DEFAULT 0,   -- คนละครั้ง / ต้นไม้ระดับขั้นต่ำ 0-5
  starts DATE NULL, expires DATE NULL, active TINYINT NOT NULL DEFAULT 1,
  note VARCHAR(120) NULL, created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);

CREATE TABLE IF NOT EXISTS coupon_uses(        -- ใครใช้โค้ดไหนกับออเดอร์ไหน
  id INT AUTO_INCREMENT PRIMARY KEY, coupon_id INT NOT NULL, user_id INT NOT NULL, order_id INT NOT NULL,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY(coupon_id), KEY(user_id));

INSERT INTO settings(k,v) VALUES
('vat_rate','7'),('vat_mode','included'),('ship_base','30'),('ship_free_km','5'),('ship_per_km','5'),
('ship_max','300'),('ship_max_km','0'),('ship_free_over','1500'),('ship_nopin','60')
ON DUPLICATE KEY UPDATE v=v;

INSERT INTO coupons(code,kind,value,max_discount,min_total,max_uses,once_per_user,min_level,note) VALUES
('WELCOME10','percent',10,100,300,0,1,0,'ตัวอย่าง: ลด 10% สูงสุด 100 บาท เมื่อซื้อครบ 300'),
('SAVE50','fixed',50,0,500,0,1,0,'ตัวอย่าง: ลด 50 บาท เมื่อซื้อครบ 500'),
('BIGTREE','percent',15,300,0,0,1,4,'ตัวอย่าง: สำหรับสมาชิกที่ต้นไม้โตถึง "ต้นใหญ่" ขึ้นไป')
ON DUPLICATE KEY UPDATE code=code;
