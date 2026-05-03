<?php
/**
 * @filesource modules/booking/models/mybookings.php
 */

namespace Booking\Mybookings;

class Model extends \Kotchasan\Model
{
    /**
     * Query bookings for current member.
     *
     * @param array $params
     * @param object $login
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    public static function toDataTable(array $params, $login)
    {
        $where = [
            ['R.member_id', (int) $login->id]
        ];
        if ($params['status'] !== '') {
            $where[] = ['R.status', (int) $params['status']];
        }
        $query = static::createQuery()
            ->select(
                'R.id',
                'R.room_id',
                'R.reason',
                'R.topic',
                'R.comment',
                'R.attendees',
                'R.begin',
                'R.end',
                'R.schedule_type',
                'R.status',
                'R.created_at',
                'Room.name room_name',
                'Room.color room_color',
                'RoomNumber.value room_number',
                'U.name member_name'
            )
            ->from('reservation R')
            ->join('rooms Room', ['Room.id', 'R.room_id'], 'LEFT')
            ->join('rooms_meta RoomNumber', [['RoomNumber.room_id', 'Room.id'], ['RoomNumber.name', 'number']], 'LEFT')
            ->join('user U', ['U.id', 'R.member_id'], 'LEFT')
            ->where($where);

        if (!empty($params['search'])) {
            $search = '%'.$params['search'].'%';
            $query->where([
                ['Room.name', 'LIKE', $search],
                ['R.topic', 'LIKE', $search],
                ['R.comment', 'LIKE', $search]
            ], 'OR');
        }

        return $query;
    }
}