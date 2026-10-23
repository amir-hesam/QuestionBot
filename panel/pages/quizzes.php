<?php
if (!defined('TELEBOT_PANEL')) {
    exit;
}

$pageTitle = 'آزمون‌ها';
$db = App::db();
$errors = array();

if (Panel::isPost()) {
    $action = Panel::post('action');

    if ($action === 'create') {
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
                'INSERT INTO quizzes (title, description, access_code, time_limit, shuffle, show_answer, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                array(
                    $title,
                    $description !== '' ? $description : null,
                    $accessCode,
                    $timeMin * 60,
                    $shuffle,
                    $showAnswer,
                    $isActive,
                    App::now(),
                )
            );
            $id = $db->id();
            Panel::flashRedirect('ok', 'آزمون ساخته شد. حالا سوال‌ها را اضافه کنید.', Panel::url('quiz', array('id' => $id)));
        }
    } elseif ($action === 'delete') {
        $id = Panel::intPost('id');
        if ($id > 0) {
            $db->exec('DELETE FROM quizzes WHERE id = ?', array($id));
            Panel::flashRedirect('ok', 'آزمون و همه داده‌هایش حذف شد.', Panel::url('quizzes'));
        }
    } elseif ($action === 'toggle') {
        $id = Panel::intPost('id');
        if ($id > 0) {
            $db->exec('UPDATE quizzes SET is_active = 1 - is_active WHERE id = ?', array($id));
            Panel::flashRedirect('ok', 'وضعیت آزمون تغییر کرد.', Panel::url('quizzes'));
        }
    } else {
        Panel::flashRedirect('err', 'عملیات نامعتبر بود.', Panel::url('quizzes'));
    }
}

$q = trim(Panel::get('q', ''));
$status = Panel::get('status', 'all');
if (!in_array($status, array('all', 'active', 'inactive'), true)) {
    $status = 'all';
}
$pageNo = max(1, Panel::intGet('page', 1));

$where = array();
$params = array();
if ($q !== '') {
    $like = '%' . Panel::like($q) . '%';
    $where[] = '(title LIKE ? OR description LIKE ? OR access_code LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($status === 'active') {
    $where[] = 'is_active = 1';
} elseif ($status === 'inactive') {
    $where[] = 'is_active = 0';
}
$whereSql = count($where) > 0 ? ' WHERE ' . implode(' AND ', $where) : '';

$total = (int) $db->val('SELECT COUNT(*) FROM quizzes' . $whereSql, $params, 0);
list($pageNo, $pages, $offset) = Panel::pageNumber($total, 20, $pageNo);

$rows = $db->all(
    'SELECT q.*,
            (SELECT COUNT(*) FROM questions x WHERE x.quiz_id = q.id) AS question_count,
            (SELECT COUNT(*) FROM attempts a WHERE a.quiz_id = q.id) AS attempt_count,
            (SELECT COUNT(*) FROM attempts a WHERE a.quiz_id = q.id AND a.status = \'finished\') AS finished_count
       FROM quizzes q' . $whereSql . '
      ORDER BY q.id DESC
      LIMIT 20 OFFSET ' . (int) $offset,
    $params
);

$showNew = Panel::intGet('new', 0) === 1 || count($errors) > 0;
$listParams = array('q' => $q, 'status' => $status);
?>
<?php if (count($errors) > 0): ?>
  <div class="alert err"><?php echo Panel::h(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <h2>آزمون‌ها (<?php echo Farsi::digits($total); ?>)</h2>
  </div>

  <form method="get" action="index.php" class="filters">
    <input type="hidden" name="page" value="quizzes">
    <label class="grow">
      جستجو
      <input type="search" name="q" value="<?php echo Panel::h($q); ?>" placeholder="عنوان، توضیح یا کد ورود">
    </label>
    <label>
      وضعیت
      <select name="status">
        <option value="all"<?php echo $status === 'all' ? ' selected' : ''; ?>>همه</option>
        <option value="active"<?php echo $status === 'active' ? ' selected' : ''; ?>>فعال</option>
        <option value="inactive"<?php echo $status === 'inactive' ? ' selected' : ''; ?>>غیرفعال</option>
      </select>
    </label>
    <button class="btn primary" type="submit">فیلتر</button>
    <a class="btn ghost" href="<?php echo Panel::h(Panel::url('quizzes')); ?>">پاک‌کردن</a>
  </form>

  <?php if (count($rows) === 0): ?>
    <div class="empty">آزمونی پیدا نشد.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>شناسه</th>
            <th>عنوان</th>
            <th>سوال</th>
            <th>شرکت‌کننده</th>
            <th>زمان</th>
            <th>کد ورود</th>
            <th>گزینه‌ها</th>
            <th>وضعیت</th>
            <th>عملیات</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $quiz): ?>
            <tr>
              <td><?php echo Farsi::digits((int) $quiz['id']); ?></td>
              <td class="wrap-cell"><?php echo Panel::h($quiz['title']); ?></td>
              <td><?php echo Farsi::digits((int) $quiz['question_count']); ?></td>
              <td>
                <?php echo Farsi::digits((int) $quiz['finished_count']); ?>/<?php echo Farsi::digits((int) $quiz['attempt_count']); ?>
              </td>
              <td><?php echo (int) $quiz['time_limit'] > 0 ? Farsi::digits((int) round($quiz['time_limit'] / 60)) . ' دقیقه' : 'آزاد'; ?></td>
              <td><?php echo $quiz['access_code'] !== null && $quiz['access_code'] !== '' ? '<code>' . Panel::h($quiz['access_code']) . '</code>' : '—'; ?></td>
              <td class="small-text">
                <?php echo (int) $quiz['shuffle'] ? 'تصادفی' : 'ثابت'; ?>
                ·
                <?php echo (int) $quiz['show_answer'] ? 'نمایش پاسخ' : 'بدون پاسخ'; ?>
              </td>
              <td>
                <span class="badge <?php echo (int) $quiz['is_active'] ? 'ok' : 'gray'; ?>">
                  <?php echo (int) $quiz['is_active'] ? 'فعال' : 'غیرفعال'; ?>
                </span>
              </td>
              <td>
                <div class="actions">
                  <a class="btn ghost small" href="<?php echo Panel::h(Panel::url('quiz', array('id' => (int) $quiz['id']))); ?>">مدیریت</a>
                  <form method="post" action="<?php echo Panel::h(Panel::url('quizzes')); ?>">
                    <?php echo PanelAuth::csrfField(); ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?php echo (int) $quiz['id']; ?>">
                    <button class="btn ghost small" type="submit"><?php echo (int) $quiz['is_active'] ? 'غیرفعال' : 'فعال'; ?></button>
                  </form>
                  <form method="post" action="<?php echo Panel::h(Panel::url('quizzes')); ?>" data-confirm="آزمون، سوال‌ها و همه نتایج آن حذف شوند؟ این کار قابل بازگشت نیست.">
                    <?php echo PanelAuth::csrfField(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int) $quiz['id']; ?>">
                    <button class="btn danger small" type="submit">حذف</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php echo Panel::pager('quizzes', $pageNo, $pages, $listParams); ?>
  <?php endif; ?>
</div>

<div class="card">
  <details<?php echo $showNew ? ' open' : ''; ?>>
    <summary>➕ ساخت آزمون جدید</summary>
    <form method="post" action="<?php echo Panel::h(Panel::url('quizzes')); ?>" class="stack">
      <?php echo PanelAuth::csrfField(); ?>
      <input type="hidden" name="action" value="create">
      <div class="form-grid">
        <label class="full">
          عنوان آزمون
          <input type="text" name="title" value="<?php echo Panel::h(Panel::post('title')); ?>" maxlength="200" required>
        </label>
        <label class="full">
          توضیح (اختیاری)
          <textarea name="description" maxlength="1000"><?php echo Panel::h(Panel::post('description')); ?></textarea>
        </label>
        <label>
          زمان به دقیقه (۰ = بدون محدودیت)
          <input type="number" name="time_min" min="0" max="600" value="<?php echo Panel::h(Panel::post('time_min', '0')); ?>">
        </label>
        <label>
          کد ورود آزمون (اختیاری)
          <input type="text" name="access_code" value="<?php echo Panel::h(Panel::post('access_code')); ?>" maxlength="32" placeholder="خالی = بدون کد">
          <span class="field-hint">فقط حروف انگلیسی و عدد؛ ۲ تا ۳۲ کاراکتر.</span>
        </label>
        <label class="check full">
          <input type="checkbox" name="shuffle" value="1"<?php echo !Panel::isPost() || isset($_POST['shuffle']) ? ' checked' : ''; ?>>
          سوال‌ها و گزینه‌ها برای هر دانش‌آموز تصادفی شوند
        </label>
        <label class="check full">
          <input type="checkbox" name="show_answer" value="1"<?php echo !Panel::isPost() || isset($_POST['show_answer']) ? ' checked' : ''; ?>>
          پاسخ درست بعد از هر سوال نمایش داده شود
        </label>
        <label class="check full">
          <input type="checkbox" name="is_active" value="1"<?php echo !Panel::isPost() || isset($_POST['is_active']) ? ' checked' : ''; ?>>
          آزمون فعال باشد
        </label>
      </div>
      <div class="row">
        <button class="btn primary" type="submit">ساخت آزمون</button>
      </div>
    </form>
  </details>
</div>
