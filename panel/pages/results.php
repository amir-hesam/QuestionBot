<?php
if (!defined('TELEBOT_PANEL')) {
    exit;
}

$pageTitle = 'نتایج';
$db = App::db();
$quizId = Panel::intGet('quiz', 0);
$q = trim(Panel::get('q', ''));
$pageNo = max(1, Panel::intGet('page', 1));

$where = array("a.status = 'finished'");
$params = array();
if ($quizId > 0) {
    $where[] = 'a.quiz_id = ?';
    $params[] = $quizId;
}
if ($q !== '') {
    $like = '%' . Panel::like($q) . '%';
    $where[] = '(s.first_name LIKE ? OR s.last_name LIKE ? OR s.code LIKE ? OR q.title LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$whereSql = ' WHERE ' . implode(' AND ', $where);

$total = (int) $db->val(
    'SELECT COUNT(*)
       FROM attempts a
       JOIN student_codes s ON s.id = a.student_id
       JOIN quizzes q ON q.id = a.quiz_id' . $whereSql,
    $params,
    0
);
list($pageNo, $pages, $offset) = Panel::pageNumber($total, 25, $pageNo);

$rows = $db->all(
    'SELECT a.*, s.code, s.first_name, s.last_name, s.class_name, q.title AS quiz_title
       FROM attempts a
       JOIN student_codes s ON s.id = a.student_id
       JOIN quizzes q ON q.id = a.quiz_id' . $whereSql . '
      ORDER BY a.finished_at DESC, a.id DESC
      LIMIT 25 OFFSET ' . (int) $offset,
    $params
);

$summary = $db->one(
    'SELECT COALESCE(SUM(a.score), 0) AS score_sum, COALESCE(SUM(a.total), 0) AS total_sum
       FROM attempts a
       JOIN student_codes s ON s.id = a.student_id
       JOIN quizzes q ON q.id = a.quiz_id' . $whereSql,
    $params
);
$scoreSum = $summary ? (int) $summary['score_sum'] : 0;
$totalSum = $summary ? (int) $summary['total_sum'] : 0;

$quizzes = $db->all('SELECT id, title FROM quizzes ORDER BY title ASC, id ASC');
$filterParams = array('quiz' => $quizId, 'q' => $q);
?>
<div class="card">
  <div class="card-head">
    <h2>نتایج آزمون‌ها (<?php echo Farsi::digits($total); ?>)</h2>
    <a class="btn ghost small" href="<?php echo Panel::h(Panel::url('export', $filterParams)); ?>">خروجی CSV همین فیلترها</a>
  </div>

  <form method="get" action="index.php" class="filters">
    <input type="hidden" name="page" value="results">
    <label class="grow">
      جستجو
      <input type="search" name="q" value="<?php echo Panel::h($q); ?>" placeholder="نام، نام خانوادگی، کد دانش‌آموزی یا عنوان آزمون">
    </label>
    <label>
      آزمون
      <select name="quiz">
        <option value="0">همه آزمون‌ها</option>
        <?php foreach ($quizzes as $quiz): ?>
          <option value="<?php echo (int) $quiz['id']; ?>"<?php echo (int) $quiz['id'] === $quizId ? ' selected' : ''; ?>>
            <?php echo Panel::h($quiz['title']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn primary" type="submit">فیلتر</button>
    <a class="btn ghost" href="<?php echo Panel::h(Panel::url('results')); ?>">پاک‌کردن</a>
  </form>

  <div class="grid grid-3 summary">
    <div class="stat">
      <div class="stat-label">نتیجه</div>
      <div class="stat-value"><?php echo Farsi::digits($total); ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">میانگین درصد</div>
      <div class="stat-value"><?php echo Farsi::digits(Farsi::pct($scoreSum, $totalSum)); ?>٪</div>
    </div>
    <div class="stat">
      <div class="stat-label">مجموع نمره خام</div>
      <div class="stat-value small"><?php echo Farsi::digits($scoreSum); ?>/<?php echo Farsi::digits($totalSum); ?></div>
    </div>
  </div>

  <?php if (count($rows) === 0): ?>
    <div class="empty">نتیجه‌ای پیدا نشد.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>دانش‌آموز</th>
            <th>کد</th>
            <th>آزمون</th>
            <th>نمره</th>
            <th>درصد</th>
            <th>پایان</th>
            <th>نحوه پایان</th>
            <th>جزئیات</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td>
                <a href="<?php echo Panel::h(Panel::url('student', array('id' => (int) $row['student_id']))); ?>">
                  <?php echo Panel::h($row['first_name'] . ' ' . $row['last_name']); ?>
                </a>
                <?php if ($row['class_name'] !== null && $row['class_name'] !== ''): ?>
                  <div class="small-text muted"><?php echo Panel::h($row['class_name']); ?></div>
                <?php endif; ?>
              </td>
              <td><code><?php echo Panel::h($row['code']); ?></code></td>
              <td class="wrap-cell">
                <a href="<?php echo Panel::h(Panel::url('quiz', array('id' => (int) $row['quiz_id']))); ?>">
                  <?php echo Panel::h($row['quiz_title']); ?>
                </a>
              </td>
              <td><?php echo Farsi::digits((int) $row['score']); ?>/<?php echo Farsi::digits((int) $row['total']); ?></td>
              <td><?php echo Farsi::digits(Farsi::pct($row['score'], $row['total'])); ?>٪</td>
              <td class="small-text"><?php echo Panel::h(Panel::dt($row['finished_at'])); ?></td>
              <td><?php echo Panel::h(Panel::finishReason($row['finish_reason'])); ?></td>
              <td>
                <a class="btn ghost small" href="<?php echo Panel::h(Panel::url('attempt', array('id' => (int) $row['id']))); ?>">مشاهده</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php echo Panel::pager('results', $pageNo, $pages, $filterParams); ?>
  <?php endif; ?>
</div>
