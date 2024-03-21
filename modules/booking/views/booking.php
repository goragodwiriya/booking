<?php
/**
 * @filesource modules/booking/views/booking.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Booking;

use Kotchasan\Html;
use Kotchasan\Language;

/**
 * module=booking-booking
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Booking\Tools\View
{
    /**
     * ฟอร์มสร้าง/แก้ไข การจอง (user)
     *
     * @param object $index
     * @param array $login
     *
     * @return string
     */
    public function render($index, $login)
    {
        $form = Html::create('form', array(
            'id' => 'setup_frm',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/booking/model/booking/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ));
        $fieldset = $form->add('fieldset', array(
            'title' => '{LNG_Details of} {LNG_Booking} '.($index->id > 0 ? self::toStatus((array) $index, true) : '')
        ));
        $groups = $fieldset->add('groups');
        // room_id
        $rooms = \Booking\Room\Model::toSelect(true, $index->room_id);
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
            'value' => isset($index->topic) ? $index->topic : ''
        ));
        $groups = $fieldset->add('groups');
        // attendees
        $groups->add('number', array(
            'id' => 'attendees',
            'labelClass' => 'g-input icon-group',
            'itemClass' => 'width50',
            'label' => '{LNG_Attendees number}',
            'value' => isset($index->attendees) ? $index->attendees : null
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
        // member_id
        $groups->add('hidden', array(
            'id' => 'member_id',
            'value' => $index->member_id
        ));
        // phone
        $groups->add('text', array(
            'id' => 'phone',
            'labelClass' => 'g-input icon-phone',
            'itemClass' => 'width50',
            'label' => '{LNG_Phone}',
            'maxlength' => 32,
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
            if ($key != 'department' && !$category->isEmpty($key)) {
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
            'value' => isset($index->comment) ? $index->comment : ''
        ));
        $fieldset = $form->add('fieldset', array(
            'class' => 'submit'
        ));
        // submit
        $fieldset->add('submit', array(
            'class' => 'button ok large icon-save',
            'value' => '{LNG_Save}'
        ));
        // id
        $fieldset->add('hidden', array(
            'id' => 'id',
            'value' => $index->id
        ));
        // คืนค่า HTML
        return $form->render();
    }
}
