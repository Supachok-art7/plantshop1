-- update_v6.sql — เพิ่มระบบ PDPA (ความยินยอม คำขอใช้สิทธิ์ ลบ/ดาวน์โหลดข้อมูล ทะเบียนเหตุละเมิด บันทึกการเข้าถึง)   ** รันครั้งเดียวพอ **
-- ใช้ MariaDB (XAMPP) รันซ้ำได้ไม่พัง ถ้าใช้ MySQL ธรรมดาให้ลบคำว่า IF NOT EXISTS ในบรรทัด ALTER ออก
SET NAMES utf8mb4;
USE `plantshop`;

ALTER TABLE users ADD COLUMN IF NOT EXISTS pdpa_version VARCHAR(20) NULL,
  ADD COLUMN IF NOT EXISTS marketing_opt_in TINYINT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;

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

INSERT INTO settings(k,v) VALUES
('pdpa_version','1.0'),('ret_chat_days','365'),('ret_order_years','5'),('pdpa_dpo','hello@shop.test')
ON DUPLICATE KEY UPDATE v=v;
