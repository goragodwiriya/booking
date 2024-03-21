<?php
/**
 * @filesource modules/booking/models/email.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Email;

use Kotchasan\Language;

/**
 * ส่งอีเมลและ LINE ไปยังผู้ที่เกี่ยวข้อง
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ส่งอีเมลและ LINE แจ้งการทำรายการ
     *
     * @param array $order
     *
     * @return string
     */
    public static function send($order)
    {
        $lines = [];
        $emails = [];
        $name = '';
        $mailto = '';
        $line_uid = '';
        // ตรวจสอบรายชื่อผู้รับ
        if (self::$cfg->demo_mode) {
            // โหมดตัวอย่าง ส่งหาผู้ทำรายการและแอดมินเท่านั้น
            $where = array(
                array('id', array($order['member_id'], 1))
            );
        } else {
            // ส่งหาผู้ทำรายการและผู้ที่เกี่ยวข้อง
            $where = array(
                // ผู้ทำรายการ, คนขับรถ
                array('U.id', $order['member_id']),
                // แอดมิน
                array('U.status', 1)
            );
            if (isset(self::$cfg->booking_approve_department[$order['approve']])) {
                if (empty(self::$cfg->booking_approve_department[$order['approve']])) {
                    // ผู้อนุมัตืตามแผนก
                    $department = $order['department'];
                } else {
                    // ผู้อนุมัตื แผนกที่เลือก
                    $department = self::$cfg->booking_approve_department[$order['approve']];
                }
                $where[] = 'SQL(D.`value`="'.$department.'" AND U.`status`='.self::$cfg->booking_approve_status[$order['approve']].')';
            }
        }
        // ตรวจสอบรายชื่อผู้รับ
        $query = static::createQuery()
            ->select('U.id', 'U.username', 'U.name', 'U.line_uid')
            ->from('user U')
            ->join('user_meta D', 'LEFT', array(array('D.member_id', 'U.id'), array('D.name', 'department')))
            ->where(array('U.active', 1))
            ->andWhere($where, 'OR')
            ->groupBy('U.id')
            ->cacheOn();
        foreach ($query->execute() as $item) {
            if ($item->id == $order['member_id']) {
                // ผู้ทำรายการ
                $name = $item->name;
                $mailto = $item->username;
                $line_uid = $item->line_uid;
            } else {
                // เจ้าหน้าที่
                $emails[] = $item->name.'<'.$item->username.'>';
                if (!empty($item->line_uid)) {
                    $lines[] = $item->line_uid;
                }
            }
        }
        // สถานะการจอง
        $status = \Booking\Tools\View::toStatus($order, false);
        // ข้อมูลห้อง
        $room = self::room($order['room_id']);
        // ข้อความ
        $msg = array(
            '{LNG_Book a room} ['.self::$cfg->web_title.']',
            '{LNG_Contact name} : '.$name,
            '{LNG_Room name} : '.$room->name,
            '{LNG_Attendees number} : '.$order['attendees'],
            '{LNG_Topic} : '.$order['topic'],
            '{LNG_Booking date} : '.\Booking\Tools\View::dateRange($order),
            '{LNG_Status} : '.$status
        );
        if (!empty($order['reason'])) {
            $msg[] = '{LNG_Reason} : '.$order['reason'];
        }
        $msg[] = 'URL : '.WEB_URL.'index.php?module=booking';
        // ข้อความของ user
        $user_msg = Language::trans(implode("\n", $msg));
        // ข้อความของแอดมิน
        $admin_msg = $user_msg.'-approve&id='.$order['id'];
        // ส่งข้อความ
        $ret = [];
        if (!empty(self::$cfg->line_api_key)) {
            // LINE Notify
            $err = \Gcms\Line::send($admin_msg);
            if ($err != '') {
                $ret[] = $err;
            }
        }
        if (!empty(self::$cfg->line_channel_access_token)) {
            // LINE ส่วนตัว
            if (!empty($lines)) {
                $err = \Gcms\Line::sendTo($lines, $admin_msg);
                if ($err != '') {
                    $ret[] = $err;
                }
            }
            if (!empty($line_uid)) {
                $err = \Gcms\Line::sendTo($line_uid, $user_msg);
                if ($err != '') {
                    $ret[] = $err;
                }
            }
        }
        if (self::$cfg->noreply_email != '') {
            // หัวข้ออีเมล
            $subject = '['.self::$cfg->web_title.'] '.Language::get('Book a room').' '.$status;
            // ส่งอีเมลไปยังผู้ทำรายการเสมอ
            $err = \Kotchasan\Email::send($name.'<'.$mailto.'>', self::$cfg->noreply_email, $subject, nl2br($user_msg));
            if ($err->error()) {
                // คืนค่า error
                $ret[] = strip_tags($err->getErrorMessage());
            }
            // รายละเอียดในอีเมล (แอดมิน)
            $admin_msg = nl2br($admin_msg);
            foreach ($emails as $item) {
                // ส่งอีเมล
                $err = \Kotchasan\Email::send($item, self::$cfg->noreply_email, $subject, $admin_msg);
                if ($err->error()) {
                    // คืนค่า error
                    $ret[] = strip_tags($err->getErrorMessage());
                }
            }
        }
        if (isset($err)) {
            // ส่งอีเมลสำเร็จ หรือ error การส่งเมล
            return empty($ret) ? Language::get('Your message was sent successfully') : implode("\n", array_unique($ret));
        } else {
            // ไม่มีอีเมลต้องส่ง
            return Language::get('Saved successfully');
        }
    }

    /**
     * คืนค่าข้อมูลห้อง
     *
     * @param int $room_id
     *
     * @return object
     */
    private static function room($room_id)
    {
        // เลขห้อง
        $select = array('V.name');
        // Query
        $query = static::createQuery()
            ->from('rooms V')
            ->where(array('V.id', $room_id))
            ->cacheOn();
        return $query->first($select);
    }
}
