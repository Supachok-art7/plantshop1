-- fix_database.sql — ซ่อม/อัปเดตฐานข้อมูลร้านสวนสุข ให้ตรงกับโค้ดรุ่นล่าสุด (v5)
-- วิธีใช้: phpMyAdmin > แท็บ SQL (หรือ Import) > วางทั้งไฟล์ / เลือกไฟล์นี้ > Go
-- ปลอดภัย: รันซ้ำกี่ครั้งก็ได้ ไม่ลบข้อมูลเดิม ไม่ทับข้อมูลที่มีอยู่แล้ว
-- ต้องใช้ MariaDB (ที่มากับ XAMPP) เพราะใช้คำสั่ง ADD COLUMN IF NOT EXISTS
-- ถ้าฐานข้อมูลพังหนักจนอยากเริ่มใหม่หมด ให้ DROP DATABASE plantshop แล้ว Import ไฟล์ database.sql แทน
SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS `plantshop` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `plantshop`;

-- ===== 1) สร้างตารางที่หายไป (ตารางที่มีอยู่แล้วจะถูกข้าม) =====
CREATE TABLE IF NOT EXISTS users(
  id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, email VARCHAR(120) NOT NULL UNIQUE,
  phone VARCHAR(20), addr TEXT, pass VARCHAR(255) NOT NULL,
  role ENUM('admin','customer') DEFAULT 'customer', created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  avatar VARCHAR(150) NULL,
  birthdate DATE NULL, gender VARCHAR(10) NULL,
  addr_line VARCHAR(150) NULL, subdistrict VARCHAR(60) NULL, district VARCHAR(60) NULL,
  province VARCHAR(60) NULL, postcode VARCHAR(10) NULL,
  lat DOUBLE NULL, lng DOUBLE NULL,
  verify_status VARCHAR(10) DEFAULT 'none',
  verify_note VARCHAR(255) NULL, id_img VARCHAR(150) NULL, selfie_img VARCHAR(150) NULL);

CREATE TABLE IF NOT EXISTS categories(
  id INT AUTO_INCREMENT PRIMARY KEY, parent_id INT NULL, name VARCHAR(80) NOT NULL,
  icon VARCHAR(16), sort INT DEFAULT 0);

CREATE TABLE IF NOT EXISTS products(
  id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, category_id INT NULL,
  price DECIMAL(10,2) NOT NULL, stock INT NOT NULL DEFAULT 0, emoji VARCHAR(16), image VARCHAR(150) NULL,
  descr VARCHAR(255), detail TEXT,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);

CREATE TABLE IF NOT EXISTS orders(
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, name VARCHAR(100), phone VARCHAR(20), addr TEXT,
  lat DOUBLE NULL, lng DOUBLE NULL,
  total DECIMAL(10,2), pm VARCHAR(10),
  status VARCHAR(30), slip VARCHAR(150) NULL,
  carrier VARCHAR(40) NULL, tracking_no VARCHAR(60) NULL,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP) AUTO_INCREMENT=1001;

CREATE TABLE IF NOT EXISTS order_events(
  id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, status VARCHAR(30), note VARCHAR(255),
  lat DOUBLE NULL, lng DOUBLE NULL,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY(order_id));

CREATE TABLE IF NOT EXISTS order_items(
  id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, product_id INT,
  name VARCHAR(150), price DECIMAL(10,2), qty INT);

CREATE TABLE IF NOT EXISTS messages(
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, sender CHAR(1) NOT NULL, body TEXT,
  is_read TINYINT DEFAULT 0, created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);

CREATE TABLE IF NOT EXISTS slides(
  id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(150), body VARCHAR(255), emoji VARCHAR(16), bg VARCHAR(40));

CREATE TABLE IF NOT EXISTS settings(k VARCHAR(40) PRIMARY KEY, v TEXT);

CREATE TABLE IF NOT EXISTS reviews(
  id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_id INT NOT NULL,
  rating TINYINT NOT NULL, body TEXT, img VARCHAR(150) NULL,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY(product_id));

CREATE TABLE IF NOT EXISTS reports(
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
  type VARCHAR(20) NOT NULL,
  target_id INT NULL,
  subject VARCHAR(150) NOT NULL, body TEXT NOT NULL, img VARCHAR(150) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'new',
  admin_reply TEXT NULL,
  user_seen TINYINT NOT NULL DEFAULT 1,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated TIMESTAMP NULL,
  KEY(user_id), KEY(status));

-- ===== 2) เพิ่มคอลัมน์ที่หายไป (รวม products.image = รูปสินค้า) =====
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS name VARCHAR(100) NOT NULL,
  ADD COLUMN IF NOT EXISTS email VARCHAR(120) NOT NULL,
  ADD COLUMN IF NOT EXISTS phone VARCHAR(20),
  ADD COLUMN IF NOT EXISTS addr TEXT,
  ADD COLUMN IF NOT EXISTS pass VARCHAR(255) NOT NULL,
  ADD COLUMN IF NOT EXISTS role ENUM('admin','customer') DEFAULT 'customer',
  ADD COLUMN IF NOT EXISTS created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS avatar VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS birthdate DATE NULL,
  ADD COLUMN IF NOT EXISTS gender VARCHAR(10) NULL,
  ADD COLUMN IF NOT EXISTS addr_line VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS subdistrict VARCHAR(60) NULL,
  ADD COLUMN IF NOT EXISTS district VARCHAR(60) NULL,
  ADD COLUMN IF NOT EXISTS province VARCHAR(60) NULL,
  ADD COLUMN IF NOT EXISTS postcode VARCHAR(10) NULL,
  ADD COLUMN IF NOT EXISTS lat DOUBLE NULL,
  ADD COLUMN IF NOT EXISTS lng DOUBLE NULL,
  ADD COLUMN IF NOT EXISTS verify_status VARCHAR(10) DEFAULT 'none',
  ADD COLUMN IF NOT EXISTS verify_note VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS id_img VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS selfie_img VARCHAR(150) NULL;

ALTER TABLE categories
  ADD COLUMN IF NOT EXISTS parent_id INT NULL,
  ADD COLUMN IF NOT EXISTS name VARCHAR(80) NOT NULL,
  ADD COLUMN IF NOT EXISTS icon VARCHAR(16),
  ADD COLUMN IF NOT EXISTS sort INT DEFAULT 0;

ALTER TABLE products
  ADD COLUMN IF NOT EXISTS name VARCHAR(150) NOT NULL,
  ADD COLUMN IF NOT EXISTS category_id INT NULL,
  ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) NOT NULL,
  ADD COLUMN IF NOT EXISTS stock INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS emoji VARCHAR(16),
  ADD COLUMN IF NOT EXISTS image VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS descr VARCHAR(255),
  ADD COLUMN IF NOT EXISTS detail TEXT,
  ADD COLUMN IF NOT EXISTS created TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS user_id INT NOT NULL,
  ADD COLUMN IF NOT EXISTS name VARCHAR(100),
  ADD COLUMN IF NOT EXISTS phone VARCHAR(20),
  ADD COLUMN IF NOT EXISTS addr TEXT,
  ADD COLUMN IF NOT EXISTS lat DOUBLE NULL,
  ADD COLUMN IF NOT EXISTS lng DOUBLE NULL,
  ADD COLUMN IF NOT EXISTS total DECIMAL(10,2),
  ADD COLUMN IF NOT EXISTS pm VARCHAR(10),
  ADD COLUMN IF NOT EXISTS status VARCHAR(30),
  ADD COLUMN IF NOT EXISTS slip VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS carrier VARCHAR(40) NULL,
  ADD COLUMN IF NOT EXISTS tracking_no VARCHAR(60) NULL,
  ADD COLUMN IF NOT EXISTS created TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE order_events
  ADD COLUMN IF NOT EXISTS order_id INT NOT NULL,
  ADD COLUMN IF NOT EXISTS status VARCHAR(30),
  ADD COLUMN IF NOT EXISTS note VARCHAR(255),
  ADD COLUMN IF NOT EXISTS lat DOUBLE NULL,
  ADD COLUMN IF NOT EXISTS lng DOUBLE NULL,
  ADD COLUMN IF NOT EXISTS created TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE order_items
  ADD COLUMN IF NOT EXISTS order_id INT NOT NULL,
  ADD COLUMN IF NOT EXISTS product_id INT,
  ADD COLUMN IF NOT EXISTS name VARCHAR(150),
  ADD COLUMN IF NOT EXISTS price DECIMAL(10,2),
  ADD COLUMN IF NOT EXISTS qty INT;

ALTER TABLE messages
  ADD COLUMN IF NOT EXISTS user_id INT NOT NULL,
  ADD COLUMN IF NOT EXISTS sender CHAR(1) NOT NULL,
  ADD COLUMN IF NOT EXISTS body TEXT,
  ADD COLUMN IF NOT EXISTS is_read TINYINT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS created TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE slides
  ADD COLUMN IF NOT EXISTS title VARCHAR(150),
  ADD COLUMN IF NOT EXISTS body VARCHAR(255),
  ADD COLUMN IF NOT EXISTS emoji VARCHAR(16),
  ADD COLUMN IF NOT EXISTS bg VARCHAR(40);

ALTER TABLE settings
  ADD COLUMN IF NOT EXISTS v TEXT;

ALTER TABLE reviews
  ADD COLUMN IF NOT EXISTS product_id INT NOT NULL,
  ADD COLUMN IF NOT EXISTS user_id INT NOT NULL,
  ADD COLUMN IF NOT EXISTS rating TINYINT NOT NULL,
  ADD COLUMN IF NOT EXISTS body TEXT,
  ADD COLUMN IF NOT EXISTS img VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS created TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE reports
  ADD COLUMN IF NOT EXISTS user_id INT NOT NULL,
  ADD COLUMN IF NOT EXISTS type VARCHAR(20) NOT NULL,
  ADD COLUMN IF NOT EXISTS target_id INT NULL,
  ADD COLUMN IF NOT EXISTS subject VARCHAR(150) NOT NULL,
  ADD COLUMN IF NOT EXISTS body TEXT NOT NULL,
  ADD COLUMN IF NOT EXISTS img VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS status VARCHAR(12) NOT NULL DEFAULT 'new',
  ADD COLUMN IF NOT EXISTS admin_reply TEXT NULL,
  ADD COLUMN IF NOT EXISTS user_seen TINYINT NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS updated TIMESTAMP NULL;

-- ===== 3) เติมหมวดหมู่มาตรฐานที่หายไป (INSERT IGNORE = มีแล้วไม่ทับ) =====
INSERT IGNORE INTO categories(id,parent_id,name,icon,sort) VALUES
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

-- ===== 4) เติมค่าตั้งค่าร้านที่หายไป =====
INSERT IGNORE INTO settings(k,v) VALUES
('pp','0812345678'),('bank','กสิกรไทย'),('acc','123-4-56789-0'),('aname','ร้านสวนสุข'),('cod','1'),
('shop_lat','13.7563'),('shop_lng','100.5018'),
('shop_phone','081-234-5678'),('shop_email','hello@shop.test'),('shop_line','@suansuk'),
('shop_addr','99 ถนนสุขุมวิท แขวงคลองเตย เขตคลองเตย กรุงเทพฯ 10110'),
('shop_hours','ทุกวัน 09:00 - 18:00 น.');

-- ===== 5) แก้ข้อมูลที่เชื่อมกันผิด =====
-- หมวดย่อยที่อ้างหมวดหลักที่ไม่มีอยู่ → ย้ายขึ้นเป็นหมวดหลัก
UPDATE categories c LEFT JOIN categories p ON p.id=c.parent_id
SET c.parent_id=NULL WHERE c.parent_id IS NOT NULL AND p.id IS NULL;
-- สินค้าที่อ้างหมวดที่ไม่มีอยู่ → ล้างค่าหมวด (ไปตั้งใหม่ที่หลังบ้าน แท็บ สินค้า)
UPDATE products p LEFT JOIN categories c ON c.id=p.category_id
SET p.category_id=NULL WHERE p.category_id IS NOT NULL AND c.id IS NULL;

-- ===== 6) บัญชีทดลอง: ถ้าไม่มีให้สร้าง / ถ้ามีอยู่ไม่ทับรหัสผ่าน =====
-- admin@shop.test / Admin@1234     customer@shop.test / Customer@1234
INSERT IGNORE INTO users(name,email,phone,addr,pass,role) VALUES
('ผู้ดูแลร้าน','admin@shop.test','0800000000','','$2y$12$uscZkdU3mJ8rgSTlWKIcYeOtca3vmEdADo0epmIr8HoHMw8s3cubm','admin'),
('สมชาย ทดสอบ','customer@shop.test','0812345678','99 ถนนสุขุมวิท กรุงเทพฯ','$2y$12$xsTtKZbPxiETiSxZrMz96OqcCtchFbwnx0WJNjO4r8LO2kh2zdvXe','customer');
-- คืนสิทธิ์แอดมินให้บัญชี admin@shop.test (เผื่อเผลอแก้ role)
UPDATE users SET role='admin' WHERE email='admin@shop.test';

-- ===== ส่วนเสริม (ลบ -- หน้าบรรทัดเมื่อต้องการใช้) =====
-- (ก) ลืมรหัสผ่านบัญชีทดลอง → รีเซ็ตกลับเป็นค่าเริ่มต้น
-- UPDATE users SET pass='$2y$12$uscZkdU3mJ8rgSTlWKIcYeOtca3vmEdADo0epmIr8HoHMw8s3cubm' WHERE email='admin@shop.test';
-- UPDATE users SET pass='$2y$12$xsTtKZbPxiETiSxZrMz96OqcCtchFbwnx0WJNjO4r8LO2kh2zdvXe' WHERE email='customer@shop.test';
-- (ข) ตาราง/คอลัมน์ id เผลอเอา AUTO_INCREMENT ออก (เปลี่ยนชื่อตารางตามที่เสียเอง)
-- ALTER TABLE products MODIFY id INT NOT NULL AUTO_INCREMENT;
-- (ค) ฐานข้อมูลรุ่นเก่ามีคอลัมน์ cat (ชื่อหมวดเป็นข้อความ) → จับสินค้าเข้าหมวดหลักตามชื่อ
-- UPDATE products SET category_id=1 WHERE category_id IS NULL AND cat='ไม้ประดับ';
-- UPDATE products SET category_id=2 WHERE category_id IS NULL AND cat='ไม้ดอก';
-- UPDATE products SET category_id=3 WHERE category_id IS NULL AND cat='ช่อดอกไม้';
-- UPDATE products SET category_id=5 WHERE category_id IS NULL AND cat LIKE 'กระถาง%';
