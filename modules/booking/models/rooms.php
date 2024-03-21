<?php
/**
 * @filesource modules/booking/models/rooms.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Rooms;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=booking-rooms
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query ข้อมูลสำหรับส่งให้กับ DataTable
     *
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable()
    {
        return static::createQuery()
            ->select('R.id', 'R.name', 'R.detail', 'R.color')
            ->from('rooms R')
            ->where(array('R.published', 1))
            ->order('R.name');
    }

    /**
     * รับค่าจาก action (rooms.php)
     *
     * @param Request $request
     */
    public function action(Request $request)
    {
        $ret = [];
        // session, referer, Ajax
        if ($request->initSession() && $request->isReferer() && $request->isAjax()) {
            $action = $request->post('action')->toString();
            if ($action === 'detail') {
                // แสดงรายละเอียดห้อง
                $search = \Booking\Write\Model::get($request->post('id')->toInt());
                if ($search) {
                    $ret['modal'] = \Booking\Detail\View::create()->room($search);
                }
            } elseif ($action === 'booking') {
                $url = WEB_URL.'index.php?module=booking-booking&room_id='.$request->post('id')->toInt();
                if (Login::isMember()) {
                    // จองห้อง
                    $ret['location'] = $url;
                } else {
                    // login
                    $ret['alert'] = Language::get('Please log in to continue');
                    $ret['location'] = WEB_URL.'index.php?module=welcome&action=login&ret='.urlencode($url);
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่า JSON
        echo json_encode($ret);
    }
}
