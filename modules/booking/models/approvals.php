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
        $approvalScope = null;
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

        if ($login && !\Gcms\Api::isAdmin($login)) {
            $approvalSteps = \Booking\Helper\Controller::getApprovalSteps();
            if (!empty($approvalSteps)) {
                $loginDepartment = isset($login->metas['department'][0]) ? trim((string) $login->metas['department'][0]) : '';
                $q = [];
                foreach ($approvalSteps as $approve => $step) {
                    if ((int) $login->status === (int) $step['status']) {
                        $department = (string) $step['department'];
                        if ($department === '') {
                            // User must be in the same department as the booking
                            if ($loginDepartment !== '') {
                                $loginDepartmentSql = \Kotchasan\Database\Sql::create($loginDepartment);
                                $q[] = "(`R`.`approve` = ".$approve." AND `R`.`department` = $loginDepartmentSql)";
                            }
                        } elseif ($department === $loginDepartment) {
                            $q[] = "(`R`.`approve` = ".$approve.")";
                        }
                    }
                }
                if (!empty($q)) {
                    $approvalScope = \Kotchasan\Database\Sql::create('('.implode(' OR ', $q).')');
                } else {
                    $where[] = ['R.id', 0];
                }
            }
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
            $query->where($approvalScope);
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
