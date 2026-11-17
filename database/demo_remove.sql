-- demo_remove.sql — ลบข้อมูลตัวอย่างทั้งหมด (ผู้ใช้ demo.* ออเดอร์ รีวิว แชท รายงาน PDPA ตัวอย่าง) และปิดโหมดทดลอง
-- ทำก่อนเปิดใช้งานจริง   วิธีใช้: phpMyAdmin > เลือกฐานข้อมูล plantshop > Import
SET NAMES utf8mb4;
USE `plantshop`;

-- ลบข้อมูลตัวอย่างเดิม (ถ้าเคยนำเข้ามาก่อน) เพื่อให้นำเข้าซ้ำได้โดยไม่ซ้ำซ้อน
DELETE FROM order_events WHERE order_id IN (SELECT id FROM orders WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'demo.%@shop.test' OR email LIKE 'deleted-demo%'));
DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'demo.%@shop.test' OR email LIKE 'deleted-demo%'));
DELETE FROM orders WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'demo.%@shop.test' OR email LIKE 'deleted-demo%');
DELETE FROM reviews WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'demo.%@shop.test' OR email LIKE 'deleted-demo%');
DELETE FROM messages WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'demo.%@shop.test' OR email LIKE 'deleted-demo%');
DELETE FROM reports WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'demo.%@shop.test' OR email LIKE 'deleted-demo%');
DELETE FROM consents WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'demo.%@shop.test' OR email LIKE 'deleted-demo%');
DELETE FROM dsr_requests WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'demo.%@shop.test' OR email LIKE 'deleted-demo%');
DELETE FROM users WHERE email LIKE 'demo.%@shop.test' OR email LIKE 'deleted-demo%';
DELETE FROM breach_log WHERE description LIKE '[ตัวอย่าง]%';
DELETE FROM audit_log WHERE target LIKE 'demo-%';

INSERT INTO settings(k,v) VALUES('demo_mode','0') ON DUPLICATE KEY UPDATE v='0';
