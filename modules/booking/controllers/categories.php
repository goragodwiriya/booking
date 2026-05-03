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
}