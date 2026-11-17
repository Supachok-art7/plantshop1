-- demo_data.sql — ข้อมูลตัวอย่างสำหรับให้ครู/ผู้ตรวจดูระบบ (ผู้ใช้ 10 กรณี + ออเดอร์ทุกสถานะ + รีวิว + แชท + รายงาน + PDPA)
-- วิธีใช้: phpMyAdmin > เลือกฐานข้อมูล plantshop > Import > เลือกไฟล์นี้ (นำเข้าซ้ำได้ ไม่ซ้ำซ้อน)
-- ต้องนำเข้า database.sql (หรือ update_v6.sql) ก่อน   รหัสผ่านของบัญชี demo.* ทุกบัญชี = Demo@1234
-- ข้อมูลทั้งหมดเป็นข้อมูลสมมติ ก่อนเปิดใช้งานจริงให้นำเข้า demo_remove.sql เพื่อลบออก
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


INSERT INTO users(name,email,phone,addr,pass,role,created,birthdate,gender,addr_line,subdistrict,district,province,postcode,lat,lng,verify_status,verify_note,pdpa_version,marketing_opt_in,deleted_at) VALUES('สมหญิง ใจดี','demo.verified@shop.test','0891112233','45/2 ถนนนิมมานเหมินท์ ซอย 9 ต.สุเทพ อ.เมืองเชียงใหม่ จ.เชียงใหม่ 50200','$2y$12$iWcdwDabKbuYpSrViCCN2./peP/qnldTLr8Al3simLwKr83Qlpvlm','customer',NOW() - INTERVAL 2160 HOUR,'1995-04-12','female','45/2 ถนนนิมมานเหมินท์ ซอย 9','สุเทพ','เมืองเชียงใหม่','เชียงใหม่','50200',18.7992,98.968,'verified',NULL,'1.0',1,NULL);
SET @u_verified = LAST_INSERT_ID();

INSERT INTO users(name,email,phone,addr,pass,role,created,birthdate,gender,addr_line,subdistrict,district,province,postcode,lat,lng,verify_status,verify_note,pdpa_version,marketing_opt_in,deleted_at) VALUES('ธนากร รอตรวจสอบ','demo.pending@shop.test','0862223344','120 หมู่ 3 ถนนมิตรภาพ ต.ในเมือง อ.เมืองขอนแก่น จ.ขอนแก่น 40000','$2y$12$iWcdwDabKbuYpSrViCCN2./peP/qnldTLr8Al3simLwKr83Qlpvlm','customer',NOW() - INTERVAL 480 HOUR,'1999-09-30','male','120 หมู่ 3 ถนนมิตรภาพ','ในเมือง','เมืองขอนแก่น','ขอนแก่น','40000',16.4419,102.836,'pending',NULL,'1.0',0,NULL);
SET @u_pending = LAST_INSERT_ID();

INSERT INTO users(name,email,phone,addr,pass,role,created,birthdate,gender,addr_line,subdistrict,district,province,postcode,lat,lng,verify_status,verify_note,pdpa_version,marketing_opt_in,deleted_at) VALUES('มานี ไม่ผ่านการตรวจ','demo.rejected@shop.test','0873334455','8 ซอยลาดพร้าว 71 ต.ลาดพร้าว อ.ลาดพร้าว จ.กรุงเทพมหานคร 10230','$2y$12$iWcdwDabKbuYpSrViCCN2./peP/qnldTLr8Al3simLwKr83Qlpvlm','customer',NOW() - INTERVAL 840 HOUR,NULL,'female','8 ซอยลาดพร้าว 71','ลาดพร้าว','ลาดพร้าว','กรุงเทพมหานคร','10230',13.8036,100.6086,'rejected','รูปบัตรไม่ชัดเจน กรุณาถ่ายใหม่ให้เห็นข้อมูลครบ','1.0',0,NULL);
SET @u_rejected = LAST_INSERT_ID();

INSERT INTO users(name,email,phone,addr,pass,role,created,birthdate,gender,addr_line,subdistrict,district,province,postcode,lat,lng,verify_status,verify_note,pdpa_version,marketing_opt_in,deleted_at) VALUES('ใหม่ ล่าสุด','demo.new@shop.test','0844445566',NULL,'$2y$12$iWcdwDabKbuYpSrViCCN2./peP/qnldTLr8Al3simLwKr83Qlpvlm','customer',NOW() - INTERVAL 24 HOUR,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'none',NULL,NULL,0,NULL);
SET @u_new = LAST_INSERT_ID();

INSERT INTO users(name,email,phone,addr,pass,role,created,birthdate,gender,addr_line,subdistrict,district,province,postcode,lat,lng,verify_status,verify_note,pdpa_version,marketing_opt_in,deleted_at) VALUES('วิชัย ส่งของภูเก็ต','demo.shipping@shop.test','0815556677','77/7 ถนนเทพกระษัตรี ต.ตลาดใหญ่ อ.เมืองภูเก็ต จ.ภูเก็ต 83000','$2y$12$iWcdwDabKbuYpSrViCCN2./peP/qnldTLr8Al3simLwKr83Qlpvlm','customer',NOW() - INTERVAL 1440 HOUR,NULL,'male','77/7 ถนนเทพกระษัตรี','ตลาดใหญ่','เมืองภูเก็ต','ภูเก็ต','83000',7.8804,98.3923,'none',NULL,'1.0',1,NULL);
SET @u_shipping = LAST_INSERT_ID();

INSERT INTO users(name,email,phone,addr,pass,role,created,birthdate,gender,addr_line,subdistrict,district,province,postcode,lat,lng,verify_status,verify_note,pdpa_version,marketing_opt_in,deleted_at) VALUES('ปราณี ยกเลิกออเดอร์','demo.cancel@shop.test','0826667788','33 ถนนนิพัทธ์อุทิศ 3 ต.หาดใหญ่ อ.หาดใหญ่ จ.สงขลา 90110','$2y$12$iWcdwDabKbuYpSrViCCN2./peP/qnldTLr8Al3simLwKr83Qlpvlm','customer',NOW() - INTERVAL 1080 HOUR,NULL,'female','33 ถนนนิพัทธ์อุทิศ 3','หาดใหญ่','หาดใหญ่','สงขลา','90110',7.0086,100.4747,'none',NULL,'1.0',0,NULL);
SET @u_cancel = LAST_INSERT_ID();

INSERT INTO users(name,email,phone,addr,pass,role,created,birthdate,gender,addr_line,subdistrict,district,province,postcode,lat,lng,verify_status,verify_note,pdpa_version,marketing_opt_in,deleted_at) VALUES('ชัย ถามแชท','demo.chat@shop.test','0837778899','9 ถนนช้างคลาน ต.ช้างคลาน อ.เมืองเชียงใหม่ จ.เชียงใหม่ 50100','$2y$12$iWcdwDabKbuYpSrViCCN2./peP/qnldTLr8Al3simLwKr83Qlpvlm','customer',NOW() - INTERVAL 360 HOUR,NULL,'male','9 ถนนช้างคลาน','ช้างคลาน','เมืองเชียงใหม่','เชียงใหม่','50100',18.7807,98.9996,'none',NULL,'1.0',0,NULL);
SET @u_chat = LAST_INSERT_ID();

INSERT INTO users(name,email,phone,addr,pass,role,created,birthdate,gender,addr_line,subdistrict,district,province,postcode,lat,lng,verify_status,verify_note,pdpa_version,marketing_opt_in,deleted_at) VALUES('ดวงใจ ใช้สิทธิ PDPA','demo.dsr@shop.test','0848889900','5 ซอยอารีย์ 2 ต.สามเสนใน อ.พญาไท จ.กรุงเทพมหานคร 10400','$2y$12$iWcdwDabKbuYpSrViCCN2./peP/qnldTLr8Al3simLwKr83Qlpvlm','customer',NOW() - INTERVAL 1680 HOUR,NULL,'female','5 ซอยอารีย์ 2','สามเสนใน','พญาไท','กรุงเทพมหานคร','10400',13.7797,100.5446,'none',NULL,'1.0',0,NULL);
SET @u_dsr = LAST_INSERT_ID();

INSERT INTO users(name,email,phone,addr,pass,role,created,birthdate,gender,addr_line,subdistrict,district,province,postcode,lat,lng,verify_status,verify_note,pdpa_version,marketing_opt_in,deleted_at) VALUES('เศรษฐี ลูกค้าประจำ','demo.vip@shop.test','0859990011','88 ถนนสุขุมวิท 39 ต.คลองตันเหนือ อ.วัฒนา จ.กรุงเทพมหานคร 10110','$2y$12$iWcdwDabKbuYpSrViCCN2./peP/qnldTLr8Al3simLwKr83Qlpvlm','customer',NOW() - INTERVAL 4800 HOUR,'1988-01-20','male','88 ถนนสุขุมวิท 39','คลองตันเหนือ','วัฒนา','กรุงเทพมหานคร','10110',13.7367,100.57,'verified',NULL,'1.0',1,NULL);
SET @u_vip = LAST_INSERT_ID();

INSERT INTO users(name,email,pass,role,created,verify_status,deleted_at) VALUES('ผู้ใช้ที่ลบบัญชีแล้ว','deleted-demo@deleted.invalid','$2y$12$iWcdwDabKbuYpSrViCCN2./peP/qnldTLr8Al3simLwKr83Qlpvlm','customer',NOW() - INTERVAL 60000 HOUR,'none',NOW() - INTERVAL 120 HOUR);
SET @u_deleted = LAST_INSERT_ID();

INSERT INTO orders(user_id,name,phone,addr,lat,lng,total,pm,status,slip,carrier,tracking_no,created) VALUES(@u_verified,'สมหญิง ใจดี','0891112233','45/2 ถนนนิมมานเหมินท์ ซอย 9 ต.สุเทพ อ.เมืองเชียงใหม่ จ.เชียงใหม่ 50200',18.7992,98.968,530,'pp','ได้รับสินค้าแล้ว','demo_slip.svg','Kerry Express','KE1234567890',NOW() - INTERVAL 720 HOUR);
SET @o = LAST_INSERT_ID();
INSERT INTO order_items(order_id,product_id,name,price,qty) VALUES
(@o,(SELECT id FROM products WHERE name='มอนสเตอร่า' LIMIT 1),'มอนสเตอร่า',350,1),
(@o,(SELECT id FROM products WHERE name='กระถางเซรามิก' LIMIT 1),'กระถางเซรามิก',180,1);
INSERT INTO order_events(order_id,status,note,lat,lng,created) VALUES
(@o,'รอชำระเงิน','สร้างออเดอร์',NULL,NULL,NOW() - INTERVAL 720 HOUR),
(@o,'แจ้งชำระแล้ว','ลูกค้าแนบสลิป',NULL,NULL,NOW() - INTERVAL 719 HOUR),
(@o,'ชำระแล้ว','ตรวจสลิปแล้ว',NULL,NULL,NOW() - INTERVAL 717 HOUR),
(@o,'เตรียมสินค้า','แพ็กสินค้าเรียบร้อย',13.7563,100.5018,NOW() - INTERVAL 696 HOUR),
(@o,'จัดส่งแล้ว','ส่งมอบให้ขนส่ง',13.7563,100.5018,NOW() - INTERVAL 690 HOUR),
(@o,'จัดส่งแล้ว','พัสดุถึงศูนย์คัดแยกเชียงใหม่',18.765,99.005,NOW() - INTERVAL 660 HOUR),
(@o,'ได้รับสินค้าแล้ว','ลูกค้ายืนยันรับสินค้า',NULL,NULL,NOW() - INTERVAL 640 HOUR);

INSERT INTO orders(user_id,name,phone,addr,lat,lng,total,pm,status,slip,carrier,tracking_no,created) VALUES(@u_verified,'สมหญิง ใจดี','0891112233','45/2 ถนนนิมมานเหมินท์ ซอย 9 ต.สุเทพ อ.เมืองเชียงใหม่ จ.เชียงใหม่ 50200',18.7992,98.968,300,'cod','จัดส่งแล้ว',NULL,'Flash Express','TH9988776655',NOW() - INTERVAL 72 HOUR);
SET @o = LAST_INSERT_ID();
INSERT INTO order_items(order_id,product_id,name,price,qty) VALUES
(@o,(SELECT id FROM products WHERE name='กุหลาบพันธุ์เล็ก' LIMIT 1),'กุหลาบพันธุ์เล็ก',150,2);
INSERT INTO order_events(order_id,status,note,lat,lng,created) VALUES
(@o,'เก็บเงินปลายทาง','สร้างออเดอร์',NULL,NULL,NOW() - INTERVAL 72 HOUR),
(@o,'เตรียมสินค้า','แพ็กสินค้าเรียบร้อย',13.7563,100.5018,NOW() - INTERVAL 52 HOUR),
(@o,'จัดส่งแล้ว','ส่งมอบให้ขนส่ง',13.7563,100.5018,NOW() - INTERVAL 46 HOUR),
(@o,'จัดส่งแล้ว','พัสดุกำลังนำส่ง',18.79,98.975,NOW() - INTERVAL 22 HOUR);

INSERT INTO orders(user_id,name,phone,addr,lat,lng,total,pm,status,slip,carrier,tracking_no,created) VALUES(@u_pending,'ธนากร รอตรวจสอบ','0862223344','120 หมู่ 3 ถนนมิตรภาพ ต.ในเมือง อ.เมืองขอนแก่น จ.ขอนแก่น 40000',16.4419,102.836,490,'pp','แจ้งชำระแล้ว','demo_slip.svg',NULL,NULL,NOW() - INTERVAL 24 HOUR);
SET @o = LAST_INSERT_ID();
INSERT INTO order_items(order_id,product_id,name,price,qty) VALUES
(@o,(SELECT id FROM products WHERE name='ช่อทิวลิป' LIMIT 1),'ช่อทิวลิป',490,1);
INSERT INTO order_events(order_id,status,note,lat,lng,created) VALUES
(@o,'รอชำระเงิน','สร้างออเดอร์',NULL,NULL,NOW() - INTERVAL 24 HOUR),
(@o,'แจ้งชำระแล้ว','ลูกค้าแนบสลิป รอตรวจสอบ',NULL,NULL,NOW() - INTERVAL 23 HOUR);

INSERT INTO orders(user_id,name,phone,addr,lat,lng,total,pm,status,slip,carrier,tracking_no,created) VALUES(@u_rejected,'มานี ไม่ผ่านการตรวจ','0873334455','8 ซอยลาดพร้าว 71 ต.ลาดพร้าว อ.ลาดพร้าว จ.กรุงเทพมหานคร 10230',13.8036,100.6086,180,'pp','รอชำระเงิน',NULL,NULL,NULL,NOW() - INTERVAL 48 HOUR);
SET @o = LAST_INSERT_ID();
INSERT INTO order_items(order_id,product_id,name,price,qty) VALUES
(@o,(SELECT id FROM products WHERE name='ดินปลูกพร้อมใช้' LIMIT 1),'ดินปลูกพร้อมใช้',60,3);
INSERT INTO order_events(order_id,status,note,lat,lng,created) VALUES
(@o,'รอชำระเงิน','สร้างออเดอร์',NULL,NULL,NOW() - INTERVAL 48 HOUR);

INSERT INTO orders(user_id,name,phone,addr,lat,lng,total,pm,status,slip,carrier,tracking_no,created) VALUES(@u_shipping,'วิชัย ส่งของภูเก็ต','0815556677','77/7 ถนนเทพกระษัตรี ต.ตลาดใหญ่ อ.เมืองภูเก็ต จ.ภูเก็ต 83000',7.8804,98.3923,390,'bank','เตรียมสินค้า','demo_slip.svg',NULL,NULL,NOW() - INTERVAL 48 HOUR);
SET @o = LAST_INSERT_ID();
INSERT INTO order_items(order_id,product_id,name,price,qty) VALUES
(@o,(SELECT id FROM products WHERE name='ช่อลาเวนเดอร์' LIMIT 1),'ช่อลาเวนเดอร์',390,1);
INSERT INTO order_events(order_id,status,note,lat,lng,created) VALUES
(@o,'รอชำระเงิน','สร้างออเดอร์',NULL,NULL,NOW() - INTERVAL 48 HOUR),
(@o,'แจ้งชำระแล้ว','ลูกค้าแนบสลิป',NULL,NULL,NOW() - INTERVAL 46 HOUR),
(@o,'ชำระแล้ว','ตรวจสลิปแล้ว',NULL,NULL,NOW() - INTERVAL 43 HOUR),
(@o,'เตรียมสินค้า','กำลังแพ็กสินค้า',13.7563,100.5018,NOW() - INTERVAL 18 HOUR);

INSERT INTO orders(user_id,name,phone,addr,lat,lng,total,pm,status,slip,carrier,tracking_no,created) VALUES(@u_shipping,'วิชัย ส่งของภูเก็ต','0815556677','77/7 ถนนเทพกระษัตรี ต.ตลาดใหญ่ อ.เมืองภูเก็ต จ.ภูเก็ต 83000',7.8804,98.3923,700,'pp','จัดส่งแล้ว','demo_slip.svg','Thailand Post','EX1234567TH',NOW() - INTERVAL 144 HOUR);
SET @o = LAST_INSERT_ID();
INSERT INTO order_items(order_id,product_id,name,price,qty) VALUES
(@o,(SELECT id FROM products WHERE name='มอนสเตอร่า' LIMIT 1),'มอนสเตอร่า',350,2);
INSERT INTO order_events(order_id,status,note,lat,lng,created) VALUES
(@o,'รอชำระเงิน','สร้างออเดอร์',NULL,NULL,NOW() - INTERVAL 144 HOUR),
(@o,'ชำระแล้ว','ตรวจสลิปแล้ว',NULL,NULL,NOW() - INTERVAL 140 HOUR),
(@o,'เตรียมสินค้า','แพ็กสินค้าเรียบร้อย',13.7563,100.5018,NOW() - INTERVAL 124 HOUR),
(@o,'จัดส่งแล้ว','ส่งมอบให้ขนส่ง',13.7563,100.5018,NOW() - INTERVAL 114 HOUR),
(@o,'จัดส่งแล้ว','ถึงศูนย์คัดแยกกรุงเทพฯ',13.82,100.55,NOW() - INTERVAL 94 HOUR),
(@o,'จัดส่งแล้ว','ถึงศูนย์คัดแยกสุราษฎร์ธานี',9.14,99.33,NOW() - INTERVAL 44 HOUR),
(@o,'จัดส่งแล้ว','ถึงศูนย์กระจายสินค้าภูเก็ต',8.04,98.37,NOW() - INTERVAL 19 HOUR);

INSERT INTO orders(user_id,name,phone,addr,lat,lng,total,pm,status,slip,carrier,tracking_no,created) VALUES(@u_cancel,'ปราณี ยกเลิกออเดอร์','0826667788','33 ถนนนิพัทธ์อุทิศ 3 ต.หาดใหญ่ อ.หาดใหญ่ จ.สงขลา 90110',7.0086,100.4747,360,'pp','ยกเลิก',NULL,NULL,NULL,NOW() - INTERVAL 240 HOUR);
SET @o = LAST_INSERT_ID();
SET @o_cancel = @o;
INSERT INTO order_items(order_id,product_id,name,price,qty) VALUES
(@o,(SELECT id FROM products WHERE name='กระบองเพชร' LIMIT 1),'กระบองเพชร',90,4);
INSERT INTO order_events(order_id,status,note,lat,lng,created) VALUES
(@o,'รอชำระเงิน','สร้างออเดอร์',NULL,NULL,NOW() - INTERVAL 240 HOUR),
(@o,'ยกเลิก','ลูกค้าขอยกเลิกออเดอร์',NULL,NULL,NOW() - INTERVAL 234 HOUR);

INSERT INTO orders(user_id,name,phone,addr,lat,lng,total,pm,status,slip,carrier,tracking_no,created) VALUES(@u_chat,'ชัย ถามแชท','0837778899','9 ถนนช้างคลาน ต.ช้างคลาน อ.เมืองเชียงใหม่ จ.เชียงใหม่ 50100',18.7807,98.9996,180,'pp','ได้รับสินค้าแล้ว','demo_slip.svg','Kerry Express','KE5566778899',NOW() - INTERVAL 288 HOUR);
SET @o = LAST_INSERT_ID();
SET @o_chat = @o;
INSERT INTO order_items(order_id,product_id,name,price,qty) VALUES
(@o,(SELECT id FROM products WHERE name='กระบองเพชร' LIMIT 1),'กระบองเพชร',90,2);
INSERT INTO order_events(order_id,status,note,lat,lng,created) VALUES
(@o,'รอชำระเงิน','สร้างออเดอร์',NULL,NULL,NOW() - INTERVAL 288 HOUR),
(@o,'ชำระแล้ว','ตรวจสลิปแล้ว',NULL,NULL,NOW() - INTERVAL 285 HOUR),
(@o,'เตรียมสินค้า','แพ็กสินค้าเรียบร้อย',13.7563,100.5018,NOW() - INTERVAL 268 HOUR),
(@o,'จัดส่งแล้ว','ส่งมอบให้ขนส่ง',13.7563,100.5018,NOW() - INTERVAL 262 HOUR),
(@o,'ได้รับสินค้าแล้ว','ลูกค้ายืนยันรับสินค้า',NULL,NULL,NOW() - INTERVAL 218 HOUR);

INSERT INTO orders(user_id,name,phone,addr,lat,lng,total,pm,status,slip,carrier,tracking_no,created) VALUES(@u_vip,'เศรษฐี ลูกค้าประจำ','0859990011','88 ถนนสุขุมวิท 39 ต.คลองตันเหนือ อ.วัฒนา จ.กรุงเทพมหานคร 10110',13.7367,100.57,710,'pp','ได้รับสินค้าแล้ว','demo_slip.svg','Kerry Express','KE7000000150',NOW() - INTERVAL 3600 HOUR);
SET @o = LAST_INSERT_ID();
INSERT INTO order_items(order_id,product_id,name,price,qty) VALUES
(@o,(SELECT id FROM products WHERE name='มอนสเตอร่า' LIMIT 1),'มอนสเตอร่า',350,1),
(@o,(SELECT id FROM products WHERE name='กระถางเซรามิก' LIMIT 1),'กระถางเซรามิก',180,2);
INSERT INTO order_events(order_id,status,note,lat,lng,created) VALUES
(@o,'รอชำระเงิน','สร้างออเดอร์',NULL,NULL,NOW() - INTERVAL 3600 HOUR),
(@o,'ชำระแล้ว','ตรวจสลิปแล้ว',NULL,NULL,NOW() - INTERVAL 3597 HOUR),
(@o,'เตรียมสินค้า','แพ็กสินค้าเรียบร้อย',13.7563,100.5018,NOW() - INTERVAL 3580 HOUR),
(@o,'จัดส่งแล้ว','ส่งมอบให้ขนส่ง',13.7563,100.5018,NOW() - INTERVAL 3574 HOUR),
(@o,'ได้รับสินค้าแล้ว','ลูกค้ายืนยันรับสินค้า',NULL,NULL,NOW() - INTERVAL 3550 HOUR);

INSERT INTO orders(user_id,name,phone,addr,lat,lng,total,pm,status,slip,carrier,tracking_no,created) VALUES(@u_vip,'เศรษฐี ลูกค้าประจำ','0859990011','88 ถนนสุขุมวิท 39 ต.คลองตันเหนือ อ.วัฒนา จ.กรุงเทพมหานคร 10110',13.7367,100.57,980,'pp','ได้รับสินค้าแล้ว','demo_slip.svg','Kerry Express','KE7000000120',NOW() - INTERVAL 2880 HOUR);
SET @o = LAST_INSERT_ID();
INSERT INTO order_items(order_id,product_id,name,price,qty) VALUES
(@o,(SELECT id FROM products WHERE name='ช่อทิวลิป' LIMIT 1),'ช่อทิวลิป',490,2);
INSERT INTO order_events(order_id,status,note,lat,lng,created) VALUES
(@o,'รอชำระเงิน','สร้างออเดอร์',NULL,NULL,NOW() - INTERVAL 2880 HOUR),
(@o,'ชำระแล้ว','ตรวจสลิปแล้ว',NULL,NULL,NOW() - INTERVAL 2877 HOUR),
(@o,'เตรียมสินค้า','แพ็กสินค้าเรียบร้อย',13.7563,100.5018,NOW() - INTERVAL 2860 HOUR),
(@o,'จัดส่งแล้ว','ส่งมอบให้ขนส่ง',13.7563,100.5018,NOW() - INTERVAL 2854 HOUR),
(@o,'ได้รับสินค้าแล้ว','ลูกค้ายืนยันรับสินค้า',NULL,NULL,NOW() - INTERVAL 2830 HOUR);

INSERT INTO orders(user_id,name,phone,addr,lat,lng,total,pm,status,slip,carrier,tracking_no,created) VALUES(@u_vip,'เศรษฐี ลูกค้าประจำ','0859990011','88 ถนนสุขุมวิท 39 ต.คลองตันเหนือ อ.วัฒนา จ.กรุงเทพมหานคร 10110',13.7367,100.57,570,'pp','ได้รับสินค้าแล้ว','demo_slip.svg','Kerry Express','KE7000000060',NOW() - INTERVAL 1440 HOUR);
SET @o = LAST_INSERT_ID();
INSERT INTO order_items(order_id,product_id,name,price,qty) VALUES
(@o,(SELECT id FROM products WHERE name='กุหลาบพันธุ์เล็ก' LIMIT 1),'กุหลาบพันธุ์เล็ก',150,3),
(@o,(SELECT id FROM products WHERE name='ดินปลูกพร้อมใช้' LIMIT 1),'ดินปลูกพร้อมใช้',60,2);
INSERT INTO order_events(order_id,status,note,lat,lng,created) VALUES
(@o,'รอชำระเงิน','สร้างออเดอร์',NULL,NULL,NOW() - INTERVAL 1440 HOUR),
(@o,'ชำระแล้ว','ตรวจสลิปแล้ว',NULL,NULL,NOW() - INTERVAL 1437 HOUR),
(@o,'เตรียมสินค้า','แพ็กสินค้าเรียบร้อย',13.7563,100.5018,NOW() - INTERVAL 1420 HOUR),
(@o,'จัดส่งแล้ว','ส่งมอบให้ขนส่ง',13.7563,100.5018,NOW() - INTERVAL 1414 HOUR),
(@o,'ได้รับสินค้าแล้ว','ลูกค้ายืนยันรับสินค้า',NULL,NULL,NOW() - INTERVAL 1390 HOUR);

INSERT INTO orders(user_id,name,phone,addr,lat,lng,total,pm,status,slip,carrier,tracking_no,created) VALUES(@u_dsr,'ดวงใจ ใช้สิทธิ PDPA','0848889900','5 ซอยอารีย์ 2 ต.สามเสนใน อ.พญาไท จ.กรุงเทพมหานคร 10400',13.7797,100.5446,390,'cod','ได้รับสินค้าแล้ว',NULL,NULL,NULL,NOW() - INTERVAL 1920 HOUR);
SET @o = LAST_INSERT_ID();
INSERT INTO order_items(order_id,product_id,name,price,qty) VALUES
(@o,(SELECT id FROM products WHERE name='ช่อลาเวนเดอร์' LIMIT 1),'ช่อลาเวนเดอร์',390,1);
INSERT INTO order_events(order_id,status,note,lat,lng,created) VALUES
(@o,'เก็บเงินปลายทาง','สร้างออเดอร์',NULL,NULL,NOW() - INTERVAL 1920 HOUR),
(@o,'จัดส่งแล้ว','ส่งมอบให้ขนส่ง',13.7563,100.5018,NOW() - INTERVAL 1890 HOUR),
(@o,'ได้รับสินค้าแล้ว','ลูกค้ารับสินค้าและชำระเงินปลายทาง',NULL,NULL,NOW() - INTERVAL 1860 HOUR);

INSERT INTO orders(user_id,name,phone,addr,lat,lng,total,pm,status,slip,carrier,tracking_no,created) VALUES(@u_deleted,'ศิริพร ใจงาม','0800001111','1 ถนนตัวอย่าง กรุงเทพมหานคร 10000',13.75,100.5,490,'pp','ได้รับสินค้าแล้ว','demo_slip.svg',NULL,NULL,NOW() - INTERVAL 60000 HOUR);
SET @o = LAST_INSERT_ID();
INSERT INTO order_items(order_id,product_id,name,price,qty) VALUES
(@o,(SELECT id FROM products WHERE name='ช่อทิวลิป' LIMIT 1),'ช่อทิวลิป',490,1);
INSERT INTO order_events(order_id,status,note,lat,lng,created) VALUES
(@o,'รอชำระเงิน','สร้างออเดอร์',NULL,NULL,NOW() - INTERVAL 60000 HOUR),
(@o,'ได้รับสินค้าแล้ว','ส่งสำเร็จ',NULL,NULL,NOW() - INTERVAL 59928 HOUR);

INSERT INTO reviews(product_id,user_id,rating,body,created) VALUES((SELECT id FROM products WHERE name='มอนสเตอร่า' LIMIT 1),@u_verified,5,'ต้นสวยมาก แพ็กมาดี ใบไม่ช้ำเลย ส่งไวด้วยค่ะ',NOW() - INTERVAL 648 HOUR);
INSERT INTO reviews(product_id,user_id,rating,body,created) VALUES((SELECT id FROM products WHERE name='มอนสเตอร่า' LIMIT 1),@u_vip,5,'ซื้อซ้ำเป็นรอบที่สามแล้ว คุณภาพสม่ำเสมอ',NOW() - INTERVAL 3360 HOUR);
INSERT INTO reviews(product_id,user_id,rating,body,created) VALUES((SELECT id FROM products WHERE name='ช่อทิวลิป' LIMIT 1),@u_vip,5,'ช่อสวยสดมาก ใช้เป็นของขวัญคนรับชอบมาก',NOW() - INTERVAL 2640 HOUR);
INSERT INTO reviews(product_id,user_id,rating,body,created) VALUES((SELECT id FROM products WHERE name='กุหลาบพันธุ์เล็ก' LIMIT 1),@u_vip,4,'กุหลาบสีสวย ดอกบานเยอะ แต่กระถางเล็กไปนิดนึง',NOW() - INTERVAL 1200 HOUR);
INSERT INTO reviews(product_id,user_id,rating,body,created) VALUES((SELECT id FROM products WHERE name='กระบองเพชร' LIMIT 1),@u_chat,4,'น่ารักดี เหมาะวางโต๊ะทำงาน ส่งช้านิดหน่อย',NOW() - INTERVAL 216 HOUR);

INSERT INTO messages(user_id,sender,body,is_read,created) VALUES(@u_chat,'c','สวัสดีครับ มีกระบองเพชรพันธุ์เล็กสำหรับวางโต๊ะทำงานไหมครับ',1,NOW() - INTERVAL 50 HOUR);
INSERT INTO messages(user_id,sender,body,is_read,created) VALUES(@u_chat,'s','สวัสดีค่ะ มีค่ะ ดูได้ที่หมวดไม้ประดับ → กระบองเพชรและไม้อวบน้ำ ราคาเริ่มต้น 90 บาทค่ะ',1,NOW() - INTERVAL 49 HOUR);
INSERT INTO messages(user_id,sender,body,is_read,created) VALUES(@u_chat,'c','สวัสดีครับ ต้นมอนสเตอร่าต้องรดน้ำบ่อยแค่ไหนครับ',0,NOW() - INTERVAL 3 HOUR);
INSERT INTO messages(user_id,sender,body,is_read,created) VALUES(@u_chat,'c','ถ้าสั่งวันนี้จะได้รับสินค้าวันไหนครับ',0,NOW() - INTERVAL 2 HOUR);
INSERT INTO messages(user_id,sender,body,is_read,created) VALUES(@u_verified,'c','สอบถามเรื่องการห่อของขวัญค่ะ',1,NOW() - INTERVAL 300 HOUR);
INSERT INTO messages(user_id,sender,body,is_read,created) VALUES(@u_verified,'s','ห่อได้ค่ะ เลือกหมวดช่อดอกไม้และของขวัญ พร้อมการ์ดอวยพรได้เลยค่ะ',1,NOW() - INTERVAL 299 HOUR);

INSERT INTO reports(user_id,type,target_id,subject,body,status,admin_reply,user_seen,created,updated) VALUES(@u_chat,'product',(SELECT id FROM products WHERE name='มอนสเตอร่า' LIMIT 1),'ใบมอนสเตอร่ามีจุดเหลือง','ได้รับต้นมาแล้วพบจุดเหลืองที่ใบ 2 ใบ อยากสอบถามว่าปกติหรือไม่','new',NULL,1,NOW() - INTERVAL 5 HOUR,NULL);
INSERT INTO reports(user_id,type,target_id,subject,body,status,admin_reply,user_seen,created,updated) VALUES(@u_chat,'order',@o_chat,'พัสดุมาช้ากว่ากำหนด','ติดตามพัสดุแล้วสถานะค้างที่ศูนย์คัดแยก 2 วัน','working','ทางร้านกำลังตรวจสอบกับขนส่ง จะแจ้งผลภายในพรุ่งนี้ค่ะ',0,NOW() - INTERVAL 40 HOUR,NOW() - INTERVAL 20 HOUR);
INSERT INTO reports(user_id,type,target_id,subject,body,status,admin_reply,user_seen,created,updated) VALUES(@u_cancel,'order',@o_cancel,'ขอยกเลิกออเดอร์','สั่งผิดจำนวน ขอยกเลิกค่ะ','resolved','ยกเลิกออเดอร์ให้เรียบร้อยแล้วค่ะ ขอบคุณที่แจ้งนะคะ',1,NOW() - INTERVAL 250 HOUR,NOW() - INTERVAL 240 HOUR);
INSERT INTO reports(user_id,type,target_id,subject,body,status,admin_reply,user_seen,created,updated) VALUES(@u_verified,'website',NULL,'ปุ่มค้นหาบนมือถือกดไม่ติด','เปิดจากมือถือ ช่องค้นหาด้านบนกดแล้วไม่มีอะไรเกิดขึ้น','resolved','แก้ไขแล้วค่ะ ลองรีเฟรชหน้าเว็บอีกครั้ง ขออภัยในความไม่สะดวก',0,NOW() - INTERVAL 120 HOUR,NOW() - INTERVAL 100 HOUR);
INSERT INTO reports(user_id,type,target_id,subject,body,status,admin_reply,user_seen,created,updated) VALUES(@u_rejected,'review',NULL,'รีวิวของสินค้าชิ้นหนึ่งไม่เหมาะสม','รู้สึกว่ารีวิวนี้ไม่น่าเชื่อถือ อยากให้ตรวจสอบ','rejected','ตรวจสอบแล้วไม่พบเนื้อหาที่ฝ่าฝืนเงื่อนไขการใช้งาน ทางร้านจึงยังไม่ลบรีวิวนี้ค่ะ',0,NOW() - INTERVAL 70 HOUR,NOW() - INTERVAL 60 HOUR);

INSERT INTO consents(user_id,purpose,version,granted,created) VALUES(@u_verified,'policy','1.0',1,NOW() - INTERVAL 2160 HOUR);
INSERT INTO consents(user_id,purpose,version,granted,created) VALUES(@u_pending,'policy','1.0',1,NOW() - INTERVAL 480 HOUR);
INSERT INTO consents(user_id,purpose,version,granted,created) VALUES(@u_rejected,'policy','1.0',1,NOW() - INTERVAL 840 HOUR);
INSERT INTO consents(user_id,purpose,version,granted,created) VALUES(@u_shipping,'policy','1.0',1,NOW() - INTERVAL 1440 HOUR);
INSERT INTO consents(user_id,purpose,version,granted,created) VALUES(@u_cancel,'policy','1.0',1,NOW() - INTERVAL 1080 HOUR);
INSERT INTO consents(user_id,purpose,version,granted,created) VALUES(@u_chat,'policy','1.0',1,NOW() - INTERVAL 360 HOUR);
INSERT INTO consents(user_id,purpose,version,granted,created) VALUES(@u_dsr,'policy','1.0',1,NOW() - INTERVAL 1680 HOUR);
INSERT INTO consents(user_id,purpose,version,granted,created) VALUES(@u_vip,'policy','1.0',1,NOW() - INTERVAL 4800 HOUR);
INSERT INTO consents(user_id,purpose,version,granted,created) VALUES(@u_verified,'marketing','1.0',1,NOW() - INTERVAL 2160 HOUR);
INSERT INTO consents(user_id,purpose,version,granted,created) VALUES(@u_verified,'cookies','1.0',1,NOW() - INTERVAL 2160 HOUR);
INSERT INTO consents(user_id,purpose,version,granted,created) VALUES(@u_shipping,'marketing','1.0',1,NOW() - INTERVAL 1440 HOUR);
INSERT INTO consents(user_id,purpose,version,granted,created) VALUES(@u_vip,'marketing','1.0',1,NOW() - INTERVAL 4800 HOUR);
INSERT INTO consents(user_id,purpose,version,granted,created) VALUES(@u_dsr,'marketing','1.0',1,NOW() - INTERVAL 1680 HOUR);
INSERT INTO consents(user_id,purpose,version,granted,created) VALUES(@u_dsr,'cookies','1.0',0,NOW() - INTERVAL 1680 HOUR);
INSERT INTO consents(user_id,purpose,version,granted,created) VALUES(@u_dsr,'marketing','1.0',0,NOW() - INTERVAL 240 HOUR);
INSERT INTO dsr_requests(user_id,type,detail,status,admin_note,created,updated) VALUES(@u_dsr,'erase','ขอลบข้อมูลส่วนบุคคลทั้งหมดของฉัน เนื่องจากไม่ได้ใช้บริการแล้ว','new',NULL,NOW() - INTERVAL 960 HOUR,NULL);
INSERT INTO dsr_requests(user_id,type,detail,status,admin_note,created,updated) VALUES(@u_dsr,'correct','ขอแก้ไขเบอร์โทรในออเดอร์เก่าให้เป็นเบอร์ใหม่','working','กำลังตรวจสอบและแก้ไขข้อมูลในออเดอร์',NOW() - INTERVAL 144 HOUR,NULL);
INSERT INTO dsr_requests(user_id,type,detail,status,admin_note,created,updated) VALUES(@u_dsr,'object','ขอคัดค้านการเก็บข้อมูลออเดอร์ของฉัน','rejected','ไม่สามารถยุติการเก็บข้อมูลออเดอร์ได้ เนื่องจากเป็นเอกสารทางบัญชีที่กฎหมายกำหนดให้เก็บรักษา จะลบหรือทำให้ไม่ระบุตัวตนเมื่อครบกำหนด',NOW() - INTERVAL 600 HOUR,NOW() - INTERVAL 570 HOUR);
INSERT INTO dsr_requests(user_id,type,detail,status,admin_note,created,updated) VALUES(@u_dsr,'withdraw','ขอถอนความยินยอมรับข่าวสารโปรโมชั่น','done','ถอนความยินยอมเรียบร้อย ร้านจะไม่ส่งข่าวสารอีก',NOW() - INTERVAL 240 HOUR,NOW() - INTERVAL 210 HOUR);
INSERT INTO dsr_requests(user_id,type,detail,status,admin_note,created,updated) VALUES(@u_pending,'restrict','ขอระงับการใช้ข้อมูลที่อยู่ชั่วคราวระหว่างตรวจสอบความถูกต้อง','new',NULL,NOW() - INTERVAL 48 HOUR,NULL);
INSERT INTO dsr_requests(user_id,type,detail,status,admin_note,created,updated) VALUES(@u_deleted,'erase','ลบบัญชีด้วยตนเอง','done','ระบบลบข้อมูลส่วนบุคคลและทำให้ไม่ระบุตัวตนแล้ว',NOW() - INTERVAL 120 HOUR,NOW() - INTERVAL 90 HOUR);
INSERT INTO breach_log(discovered_at,description,affected,notified_pdpc,notified_users,status,created) VALUES
(NOW() - INTERVAL 20 HOUR,'[ตัวอย่าง] อีเมลสรุปรายการสั่งซื้อถูกส่งไปผิดผู้รับ 1 ฉบับ มีชื่อและที่อยู่จัดส่งของลูกค้า 1 ราย ร้านเรียกคืนข้อความและติดต่อผู้รับให้ลบแล้ว กำลังประเมินความเสี่ยง',1,NULL,0,'open',NOW() - INTERVAL 20 HOUR),
(NOW() - INTERVAL 15 DAY,'[ตัวอย่าง] พบความพยายามเดารหัสผ่านบัญชีแอดมินหลายครั้ง ไม่พบการเข้าถึงสำเร็จ ร้านเปลี่ยนรหัสผ่านและเปิดใช้การจำกัดการล็อกอิน',0,NOW() - INTERVAL 14 DAY,1,'closed',NOW() - INTERVAL 15 DAY);
SET @admin = (SELECT id FROM users WHERE email='admin@shop.test' LIMIT 1);
INSERT INTO audit_log(admin_id,action,target,created) VALUES
(@admin,'view_private_doc','demo-id-card.jpg',NOW() - INTERVAL 30 HOUR),
(@admin,'verify_review','demo-user#pending',NOW() - INTERVAL 29 HOUR),
(@admin,'dsr_update','demo-#working',NOW() - INTERVAL 5 HOUR),
(@admin,'retention_run','demo-chat=0 orders=0',NOW() - INTERVAL 3 DAY);
INSERT INTO settings(k,v) VALUES('demo_mode','1') ON DUPLICATE KEY UPDATE v='1';
