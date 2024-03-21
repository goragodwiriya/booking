<?php
/**
 * @filesource modules/booking/controllers/report.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Report;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=booking-report
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Booking\Base\Controller
{
    /**
     * รายงานการจอง (admin)
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ข้อความ title bar
        $this->title = Language::get('Book a room');
        // เลือกเมนู
        $this->menu = 'report';
        // สมาชิก
        $login = Login::isMember();
        if ($login) {
            // ค่าที่ส่งมา
            $params = array(
                'from' => $request->request('from')->date(),
                'to' => $request->request('to')->date(),
                'room_id' => $request->request('room_id')->toInt(),
                'use' => $request->request('use')->toInt(),
                'status' => $request->request('status', -1, 'bookingReport_status')->toInt(),
                'booking_status' => Language::get('BOOKING_STATUS')
            );
            setcookie('bookingReport_status', $params['status'], time() + 2592000, '/', HOST, HTTPS, true);
            // สามารถอนุมัติได้
            $params['approve'] = self::reportApprove($login);
            if ($params['approve'] > 0 && empty(self::$cfg->booking_approve_department[$params['approve']])) {
                // ผู้อนุมัติ ภายในแผนกของตัวเอง
                $params['department'] = $login['department'][0];
            }
            if ($params['approve'] != 0) {
                // ข้อความ title bar
                if (isset($params['booking_status'][$params['status']])) {
                    $title = $params['booking_status'][$params['status']];
                    $this->title .= ' '.$title;
                }
                // แสดงผล
                $section = Html::create('section');
                // breadcrumbs
                $breadcrumbs = $section->add('nav', array(
                    'class' => 'breadcrumbs'
                ));
                $ul = $breadcrumbs->add('ul');
                $ul->appendChild('<li><span class="icon-verfied">{LNG_Report}</span></li>');
                $ul->appendChild('<li><span>{LNG_Book a room}</span></li>');
                if (isset($title)) {
                    $ul->appendChild('<li><span>'.$title.'</span></li>');
                }
                $section->add('header', array(
                    'innerHTML' => '<h2 class="icon-report">'.$this->title.'</h2>'
                ));
                // menu
                $section->appendChild(\Index\Tabmenus\View::render($request, 'report', 'booking'));
                $div = $section->add('div', array(
                    'class' => 'content_bg'
                ));
                // แสดงตาราง
                $div->appendChild(\Booking\Report\View::create()->render($request, $params));
                // คืนค่า HTML
                return $section->render();
            }
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
