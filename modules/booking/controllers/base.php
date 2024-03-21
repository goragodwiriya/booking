<?php
/**
 * @filesource modules/booking/controllers/base.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Base;

/**
 * base Controller สำหรับ Booking
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * ตรวจสอบสิทธิ์ผู้อนุมัติ
     * คืนค่า -1 ถ้าเป็นแอดมิน
     * คืนค่า 0 ถ้าไม่มีสิทธิ์
     * คืนค่า ลำดับอนุมัติ ถ้าเป็นผู้อนุมัติ
     *
     * @param array $login
     *
     * @return int
     */
    public static function reportApprove($login)
    {
        if ($login) {
            if ($login['status'] == 1) {
                // แอดมิน
                return -1;
            } else {
                foreach (self::$cfg->booking_approve_status as $level => $status) {
                    if ($status == $login['status']) {
                        if (empty(self::$cfg->booking_approve_department[$level])) {
                            // หัวหน้าแผนกของตัวเอง
                            return $level;
                        } elseif (in_array(self::$cfg->booking_approve_department[$level], $login['department'])) {
                            // หัวหน้าแผนกที่กำหนด เช่น HR
                            return $level;
                        }
                    }
                }
            }
        }
        // ไม่มีสิทธิ
        return 0;
    }

    /**
     * ตรวจสอบว่าสามารถอนุมัติรายการที่ $index หรือไม่
     * คืนค่า 0 ถ้าไม่มีสิทธิ์
     * คืนค่า -1 ถ้าเป็นแอดมิน
     * คืนค่า ลำดับอนุมัติ ถ้าเป็นผู้อนุมัติ
     *
     * @param array $login
     * @param object $index
     *
     * @return int
     */
    public static function canApprove($login, $index)
    {
        if ($index && $login && isset(self::$cfg->booking_approve_status[$index->approve])) {
            if ($login['status'] == 1) {
                // แอดมิน อนุมัติได้ทุกรายการ
                return -1;
            }
            if ($index->status == 0) {
                if (self::$cfg->booking_approve_status[$index->approve] == $login['status']) {
                    if (empty(self::$cfg->booking_approve_department[$index->approve])) {
                        // อนุมัติภายในแผนก
                        if (in_array($index->department, $login['department'])) {
                            // หัวหน้าแผนก
                            return $index->approve;
                        }
                    } elseif (in_array(self::$cfg->booking_approve_department[$index->approve], $login['department'])) {
                        // หัวหน้าแผนกที่กำหนด เช่น HR
                        return $index->approve;
                    }
                }
            }
        }
        // ไม่มีสิทธิ
        return 0;
    }
}
