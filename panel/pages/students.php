<?php
if (!defined('TELEBOT_PANEL')) {
    exit;
}

$pageTitle = 'دانش‌آموزان';
$db = App::db();

if (Panel::isPost()) {
    $action = Panel::post('action');
    $id = Panel::intPost('id');
    $q = Panel::post('return_q');
    $status = Panel::post('return_status', 'all');
    $pageNo = max(1, Panel::intPost('return_page', 1));
    $back = Panel::url('students', array('q' => $q, 'status' => $status, 'page' => $pageNo));

    if ($id > 0 && $action === 'delete') {
        $db->exec('DELETE FROM student_codes WHERE id = ?', array($id));
        Panel::flashRedirect('ok', 'دانش‌آموز و همه نتایج او حذف شد.', $back);
    }
    if ($id > 0 && $action === 'unlock') {
        $db->exec('UPDATE student_codes SET locked_telegram_id = NULL WHERE id = ?', array($id));
        Panel::flashRedirect('ok', 'قفل تلگرام دانش‌آموز آزاد شد.', $back);
    }
    if ($id > 0 && $action === 'toggle') {
        $db->exec('UPDATE student_codes SET is_active = 1 - is_active WHERE id = ?', array($id));
        Panel::flashRedirect('ok', 'وضعیت دانش‌آموز تغییر کرد.', $back);
    }
    Panel::flashRedirect('err', 'عملیات نامعتبر بود.', $back);
}

$q = trim(Panel::get('q', ''));
$status = Panel::get('status', 'all');
if (!in_array($status, array('all', 'active', 'inactive', 'locked'), true)) {
    $status = 'all';
}
$pageNo = max(1, Panel::intGet('page', 1));

$where = array();
$params = array();
if ($q !== '') {
    $like = '%' . Panel::like($q) . '%';
    $where[] = '(code LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR class_name LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($status === 'active') {
    $where[] = 'is_active = 1';
} elseif ($status === 'inactive') {
    $where[] = 'is_active = 0';
} elseif ($status === 'locked') {
    $where[] = 'locked_telegram_id IS NOT NULL';
}
$whereSql = count($where) > 0 ? ' WHERE ' . implode(' AND ', $where) : '';

$total = (int) $db->val('SELECT COUNT(*) FROM student_codes' . $whereSql, $params, 0);
list($pageNo, $pages, $offset) = Panel::pageNumber($total, 25, $pageNo);

$rows = $db->all(
    'SELECT s.*,
            (SELECT COUNT(*) FROM attempts a WHERE a.student_id = s.id) AS attempt_count,
            (SELECT COUNT(*) FROM attempts a WHERE a.student_id = s.id AND a.status = \'finished\') AS finished_count
       FROM student_codes s' . $whereSql . '
      ORDER BY s.id DESC
      LIMIT 25 OFFSET ' . (int) $offset,
    $params
);

$listParams = array('q' => $q, 'status' => $status);
?>
<div class="card">
  <div class="card-head">
    <h2>لیست دانش‌آموزان (<?php echo Farsi::digits($total); ?>)</h2>
    <a class="btn primary" href="<?php echo Panel::h(Panel::url('student', array('new' => 1))); ?>">➕ دانش‌آموز جدید</a>
  </div>

  <form method="get" action="index.php" class="filters">
    <input type="hidden" name="page" value="students">
    <label class="grow">
      جستجو
      <input type="search" name="q" value="<?php echo Panel::h($q); ?>" placeholder="نام، نام خانوادگی، کد یا کلاس">
    </label>
    <label>
      وضعیت
      <select name="status">
        <option value="all"<?php echo $status === 'all' ? ' selected' : ''; ?>>همه</option>
        <option value="active"<?php echo $status === 'active' ? ' selected' : ''; ?>>فعال</option>
        <option value="inactive"<?php echo $status === 'inactive' ? ' selected' : ''; ?>>غیرفعال</option>
        <option value="locked"<?php echo $status === 'locked' ? ' selected' : ''; ?>>قفل‌شده روی تلگرام</option>
      </select>
    </label>
    <button class="btn primary" type="submit">فیلتر</button>
    <a class="btn ghost" href="<?php echo Panel::h(Panel::url('students')); ?>">پاک‌کردن</a>
  </form>

  <?php if (count($rows) === 0): ?>
    <div class="empty">دانش‌آموزی پیدا نشد.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>کد</th>
            <th>نام</th>
            <th>کلاس</th>
            <th>وضعیت</th>
            <th>قفل تلگرام</th>
            <th>تلاش‌ها</th>
            <th>عملیات</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <?php $locked = !empty($row['locked_telegram_id']); ?>
            <tr>
              <td><code><?php echo Panel::h($row['code']); ?></code></td>
              <td><?php echo Panel::h($row['first_name'] . ' ' . $row['last_name']); ?></td>
              <td><?php echo $row['class_name'] !== null && $row['class_name'] !== '' ? Panel::h($row['class_name']) : '—'; ?></td>
              <td>
                <span class="badge <?php echo (int) $row['is_active'] ? 'ok' : 'gray'; ?>">
                  <?php echo (int) $row['is_active'] ? 'فعال' : 'غیرفعال'; ?>
                </span>
              </td>
              <td>
                <?php if ($locked): ?>
                  <span class="badge warn" title="شناسه تلگرام">قفل</span>
                  <span class="small-text muted"><?php echo Panel::h($row['locked_telegram_id']); ?></span>
                <?php else: ?>
                  <span class="badge ok">آزاد</span>
                <?php endif; ?>
              </td>
              <td>
                <?php echo Farsi::digits((int) $row['finished_count']); ?>/<?php echo Farsi::digits((int) $row['attempt_count']); ?>
              </td>
              <td>
                <div class="actions">
                  <a class="btn ghost small" href="<?php echo Panel::h(Panel::url('student', array('id' => (int) $row['id']))); ?>">مشاهده</a>

                  <?php if ($locked): ?>
                    <form method="post" action="<?php echo Panel::h(Panel::url('students')); ?>" data-confirm="قفل تلگرام این دانش‌آموز آزاد شود؟">
                      <?php echo PanelAuth::csrfField(); ?>
                      <input type="hidden" name="action" value="unlock">
                      <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                      <input type="hidden" name="return_q" value="<?php echo Panel::h($q); ?>">
                      <input type="hidden" name="return_status" value="<?php echo Panel::h($status); ?>">
                      <input type="hidden" name="return_page" value="<?php echo (int) $pageNo; ?>">
                      <button class="btn ghost small" type="submit">آزادسازی قفل</button>
                    </form>
                  <?php endif; ?>

                  <form method="post" action="<?php echo Panel::h(Panel::url('students')); ?>">
                    <?php echo PanelAuth::csrfField(); ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                    <input type="hidden" name="return_q" value="<?php echo Panel::h($q); ?>">
                    <input type="hidden" name="return_status" value="<?php echo Panel::h($status); ?>">
                    <input type="hidden" name="return_page" value="<?php echo (int) $pageNo; ?>">
                    <button class="btn ghost small" type="submit"><?php echo (int) $row['is_active'] ? 'غیرفعال' : 'فعال'; ?></button>
                  </form>

                  <form method="post" action="<?php echo Panel::h(Panel::url('students')); ?>" data-confirm="دانش‌آموز و همه نتایج او حذف شوند؟ این کار قابل بازگشت نیست.">
                    <?php echo PanelAuth::csrfField(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                    <input type="hidden" name="return_q" value="<?php echo Panel::h($q); ?>">
                    <input type="hidden" name="return_status" value="<?php echo Panel::h($status); ?>">
                    <input type="hidden" name="return_page" value="<?php echo (int) $pageNo; ?>">
                    <button class="btn danger small" type="submit">حذف</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php echo Panel::pager('students', $pageNo, $pages, $listParams); ?>
  <?php endif; ?>
</div>
