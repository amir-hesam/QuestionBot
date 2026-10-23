<?php
if (!defined('TELEBOT_PANEL')) {
    exit;
}

$db = App::db();
$quizId = Panel::intGet('id', 0);
$quiz = $quizId > 0 ? $db->one('SELECT * FROM quizzes WHERE id = ?', array($quizId)) : null;

if (!$quiz) {
    echo '<div class="alert err">آزمون پیدا نشد.</div>';
    echo '<p><a class="btn ghost" href="' . Panel::h(Panel::url('quizzes')) . '">بازگشت به آزمون‌ها</a></p>';
    return;
}

$pageTitle = $quiz['title'];
$errors = array();
$formQuiz = $quiz;

if (Panel::isPost()) {
    $action = Panel::post('action');

    if ($action === 'update') {
        $title = Panel::post('title');
        $description = Panel::post('description');
        $timeMin = Panel::intPost('time_min', 0);
        $rawCode = Panel::post('access_code');
        $shuffle = isset($_POST['shuffle']) ? 1 : 0;
        $showAnswer = isset($_POST['show_answer']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (Farsi::len($title) < 1 || Farsi::len($title) > 200) {
            $errors[] = 'عنوان آزمون باید بین ۱ تا ۲۰۰ کاراکتر باشد.';
        }
        if (Farsi::len($description) > 1000) {
            $errors[] = 'توضیح حداکثر ۱۰۰۰ کاراکتر است.';
        }
        if ($timeMin < 0 || $timeMin > 600) {
            $errors[] = 'زمان آزمون باید بین ۰ تا ۶۰۰ دقیقه باشد.';
        }
        $accessCode = $rawCode === '' ? null : Codes::normalize($rawCode);
        if ($accessCode !== null && !Codes::isValidAccessCode($accessCode)) {
            $errors[] = 'کد ورود باید ۲ تا ۳۲ کاراکتر و فقط حروف انگلیسی و عدد باشد.';
        }

        if (count($errors) === 0) {
            $db->exec(
                'UPDATE quizzes
                    SET title = ?, description = ?, access_code = ?, time_limit = ?,
                        shuffle = ?, show_answer = ?, is_active = ?
                  WHERE id = ?',
                array(
                    $title,
                    $description !== '' ? $description : null,
                    $accessCode,
                    $timeMin * 60,
                    $shuffle,
                    $showAnswer,
                    $isActive,
                    $quizId,
                )
            );
            Panel::flashRedirect('ok', 'تنظیمات آزمون ذخیره شد.', Panel::url('quiz', array('id' => $quizId)));
        }

        $formQuiz = array_merge($quiz, array(
            'title' => $title,
            'description' => $description,
            'access_code' => $accessCode,
            'time_limit' => $timeMin * 60,
            'shuffle' => $shuffle,
            'show_answer' => $showAnswer,
            'is_active' => $isActive,
        ));
    } elseif ($action === 'toggle') {
        $db->exec('UPDATE quizzes SET is_active = 1 - is_active WHERE id = ?', array($quizId));
        Panel::flashRedirect('ok', 'وضعیت آزمون تغییر کرد.', Panel::url('quiz', array('id' => $quizId)));
    } elseif ($action === 'delete') {
        $db->exec('DELETE FROM quizzes WHERE id = ?', array($quizId));
        Panel::flashRedirect('ok', 'آزمون و همه داده‌هایش حذف شد.', Panel::url('quizzes'));
    } elseif ($action === 'delete_question') {
        $questionId = Panel::intPost('question_id');
        $question = $db->one(
            'SELECT * FROM questions WHERE id = ? AND quiz_id = ?',
            array($questionId, $quizId)
        );
        if ($question) {
            $db->exec('DELETE FROM questions WHERE id = ?', array($questionId));
            Panel::flashRedirect('ok', 'سوال حذف شد.', Panel::url('quiz', array('id' => $quizId)));
        }
        Panel::flashRedirect('err', 'سوال پیدا نشد.', Panel::url('quiz', array('id' => $quizId)));
    } elseif ($action === 'move_question') {
        $questionId = Panel::intPost('question_id');
        $direction = Panel::post('direction');
        $rows = $db->all(
            'SELECT id FROM questions WHERE quiz_id = ? ORDER BY position ASC, id ASC',
            array($quizId)
        );
        $ids = array();
        foreach ($rows as $r) {
            $ids[] = (int) $r['id'];
        }
        $position = array_search($questionId, $ids, true);
        if ($position !== false) {
            $target = $direction === 'up' ? $position - 1 : $position + 1;
            if ($target >= 0 && $target < count($ids)) {
                $tmp = $ids[$position];
                $ids[$position] = $ids[$target];
                $ids[$target] = $tmp;
                $pdo = $db->pdo();
                $pdo->beginTransaction();
                try {
                    foreach ($ids as $index => $id) {
                        $db->exec(
                            'UPDATE questions SET position = ? WHERE id = ? AND quiz_id = ?',
                            array($index + 1, $id, $quizId)
                        );
                    }
                    $pdo->commit();
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            }
        }
        Panel::flashRedirect('ok', 'ترتیب سوال‌ها ذخیره شد.', Panel::url('quiz', array('id' => $quizId)));
    } else {
        Panel::flashRedirect('err', 'عملیات نامعتبر بود.', Panel::url('quiz', array('id' => $quizId)));
    }
}

$questions = $db->all(
    'SELECT * FROM questions WHERE quiz_id = ? ORDER BY position ASC, id ASC',
    array($quizId)
);
$attemptCount = (int) $db->val('SELECT COUNT(*) FROM attempts WHERE quiz_id = ?', array($quizId), 0);
$finishedCount = (int) $db->val(
    "SELECT COUNT(*) FROM attempts WHERE quiz_id = ? AND status = 'finished'",
    array($quizId),
    0
);
$timeMinutes = (int) round(((int) $formQuiz['time_limit']) / 60);
?>
<?php if (count($errors) > 0): ?>
  <div class="alert err"><?php echo Panel::h(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <h2>خلاصه آزمون</h2>
    <div class="actions">
      <a class="btn ghost small" href="<?php echo Panel::h(Panel::url('quizzes')); ?>">همه آزمون‌ها</a>
      <a class="btn ghost small" href="<?php echo Panel::h(Panel::url('results', array('quiz' => $quizId))); ?>">نتایج</a>
      <a class="btn ghost small" href="<?php echo Panel::h(Panel::url('export', array('quiz' => $quizId))); ?>">خروجی CSV</a>
    </div>
  </div>
  <div class="grid grid-4">
    <div class="stat">
      <div class="stat-label">شناسه آزمون</div>
      <div class="stat-value small"><?php echo Farsi::digits($quizId); ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">سوال‌ها</div>
      <div class="stat-value"><?php echo Farsi::digits(count($questions)); ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">تلاش‌ها</div>
      <div class="stat-value"><?php echo Farsi::digits($attemptCount); ?></div>
      <div class="small-text muted">تمام‌شده: <?php echo Farsi::digits($finishedCount); ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">وضعیت</div>
      <div class="stat-value small">
        <span class="badge <?php echo (int) $formQuiz['is_active'] ? 'ok' : 'gray'; ?>">
          <?php echo (int) $formQuiz['is_active'] ? 'فعال' : 'غیرفعال'; ?>
        </span>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h2>تنظیمات آزمون</h2>
    <div class="actions">
      <form method="post" data-confirm="وضعیت فعال/غیرفعال آزمون تغییر کند؟">
        <?php echo PanelAuth::csrfField(); ?>
        <input type="hidden" name="action" value="toggle">
        <button class="btn ghost small" type="submit"><?php echo (int) $formQuiz['is_active'] ? 'غیرفعال‌کردن' : 'فعال‌کردن'; ?></button>
      </form>
      <form method="post" data-confirm="آزمون، سوال‌ها و همه نتایج آن حذف شوند؟ این کار قابل بازگشت نیست.">
        <?php echo PanelAuth::csrfField(); ?>
        <input type="hidden" name="action" value="delete">
        <button class="btn danger small" type="submit">حذف آزمون</button>
      </form>
    </div>
  </div>

  <form method="post" action="<?php echo Panel::h(Panel::url('quiz', array('id' => $quizId))); ?>" class="stack">
    <?php echo PanelAuth::csrfField(); ?>
    <input type="hidden" name="action" value="update">
    <div class="form-grid">
      <label class="full">
        عنوان آزمون
        <input type="text" name="title" value="<?php echo Panel::h($formQuiz['title']); ?>" maxlength="200" required>
      </label>
      <label class="full">
        توضیح
        <textarea name="description" maxlength="1000"><?php echo Panel::h($formQuiz['description']); ?></textarea>
      </label>
      <label>
        زمان به دقیقه (۰ = بدون محدودیت)
        <input type="number" name="time_min" min="0" max="600" value="<?php echo Panel::h($timeMinutes); ?>">
      </label>
      <label>
        کد ورود (اختیاری)
        <input type="text" name="access_code" value="<?php echo Panel::h($formQuiz['access_code']); ?>" maxlength="32">
        <span class="field-hint">خالی = بدون کد ورود.</span>
      </label>
      <label class="check full">
        <input type="checkbox" name="shuffle" value="1"<?php echo (int) $formQuiz['shuffle'] ? ' checked' : ''; ?>>
        سوال‌ها و گزینه‌ها تصادفی شوند
      </label>
      <label class="check full">
        <input type="checkbox" name="show_answer" value="1"<?php echo (int) $formQuiz['show_answer'] ? ' checked' : ''; ?>>
        پاسخ درست بعد از هر سوال نمایش داده شود
      </label>
      <label class="check full">
        <input type="checkbox" name="is_active" value="1"<?php echo (int) $formQuiz['is_active'] ? ' checked' : ''; ?>>
        آزمون فعال باشد
      </label>
    </div>
    <div class="row">
      <button class="btn primary" type="submit">ذخیره تنظیمات</button>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-head">
    <h2>سوال‌ها (<?php echo Farsi::digits(count($questions)); ?>)</h2>
    <a class="btn primary" href="<?php echo Panel::h(Panel::url('question', array('quiz' => $quizId))); ?>">➕ افزودن سوال</a>
  </div>

  <?php if (count($questions) === 0): ?>
    <div class="empty">این آزمون هنوز سوالی ندارد.</div>
  <?php else: ?>
    <?php foreach ($questions as $index => $question): ?>
      <div class="question-block">
        <div class="row question-head">
          <div class="question-text">
            <?php echo Farsi::digits($index + 1); ?>) <?php echo Panel::h($question['text']); ?>
          </div>
          <div class="actions">
            <a class="btn ghost small" href="<?php echo Panel::h(Panel::url('question', array('quiz' => $quizId, 'id' => (int) $question['id']))); ?>">ویرایش</a>
            <form method="post">
              <?php echo PanelAuth::csrfField(); ?>
              <input type="hidden" name="action" value="move_question">
              <input type="hidden" name="question_id" value="<?php echo (int) $question['id']; ?>">
              <input type="hidden" name="direction" value="up">
              <button class="btn ghost small" type="submit"<?php echo $index === 0 ? ' disabled' : ''; ?>>↑</button>
            </form>
            <form method="post">
              <?php echo PanelAuth::csrfField(); ?>
              <input type="hidden" name="action" value="move_question">
              <input type="hidden" name="question_id" value="<?php echo (int) $question['id']; ?>">
              <input type="hidden" name="direction" value="down">
              <button class="btn ghost small" type="submit"<?php echo $index === count($questions) - 1 ? ' disabled' : ''; ?>>↓</button>
            </form>
            <form method="post" data-confirm="این سوال حذف شود؟ پاسخ‌های ثبت‌شده برای آن نیز حذف می‌شوند.">
              <?php echo PanelAuth::csrfField(); ?>
              <input type="hidden" name="action" value="delete_question">
              <input type="hidden" name="question_id" value="<?php echo (int) $question['id']; ?>">
              <button class="btn danger small" type="submit">حذف</button>
            </form>
          </div>
        </div>
        <div class="option-list">
          <?php foreach (array('A', 'B', 'C', 'D') as $letter): ?>
            <div class="<?php echo $question['correct'] === $letter ? 'correct' : ''; ?>">
              <?php echo Panel::h(Panel::optionLabel($letter)); ?>) <?php echo Panel::h($question['option_' . strtolower($letter)]); ?>
              <?php echo $question['correct'] === $letter ? ' ✅' : ''; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if ($attemptCount > 0): ?>
      <div class="alert info">
        این آزمون <?php echo Farsi::digits($attemptCount); ?> تلاش دارد. تغییر یا حذف سوال فقط روی تلاش‌های جدید اثر می‌گذارد؛
        نمره و جزئیات تلاش‌های قبلی ممکن است با متن جدید هم‌خوان نباشد.
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
