<?php
/**
 * Student Telegram Mini App shell.
 *
 * Open through the bot menu button or a web_app inline button.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

function miniapp_h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if (!MiniApp::enabled()) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>مینی‌اپ غیرفعال است</title></head><body style="font-family:Tahoma,sans-serif;padding:32px;text-align:center">'
        . '<h1>مینی‌اپ غیرفعال است</h1><p>برای فعال‌سازی، گزینه miniapp.enabled را در config.php روی true بگذارید.</p>'
        . '</body></html>';
    exit;
}

$cfg = App::cfg('miniapp', array());
$title = is_array($cfg) && isset($cfg['title']) && trim((string) $cfg['title']) !== ''
    ? trim((string) $cfg['title'])
    : 'آزمون آنلاین';

$nonce = base64_encode(random_bytes(16));
$cssVersion = @filemtime(__DIR__ . '/assets/style.css');
$jsVersion = @filemtime(__DIR__ . '/assets/app.js');
$cssVersion = $cssVersion ? $cssVersion : 1;
$jsVersion = $jsVersion ? $jsVersion : 1;

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, no-cache, must-revalidate');
header(
    "Content-Security-Policy: default-src 'self'; "
    . "script-src 'self' 'nonce-" . $nonce . "' https://telegram.org; "
    . "style-src 'self'; img-src 'self' data:; font-src 'self'; "
    . "connect-src 'self'; base-uri 'none'; form-action 'none'"
);
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="theme-color" content="#070b16">
<title><?php echo miniapp_h($title); ?></title>
<script nonce="<?php echo miniapp_h($nonce); ?>">
(function () {
    try {
        var saved = window.localStorage.getItem('miniapp-theme');
        var dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        var theme = saved === 'light' || saved === 'dark' ? saved : (dark ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.style.colorScheme = theme;
    } catch (e) {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
})();
</script>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<link rel="stylesheet" href="assets/style.css?v=<?php echo (int) $cssVersion; ?>">
</head>
<body>
<div class="app">
  <header class="topbar">
    <div class="brand">
      <span class="brand-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 9.5 12 4l9 5.5-9 5.5-9-5.5Z"></path>
          <path d="M7 12v4.5c0 1.4 2.2 2.5 5 2.5s5-1.1 5-2.5V12"></path>
        </svg>
      </span>
      <span class="brand-text">
        <strong><?php echo miniapp_h($title); ?></strong>
        <small>پنل دانش‌آموز</small>
      </span>
    </div>
    <button class="icon-btn" type="button" id="themeBtn" aria-label="تغییر تم"></button>
  </header>

  <main id="view" class="view" aria-live="polite">
    <div class="boot">
      <div class="spinner" aria-hidden="true"></div>
      <p>در حال بارگذاری…</p>
    </div>
  </main>

  <nav id="bottomNav" class="bottom-nav hidden" aria-label="ناوبری اصلی">
    <button type="button" class="nav-item active" data-tab="exams">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <rect x="4" y="4" width="16" height="16" rx="3"></rect>
        <path d="M8 9h8M8 13h8M8 17h5"></path>
      </svg>
      <span>آزمون‌ها</span>
    </button>
    <button type="button" class="nav-item" data-tab="history">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M4 19V5"></path>
        <path d="M4 19h16"></path>
        <path d="M8 15v-4M12 15V7M16 15v-6"></path>
      </svg>
      <span>نتایج من</span>
    </button>
  </nav>

  <div id="toast" class="toast" role="status"></div>
</div>
<script src="assets/app.js?v=<?php echo (int) $jsVersion; ?>"></script>
</body>
</html>
