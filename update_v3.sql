-- update_v3.sql — อัปเดตฐานข้อมูลจากรุ่น v2 โดยไม่ลบข้อมูลเดิม   ** รันครั้งเดียวพอ **
-- เพิ่ม: ระบบติดตามพัสดุ (order_events) / โปรไฟล์ละเอียด+ยืนยันตัวตน / หมวดหมู่ 2 ระดับ
SET NAMES utf8mb4;
USE `plantshop`;

-- 1) โปรไฟล์ละเอียด
ALTER TABLE users
  ADD COLUMN avatar VARCHAR(150) NULL, ADD COLUMN birthdate DATE NULL, ADD COLUMN gender VARCHAR(10) NULL,
  ADD COLUMN addr_line VARCHAR(150) NULL, ADD COLUMN subdistrict VARCHAR(60) NULL, ADD COLUMN district VARCHAR(60) NULL,
  ADD COLUMN province VARCHAR(60) NULL, ADD COLUMN postcode VARCHAR(10) NULL,
  ADD COLUMN lat DOUBLE NULL, ADD COLUMN lng DOUBLE NULL,
  ADD COLUMN verify_status VARCHAR(10) DEFAULT 'none', ADD COLUMN verify_note VARCHAR(255) NULL,
  ADD COLUMN id_img VARCHAR(150) NULL, ADD COLUMN selfie_img VARCHAR(150) NULL;

-- 2) ติดตามพัสดุ
ALTER TABLE orders ADD COLUMN carrier VARCHAR(40) NULL, ADD COLUMN tracking_no VARCHAR(60) NULL;
CREATE TABLE IF NOT EXISTS order_events(       -- ประวัติความคืบหน้าของออเดอร์ (ไทม์ไลน์ + เส้นทางบนแผนที่)
  id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, status VARCHAR(30), note VARCHAR(255),
  lat DOUBLE NULL, lng DOUBLE NULL,                           -- ตำแหน่งพัสดุ ณ ตอนนั้น (ถ้ามี)
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY(order_id));
-- ออเดอร์เดิมแต่ละใบ ใส่ประวัติเริ่มต้น 1 รายการ
INSERT INTO order_events(order_id,status,note,created) SELECT id,status,'ข้อมูลก่อนเริ่มใช้ระบบติดตาม',created FROM orders;
INSERT INTO settings(k,v) VALUES('shop_lat','13.7563'),('shop_lng','100.5018') ON DUPLICATE KEY UPDATE v=v;

-- 3) หมวดหมู่ใหม่ (ช่อง cat เดิมยังเก็บไว้ ไม่ลบ แต่เว็บใช้ category_id แทน)
CREATE TABLE IF NOT EXISTS categories(         -- หมวดหมู่ 2 ระดับ: parent_id NULL = หมวดหลัก
  id INT AUTO_INCREMENT PRIMARY KEY, parent_id INT NULL, name VARCHAR(80) NOT NULL,
  icon VARCHAR(16), sort INT DEFAULT 0);
INSERT INTO categories(id,parent_id,name,icon,sort) VALUES
(1,NULL,'ไม้ประดับ','🪴',1),
(2,NULL,'ไม้ดอก','🌸',2),
(3,NULL,'ช่อดอกไม้และของขวัญ','💐',3),
(4,NULL,'ไม้ผลและสมุนไพร','🍋',4),
(5,NULL,'กระถางและอุปกรณ์','🏺',5),
(11,1,'ไม้ใบ','🌿',1),
(12,1,'กระบองเพชรและไม้อวบน้ำ','🌵',2),
(13,1,'ไม้ฟอกอากาศ','🍃',3),
(21,2,'ไม้ดอกกระถาง','🌺',1),
(22,2,'ไม้ดอกตามฤดูกาล','🌼',2),
(31,3,'ช่อดอกไม้สด','💐',1),
(32,3,'กระเช้าและชุดของขวัญ','🎁',2),
(41,4,'ไม้ผลกระถาง','🍋',1),
(42,4,'สมุนไพรและผักสวนครัว','🌱',2),
(51,5,'กระถาง','🏺',1),
(52,5,'ดินและปุ๋ย','🧪',2),
(53,5,'อุปกรณ์ดูแลต้นไม้','🧰',3);
ALTER TABLE products ADD COLUMN category_id INT NULL;
UPDATE products SET category_id=11 WHERE name='มอนสเตอร่า';
UPDATE products SET category_id=12 WHERE name='กระบองเพชร';
UPDATE products SET category_id=21 WHERE name IN ('กุหลาบพันธุ์เล็ก','ทานตะวันแคระ');
UPDATE products SET category_id=31 WHERE name IN ('ช่อทิวลิป','ช่อลาเวนเดอร์');
UPDATE products SET category_id=51 WHERE name='กระถางเซรามิก';
UPDATE products SET category_id=52 WHERE name='ดินปลูกพร้อมใช้';
-- สินค้าที่แอดมินเพิ่มเองและยังไม่มีหมวด: จับเข้าหมวดหลักตามชื่อหมวดเดิม
UPDATE products SET category_id=1 WHERE category_id IS NULL AND cat='ไม้ประดับ';
UPDATE products SET category_id=2 WHERE category_id IS NULL AND cat='ไม้ดอก';
UPDATE products SET category_id=3 WHERE category_id IS NULL AND cat='ช่อดอกไม้';
UPDATE products SET category_id=5 WHERE category_id IS NULL AND cat LIKE 'กระถาง%';

-- สินค้าตัวอย่างใหม่ให้ครบทุกหมวด
INSERT INTO products(name,category_id,price,stock,emoji,descr) VALUES
('กระเช้าไม้มงคล',32,590,5,'🎁','ชุดของขวัญต้นไม้มงคลพร้อมกระเช้า'),
('มะนาวแป้นกระถาง',41,280,10,'🍋','ปลูกในกระถางได้ ให้ผลจริง'),
('โหระพาไทย',42,45,25,'🌿','เด็ดใบทำอาหารได้ทันที'),
('ต้นพลูด่าง',13,120,18,'🍃','ฟอกอากาศ ทนร่ม');
UPDATE products SET detail='ชุดของขวัญต้นไม้มงคลขนาดเล็กจัดใส่กระเช้าพร้อมการ์ดอวยพร เหมาะสำหรับขึ้นบ้านใหม่และเปิดร้าน' WHERE name='กระเช้าไม้มงคล';
UPDATE products SET detail='มะนาวแป้นปลูกในกระถางได้ ต้องการแดดเต็มวัน รดน้ำสม่ำเสมอ ให้ผลได้ในกระถางขนาดใหญ่' WHERE name='มะนาวแป้นกระถาง';
UPDATE products SET detail='โหระพาไทยกลิ่นหอม ปลูกง่ายโตเร็ว เด็ดใบไปปรุงอาหารได้ ชอบแดดและรดน้ำสม่ำเสมอ' WHERE name='โหระพาไทย';
UPDATE products SET detail='พลูด่างไม้เลื้อยปลูกง่ายทนร่ม วางได้ทั้งในบ้านและออฟฟิศ ตัดแต่งยอดเพื่อให้แตกพุ่มสวย' WHERE name='ต้นพลูด่าง';
