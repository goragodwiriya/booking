<?php
/**
 * @filesource modules/booking/controllers/init.php
 */

namespace Booking\Init;

use Booking\Helper\Controller as Helper;
use Gcms\Api as ApiController;

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
}
