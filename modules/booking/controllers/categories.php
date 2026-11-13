<?php
/**
 * @filesource modules/booking/controllers/categories.php
 */

namespace Booking\Categories;

class Controller extends \Index\Categories\Controller
{
    /**
     * Supported category types and their labels.
     *
     * @var array
     */
    protected $categories = [
        'use' => '{LNG_Use for}',
        'accessories' => '{LNG_Accessories}'
    ];

    /**
     * สิทธิ์เดียวกับที่เห็นเมนูหมวดหมู่ (Booking\Init\Controller::initMenus)
     *
     * @var array
     */
    protected $permission = ['can_manage_booking', 'can_config'];
}
