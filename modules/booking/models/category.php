<?php
/**
 * @filesource modules/booking/models/category.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Category;

use Kotchasan\Language;

/**
 * คลาสสำหรับอ่านข้อมูลหมวดหมู่
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Gcms\Category
{
    /**
     * init Class
     */
    public function __construct()
    {
        // ชื่อหมวดหมู่
        $this->categories = Language::get('BOOKING_SELECT', []) + Language::get('BOOKING_OPTIONS', []);
    }
}
