<?php
/**
 * @filesource modules/booking/models/report.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Report;

use Gcms\Login;
use Kotchasan\Database\Sql;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=booking-report
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query ข้อมูลสำหรับส่งให้กับ DataTable
     *
     * @param array $params
     *
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable($params)
    {
        $where = [];
        if ($params['status'] > -1) {
            $where[] = array('V.status', $params['status']);
        }
        if ($params['room_id'] > 0) {
            $where[] = array('V.room_id', $params['room_id']);
        }
        if (!empty($params['from']) || !empty($params['to'])) {
            if (empty($params['to'])) {
                $sql = "((V.`begin`>='$params[from] 00:00:00')";
                $sql .= " OR ('$params[from] 00:00:00' BETWEEN V.`begin` AND V.`end`))";
            } elseif (empty($params['from'])) {
                $sql = "((V.`begin`<='$params[to] 23:59:59')";
                $sql .= " OR ('$params[to] 00:00:00' BETWEEN V.`begin` AND V.`end`))";
            } else {
                $sql = "((V.`begin`>='$params[from] 00:00:00' AND V.`begin`<='$params[to] 23:59:59')";
                $sql .= " OR ('$params[from] 00:00:00' BETWEEN V.`begin` AND V.`end` AND '$params[to] 00:00:00' BETWEEN V.`begin` AND V.`end`))";
            }
            $where[] = Sql::create($sql);
        }
        $select = array('V.id', 'V.topic', 'R.name', 'R.color');
        $query = static::createQuery()
            ->from('reservation V')
            ->join('rooms R', 'LEFT', array('R.id', 'V.room_id'))
            ->join('user U', 'LEFT', array('U.id', 'V.member_id'));
        $n = 1;
        foreach (Language::get('BOOKING_SELECT', []) as $key => $label) {
            $on = array(
                array('M'.$n.'.reservation_id', 'V.id'),
                array('M'.$n.'.name', $key)
            );
            if (!empty($params[$key])) {
                $where[] = array('M'.$n.'.value', $params[$key]);
            }
            $query->join('reservation_data M'.$n, 'LEFT', $on);
            $select[] = 'M'.$n.'.value '.$label;
            ++$n;
        }
        $today = date('Y-m-d H:i:s');
        $select = array_merge($select, array(
            'U.name contact',
            'U.phone',
            'V.begin',
            'V.end',
            'V.create_date',
            'V.status',
            'V.approve',
            'V.reason',
            Sql::create('(CASE WHEN "'.$today.'" BETWEEN V.`begin` AND V.`end` THEN 1 WHEN "'.$today.'" > V.`end` THEN 2 ELSE 0 END) AS `today`'),
            Sql::create('TIMESTAMPDIFF(MINUTE,"'.$today.'",V.`begin`) AS `remain`')
        ));
        return $query->select($select)
            ->where($where);
    }

    /**
     * รับค่าจาก action (report.php)
     *
     * @param Request $request
     */
    public function action(Request $request)
    {
        $ret = [];
        // session, referer, member
        if ($request->initSession() && $request->isReferer() && $login = Login::isMember()) {
            // ตรวจสอบสิทธิ์ผู้อนุมัติ
            $reportApprove = \Booking\Base\Controller::reportApprove($login);
            if ($reportApprove != 0) {
                // ค่าที่ส่งมา
                $action = $request->post('action')->toString();
                // id ที่ส่งมา
                if (preg_match_all('/,?([0-9]+),?/', $request->post('id')->toString(), $match)) {
                    if ($action === 'delete') {
                        $where = array(
                            array('id', $match[1])
                        );
                        if ($login['status'] != 1) {
                            // แอดมินลบได้ทั้งหมด, สถานะอื่นๆไม่สามารถลบรายการที่อนุมัติแล้วได้
                            $where[] = Sql::create('(NOW()<`begin` OR `status`!=1)');
                        }
                        $query = static::createQuery()
                            ->select('id')
                            ->from('reservation')
                            ->where($where);
                        $ids = [];
                        foreach ($query->execute() as $item) {
                            $ids[] = $item->id;
                        }
                        if (!empty($ids)) {
                            // ลบ
                            $this->db()->delete($this->getTableName('reservation'), array('id', $match[1]), 0);
                            $this->db()->delete($this->getTableName('reservation_data'), array('reservation_id', $match[1]), 0);
                            // log
                            \Index\Log\Model::add(0, 'booking', 'Delete', '{LNG_Delete} {LNG_Book a room} ID : '.implode(', ', $ids), $login['id']);
                        }
                        // reload
                        $ret['location'] = 'reload';
                    } elseif ($action === 'approve') {
                        // ปรับสถานะ
                        $index = $this->createQuery()
                            ->from('reservation')
                            ->where(array('id', $request->post('id')->toInt()))
                            ->toArray()
                            ->first();
                        if ($index) {
                            $status = $request->post('status')->toInt();
                            // ฟอร์มอนุมัติ
                            $ret['modal'] = \Booking\Approved\View::create()->render($index, $status, $login);
                        }
                    }
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }
}
