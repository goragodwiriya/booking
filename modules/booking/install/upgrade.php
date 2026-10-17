<?php
/**
 * modules/booking/install/upgrade.php — พาฐานเดิมมาถึงสคีมาของโมดูล booking
 *
 * install/upgrade_core.php เรียกไฟล์นี้ให้เองสำหรับทุกโมดูลที่มี ตัวแปรที่ใช้ได้
 * คือชุดเดียวกับที่ upgrade_core ใช้ : $db, $db_config, $prefix, $content, $config
 *
 * ⚠️ ก่อนมีไฟล์นี้ ตัวปรับรุ่นของโปรเจ็ค **ไม่ได้แตะตารางของโมดูลเลยสักตัว**
 * ติดตั้งใหม่จึงได้สคีมาถูก แต่ไซต์ที่อัปเกรดได้สคีมาเก่าค้างไว้ แล้วพังตอนใช้งาน
 * ด้วย "Unknown column 'R.is_active'" — ผู้ใช้เห็นเป็น "อัปเกรดแล้วข้อมูลหาย"
 *
 * กฎเดียวกับ upgrade_core : ทุกเงื่อนไขถามว่า "ต้องแก้ไหม" ไม่ใช่ "ตอนนี้เป็นอะไร"
 */
if (!defined('ROOT_PATH')) {
    exit;
}

$_t_rooms = $prefix.'_rooms';
$_t_rooms_meta = $prefix.'_rooms_meta';
$_t_reservation = $prefix.'_reservation';
$_t_reservation_data = $prefix.'_reservation_data';

foreach ([$_t_rooms, $_t_rooms_meta, $_t_reservation, $_t_reservation_data] as $_t) {
    // นิยามตารางอยู่ที่ modules/booking/install/database.sql ที่เดียว
    if (ensureTable($db, $prefix, $_t)) {
        $content[] = '<li class="correct">booking: สร้างตาราง '.$_t.'</li>';
    }
    // ต้องแปลงก่อนปรับคอลัมน์เสมอ — CONVERT TO CHARACTER SET เลื่อนชนิด TEXT
    // เป็น MEDIUMTEXT ถ้าแปลงทีหลังชนิดจะไม่ตรงกับที่ติดตั้งใหม่ได้
    if (convertToInnoDB($db, $_t)) {
        $content[] = '<li class="correct">'.$_t.': แปลงเป็น InnoDB</li>';
    }
    if (convertToUtf8mb4($db, $_t)) {
        $content[] = '<li class="correct">'.$_t.': แปลงเป็น utf8mb4</li>';
    }
}

// =============================================================================
// rooms — published → is_active
//
// ใช้แพทเทิร์นเดียวกับที่ upgrade_core ทำกับตาราง category : เพิ่มคอลัมน์ใหม่
// คัดลอกค่าเดิมมา แล้วค่อยลบคอลัมน์เก่า (ค่าไม่หาย เพราะย้ายไปอยู่คอลัมน์ใหม่แล้ว)
// =============================================================================
if (!$db->fieldExists($_t_rooms, 'is_active')) {
    $db->query("ALTER TABLE `$_t_rooms` ADD `is_active` TINYINT(1) NULL");
    if ($db->fieldExists($_t_rooms, 'published')) {
        $db->query("UPDATE `$_t_rooms` SET `is_active` = `published`");
    } else {
        $db->query("UPDATE `$_t_rooms` SET `is_active` = 1");
    }
    $content[] = '<li class="correct">rooms: เพิ่ม is_active</li>';
}
if ($db->fieldExists($_t_rooms, 'published')) {
    $db->query("ALTER TABLE `$_t_rooms` DROP COLUMN `published`");
    $content[] = '<li class="correct">rooms: ลบ published (ค่าย้ายไป is_active แล้ว)</li>';
}
foreach ([
    'name' => ['varchar(150)', false, null, 'id'],
    'detail' => ['text', false, null, 'name'],
    'color' => ['varchar(20)', false, '', 'detail'],
    'is_active' => ['tinyint(1)', false, 1, 'color']
] as $_col => $_def) {
    if (ensureColumn($db, $_t_rooms, $_col, $_def[0], $_def[1], $_def[2], '', $_def[3])) {
        $content[] = '<li class="correct">rooms: ปรับคอลัมน์ '.$_col.'</li>';
    }
}

// =============================================================================
// reservation — create_date → created_at และเพิ่ม schedule_type
//
// ⚠️ โค้ดของโมดูลอ้าง `created_at` 8 จุด และ `schedule_type` 18 จุด
// ไซต์ที่อัปเกรดโดยไม่มีสองคอลัมน์นี้จะพังทันทีที่เปิดหน้าจอง
// =============================================================================
if (!$db->fieldExists($_t_reservation, 'created_at') && $db->fieldExists($_t_reservation, 'create_date')) {
    // CHANGE เก็บข้อมูลเดิมไว้ครบ เป็นการเปลี่ยนชื่อ ไม่ใช่สร้างใหม่แล้วทิ้งของเก่า
    $db->query("ALTER TABLE `$_t_reservation` CHANGE `create_date` `created_at` DATETIME NULL DEFAULT NULL");
    $content[] = '<li class="correct">reservation: เปลี่ยนชื่อ create_date → created_at</li>';
}
// approver → approve (เปลี่ยนชื่อ ไม่ใช่สร้างใหม่ ค่าเดิมจึงไม่หาย)
if (!$db->fieldExists($_t_reservation, 'approve') && $db->fieldExists($_t_reservation, 'approver')) {
    $db->query("ALTER TABLE `$_t_reservation` CHANGE `approver` `approve` TINYINT(1) NOT NULL");
    $content[] = '<li class="correct">reservation: เปลี่ยนชื่อ approver → approve</li>';
}
foreach ([
    'created_at' => ['datetime', true, null, 'member_id'],
    'comment' => ['text', true, null, 'topic'],
    'schedule_type' => ['varchar(20)', false, 'daily-slot', 'end'],
    // ⚠️ ส่ง null เป็นค่าปริยาย ไม่ใช่ 0 — สคีมาปัจจุบันประกาศ NOT NULL เฉย ๆ
    // ไม่ได้กำหนด DEFAULT ถ้าใส่ 0 ลงไปสคีมาจะไม่ตรงกับฐานที่ติดตั้งใหม่
    'approve' => ['tinyint(1)', false, null, 'reason'],
    'closed' => ['tinyint(1)', false, null, 'approve'],
    'department' => ['varchar(10)', true, null, 'closed']
] as $_col => $_def) {
    if (ensureColumn($db, $_t_reservation, $_col, $_def[0], $_def[1], $_def[2], '', $_def[3])) {
        $content[] = '<li class="correct">reservation: ปรับคอลัมน์ '.$_col.'</li>';
    }
}

// =============================================================================
// ดัชนีที่ query ใช้จริง
//
// ⚠️ ต้องทำ "หลัง" ปรับคอลัมน์เสร็จ เพราะบางดัชนีอ้างคอลัมน์ที่เพิ่งเปลี่ยนชื่อมา
// (idx_room_availability อ้าง approve ซึ่งเมื่อกี้ยังชื่อ approver อยู่)
// =============================================================================
if (ensureIndexes($db, $_t_reservation, [
    'idx_room_availability' => '`room_id`, `status`, `approve`, `begin`, `end`',
    'member_id' => '`member_id`, `created_at`'
])) {
    $content[] = '<li class="correct">reservation: ปรับดัชนี</li>';
}
if (ensureIndexes($db, $_t_reservation_data, [
    'idx_reservation_data' => '`reservation_id`, `name`'
])) {
    $content[] = '<li class="correct">reservation_data: ปรับดัชนี</li>';
}
if (ensureIndexes($db, $_t_rooms_meta, [
    'idx_room_meta' => '`room_id`, `name`'
])) {
    $content[] = '<li class="correct">rooms_meta: ปรับดัชนี</li>';
}

// =============================================================================
// ดัชนีรุ่นเก่าที่ถูกแทนด้วยดัชนีรวมข้างบนแล้ว
//
// เก็บไว้ก็ไม่มีอะไรใช้ แต่ทำให้ทุกการเขียนต้องอัปเดตดัชนีเพิ่มโดยเปล่าประโยชน์
// และทำให้ไซต์ที่ปรับรุ่นมีดัชนีไม่เท่าไซต์ที่ติดตั้งใหม่ไปตลอด
// การลบดัชนีไม่ทำข้อมูลหาย จึงต่างจากกฎ "ห้ามลบของเดิม" ซึ่งคุ้มครองข้อมูล
// =============================================================================
if (dropIndexes($db, $_t_rooms_meta, ['room_id'])) {
    $content[] = '<li class="correct">rooms_meta: ลบดัชนีรุ่นเก่าที่ถูกแทนแล้ว</li>';
}
if (dropIndexes($db, $_t_reservation_data, ['reservation_id'])) {
    $content[] = '<li class="correct">reservation_data: ลบดัชนีรุ่นเก่าที่ถูกแทนแล้ว</li>';
}

$content[] = '<li class="correct">booking อัปเกรดสำเร็จ</li>';
