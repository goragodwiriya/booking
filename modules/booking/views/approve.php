<?php
/**
 * @filesource modules/booking/views/approve.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Approve;

use Kotchasan\Date;
use Kotchasan\Html;
use Kotchasan\Language;

/**
 * module=booking-approve
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Booking\Tools\View
{
    /**
     * ฟอร์มแก้ไข การจอง (admin)
     *
     * @param object $index
     * @param array $login
     *
     * @return string
     */
    public function render($index, $login)
    {
        // สามารถอนุมัติได้
        $canApprove = \Booking\Base\Controller::canApprove($login, $index);
        // สามารถแก้ไขได้ แอดมิน และตามที่กำหนดใน $booking_approve_can_edit
        $canEdit = $canApprove == -1 || !empty(self::$cfg->booking_approve_can_edit[$canApprove]);
        // form
        $form = Html::create('form', array(
            'id' => 'setup_frm',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/booking/model/approve/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ));
        $fieldset = $form->add('fieldset', array(
            'title' => '{LNG_Details of} {LNG_Booking} '.self::toStatus((array) $index, true)
        ));
        $groups = $fieldset->add('groups');
        // room_id
        $rooms = \Booking\Room\Model::toSelect(false, $index->room_id);
        if (count($rooms) < 10) {
            $groups->add('select', array(
                'id' => 'room_id',
                'labelClass' => 'g-input icon-office',
                'itemClass' => 'width50',
                'label' => '{LNG_Room name}',
                'options' => $rooms,
                'value' => $index->room_id
            ));
        } else {
            $groups->add('text', array(
                'id' => 'room_id',
                'labelClass' => 'g-input icon-office',
                'itemClass' => 'width50',
                'label' => '{LNG_Room name}',
                'placeholder' => Language::replace('Search :name and select from the list', array(':name' => 'Room name')),
                'datalist' => $rooms,
                'value' => $index->room_id
            ));
        }
        // topic
        $groups->add('text', array(
            'id' => 'topic',
            'labelClass' => 'g-input icon-edit',
            'itemClass' => 'width50',
            'label' => '{LNG_Topic}',
            'maxlength' => 150,
            'value' => $index->topic
        ));
        $groups = $fieldset->add('groups');
        // attendees
        $groups->add('number', array(
            'id' => 'attendees',
            'labelClass' => 'g-input icon-group',
            'itemClass' => 'width50',
            'label' => '{LNG_Attendees number}',
            'value' => $index->attendees
        ));
        $groups = $fieldset->add('groups');
        // name
        $groups->add('text', array(
            'id' => 'name',
            'labelClass' => 'g-input icon-customer',
            'itemClass' => 'width50',
            'label' => '{LNG_Contact name}',
            'disabled' => true,
            'value' => $index->name
        ));
        // phone
        $groups->add('text', array(
            'id' => 'phone',
            'labelClass' => 'g-input icon-phone',
            'itemClass' => 'width50',
            'label' => '{LNG_Phone}',
            'disabled' => true,
            'value' => $index->phone
        ));
        $groups = $fieldset->add('groups');
        // begin_date
        $begin = empty($index->begin) ? time() : strtotime($index->begin);
        $groups->add('date', array(
            'id' => 'begin_date',
            'label' => '{LNG_Begin date}',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'width50',
            'label' => '{LNG_Begin date}',
            'value' => date('Y-m-d', $begin)
        ));
        // begin_time
        $groups->add('time', array(
            'id' => 'begin_time',
            'label' => '{LNG_Begin time}',
            'labelClass' => 'g-input icon-clock',
            'itemClass' => 'width50',
            'value' => date('H:i', $begin)
        ));
        $groups = $fieldset->add('groups');
        // end_date
        $end = empty($index->end) ? time() : strtotime($index->end);
        $groups->add('date', array(
            'id' => 'end_date',
            'label' => '{LNG_End date}',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'width50',
            'value' => date('Y-m-d', $end)
        ));
        // end_time
        $groups->add('time', array(
            'id' => 'end_time',
            'label' => '{LNG_End time}',
            'labelClass' => 'g-input icon-clock',
            'itemClass' => 'width50',
            'value' => date('H:i', $end)
        ));
        // ตัวเลือก select
        $category = \Booking\Category\Model::init();
        $i = 0;
        foreach (Language::get('BOOKING_SELECT', []) as $key => $label) {
            if (!$category->isEmpty($key)) {
                if ($i % 2 == 0) {
                    $groups = $fieldset->add('groups');
                }
                $i++;
                $groups->add('select', array(
                    'id' => $key,
                    'labelClass' => 'g-input icon-menus',
                    'itemClass' => 'width50',
                    'label' => $label,
                    'options' => $category->toSelect($key),
                    'value' => isset($index->{$key}) ? $index->{$key} : 0
                ));
            }
        }
        // textbox
        foreach (Language::get('BOOKING_TEXT', []) as $key => $label) {
            $fieldset->add('text', array(
                'id' => $key,
                'labelClass' => 'g-input icon-edit',
                'itemClass' => 'item',
                'label' => $label,
                'maxlength' => 250,
                'value' => isset($index->{$key}) ? $index->{$key} : ''
            ));
        }
        // ตัวเลือก checkbox
        foreach (Language::get('BOOKING_OPTIONS', []) as $key => $label) {
            if (!$category->isEmpty($key)) {
                $fieldset->add('checkboxgroups', array(
                    'id' => $key,
                    'labelClass' => 'g-input icon-list',
                    'itemClass' => 'item',
                    'label' => $label,
                    'options' => $category->toSelect($key),
                    'value' => isset($index->{$key}) ? explode(',', $index->{$key}) : []
                ));
            }
        }
        // comment
        $fieldset->add('textarea', array(
            'id' => 'comment',
            'labelClass' => 'g-input icon-file',
            'itemClass' => 'item',
            'label' => '{LNG_Other}',
            'rows' => 3,
            'value' => $index->comment
        ));
        // status
        $fieldset->add('select', array(
            'id' => 'status',
            'labelClass' => 'g-input icon-star0',
            'itemClass' => 'item',
            'label' => '{LNG_Status}',
            'options' => Language::get('BOOKING_STATUS'),
            'disabled' => true,
            'value' => $index->status
        ));
        // reason
        $fieldset->add('text', array(
            'id' => 'reason',
            'labelClass' => 'g-input icon-question',
            'itemClass' => 'item',
            'label' => '{LNG_Reason}',
            'disabled' => true,
            'value' => $index->reason
        ));
        if ($canApprove != 0) {
            $fieldset = $form->add('fieldset', array(
                'class' => 'submit'
            ));
            if ($canEdit) {
                // submit
                $fieldset->add('submit', array(
                    'class' => 'button blue large icon-save border',
                    'value' => '{LNG_Save}'
                ));
            }
            $booking_status = Language::get('BOOKING_STATUS');
            // อนุมัติ
            $fieldset->add('button', array(
                'class' => 'button save large icon-valid',
                'id' => 'change_status1',
                'value' => $booking_status[1]
            ));
            // ไม่อนุมัติ
            $fieldset->add('button', array(
                'class' => 'button red large icon-invalid',
                'id' => 'change_status2',
                'value' => $booking_status[2]
            ));
        }
        // id
        $fieldset->add('hidden', array(
            'id' => 'id',
            'value' => $index->id
        ));
        // Javascript
        $form->script('initBookingApprove();');
        // คืนค่า HTML
        return $form->render();
    }
}
