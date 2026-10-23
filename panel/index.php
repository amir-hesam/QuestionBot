<?php
/**
 * Web admin panel front controller.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

PanelAuth::start();

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, no-cache, must-revalidate');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");

$page = Panel::get('page', 'dashboard');
if (!preg_match('/^[a-z]+$/', $page)) {
    $page = 'dashboard';
}

if ($page === 'logout') {
    Panel::requirePost();
    PanelAuth::verifyCsrf();
    PanelAuth::logout();
    PanelAuth::flash('ok', 'از پنل خارج شدید.');
    Panel::redirect('index.php');
}

if (!PanelAuth::check()) {
    $error = '';
    $panelState = null;
    $panelEnabled = false;
    $panelError = '';

    try {
        $panelState = PanelAccess::state();
        $panelEnabled = $panelState !== null && !PanelAccess::expired($panelState);
    } catch (Throwable $e) {
        $panelError = 'اتصال به دیتابیس برقرار نشد. تنظیمات config.php را بررسی کنید.';
        App::log('panel login state: ' . $e->getMessage());
    }

    if (Panel::isPost()) {
        if (!PanelAuth::csrfValid()) {
            $error = 'نشست منقضی شده است. صفحه را دوباره بارگذاری کنید.';
        } else {
            try {
                $result = PanelAuth::login(Panel::post('user'), (string) Panel::rawPost('pass'));
                if ($result[0]) {
                    Panel::redirect('index.php');
                }
                $error = $result[1];
            } catch (Throwable $e) {
                App::log('panel login: ' . $e->getMessage());
                $error = 'خطا در ورود. اتصال دیتابیس را بررسی کنید.';
            }
        }
    }

    $flashes = PanelAuth::flashes();
    include __DIR__ . '/views/login.php';
    exit;
}

if (Panel::isPost()) {
    PanelAuth::verifyCsrf();
}

if ($page === 'export') {
    try {
        ResultsExport::stream(Panel::intGet('quiz', 0), Panel::get('q', ''));
    } catch (Throwable $e) {
        App::log('panel export: ' . $e->getMessage());
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'export failed';
    }
    exit;
}

$allowed = array(
    'dashboard',
    'students',
    'student',
    'quizzes',
    'quiz',
    'question',
    'results',
    'attempt',
    'broadcast',
    'tools',
);
if (!in_array($page, $allowed, true)) {
    $page = 'dashboard';
}

define('TELEBOT_PANEL', true);

$pageTitle = '';
$content = '';
ob_start();
try {
    include __DIR__ . '/pages/' . $page . '.php';
} catch (Throwable $e) {
    App::log('panel page ' . $page . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $content = '';
    ob_end_clean();
    ob_start();
    echo '<div class="alert err">خطایی رخ داد. لطفاً دوباره تلاش کنید یا error.log را بررسی کنید.</div>';
}
$content = ob_get_clean();

$titles = array(
    'dashboard' => 'داشبورد',
    'students' => 'دانش‌آموزان',
    'student' => 'دانش‌آموز',
    'quizzes' => 'آزمون‌ها',
    'quiz' => 'جزئیات آزمون',
    'question' => 'سوال',
    'results' => 'نتایج',
    'attempt' => 'جزئیات تلاش',
    'broadcast' => 'پیام همگانی',
    'tools' => 'ابزارها',
);
$title = $pageTitle !== '' ? $pageTitle : (isset($titles[$page]) ? $titles[$page] : 'پنل مدیریت');
$flashes = PanelAuth::flashes();

include __DIR__ . '/views/layout.php';
