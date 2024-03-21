<?php
/**
 * @filesource modules/booking/views/write.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Write;

use Kotchasan\Html;
use Kotchasan\Language;

/**
 * module=booking-write
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * ฟอร์มสร้าง/แก้ไข ห้องประชุม
     *
     * @param object $index
     * @param array  $login
     *
     * @return string
     */
    public function render($index, $login)
    {
        $form = Html::create('form', array(
            'id' => 'setup_frm',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/booking/model/write/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ));
        $fieldset = $form->add('fieldset', array(
            'titleClass' => 'icon-write',
            'title' => '{LNG_Details of} {LNG_Room}'
        ));
        // name
        $fieldset->add('text', array(
            'id' => 'name',
            'labelClass' => 'g-input icon-edit',
            'itemClass' => 'item',
            'label' => '{LNG_Room name}',
            'maxlength' => 64,
            'value' => isset($index->name) ? $index->name : ''
        ));
        // color
        $fieldset->add('color', array(
            'id' => 'color',
            'labelClass' => 'g-input icon-color',
            'itemClass' => 'item',
            'label' => '{LNG_Color}',
            'value' => isset($index->color) ? $index->color : ''
        ));
        // detail
        $fieldset->add('textarea', array(
            'id' => 'detail',
            'labelClass' => 'g-input icon-file',
            'itemClass' => 'item',
            'label' => '{LNG_Detail}',
            'rows' => 3,
            'value' => isset($index->detail) ? $index->detail : ''
        ));
        foreach (Language::get('ROOM_CUSTOM_TEXT', []) as $key => $label) {
            $fieldset->add('text', array(
                'id' => $key,
                'labelClass' => 'g-input icon-edit',
                'itemClass' => 'item',
                'label' => $label,
                'value' => isset($index->{$key}) ? $index->{$key} : ''
            ));
        }
        // picture
        if (is_file(ROOT_PATH.DATA_FOLDER.'booking/'.$index->id.'.jpg')) {
            $img = WEB_URL.DATA_FOLDER.'booking/'.$index->id.'.jpg';
        } else {
            $img = WEB_URL.'modules/booking/img/noimage.png';
        }
        $fieldset->add('file', array(
            'id' => 'picture',
            'labelClass' => 'g-input icon-upload',
            'itemClass' => 'item',
            'label' => '{LNG_Image}',
            'comment' => '{LNG_Browse image uploaded, type :type} ({LNG_resized automatically})',
            'dataPreview' => 'imgPicture',
            'capture' => true,
            'previewSrc' => $img,
            'accept' => self::$cfg->booking_img_typies
        ));
        $fieldset = $form->add('fieldset', array(
            'class' => 'submit'
        ));
        // submit
        $fieldset->add('submit', array(
            'class' => 'button save large icon-save',
            'value' => '{LNG_Save}'
        ));
        // id
        $fieldset->add('hidden', array(
            'id' => 'id',
            'value' => $index->id
        ));
        \Gcms\Controller::$view->setContentsAfter(array(
            '/:type/' => implode(', ', self::$cfg->booking_img_typies)
        ));
        // คืนค่า HTML
        return $form->render();
    }
}
