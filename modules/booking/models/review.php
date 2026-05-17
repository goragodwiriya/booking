<?php
/**
 * @filesource modules/booking/models/review.php
 */

namespace Booking\Review;

use Booking\Booking\Model as BookingModel;
use Booking\Helper\Controller as Helper;

class Model extends \Kotchasan\Model
{
    /**
     * Get a reservation for approver review.
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function get(int $id)
    {
        if ($id <= 0) {
            return null;
        }

        $row = static::createQuery()
            ->select(
                'R.id',
                'R.member_id',
                'R.room_id',
                'R.reason',
                'R.topic',
                'R.comment',
                'R.attendees',
                'R.begin',
                'R.end',
                'R.schedule_type',
                'R.status',
                'R.approve',
                'R.closed',
                'U.name member_name',
                'U.username member_username',
                'Room.name room_name',
                'RoomNumber.value room_number'
            )
            ->from('reservation R')
            ->join('user U', ['U.id', 'R.member_id'], 'LEFT')
            ->join('rooms Room', ['Room.id', 'R.room_id'], 'LEFT')
            ->join('rooms_meta RoomNumber', [['RoomNumber.room_id', 'Room.id'], ['RoomNumber.name', 'number']], 'LEFT')
            ->where(['R.id', $id])
            ->first();

        if (!$row) {
            return null;
        }

        $meta = BookingModel::getMetaValues($id);
        $row->use = (string) ($meta['use'] ?? '');
        $row->use_text = Helper::getUseLabel($meta['use'] ?? '');
        $row->accessories = BookingModel::csvToArray($meta['accessories'] ?? '');
        $row->accessories_text = Helper::formatAccessoryNames($meta['accessories'] ?? '');

        return $row;
    }

    /**
     * Can this reservation still be processed?
     *
     * @param object|null $row
     *
     * @return bool
     */
    public static function canProcess($row): bool
    {
        if (!$row) {
            return false;
        }

        return Helper::canProcessBooking($row);
    }
}