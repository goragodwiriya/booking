<?php
/**
 * @filesource Gcms/Config.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Gcms;

/**
 * Config Class สำหรับ GCMS
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Config extends \Kotchasan\Config
{
    /**
     * กำหนดอายุของแคช (วินาที)
     * 0 หมายถึงไม่มีการใช้งานแคช
     *
     * @var int
     */
    public $cache_expire = 5;
    /**
     * สีของสมาชิกตามสถานะ
     *
     * @var array
     */
    public $color_status = [
        0 => '#259B24',
        1 => '#FF0000',
        2 => '#0000FF'
    ];
    /**
     * ถ้ากำหนดเป็น true บัญชี Facebook จะเป็นบัญชีตัวอย่าง
     * ได้รับสถานะแอดมิน (สมาชิกใหม่) แต่อ่านได้อย่างเดียว
     *
     * @var bool
     */
    public $demo_mode = false;
    /**
     * App ID สำหรับการเข้าระบบด้วย Facebook https://gcms.in.th/howto/การขอ_app_id_จาก_facebook.html
     *
     * @var string
     */
    public $facebook_appId = '';
    /**
     * Client ID สำหรับการเข้าระบบโดย Google
     *
     * @var string
     */
    public $google_client_id = '';
    /**
     * รายชื่อฟิลด์จากตารางสมาชิก สำหรับตรวจสอบการ login
     *
     * @var array
     */
    public $login_fields = ['username'];
    /**
     * สถานะสมาชิก
     * 0 สมาชิกทั่วไป
     * 1 ผู้ดูแลระบบ
     * 2 เจ้าหน้าที่
     *
     * @var array
     */
    public $member_status = [
        0 => 'สมาชิก',
        1 => 'ผู้ดูแลระบบ',
        2 => 'เจ้าหน้าที่'
    ];
    /**
     * คีย์สำหรับการเข้ารหัส ควรแก้ไขให้เป็นรหัสของตัวเอง
     * ตัวเลขหรือภาษาอังกฤษเท่านั้น ไม่น้อยกว่า 10 ตัว
     *
     * @var string
     */
    public $password_key = '1234567890';
    /**
     * สามารถขอรหัสผ่านในหน้าเข้าระบบได้
     *
     * @var bool
     */
    public $user_forgot = true;
    /**
     * บุคคลทั่วไป สามารถสมัครสมาชิกได้
     *
     * @var bool
     */
    public $user_register = true;
    /**
     * ตั้งค่าการเข้าระบบของสมาชิกใหม่
     * 1 สมัครสมาชิกแล้วเข้าระบบได้ทันที (ค่าเริ่มต้น)
     * 0 สมัครสมาชิกแล้วยังไม่สามารถเข้าระบบได้ ต้องรอแอดมินอนุมัติ
     *
     * @var int
     */
    public $new_members_active = 1;
    /**
     * ส่งอีเมลต้อนรับ เมื่อบุคคลทั่วไปสมัครสมาชิก
     *
     * @var bool
     */
    public $welcome_email = true;
    /**
     * ข้อความแสดงในหน้า login
     *
     * @var string
     */
    public $login_message = '';
    /**
     * ชื่อคลาสของข้อความแสดงในหน้า login warning,tip,message
     *
     * @var string
     */
    public $login_message_style = 'hidden';
    /**
     * Channel ID
     * จาก Line Login
     *
     * @var string
     */
    public $line_channel_id = '';
    /**
     * Channel secret
     * จาก Line Login
     *
     * @var string
     */
    public $line_channel_secret = '';
    /**
     * Bot basic ID
     * จาก Messaging API
     *
     * @var string
     */
    public $line_official_account = '';
    /**
     * Channel access token (long-lived)
     * จาก Messaging API
     *
     * @var string
     */
    public $line_channel_access_token = '';
    /**
     * Bot Username
     * Bot Username จาก Telegram
     *
     * @var string
     */
    public $telegram_bot_username = '';
    /**
     * Chat ID
     * Bot Chat ID จาก Telegram
     *
     * @var string
     */
    public $telegram_chat_id = '';
    /**
     * Bot token
     * API Token จาก Telegram
     *
     * @var string
     */
    public $telegram_bot_token = '';
    /**
     * รายการหมวดหมู่ของสมาชิก ที่ต้องระบุ
     *
     * @var array
     */
    public $categories_required = [];
    /**
     * รายการหมวดหมู่ที่สมาชิกไม่สามารถแก้ไขได้
     *
     * @var array
     */
    public $categories_disabled = [];
    /**
     * รายการหมวดหมู่สมาชิกที่สามารถมีได้หลายรายการ
     *
     * @var array
     */
    public $categories_multiple = [];
    /**
     * แผนกเริ่มต้นสำหรับสมาชิกใหม่ ใช้ในกรณีที่สมาชิกจำเป็นต้องระบุแผนก
     *
     * @var string
     */
    public $default_department = '';
    /**
     * รายการรูปภาพอัปโหลดของสมาชิก และ ชื่อ
     *
     * @var array
     */
    public $member_images = [
        'avatar' => '{LNG_Avatar}',
        'signature' => '{LNG_Signature}'
    ];
    /**
     * ชนิดของไฟล์รูปภาพของสมาชิกที่รองรับ
     *
     * @var array
     */
    public $member_img_typies = ['jpg', 'jpeg', 'png', 'webp'];
    /**
     * ขนาดรูปภาพสมาชิกที่จัดเก็บ (พิกเซล)
     *
     * @var int
     */
    public $member_img_size = 250;
    /**
     * ชนิดของไฟล์รูปภาพที่รองรับ (ค่าเรี่มต้น)
     *
     * @var array
     */
    public $img_typies = ['jpg', 'jpeg', 'png', 'webp'];
    /**
     * ขนาดรูปภาพที่จัดเก็บ (พิกเซล)
     * สำหรับรูปภาพทั่วไป
     *
     * @var int
     */
    public $stored_img_size = 800;
    /**
     * ชนิดของไฟล์รูปภาที่จัดเก็บ
     * ต้องมี . ด้านหน้าด้วย
     *
     * @var array
     */
    public $stored_img_type = '.webp';
    /**
     * กำหนดให้สมาชิกต้องยอมรับเงื่อนไขก่อนสมัครสมาชิกหรือไม่
     * ควรตั้งค่าเป็น true หากต้องการให้สมาชิกยอมรับเงื่อนไขก่อนสมัครสมาชิก
     * ควรตั้งค่าเป็น false หากไม่ต้องการให้สมาชิกยอมรับเงื่อนไขก่อนสมัครสมาชิก
     * ค่าเริ่มต้นคือ true
     * @var bool
     */
    public $require_terms_acceptance = true;
    /**
     * เวลาหมดอายุของ Token ในกระบวนการ login (วินาที)
     * 0 = ตรวจสอบกับฐานข้อมูลเสมอ
     * 3600 = 1 ชม.
     *
     * @var int
     */
    public $token_login_expire_time = 3600;
    /**
     * กำหนดเวลาในการขอ OTP ครั้งต่อไป เป็นวินาที
     *
     * @var int
     */
    public $otp_request_timeout = 300;
    /**
     * JWT secret used for signing access tokens. Set a long random value in production.
     * If empty, JWT will not be issued by the login API.
     *
     * @var string
     */
    public $jwt_secret = '';

    /**
     * JWT access token lifetime in seconds (default 15 minutes).
     *
     * @var int
     */
    public $jwt_ttl = 900;

    /**
     * Whether to set access_token as HttpOnly secure cookie on login (default true).
     *
     * @var bool
     */
    public $jwt_cookie = true;

    /**
     * Refresh token lifetime in seconds (used for documentation purposes).
     * Refresh token persistence and rotation handled by user->token field.
     *
     * @var int
     */
    public $refresh_ttl = 604800; // 7 days

    /**
     * API token for authentication.
     *
     * @var array
     */
    public $api_tokens = [];

    /**
     * API secret for signature validation.
     *
     * @var string
     */
    public $api_secret = '';

    /**
     * Allowed IP addresses for API access.
     *
     * @var array
     */
    public $api_ips = ['0.0.0.0'];

    /**
     * CORS origin setting for API.
     *
     * @var string
     */
    public $api_cors = '';

    /**
     * กำหนดค่าคีย์ของ Login session ระบุให้แตกต่างกันในแต่ละแอพพลิเคชั่น หากต้องการให้แยกจากกัน
     * ค่าเริ่มต้นคือ 'login'
     * @var string
     */
    public $session_key = '';

    /**
     * หน่วยสกุลเงิน
     *
     * @var string
     */
    public $currency_unit = 'THB';

    /**
     * สถานะการจองที่แสดงในปฏิทิน
     *
     * @var array
     */
    public $booking_calendar_status = [1];
    /**
     * การอนุมัติหรือแก้ไขการจองห้อง
     *
     * @var int
     */
    public $booking_approving = 0;
    /**
     * การยกเลิกการจองห้อง
     *
     * @var int
     */
    public $booking_cancellation = 0;
    /**
     * สถานะยกเลิกการจองห้อง
     *
     * @var int
     */
    public $booking_cancled_status = 3;
    /**
     * ระดับการอนุมัติ
     *
     * @var int
     */
    public $booking_approve_level = 1;
    /**
     * สถานะอนุมัติ
     *
     * @var array
     */
    public $booking_approve_status = [
        1 => 0
    ];
    /**
     * แผนกผู้อนุมัติ
     *
     * @var array
     */
    public $booking_approve_department = [
        1 => '1'
    ];
    /**
     * สถานะของรายการที่สามารถลบโดยผู้จองได้
     *
     * @var array
     */
    public $booking_delete = [3];
}
