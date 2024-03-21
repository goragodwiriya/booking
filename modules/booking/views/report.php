<?php
/**
 * @filesource modules/booking/views/report.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Report;

use Kotchasan\DataTable;
use Kotchasan\Date;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=booking-report
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Booking\Tools\View
{
    /**
     * @var array
     */
    private $status;
    /**
     * @var object
     */
    private $category;
    /**
     * @var array
     */
    private $topic = array('topic' => '');

    /**
     * รายงานการจอง (แอดมิน)
     *
     * @param Request $request
     * @param array  $params
     *
     * @return string
     */
    public function render(Request $request, $params)
    {
        $this->category = \Booking\Category\Model::init();
        $this->status = $params['booking_status'];
        unset($params['booking_status']);
        $hideColumns = array('id', 'today', 'begin', 'end', 'color', 'remain', 'phone', 'approve');
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
                'default' => 0,
                'text' => '{LNG_Room}',
                'options' => array(0 => '{LNG_all items}')+\Booking\Room\Model::toSelect(),
                'value' => $params['room_id']
            )
        );
        foreach (Language::get('BOOKING_SELECT', []) as $key => $label) {
            if (!$this->category->isEmpty($key)) {
                $params[$key] = $request->request($key)->toInt();
                $filters[] = array(
                    'name' => $key,
                    'text' => $label,
                    'options' => array(0 => '{LNG_all items}') + $this->category->toSelect($key),
                    'value' => $params[$key]
                );
                $this->topic[] = $label;
                $this->topic[$key] = '';
                $hideColumns[] = $label;
            }
        }
        $filters[] = array(
            'name' => 'status',
            'text' => '{LNG_Status}',
            'options' => array(-1 => '{LNG_all items}') + $this->status,
            'value' => $params['status']
        );
        // URL สำหรับส่งให้ตาราง
        $uri = $request->createUriWithGlobals(WEB_URL.'index.php');
        // ตาราง
        $table = new DataTable(array(
            /* Uri */
            'uri' => $uri,
            /* Model */
            'model' => \Booking\Report\Model::toDataTable($params),
            /* รายการต่อหน้า */
            'perPage' => $request->cookie('bookingReport_perPage', 30)->toInt(),
            /* เรียงลำดับ */
            'sort' => $request->cookie('bookingReport_sort', 'create_date')->toString(),
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => array($this, 'onRow'),
            /* คอลัมน์ที่ไม่ต้องแสดงผล */
            'hideColumns' => $hideColumns,
            /* คอลัมน์ที่สามารถค้นหาได้ */
            'searchColumns' => array('topic', 'name', 'contact', 'phone'),
            /* ตั้งค่าการกระทำของของตัวเลือกต่างๆ ด้านล่างตาราง ซึ่งจะใช้ร่วมกับการขีดถูกเลือกแถว */
            'action' => 'index.php/booking/model/report/action',
            'actionCallback' => 'dataTableActionCallback',
            'actions' => array(
                array(
                    'id' => 'action',
                    'class' => 'ok',
                    'text' => '{LNG_With selected}',
                    'options' => array(
                        'delete' => '{LNG_Delete}'
                    )
                )
            ),
            /* ตัวเลือกด้านบนของตาราง ใช้จำกัดผลลัพท์การ query */
            'filters' => $filters,
            /* ส่วนหัวของตาราง และการเรียงลำดับ (thead) */
            'headers' => array(
                'topic' => array(
                    'text' => '{LNG_Topic}'
                ),
                'name' => array(
                    'text' => '{LNG_Room name}',
                    'sort' => 'name'
                ),
                'contact' => array(
                    'text' => '{LNG_Contact name}'
                ),
                'create_date' => array(
                    'text' => '{LNG_Created}',
                    'class' => 'center',
                    'sort' => 'create_date'
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
                'contact' => array(
                    'class' => 'nowrap small'
                ),
                'create_date' => array(
                    'class' => 'center small'
                ),
                'status' => array(
                    'class' => 'center'
                )
            ),
            /* ฟังก์ชั่นตรวจสอบการแสดงผลปุ่มในแถว */
            'onCreateButton' => array($this, 'onCreateButton'),
            /* ปุ่มแสดงในแต่ละแถว */
            'buttons' => array(
                'edit' => array(
                    'class' => 'icon-valid button green notext',
                    'href' => $uri->createBackUri(array('module' => 'booking-approve', 'id' => ':id')),
                    'title' => '{LNG_Approve}/{LNG_Edit}'
                )
            )
        ));
        // save cookie
        setcookie('bookingReport_perPage', $table->perPage, time() + 2592000, '/', HOST, HTTPS, true);
        setcookie('bookingReport_sort', $table->sort, time() + 2592000, '/', HOST, HTTPS, true);
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
        if ($item['phone'] != '') {
            $item['contact'] .= '<br><a class=icon-phone href="tel:'.$item['phone'].'">'.$item['phone'].'</a>';
        }
        $item['name'] = '<span class=term style="background-color:'.$item['color'].'">'.$item['name'].'</span>';
        $item['name'] .= '<div class="small nowrap">'.self::dateRange($item).'</div>';
        $item['create_date'] = '<span class=small>'.Date::format($item['create_date'], 'd M Y').'<br>{LNG_Time} '.Date::format($item['create_date'], 'H:i').'</span>';
        foreach ($this->category->items() as $k => $v) {
            if (isset($item[$v])) {
                $this->topic[$k] = $this->category->get($k, $item[$v]);
            }
        }
        $item['topic'] = '<div class=two_lines><b>'.$item['topic'].'</b><small class=block>'.implode(' ', $this->topic).'</small></div>';
        $item['reason'] = '<span class="two_lines small" title="'.$item['reason'].'">'.$item['reason'].'</span>';
        $item['status'] = self::showStatus($this->status, $item['status']);
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
            if (empty(self::$cfg->booking_approving) && $item['today'] == 2) {
                return false;
            } elseif (self::$cfg->booking_approving == 1 && $item['remain'] < 0) {
                return false;
            } else {
                return $attributes;
            }
        } else {
            return $attributes;
        }
    }
}
