<?php
/**
 * One-time installer / diagnostics panel.
 * Open: install.php?key=YOUR_ADMIN_KEY
 */

require __DIR__ . '/app/bootstrap.php';

function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url()
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $dir = str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/'));
    $dir = rtrim($dir, '/');
    return $scheme . '://' . $host . $dir;
}

$key = isset($_GET['key']) ? (string) $_GET['key'] : '';
$adminKey = (string) App::cfg('admin_key', '');
if ($adminKey === '' || $key === '' || !hash_equals($adminKey, $key)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "403 Forbidden\n\nAdd ?key=YOUR_ADMIN_KEY to the URL (see config.php).";
    exit;
}

$action = isset($_GET['action']) ? (string) $_GET['action'] : '';
$messages = array();

if ($action !== '') {
    try {
        $db = App::db();
        if ($action === 'migrate') {
            $db->migrate();
            $messages[] = array('ok', 'جدول‌ها با موفقیت ساخته/به‌روزرسانی شدند.');
        } elseif ($action === 'webhook') {
            $url = isset($_GET['url']) && $_GET['url'] !== ''
                ? (string) $_GET['url']
                : (string) App::cfg('webhook_url', '');
            if ($url === '') {
                $url = base_url() . '/index.php';
            }
            if (strpos($url, 'https://') !== 0) {
                throw new RuntimeException('آدرس وب‌هوک باید با https:// شروع شود.');
            }
            $res = App::tg()->setWebhook($url, (string) App::cfg('secret_token', ''));
            if (empty($res['ok'])) {
                throw new RuntimeException('setWebhook: ' . (isset($res['description']) ? $res['description'] : 'unknown error'));
            }
            $messages[] = array('ok', 'وب‌هوک ثبت شد: ' . $url);
        } elseif ($action === 'deletewebhook') {
            $res = App::tg()->deleteWebhook();
            if (empty($res['ok'])) {
                throw new RuntimeException('deleteWebhook: ' . (isset($res['description']) ? $res['description'] : 'unknown error'));
            }
            $messages[] = array('ok', 'وب‌هوک حذف شد. حالا می‌توانید از حالت poll استفاده کنید.');
        } elseif ($action === 'me') {
            $res = App::tg()->getMe();
            $messages[] = array(empty($res['ok']) ? 'err' : 'ok', json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } elseif ($action === 'info') {
            $res = App::tg()->getWebhookInfo();
            $messages[] = array(empty($res['ok']) ? 'err' : 'ok', json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $messages[] = array('err', 'عملیات ناشناخته.');
        }
    } catch (Throwable $e) {
        $messages[] = array('err', $e->getMessage());
    }
}

$phpOk = version_compare(PHP_VERSION, '7.4.0', '>=');
$ext = array(
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'curl' => extension_loaded('curl'),
    'json' => extension_loaded('json'),
    'mbstring' => extension_loaded('mbstring'),
    'openssl' => extension_loaded('openssl'),
);

$cfg = App::cfg();
$token = isset($cfg['token']) ? (string) $cfg['token'] : '';
$dbCfg = isset($cfg['db']) && is_array($cfg['db']) ? $cfg['db'] : array();
$tokenOk = $token !== '' && strpos($token, 'PASTE_') === false && strpos($token, ':') !== false;
$dbOk = !empty($dbCfg['name']) && strpos((string) $dbCfg['name'], 'cpaneluser_') !== 0
    && strpos((string) $dbCfg['pass'], 'CHANGE_ME') === false;
$secretOk = strlen((string) App::cfg('secret_token', '')) >= 16
    && strpos((string) App::cfg('secret_token', ''), 'CHANGE_ME') === false;
$admins = (array) App::cfg('admin_ids', array());
$adminOk = count($admins) > 0 && (string) $admins[0] !== '123456789';
$mode = (string) App::cfg('mode', 'webhook');

$dbStatus = null;
$tables = 0;
try {
    $db = App::db();
    $val = $db->val('SELECT DATABASE()');
    $tables = (int) $db->val('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()', array(), 0);
    $dbStatus = 'متصل به دیتابیس «' . $val . '» — ' . Farsi::digits($tables) . ' جدول';
} catch (Throwable $e) {
    $dbStatus = 'خطا: ' . $e->getMessage();
}

$cron = base_url();
$links = array(
    'migrate' => 'install.php?key=' . rawurlencode($key) . '&action=migrate',
    'webhook' => 'install.php?key=' . rawurlencode($key) . '&action=webhook',
    'deletewebhook' => 'install.php?key=' . rawurlencode($key) . '&action=deletewebhook',
    'info' => 'install.php?key=' . rawurlencode($key) . '&action=info',
    'me' => 'install.php?key=' . rawurlencode($key) . '&action=me',
);
$cronFinish = $cron . '/cron_finish.php?key=' . rawurlencode($key);
$pollUrl = $cron . '/poll.php?key=' . rawurlencode($key);
$exportUrl = $cron . '/export.php?key=' . rawurlencode($key);

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>نصب ربات آزمون</title>
<style>
 body{font-family:Tahoma,Arial,sans-serif;background:#f4f6fb;color:#222;margin:0;padding:24px}
 .wrap{max-width:820px;margin:0 auto}
 .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.06);padding:18px;margin-bottom:16px}
 h1{font-size:22px;margin:0 0 12px}
 h2{font-size:16px;margin:0 0 10px}
 table{width:100%;border-collapse:collapse;font-size:14px}
 td{padding:7px 4px;border-bottom:1px solid #eee;vertical-align:top}
 .ok{color:#12833b}.err{color:#c02626}.warn{color:#a86a00}
 code{background:#f1f3f7;padding:2px 5px;border-radius:4px;direction:ltr;display:inline-block;word-break:break-all;font-size:12px}
 a.btn{display:inline-block;background:#2b6cb0;color:#fff;text-decoration:none;padding:8px 13px;border-radius:8px;margin:3px}
 a.btn.alt{background:#4a5568}
 .msg{padding:10px 12px;border-radius:8px;margin-bottom:10px;white-space:pre-wrap;font-size:13px}
 .msg.ok{background:#e6f6ec}.msg.err{background:#fdeaea}
 ul{margin:6px 0;padding-inline-start:20px;font-size:13px;line-height:1.9}
</style>
</head>
<body>
<div class="wrap">
 <div class="card">
  <h1>🤖 نصب ربات آزمون تستی</h1>
  <?php foreach ($messages as $m): ?>
   <div class="msg <?php echo h($m[0]); ?>"><?php echo h($m[1]); ?></div>
  <?php endforeach; ?>
  <p style="font-size:13px;color:#555;margin:0">
   این صفحه اطلاعات حساس را نمایش نمی‌دهد. پس از نصب کامل، آن را از هاست حذف یا دسترسی‌اش را ببندید.
  </p>
  <p style="margin:12px 0 0">
   <a class="btn" href="<?php echo h($links['migrate']); ?>">۱) ساخت جدول‌ها</a>
   <a class="btn" href="<?php echo h($links['webhook']); ?>">۲) ثبت وب‌هوک</a>
   <a class="btn alt" href="<?php echo h($links['me']); ?>">تست توکن</a>
   <a class="btn alt" href="<?php echo h($links['info']); ?>">وضعیت وب‌هوک</a>
   <a class="btn alt" href="<?php echo h($links['deletewebhook']); ?>">حذف وب‌هوک</a>
  </p>
 </div>

 <div class="card">
  <h2>وضعیت</h2>
  <table>
   <tr><td>نسخه PHP</td><td class="<?php echo $phpOk ? 'ok' : 'err'; ?>"><?php echo h(PHP_VERSION); ?></td></tr>
   <tr><td>افزونه‌ها</td><td>
    <?php foreach ($ext as $name => $on): ?>
     <span class="<?php echo $on ? 'ok' : ($name === 'curl' || $name === 'mbstring' ? 'warn' : 'err'); ?>"><?php echo h($name); ?><?php echo $on ? ' ✓' : ' ✗'; ?></span>
    <?php endforeach; ?>
   </td></tr>
   <tr><td>توکن ربات</td><td class="<?php echo $tokenOk ? 'ok' : 'err'; ?>"><?php echo $tokenOk ? 'تنظیم شده' : 'تنظیم نشده'; ?></td></tr>
   <tr><td>اطلاعات دیتابیس</td><td class="<?php echo $dbOk ? 'ok' : 'err'; ?>"><?php echo $dbOk ? 'تنظیم شده' : 'تنظیم نشده'; ?></td></tr>
   <tr><td>دیتابیس</td><td class="<?php echo $tables > 0 ? 'ok' : 'err'; ?>"><?php echo h($dbStatus); ?></td></tr>
   <tr><td>کلید مخفی وب‌هوک</td><td class="<?php echo $secretOk ? 'ok' : 'err'; ?>"><?php echo $secretOk ? 'مناسب' : 'ضعیف یا پیش‌فرض'; ?></td></tr>
   <tr><td>ادمین‌ها</td><td class="<?php echo $adminOk ? 'ok' : 'err'; ?>"><?php echo Farsi::digits(count($admins)); ?> عدد</td></tr>
   <tr><td>حالت دریافت</td><td><code><?php echo h($mode); ?></code></td></tr>
  </table>
 </div>

 <div class="card">
  <h2>کران‌جاب‌ها (cPanel → Cron Jobs)</h2>
  <p style="font-size:13px">هر دقیقه (برای پایان خودکار آزمون‌های زمان‌دار):</p>
  <code><?php echo h('wget -q -O /dev/null "' . $cronFinish . '"'); ?></code>
  <p style="font-size:13px;margin-top:12px">اگر حالت <code>poll</code> را انتخاب کرده‌اید، هر دقیقه این را هم اجرا کنید:</p>
  <code><?php echo h('wget -q -O /dev/null "' . $pollUrl . '"'); ?></code>
  <p style="font-size:13px;margin-top:12px">خروجی CSV نتایج:</p>
  <code><?php echo h($exportUrl); ?></code>
 </div>

 <div class="card">
  <h2>ترتیب کار</h2>
  <ul>
   <li>در cPanel یک دیتابیس MySQL و یک کاربر بسازید و اطلاعات را در <code>config.php</code> وارد کنید.</li>
   <li>توکن ربات را از <code>@BotFather</code> بگیرید و در <code>config.php</code> بگذارید.</li>
   <li>شناسه عددی خودتان را از <code>@userinfobot</code> بگیرید و در <code>admin_ids</code> بگذارید.</li>
   <li>کلید <code>admin_key</code> و <code>secret_token</code> را به رشته‌های تصادفی طولانی تغییر دهید.</li>
   <li>دکمه «ساخت جدول‌ها» را بزنید.</li>
   <li>دکمه «ثبت وب‌هوک» را بزنید (هاست باید SSL داشته باشد).</li>
   <li>در تلگرام به ربات <code>/start</code> بفرستید و با <code>/newquiz</code> آزمون بسازید.</li>
   <li>با <code>/addstudent</code> برای هر دانش‌آموز کد بسازید و کد را به او بدهید.</li>
   <li>کران‌جاب پایان خودکار را تنظیم کنید.</li>
   <li>در پایان، <code>install.php</code> را حذف کنید.</li>
  </ul>
 </div>
</div>
</body>
</html>
