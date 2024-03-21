<?php
/**
 * @filesource modules/booking/views/detail.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Detail;

use Kotchasan\Language;

/**
 * module=booking-rooms
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * แสดงรายละเอียดห้อง
     *
     * @param object $order
     *
     * @return string
     */
    public function room($order)
    {
        $content = '<article class="modal_detail">';
        $content .= '<header><h3 class="icon-office cuttext">{LNG_Details of} {LNG_Room}</h3></header>';
        if (is_file(ROOT_PATH.DATA_FOLDER.'booking/'.$order->id.'.jpg')) {
            $content .= '<figure class="center"><img src="'.WEB_URL.DATA_FOLDER.'booking/'.$order->id.'.jpg"></figure>';
        }
        $content .= '<table class="border data fullwidth"><tbody>';
        $content .= '<tr><th>{LNG_Room name}</th><td><span class="term" style="background-color:'.$order->color.'">'.$order->name.'</span></td></tr>';
        if ($order->detail != '') {
            $content .= '<tr><th>{LNG_Detail}</th><td>'.nl2br($order->detail).'</td></tr>';
        }
        foreach (Language::get('ROOM_CUSTOM_TEXT', []) as $key => $label) {
            if (!empty($order->{$key})) {
                $content .= '<tr><th>'.$label.'</th><td>'.$order->{$key}.'</td></tr>';
            }
        }
        $content .= '</tbody></article>';
        $content .= '</article>';
        // คืนค่า HTML
        return Language::trans($content);
    }

    /**
     * แสดงรายละเอียดการจอง
     *
     * @param array $order
     *
     * @return string
     */
    public function booking($order)
    {
        $content = '<article class="modal_detail">';
        $content .= '<header><h3 class="icon-calendar cuttext">{LNG_Details of} {LNG_Booking}</h3></header>';
        $content .= '<table class="border data fullwidth"><tbody>';
        $content .= '<tr><th class=top>{LNG_Topic}</th><td>'.$order['topic'].'</td></tr>';
        $content .= '<tr><th>{LNG_Room name}</th><td><span class="term" style="background-color:'.$order['color'].'">'.$order['name'].'</span></td></tr>';
        foreach (Language::get('ROOM_CUSTOM_TEXT', []) as $key => $label) {
            if (!empty($order[$key])) {
                $content .= '<tr><th>'.$label.'</th><td>'.$order[$key].'</td></tr>';
            }
        }
        $content .= '<tr><th>{LNG_Attendees number}</th><td>'.$order['attendees'].'</td></tr>';
        $content .= '<tr><th>{LNG_Contact name}</th><td>'.$order['contact'].'</td></tr>';
        $content .= '<tr><th>{LNG_Phone}</th><td><a href="tel:'.$order['phone'].'">'.$order['phone'].'</a></td></tr>';
        $content .= '<tr><th class=top>{LNG_Booking date}</th><td>'.\Booking\Tools\View::dateRange($order).'</td></tr>';
        // หมวดหมู่
        $category = \Booking\Category\Model::init();
        foreach (Language::get('BOOKING_SELECT', []) as $key => $label) {
            if (!empty($order[$key])) {
                $content .= '<tr><th>'.$label.'</th><td>'.$category->get($key, $order[$key]).'</td></tr>';
            }
        }
        foreach (Language::get('BOOKING_TEXT', []) as $key => $label) {
            if (!empty($order[$key])) {
                $content .= '<tr><th>'.$label.'</th><td>'.$order[$key].'</td></tr>';
            }
        }
        foreach (Language::get('BOOKING_OPTIONS', []) as $key => $label) {
            if (!empty($order[$key])) {
                $options = explode(',', $order[$key]);
                $vals = [];
                foreach ($category->toSelect($key) as $i => $v) {
                    if (in_array($i, $options)) {
                        $vals[] = $v;
                    }
                }
                $content .= '<tr><th>'.$label.'</th><td>'.implode(', ', $vals).'</td></tr>';
            }
        }
        if (!empty($order['comment'])) {
            $content .= '<tr><th class=top>{LNG_Other}</th><td>'.nl2br($order['comment']).'</td></tr>';
        }
        $content .= '<tr><th>{LNG_Status}</th><td>'.self::showStatus(Language::get('BOOKING_STATUS'), $order['status']).'</td></tr>';
        if (!empty($order['reason'])) {
            $content .= '<tr><th class=top>{LNG_Reason}</th><td>'.$order['reason'].'</td></tr>';
        }
        $content .= '</tbody></article>';
        $content .= '</article>';
        // คืนค่า HTML
        return Language::trans($content);
    }
}
