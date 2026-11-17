<?php
/**
 * install.php — ตัวติดตั้งอัตโนมัติ (ทางเลือกแทนการ import database.sql ใน phpMyAdmin)
 * วิธีใช้: เปิด http://localhost/plantshop/install.php ครั้งเดียว แล้ว "ลบไฟล์นี้ทิ้ง"
 * ทำอะไร: อ่านไฟล์ database.sql แล้วรันทีละคำสั่ง (โครงสร้างจึงตรงกับไฟล์ SQL เสมอ)
 * ถ้ามีข้อมูลสมาชิกอยู่แล้วจะไม่รัน เพื่อกันข้อมูลซ้ำ
 */
require 'config.php';

$pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// ถ้าฐานข้อมูลมีสมาชิกอยู่แล้ว = ติดตั้งไปแล้ว
$exists = $pdo->query("SHOW DATABASES LIKE '" . DB_NAME . "'")->fetch();
if ($exists) {
    $pdo->exec('USE `' . DB_NAME . '`');
    $hasUsers = $pdo->query("SHOW TABLES LIKE 'users'")->fetch() && $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    if ($hasUsers) {
        exit('<p>มีข้อมูลอยู่แล้ว ไม่ติดตั้งซ้ำ</p><p><a href="index.php">ไปหน้าร้าน</a></p><p>⚠️ ลบไฟล์ install.php ทิ้ง</p>');
    }
}

// อ่าน database.sql → ตัดบรรทัดคอมเมนต์ (--) → แยกคำสั่งด้วย ; → รันทีละคำสั่ง
$sql = file_get_contents(__DIR__ . '/database.sql');
$sql = preg_replace('/--[^\n]*/', '', $sql);          // ลบคอมเมนต์ท้ายบรรทัดและทั้งบรรทัด
foreach (explode(';', $sql) as $statement) {
    if (trim($statement) !== '') {
        $pdo->exec($statement);
    }
}
echo '<p>✅ ติดตั้งสำเร็จ</p><p><a href="index.php">ไปหน้าร้าน</a> · <a href="login.php">เข้าสู่ระบบ</a></p><p>⚠️ ลบไฟล์ install.php ทิ้งหลังติดตั้งเสร็จ</p>';
