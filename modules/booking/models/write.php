<?php
/**
 * @filesource modules/booking/models/write.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Write;

use Gcms\Login;
use Kotchasan\File;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=booking-write
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านข้อมูลรายการที่เลือก
     * ถ้า $id = 0 หมายถึงรายการใหม่
     * คืนค่าข้อมูล object ไม่พบคืนค่า null
     *
     * @param int  $id     ID
     *
     * @return object|null
     */
    public static function get($id)
    {
        if (empty($id)) {
            // ใหม่
            return (object) array(
                'id' => 0
            );
        } else {
            // แก้ไข อ่านรายการที่เลือก
            $query = static::createQuery()
                ->from('rooms R')
                ->where(array('R.id', $id));
            $select = array('R.*');
            $n = 1;
            foreach (Language::get('ROOM_CUSTOM_TEXT', []) as $key => $label) {
                $query->join('rooms_meta M'.$n, 'LEFT', array(array('M'.$n.'.room_id', 'R.id'), array('M'.$n.'.name', $key)));
                $select[] = 'M'.$n.'.value '.$key;
                ++$n;
            }
            return $query->first($select);
        }
    }

    /**
     * บันทึกข้อมูลที่ส่งมาจากฟอร์ม (write.php)
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = [];
        // session, token, can_manage_room, ไม่ใช่สมาชิกตัวอย่าง
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            if (Login::notDemoMode($login) && Login::checkPermission($login, 'can_manage_room')) {
                try {
                    // ค่าที่ส่งมา
                    $save = array(
                        'name' => $request->post('name')->topic(),
                        'color' => $request->post('color')->filter('\#A-Z0-9'),
                        'detail' => $request->post('detail')->textarea()
                    );
                    $metas = [];
                    foreach (Language::get('ROOM_CUSTOM_TEXT', []) as $key => $label) {
                        $metas[$key] = $request->post($key)->topic();
                    }
                    $id = $request->post('id')->toInt();
                    // ตรวจสอบรายการที่เลือก
                    $index = self::get($id, $id == 0);
                    if ($index) {
                        if ($save['name'] == '') {
                            // ไม่ได้กรอก name
                            $ret['ret_name'] = 'Please fill in';
                        } else {
                            // Database
                            $db = $this->db();
                            // table
                            $table = $this->getTableName('rooms');
                            if ($index->id == 0) {
                                $save['id'] = $db->getNextId($table);
                            } else {
                                $save['id'] = $index->id;
                            }
                            // ไดเร็คทอรี่เก็บไฟล์
                            $dir = ROOT_PATH.DATA_FOLDER.'booking/';
                            // อัปโหลดไฟล์
                            foreach ($request->getUploadedFiles() as $item => $file) {
                                /* @var $file \Kotchasan\Http\UploadedFile */
                                if ($file->hasUploadFile()) {
                                    if (!File::makeDirectory($dir)) {
                                        // ไดเรคทอรี่ไม่สามารถสร้างได้
                                        $ret['ret_'.$item] = Language::replace('Directory %s cannot be created or is read-only.', DATA_FOLDER.'booking/');
                                    } elseif (!$file->validFileExt(self::$cfg->booking_img_typies)) {
                                        // ชนิดของไฟล์ไม่ถูกต้อง
                                        $ret['ret_'.$item] = Language::get('The type of file is invalid');
                                    } elseif ($item == 'picture') {
                                        try {
                                            $file->resizeImage(self::$cfg->booking_img_typies, $dir, $save['id'].'.jpg', self::$cfg->booking_w);
                                        } catch (\Exception $exc) {
                                            // ไม่สามารถอัปโหลดได้
                                            $ret['ret_'.$item] = Language::get($exc->getMessage());
                                        }
                                    }
                                } elseif ($file->hasError()) {
                                    // ข้อผิดพลาดการอัปโหลด
                                    $ret['ret_'.$item] = Language::get($file->getErrorMessage());
                                }
                            }
                            if (empty($ret)) {
                                if ($index->id == 0) {
                                    // ใหม่
                                    $db->insert($table, $save);
                                } else {
                                    // แก้ไข
                                    $db->update($table, $save['id'], $save);
                                }
                                // อัปเดต meta
                                $meta_table = $this->getTableName('rooms_meta');
                                $db->delete($meta_table, array('room_id', $save['id']), 0);
                                foreach ($metas as $key => $value) {
                                    if ($value != '') {
                                        $db->insert($meta_table, array(
                                            'room_id' => $save['id'],
                                            'name' => $key,
                                            'value' => $value
                                        ));
                                    }
                                }
                                // log
                                \Index\Log\Model::add($save['id'], 'booking', 'Save', '{LNG_Room} ID : '.$save['id'], $login['id']);
                                // คืนค่า
                                $ret['alert'] = Language::get('Saved successfully');
                                $ret['location'] = $request->getUri()->postBack('index.php', array('module' => 'booking-setup'));
                                // เคลียร์
                                $request->removeToken();
                            }
                        }
                    }
                } catch (\Kotchasan\InputItemException $e) {
                    $ret['alert'] = $e->getMessage();
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }
}
