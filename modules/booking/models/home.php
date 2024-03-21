<?php
/**
 * @filesource modules/booking/models/home.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Home;

use Kotchasan\Database\Sql;

/**
 * โมเดลสำหรับอ่านข้อมูลแสดงในหน้า  Home
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * คืนค่าจำนวนการจองแยกแต่ละสถานะ
     * ถ้า login เป็นผู้อนุมัติคืนค่ารายการที่เกี่ยวข้อง
     * ถ้าไม่ใช่คืนค่าประวัติของตัวเอง
     *
     * @param array $login
     *
     * @return object
     */
    public static function get($login)
    {
        // สามารถอนุมัติได้
        $reportApprove = \Booking\Base\Controller::reportApprove($login);
        $where = array(
            array('F.member_id', $login['id']),
            array('F.status', [0, 1, 2])
        );
        $qs = [];
        $qs[] = static::createQuery()
            ->select('1 type', 'F.status', 'SQL(COUNT(*) `count`)')
            ->from('reservation F')
            ->where($where)
            ->groupBy('F.status')
            ->cacheOn();
        if ($reportApprove != 0) {
            $where = array(
                array('F.status', 0)
            );
            if ($reportApprove > 0) {
                // ตรวจสอบว่าอนุมัติภายในแผนก ฟรือทุกแผนก
                $withinDepartment = false;
                $reportApprove = [];
                foreach (self::$cfg->booking_approve_department as $approve => $department) {
                    if (self::$cfg->booking_approve_status[$approve] == $login['status']) {
                        if (empty($department)) {
                            // ภายในแผนกของตัวเอง
                            $withinDepartment = true;
                            $reportApprove[] = $approve;
                        } elseif (in_array($department, $login['department'])) {
                            // ทุกแผนก
                            $withinDepartment = false;
                            $reportApprove[] = $approve;
                        }
                    }
                }
                $where[] = array('F.approve', $reportApprove);
                if ($withinDepartment) {
                    $where[] = array('F.department', $login['department']);
                }
            }
            $q = static::createQuery()
                ->select('SQL(DISTINCT F.id AS id)')
                ->from('reservation F')
                ->where($where);
            $qs[] = static::createQuery()
                ->select('0 type', '0 status', 'SQL(COUNT(*) `count`)')
                ->from(array($q, 'Q'));
        }
        $query = static::createQuery()
            ->select()
            ->unionAll($qs)
            ->cacheOn();
        $result = [];
        foreach ($query->execute() as $item) {
            $result[$item->type][$item->status] = $item->count;
        }
        return $result;
    }

    /**
     * จำนวนห้องทั้งหมดที่เปิดใช้งาน
     *
     * @return int
     */
    public static function rooms()
    {
        $where = array(
            array('published', 1)
        );
        $search = static::createQuery()
            ->selectCount()
            ->from('rooms')
            ->where($where)
            ->execute();
        if (!empty($search)) {
            return $search[0]->count;
        }
        return 0;
    }

    /**
     * คืนค่าปีที่มีการจองสูงสุดและต่ำสุด
     * สำหรับแสดงในปฏิทิน
     * ถ้าไม่มีข้อมูลคืนค่าปีปัจจุบัน
     *
     * @return object
     */
    public static function getYearRange()
    {
        $result = static::createQuery()
            ->from('reservation')
            ->first(Sql::YEAR(Sql::MAX('end'), 'max'), Sql::YEAR(Sql::MIN('begin'), 'min'));
        if (empty($result->min)) {
            $result->min = date('Y');
        }
        if (empty($result->max)) {
            $result->max = date('Y');
        }
        return $result;
    }
}
