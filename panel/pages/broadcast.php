<?php
if (!defined('TELEBOT_PANEL')) {
    exit;
}

$pageTitle = 'پیام همگانی';
$db = App::db();
$errors = array();
$result = null;
$message = Panel::isPost() ? Panel::rawPost('message') : '';
$audience = Panel::isPost() ? Panel::post('audience') : 'all';
if (!in_array($audience, array('all', 'students'), true)) {
    $audience = 'all';
}

$allCount = (int) $db->val('SELECT COUNT(*) FROM bot_users', array(), 0);
$studentCount = (int) $db->val(
    'SELECT COUNT(DISTINCT locked_telegram_id) FROM student_codes WHERE is_active = 1 AND locked_telegram_id IS NOT NULL',
    array(),
    0
);

if (Panel::isPost()) {
    if (trim($message) === '') {
        $errors[] = 'متن پیام خالی است.';
    } elseif (Farsi::len($message) > 4000) {
        $errors[] = 'متن پیام حداکثر ۴۰۰۰ کاراکتر است.';
    }

    if (count($errors) === 0) {
        if ($audience === 'students') {
            $rows = $db->all(
                'SELECT DISTINCT locked_telegram_id FROM student_codes
                  WHERE is_active = 1 AND locked_telegram_id IS NOT NULL'
            );
        } else {
            $rows = $db->all('SELECT telegram_id FROM bot_users');
        }

        $ok = 0;
        $fail = 0;
        $sendText = Farsi::esc(trim($message));
        foreach ($rows as $row) {
            $chatId = $audience === 'students' ? $row['locked_telegram_id'] : $row['telegram_id'];
            try {
                $response = App::tg()->send($chatId, $sendText);
                if (!empty($response['ok'])) {
                    $ok++;
                } else {
                    $fail++;
                }
            } catch (Throwable $e) {
                $fail++;
                App::log('panel broadcast: ' . $e->getMessage());
            }
            usleep(50000);
        }
        $result = array('ok' => $ok, 'fail' => $fail);
        $message = '';
    }
}
?>
<?php if (count($errors) > 0): ?>
  <div class="alert err"><?php echo Panel::h(implode(' ', $errors)); ?></div>
<?php endif; ?>

<?php if ($result !== null): ?>
  <div class="alert <?php echo $result['fail'] > 0 ? 'warn' : 'ok'; ?>">
    ارسال تمام شد. موفق: <?php echo Farsi::digits($result['ok']); ?>
    · ناموفق: <?php echo Farsi::digits($result['fail']); ?>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <h2>ارسال پیام تلگرامی</h2>
  </div>

  <div class="grid grid-2 summary">
    <div class="stat">
      <div class="stat-label">همه کاربران ربات</div>
      <div class="stat-value"><?php echo Farsi::digits($allCount); ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">دانش‌آموزان فعال متصل</div>
      <div class="stat-value"><?php echo Farsi::digits($studentCount); ?></div>
    </div>
  </div>

  <form method="post" action="<?php echo Panel::h(Panel::url('broadcast')); ?>" class="stack" data-confirm="پیام برای همه گیرندگان انتخاب‌شده ارسال شود؟">
    <?php echo PanelAuth::csrfField(); ?>
    <label>
      گیرندگان
      <select name="audience">
        <option value="all"<?php echo $audience === 'all' ? ' selected' : ''; ?>>همه کاربران ربات (<?php echo Farsi::digits($allCount); ?>)</option>
        <option value="students"<?php echo $audience === 'students' ? ' selected' : ''; ?>>دانش‌آموزان فعال متصل (<?php echo Farsi::digits($studentCount); ?>)</option>
      </select>
    </label>
    <label>
      متن پیام
      <textarea name="message" maxlength="4000" rows="8" required placeholder="متن پیام خود را بنویسید. خط جدید مجاز است."><?php echo Panel::h($message); ?></textarea>
      <span class="field-hint">حداکثر ۴۰۰۰ کاراکتر. متن به‌صورت امن (بدون HTML) ارسال می‌شود.</span>
    </label>
    <div class="row">
      <button class="btn primary" type="submit">ارسال پیام</button>
    </div>
  </form>

  <div class="alert info">
    ارسال برای تعداد زیاد کاربران ممکن است چند لحظه طول بکشد. اگر صفحه قطع شد، ارسال در سرور ادامه پیدا نمی‌کند.
  </div>
</div>
