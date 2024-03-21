<?php
/**
 * @filesource modules/booking/controllers/initmenu.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Initmenu;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * Init Menu
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Booking\Base\Controller
{
    /**
     * ฟังก์ชั่นเริ่มต้นการทำงานของโมดูลที่ติดตั้ง
     * และจัดการเมนูของโมดูล
     *
     * @param Request                $request
     * @param \Index\Menu\Controller $menu
     * @param array                  $login
     */
    public static function execute(Request $request, $menu, $login)
    {
        if ($login || empty(self::$cfg->booking_login_type)) {
            $menu->addTopLvlMenu('rooms', '{LNG_Book a room}', 'index.php?module=booking-rooms', null, 'member');
        }
        if ($login) {
            $submenus = [];
            foreach (Language::get('BOOKING_STATUS', []) as $status => $text) {
                $submenus[] = array(
                    'text' => $text,
                    'url' => 'index.php?module=booking&amp;status='.$status
                );
            }
            $menu->addTopLvlMenu('booking', '{LNG_My Booking}', null, $submenus, 'member');
        }
        $submenus = [];
        // สามารถตั้งค่าระบบได้
        if (Login::checkPermission($login, 'can_config')) {
            $submenus['settings'] = array(
                'text' => '{LNG_Settings}',
                'url' => 'index.php?module=booking-settings'
            );
        }
        // สามารถจัดการห้องประชุมได้
        if (Login::checkPermission($login, 'can_manage_room')) {
            $submenus['setup'] = array(
                'text' => '{LNG_List of} {LNG_Room}',
                'url' => 'index.php?module=booking-setup'
            );
            foreach (Language::get('BOOKING_OPTIONS', []) as $type => $text) {
                $submenus[] = array(
                    'text' => $text,
                    'url' => 'index.php?module=booking-categories&amp;type='.$type
                );
            }
            foreach (Language::get('BOOKING_SELECT', []) as $type => $text) {
                $submenus[] = array(
                    'text' => $text,
                    'url' => 'index.php?module=booking-categories&amp;type='.$type
                );
            }
        }
        if (!empty($submenus)) {
            $menu->add('settings', '{LNG_Book a room}', null, $submenus, 'booking');
        }
        // สามารถอนุมัติ, ดูรายงาน ได้
        $canReportApprove = self::reportApprove($login);
        // สามารถดูรายงานได้ (จอง)
        if ($canReportApprove) {
            $menu->add('report', '{LNG_Book a room}', 'index.php?module=booking-report', null, 'booking');
        }
    }
}
