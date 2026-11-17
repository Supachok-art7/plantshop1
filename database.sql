-- database.sql (v5) — สร้างฐานข้อมูลร้านสวนสุขทั้งหมด (ติดตั้งใหม่ / install.php ก็อ่านไฟล์นี้ไปรัน)
-- วิธีใช้: phpMyAdmin > แท็บ Import > เลือกไฟล์นี้ > Import
-- ข้อควรระวัง: ห้ามใส่เครื่องหมายอัฒภาค ; ในข้อความคอมเมนต์หรือข้อมูล (install.php แยกคำสั่งด้วย ;)
SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS `plantshop` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `plantshop`;

CREATE TABLE IF NOT EXISTS users(              -- สมาชิก + แอดมิน
  id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, email VARCHAR(120) NOT NULL UNIQUE,
  phone VARCHAR(20), addr TEXT, pass VARCHAR(255) NOT NULL,   -- pass = รหัสผ่านที่เข้ารหัสแล้ว (bcrypt)
  role ENUM('admin','customer') DEFAULT 'customer', created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  avatar VARCHAR(150) NULL,                                   -- รูปโปรไฟล์ (ไฟล์ใน uploads/)
  birthdate DATE NULL, gender VARCHAR(10) NULL,
  addr_line VARCHAR(150) NULL, subdistrict VARCHAR(60) NULL, district VARCHAR(60) NULL,
  province VARCHAR(60) NULL, postcode VARCHAR(10) NULL,       -- ที่อยู่แยกช่อง (addr = ที่อยู่รวมสำหรับจัดส่ง)
  lat DOUBLE NULL, lng DOUBLE NULL,                           -- หมุดบ้านเริ่มต้นบนแผนที่
  verify_status VARCHAR(10) DEFAULT 'none',                   -- none / pending / verified / rejected
  verify_note VARCHAR(255) NULL, id_img VARCHAR(150) NULL, selfie_img VARCHAR(150) NULL,
  pdpa_version VARCHAR(20) NULL, marketing_opt_in TINYINT NOT NULL DEFAULT 0, deleted_at DATETIME NULL);  -- (id_img/selfie_img = เอกสารยืนยันตัวตน ถูกลบหลังตรวจเสร็จ)

CREATE TABLE IF NOT EXISTS categories(         -- หมวดหมู่ 2 ระดับ: parent_id NULL = หมวดหลัก
  id INT AUTO_INCREMENT PRIMARY KEY, parent_id INT NULL, name VARCHAR(80) NOT NULL,
  icon VARCHAR(16), sort INT DEFAULT 0);

CREATE TABLE IF NOT EXISTS products(           -- สินค้า
  id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, category_id INT NULL,
  price DECIMAL(10,2) NOT NULL, stock INT NOT NULL DEFAULT 0, emoji VARCHAR(16),
  image VARCHAR(150) NULL,                                    -- รูปสินค้า (ไฟล์ใน uploads/ แอดมินอัปโหลดได้)
  descr VARCHAR(255), detail TEXT,                            -- descr = สั้นๆ, detail = รายละเอียดเต็ม
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);

CREATE TABLE IF NOT EXISTS orders(             -- ออเดอร์ (เลขเริ่มที่ 1001)
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, name VARCHAR(100), phone VARCHAR(20), addr TEXT,
  lat DOUBLE NULL, lng DOUBLE NULL,                           -- พิกัดปักหมุดจัดส่ง
  total DECIMAL(10,2), pm VARCHAR(10),                        -- pm: pp=PromptPay, bank=โอน, cod=ปลายทาง
  status VARCHAR(30), slip VARCHAR(150) NULL,                 -- slip = ชื่อไฟล์สลิปใน uploads/
  carrier VARCHAR(40) NULL, tracking_no VARCHAR(60) NULL,     -- ขนส่ง + เลขพัสดุ
  subtotal DECIMAL(10,2) NULL, discount DECIMAL(10,2) NOT NULL DEFAULT 0, shipping DECIMAL(10,2) NOT NULL DEFAULT 0,   -- total = ยอดจ่ายจริง
  distance_km DECIMAL(7,1) NULL, vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0, vat_mode VARCHAR(10) NOT NULL DEFAULT 'included',
  vat DECIMAL(10,2) NOT NULL DEFAULT 0, coupon VARCHAR(20) NULL,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP) AUTO_INCREMENT=1001;

CREATE TABLE IF NOT EXISTS order_events(       -- ประวัติความคืบหน้าของออเดอร์ (ไทม์ไลน์ + เส้นทางบนแผนที่)
  id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, status VARCHAR(30), note VARCHAR(255),
  lat DOUBLE NULL, lng DOUBLE NULL,                           -- ตำแหน่งพัสดุ ณ ตอนนั้น (ถ้ามี)
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY(order_id));

CREATE TABLE IF NOT EXISTS order_items(        -- รายการสินค้าในแต่ละออเดอร์
  id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, product_id INT,
  name VARCHAR(150), price DECIMAL(10,2), qty INT);           -- เก็บชื่อ/ราคา ณ ตอนซื้อ

CREATE TABLE IF NOT EXISTS messages(           -- แชท: sender c=ลูกค้า, s=ร้าน
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, sender CHAR(1) NOT NULL, body TEXT,
  is_read TINYINT DEFAULT 0, created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);

CREATE TABLE IF NOT EXISTS slides(             -- สไลด์โฆษณาหน้าแรก
  id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(150), body VARCHAR(255), emoji VARCHAR(16), bg VARCHAR(40));

CREATE TABLE IF NOT EXISTS settings(k VARCHAR(40) PRIMARY KEY, v TEXT);   -- ตั้งค่าร้าน (PromptPay, พิกัดร้าน ฯลฯ)
CREATE TABLE IF NOT EXISTS reviews(            -- รีวิวสินค้า (แนบรูปได้ 1 รูป)
  id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_id INT NOT NULL,
  rating TINYINT NOT NULL, body TEXT, img VARCHAR(150) NULL,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY(product_id));

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

CREATE TABLE IF NOT EXISTS consents(            -- บันทึกความยินยอม (หลักฐานตาม PDPA)
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
  purpose VARCHAR(20) NOT NULL,                -- policy / marketing / cookies
  version VARCHAR(20), granted TINYINT NOT NULL DEFAULT 1,   -- granted 1 = ให้ความยินยอม 0 = ถอน
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY(user_id));

CREATE TABLE IF NOT EXISTS dsr_requests(       -- คำขอใช้สิทธิ์ของเจ้าของข้อมูล (ต้องตอบภายใน 30 วัน)
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
  type VARCHAR(20) NOT NULL,                   -- access / correct / erase / restrict / object / portability / withdraw
  detail TEXT, status VARCHAR(10) NOT NULL DEFAULT 'new',   -- new / working / done / rejected
  admin_note TEXT NULL, created TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated TIMESTAMP NULL,
  KEY(user_id), KEY(status));

CREATE TABLE IF NOT EXISTS audit_log(          -- บันทึกการเข้าถึงข้อมูลสำคัญของแอดมิน
  id INT AUTO_INCREMENT PRIMARY KEY, admin_id INT NULL, action VARCHAR(40) NOT NULL, target VARCHAR(80),
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY(created));

CREATE TABLE IF NOT EXISTS breach_log(         -- ทะเบียนเหตุละเมิดข้อมูล (แจ้ง สคส. ภายใน 72 ชั่วโมง)
  id INT AUTO_INCREMENT PRIMARY KEY, discovered_at DATETIME NOT NULL, description TEXT NOT NULL,
  affected INT NOT NULL DEFAULT 0, notified_pdpc DATETIME NULL, notified_users TINYINT NOT NULL DEFAULT 0,
  status VARCHAR(10) NOT NULL DEFAULT 'open', created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);

-- ===== ข้อมูลตัวอย่าง =====
-- บัญชีทดลอง: admin@shop.test / Admin@1234  และ  customer@shop.test / Customer@1234
INSERT INTO users(name,email,phone,addr,pass,role) VALUES
('ผู้ดูแลร้าน','admin@shop.test','0800000000','','$2y$12$uscZkdU3mJ8rgSTlWKIcYeOtca3vmEdADo0epmIr8HoHMw8s3cubm','admin'),
('สมชาย ทดสอบ','customer@shop.test','0812345678','99 ถนนสุขุมวิท กรุงเทพฯ','$2y$12$xsTtKZbPxiETiSxZrMz96OqcCtchFbwnx0WJNjO4r8LO2kh2zdvXe','customer')
ON DUPLICATE KEY UPDATE email=email;

-- หมวดหมู่ (หมวดหลัก 1-5 / หมวดย่อยเลขสองหลัก)
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

INSERT INTO products(name,category_id,price,stock,emoji,descr) VALUES
('มอนสเตอร่า',11,350,12,'🪴','ใบสวย ดูแลง่าย'),
('กระบองเพชร',12,90,30,'🌵','ทนแล้ง เหมาะวางโต๊ะทำงาน'),
('กุหลาบพันธุ์เล็ก',21,150,15,'🌹','ดอกสีแดงสด'),
('ทานตะวันแคระ',21,120,3,'🌻','ปลูกในกระถางได้'),
('ช่อทิวลิป',31,490,8,'🌷','ช่อสดพร้อมห่อของขวัญ'),
('ช่อลาเวนเดอร์',31,390,6,'💐','กลิ่นหอมผ่อนคลาย'),
('กระถางเซรามิก',51,180,20,'🏺','ขนาด 6 นิ้ว'),
('ดินปลูกพร้อมใช้',52,60,40,'🌱','ถุงละ 5 ลิตร'),
('กระเช้าไม้มงคล',32,590,5,'🎁','ชุดของขวัญต้นไม้มงคลพร้อมกระเช้า'),
('มะนาวแป้นกระถาง',41,280,10,'🍋','ปลูกในกระถางได้ ให้ผลจริง'),
('โหระพาไทย',42,45,25,'🌿','เด็ดใบทำอาหารได้ทันที'),
('ต้นพลูด่าง',13,120,18,'🍃','ฟอกอากาศ ทนร่ม');

-- รายละเอียดเต็มของสินค้า (ข้อมูลตัวอย่าง แก้ได้ในหลังบ้าน)
UPDATE products SET detail='ไม้ใบยอดนิยมสำหรับตกแต่งบ้านและออฟฟิศ ชอบแสงรำไร ไม่ควรโดนแดดจัดโดยตรง รดน้ำเมื่อผิวดินแห้ง และอย่าให้น้ำขัง ขนาดต้นประมาณ 30-40 ซม.' WHERE name='มอนสเตอร่า';
UPDATE products SET detail='ไม้อวบน้ำทนแล้ง วางริมหน้าต่างที่ได้รับแสงเยอะ รดน้ำน้อย สัปดาห์ละครั้งหรือน้อยกว่า เหมาะวางโต๊ะทำงานและเป็นของขวัญ' WHERE name='กระบองเพชร';
UPDATE products SET detail='กุหลาบพุ่มเล็กดอกสีแดงสด ชอบแดดเช้า รดน้ำสม่ำเสมอแต่ไม่แฉะ ตัดดอกที่เหี่ยวออกเพื่อให้ออกดอกต่อเนื่อง' WHERE name='กุหลาบพันธุ์เล็ก';
UPDATE products SET detail='ทานตะวันพันธุ์เตี้ยปลูกในกระถางได้ ต้องการแดดจัดอย่างน้อยวันละ 6 ชั่วโมง รดน้ำเช้าเย็นเมื่อดินเริ่มแห้ง' WHERE name='ทานตะวันแคระ';
UPDATE products SET detail='ช่อดอกไม้สดจัดพร้อมห่อของขวัญ เหมาะสำหรับวันเกิดและโอกาสพิเศษ เปลี่ยนน้ำทุกวันและตัดปลายก้านเล็กน้อยเพื่อให้บานทนขึ้น' WHERE name='ช่อทิวลิป';
UPDATE products SET detail='ช่อลาเวนเดอร์กลิ่นหอมผ่อนคลาย วางในที่ร่มระบายอากาศดี สามารถตากแห้งเก็บไว้เป็นของตกแต่งได้' WHERE name='ช่อลาเวนเดอร์';
UPDATE products SET detail='กระถางเซรามิกขนาด 6 นิ้ว มีรูระบายน้ำที่ก้น เหมาะกับไม้ประดับและกระบองเพชรขนาดเล็ก' WHERE name='กระถางเซรามิก';
UPDATE products SET detail='ดินปลูกผสมพร้อมใช้ ระบายน้ำดี ถุงละ 5 ลิตร ใช้ได้กับไม้ประดับและไม้ดอกทั่วไป' WHERE name='ดินปลูกพร้อมใช้';
UPDATE products SET detail='ชุดของขวัญต้นไม้มงคลขนาดเล็กจัดใส่กระเช้าพร้อมการ์ดอวยพร เหมาะสำหรับขึ้นบ้านใหม่และเปิดร้าน' WHERE name='กระเช้าไม้มงคล';
UPDATE products SET detail='มะนาวแป้นปลูกในกระถางได้ ต้องการแดดเต็มวัน รดน้ำสม่ำเสมอ ให้ผลได้ในกระถางขนาดใหญ่' WHERE name='มะนาวแป้นกระถาง';
UPDATE products SET detail='โหระพาไทยกลิ่นหอม ปลูกง่ายโตเร็ว เด็ดใบไปปรุงอาหารได้ ชอบแดดและรดน้ำสม่ำเสมอ' WHERE name='โหระพาไทย';
UPDATE products SET detail='พลูด่างไม้เลื้อยปลูกง่ายทนร่ม วางได้ทั้งในบ้านและออฟฟิศ ตัดแต่งยอดเพื่อให้แตกพุ่มสวย' WHERE name='ต้นพลูด่าง';

INSERT INTO slides(title,body,emoji,bg) VALUES
('โปรโมชั่นต้อนรับสมาชิกใหม่','สมัครสมาชิกวันนี้ รับข่าวสารโปรโมชั่นก่อนใคร','🎁','#15803d,#4ade80'),
('ลด 15% ช่อดอกไม้','ช่วงนี้เท่านั้น ช่อดอกไม้พร้อมห่อของขวัญ','💐','#0f766e,#5eead4');

-- ค่าตั้งต้นร้าน (ข้อมูลทดสอบ ให้เปลี่ยนเป็นของร้านจริงในหลังบ้าน) shop_lat/shop_lng = ตำแหน่งร้านบนแผนที่ติดตาม
INSERT INTO settings(k,v) VALUES
('pp','0812345678'),('bank','กสิกรไทย'),('acc','123-4-56789-0'),('aname','ร้านสวนสุข'),('cod','1'),
('shop_lat','13.7563'),('shop_lng','100.5018'),
('shop_phone','081-234-5678'),('shop_email','hello@shop.test'),('shop_line','@suansuk'),
('shop_addr','99 ถนนสุขุมวิท แขวงคลองเตย เขตคลองเตย กรุงเทพฯ 10110'),
('shop_hours','ทุกวัน 09:00 - 18:00 น.'),
('pdpa_version','1.0'),('ret_chat_days','365'),('ret_order_years','5'),('pdpa_dpo','hello@shop.test')
ON DUPLICATE KEY UPDATE v=VALUES(v);

-- รีวิวตัวอย่าง
INSERT INTO reviews(product_id,user_id,rating,body)
SELECT 1,id,5,'ต้นสวยมาก ส่งไวและแพ็กมาดี' FROM users WHERE email='customer@shop.test';

-- ===== ออเดอร์ตัวอย่างสำหรับลองระบบติดตาม (ลูกค้าทดลอง, สถานะ "จัดส่งแล้ว") =====
INSERT INTO orders(id,user_id,name,phone,addr,lat,lng,total,pm,status,carrier,tracking_no)
SELECT 1001,id,'สมชาย ทดสอบ','0812345678','99 ถนนสุขุมวิท กรุงเทพฯ',13.7370,100.5600,350,'pp','จัดส่งแล้ว','Flash Express','TH0123456789'
FROM users WHERE email='customer@shop.test';
INSERT INTO order_items(order_id,product_id,name,price,qty) VALUES(1001,1,'มอนสเตอร่า',350,1);
INSERT INTO order_events(order_id,status,note,lat,lng) VALUES
(1001,'รอชำระเงิน','สร้างออเดอร์',NULL,NULL),
(1001,'ชำระแล้ว','ตรวจสลิปแล้ว',NULL,NULL),
(1001,'เตรียมสินค้า','แพ็กสินค้าเรียบร้อย',13.7563,100.5018),
(1001,'จัดส่งแล้ว','ส่งมอบให้ขนส่งแล้ว',13.7563,100.5018),
(1001,'จัดส่งแล้ว','พัสดุถึงศูนย์คัดแยก',13.7249,100.5340);

-- ตัวอย่างรายงานจากลูกค้าทดลอง (ไว้ลองหน้า แจ้งรายงาน / แท็บ รายงาน ในหลังบ้าน)
INSERT INTO reports(user_id,type,target_id,subject,body,status)
SELECT id,'order',1001,'ตัวอย่าง: สอบถามพัสดุยังไม่ถึง','ตัวอย่างรายงานสำหรับทดลองระบบ พัสดุแสดงสถานะจัดส่งแล้วแต่ยังไม่ถึงที่หมาย รบกวนตรวจสอบให้หน่อยครับ','new'
FROM users WHERE email='customer@shop.test';

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
