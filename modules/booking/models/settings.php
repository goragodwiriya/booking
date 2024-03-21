<?php
/**
 * @filesource modules/booking/models/settings.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Settings;

use Gcms\Config;
use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=booking-settings
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\KBase
{
    /**
     * รับค่าจากฟอร์ม (settings.php)
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = [];
        // session, token, can_config, ไม่ใช่สมาชิกตัวอย่าง
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            if (Login::notDemoMode($login) && Login::checkPermission($login, 'can_config')) {
                try {
                    // โหลด config
                    $config = Config::load(ROOT_PATH.'settings/config.php');
                    // รับค่าจากการ POST
                    $config->booking_login_type = $request->post('booking_login_type')->toInt();
                    $config->booking_w = max(100, $request->post('booking_w')->toInt());
                    $config->booking_approving = $request->post('booking_approving')->toInt();
                    $config->booking_delete = $request->post('booking_delete')->toInt();
                    $config->booking_cancellation = $request->post('booking_cancellation')->toInt();
                    $config->booking_notifications = $request->post('booking_notifications')->toInt();
                    $config->booking_approve_status = [];
                    $config->booking_approve_department = [];
                    $booking_approve_status = $request->post('booking_approve_status', [])->toInt();
                    $booking_approve_department = $request->post('booking_approve_department', [])->topic();
                    $approve_level = $request->post('booking_approve_level')->toInt();
                    for ($level = 1; $level <= $approve_level; $level++) {
                        $config->booking_approve_status[$level] = $booking_approve_status[$level];
                        $config->booking_approve_department[$level] = $booking_approve_department[$level];
                    }
                    // save config
                    if (Config::save($config, ROOT_PATH.'settings/config.php')) {
                        // log
                        \Index\Log\Model::add(0, 'booking', 'Save', '{LNG_Module settings} {LNG_Book a room}', $login['id']);
                        // คืนค่า
                        $ret['alert'] = Language::get('Saved successfully');
                        $ret['location'] = 'reload';
                        // เคลียร์
                        $request->removeToken();
                    } else {
                        // ไม่สามารถบันทึก config ได้
                        $ret['alert'] = Language::replace('File %s cannot be created or is read-only.', 'settings/config.php');
                    }
                } catch (\Kotchasan\InputItemException $e) {
                    $ret['alert'] = $e->getMessage();
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }
}
