<?php
if (!defined('TELEBOT_PANEL')) {
    exit;
}

$pageTitle = 'ابزارها';
$db = App::db();
$notice = null;
$botInfo = null;
$webhookInfo = null;

if (Panel::isPost()) {
    $action = Panel::post('action');

    if ($action === 'test_bot') {
        try {
            $botInfo = App::tg()->getMe();
            $webhookInfo = App::tg()->getWebhookInfo();
        } catch (Throwable $e) {
            $notice = array('err', 'اتصال به تلگرام برقرار نشد: ' . $e->getMessage());
        }
    } elseif ($action === 'finalize_expired') {
        $ids = $db->all(
            "SELECT id FROM attempts WHERE status = 'in_progress' AND deadline IS NOT NULL AND deadline <= ?",
            array(App::now())
        );
        $count = 0;
        foreach ($ids as $row) {
            try {
                Timer::finalize((int) $row['id'], 'timeout');
                $count++;
            } catch (Throwable $e) {
                App::log('panel finalize expired: ' . $e->getMessage());
            }
        }
        Panel::flashRedirect('ok', 'تعداد ' . Farsi::digits($count) . ' تلاش منقضی پایان یافت.', Panel::url('tools'));
    } elseif ($action === 'clear_throttle') {
        $db->exec('DELETE FROM kv WHERE k LIKE ?', array('panel\\_login\\_%'));
        Panel::flashRedirect('ok', 'سوابق قفل ورود پنل پاک شد.', Panel::url('tools'));
    } elseif ($action === 'disable_panel') {
        PanelAccess::disable();
        PanelAuth::logout();
        PanelAuth::flash('ok', 'پنل وب غیرفعال شد. برای فعال‌سازی دوباره در تلگرام /panel بزنید.');
        Panel::redirect('index.php');
    } else {
        Panel::flashRedirect('err', 'عملیات نامعتبر بود.', Panel::url('tools'));
    }
}

$panelState = null;
$panelError = '';
try {
    $panelState = PanelAccess::state();
} catch (Throwable $e) {
    $panelError = $e->getMessage();
}
$panelEnabled = $panelState !== null && !PanelAccess::expired($panelState);
$panelUrl = PanelAccess::panelUrl();
$expiresAt = null;
if ($panelState && !empty($panelState['at']) && !empty($panelState['ttl'])) {
    $expiresAt = date('Y-m-d H:i:s', strtotime((string) $panelState['at']) + (int) $panelState['ttl']);
}

$dbVersion = '—';
try {
    $dbVersion = (string) $db->pdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
} catch (Throwable $e) {
    $dbVersion = 'نامشخص';
}

$expiredCount = (int) $db->val(
    "SELECT COUNT(*) FROM attempts WHERE status = 'in_progress' AND deadline IS NOT NULL AND deadline <= ?",
    array(App::now()),
    0
);
$inProgressCount = (int) $db->val(
    "SELECT COUNT(*) FROM attempts WHERE status = 'in_progress'",
    array(),
    0
);
?>
<?php if (is_array($notice)): ?>
  <div class="alert <?php echo $notice[0] === 'ok' ? 'ok' : 'err'; ?>"><?php echo Panel::h($notice[1]); ?></div>
<?php endif; ?>

<?php if (is_array($botInfo)): ?>
  <div class="card">
    <div class="card-head">
      <h2>وضعیت ربات</h2>
    </div>
    <?php if (!empty($botInfo['ok'])): ?>
      <div class="alert ok">اتصال به تلگرام برقرار است.</div>
      <?php $bot = isset($botInfo['result']) && is_array($botInfo['result']) ? $botInfo['result'] : array(); ?>
      <dl class="detail-list">
        <div>
          <dt>نام ربات</dt>
          <dd><?php echo Panel::h(isset($bot['first_name']) ? $bot['first_name'] : '—'); ?></dd>
        </div>
        <div>
          <dt>نام کاربری</dt>
          <dd><?php echo isset($bot['username']) ? '@' . Panel::h($bot['username']) : '—'; ?></dd>
        </div>
        <div>
          <dt>شناسه ربات</dt>
          <dd><?php echo Panel::h(isset($bot['id']) ? $bot['id'] : '—'); ?></dd>
        </div>
        <div>
          <dt>وضعیت وبهوک</dt>
          <dd>
            <?php
            $wh = is_array($webhookInfo) && !empty($webhookInfo['result']) && is_array($webhookInfo['result'])
                ? $webhookInfo['result'] : array();
            echo !empty($wh['url']) ? 'تنظیم‌شده' : 'تنظیم نشده (حالت poll یا خطا)';
            ?>
          </dd>
        </div>
        <div class="full">
          <dt>آدرس وبهوک</dt>
          <dd class="small-text"><?php echo !empty($wh['url']) ? Panel::h($wh['url']) : '—'; ?></dd>
        </div>
        <?php if (!empty($wh['last_error_message'])): ?>
          <div class="full">
            <dt>آخرین خطای وبهوک</dt>
            <dd class="small-text"><?php echo Panel::h($wh['last_error_message']); ?></dd>
          </div>
        <?php endif; ?>
      </dl>
    <?php else: ?>
      <div class="alert err">
        تلگرام پاسخ ناموفق داد:
        <?php echo Panel::h(isset($botInfo['description']) ? $botInfo['description'] : 'خطای نامشخص'); ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <h2>دسترسی پنل وب</h2>
  </div>
  <?php if ($panelError !== ''): ?>
    <div class="alert err">وضعیت پنل خوانده نشد: <?php echo Panel::h($panelError); ?></div>
  <?php endif; ?>
  <dl class="detail-list">
    <div>
      <dt>وضعیت</dt>
      <dd>
        <span class="badge <?php echo $panelEnabled ? 'ok' : 'gray'; ?>">
          <?php echo $panelEnabled ? 'فعال' : 'غیرفعال'; ?>
        </span>
      </dd>
    </div>
    <div>
      <dt>نام کاربری</dt>
      <dd><code><?php echo Panel::h(PanelAccess::username()); ?></code></dd>
    </div>
    <div>
      <dt>آخرین چرخش رمز</dt>
      <dd><?php echo Panel::h(Panel::dt($panelState && !empty($panelState['at']) ? $panelState['at'] : null)); ?></dd>
    </div>
    <div>
      <dt>انقضای رمز</dt>
      <dd><?php echo $expiresAt !== null ? Panel::h(Panel::dt($expiresAt)) : 'بدون انقضا'; ?></dd>
    </div>
    <div class="full">
      <dt>آدرس پنل</dt>
      <dd class="small-text"><code><?php echo Panel::h($panelUrl); ?></code></dd>
    </div>
  </dl>
  <div class="alert info">
    برای ساخت رمز جدید، در تلگرام دکمه «🔐 پنل وب» را بزنید یا دستور <code>/panel</code> را در چت خصوصی ربات ارسال کنید.
    هر بار رمز قبلی و نشست‌های وب فعلی باطل می‌شوند.
  </div>
  <div class="row">
    <form method="post" data-confirm="پنل وب غیرفعال شود و همه نشست‌ها خارج شوند؟ برای ورود دوباره باید در تلگرام /panel بزنید.">
      <?php echo PanelAuth::csrfField(); ?>
      <input type="hidden" name="action" value="disable_panel">
      <button class="btn danger" type="submit"<?php echo $panelEnabled ? '' : ' disabled'; ?>>غیرفعال‌کردن پنل وب</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h2>سیستم</h2>
    <form method="post">
      <?php echo PanelAuth::csrfField(); ?>
      <input type="hidden" name="action" value="test_bot">
      <button class="btn ghost small" type="submit" data-busy="در حال بررسی...">بررسی اتصال ربات</button>
    </form>
  </div>
  <dl class="detail-list">
    <div>
      <dt>نسخه PHP</dt>
      <dd><?php echo Panel::h(PHP_VERSION); ?></dd>
    </div>
    <div>
      <dt>نسخه دیتابیس</dt>
      <dd><?php echo Panel::h($dbVersion); ?></dd>
    </div>
    <div>
      <dt>منطقه زمانی</dt>
      <dd><?php echo Panel::h(date_default_timezone_get()); ?></dd>
    </div>
    <div>
      <dt>افزونه mbstring</dt>
      <dd><?php echo function_exists('mb_strlen') ? 'فعال' : 'غیرفعال'; ?></dd>
    </div>
    <div>
      <dt>cURL</dt>
      <dd><?php echo function_exists('curl_init') ? 'فعال' : 'غیرفعال'; ?></dd>
    </div>
    <div>
      <dt>آخرین خطا</dt>
      <dd class="small-text">
        <?php
        $logFile = ROOT_DIR . '/error.log';
        if (is_file($logFile) && is_readable($logFile)) {
            $size = filesize($logFile);
            echo 'فایل error.log موجود است (' . Panel::h(Farsi::digits((string) $size)) . ' بایت)';
        } else {
            echo 'فایل error.log موجود نیست';
        }
        ?>
      </dd>
    </div>
  </dl>
</div>

<div class="card">
  <div class="card-head">
    <h2>نگهداری</h2>
  </div>
  <dl class="detail-list">
    <div>
      <dt>تلاش‌های در جریان</dt>
      <dd><?php echo Farsi::digits($inProgressCount); ?></dd>
    </div>
    <div>
      <dt>تلاش‌های منقضی</dt>
      <dd><?php echo Farsi::digits($expiredCount); ?></dd>
    </div>
  </dl>
  <div class="row">
    <form method="post" data-confirm="همه تلاش‌های منقضی همین حالا پایان یابند و نتیجه ثبت شود؟">
      <?php echo PanelAuth::csrfField(); ?>
      <input type="hidden" name="action" value="finalize_expired">
      <button class="btn primary" type="submit">پایان دادن تلاش‌های منقضی (<?php echo Farsi::digits($expiredCount); ?>)</button>
    </form>
    <form method="post" data-confirm="سوابق قفل ورود پنل پاک شود؟ این کار محدودیت تلاش ورود را بازنشانی می‌کند.">
      <?php echo PanelAuth::csrfField(); ?>
      <input type="hidden" name="action" value="clear_throttle">
      <button class="btn ghost" type="submit">پاک‌کردن سوابق قفل ورود</button>
    </form>
  </div>
</div>
