<?php
/**
 * ============================================================
 *  تنظیمات ربات آزمون تستی
 * ------------------------------------------------------------
 *  این فایل را با اطلاعات واقعی خود پر کنید.
 *  هرگز این فایل را در جای عمومی منتشر نکنید.
 * ============================================================
 */

if (!defined('TELEBOT')) {
    http_response_code(403);
    exit('Forbidden');
}

return array(

    // توکنی که از @BotFather می‌گیرید
    'token' => 'PASTE_YOUR_BOT_TOKEN_HERE',

    // اطلاعات دیتابیس MySQL که در cPanel ساخته‌اید
    'db' => array(
        'host' => 'localhost',
        'name' => 'cpaneluser_telebot',
        'user' => 'cpaneluser_telebot',
        'pass' => 'CHANGE_ME_DB_PASSWORD',
    ),

    // شناسه عددی تلگرام ادمین‌ها (از @userinfobot بگیرید)
    // می‌توانید چندتا بنویسید: array(111111111, 222222222)
    'admin_ids' => array(
        123456789,
    ),

    // یک رشته تصادفی برای امنیت وب‌هوک
    // فقط حروف انگلیسی، عدد، _ و - مجاز است
    'secret_token' => 'CHANGE_ME_TO_RANDOM_SECRET_12345',

    // کلید دسترسی به install.php و export.php و poll.php
    'admin_key' => 'CHANGE_ME_ADMIN_KEY_67890',

    // حالت دریافت پیام: 'webhook' یا 'poll'
    'mode' => 'webhook',

    // آدرس وب‌هوک؛ خالی بگذارید تا خودکار تشخیص داده شود
    'webhook_url' => '',

    // منطقه زمانی
    'timezone' => 'Asia/Tehran',

    // طول کد خودکار دانش‌آموزی
    'code_length' => 6,
);
