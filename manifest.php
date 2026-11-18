<?php
/**
 * manifest.php
 * Web App Manifest ของ PWA ชื่อ คำอธิบาย และสีของแอปมาจากค่าในหน้าตั้งค่า
 *
 * @author Goragod Wiriya <admin@goragod.com>
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */
// load Kotchasan
include 'load.php';
// Initial Kotchasan Framework
$app = Kotchasan::createWebApplication('Gcms\Config');
$app->defaultController = 'Index\Manifest\Controller';
$app->run();
