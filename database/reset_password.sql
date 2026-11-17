-- reset_password.sql — รีเซ็ตรหัสผ่านบัญชีทดลองกลับเป็นค่าเริ่มต้น
-- วิธีใช้: phpMyAdmin > เลือกฐานข้อมูล plantshop > แท็บ SQL > วางทั้งไฟล์ > Go
-- หลังรัน: admin@shop.test / Admin@1234   และ   customer@shop.test / Customer@1234
-- (ค่าในช่อง pass เป็นรหัสที่เข้ารหัส bcrypt แล้ว ห้ามพิมพ์รหัสผ่านธรรมดาลงช่องนี้ตรงๆ เพราะเว็บจะเทียบไม่ผ่าน)
USE `plantshop`;
-- อีเมลในฐานข้อมูลต้องเป็นตัวพิมพ์เล็กและไม่มีช่องว่าง (หน้าล็อกอินแปลงเป็นตัวพิมพ์เล็กก่อนค้นหา)
UPDATE users SET email=LOWER(TRIM(email));
UPDATE users SET pass='$2y$12$uscZkdU3mJ8rgSTlWKIcYeOtca3vmEdADo0epmIr8HoHMw8s3cubm', role='admin' WHERE email='admin@shop.test';
UPDATE users SET pass='$2y$12$xsTtKZbPxiETiSxZrMz96OqcCtchFbwnx0WJNjO4r8LO2kh2zdvXe' WHERE email='customer@shop.test';
-- ถ้าเผลอเปลี่ยนอีเมลแอดมินด้วย ให้ใช้แบบนี้แทน (id 1 = แอดมินคนแรก)
-- UPDATE users SET pass='$2y$12$uscZkdU3mJ8rgSTlWKIcYeOtca3vmEdADo0epmIr8HoHMw8s3cubm', role='admin' WHERE id=1;
