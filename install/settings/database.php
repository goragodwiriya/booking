<?php
/* settings/database.php */

return array(
    'mysql' => array(
        'dbdriver' => 'mysql',
        'username' => 'root',
        'password' => '',
        'dbname' => 'booking',
        'prefix' => 'app'
    ),
    'tables' => array(
        'category' => 'category',
        'language' => 'language',
        'line' => 'line',
        'reservation' => 'reservation',
        'reservation_data' => 'reservation_data',
        'rooms' => 'rooms',
        'rooms_meta' => 'rooms_meta',
        'logs' => 'logs',
        'user' => 'user',
        'user_meta' => 'user_meta'
    )
);
