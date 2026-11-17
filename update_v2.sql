-- update_v2.sql — อัปเดตฐานข้อมูลเดิม (ที่เคยนำเข้า database.sql รุ่นแรกไปแล้ว) โดยไม่ลบข้อมูล
-- เพิ่ม: คอลัมน์รายละเอียดสินค้า + ตารางรีวิว   ** รันครั้งเดียวพอ **
SET NAMES utf8mb4;
USE `plantshop`;
ALTER TABLE products ADD COLUMN detail TEXT NULL AFTER descr;
CREATE TABLE IF NOT EXISTS reviews(            -- รีวิวสินค้า (แนบรูปได้ 1 รูป)
  id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_id INT NOT NULL,
  rating TINYINT NOT NULL, body TEXT, img VARCHAR(150) NULL,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY(product_id));

UPDATE products SET detail='ไม้ใบยอดนิยมสำหรับตกแต่งบ้านและออฟฟิศ ชอบแสงรำไร ไม่ควรโดนแดดจัดโดยตรง รดน้ำเมื่อผิวดินแห้ง และอย่าให้น้ำขัง ขนาดต้นประมาณ 30-40 ซม.' WHERE name='มอนสเตอร่า';
UPDATE products SET detail='ไม้อวบน้ำทนแล้ง วางริมหน้าต่างที่ได้รับแสงเยอะ รดน้ำน้อย สัปดาห์ละครั้งหรือน้อยกว่า เหมาะวางโต๊ะทำงานและเป็นของขวัญ' WHERE name='กระบองเพชร';
UPDATE products SET detail='กุหลาบพุ่มเล็กดอกสีแดงสด ชอบแดดเช้า รดน้ำสม่ำเสมอแต่ไม่แฉะ ตัดดอกที่เหี่ยวออกเพื่อให้ออกดอกต่อเนื่อง' WHERE name='กุหลาบพันธุ์เล็ก';
UPDATE products SET detail='ทานตะวันพันธุ์เตี้ยปลูกในกระถางได้ ต้องการแดดจัดอย่างน้อยวันละ 6 ชั่วโมง รดน้ำเช้าเย็นเมื่อดินเริ่มแห้ง' WHERE name='ทานตะวันแคระ';
UPDATE products SET detail='ช่อดอกไม้สดจัดพร้อมห่อของขวัญ เหมาะสำหรับวันเกิดและโอกาสพิเศษ เปลี่ยนน้ำทุกวันและตัดปลายก้านเล็กน้อยเพื่อให้บานทนขึ้น' WHERE name='ช่อทิวลิป';
UPDATE products SET detail='ช่อลาเวนเดอร์กลิ่นหอมผ่อนคลาย วางในที่ร่มระบายอากาศดี สามารถตากแห้งเก็บไว้เป็นของตกแต่งได้' WHERE name='ช่อลาเวนเดอร์';
UPDATE products SET detail='กระถางเซรามิกขนาด 6 นิ้ว มีรูระบายน้ำที่ก้น เหมาะกับไม้ประดับและกระบองเพชรขนาดเล็ก' WHERE name='กระถางเซรามิก';
UPDATE products SET detail='ดินปลูกผสมพร้อมใช้ ระบายน้ำดี ถุงละ 5 ลิตร ใช้ได้กับไม้ประดับและไม้ดอกทั่วไป' WHERE name='ดินปลูกพร้อมใช้';

INSERT INTO reviews(product_id,user_id,rating,body)
SELECT 1,id,5,'ต้นสวยมาก ส่งไวและแพ็กมาดี' FROM users WHERE email='customer@shop.test';
