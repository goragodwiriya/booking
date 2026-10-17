<?php
/**
 * @filesource modules/booking/controllers/init.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Init;

use Booking\Helper\Controller as Helper;
use Gcms\Api as ApiController;
use Kotchasan\Database\Sql;

class Controller extends \Gcms\Controller
{
    /**
     * Register booking permissions.
     *
     * @param array $permissions
     * @param mixed $params
     * @param object|null $login
     *
     * @return array
     */
    public static function initPermission($permissions, $params = null, $login = null)
    {
        $permissions[] = [
            'value' => 'can_manage_booking',
            'text' => '{LNG_Can manage} {LNG_Booking}'
        ];

        return $permissions;
    }

    /**
     * Register booking menus.
     *
     * @param array $menus
     * @param mixed $params
     * @param object|null $login
     *
     * @return array
     */
    public static function initMenus($menus, $params = null, $login = null)
    {
        if (!$login) {
            return $menus;
        }

        $memberMenu = [
            [
                'title' => '{LNG_My bookings}',
                'url' => '/my-bookings',
                'icon' => 'icon-list'
            ],
            [
                'title' => '{LNG_Book a room}',
                'url' => '/booking',
                'icon' => 'icon-edit'
            ],
            [
                'title' => '{LNG_All rooms}',
                'url' => '/rooms',
                'icon' => 'icon-office'
            ]
        ];

        if (Helper::canAccessApprovalArea($login)) {
            $memberMenu[] = [
                'title' => '{LNG_Booking approvals}',
                'url' => '/approvals',
                'icon' => 'icon-verfied'
            ];
        }

        $menus = parent::insertMenuAfter($menus, $memberMenu, 0);

        if (!ApiController::hasPermission($login, ['can_manage_booking', 'can_config'])) {
            return $menus;
        }

        $children = [
            [
                'title' => '{LNG_Settings}',
                'url' => '/booking-settings',
                'icon' => 'icon-cog'
            ],
            [
                'title' => '{LNG_Rooms}',
                'url' => '/room-management',
                'icon' => 'icon-office'
            ]
        ];
        $categories = \Booking\Category\Controller::items();
        foreach ($categories as $key => $menu) {
            $children[] = [
                'title' => $menu,
                'url' => '/booking-categories?type='.$key,
                'icon' => 'icon-tags'
            ];
        }

        $settingsMenu = [
            [
                'title' => '{LNG_Booking}',
                'icon' => 'icon-office',
                'children' => $children
            ]
        ];

        return parent::insertMenuChildren($menus, $settingsMenu, 'settings', null, 1);
    }

    /**
     * การ์ดหน้าแรก: ใบจองห้องของฉันแยกตามสถานะ · คำขอที่รออนุมัติ (ผู้อนุมัติ)
     *
     * ผู้เยี่ยมชม (เปิด dashboard_guest) ไม่มีการ์ด เห็นแค่ปฏิทิน · hint บอกชื่อโมดูล
     * เพราะ car ส่งการ์ดสถานะชื่อเดียวกันขึ้นหน้าแรกเดียวกัน
     *
     * @param array $cards
     * @param mixed $params
     * @param object|null $login
     *
     * @return array
     */
    public static function initDashboardCards($cards, $params = null, $login = null)
    {
        // ผู้เยี่ยมชม (dashboard_guest) — ไม่มีใบจองของตัวเอง
        if (!$login) {
            return $cards;
        }
        $statuses = [
            Helper::STATUS_PENDING_REVIEW => ['Pending review', 'icon-loading'],
            Helper::STATUS_RETURNED_FOR_EDIT => ['Returned for edit', 'icon-edit'],
            Helper::STATUS_APPROVED => ['Approved', 'icon-valid']
        ];
        // My bookings counts
        $query = \Kotchasan\Model::createQuery()
            ->select(Sql::COUNT('id', 'count'), 'status')
            ->from('reservation')
            ->where(['member_id', (int) $login->id])
            ->groupBy('status')
            ->cacheOn();
        foreach ($query->fetchAll() as $row) {
            if (isset($statuses[$row->status])) {
                $cards[] = [
                    'title' => $statuses[$row->status][0],
                    'value' => (int) $row->count,
                    'icon' => $statuses[$row->status][1],
                    'url' => '/my-bookings?status='.$row->status,
                    'class' => '',
                    'unit' => '',
                    'hint' => ''
                ];
            }
        }

        if (Helper::canAccessApprovalArea($login)) {
            $approveLevel = Helper::getApproveLevel($login);
            $q = \Kotchasan\Model::createQuery()
                ->select(Sql::COUNT('id', 'count'))
                ->from('reservation')
                ->where(['status', Helper::STATUS_PENDING_REVIEW]);

            if ($approveLevel !== -1) {
                $q->where(['approve', $approveLevel]);
                $step = Helper::getApprovalStepConfig($approveLevel);
                if ($step !== null && $step['department'] === '') {
                    $department = isset($login->metas['department'][0]) ? trim((string) $login->metas['department'][0]) : '';
                    if ($department !== '') {
                        $q->where(['department', $department]);
                    } else {
                        $q->where(['id', 0]);
                    }
                }
            }

            $row = $q->first();

            $cards[] = [
                'title' => 'Requests to review',
                'value' => (int) ($row ? $row->count : 0),
                'icon' => 'icon-verfied',
                'url' => '/approvals?status=0',
                'class' => '',
                'unit' => '',
                'hint' => ''
            ];
        }

        return $cards;
    }

    /**
     * แหล่งข้อมูลของปฏิทินรวม (api/index/calendar)
     *
     * แกนเพิ่มปฏิทินบนหน้าแรกให้เองเมื่อมีแหล่งข้อมูล — โปรเจ็คที่รวม
     * โมดูลจองรถไว้ด้วย รายการของทั้งสองโมดูลอยู่ในปฏิทินเดียวกัน
     *
     * @param array $sources
     * @param mixed $params
     * @param object|null $login
     *
     * @return array
     */
    public static function initCalendarSources($sources, $params = null, $login = null)
    {
        $sources[] = 'booking';

        return $sources;
    }

    /**
     * รายการการจองห้องบนปฏิทินรวม — ผู้เยี่ยมชมเห็นด้วยเมื่อเปิด dashboard_guest (แกนตรวจให้แล้ว)
     *
     * @param array $events
     * @param array $params ['start' => Y-m-d, 'end' => Y-m-d]
     * @param object|null $login
     *
     * @return array
     */
    public static function initCalendarEvents($events, $params = null, $login = null)
    {
        return array_merge($events, \Booking\Calendar\Model::events($params['start'], $params['end']));
    }
}
