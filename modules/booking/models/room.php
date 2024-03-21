<?php
/**
 * @filesource modules/booking/models/room.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Room;

/**
 * โมเดลสำหรับ (rooms.php)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model
{
    /**
     * Query ห้องประชุม ใส่ลงใน select
     *
     * @param bool $published
     * @param int $room_id
     *
     * @return array
     */
    public static function toSelect($published = true, $room_id = 0)
    {
        $where = [];
        if ($published) {
            $where[] = array('published', 1);
        }
        if ($room_id > 0) {
            $where[] = array('id', $room_id);
        }
        $query = \Kotchasan\Model::createQuery()
            ->select('id', 'name')
            ->from('rooms')
            ->where($where, 'OR')
            ->order('name')
            ->cacheOn();
        $result = [];
        foreach ($query->execute() as $item) {
            $result[$item->id] = $item->name;
        }
        return $result;
    }
}
