<?php
if (!defined('TELEBOT_PANEL')) {
    exit;
}

$db = App::db();
$attemptId = Panel::intGet('id', 0);
$attempt = $attemptId > 0 ? $db->one(
    'SELECT a.*, s.code, s.first_name, s.last_name, s.class_name, q.title AS quiz_title
       FROM attempts a
       JOIN student_codes s ON s.id = a.student_id
       JOIN quizzes q ON q.id = a.quiz_id
      WHERE a.id = ?',
    array($attemptId)
) : null;

if (!$attempt) {
    echo '<div class="alert err">تلاش پیدا نشد.</div>';
    echo '<p><a class="btn ghost" href="' . Panel::h(Panel::url('results')) . '">بازگشت به نتایج</a></p>';
    return;
}

$pageTitle = 'تلاش #' . $attemptId;
$studentUrl = Panel::url('student', array('id' => (int) $attempt['student_id']));

if (Panel::isPost()) {
    $action = Panel::post('action');
    if ($action === 'finalize' && $attempt['status'] !== 'finished') {
        Timer::finalize($attemptId, 'admin');
        Panel::flashRedirect('ok', 'تلاش به‌صورت دستی پایان یافت.', Panel::url('attempt', array('id' => $attemptId)));
    }
    if ($action === 'delete') {
        $db->exec('DELETE FROM attempts WHERE id = ?', array($attemptId));
        Panel::flashRedirect('ok', 'تلاش حذف شد. دانش‌آموز می‌تواند دوباره شرکت کند.', $studentUrl);
    }
    Panel::flashRedirect('err', 'عملیات نامعتبر بود.', Panel::url('attempt', array('id' => $attemptId)));
}

$answers = $db->all(
    'SELECT an.*, q.position, q.text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct
       FROM answers an
       LEFT JOIN questions q ON q.id = an.question_id
      WHERE an.attempt_id = ?
      ORDER BY q.position ASC, an.id ASC',
    array($attemptId)
);

$finished = $attempt['status'] === 'finished';
$correctCount = 0;
$wrongCount = 0;
$emptyCount = 0;
foreach ($answers as $answer) {
    if ($answer['selected'] === null || $answer['selected'] === '') {
        $emptyCount++;
    } elseif ((int) $answer['is_correct'] === 1) {
        $correctCount++;
    } else {
        $wrongCount++;
    }
}
?>
<div class="card">
  <div class="card-head">
    <h2>تلاش #<?php echo Farsi::digits($attemptId); ?></h2>
    <div class="actions">
      <?php if (!$finished): ?>
        <form method="post" data-confirm="این تلاش همین حالا پایان یابد و نتیجه ثبت شود؟">
          <?php echo PanelAuth::csrfField(); ?>
          <input type="hidden" name="action" value="finalize">
          <button class="btn primary small" type="submit">پایان دستی</button>
        </form>
      <?php endif; ?>
      <form method="post" data-confirm="این تلاش و پاسخ‌هایش حذف شوند تا دانش‌آموز بتواند دوباره شرکت کند؟">
        <?php echo PanelAuth::csrfField(); ?>
        <input type="hidden" name="action" value="delete">
        <button class="btn danger small" type="submit">ریست تلاش</button>
      </form>
    </div>
  </div>

  <dl class="detail-list">
    <div>
      <dt>دانش‌آموز</dt>
      <dd>
        <a href="<?php echo Panel::h($studentUrl); ?>">
          <?php echo Panel::h($attempt['first_name'] . ' ' . $attempt['last_name']); ?>
        </a>
        <span class="muted">(<?php echo Panel::h($attempt['code']); ?>)</span>
      </dd>
    </div>
    <div>
      <dt>آزمون</dt>
      <dd>
        <a href="<?php echo Panel::h(Panel::url('quiz', array('id' => (int) $attempt['quiz_id']))); ?>">
          <?php echo Panel::h($attempt['quiz_title']); ?>
        </a>
      </dd>
    </div>
    <div>
      <dt>وضعیت</dt>
      <dd>
        <span class="badge <?php echo $finished ? 'ok' : 'warn'; ?>">
          <?php echo Panel::h(Panel::attemptStatus($attempt['status'])); ?>
        </span>
      </dd>
    </div>
    <div>
      <dt>نمره</dt>
      <dd>
        <?php if ($finished): ?>
          <strong><?php echo Farsi::digits((int) $attempt['score']); ?>/<?php echo Farsi::digits((int) $attempt['total']); ?></strong>
          (<?php echo Farsi::digits(Farsi::pct($attempt['score'], $attempt['total'])); ?>٪)
        <?php else: ?>
          —
        <?php endif; ?>
      </dd>
    </div>
    <div>
      <dt>شروع</dt>
      <dd><?php echo Panel::h(Panel::dt($attempt['started_at'])); ?></dd>
    </div>
    <div>
      <dt>مهلت</dt>
      <dd><?php echo Panel::h(Panel::dt($attempt['deadline'])); ?></dd>
    </div>
    <div>
      <dt>پایان</dt>
      <dd><?php echo Panel::h(Panel::dt($attempt['finished_at'])); ?></dd>
    </div>
    <div>
      <dt>نحوه پایان</dt>
      <dd><?php echo Panel::h(Panel::finishReason($attempt['finish_reason'])); ?></dd>
    </div>
    <div>
      <dt>مدت پاسخ‌دهی</dt>
      <dd><?php echo Panel::h(Panel::dur($attempt['started_at'], $attempt['finished_at'])); ?></dd>
    </div>
    <div>
      <dt>شناسه تلگرام</dt>
      <dd><?php echo Panel::h($attempt['telegram_id']); ?></dd>
    </div>
  </dl>
</div>

<div class="card">
  <div class="card-head">
    <h2>پاسخ‌ها (<?php echo Farsi::digits(count($answers)); ?>)</h2>
    <div class="small-text muted">
      درست: <?php echo Farsi::digits($correctCount); ?>
      · غلط: <?php echo Farsi::digits($wrongCount); ?>
      · بی‌پاسخ: <?php echo Farsi::digits($emptyCount); ?>
    </div>
  </div>

  <?php if (count($answers) === 0): ?>
    <div class="empty">پاسخی برای این تلاش ثبت نشده است.</div>
  <?php else: ?>
    <?php foreach ($answers as $index => $answer): ?>
      <?php
      $selected = $answer['selected'];
      $correct = $answer['correct'];
      $isCorrect = $selected !== null && $correct !== null && $selected === $correct;
      ?>
      <div class="question-block">
        <div class="question-text">
          <?php echo Farsi::digits($index + 1); ?>)
          <?php echo $answer['text'] !== null ? Panel::h($answer['text']) : '<span class="muted">سوال حذف شده است</span>'; ?>
          <?php if ($isCorrect): ?>
            <span class="badge ok">درست</span>
          <?php elseif ($selected !== null && $selected !== ''): ?>
            <span class="badge err">غلط</span>
          <?php else: ?>
            <span class="badge gray">بی‌پاسخ</span>
          <?php endif; ?>
        </div>

        <?php if ($answer['text'] !== null): ?>
          <div class="option-list">
            <?php foreach (array('A', 'B', 'C', 'D') as $letter): ?>
              <?php
              $classes = array();
              if ($letter === $correct) {
                  $classes[] = 'correct';
              }
              if ($letter === $selected && $letter !== $correct) {
                  $classes[] = 'wrong';
              }
              ?>
              <div class="<?php echo implode(' ', $classes); ?>">
                <?php echo Panel::h(Panel::optionLabel($letter)); ?>)
                <?php echo Panel::h($answer['option_' . strtolower($letter)]); ?>
                <?php if ($letter === $selected): ?>
                  <strong>(انتخاب دانش‌آموز)</strong>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="small-text muted">
            پاسخ ثبت‌شده: <?php echo $selected !== null && $selected !== '' ? Panel::h(Panel::optionLabel($selected)) : 'بدون پاسخ'; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
