<?php
/**
 * @filesource modules/booking/controllers/booking.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Booking;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=booking-booking
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * จองห้องประชุม
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ข้อความ title bar
        $this->title = Language::get('Booking');
        // เลือกเมนู
        $this->menu = 'rooms';
        // สมาชิก
        $login = Login::isMember();
        // ตรวจสอบรายการที่เลือก
        $index = \Booking\Booking\Model::get($request->request('id')->toInt(), $request->request('room_id')->toInt(), $login);
        if ($index && ($login || empty(self::$cfg->booking_login_type))) {
            if (empty($login['department'])) {
                // สมาชิกไม่สังกัดแผนก
                if (in_array('department', self::$cfg->categories_disabled)) {
                    $error = Language::get('You are not affiliated with a department. Please contact the administrator.');
                } else {
                    $error = Language::get('You are not affiliated with a department. Please select a department first.');
                }
                return '<div class=setup_frm><div class="center error">'.$error.'</div></div>';
            } elseif ($login['id'] == $index->member_id && ($index->id == 0 || ($index->status == 0 && $index->approve == 1 && $index->today == 0))) {
                // เจ้าของ และรายการใหม่ หรือยังไม่ได้อนุมัติ และไม่ใช่วันนี้
                $this->menu = $index->id == 0 ? 'rooms' : 'booking';
                // ข้อความ title bar
                $title = Language::get($index->id == 0 ? 'Add' : 'Edit');
                $this->title = $title.' '.$this->title;
                // แสดงผล
                $section = Html::create('section');
                // breadcrumbs
                $breadcrumbs = $section->add('nav', array(
                    'class' => 'breadcrumbs'
                ));
                $ul = $breadcrumbs->add('ul');
                $ul->appendChild('<li><span class="icon-calendar">{LNG_Room}</span></li>');
                $ul->appendChild('<li><a href="{BACKURL?module=booking-setup&id=0}">{LNG_Book a room}</a></li>');
                $ul->appendChild('<li><span>'.$title.'</span></li>');
                $section->add('header', array(
                    'innerHTML' => '<h2 class="icon-write">'.$this->title.'</h2>'
                ));
                $div = $section->add('div', array(
                    'class' => 'content_bg'
                ));
                // แสดงฟอร์ม
                $div->appendChild(\Booking\Booking\View::create()->render($index, $login));
                // คืนค่า HTML
                return $section->render();
            }
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
