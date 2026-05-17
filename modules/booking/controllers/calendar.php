<?php
/**
 * @filesource modules/booking/controllers/calendar.php
 */

namespace Booking\Calendar;

use Booking\Helper\Controller as Helper;
use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

class Controller extends ApiController
{
    /**
     * Get reservation events for EventCalendar.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function index(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');

            $start = $request->get('start')->date();
            $end = $request->get('end')->date();

            $query = \Kotchasan\Model::createQuery()
                ->select(
                    'R.id',
                    'R.begin',
                    'R.end',
                    'R.schedule_type',
                    'Room.name room_name',
                    'Room.color room_color',
                    'RoomNumber.value room_number',
                    'U.name member_name'
                )
                ->from('reservation R')
                ->join('rooms Room', ['Room.id', 'R.room_id'], 'LEFT')
                ->join('rooms_meta RoomNumber', [['RoomNumber.room_id', 'Room.id'], ['RoomNumber.name', 'number']], 'LEFT')
                ->join('user U', ['U.id', 'R.member_id'], 'LEFT')
                ->where([
                    ['R.status', self::$cfg->booking_calendar_status],
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

                $label = !empty($item->room_number) ? $item->room_number : $item->room_name;
                if ($schedule['schedule_type'] === Helper::SCHEDULE_DAILY_SLOT) {
                    $events[] = [
                        'id' => (string) $item->id,
                        'title' => $label,
                        'start' => $item->begin,
                        'end' => $item->end,
                        'rangeStart' => $schedule['begin_date'],
                        'rangeEnd' => $schedule['end_date'],
                        'slotStartTime' => substr($schedule['begin_time'], 0, 5),
                        'slotEndTime' => substr($schedule['end_time'], 0, 5),
                        'scheduleType' => 'recurring-slot',
                        'allDay' => false,
                        'color' => $item->room_color ?: '#4285F4'
                    ];
                    continue;
                }

                $events[] = [
                    'id' => (string) $item->id,
                    'title' => $label.', '.Helper::formatBookingTime($item, true),
                    'start' => $item->begin,
                    'end' => $item->end,
                    'scheduleType' => 'continuous',
                    'allDay' => false,
                    'color' => $item->room_color ?: '#4285F4'
                ];
            }

            return $this->successResponse([
                'data' => $events
            ], 'Calendar data retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}