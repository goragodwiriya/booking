<?php
/* config.php */
return array(
    'version' => '6.0.5',
    'web_title' => 'E-Booking',
    'web_description' => 'ระบบจองห้องประชุม',
    'timezone' => 'Asia/Bangkok',
    'member_status' => array(
        0 => 'สมาชิก',
        1 => 'ผู้ดูแลระบบ',
        2 => 'ผู้รับผิดชอบ'
    ),
    'color_status' => array(
        0 => '#259B24',
        1 => '#FF0000',
        2 => '#0E0EDA'
    ),
    'default_icon' => 'icon-calendar',
    'skin' => 'skin/booking',
    'theme_width' => 'wide',
    'booking_approve_status' => array(
        1 => 2
    ),
    'booking_approve_department' => array(
        1 => '1'
    ),
    'show_title_logo' => 0,
    'new_line_title' => 0,
    'header_bg_color' => '#769E51',
    'warpper_bg_color' => '#D2D2D2',
    'content_bg' => '#FFFFFF',
    'header_color' => '#FFFFFF',
    'footer_color' => '#7E7E7E',
    'logo_color' => '#000000',
    'login_header_color' => '#000000',
    'login_footer_color' => '#7E7E7E',
    'login_color' => '#000000',
    'login_bg_color' => '#D2D2D2'
);
