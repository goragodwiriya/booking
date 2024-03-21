<?php
/**
 * @filesource modules/booking/models/calendar.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Calendar;

use Kotchasan\Database\Sql;
use Kotchasan\Date;
use Kotchasan\Http\Request;
use Kotchasan\Language;
use Kotchasan\Text;

/**
 * คืนค่าข้อมูลปฏิทิน
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * คืนค่าข้อมูลปฏิทินเป็น JSON
     *
     * @param Request $request
     *
     * @return static
     */
    public function toJSON(Request $request)
    {
        if ($request->initSession() && $request->isReferer() && $request->isAjax()) {
            // ค่าที่ส่งมา
            $first = strtotime($request->post('year')->toInt().'-'.$request->post('month')->toInt().'-01');
            $d = date('w', $first);
            $from = date('Y-m-d 00:00:00', strtotime('-'.$d.' days', $first));
            $params = array(
                // วันที่เริ่มต้นและสิ้นสุดตามที่ปฏิทินแสดงผล
                'from' => $from,
                'to' => date('Y-m-d 23:59:59', strtotime($from.' + 41 days'))
            );
            $events = [];
            // โหลดโมดูลที่ติดตั้งแล้ว
            $modules = \Gcms\Modules::create();
            foreach ($modules->getModels('Calendar') as $className) {
                if (method_exists($className, 'get')) {
                    // โหลดค่าติดตั้งโมดูล
                    $className::get($params, $events);
                }
            }
            // คืนค่า JSON
            echo json_encode($events);
        }
    }

    /**
     * คืนค่าข้อมูลปฏิทิน (booking)
     * สำหรับแสดงในปฏิทินหน้าแรก
     *
     * @param array $params
     * @param array $events
     *
     * @return mixed
     */
    public static function get($params, &$events)
    {
        $query = \Kotchasan\Model::createQuery()
            ->select('V.id', 'V.topic', 'V.begin', 'V.end', 'R.color')
            ->from('reservation V')
            ->join('rooms R', 'INNER', array('R.id', 'V.room_id'))
            ->where(array('V.status', self::$cfg->booking_calendar_status))
            ->andWhere(array(
                Sql::create("V.`begin`>='$params[from]' AND V.`begin`<='$params[to]'"),
                Sql::create("V.`end`>='$params[from]' AND V.`end`<='$params[to]'")
            ), 'OR')
            ->order('V.begin')
            ->cacheOn();
        foreach ($query->execute() as $item) {
            $events[] = array(
                'id' => $item->id.'_booking',
                'title' => self::title($item),
                'start' => $item->begin,
                'end' => $item->end,
                'color' => $item->color,
                'class' => 'icon-calendar'
            );
        }
    }

    /**
     * คืนค่าเวลาจอง
     *
     * @param object $item
     *
     * @return string
     */
    private static function title($item)
    {
        if (
            preg_match('/([0-9]{4,4}\-[0-9]{2,2}\-[0-9]{2,2})\s[0-9\:]+$/', $item->begin, $begin) &&
            preg_match('/([0-9]{4,4}\-[0-9]{2,2}\-[0-9]{2,2})\s[0-9\:]+$/', $item->end, $end)
        ) {
            if ($begin[1] == $end[1]) {
                $return = '{LNG_Time} '.Date::format($item->begin, 'TIME_FORMAT').' {LNG_to} '.Date::format($item->end, 'TIME_FORMAT');
            } else {
                $return = Date::format($item->begin).' {LNG_to} '.Date::format($item->end);
            }
            return Language::trans($return)."\n".Text::unhtmlspecialchars($item->topic);
        }
    }
}
