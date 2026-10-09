<?php
/**
 * modules/booking/tests/run.php — ชุดทดสอบโมดูลจองห้อง
 *
 * สร้างฐานทดสอบของตัวเองด้วยตัวติดตั้งจริง (install/cli-fresh.php) แล้วชี้
 * Kotchasan ไปที่ฐานนั้นผ่าน APP_PATH ชั่วคราว ไม่แตะฐานหรือไฟล์ตั้งค่าของโปรเจ็ค
 *
 * ⚠️ เรียกเฉพาะชั้น Model/Helper — ชั้น Controller ส่งอีเมลแจ้งผู้จองและผู้ดูแล
 * ทุกครั้งที่บันทึก ห้ามเรียกจากชุดทดสอบ
 *
 * ใช้:  php modules/booking/tests/run.php [--db=<ชื่อฐานทดสอบ>] [--keep]
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

$root = dirname(__DIR__, 3);
chdir($root);

$options = ['db' => 'nowtest_booking', 'keep' => false];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $match) && array_key_exists($match[1], $options)) {
        $options[$match[1]] = isset($match[2]) ? $match[2] : true;
    } else {
        fwrite(STDERR, "ไม่รู้จักตัวเลือก $arg\n");
        exit(1);
    }
}
$dbname = $options['db'];

exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/install/cli-fresh.php').' '.escapeshellarg($dbname).' app --no-admin 2>&1', $output, $code);
if ($code !== 0) {
    fwrite(STDERR, "สร้างฐานทดสอบไม่ได้\n".implode("\n", $output)."\n");
    exit(1);
}

$work = sys_get_temp_dir().'/nowjs-booking-'.md5($root);
@mkdir($work.'/settings', 0700, true);
$database = include $root.'/settings/database.php';
$database['mysql']['dbname'] = $dbname;
file_put_contents($work.'/settings/database.php', "<?php\nreturn ".var_export($database, true).";\n");
define('APP_PATH', $work.'/');

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/api';
$_SERVER['SCRIPT_NAME'] = '/api.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
session_save_path(sys_get_temp_dir());
@session_start();

include $root.'/load.php';
Kotchasan::createWebApplication('Gcms\Config');

$ok = 0;
$fail = 0;
$failed = [];

function t($label, $cond)
{
    global $ok, $fail, $failed;
    if ($cond) {
        ++$ok;
        echo "  [ok]   $label\n";
    } else {
        ++$fail;
        $failed[] = $label;
        echo "  [FAIL] $label\n";
    }
}

function group($title)
{
    echo "\n== $title\n";
}

use Booking\Booking\Model as Booking;
use Booking\Helper\Controller as Helper;
use Booking\Room\Model as Room;

$db = \Kotchasan\Model::createDB();
$prefix = $database['mysql']['prefix'];
$cfg = \Gcms\Config::create();

/**
 * สร้างใบจองตรงผ่าน Model คืน id
 */
function reservation($roomId, $begin, $end, $status = 0, $approve = 1, $type = 'daily-slot')
{
    return Booking::saveReservation(0, [
        'room_id' => $roomId, 'member_id' => 1, 'created_at' => date('Y-m-d H:i:s'),
        'topic' => 'ประชุมทดสอบ', 'attendees' => 5, 'begin' => $begin, 'end' => $end,
        'schedule_type' => $type, 'status' => $status, 'approve' => $approve, 'closed' => 1
    ], []);
}

function row($status, $begin, $end)
{
    return (object) ['status' => $status, 'begin' => $begin, 'end' => $end];
}

// -----------------------------------------------------------------------------
group('ตัวติดตั้งสร้างตารางของโมดูล');

$pdoCfg = $database['mysql'];
$pdo = new PDO('mysql:host='.$pdoCfg['hostname'].';dbname='.$dbname.';charset=utf8mb4', $pdoCfg['username'], $pdoCfg['password']);
foreach (['rooms', 'rooms_meta', 'reservation', 'reservation_data'] as $table) {
    t("มีตาราง {$prefix}_$table", (bool) $pdo->query("SHOW TABLES LIKE '{$prefix}_$table'")->fetchColumn());
}

// -----------------------------------------------------------------------------
group('ห้อง');

$room = Room::save(0, ['name' => 'ห้องประชุมใหญ่', 'color' => '#336699', 'detail' => '', 'is_active' => 1], ['number' => 'R101', 'seats' => '20']);
$room2 = Room::save(0, ['name' => 'ห้องประชุมเล็ก', 'color' => '#993366', 'detail' => '', 'is_active' => 1], []);
t('บันทึกห้องได้', $room > 0 && Helper::roomExists($room));
t('ข้อมูลเสริมของห้องถูกเก็บ', Room::getMetaValues($room)['number'] === 'R101');
$ids = array_column(Helper::getRoomOptions(true), 'value');
t('ห้องที่เปิดใช้อยู่ในตัวเลือกจอง', in_array((string) $room, $ids, true));
Room::toggleActive($room2);
t('ปิดใช้ห้องแล้วหายจากตัวเลือกจอง', !in_array((string) $room2, array_column(Helper::getRoomOptions(true), 'value'), true));
$withOld = Helper::getRoomOptions(true, $room2);
t('แก้ใบจองเดิมยังเห็นห้องที่ปิดไปแล้ว พร้อมป้าย (Inactive)',
    in_array((string) $room2, array_column($withOld, 'value'), true)
    && strpos(implode(' ', array_column($withOld, 'text')), '{LNG_Inactive}') !== false);
Room::toggleActive($room2);

// -----------------------------------------------------------------------------
group('สถานะใบจอง — ทุกค่าคงที่ต้องมีป้ายชื่อและบันทึกได้ตามจริง');

$constants = (new ReflectionClass(Helper::class))->getConstants();
foreach ($constants as $name => $value) {
    if (strpos($name, 'STATUS_') !== 0) {
        continue;
    }
    t("$name ($value) ไม่ถูกแปลงเป็นสถานะอื่น", Helper::normalizeStatusId($value) === $value);
    t("$name ($value) มีป้ายชื่อ", Helper::getStatusLabel($value) !== '-');
}

// ⚠️ ปุ่ม "ส่งกลับแก้ไข" เรียก updateStatus(id, 5) — ถ้า 5 ไม่อยู่ในรายการสถานะ
// normalizeStatusId จะแปลงเป็น "รอการอนุมัติ" ผู้จองไม่รู้ว่าถูกส่งกลับ
$returned = reservation($room, '2030-01-10 09:00:01', '2030-01-10 10:00:00');
Booking::updateStatus($returned, Helper::STATUS_RETURNED_FOR_EDIT, ['reason' => 'แก้จำนวนผู้เข้าร่วม']);
$saved = $db->first($prefix.'_reservation', ['id', $returned]);
t('ส่งกลับแก้ไขแล้วสถานะในฐานเป็น 5 จริง', (int) $saved->status === Helper::STATUS_RETURNED_FOR_EDIT);
t('ค่าสถานะที่อ่านจากฐานเป็นจำนวนเต็ม (เงื่อนไขใช้ in_array แบบเข้มงวด)', is_int($saved->status));
t('ใบที่ถูกส่งกลับ ผู้จองแก้ไขได้', Helper::canEditBooking($saved));

// -----------------------------------------------------------------------------
group('ช่วงเวลาชนกัน');

$s = function ($b, $e, $type = 'daily-slot') {
    return Helper::buildBookingSchedule($b, $e, $type);
};
t('วันเดียวกันเวลาทับกัน = ชน',
    Helper::bookingSchedulesOverlap($s('2030-02-01 09:00:01', '2030-02-01 10:00:00'), $s('2030-02-01 09:30:01', '2030-02-01 10:30:00')));
t('ต่อคิวพอดี (จบ 10:00 เริ่ม 10:00) = ไม่ชน',
    !Helper::bookingSchedulesOverlap($s('2030-02-01 09:00:01', '2030-02-01 10:00:00'), $s('2030-02-01 10:00:01', '2030-02-01 11:00:00')));
t('จองหลายวันแบบช่วงเวลาเดิมทุกวัน ไม่ชนกับเวลาอื่นในวันกลาง',
    !Helper::bookingSchedulesOverlap($s('2030-02-01 09:00:01', '2030-02-03 10:00:00'), $s('2030-02-02 10:30:01', '2030-02-02 11:00:00', 'continuous')));
t('จองหลายวันแบบช่วงเวลาเดิมทุกวัน ชนกับเวลาเดียวกันในวันกลาง',
    Helper::bookingSchedulesOverlap($s('2030-02-01 09:00:01', '2030-02-03 10:00:00'), $s('2030-02-02 09:30:01', '2030-02-02 09:45:00', 'continuous')));
t('จองต่อเนื่องข้ามวัน กินทั้งวันกลาง',
    Helper::bookingSchedulesOverlap($s('2030-02-01 14:00:01', '2030-02-03 10:00:00', 'continuous'), $s('2030-02-02 12:00:01', '2030-02-02 13:00:00')));
t('ต่างวันกัน = ไม่ชน',
    !Helper::bookingSchedulesOverlap($s('2030-02-01 09:00:01', '2030-02-01 10:00:00'), $s('2030-02-02 09:00:01', '2030-02-02 10:00:00')));

// -----------------------------------------------------------------------------
group('ห้องว่างไหม — ใบที่รออนุมัติ อนุมัติแล้ว หรือส่งกลับแก้ไข กันเวลาทั้งหมด');

$want = ['id' => 0, 'room_id' => $room, 'begin' => '2030-03-01 10:00:01', 'end' => '2030-03-01 11:00:00', 'schedule_type' => 'daily-slot'];
$a = reservation($room, '2030-03-01 10:00:01', '2030-03-01 11:00:00', Helper::STATUS_PENDING_REVIEW);
t('ใบที่ยังรออนุมัติกันเวลา', !Booking::availability($want));
Booking::updateStatus($a, Helper::STATUS_APPROVED);
t('อนุมัติแล้วกันเวลา', !Booking::availability($want));
t('คนละห้องไม่กันกัน', Booking::availability(['room_id' => $room2] + $want));
t('แก้ใบของตัวเองไม่ชนกับตัวเอง', Booking::availability(['id' => $a] + $want));
$b = reservation($room, '2030-03-02 10:00:01', '2030-03-02 11:00:00', Helper::STATUS_PENDING_REVIEW, 2);
t('ใบที่ผ่านขั้นอนุมัติแรกไปแล้ว (approve > 1) กันเวลา',
    !Booking::availability(['begin' => '2030-03-02 10:30:01', 'end' => '2030-03-02 11:30:00'] + $want));
Booking::updateStatus($a, Helper::STATUS_CANCELLED_BY_REQUESTER);
t('ยกเลิกแล้วคืนเวลาให้คนอื่น', Booking::availability($want));
$rejected = reservation($room, '2030-03-03 10:00:01', '2030-03-03 11:00:00', Helper::STATUS_REJECTED, 2);
t('ใบที่ถูกปฏิเสธคืนเวลาให้คนอื่น แม้ผ่านขั้นอนุมัติแรกไปแล้ว',
    Booking::availability(['begin' => '2030-03-03 10:00:01', 'end' => '2030-03-03 11:00:00'] + $want));
$sent = reservation($room, '2030-03-04 10:00:01', '2030-03-04 11:00:00', Helper::STATUS_RETURNED_FOR_EDIT);
t('ใบที่ส่งกลับแก้ไขยังกันเวลาไว้ให้ผู้จอง',
    !Booking::availability(['begin' => '2030-03-04 10:00:01', 'end' => '2030-03-04 11:00:00'] + $want));

// -----------------------------------------------------------------------------
group('ผู้จองยกเลิก/ลบเองได้ไหม — ตามค่าตั้ง');

$future = ['2030-04-01 09:00:01', '2030-04-01 10:00:00'];
$past = ['2020-04-01 09:00:01', '2020-04-01 10:00:00'];

$cfg->booking_cancellation = Helper::CANCELLATION_PENDING_ONLY;
t('ค่าปริยาย: ใบรออนุมัติยกเลิกได้', Helper::canCancelBookingByRequester(row(0, ...$future)));
t('ค่าปริยาย: ใบอนุมัติแล้วยกเลิกไม่ได้', !Helper::canCancelBookingByRequester(row(1, ...$future)));
$cfg->booking_cancellation = Helper::CANCELLATION_BEFORE_START;
t('ก่อนถึงเวลาจอง: ใบอนุมัติที่ยังไม่ถึงเวลายกเลิกได้', Helper::canCancelBookingByRequester(row(1, ...$future)));
t('ก่อนถึงเวลาจอง: เลยเวลาเริ่มแล้วยกเลิกไม่ได้', !Helper::canCancelBookingByRequester(row(1, ...$past)));
$cfg->booking_cancellation = Helper::CANCELLATION_ALWAYS;
t('ยกเลิกย้อนหลังได้: ใบอนุมัติที่ผ่านไปแล้วยกเลิกได้', Helper::canCancelBookingByRequester(row(1, ...$past)));
t('ใบที่ถูกยกเลิกไปแล้วยกเลิกซ้ำไม่ได้', !Helper::canCancelBookingByRequester(row(3, ...$future)));
$cfg->booking_cancellation = Helper::CANCELLATION_PENDING_ONLY;

// หน้าตั้งค่าเขียนไว้ว่า "ไม่เลือกเลย ลบได้เฉพาะผู้อนุมัติ"
$cfg->booking_delete = [];
t('ไม่ได้เลือกสถานะที่ลบได้ ผู้จองลบใบที่ตัวเองยกเลิกไม่ได้', !Helper::canDeleteBookingByRequester(row(3, ...$future)));
$cfg->booking_delete = [Helper::STATUS_CANCELLED_BY_REQUESTER];
t('เลือกสถานะ "ยกเลิกโดยผู้จอง" ไว้ ผู้จองลบใบนั้นได้', Helper::canDeleteBookingByRequester(row(3, ...$future)));
t('แต่ลบใบที่อนุมัติแล้วไม่ได้', !Helper::canDeleteBookingByRequester(row(1, ...$future)));
$cfg->booking_delete = [];

// -----------------------------------------------------------------------------
group('ขั้นอนุมัติ');

$admin = (object) ['id' => 1, 'status' => 1, 'permission' => []];
$cfg->booking_approve_level = 0;
t('ไม่ได้ตั้งขั้นอนุมัติ = ไม่มีขั้น', Helper::getApprovalSteps() === []);
t('ไม่มีขั้นอนุมัติ แม้ผู้ดูแลก็ไม่เห็นหน้าอนุมัติ', !Helper::canAccessApprovalArea($admin));
$cfg->booking_approve_level = 2;
$cfg->booking_approve_status = [1 => 2, 2 => 3];
$cfg->booking_approve_department = [1 => '', 2 => 'HR'];
t('ตั้งสองขั้นได้สองขั้น', Helper::getApprovalLevelCount() === 2);
t('ขั้นถัดจาก 1 คือ 2', Helper::getNextApprovalStep(1) === 2);
t('ขั้นสุดท้ายไม่มีขั้นถัดไป', Helper::getNextApprovalStep(2) === 0);
t('ขั้นที่ 2 เป็นของแผนก HR', Helper::getApprovalStepConfig(2)['department'] === 'HR');
t('มีขั้นอนุมัติแล้ว ผู้ดูแลเข้าหน้าอนุมัติได้', Helper::canAccessApprovalArea($admin));
// หน้าอนุมัติของผู้อนุมัติที่ไม่ใช่แอดมิน: ขั้น 1 ไม่ระบุแผนก = เห็นเฉพาะใบของแผนกตัวเอง
// (เดิมต่อค่าแผนกเป็น SQL ดิบ `department` = IT → Unknown column หน้าอนุมัติล้มทั้งหน้า)
$scoped = function ($department, $begin) use ($room) {
    return Booking::saveReservation(0, [
        'room_id' => $room, 'member_id' => 1, 'department' => $department, 'created_at' => date('Y-m-d H:i:s'),
        'topic' => 'ทดสอบขอบเขตผู้อนุมัติ', 'attendees' => 2, 'begin' => $begin, 'end' => substr($begin, 0, 11).'10:00:00',
        'schedule_type' => 'continuous', 'status' => Helper::STATUS_PENDING_REVIEW, 'approve' => 1, 'closed' => 1
    ], []);
};
$deptIt = $scoped('IT', '2031-01-05 09:00:01');
$deptHr = $scoped('HR', '2031-01-06 09:00:01');
$approverIt = (object) ['id' => 2, 'status' => 2, 'permission' => [], 'metas' => ['department' => ['IT']]];
$seen = array_map(function ($r) {
    return (int) $r->id;
}, \Booking\Approvals\Model::toDataTable(['status' => '0', 'room_id' => '', 'member_id' => '', 'from' => null, 'to' => null], $approverIt)->fetchAll());
t('ผู้อนุมัติขั้น 1 แผนก IT เห็นใบรออนุมัติของ IT แต่ไม่เห็นของ HR', in_array($deptIt, $seen, true) && !in_array($deptHr, $seen, true));
$cfg->booking_approve_level = 0;

// -----------------------------------------------------------------------------
group('คำแปลของหน้าเว็บ');

// หน้าเว็บแปลจาก language/<lang>.json โดยยุบช่องว่างก่อนค้น (I18nManager)
$json = json_decode(file_get_contents($root.'/language/th.json'), true);
$missing = [];
foreach (glob($root.'/templates/booking/*.html') as $file) {
    $html = file_get_contents($file);
    preg_match_all('/\{LNG_([^}]+)\}/', $html, $m1);
    preg_match_all('/data-i18n(?:="")?[^>]*>\s*([^<{]+?)\s*</u', $html, $m2);
    foreach (array_merge($m1[1], $m2[1]) as $key) {
        $key = trim(preg_replace('/\s+/u', ' ', $key));
        if ($key !== '' && !array_key_exists($key, $json)) {
            $missing[$key] = basename($file);
        }
    }
}
t('ข้อความทุกจุดในเทมเพลตมีคำแปลใน th.json'.(empty($missing) ? '' : ' — ขาด: '.implode(' · ', array_keys($missing))), empty($missing));

$statusCount = count(array_filter(array_keys($constants), function ($n) {
    return strpos($n, 'STATUS_') === 0;
}));
foreach (['th', 'en'] as $lang) {
    $php = include $root.'/language/'.$lang.'.php';
    $js = json_decode(file_get_contents($root.'/language/'.$lang.'.json'), true);
    t("BOOKING_STATUS ใน $lang.php มีครบทุกสถานะ", isset($php['BOOKING_STATUS']) && count($php['BOOKING_STATUS']) === $statusCount);
    t("BOOKING_STATUS ใน $lang.json ตรงกับ $lang.php", isset($js['BOOKING_STATUS']) && array_values($js['BOOKING_STATUS']) === array_values($php['BOOKING_STATUS']));
}

// -----------------------------------------------------------------------------
group('route ไม่ชนกับโมดูลอื่น');

$owners = [];
foreach (glob($root.'/modules/*/admin.js') as $file) {
    preg_match_all("/RouterManager\\.register\\('([^']+)'/", file_get_contents($file), $m);
    foreach ($m[1] as $route) {
        $owners[$route][] = basename(dirname($file));
    }
}
$clash = [];
foreach ($owners as $route => $modules) {
    if (in_array('booking', $modules, true) && count($modules) > 1) {
        $clash[] = $route.' ('.implode(', ', $modules).')';
    }
}
t('route ของ booking ไม่มีโมดูลอื่นลงทะเบียนซ้ำ'.(empty($clash) ? '' : ' — '.implode(' · ', $clash)), empty($clash));
// หน้าแรกของแกนเป็นแดชบอร์ดที่รับการ์ด/บล็อกจากโมดูลอื่น ยึด '/' ได้เฉพาะโปรเจ็คที่ไม่มี
// โมดูลอื่นส่งอะไรขึ้นหน้าแรก (booking เดี่ยว) — ใน oms ยึดแล้วการ์ดยอดขายหายทั้งหน้า
$dashboardProviders = [];
foreach (glob($root.'/modules/*/controllers/init.php') as $file) {
    $name = basename(dirname(dirname($file)));
    if ($name !== 'booking' && preg_match('/function initDashboard(Blocks)?\s*\(/', file_get_contents($file))) {
        $dashboardProviders[] = $name;
    }
}
t('ไม่ยึดหน้าแรก (/) ที่โมดูลอื่นใช้แสดงการ์ด'.(empty($dashboardProviders) ? '' : ' ('.implode(', ', $dashboardProviders).')'),
    empty($dashboardProviders) || !in_array('booking', isset($owners['/']) ? $owners['/'] : [], true));

// -----------------------------------------------------------------------------
group('ปฏิทินรวมของแกน (api/index/calendar) — แหล่งข้อมูล · รายการ · ผู้เยี่ยมชม · เมนู');

$calStatus = (int) ((array) $cfg->booking_calendar_status)[0];
$calId = reservation($room, '2031-03-10 09:00:00', '2031-03-10 10:30:00', $calStatus, 1, Helper::SCHEDULE_CONTINUOUS);
$calPending = reservation($room, '2031-03-11 09:00:00', '2031-03-11 10:00:00', Helper::STATUS_PENDING_REVIEW, 1, Helper::SCHEDULE_CONTINUOUS);
$calParams = ['start' => '2031-03-01', 'end' => '2031-03-31'];
t('แหล่งข้อมูลของ booking อยู่ในปฏิทินรวม', in_array('booking', \Index\Calendar\Controller::sources(null), true));
$calEvents = array_column(\Gcms\Controller::initModule([], 'initCalendarEvents', null, $calParams), null, 'id');
$calEvent = $calEvents['booking-'.$calId] ?? null;
t('ใบที่อนุมัติแล้วขึ้นปฏิทินรวม: id มีชื่อโมดูลนำหน้า · ไอคอน · คลิกเปิด api/booking/view ของตัวเอง',
    $calEvent !== null && $calEvent['icon'] === 'icon-office' && $calEvent['clickApi'] === 'api/booking/view?id='.$calId
    && strpos($calEvent['title'], 'R101') === 0 && $calEvent['color'] === '#336699');
t('ใบที่ยังไม่อนุมัติไม่ขึ้นปฏิทิน', !isset($calEvents['booking-'.$calPending]));
t('โมดูลไม่ส่งบล็อกปฏิทินขึ้นหน้าแรกเอง (ปฏิทินรวมของแกนแทน)',
    !in_array('calendar', array_column(\Gcms\Controller::initModule([], 'initDashboardBlocks', null), 'kind'), true));

// API ของแกน — ผู้เยี่ยมชม (login ว่าง) เห็นเมื่อเปิด dashboard_guest เท่านั้น
$calendarApi = new class extends \Index\Calendar\Controller
{
    /**
     * @param \Kotchasan\Http\Request $request
     */
    protected function authenticateRequest(\Kotchasan\Http\Request $request)
    {
        return null;
    }
};
$calGet = function ($query) use ($calendarApi) {
    $method = $_SERVER['REQUEST_METHOD'] ?? null;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $response = $calendarApi->index((new \Kotchasan\Http\Request())->withQueryParams($query));
    $_SERVER['REQUEST_METHOD'] = $method;

    return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
};
$guestBefore = $cfg->dashboard_guest ?? null;
$cfg->dashboard_guest = false;
t('ผู้เยี่ยมชมเปิดปฏิทินไม่ได้เมื่อปิด dashboard_guest (401)', $calGet($calParams)[0] === 401);
$cfg->dashboard_guest = true;
list($calCode, $calBody) = $calGet($calParams);
t('ผู้เยี่ยมชมเห็นปฏิทินเมื่อเปิด dashboard_guest และได้รายการของ booking',
    $calCode === 200 && in_array('booking-'.$calId, array_column($calBody['data']['data'] ?? [], 'id'), true));
t('ช่วงวันที่ผิด (ไม่ส่ง · เริ่มหลังสิ้นสุด) ตอบ 400', $calGet([])[0] === 400 && $calGet(['start' => '2031-03-31', 'end' => '2031-03-01'])[0] === 400);
$cfg->dashboard_guest = $guestBefore;

// ปฏิทินรวมอยู่บนหน้าแรกที่เดียว (บล็อกแรก) — ไม่มีเมนูและหน้า /calendar แยก (เจ้าของสั่ง 2026-10-07: ซ้ำกับหน้าแรก)
$dashboardApi = new class extends \Index\Dashboard\Controller
{
    /**
     * @param \Kotchasan\Http\Request $request
     */
    protected function authenticateRequest(\Kotchasan\Http\Request $request)
    {
        return null;
    }
};
$guestBefore = $cfg->dashboard_guest ?? null;
$cfg->dashboard_guest = true;
$method = $_SERVER['REQUEST_METHOD'] ?? null;
$_SERVER['REQUEST_METHOD'] = 'GET';
$dashWarnings = [];
set_error_handler(function ($no, $msg, $file, $line) use (&$dashWarnings) {
    $dashWarnings[] = basename($file).':'.$line.' '.$msg;
    return true;
});
$dashBody = json_decode((string) $dashboardApi->index(new \Kotchasan\Http\Request())->getBody(), true);
restore_error_handler();
$_SERVER['REQUEST_METHOD'] = $method;
$cfg->dashboard_guest = $guestBefore;
$calBlocks = $dashBody['data']['blocks'] ?? [];
$calMember = (object) ['id' => 1, 'status' => 0, 'permission' => [], 'metas' => []];
t('ผู้เยี่ยมชมเปิดหน้าแรก (dashboard_guest) ได้โดยไม่มี warning'.(empty($dashWarnings) ? '' : ' — '.implode(' · ', array_slice($dashWarnings, 0, 3))),
    empty($dashWarnings));
t('ปฏิทินรวมเป็นบล็อกแรกบนหน้าแรก และไม่มีเมนูปฏิทินแยก',
    ($calBlocks[0]['kind'] ?? '') === 'calendar' && ($calBlocks[0]['url'] ?? '') === 'api/index/calendar'
    && !in_array('/calendar', array_column(\Index\Menus\Controller::getMenus($calMember), 'url'), true));

// ไฟล์ที่โหลดเองไม่มี ?v= = service worker ให้ตัวที่เคยโหลดไว้ตลอดไป (เคยได้ EventCalendar ก่อนมี icon/clickApi
// รายการไม่มีไอคอน คลิกแล้วไม่เปิดรายละเอียดของโมดูล) · ไม่มีคำอธิบายไอคอนเหนือปฏิทินแล้ว ไอคอนอยู่หน้าชื่อทุกรายการ
$coreJs = file_get_contents($root.'/js/main.js');
$moduleJs = file_get_contents($root.'/modules/booking/admin.js');
t('main.js โหลด EventCalendar เองพร้อมเลขรุ่น (?v= ของ main.js) · โมดูลไม่โหลดเอง',
    strpos($coreJs, "['Now/dist/eventcalendar.min.js', 'Now/dist/eventcalendar.min.css'].map(versionedUrl)") !== false
    && strpos($moduleJs, 'eventcalendar') === false);
t('ไม่มีหน้า /calendar แยก (route · เทมเพลต) · บล็อกบนหน้าแรกไม่มีคำอธิบายไอคอน',
    strpos($coreJs, "'/calendar'") === false && !is_file($root.'/templates/calendar.html')
    && strpos(file_get_contents($root.'/templates/index.html'), 'legend') === false);
t('admin.js โหลด GallerySlideshow พร้อมเลขรุ่น (หลัง main.js · versionedUrl)',
    preg_match("#'Now/css/galleryslideshow.css'\\s*\\]\\.map\\(versionedUrl\\)#", $moduleJs) === 1
    && preg_match('#^Utils\\.dom\\.loadResources#m', $moduleJs) === 0);

// -----------------------------------------------------------------------------
echo "\n".str_repeat('-', 60)."\n";
echo 'ผ่าน '.$ok.' / ล้มเหลว '.$fail."\n";
if ($fail > 0) {
    echo "ข้อที่ไม่ผ่าน:\n";
    foreach ($failed as $label) {
        echo '  - '.$label."\n";
    }
}
if ($options['keep'] !== true) {
    $m = $database['mysql'];
    (new PDO('mysql:host='.$m['hostname'].';charset=utf8mb4', $m['username'], $m['password']))->exec('DROP DATABASE IF EXISTS `'.$dbname.'`');
}
exit($fail > 0 ? 1 : 0);
