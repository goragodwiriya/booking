<?php
/**
 * install/core-manifest.php — นิยาม "แกน" ที่ต้องเหมือนกันทุกโปรเจ็ค
 *
 * ใช้ร่วมกันโดย cli-check-core.php (เทียบ) และ cli-sync-core.php (กระจายจาก adminframework)
 * เพื่อให้สองเครื่องมือมองรายการเดียวกัน — แก้ที่นี่ที่เดียว
 *
 * นโยบาย: Now.js, Kotchasan, Gcms, modules/index, modules/export, เครื่องมือใน install/,
 * js/main.js, load.php, api.php, export.php, index.php ต้อง "เหมือนกันทุกโปรเจ็ค 100%"
 * ส่วนที่ต่างกันได้มีเฉพาะของที่เป็นของโปรเจ็คนั้นจริง ๆ (รายการ skip)
 *
 * ⚠️ โมดูลที่มีหน้าเว็บ "ไม่ได้อยู่ในโฟลเดอร์เดียว" — โค้ดอยู่ที่ modules/<ชื่อ>
 * แต่แม่แบบหน้าเว็บอยู่ที่ templates/<ชื่อ> ต้องใส่ทั้งคู่ ไม่งั้นเทมเพลตหลุดจากกัน
 * ได้เงียบ ๆ ทั้งที่ไฟล์ PHP รายงานว่า "ตรงกันทุกไฟล์"
 * modules/index ใช้แม่แบบที่ templates/ ชั้นบนสุดและ templates/settings — ยกเว้น templates/index.html
 * (โครงหน้าแอป) ที่บางโปรเจ็คยังเป็นรุ่นเก่าซึ่งต้องย้ายเองทีละโปรเจ็ค
 *
 * ⚠️ ของที่อยู่นอก Now/ แต่แกนพึ่งพา: Now/dist/richtext-editor.min.js ถูก build จาก js/components/editor
 * และเรียก js/components/editor/php/filebrowser.php ของโปรเจ็คนั้น · js/main.js โหลด sidebar.js,
 * topbar.js และ SocialLogin.js — ต้องไปพร้อมกัน ไม่งั้น bundle ใหม่คุยกับ backend รุ่นเก่า
 */
return [
    // ไฟล์/โฟลเดอร์ที่ถือว่าเป็นแกน
    'core' => ['Kotchasan', 'Gcms', 'Now', 'modules/index', 'modules/export', 'modules/inventory',
        'templates/inventory', 'modules/payment', 'templates/payment',
        'modules/pos', 'templates/pos', 'modules/ecommerce',
        'modules/shipping', 'templates/shipping',
        'templates/403.html', 'templates/404.html', 'templates/activate.html', 'templates/forgot.html',
        'templates/login.html', 'templates/profile.html', 'templates/register.html',
        'templates/reset-password.html', 'templates/users.html', 'templates/user-view.html', 'templates/settings',
        'js/components/editor', 'js/components/sidebar.js', 'js/components/topbar.js', 'js/components/SocialLogin.js',
        'js/main.js', 'install', 'load.php', 'api.php', 'export.php', 'index.php'],

    // โมดูลที่ "ไม่ใช่ทุกโปรเจ็คต้องมี" — โปรเจ็คที่ไม่ได้ติดตั้งไว้เลยไม่ถือว่าขาดไฟล์
    // (ต่างจาก skip : skip คือไม่เทียบเลย ส่วนนี้คือ "ถ้าไม่มีทั้งโมดูลก็ข้าม แต่ถ้ามีต้องเหมือน")
    'optional' => ['modules/inventory', 'templates/inventory', 'modules/payment', 'templates/payment',
        'modules/pos', 'templates/pos', 'modules/ecommerce',
        'modules/shipping', 'templates/shipping'],

    // ของโปรเจ็คนั้นเอง ไม่เทียบและไม่ทับ
    'skip' => ['Gcms/Config.php', 'install/database.sql', 'install/upgrade2.php',
        'install/settings', 'install/preflight-module.php', 'install/testdb-module.php', 'install/img',
        // สคีมารุ่นก่อนที่แต่ละโปรเจ็คคัดไว้ให้ชุดทดสอบ F3 ใช้ — ของใครของมัน
        'install/legacy',
        // ผลลัพธ์ build ของแต่ละโปรเจ็ค (source map) และ bundle เฉพาะโปรเจ็ค
        'Now/node_modules',
        // ส่วนของแกนที่โปรเจ็คนั้นแยกทางโดยตั้งใจ บรรทัดละหนึ่ง path — cli-sync-core/cli-check-core
        // ข้ามส่วนนั้นของโปรเจ็คนั้น (oms.in.th: install ทั้งโฟลเดอร์ ตัวติดตั้งชุดใหม่ 7.6.1)
        'install/core-own.txt',
        // ค่าตั้งของ FileBrowser (storages, writePermissions) เลือกเองต่อโปรเจ็ค — filebrowser.php มีค่าปริยายทุกคีย์
        'js/components/editor/php/config.php'],

    // แกนแบบย่อสำหรับโปรเจ็คตระกูล GCMS (CMS) ที่ใช้ Kotchasan + Now.js ร่วมกัน
    // แต่ Gcms/modules/index เป็นของตัวเอง — กระจายเฉพาะสองส่วนนี้
    'nowjs' => ['Kotchasan', 'Now'],

    // ไฟล์ที่คุณสมบัติของโปรเจ็คอยู่ในนั้น: ใช้ 3-way merge แทนการทับ เมื่อโปรเจ็คแก้ไว้
    // (ค่าปริยายของ load.php เช่น DEBUG/INIT_LANGUAGE · index.php ที่ใส่ CSS เพิ่ม)
    'merge' => ['load.php', 'index.php', 'js/main.js', 'api.php', 'export.php']
];
