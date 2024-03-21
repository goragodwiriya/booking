<?php
/**
 * @filesource modules/booking/controllers/home.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Home;

use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * Controller สำหรับการแสดงผลหน้า Home
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * ฟังก์ชั่นสร้าง card
     *
     * @param Request               $request
     * @param \Kotchasan\Collection $card
     * @param array                 $login
     */
    public static function addCard(Request $request, $card, $login)
    {
        if ($login) {
            $icons = ['icon-verfied', 'icon-valid', 'icon-invalid'];
            $booking_status = Language::get('BOOKING_STATUS');
            // query ข้อมูล card
            $datas = \Booking\Home\Model::get($login);
            // รายการจองของตัวเอง
            foreach ($booking_status as $status => $label) {
                if (isset($datas[1][$status])) {
                    $url = WEB_URL.'index.php?module=booking&amp;status=';
                    \Index\Home\Controller::renderCard($card, $icons[$status], '{LNG_My Booking}', number_format($datas[1][$status]), '{LNG_Book a room} '.$label, $url.$status);
                }
            }
            // รายการจองผู้อนุมัติรออนุมัติ
            if (!empty($datas[0][0])) {
                $url = WEB_URL.'index.php?module=booking-report&amp;status=0';
                if ($login['status'] != 1) {
                    $url .= '&amp;type='.$login['id'];
                }
                \Index\Home\Controller::renderCard($card, $icons[0], '{LNG_Can be approve}', number_format($datas[0][0]), '{LNG_Book a room} '.$booking_status[0], $url);
            }
            \Index\Home\Controller::renderCard($card, 'icon-office', '{LNG_Room}', number_format(\Booking\Home\Model::rooms()), '{LNG_All rooms}', 'index.php?module=booking-rooms');
        }
    }

    /**
     * ฟังก์ชั่นสร้าง block
     *
     * @param Request $request
     * @param Collection $block
     * @param array $login
     */
    public static function addBlock(Request $request, $block, $login)
    {
        if ($login || empty(self::$cfg->booking_login_type)) {
            $content = \Booking\Home\View::create()->render($request, $login);
            $block->set('Booking calendar', $content);
        }
    }
}
