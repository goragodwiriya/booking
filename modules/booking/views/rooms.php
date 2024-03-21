<?php
/**
 * @filesource modules/booking/views/rooms.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Rooms;

use Kotchasan\DataTable;
use Kotchasan\Http\Request;
use Kotchasan\Text;

/**
 * module=booking-rooms
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * ตารางห้องประชุม
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // URL สำหรับส่งให้ตาราง
        $uri = $request->createUriWithGlobals(WEB_URL.'index.php');
        // ตาราง
        $table = new DataTable(array(
            /* Uri */
            'uri' => $uri,
            /* Model */
            'model' => \Booking\Rooms\Model::toDataTable(),
            /* รายการต่อหน้า */
            'perPage' => $request->cookie('bookingRooms_perPage', 30)->toInt(),
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => array($this, 'onRow'),
            /* คอลัมน์ที่ไม่ต้องแสดงผล */
            'hideColumns' => array('detail', 'color'),
            /* ตั้งค่าการกระทำของของตัวเลือกต่างๆ ด้านล่างตาราง ซึ่งจะใช้ร่วมกับการขีดถูกเลือกแถว */
            'action' => 'index.php/booking/model/rooms/action',
            'actionCallback' => 'dataTableActionCallback',
            /* ส่วนหัวของตาราง และการเรียงลำดับ (thead) */
            'headers' => array(
                'id' => array(
                    'text' => ''
                ),
                'name' => array(
                    'text' => '{LNG_Detail}'
                )
            ),
            /* รูปแบบการแสดงผลของคอลัมน์ (tbody) */
            'cols' => array(
                'id' => array(
                    'class' => 'top'
                ),
                'name' => array(
                    'class' => 'top'
                )
            ),
            /* ปุ่มแสดงในแต่ละแถว */
            'buttons' => array(
                'booking' => array(
                    'class' => 'icon-addtocart button blue',
                    'id' => ':id',
                    'text' => '{LNG_Book a room}'
                ),
                'detail' => array(
                    'class' => 'icon-info button orange',
                    'id' => ':id',
                    'text' => '{LNG_Detail}'
                )
            )
        ));
        // save cookie
        setcookie('bookingRooms_perPage', $table->perPage, time() + 2592000, '/', HOST, HTTPS, true);
        // คืนค่า HTML
        return $table->render();
    }

    /**
     * จัดรูปแบบการแสดงผลในแต่ละแถว
     *
     * @param array  $item ข้อมูลแถว
     * @param int    $o    ID ของข้อมูล
     * @param object $prop กำหนด properties ของ TR
     *
     * @return array
     */
    public function onRow($item, $o, $prop)
    {
        $thumb = is_file(ROOT_PATH.DATA_FOLDER.'booking/'.$item['id'].'.jpg') ? WEB_URL.DATA_FOLDER.'booking/'.$item['id'].'.jpg' : WEB_URL.'modules/booking/img/noimage.png';
        $item['id'] = '<img src="'.$thumb.'" style="max-height:4em;max-width:8em;" alt=thumbnail>';
        $item['name'] = '<span class="term" style="background-color:'.$item['color'].'">'.$item['name'].'</span><span class="one_line">'.strip_tags($item['detail']).'</span>';
        return $item;
    }
}
