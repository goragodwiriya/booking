<?php
/**
 * @filesource modules/booking/models/approvals.php
 */

namespace Booking\Approvals;

class Model extends \Kotchasan\Model
{
    /**
     * Query bookings for approver view.
     *
     * @param array $params
     * @param object|null $login
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    public static function toDataTable(array $params, $login = null)
    {
        $where = [];
        if ($params['status'] !== '') {
            $where[] = ['R.status', (int) $params['status']];
        }
        if ($params['room_id'] !== '') {
            $where[] = ['R.room_id', (int) $params['room_id']];
        }
        if ($params['member_id'] !== '') {
            $where[] = ['R.member_id', (int) $params['member_id']];
        }
        if (!empty($params['from'])) {
            $where[] = ['R.begin', '>=', $params['from'].' 00:00:00'];
        }
        if (!empty($params['to'])) {
            $where[] = ['R.begin', '<=', $params['to'].' 23:59:59'];
        }

        // ขอบเขตของผู้อนุมัติ: ขั้นที่สถานะของผู้ดูอนุมัติได้ (และแผนกของขั้นนั้น) — false = ไม่เห็นคำขอใดเลย
        $approvalScope = self::approvalScope($login);
        if ($approvalScope === false) {
            $where[] = ['R.id', 0];
            $approvalScope = null;
        }

        $query = static::createQuery()
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
                'R.created_at',
                'U.name member_name',
                'Room.name room_name',
                'RoomNumber.value room_number'
            )
            ->from('reservation R')
            ->join('user U', ['U.id', 'R.member_id'], 'LEFT')
            ->join('rooms Room', ['Room.id', 'R.room_id'], 'LEFT')
            ->join('rooms_meta RoomNumber', [['RoomNumber.room_id', 'Room.id'], ['RoomNumber.name', 'number']], 'LEFT')
            ->where($where);

        if ($approvalScope !== null) {
            $query->whereRaw($approvalScope[0], 'AND', $approvalScope[1]);
        }

        if (!empty($params['search'])) {
            $search = '%'.$params['search'].'%';
            $query->where([
                ['Room.name', 'LIKE', $search],
                ['R.topic', 'LIKE', $search],
                ['R.comment', 'LIKE', $search],
                ['R.reason', 'LIKE', $search]
            ], 'OR');
        }

        return $query;
    }

    /**
     * ขอบเขตของหน้าอนุมัติตามขั้นที่ผู้ดูอนุมัติได้ (สถานะของผู้ใช้ · แผนกของขั้น)
     *
     * @param object|null $login
     *
     * @return array|false|null [sql, bindings] · false = ไม่เห็นคำขอใดเลย · null = ไม่จำกัด (ผู้ดูแล · ยังไม่ได้ตั้งขั้นอนุมัติ)
     */
    private static function approvalScope($login)
    {
        if (!$login || \Gcms\Api::isAdmin($login)) {
            return null;
        }
        $approvalSteps = \Booking\Helper\Controller::getApprovalSteps();
        if (empty($approvalSteps)) {
            return null;
        }
        $loginDepartment = isset($login->metas['department'][0]) ? trim((string) $login->metas['department'][0]) : '';
        $q = [];
        $bindings = [];
        foreach ($approvalSteps as $approve => $step) {
            if ((int) $login->status === (int) $step['status']) {
                $department = (string) $step['department'];
                if ($department === '') {
                    // User must be in the same department as the booking
                    // ⚠️ ค่าแผนกต้องผูกเป็นพารามิเตอร์ — Sql::create() คือ SQL ดิบ ได้ `department` = IT (Unknown column)
                    if ($loginDepartment !== '') {
                        $q[] = '(`R`.`approve` = '.(int) $approve.' AND `R`.`department` = :scope_department)';
                        $bindings[':scope_department'] = $loginDepartment;
                    }
                } elseif ($department === $loginDepartment) {
                    $q[] = '(`R`.`approve` = '.(int) $approve.')';
                }
            }
        }

        return empty($q) ? false : ['('.implode(' OR ', $q).')', $bindings];
    }

    /**
     * ผู้ยื่นคำขอที่ผู้ดูเห็นในหน้าอนุมัติ — ตัวเลือกของตัวกรองผู้ยื่น (Index\Users\Model::toOptions)
     *
     * @param object|null $login
     *
     * @return array|null id ของผู้ยื่น · null = ทุกคน (ผู้ดูแล · ยังไม่ได้ตั้งขั้นอนุมัติ)
     */
    public static function memberIds($login): ?array
    {
        $scope = self::approvalScope($login);
        if ($scope === null) {
            return null;
        }
        if ($scope === false) {
            return [];
        }
        $ids = [];
        foreach (static::createQuery()->select('R.member_id')->from('reservation R')->whereRaw($scope[0], 'AND', $scope[1])->groupBy('R.member_id')->fetchAll() as $row) {
            $ids[] = (int) (is_array($row) ? $row['member_id'] : $row->member_id);
        }

        return $ids;
    }

    /**
     * Remove reservations by IDs.
     *
     * @param array $ids
     *
     * @return bool
     */
    public static function remove(array $ids): bool
    {
        $db = \Kotchasan\DB::create();
        $db->delete('reservation', ['id', $ids], 0);
        $db->delete('reservation_data', ['reservation_id', $ids], 0);

        return true;
    }
}
