<?php
/**
 * @filesource modules/booking/views/index.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Index;

use Kotchasan\DataTable;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=booking
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Booking\Tools\View
{
    /**
     * @var object
     */
    private $category;
    /**
     * @var array
     */
    private $rooms;

    /**
     * รายการจอง (ผู้จอง)
     *
     * @param Request $request
     * @param array $params
     *
     * @return string
     */
    public function render(Request $request, $params)
    {
        $this->category = \Booking\Category\Model::init();
        $this->rooms = \Booking\Room\Model::toSelect();
        $hideColumns = array('today', 'end', 'phone', 'begin', 'color', 'approve', 'closed');
        // filter
        $filters = array(
            array(
                'name' => 'from',
                'type' => 'date',
                'text' => '{LNG_from}',
                'value' => $params['from']
            ),
            array(
                'name' => 'to',
                'type' => 'date',
                'text' => '{LNG_to}',
                'value' => $params['to']
            ),
            array(
                'name' => 'room_id',
                'text' => '{LNG_Room}',
                'options' => array(0 => '{LNG_all items}') + $this->rooms,
                'value' => $params['room_id']
            )
        );
        foreach (Language::get('BOOKING_SELECT', []) as $key => $label) {
            if (!$this->category->isEmpty($key)) {
                $this->topic[] = $label;
                $this->topic[$key] = '';
                $hideColumns[] = $label;
            }
        }
        $filters[] = array(
            'name' => 'status',
            'text' => '{LNG_Status}',
            'options' => array(-1 => '{LNG_all items}') + $params['booking_status'],
            'value' => $params['status']
        );
        // URL สำหรับส่งให้ตาราง
        $uri = $request->createUriWithGlobals(WEB_URL.'index.php');
        // ตาราง
        $table = new DataTable(array(
            /* Uri */
            'uri' => $uri,
            /* Model */
            'model' => \Booking\Index\Model::toDataTable($params),
            /* รายการต่อหน้า */
            'perPage' => $request->cookie('bookingIndex_perPage', 30)->toInt(),
            /* เรียงลำดับ */
            'sort' => 'begin DESC',
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => array($this, 'onRow'),
            /* คอลัมน์ที่ไม่ต้องแสดงผล */
            'hideColumns' => $hideColumns,
            /* คอลัมน์ที่สามารถค้นหาได้ */
            'searchColumns' => array('topic'),
            /* ตั้งค่าการกระทำของของตัวเลือกต่างๆ ด้านล่างตาราง ซึ่งจะใช้ร่วมกับการขีดถูกเลือกแถว */
            'action' => 'index.php/booking/model/index/action',
            'actionCallback' => 'dataTableActionCallback',
            /* ตัวเลือกด้านบนของตาราง ใช้จำกัดผลลัพท์การ query */
            'filters' => $filters,
            /* ส่วนหัวของตาราง และการเรียงลำดับ (thead) */
            'headers' => array(
                'topic' => array(
                    'text' => '{LNG_Topic}'
                ),
                'id' => array(
                    'text' => ''
                ),
                'room_id' => array(
                    'text' => '{LNG_Room name}'
                ),
                'status' => array(
                    'text' => '{LNG_Status}',
                    'class' => 'center'
                ),
                'reason' => array(
                    'text' => '{LNG_Reason}'
                )
            ),
            /* รูปแบบการแสดงผลของคอลัมน์ (tbody) */
            'cols' => array(
                'topic' => array(
                    'class' => 'top'
                ),
                'status' => array(
                    'class' => 'center'
                )
            ),
            /* ฟังก์ชั่นตรวจสอบการแสดงผลปุ่มในแถว */
            'onCreateButton' => array($this, 'onCreateButton'),
            /* ปุ่มแสดงในแต่ละแถว */
            'buttons' => array(
                'cancel' => array(
                    'class' => 'icon-warning button orange',
                    'id' => ':id',
                    'text' => '{LNG_Cancel}'
                ),
                'delete' => array(
                    'class' => 'icon-delete button red',
                    'id' => ':id',
                    'text' => '{LNG_Delete}'
                ),
                'edit' => array(
                    'class' => 'icon-edit button green',
                    'href' => $uri->createBackUri(array('module' => 'booking-booking', 'id' => ':id')),
                    'text' => '{LNG_Edit}'
                ),
                'detail' => array(
                    'class' => 'icon-info button blue',
                    'id' => ':id',
                    'text' => '{LNG_Detail}'
                )
            ),
            /* ปุ่มเพิ่ม */
            'addNew' => array(
                'class' => 'float_button icon-addtocart',
                'href' => 'index.php?module=booking-booking',
                'title' => '{LNG_Book a room}'
            )
        ));
        // save cookie
        setcookie('bookingIndex_perPage', $table->perPage, time() + 2592000, '/', HOST, HTTPS, true);
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
        if ($item['today'] == 1) {
            $prop->class = 'bg3';
        }
        $thumb = is_file(ROOT_PATH.DATA_FOLDER.'booking/'.$item['room_id'].'.jpg') ? WEB_URL.DATA_FOLDER.'booking/'.$item['room_id'].'.jpg' : WEB_URL.'modules/booking/img/noimage.png';
        $item['id'] = '<img src="'.$thumb.'" style="max-height:4em;max-width:8em;" alt=thumbnail>';
        $topic = [];
        foreach ($this->category->items() as $k => $v) {
            if (isset($item[$v])) {
                $topic[] = $v;
                $topic[] = $this->category->get($k, $item[$v]);
            }
        }
        $item['topic'] = '<div class=two_lines><b>'.$item['topic'].'</b><small class=block>'.implode(' ', $topic).'</small></div>';
        $item['reason'] = '<span class="two_lines small" title="'.$item['reason'].'">'.$item['reason'].'</span>';
        $item['status'] = self::toStatus($item, true);
        $item['room_id'] = isset($this->rooms[$item['room_id']]) ? '<span class="term" style="background-color:'.$item['color'].'">'.$this->rooms[$item['room_id']].'</span>' : '';
        $item['room_id'] .= '<div class="small nowrap">'.self::dateRange($item).'</div>';
        return $item;
    }

    /**
     * ฟังกชั่นตรวจสอบว่าสามารถสร้างปุ่มได้หรือไม่
     *
     * @param string $btn
     * @param array $attributes
     * @param array $item
     *
     * @return array
     */
    public function onCreateButton($btn, $attributes, $item)
    {
        if ($btn == 'edit') {
            return $item['status'] == 0 && $item['approve'] == 1 && $item['today'] == 0 ? $attributes : false;
        } elseif ($btn == 'cancel') {
            return \Booking\Index\Model::canCancle($item) ? $attributes : false;
        } elseif ($btn == 'delete') {
            return !empty(self::$cfg->booking_delete) && $item['status'] == 3 ? $attributes : false;
        } else {
            return $attributes;
        }
    }
}
