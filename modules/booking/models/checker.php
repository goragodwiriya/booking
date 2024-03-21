<?php
/**
 * @filesource modules/booking/models/checker.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Checker;

use Kotchasan\Database\Sql;

/**
 * คลาสสำหรับตรวจสอบข้อมูล
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model
{
    /**
     * ตรวจสอบห้องว่าง
     * คืนค่า true ถ้าห้องว่าง
     * ไม่ว่าง คืนค่า false
     *
     * @param array $save
     *
     * @return bool
     */
    public static function availability($save)
    {
        $where = array(
            array('room_id', $save['room_id']),
            Sql::create('(`status`=1 OR `approve`>1)')
        );
        if (!empty($save['id'])) {
            $where[] = array('id', '!=', $save['id']);
        }
        $search = \Kotchasan\Model::createQuery()
            ->from('reservation')
            ->where($where)
            ->andWhere(array(
                Sql::create("('$save[end]' BETWEEN `begin` AND `end`)"),
                Sql::create("('$save[begin]' BETWEEN `begin` AND `end`)"),
                Sql::create("(`begin` BETWEEN '$save[begin]' AND '$save[end]' AND `end` BETWEEN '$save[begin]' AND '$save[end]')")
            ), 'OR')
            ->first('id');
        return $search === false ? true : false;
    }
}
