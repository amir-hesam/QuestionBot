<?php
if (!defined('TELEBOT_PANEL')) {
    exit;
}

$pageTitle = 'داشبورد';
$db = App::db();

$students = (int) $db->val('SELECT COUNT(*) FROM student_codes', array(), 0);
$activeStudents = (int) $db->val('SELECT COUNT(*) FROM student_codes WHERE is_active = 1', array(), 0);
$quizzes = (int) $db->val('SELECT COUNT(*) FROM quizzes', array(), 0);
$activeQuizzes = (int) $db->val('SELECT COUNT(*) FROM quizzes WHERE is_active = 1', array(), 0);
$inProgress = (int) $db->val("SELECT COUNT(*) FROM attempts WHERE status = 'in_progress'", array(), 0);
$finished = (int) $db->val("SELECT COUNT(*) FROM attempts WHERE status = 'finished'", array(), 0);

$recent = $db->all(
    'SELECT a.*, q.title AS quiz_title, s.code, s.first_name, s.last_name, s.class_name
       FROM attempts a
       JOIN quizzes q ON q.id = a.quiz_id
       JOIN student_codes s ON s.id = a.student_id
      ORDER BY a.id DESC
      LIMIT 10'
);

$activeList = $db->all(
    'SELECT q.*,
            (SELECT COUNT(*) FROM questions x WHERE x.quiz_id = q.id) AS question_count,
            (SELECT COUNT(*) FROM attempts a WHERE a.quiz_id = q.id) AS attempt_count
       FROM quizzes q
      ORDER BY q.is_active DESC, q.id DESC
      LIMIT 6'
);
?>
<div class="grid grid-4">
  <div class="stat">
    <div class="stat-label">دانش‌آموزان</div>
    <div class="stat-value"><?php echo Farsi::digits($students); ?></div>
    <div class="small-text muted">فعال: <?php echo Farsi::digits($activeStudents); ?></div>
  </div>
  <div class="stat">
    <div class="stat-label">آزمون‌ها</div>
    <div class="stat-value"><?php echo Farsi::digits($quizzes); ?></div>
    <div class="small-text muted">فعال: <?php echo Farsi::digits($activeQuizzes); ?></div>
  </div>
  <div class="stat">
    <div class="stat-label">تلاش‌های در جریان</div>
    <div class="stat-value"><?php echo Farsi::digits($inProgress); ?></div>
    <div class="small-text muted">منتظر پایان یا اتمام زمان</div>
  </div>
  <div class="stat">
    <div class="stat-label">نتایج ثبت‌شده</div>
    <div class="stat-value"><?php echo Farsi::digits($finished); ?></div>
    <div class="small-text muted">آزمون‌های تمام‌شده</div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h2>دسترسی سریع</h2>
  </div>
  <div class="row">
    <a class="btn primary" href="<?php echo Panel::h(Panel::url('student', array('new' => 1))); ?>">➕ دانش‌آموز جدید</a>
    <a class="btn primary" href="<?php echo Panel::h(Panel::url('quizzes', array('new' => 1))); ?>">➕ آزمون جدید</a>
    <a class="btn ghost" href="<?php echo Panel::h(Panel::url('results')); ?>">📊 نتایج</a>
    <a class="btn ghost" href="<?php echo Panel::h(Panel::url('broadcast')); ?>">📤 پیام همگانی</a>
    <a class="btn ghost" href="<?php echo Panel::h(Panel::url('tools')); ?>">🧰 ابزارها</a>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h2>آخرین تلاش‌ها</h2>
    <a class="btn ghost small" href="<?php echo Panel::h(Panel::url('results')); ?>">همه نتایج</a>
  </div>
  <?php if (count($recent) === 0): ?>
    <div class="empty">هنوز تلاشی ثبت نشده است.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>دانش‌آموز</th>
            <th>آزمون</th>
            <th>وضعیت</th>
            <th>نمره</th>
            <th>شروع</th>
            <th>پایان</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $row): ?>
            <?php $isFinished = $row['status'] === 'finished'; ?>
            <tr>
              <td>
                <code><?php echo Panel::h($row['code']); ?></code>
                <?php echo Panel::h($row['first_name'] . ' ' . $row['last_name']); ?>
              </td>
              <td class="wrap-cell"><?php echo Panel::h($row['quiz_title']); ?></td>
              <td>
                <span class="badge <?php echo $isFinished ? 'ok' : 'warn'; ?>">
                  <?php echo Panel::h(Panel::attemptStatus($row['status'])); ?>
                </span>
              </td>
              <td>
                <?php if ($isFinished): ?>
                  <?php echo Farsi::digits((int) $row['score']); ?>/<?php echo Farsi::digits((int) $row['total']); ?>
                  (<?php echo Farsi::digits(Farsi::pct($row['score'], $row['total'])); ?>٪)
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td class="small-text"><?php echo Panel::h(Panel::dt($row['started_at'])); ?></td>
              <td class="small-text"><?php echo Panel::h(Panel::dt($row['finished_at'])); ?></td>
              <td><a class="btn ghost small" href="<?php echo Panel::h(Panel::url('attempt', array('id' => (int) $row['id']))); ?>">جزئیات</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-head">
    <h2>آزمون‌ها</h2>
    <a class="btn ghost small" href="<?php echo Panel::h(Panel::url('quizzes')); ?>">مدیریت آزمون‌ها</a>
  </div>
  <?php if (count($activeList) === 0): ?>
    <div class="empty">هنوز آزمونی ساخته نشده است.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>عنوان</th>
            <th>سوال</th>
            <th>تلاش</th>
            <th>زمان</th>
            <th>وضعیت</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($activeList as $quiz): ?>
            <tr>
              <td class="wrap-cell"><?php echo Panel::h($quiz['title']); ?></td>
              <td><?php echo Farsi::digits((int) $quiz['question_count']); ?></td>
              <td><?php echo Farsi::digits((int) $quiz['attempt_count']); ?></td>
              <td><?php echo (int) $quiz['time_limit'] > 0 ? Farsi::digits((int) round($quiz['time_limit'] / 60)) . ' دقیقه' : 'بدون محدودیت'; ?></td>
              <td>
                <span class="badge <?php echo (int) $quiz['is_active'] ? 'ok' : 'gray'; ?>">
                  <?php echo (int) $quiz['is_active'] ? 'فعال' : 'غیرفعال'; ?>
                </span>
              </td>
              <td><a class="btn ghost small" href="<?php echo Panel::h(Panel::url('quiz', array('id' => (int) $quiz['id']))); ?>">مدیریت</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
