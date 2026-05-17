-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 24, 2026 at 12:27 PM
-- Server version: 10.4.34-MariaDB
-- PHP Version: 7.2.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_category`
--

CREATE TABLE `{prefix}_category` (
  `type` varchar(20) NOT NULL,
  `category_id` varchar(10) DEFAULT '0',
  `language` varchar(2) DEFAULT '',
  `topic` varchar(150) NOT NULL,
  `color` varchar(16) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `{prefix}_category`
--

INSERT INTO `{prefix}_category` (`type`, `category_id`, `topic`, `color`, `is_active`) VALUES
('department', '1', 'บริหาร', NULL, 1),
('department', '2', 'จัดซื้อจัดจ้าง', NULL, 1),
('department', '3', 'บุคคล', NULL, 1),
('use', '1', 'ประชุม', '', 1),
('use', '2', 'สัมนา', '', 1),
('use', '3', 'จัดเลี้ยง', '', 1),
('accessories', '4', 'ของว่าง', '', 1),
('accessories', '3', 'เครื่องฉายแผ่นใส', '', 1),
('accessories', '2', 'จอโปรเจ็คเตอร์', '', 1),
('accessories', '1', 'เครื่องคอมพิวเตอร์', '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_language`
--

CREATE TABLE `{prefix}_language` (
  `id` int(11) NOT NULL,
  `key` text NOT NULL,
  `type` varchar(5) NOT NULL,
  `th` text DEFAULT NULL,
  `en` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_logs`
--

CREATE TABLE `{prefix}_logs` (
  `id` int(11) NOT NULL,
  `src_id` int(11) NOT NULL,
  `module` varchar(20) NOT NULL,
  `action` varchar(20) NOT NULL,
  `created_at` datetime NOT NULL,
  `reason` text DEFAULT NULL,
  `member_id` int(11) NOT NULL,
  `topic` text NOT NULL,
  `datas` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_reservation`
--

CREATE TABLE `{prefix}_reservation` (
  `id` int(11) NOT NULL,
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
  `department` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_reservation_data`
--

CREATE TABLE `{prefix}_reservation_data` (
  `reservation_id` int(11) NOT NULL,
  `name` varchar(20) NOT NULL,
  `value` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_rooms`
--

CREATE TABLE `{prefix}_rooms` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `detail` text NOT NULL,
  `color` varchar(20) NOT NULL,
  `is_active` int(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `{prefix}_rooms`
--

INSERT INTO `{prefix}_rooms` (`id`, `name`, `detail`, `color`, `is_active`) VALUES
(1, 'ห้องประชุม 2', 'ห้องประชุมพร้อมระบบ Video conference\r\nที่นั่งผู้เข้าร่วมประชุม รูปตัว U 2 แถว', '#01579B', 1),
(2, 'ห้องประชุม 1', 'ห้องประชุมขนาดใหญ่\r\nพร้อมสิ่งอำนวยความสะดวกครบครัน', '#1A237E', 1),
(3, 'ห้องประชุมส่วนเทคโนโลยีสารสนเทศ', 'ห้องประชุมขนาดใหญ่ (Hall)\r\nเหมาะสำรับการสัมนาเป็นหมู่คณะ และ จัดเลี้ยง', '#B71C1C', 1);

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_rooms_meta`
--

CREATE TABLE `{prefix}_rooms_meta` (
  `room_id` int(11) NOT NULL,
  `name` varchar(20) NOT NULL,
  `value` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `{prefix}_rooms_meta`
--

INSERT INTO `{prefix}_rooms_meta` (`room_id`, `name`, `value`) VALUES
(2, 'seats', '20 ที่นั่ง'),
(2, 'number', 'R-0001'),
(2, 'building', 'อาคาร 1'),
(1, 'seats', '50 ที่นั่ง รูปตัว U'),
(1, 'number', 'R-0002'),
(1, 'building', 'อาคาร 2'),
(3, 'building', 'โรงอาหาร'),
(3, 'seats', '100 คน');

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_user`
--

CREATE TABLE `{prefix}_user` (
  `id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `salt` varchar(32) NOT NULL DEFAULT '',
  `password` varchar(64) NOT NULL,
  `token` varchar(512) DEFAULT NULL,
  `token_expires` datetime DEFAULT NULL,
  `status` tinyint(1) DEFAULT 0,
  `permission` text DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `sex` varchar(1) DEFAULT NULL,
  `id_card` varchar(13) DEFAULT NULL,
  `tax_id` varchar(13) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `address` varchar(64) DEFAULT NULL,
  `address2` varchar(64) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `phone1` varchar(20) DEFAULT NULL,
  `provinceID` smallint(3) DEFAULT NULL,
  `province` varchar(64) DEFAULT NULL,
  `zipcode` varchar(5) DEFAULT NULL,
  `country` varchar(2) DEFAULT 'TH',
  `created_at` datetime DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `social` enum('user','facebook','google','line','telegram') DEFAULT 'user',
  `line_uid` varchar(33) DEFAULT NULL,
  `telegram_id` varchar(20) DEFAULT NULL,
  `activatecode` varchar(64) DEFAULT NULL,
  `visited` int(11) NOT NULL DEFAULT 0,
  `website` varchar(255) DEFAULT NULL,
  `company` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_user_meta`
--

CREATE TABLE `{prefix}_user_meta` (
  `value` varchar(10) NOT NULL,
  `name` varchar(20) NOT NULL,
  `member_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Indexes for table `{prefix}_category`
--
ALTER TABLE `{prefix}_category`
  ADD KEY `type` (`type`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `language` (`language`);

--
-- Indexes for table `{prefix}_language`
--
ALTER TABLE `{prefix}_language`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `{prefix}_logs`
--
ALTER TABLE `{prefix}_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `src_id` (`src_id`),
  ADD KEY `module` (`module`),
  ADD KEY `action` (`action`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `{prefix}_reservation`
--
ALTER TABLE `{prefix}_reservation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `{prefix}_reservation_data`
--
ALTER TABLE `{prefix}_reservation_data`
  ADD KEY `idx_reservation_data` (`reservation_id`,`name`) USING BTREE;

--
-- Indexes for table `{prefix}_rooms`
--
ALTER TABLE `{prefix}_rooms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `{prefix}_rooms_meta`
--
ALTER TABLE `{prefix}_rooms_meta`
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `{prefix}_user`
--
ALTER TABLE `{prefix}_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `id_card` (`id_card`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD UNIQUE KEY `token` (`token`) USING HASH,
  ADD KEY `activatecode` (`activatecode`),
  ADD KEY `line_uid` (`line_uid`),
  ADD KEY `telegram_id` (`telegram_id`),
  ADD KEY `idx_status` (`active`,`status`);

--
-- Indexes for table `{prefix}_user_meta`
--
ALTER TABLE `{prefix}_user_meta`
  ADD KEY `member_id` (`member_id`,`name`);

--
-- AUTO_INCREMENT for table `{prefix}_language`
--
ALTER TABLE `{prefix}_language`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `{prefix}_logs`
--
ALTER TABLE `{prefix}_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `{prefix}_reservation`
--
ALTER TABLE `{prefix}_reservation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `{prefix}_rooms`
--
ALTER TABLE `{prefix}_rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `{prefix}_user`
--
ALTER TABLE `{prefix}_user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
