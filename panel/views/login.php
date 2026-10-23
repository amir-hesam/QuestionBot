<?php
if (!isset($error)) {
    $error = '';
}
if (!isset($panelEnabled)) {
    $panelEnabled = false;
}
if (!isset($panelError)) {
    $panelError = '';
}
if (!isset($flashes) || !is_array($flashes)) {
    $flashes = array();
}
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ورود به پنل مدیریت</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
<main class="login-card">
  <div class="login-brand">
    <span class="brand-mark">🤖</span>
    <div>
      <h1>پنل مدیریت ربات آزمون</h1>
      <p class="muted">ورود امن برای مدیریت دانش‌آموزان، آزمون‌ها و نتایج</p>
    </div>
  </div>

  <?php foreach ($flashes as $flash): ?>
    <div class="alert <?php echo Panel::h($flash[0]); ?>"><?php echo Panel::h($flash[1]); ?></div>
  <?php endforeach; ?>

  <?php if ($panelError !== ''): ?>
    <div class="alert err"><?php echo Panel::h($panelError); ?></div>
  <?php endif; ?>

  <?php if ($error !== ''): ?>
    <div class="alert err"><?php echo Panel::h($error); ?></div>
  <?php endif; ?>

  <?php if (!$panelEnabled && $panelError === ''): ?>
    <div class="alert warn">
      پنل وب غیرفعال است یا رمز آن منقضی شده.
      در تلگرام وارد ربات شوید و دستور <code>/panel</code> را بزنید یا دکمه «🔐 پنل وب» را انتخاب کنید.
    </div>
  <?php endif; ?>

  <form method="post" action="index.php" class="stack" autocomplete="on">
    <?php echo PanelAuth::csrfField(); ?>
    <label>
      نام کاربری
      <input type="text" name="user" value="<?php echo Panel::h(PanelAccess::username()); ?>" autocomplete="username" required>
    </label>
    <label>
      رمز عبور
      <input type="password" name="pass" autocomplete="current-password" required>
    </label>
    <button class="btn primary" type="submit"<?php echo $panelEnabled ? '' : ' disabled'; ?>>ورود به پنل</button>
  </form>

  <p class="login-help muted">
    رمز عبور را از پیام ربات دریافت می‌کنید. هر بار زدن دستور <code>/panel</code> رمز جدید می‌سازد و
    نشست‌های قبلی را خارج می‌کند.
  </p>
</main>
</body>
</html>
