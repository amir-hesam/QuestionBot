<?php
if (!defined('TELEBOT_PANEL')) {
    exit;
}
$user = PanelAuth::user();
$nav = array(
    'dashboard' => 'داشبورد',
    'students' => 'دانش‌آموزان',
    'quizzes' => 'آزمون‌ها',
    'results' => 'نتایج',
    'broadcast' => 'پیام همگانی',
    'tools' => 'ابزارها',
);
if (!isset($flashes) || !is_array($flashes)) {
    $flashes = array();
}
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo Panel::h($title); ?> — پنل مدیریت</title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/app.js" defer></script>
</head>
<body>
<header class="topbar">
  <a class="brand" href="index.php">
    <span class="brand-mark">🤖</span>
    <span>پنل مدیریت ربات آزمون</span>
  </a>
  <nav class="nav">
    <?php foreach ($nav as $key => $label): ?>
      <a class="nav-link<?php echo $page === $key ? ' active' : ''; ?>" href="<?php echo Panel::h(Panel::url($key)); ?>"><?php echo Panel::h($label); ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="account">
    <span class="muted"><?php echo Panel::h($user); ?></span>
    <form method="post" action="<?php echo Panel::h(Panel::url('logout')); ?>" class="inline">
      <?php echo PanelAuth::csrfField(); ?>
      <button class="btn ghost small" type="submit">خروج</button>
    </form>
  </div>
</header>

<main class="wrap">
  <div class="page-head">
    <h1><?php echo Panel::h($title); ?></h1>
  </div>

  <?php foreach ($flashes as $flash): ?>
    <div class="alert <?php echo Panel::h($flash[0]); ?>"><?php echo Panel::h($flash[1]); ?></div>
  <?php endforeach; ?>

  <?php echo $content; ?>
</main>
</body>
</html>
