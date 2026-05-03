<?php
if (defined('ROOT_PATH')) {
    if (empty($_POST['username']) || empty($_POST['password'])) {
        include ROOT_PATH.'install/upgrade1.php';
    } else {
        $error = false;
        // Database Class
        include ROOT_PATH.'install/db.php';
        // ค่าติดตั้งฐานข้อมูล
        $db_config = include ROOT_PATH.'settings/database.php';
        try {
            $db_config = $db_config['mysql'];
            // เขื่อมต่อฐานข้อมูล
            $db = new Db($db_config);
        } catch (\Exception $exc) {
            $error = true;
            echo '<h2>ความผิดพลาดในการเชื่อมต่อกับฐานข้อมูล</h2>';
            echo '<p class=warning>ไม่สามารถเชื่อมต่อกับฐานข้อมูลของคุณได้ในขณะนี้</p>';
            echo '<p>อาจเป็นไปได้ว่า</p>';
            echo '<ol>';
            echo '<li>เซิร์ฟเวอร์ของฐานข้อมูลของคุณไม่สามารถใช้งานได้ในขณะนี้</li>';
            echo '<li>ค่ากำหนดของฐานข้อมูลไม่ถูกต้อง (ตรวจสอบไฟล์ settings/database.php)</li>';
            echo '<li>ไม่พบฐานข้อมูลที่ต้องการติดตั้ง กรุณาสร้างฐานข้อมูลก่อน หรือใช้ฐานข้อมูลที่มีอยู่แล้ว</li>';
            echo '<li class="incorrect">'.$exc->getMessage().'</li>';
            echo '</ol>';
            echo '<p>หากคุณไม่สามารถดำเนินการแก้ไขข้อผิดพลาดด้วยตัวของคุณเองได้ ให้ติดต่อผู้ดูแลระบบเพื่อขอข้อมูลที่ถูกต้อง หรือ ลองติดตั้งใหม่</p>';
            echo '<p class="submit"><a href="index.php?step=1" class="btn large btn-secondary">กลับไปลองใหม่</a></p>';
        }
        if (!$error) {
            // เชื่อมต่อฐานข้อมูลสำเร็จ
            $content = ['<li class="correct">เชื่อมต่อฐานข้อมูลสำเร็จ</li>'];
            try {
                // =========================================================
                // user
                // =========================================================
                $table_user = $db_config['prefix'].'_user';
                if (empty($config['password_key'])) {
                    // อัปเดตข้อมูลผู้ดูแลระบบ
                    $config['password_key'] = uniqid();
                }
                // ตรวจสอบการ login
                updateAdmin($db, $table_user, $_POST['username'], $_POST['password'], $config['password_key']);

                foreach (['username', 'token', 'id_card', 'phone', 'activatecode', 'line_uid', 'telegram_id', 'status'] as $_idx) {
                    if ($db->indexExists($table_user, $_idx)) {
                        $db->query("ALTER TABLE `$table_user` DROP INDEX `$_idx`");
                    }
                }

                // rename create_date → created_at
                if ($db->fieldExists($table_user, 'create_date')) {
                    $db->query("ALTER TABLE `$table_user` CHANGE `create_date` `created_at` DATETIME NULL DEFAULT NULL");
                    $content[] = '<li class="correct">user: เปลี่ยนชื่อ create_date → created_at</li>';
                }
                // activatecode: varchar(32) NOT NULL → varchar(64) NULL
                if (!$db->isColumnType($table_user, 'activatecode', 'varchar(64)')) {
                    $db->query("ALTER TABLE `$table_user` CHANGE `activatecode` `activatecode` VARCHAR(64) NULL DEFAULT NULL");
                    $content[] = '<li class="correct">user: แก้ไข activatecode เป็น VARCHAR(64) NULL</li>';
                }
                // address: varchar(150) → varchar(64)
                if (!$db->isColumnType($table_user, 'address', 'varchar(64)')) {
                    $db->query("ALTER TABLE `$table_user` CHANGE `address` `address` VARCHAR(64) NULL DEFAULT NULL");
                    $content[] = '<li class="correct">user: แก้ไข address เป็น VARCHAR(64)</li>';
                }
                // password: varchar(50) → varchar(64)
                if (!$db->isColumnType($table_user, 'password', 'varchar(64)')) {
                    $db->query("ALTER TABLE `$table_user` CHANGE `password` `password` VARCHAR(64) NOT NULL");
                    $content[] = '<li class="correct">user: แก้ไข password เป็น VARCHAR(64)</li>';
                }
                // permission: text → TEXT
                if (!$db->isColumnType($table_user, 'permission', 'text')) {
                    $db->query("ALTER TABLE `$table_user` CHANGE `permission` `permission` TEXT NULL DEFAULT NULL");
                    $content[] = '<li class="correct">user: แก้ไข permission เป็น TEXT</li>';
                }
                // phone: varchar(32) → varchar(20)
                if (!$db->isColumnType($table_user, 'phone', 'varchar(20)')) {
                    $db->query("ALTER TABLE `$table_user` CHANGE `phone` `phone` VARCHAR(20) NULL DEFAULT NULL");
                    $db->query("UPDATE `$table_user` SET `phone` = NULL WHERE `phone` = ''");
                    $content[] = '<li class="correct">user: แก้ไข phone เป็น VARCHAR(20)</li>';
                }
                if ($db->fieldExists($table_user, 'id_card')) {
                    $db->query("ALTER TABLE `$table_user` CHANGE `id_card` `id_card` VARCHAR(13) NULL DEFAULT NULL");
                    $db->query("UPDATE `$table_user` SET `id_card` = NULL WHERE `id_card` = ''");
                    $content[] = '<li class="correct">user: อัปเดท id_card</li>';
                }
                // province: varchar(50) → varchar(64)
                if (!$db->isColumnType($table_user, 'province', 'varchar(64)')) {
                    $db->query("ALTER TABLE `$table_user` CHANGE `province` `province` VARCHAR(64) NULL DEFAULT NULL");
                    $content[] = '<li class="correct">user: แก้ไข province เป็น VARCHAR(64)</li>';
                }
                // provinceID: varchar(3) → smallint(3)
                if (!$db->isColumnType($table_user, 'provinceID', 'smallint(3)')) {
                    $db->query("ALTER TABLE `$table_user` CHANGE `provinceID` `provinceID` SMALLINT(3) NULL DEFAULT NULL");
                    $content[] = '<li class="correct">user: แก้ไข provinceID เป็น SMALLINT(3)</li>';
                }
                // salt: allow null
                if (!$db->isColumnType($table_user, 'salt', 'varchar(32)')) {
                    $db->query("ALTER TABLE `$table_user` CHANGE `salt` `salt` VARCHAR(32) NOT NULL DEFAULT ''");
                    $content[] = '<li class="correct">user: แก้ไข salt เป็น NOT NULL DEFAULT \'\'</li>';
                }
                // social: tinyint → enum (migrate 0 → 'user' first)
                if ($db->isColumnType($table_user, 'social', 'tinyint')) {
                    $db->query("ALTER TABLE `$table_user` CHANGE `social` `social` VARCHAR(32) NULL DEFAULT NULL");
                    $db->query("UPDATE `$table_user` SET `social` = 'user' WHERE `social` = 0 OR `social` IS NULL");
                    $db->query("UPDATE `$table_user` SET `social` = 'facebook' WHERE `social` = 1");
                    $db->query("UPDATE `$table_user` SET `social` = 'google' WHERE `social` = 2");
                    $db->query("UPDATE `$table_user` SET `social` = 'line' WHERE `social` = 3");
                    $db->query("UPDATE `$table_user` SET `social` = 'telegram' WHERE `social` = 4");
                    $db->query("ALTER TABLE `$table_user` CHANGE `social` `social` ENUM('user','facebook','google','line','telegram') NULL DEFAULT 'user'");
                    $content[] = '<li class="correct">user: แก้ไข social เป็น ENUM</li>';
                }
                // telegram_id: varchar(13) → varchar(20)
                if (!$db->isColumnType($table_user, 'telegram_id', 'varchar(20)')) {
                    $db->query("ALTER TABLE `$table_user` CHANGE `telegram_id` `telegram_id` VARCHAR(20) NULL DEFAULT NULL");
                    $content[] = '<li class="correct">user: แก้ไข telegram_id เป็น VARCHAR(20)</li>';
                }
                // token: varchar(50) → varchar(512)
                if (!$db->isColumnType($table_user, 'token', 'varchar(512)')) {
                    $db->query("ALTER TABLE `$table_user` CHANGE `token` `token` VARCHAR(512) NULL DEFAULT NULL");
                    $content[] = '<li class="correct">user: แก้ไข token เป็น VARCHAR(512)</li>';
                }
                // zipcode: varchar(10) → varchar(5)
                if (!$db->isColumnType($table_user, 'zipcode', 'varchar(5)')) {
                    $db->query("ALTER TABLE `$table_user` CHANGE `zipcode` `zipcode` VARCHAR(5) NULL DEFAULT NULL");
                    $content[] = '<li class="correct">user: แก้ไข zipcode เป็น VARCHAR(5)</li>';
                }
                // add new columns
                if (!$db->fieldExists($table_user, 'address2')) {
                    $db->query("ALTER TABLE `$table_user` ADD `address2` VARCHAR(64) NULL DEFAULT NULL");
                    $content[] = '<li class="correct">user: เพิ่ม address2</li>';
                }
                if (!$db->fieldExists($table_user, 'birthday')) {
                    $db->query("ALTER TABLE `$table_user` ADD `birthday` DATE NULL DEFAULT NULL");
                    $content[] = '<li class="correct">user: เพิ่ม birthday</li>';
                }
                if (!$db->fieldExists($table_user, 'company')) {
                    $db->query("ALTER TABLE `$table_user` ADD `company` VARCHAR(64) NULL DEFAULT NULL");
                    $content[] = '<li class="correct">user: เพิ่ม company</li>';
                }
                if (!$db->fieldExists($table_user, 'phone1')) {
                    $db->query("ALTER TABLE `$table_user` ADD `phone1` VARCHAR(20) NULL DEFAULT NULL");
                    $content[] = '<li class="correct">user: เพิ่ม phone1</li>';
                }
                if (!$db->fieldExists($table_user, 'tax_id')) {
                    $db->query("ALTER TABLE `$table_user` ADD `tax_id` VARCHAR(13) NULL DEFAULT NULL");
                    $content[] = '<li class="correct">user: เพิ่ม tax_id</li>';
                }
                if (!$db->fieldExists($table_user, 'token_expires')) {
                    $db->query("ALTER TABLE `$table_user` ADD `token_expires` DATETIME NULL DEFAULT NULL");
                    $content[] = '<li class="correct">user: เพิ่ม token_expires</li>';
                }
                if (!$db->fieldExists($table_user, 'visited')) {
                    $db->query("ALTER TABLE `$table_user` ADD `visited` INT(11) NOT NULL DEFAULT 0");
                    $content[] = '<li class="correct">user: เพิ่ม visited</li>';
                }
                if (!$db->fieldExists($table_user, 'website')) {
                    $db->query("ALTER TABLE `$table_user` ADD `website` VARCHAR(255) NULL DEFAULT NULL");
                    $content[] = '<li class="correct">user: เพิ่ม website</li>';
                }

                foreach (['activatecode', 'line_uid', 'telegram_id'] as $_idx) {
                    if (!$db->indexExists($table_user, $_idx)) {
                        $db->query("ALTER TABLE `$table_user` ADD INDEX `$_idx` (`$_idx`)");
                    }
                }
                foreach (['username', 'token', 'id_card', 'phone'] as $_idx) {
                    if (!$db->indexExists($table_user, $_idx)) {
                        $db->query("ALTER TABLE `$table_user` ADD UNIQUE `$_idx` (`$_idx`)");
                    }
                }

                if (!$db->indexExists($table_user, 'idx_status')) {
                    $db->query("ALTER TABLE `$table_user` ADD INDEX `idx_status` (`active`, `status`)");
                    $content[] = '<li class="correct">user: เพิ่ม index idx_status(active, status)</li>';
                }

                $content[] = '<li class="correct">user อัปเกรดสำเร็จ</li>';

                // =========================================================
                // reservation
                // =========================================================
                $table_reservation = $db_config['prefix'].'_reservation';

                if ($db->fieldExists($table_reservation, 'create_date')) {
                    $db->query("ALTER TABLE `$table_reservation` CHANGE `create_date` `created_at` DATETIME NULL DEFAULT NULL");
                    $content[] = '<li class="correct">reservation: เปลี่ยนชื่อ create_date → created_at</li>';
                }
                if ($db->isColumnType($table_reservation, 'comment', 'text')) {
                    $db->query("ALTER TABLE `$table_reservation` CHANGE `comment` `comment` TEXT NULL DEFAULT NULL");
                    $content[] = '<li class="correct">reservation: แก้ไข comment เป็น TEXT</li>';
                }
                if ($db->isColumnType($table_reservation, 'detail', 'text')) {
                    $db->query("UPDATE `$table_reservation` SET `detail` = '' WHERE `detail` IS NULL");
                    $db->query("ALTER TABLE `$table_reservation` CHANGE `detail` `detail` TEXT NOT NULL");
                    $content[] = '<li class="correct">reservation: แก้ไข detail เป็น TEXT NOT NULL</li>';
                }
                if ($db->isColumnType($table_reservation, 'reason', 'text')) {
                    $db->query("ALTER TABLE `$table_reservation` CHANGE `reason` `reason` TEXT NULL DEFAULT NULL");
                    $content[] = '<li class="correct">reservation: แก้ไข reason เป็น TEXT</li>';
                }
                if (!$db->fieldExists($table_reservation, 'schedule_type')) {
                    $db->query("ALTER TABLE `$table_reservation` ADD `schedule_type` VARCHAR(20) NULL DEFAULT NULL AFTER `end`");
                    $db->query("UPDATE `$table_reservation` SET `schedule_type` = 'continuous' WHERE `schedule_type` IS NULL OR `schedule_type` = ''");
                    $db->query("ALTER TABLE `$table_reservation` CHANGE `schedule_type` `schedule_type` VARCHAR(20) NOT NULL DEFAULT 'daily-slot'");
                    $content[] = '<li class="correct">reservation: เพิ่ม schedule_type และกำหนดข้อมูลเดิมเป็น continuous</li>';
                } elseif ($db->isColumnType($table_reservation, 'schedule_type', 'varchar(20)')) {
                    $db->query("UPDATE `$table_reservation` SET `schedule_type` = 'continuous' WHERE `schedule_type` IS NULL OR `schedule_type` = ''");
                    $db->query("ALTER TABLE `$table_reservation` CHANGE `schedule_type` `schedule_type` VARCHAR(20) NOT NULL DEFAULT 'daily-slot'");
                    $content[] = '<li class="correct">reservation: กำหนดค่าเริ่มต้น schedule_type เป็น daily-slot และเก็บข้อมูลเดิมเป็น continuous</li>';
                }
                // swap index status → idx_room_availability + member_id(member_id, created_at)
                if ($db->indexExists($table_reservation, 'status') && !$db->indexExists($table_reservation, 'idx_room_availability')) {
                    $db->query("DROP INDEX `status` ON `$table_reservation`");
                    $db->query("ALTER TABLE `$table_reservation` ADD INDEX `idx_room_availability` (`room_id`, `status`, `approve`, `begin`, `end`)");
                    $content[] = '<li class="correct">reservation: เพิ่ม index idx_room_availability</li>';
                }
                if (!$db->indexExists($table_reservation, 'member_id')) {
                    $db->query("ALTER TABLE `$table_reservation` ADD INDEX `member_id` (`member_id`, `created_at`)");
                    $content[] = '<li class="correct">reservation: เพิ่ม index member_id(member_id, created_at)</li>';
                }

                $content[] = '<li class="correct">reservation อัปเกรดสำเร็จ</li>';

                // =========================================================
                // rooms
                // =========================================================
                $table_rooms = $db_config['prefix'].'_rooms';
                if ($db->isColumnType($table_rooms, 'color', 'varchar(20)')) {
                    $db->query("UPDATE `$table_rooms` SET `color` = '' WHERE `color` IS NULL");
                    $db->query("ALTER TABLE `$table_rooms` CHANGE `color` `color` VARCHAR(20) NOT NULL DEFAULT ''");
                    $content[] = '<li class="correct">rooms: แก้ไข color เป็น NOT NULL</li>';
                }
                if ($db->isColumnType($table_rooms, 'detail', 'text')) {
                    $db->query("UPDATE `$table_rooms` SET `detail` = '' WHERE `detail` IS NULL");
                    $db->query("ALTER TABLE `$table_rooms` CHANGE `detail` `detail` TEXT NOT NULL");
                    $content[] = '<li class="correct">rooms: แก้ไข detail เป็น TEXT NOT NULL</li>';
                }
                if ($db->isColumnType($table_rooms, 'number', 'varchar(20)')) {
                    $db->query("UPDATE `$table_rooms` SET `number` = '' WHERE `number` IS NULL");
                    $db->query("ALTER TABLE `$table_rooms` CHANGE `number` `number` VARCHAR(20) NOT NULL DEFAULT ''");
                    $content[] = '<li class="correct">rooms: แก้ไข number เป็น NOT NULL</li>';
                }
                // migrate published → is_active
                if (!$db->fieldExists($table_rooms, 'is_active')) {
                    $db->query("ALTER TABLE `$table_rooms` ADD `is_active` TINYINT(1) NULL");
                    if ($db->fieldExists($table_rooms, 'published')) {
                        $db->query("UPDATE `$table_rooms` SET `is_active` = `published`");
                    } else {
                        $db->query("UPDATE `$table_rooms` SET `is_active` = 1");
                    }
                    $db->query("ALTER TABLE `$table_rooms` MODIFY `is_active` TINYINT(1) NOT NULL DEFAULT 1");
                    $content[] = '<li class="correct">rooms: เพิ่ม is_active</li>';
                }
                if ($db->fieldExists($table_rooms, 'published')) {
                    $db->query("ALTER TABLE `$table_rooms` DROP COLUMN `published`");
                    $content[] = '<li class="correct">rooms: ลบ published</li>';
                }
                $content[] = '<li class="correct">rooms อัปเกรดสำเร็จ</li>';

                // =========================================================
                // rooms_meta
                // =========================================================
                $table_rooms_meta = $db_config['prefix'].'_rooms_meta';
                if ($db->indexExists($table_rooms_meta, 'room_id')) {
                    $db->query("ALTER TABLE `$table_rooms_meta` DROP INDEX `room_id`;");
                }
                if (!$db->indexExists($table_rooms_meta, 'idx_room_id')) {
                    $db->query("ALTER TABLE `$table_rooms_meta` ADD INDEX `idx_room_meta` (`room_id`, `name`) USING BTREE;");
                }
                $content[] = '<li class="correct">rooms_meta อัปเกรดสำเร็จ</li>';

                // =========================================================
                // category
                // =========================================================
                $table_category = $db_config['prefix'].'_category';

                if ($db->isColumnType($table_category, 'category_id', 'varchar(10)')) {
                    $db->query("UPDATE `$table_category` SET `category_id` = '0' WHERE `category_id` IS NULL");
                    $db->query("ALTER TABLE `$table_category` CHANGE `category_id` `category_id` VARCHAR(10) NOT NULL DEFAULT '0'");
                    $content[] = '<li class="correct">category: แก้ไข category_id เป็น NOT NULL</li>';
                }
                if ($db->isColumnType($table_category, 'language', 'varchar(2)')) {
                    $db->query("UPDATE `$table_category` SET `language` = '' WHERE `language` IS NULL");
                    $db->query("ALTER TABLE `$table_category` CHANGE `language` `language` VARCHAR(2) NOT NULL DEFAULT ''");
                    $content[] = '<li class="correct">category: แก้ไข language เป็น NOT NULL</li>';
                }
                // migrate published → is_active
                if (!$db->fieldExists($table_category, 'is_active')) {
                    $db->query("ALTER TABLE `$table_category` ADD `is_active` TINYINT(1) NULL");
                    if ($db->fieldExists($table_category, 'published')) {
                        $db->query("UPDATE `$table_category` SET `is_active` = `published`");
                    } else {
                        $db->query("UPDATE `$table_category` SET `is_active` = 1");
                    }
                    $db->query("ALTER TABLE `$table_category` MODIFY `is_active` TINYINT(1) NOT NULL DEFAULT 1");
                    $content[] = '<li class="correct">category: เพิ่ม is_active</li>';
                }
                if ($db->fieldExists($table_category, 'published')) {
                    $db->query("ALTER TABLE `$table_category` DROP COLUMN `published`");
                    $content[] = '<li class="correct">category: ลบ published</li>';
                }
                $db->query("UPDATE `$table_category` SET `type` = 'car_accessory' WHERE `type` = 'car_accessories'");
                $content[] = '<li class="correct">category อัปเกรดสำเร็จ</li>';

                // =========================================================
                // logs
                // =========================================================
                $table_logs = $db_config['prefix'].'_logs';

                if ($db->fieldExists($table_logs, 'create_date')) {
                    $db->query("ALTER TABLE `$table_logs` CHANGE `create_date` `created_at` DATETIME NOT NULL");
                    $content[] = '<li class="correct">logs: เปลี่ยนชื่อ create_date → created_at</li>';
                }
                if ($db->isColumnType($table_logs, 'datas', 'text')) {
                    $db->query("ALTER TABLE `$table_logs` CHANGE `datas` `datas` TEXT NULL DEFAULT NULL");
                    $content[] = '<li class="correct">logs: แก้ไข datas เป็น TEXT</li>';
                }
                if ($db->isColumnType($table_logs, 'member_id', 'int(11)')) {
                    $db->query("UPDATE `$table_logs` SET `member_id` = 0 WHERE `member_id` IS NULL");
                    $db->query("ALTER TABLE `$table_logs` CHANGE `member_id` `member_id` INT(11) NOT NULL");
                    $content[] = '<li class="correct">logs: แก้ไข member_id เป็น NOT NULL</li>';
                }
                if ($db->isColumnType($table_logs, 'reason', 'text')) {
                    $db->query("ALTER TABLE `$table_logs` CHANGE `reason` `reason` TEXT NULL DEFAULT NULL");
                    $content[] = '<li class="correct">logs: แก้ไข reason เป็น TEXT</li>';
                }
                if ($db->isColumnType($table_logs, 'topic', 'text')) {
                    $db->query("ALTER TABLE `$table_logs` CHANGE `topic` `topic` TEXT NOT NULL");
                    $content[] = '<li class="correct">logs: แก้ไข topic เป็น TEXT</li>';
                }
                if (!$db->indexExists($table_logs, 'created_at')) {
                    $db->query("ALTER TABLE `$table_logs` ADD INDEX `created_at` (`created_at`)");
                    $content[] = '<li class="correct">logs: เพิ่ม index created_at</li>';
                }
                $content[] = '<li class="correct">logs อัปเกรดสำเร็จ</li>';

                // =========================================================
                // language
                // =========================================================
                $table_language = $db_config['prefix'].'_language';

                foreach (['js', 'la', 'owner'] as $_col) {
                    if ($db->fieldExists($table_language, $_col)) {
                        $db->query("ALTER TABLE `$table_language` DROP COLUMN `$_col`");
                        $content[] = '<li class="correct">language: ลบ '.$_col.'</li>';
                    }
                }
                $content[] = '<li class="correct">language อัปเกรดสำเร็จ</li>';

                // บันทึก settings/config.php
                $config['version'] = $new_config['version'];
                $config['reversion'] = time();
                if (function_exists('imagewebp')) {
                    $config['stored_img_type'] = isset($config['stored_img_type']) ? $config['stored_img_type'] : '.jpg';
                } else {
                    $config['stored_img_type'] = '.jpg';
                }
                if (isset($new_config['default_icon'])) {
                    $config['default_icon'] = $new_config['default_icon'];
                }
                // กำหนดค่า API หากยังไม่มี
                include_once ROOT_PATH.'Kotchasan/Password.php';
                if (empty($config['api_tokens']['internal']) || empty($config['api_tokens']['external'])) {
                    $config['api_tokens'] = [
                        'internal' => \Kotchasan\Password::uniqid(40),
                        'external' => \Kotchasan\Password::uniqid(40)
                    ];
                }
                if (empty($config['api_secret'])) {
                    $config['api_secret'] = \Kotchasan\Password::uniqid();
                }
                if (empty($config['jwt_secret'])) {
                    $config['jwt_secret'] = \Kotchasan\Password::uniqid(64);
                }
                if (!isset($config['api_ips'])) {
                    $config['api_ips'] = ['0.0.0.0'];
                }
                if (!isset($config['api_cors'])) {
                    $config['api_cors'] = '*';
                }
                $f = save($config, ROOT_PATH.'settings/config.php');
                $content[] = '<li class="'.($f ? 'correct' : 'incorrect').'">บันทึก <b>config.php</b> ...</li>';
                // นำเข้าภาษา
                include ROOT_PATH.'install/language.php';
            } catch (\PDOException $exc) {
                $content[] = '<li class="incorrect">'.$exc->getMessage().'</li>';
                $error = true;
            } catch (\Exception $exc) {
                $content[] = '<li class="incorrect">'.$exc->getMessage().'</li>';
                $error = true;
            }
            if (!$error) {
                echo '<h2>ปรับรุ่นเรียบร้อย</h2>';
                echo '<p>การปรับรุ่นได้ดำเนินการเสร็จเรียบร้อยแล้ว หากคุณต้องการความช่วยเหลือในการใช้งาน คุณสามารถ ติดต่อสอบถามได้ที่ <a href="https://www.kotchasan.com" target="_blank">https://www.kotchasan.com</a></p>';
                echo '<ul>'.implode('', $content).'</ul>';
                echo '<p class=warning>กรุณาลบไดเร็คทอรี่ <em>install/</em> ออกจาก Server ของคุณ</p>';
                echo '<p>คุณควรปรับ chmod ให้ไดเร็คทอรี่ <em>datas/</em> และ <em>settings/</em> (และไดเร็คทอรี่อื่นๆที่คุณได้ปรับ chmod ไว้ก่อนการปรับรุ่น) ให้เป็น 644 ก่อนดำเนินการต่อ (ถ้าคุณได้ทำการปรับ chmod ไว้ด้วยตัวเอง)</p>';
                echo '<p class="submit"><a href="../" class="btn btn-primary large">เข้าระบบ</a></p>';
            } else {
                echo '<h2>ปรับรุ่นไม่สำเร็จ</h2>';
                echo '<p>การปรับรุ่นยังไม่สมบูรณ์ ลองตรวจสอบข้อผิดพลาดที่เกิดขึ้นและแก้ไขดู หากคุณต้องการความช่วยเหลือการติดตั้ง คุณสามารถ ติดต่อสอบถามได้ที่ <a href="https://www.kotchasan.com" target="_blank">https://www.kotchasan.com</a></p>';
                echo '<ul>'.implode('', $content).'</ul>';
                echo '<p class="submit"><a href="." class="btn btn-primary large">ลองใหม่</a></p>';
            }
        }
    }
}

/**
 * @param Db $db
 * @param string $table_name
 * @param string $username
 * @param string $password
 * @param string $password_key
 */
function updateAdmin($db, $table_name, $username, $password, $password_key)
{
    include ROOT_PATH.'Kotchasan/Text.php';
    $username = \Kotchasan\Text::username($username);
    $password = \Kotchasan\Text::password($password);
    $result = $db->first($table_name, [
        'username' => $username,
        'status' => 1
    ]);
    if (!$result || $result->id > 1) {
        throw new \Exception('ชื่อผู้ใช้ไม่ถูกต้อง หรือไม่ใช่ผู้ดูแลระบบสูงสุด');
    } elseif ($result->password === sha1($password.$result->salt)) {
        // password เวอร์ชั่นเก่า
        $password = sha1($password_key.$password.$result->salt);
        $db->update($table_name, ['id' => $result->id], ['password' => $password]);
    } elseif ($result->password != sha1($password_key.$password.$result->salt)) {
        throw new \Exception('รหัสผ่านไม่ถูกต้อง');
    }
}

/**
 * @param array $config
 * @param string $file
 */
function save($config, $file)
{
    $f = @fopen($file, 'wb');
    if ($f !== false) {
        if (!preg_match('/^.*\/([^\/]+)\.php?/', $file, $match)) {
            $match[1] = 'config';
        }
        fwrite($f, '<'."?php\n/* $match[1].php */\nreturn ".var_export((array) $config, true).';');
        fclose($f);
        return true;
    } else {
        return false;
    }
}
