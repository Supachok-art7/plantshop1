-- update_v5.sql — เพิ่มรูปสินค้า (แอดมินอัปโหลดรูปได้ที่หลังบ้าน แท็บ สินค้า)   ** รันครั้งเดียวพอ **
-- ใช้ MariaDB (XAMPP) รันซ้ำได้ไม่พัง ถ้าใช้ MySQL ธรรมดาให้ลบคำว่า IF NOT EXISTS ออก
SET NAMES utf8mb4;
USE `plantshop`;
ALTER TABLE products ADD COLUMN IF NOT EXISTS image VARCHAR(150) NULL AFTER emoji;
