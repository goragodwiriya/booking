<?php
/**
 * @filesource modules/booking/views/home.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Home;

use Kotchasan\Html;

/**
 * หน้า Home
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * หน้า Home
     *
     * @param object $index
     * @param array  $login
     *
     * @return string
     */
    public function render($index, $login)
    {
        $section = Html::create('div');
        $section->add('header', array(
            'innerHTML' => '<h2 class="icon-calendar">{LNG_Booking calendar} {LNG_Room}</h2>'
        ));
        $div = $section->add('div', array(
            'class' => 'setup_frm'
        ));
        $div->add('div', array(
            'id' => 'booking-calendar'
        ));
        // สีของห้องทั้งหมด
        $query = \Booking\Rooms\Model::toDataTable()->cacheOn();
        $rooms = '';
        foreach ($query->execute() as $item) {
            $rooms .= '<a id=room_'.$item->id.' class="item cuttext"><span style="background-color:'.$item->color.'"></span>'.$item->name.'</a>';
        }
        $div->add('div', array(
            'id' => 'room_links',
            'class' => 'calendar_links clear document-list col3',
            'innerHTML' => $rooms
        ));
        // คืนค่าปีที่มีการจองสูงสุดและต่ำสุด
        $range = \Booking\Home\Model::getYearRange();
        /* Javascript */
        $section->script('initBookingCalendar('.$range->min.', '.$range->max.');');
        // คืนค่า HTML
        return $section->render();
    }
}
