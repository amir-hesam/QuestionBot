<?php
if (!defined('TELEBOT_PANEL')) {
    exit;
}

$pageTitle = 'دانش‌آموز';
$db = App::db();
$studentId = Panel::intGet('id', 0);
$isNew = $studentId <= 0;
$student = null;
$errors = array();
$formError = '';

if (!$isNew) {
    $student = $db->one('SELECT * FROM student_codes WHERE id = ?', array($studentId));
    if (!$student) {
        echo '<div class="alert err">دانش‌آموز پیدا نشد.</div>';
        echo '<p><a class="btn ghost" href="' . Panel::h(Panel::url('students')) . '">بازگشت به لیست</a></p>';
        return;
    }
    $pageTitle = $student['first_name'] . ' ' . $student['last_name'];
}

if (Panel::isPost()) {
    $action = Panel::post('action');

    if ($action === 'create' || $action === 'update') {
        $first = Panel::post('first_name');
        $last = Panel::post('last_name');
        $class = Panel::post('class_name');
        $rawCode = Panel::post('code');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (Farsi::len($first) < 1 || Farsi::len($first) > 100) {
            $errors[] = 'نام باید بین ۱ تا ۱۰۰ کاراکتر باشد.';
        }
        if (Farsi::len($last) < 1 || Farsi::len($last) > 100) {
            $errors[] = 'نام خانوادگی باید بین ۱ تا ۱۰۰ کاراکتر باشد.';
        }
        if (Farsi::len($class) > 100) {
            $errors[] = 'کلاس حداکثر ۱۰۰ کاراکتر است.';
        }

        $code = $rawCode === '' ? '' : Codes::normalize($rawCode);
        if ($code !== '' && !Codes::isValidCustom($code)) {
            $errors[] = 'کد باید ۲ تا ۱۶ کاراکتر و فقط حروف انگلیسی و عدد باشد.';
        }
        if ($rawCode === '' && $isNew) {
            $code = Codes::generateUnique($db, (int) App::cfg('code_length', 6));
        }
        if ($rawCode === '' && !$isNew) {
            $code = $student['code'];
        }
        if ($code !== '') {
            $found = Codes::findByCode($db, $code);
            if ($found && (!$student || (int) $found['id'] !== (int) $student['id'])) {
                $errors[] = 'این کد قبلاً استفاده شده است.';
            }
        }

        if (count($errors) > 0) {
            $formError = implode(' ', $errors);
            $student = array(
                'id' => $student ? (int) $student['id'] : 0,
                'code' => $code !== '' ? $code : $rawCode,
                'first_name' => $first,
                'last_name' => $last,
                'class_name' => $class,
                'is_active' => $isActive,
                'locked_telegram_id' => $student ? $student['locked_telegram_id'] : null,
                'created_at' => $student ? $student['created_at'] : null,
            );
            $isNew = (int) $student['id'] <= 0;
        } else {
            if ($isNew) {
                $db->exec(
                    'INSERT INTO student_codes (code, first_name, last_name, class_name, is_active, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?, NULL, ?)',
                    array($code, $first, $last, $class !== '' ? $class : null, $isActive, App::now())
                );
                $id = $db->id();
                Panel::flashRedirect('ok', 'دانش‌آموز با کد ' . $code . ' ثبت شد.', Panel::url('student', array('id' => $id)));
            }
            $db->exec(
                'UPDATE student_codes SET code = ?, first_name = ?, last_name = ?, class_name = ?, is_active = ? WHERE id = ?',
                array($code, $first, $last, $class !== '' ? $class : null, $isActive, (int) $student['id'])
            );
            Panel::flashRedirect('ok', 'اطلاعات دانش‌آموز ذخیره شد.', Panel::url('student', array('id' => (int) $student['id'])));
        }
    } elseif ($student) {
        if ($action === 'unlock') {
            $db->exec('UPDATE student_codes SET locked_telegram_id = NULL WHERE id = ?', array((int) $student['id']));
            Panel::flashRedirect('ok', 'قفل تلگرام آزاد شد.', Panel::url('student', array('id' => (int) $student['id'])));
        }
        if ($action === 'toggle') {
            $db->exec('UPDATE student_codes SET is_active = 1 - is_active WHERE id = ?', array((int) $student['id']));
            Panel::flashRedirect('ok', 'وضعیت دانش‌آموز تغییر کرد.', Panel::url('student', array('id' => (int) $student['id'])));
        }
        if ($action === 'delete') {
            $db->exec('DELETE FROM student_codes WHERE id = ?', array((int) $student['id']));
            Panel::flashRedirect('ok', 'دانش‌آموز و همه نتایج او حذف شد.', Panel::url('students'));
        }
        if ($action === 'reset_attempt' || $action === 'finalize_attempt' || $action === 'delete_attempt') {
            $attemptId = Panel::intPost('attempt_id');
            $att = $db->one(
                'SELECT * FROM attempts WHERE id = ? AND student_id = ?',
                array($attemptId, (int) $student['id'])
            );
            if (!$att) {
                Panel::flashRedirect('err', 'تلاش موردنظر پیدا نشد.', Panel::url('student', array('id' => (int) $student['id'])));
            }
            if ($action === 'reset_attempt' || $action === 'delete_attempt') {
                $db->exec('DELETE FROM attempts WHERE id = ?', array($attemptId));
                Panel::flashRedirect('ok', 'تلاش حذف شد. دانش‌آموز می‌تواند دوباره شرکت کند.', Panel::url('student', array('id' => (int) $student['id'])));
            }
            Timer::finalize($attemptId, 'admin');
            Panel::flashRedirect('ok', 'تلاش به‌صورت دستی پایان یافت و نتیجه ثبت شد.', Panel::url('student', array('id' => (int) $student['id'])));
        }
        Panel::flashRedirect('err', 'عملیات نامعتبر بود.', Panel::url('student', array('id' => (int) $student['id'])));
    }
}

if ($formError === '' && $student) {
    $formCode = $student['code'];
} elseif (Panel::isPost()) {
    $formCode = Panel::post('code');
} else {
    $formCode = '';
}

$attempts = array();
if (!$isNew && $student) {
    $attempts = $db->all(
        'SELECT a.*, q.title AS quiz_title
           FROM attempts a
           JOIN quizzes q ON q.id = a.quiz_id
          WHERE a.student_id = ?
          ORDER BY a.id DESC',
        array((int) $student['id'])
    );
}

$locked = $student && !empty($student['locked_telegram_id']);
?>
<?php if ($formError !== ''): ?>
  <div class="alert err"><?php echo Panel::h($formError); ?></div>
<?php endif; ?>

<?php if ($isNew): ?>
  <div class="card">
    <div class="card-head">
      <h2>ثبت دانش‌آموز جدید</h2>
      <a class="btn ghost" href="<?php echo Panel::h(Panel::url('students')); ?>">بازگشت</a>
    </div>
    <form method="post" action="<?php echo Panel::h(Panel::url('student', array('new' => 1))); ?>" class="stack">
      <?php echo PanelAuth::csrfField(); ?>
      <input type="hidden" name="action" value="create">
      <div class="form-grid">
        <label>
          نام
          <input type="text" name="first_name" value="<?php echo Panel::h(Panel::post('first_name', $student ? $student['first_name'] : '')); ?>" maxlength="100" required>
        </label>
        <label>
          نام خانوادگی
          <input type="text" name="last_name" value="<?php echo Panel::h(Panel::post('last_name', $student ? $student['last_name'] : '')); ?>" maxlength="100" required>
        </label>
        <label>
          کلاس / پایه
          <input type="text" name="class_name" value="<?php echo Panel::h(Panel::post('class_name', $student ? $student['class_name'] : '')); ?>" maxlength="100">
        </label>
        <label>
          کد دانش‌آموزی (اختیاری)
          <input type="text" name="code" value="<?php echo Panel::h($formCode); ?>" maxlength="16" placeholder="خالی = ساخت خودکار">
          <span class="field-hint">فقط حروف انگلیسی و عدد؛ ۲ تا ۱۶ کاراکتر.</span>
        </label>
        <label class="check full">
          <input type="checkbox" name="is_active" value="1"<?php echo (!$student || (int) $student['is_active']) ? ' checked' : ''; ?>>
          دانش‌آموز فعال باشد
        </label>
      </div>
      <div class="row">
        <button class="btn primary" type="submit">ثبت دانش‌آموز</button>
      </div>
    </form>
  </div>
<?php else: ?>
  <div class="card">
    <div class="card-head">
      <h2>مشخصات</h2>
      <div class="actions">
        <a class="btn ghost small" href="<?php echo Panel::h(Panel::url('students')); ?>">لیست دانش‌آموزان</a>
        <?php if ($locked): ?>
          <form method="post" data-confirm="قفل تلگرام این دانش‌آموز آزاد شود؟">
            <?php echo PanelAuth::csrfField(); ?>
            <input type="hidden" name="action" value="unlock">
            <button class="btn ghost small" type="submit">آزادسازی قفل</button>
          </form>
        <?php endif; ?>
        <form method="post">
          <?php echo PanelAuth::csrfField(); ?>
          <input type="hidden" name="action" value="toggle">
          <button class="btn ghost small" type="submit"><?php echo (int) $student['is_active'] ? 'غیرفعال‌کردن' : 'فعال‌کردن'; ?></button>
        </form>
        <form method="post" data-confirm="دانش‌آموز و همه نتایج او حذف شوند؟ این کار قابل بازگشت نیست.">
          <?php echo PanelAuth::csrfField(); ?>
          <input type="hidden" name="action" value="delete">
          <button class="btn danger small" type="submit">حذف دانش‌آموز</button>
        </form>
      </div>
    </div>

    <dl class="detail-list">
      <div>
        <dt>کد دانش‌آموزی</dt>
        <dd><code><?php echo Panel::h($student['code']); ?></code></dd>
      </div>
      <div>
        <dt>وضعیت</dt>
        <dd>
          <span class="badge <?php echo (int) $student['is_active'] ? 'ok' : 'gray'; ?>">
            <?php echo (int) $student['is_active'] ? 'فعال' : 'غیرفعال'; ?>
          </span>
        </dd>
      </div>
      <div>
        <dt>قفل تلگرام</dt>
        <dd><?php echo $locked ? 'قفل روی شناسه ' . Panel::h($student['locked_telegram_id']) : 'آزاد'; ?></dd>
      </div>
      <div>
        <dt>تاریخ ثبت</dt>
        <dd><?php echo Panel::h(Panel::dt($student['created_at'])); ?></dd>
      </div>
    </dl>
  </div>

  <div class="card">
    <div class="card-head">
      <h2>ویرایش اطلاعات</h2>
    </div>
    <form method="post" action="<?php echo Panel::h(Panel::url('student', array('id' => (int) $student['id']))); ?>" class="stack">
      <?php echo PanelAuth::csrfField(); ?>
      <input type="hidden" name="action" value="update">
      <div class="form-grid">
        <label>
          نام
          <input type="text" name="first_name" value="<?php echo Panel::h($student['first_name']); ?>" maxlength="100" required>
        </label>
        <label>
          نام خانوادگی
          <input type="text" name="last_name" value="<?php echo Panel::h($student['last_name']); ?>" maxlength="100" required>
        </label>
        <label>
          کلاس / پایه
          <input type="text" name="class_name" value="<?php echo Panel::h($student['class_name']); ?>" maxlength="100">
        </label>
        <label>
          کد دانش‌آموزی
          <input type="text" name="code" value="<?php echo Panel::h($formCode); ?>" maxlength="16" required>
          <span class="field-hint">تغییر کد روی نتایج قبلی اثری ندارد.</span>
        </label>
        <label class="check full">
          <input type="checkbox" name="is_active" value="1"<?php echo (int) $student['is_active'] ? ' checked' : ''; ?>>
          دانش‌آموز فعال باشد
        </label>
      </div>
      <div class="row">
        <button class="btn primary" type="submit">ذخیره تغییرات</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-head">
      <h2>آزمون‌ها و نتایج (<?php echo Farsi::digits(count($attempts)); ?>)</h2>
      <a class="btn ghost small" href="<?php echo Panel::h(Panel::url('results', array('q' => $student['code']))); ?>">نتایج این دانش‌آموز</a>
    </div>
    <?php if (count($attempts) === 0): ?>
      <div class="empty">این دانش‌آموز هنوز در آزمونی شرکت نکرده است.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>آزمون</th>
              <th>وضعیت</th>
              <th>نمره</th>
              <th>شروع</th>
              <th>پایان / مهلت</th>
              <th>نحوه پایان</th>
              <th>عملیات</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($attempts as $att): ?>
              <?php $finished = $att['status'] === 'finished'; ?>
              <tr>
                <td class="wrap-cell"><?php echo Panel::h($att['quiz_title']); ?></td>
                <td>
                  <span class="badge <?php echo $finished ? 'ok' : 'warn'; ?>">
                    <?php echo Panel::h(Panel::attemptStatus($att['status'])); ?>
                  </span>
                </td>
                <td>
                  <?php if ($finished): ?>
                    <?php echo Farsi::digits((int) $att['score']); ?>/<?php echo Farsi::digits((int) $att['total']); ?>
                    (<?php echo Farsi::digits(Farsi::pct($att['score'], $att['total'])); ?>٪)
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
                <td class="small-text"><?php echo Panel::h(Panel::dt($att['started_at'])); ?></td>
                <td class="small-text"><?php echo Panel::h(Panel::dt($finished ? $att['finished_at'] : $att['deadline'])); ?></td>
                <td><?php echo Panel::h(Panel::finishReason($att['finish_reason'])); ?></td>
                <td>
                  <div class="actions">
                    <a class="btn ghost small" href="<?php echo Panel::h(Panel::url('attempt', array('id' => (int) $att['id']))); ?>">جزئیات</a>
                    <?php if (!$finished): ?>
                      <form method="post" data-confirm="این تلاش همین حالا پایان یابد و نتیجه ثبت شود؟">
                        <?php echo PanelAuth::csrfField(); ?>
                        <input type="hidden" name="action" value="finalize_attempt">
                        <input type="hidden" name="attempt_id" value="<?php echo (int) $att['id']; ?>">
                        <button class="btn ghost small" type="submit">پایان دستی</button>
                      </form>
                    <?php endif; ?>
                    <form method="post" data-confirm="این تلاش حذف شود تا دانش‌آموز بتواند دوباره شرکت کند؟">
                      <?php echo PanelAuth::csrfField(); ?>
                      <input type="hidden" name="action" value="reset_attempt">
                      <input type="hidden" name="attempt_id" value="<?php echo (int) $att['id']; ?>">
                      <button class="btn danger small" type="submit">ریست تلاش</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
