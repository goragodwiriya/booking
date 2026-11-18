<?php
/**
 * @filesource modules/booking/models/calendar.php
 *
 * รายการจองห้องบนปฏิทินรวมของแกน (api/index/calendar) — เรียกจาก hook initCalendarEvents
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Booking\Calendar;

use Booking\Helper\Controller as Helper;

/**
 * การจองห้องที่สถานะอยู่ใน booking_calendar_status
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ไอคอนของการจองห้องบนปฏิทินรวม
     */
    const ICON = 'icon-office';

    /**
     * รายการในช่วงวันที่ ตามรูปแบบ event ของ EventCalendar
     *
     * @param string $start Y-m-d
     * @param string $end   Y-m-d
     *
     * @return array
     */
    public static function events($start, $end)
    {
        $query = static::createQuery()
            ->select(
                'R.id',
                'R.begin',
                'R.end',
                'R.schedule_type',
                'Room.name room_name',
                'Room.color room_color',
                'RoomNumber.value room_number'
            )
            ->from('reservation R')
            ->join('rooms Room', ['Room.id', 'R.room_id'], 'LEFT')
            ->join('rooms_meta RoomNumber', [['RoomNumber.room_id', 'Room.id'], ['RoomNumber.name', 'number']], 'LEFT')
            ->where([
                // ค่าตั้งต้นอยู่ที่โมดูล — โปรเจ็คที่รับโมดูลนี้ไปรวมอาจไม่ได้ประกาศไว้ใน Gcms\Config
                ['R.status', self::$cfg->booking_calendar_status ?? [Helper::STATUS_APPROVED]],
                ['R.begin', '<=', $end.' 23:59:59'],
                ['R.end', '>=', $start.' 00:00:00']
            ])
            ->orderBy('R.begin')
            ->cacheOn();

        $events = [];
        foreach ($query->fetchAll() as $item) {
            $scheduleType = Helper::getScheduleType($item, Helper::SCHEDULE_CONTINUOUS);
            $schedule = Helper::buildBookingSchedule((string) $item->begin, (string) $item->end, $scheduleType);
            if ($schedule === null) {
                continue;
            }

            // id ขึ้นต้นด้วยชื่อโมดูล — ปฏิทินรวมมีรายการของหลายโมดูล id ต้องไม่ชนกัน
            $event = [
                'id' => 'booking-'.$item->id,
                'start' => $item->begin,
                'end' => $item->end,
                'allDay' => false,
                'color' => $item->room_color ?: '#4285F4',
                'icon' => self::ICON,
                'clickApi' => 'api/booking/view?id='.$item->id
            ];
            $label = !empty($item->room_number) ? $item->room_number : $item->room_name;
            if ($schedule['schedule_type'] === Helper::SCHEDULE_DAILY_SLOT) {
                $events[] = $event + [
                    'title' => $label,
                    'rangeStart' => $schedule['begin_date'],
                    'rangeEnd' => $schedule['end_date'],
                    'slotStartTime' => substr($schedule['begin_time'], 0, 5),
                    'slotEndTime' => substr($schedule['end_time'], 0, 5),
                    'scheduleType' => 'recurring-slot'
                ];
                continue;
            }
            $events[] = $event + [
                'title' => $label.', '.Helper::formatBookingTime($item, true),
                'scheduleType' => 'continuous'
            ];
        }

        return $events;
    }
}
