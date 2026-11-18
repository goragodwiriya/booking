-- ---------------------------------------------------------------------------
-- modules/booking/install/database.sql — ตารางที่โมดูล booking เป็นเจ้าของ
--
-- **ประกาศที่นี่ที่เดียว** ห้ามประกาศซ้ำใน install/database.sql ของโปรเจ็ค
-- ประกาศสองที่ = ติดตั้งใหม่ล้มด้วย "Table already exists" และนิยามสองชุด
-- จะค่อย ๆ ต่างกันจนไซต์ที่อัปเกรดคนละเส้นทางได้สคีมาไม่เหมือนกัน
--
-- ทั้งการติดตั้งใหม่ (common.php::schemaFiles) และการปรับรุ่น (ensureTable)
-- อ่านนิยามจากไฟล์นี้ไฟล์เดียว
-- ---------------------------------------------------------------------------

-- ---------------------------------------------------------------------------
-- rooms — ห้องประชุม
-- ---------------------------------------------------------------------------
CREATE TABLE `{prefix}_rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `detail` text NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- rooms_meta — คุณสมบัติของห้องแบบ key/value (เลขห้อง จำนวนที่นั่ง อาคาร)
--
-- ทุก query ที่ดึงคุณสมบัติของห้อง join ด้วย (room_id, name) เสมอ
-- (Booking\Catalog\Model, Booking\Booking\Model, Booking\Approvals\Model)
-- index จึงต้องคลุมสองคอลัมน์ ไม่ใช่ room_id ตัวเดียว
-- ---------------------------------------------------------------------------
CREATE TABLE `{prefix}_rooms_meta` (
  `room_id` int(11) NOT NULL,
  `name` varchar(20) NOT NULL,
  `value` varchar(150) NOT NULL,
  KEY `idx_room_meta` (`room_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- reservation — ใบจองห้องประชุม
--
-- idx_room_availability คือ index ที่ใช้ตอบคำถาม "ห้องนี้ช่วงเวลานี้ว่างไหม"
-- ซึ่งเป็นคำถามที่ระบบถามบ่อยที่สุด เรียงคอลัมน์ตามลำดับที่ WHERE ใช้จริง
-- ---------------------------------------------------------------------------
CREATE TABLE `{prefix}_reservation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `topic` varchar(150) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `attendees` int(11) NOT NULL,
  `begin` datetime DEFAULT NULL,
  `end` datetime DEFAULT NULL,
  `schedule_type` varchar(20) NOT NULL DEFAULT 'daily-slot',
  `status` tinyint(1) NOT NULL,
  `reason` varchar(128) DEFAULT NULL,
  `approve` tinyint(1) NOT NULL,
  `closed` tinyint(1) NOT NULL,
  `department` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_room_availability` (`room_id`,`status`,`approve`,`begin`,`end`),
  KEY `member_id` (`member_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- reservation_data — ข้อมูลเพิ่มเติมของใบจองแบบ key/value
-- ---------------------------------------------------------------------------
CREATE TABLE `{prefix}_reservation_data` (
  `reservation_id` int(11) NOT NULL,
  `name` varchar(20) NOT NULL,
  `value` varchar(150) NOT NULL,
  KEY `idx_reservation_data` (`reservation_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- ข้อมูลตั้งต้นของโมดูลนี้
--
-- ⚠️ ต้องอยู่ที่นี่ ไม่ใช่ใน install/database.sql ของโปรเจ็ค เพราะ schemaFiles()
-- รันไฟล์ของโปรเจ็ค **ก่อน** ไฟล์ของโมดูล — INSERT ที่อยู่ฝั่งโปรเจ็คจะวิ่งไปหา
-- ตารางที่ยังไม่ถูกสร้าง แล้วการติดตั้งใหม่ล้มทันที
-- ---------------------------------------------------------------------------

INSERT INTO `{prefix}_rooms` (`id`, `name`, `detail`, `color`, `is_active`) VALUES
(1, 'ห้องประชุม 2', 'ห้องประชุมพร้อมระบบ Video conference\r\nที่นั่งผู้เข้าร่วมประชุม รูปตัว U 2 แถว', '#01579B', 1),
(2, 'ห้องประชุม 1', 'ห้องประชุมขนาดใหญ่\r\nพร้อมสิ่งอำนวยความสะดวกครบครัน', '#1A237E', 1),
(3, 'ห้องประชุมส่วนเทคโนโลยีสารสนเทศ', 'ห้องประชุมขนาดใหญ่ (Hall)\r\nเหมาะสำรับการสัมนาเป็นหมู่คณะ และ จัดเลี้ยง', '#B71C1C', 1);

INSERT INTO `{prefix}_rooms_meta` (`room_id`, `name`, `value`) VALUES
(2, 'seats', '20 ที่นั่ง'),
(2, 'number', 'R-0001'),
(2, 'building', 'อาคาร 1'),
(1, 'seats', '50 ที่นั่ง รูปตัว U'),
(1, 'number', 'R-0002'),
(1, 'building', 'อาคาร 2'),
(3, 'building', 'โรงอาหาร'),
(3, 'seats', '100 คน');
