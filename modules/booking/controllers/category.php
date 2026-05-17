<?php
/**
 * @filesource modules/booking/controllers/category.php
 */

namespace Booking\Category;

class Controller extends \Gcms\Category
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