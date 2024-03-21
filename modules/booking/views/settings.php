<?php
/**
 * @filesource modules/booking/views/settings.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Settings;

use Kotchasan\Html;
use Kotchasan\Language;

/**
 * module=booking-settings
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * ฟอร์มตั้งค่า
     *
     * @return string
     */
    public function render()
    {
        $booleans = Language::get('BOOLEANS');
        // form
        $form = Html::create('form', array(
            'id' => 'setup_frm',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/booking/model/settings/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ));
        $fieldset = $form->add('fieldset', array(
            'titleClass' => 'icon-config',
            'title' => '{LNG_Module settings}'
        ));
        // booking_login_type
        $fieldset->add('select', array(
            'id' => 'booking_login_type',
            'labelClass' => 'g-input icon-visited',
            'itemClass' => 'item',
            'label' => '{LNG_Booking calendar}/{LNG_Book a room}',
            'options' => Language::get('LOGIN_TYPIES'),
            'value' => isset(self::$cfg->booking_login_type) ? self::$cfg->booking_login_type : 0
        ));
        // booking_w
        $fieldset->add('text', array(
            'id' => 'booking_w',
            'labelClass' => 'g-input icon-width',
            'itemClass' => 'item',
            'label' => '{LNG_Size of} {LNG_Image} ({LNG_Width})',
            'comment' => '{LNG_Image size is in pixels} ({LNG_resized automatically})',
            'value' => isset(self::$cfg->booking_w) ? self::$cfg->booking_w : 600
        ));
        // booking_approving
        $fieldset->add('select', array(
            'id' => 'booking_approving',
            'labelClass' => 'g-input icon-write',
            'itemClass' => 'item',
            'label' => '{LNG_Approving/editing reservations}',
            'options' => Language::get('APPROVING_RESERVATIONS'),
            'value' => isset(self::$cfg->booking_approving) ? self::$cfg->booking_approving : 0
        ));
        // booking_cancellation
        $fieldset->add('select', array(
            'id' => 'booking_cancellation',
            'labelClass' => 'g-input icon-warning',
            'itemClass' => 'item',
            'label' => '{LNG_Cancellation}',
            'options' => Language::get('CANCEL_RESERVATIONS'),
            'value' => isset(self::$cfg->booking_cancellation) ? self::$cfg->booking_cancellation : 0
        ));
        // booking_delete
        $fieldset->add('select', array(
            'id' => 'booking_delete',
            'labelClass' => 'g-input icon-delete',
            'itemClass' => 'item',
            'label' => '{LNG_Delete items that have been canceled by the booker}',
            'options' => $booleans,
            'value' => isset(self::$cfg->booking_delete) ? self::$cfg->booking_delete : 0
        ));
        $fieldset = $form->add('fieldset', array(
            'id' => 'verfied',
            'titleClass' => 'icon-verfied',
            'title' => '{LNG_Approval}'
        ));
        // booking_approve_level
        $fieldset->add('select', array(
            'id' => 'booking_approve_level',
            'labelClass' => 'g-input icon-menus',
            'itemClass' => 'item',
            'label' => '{LNG_Approval}',
            'options' => $booleans,
            'value' => count(self::$cfg->booking_approve_status)
        ));
        // หมวดหมู่
        $category = \Index\Category\Model::init();
        $groups = $fieldset->add('groups');
        // booking_approve_status
        $groups->add('select', array(
            'id' => 'booking_approve_status1',
            'name' => 'booking_approve_status[1]',
            'labelClass' => 'g-input icon-star0',
            'itemClass' => 'width50',
            'label' => '{LNG_Approval} ({LNG_Member status})',
            'options' => self::$cfg->member_status,
            'value' => empty(self::$cfg->booking_approve_status[1]) ? 0 : self::$cfg->booking_approve_status[1]
        ));
        // booking_approve_department
        $groups->add('select', array(
            'id' => 'booking_approve_department1',
            'name' => 'booking_approve_department[1]',
            'labelClass' => 'g-input icon-group',
            'itemClass' => 'width50',
            'label' => $category->name('department'),
            'options' => $category->toSelect('department'),
            'value' => empty(self::$cfg->booking_approve_department[1]) ? '' : self::$cfg->booking_approve_department[1]
        ));
        $fieldset = $form->add('fieldset', array(
            'titleClass' => 'icon-comments',
            'title' => '{LNG_Notification}'
        ));
        // booking_notifications
        $fieldset->add('select', array(
            'id' => 'booking_notifications',
            'labelClass' => 'g-input icon-email',
            'itemClass' => 'item',
            'label' => '{LNG_Notify relevant parties when booking details are modified by customers}',
            'options' => Language::get('BOOLEANS'),
            'value' => self::$cfg->booking_notifications
        ));
        $fieldset = $form->add('fieldset', array(
            'class' => 'submit'
        ));
        // submit
        $fieldset->add('submit', array(
            'class' => 'button save large icon-save',
            'value' => '{LNG_Save}'
        ));
        // Javascript
        $form->script('initBookingSettings();');
        // คืนค่า HTML
        return $form->render();
    }
}
