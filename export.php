<?php
/**
 * CSV export of finished attempts (UTF-8 with BOM, Excel friendly).
 * URL: export.php?key=ADMIN_KEY[&quiz=ID]
 */

require __DIR__ . '/app/bootstrap.php';

Guard::authorize();

try {
    $db = App::db();
    $quizId = isset($_GET['quiz']) ? (int) $_GET['quiz'] : 0;

    $sql = 'SELECT a.id, a.status, a.finish_reason, a.score, a.total,
                   a.started_at, a.finished_at,
                   q.id AS quiz_id, q.title AS quiz_title,
                   s.code, s.first_name, s.last_name, s.class_name
              FROM attempts a
              JOIN quizzes q ON q.id = a.quiz_id
              JOIN student_codes s ON s.id = a.student_id
             WHERE a.status = ?';
    $params = array('finished');
    if ($quizId > 0) {
        $sql .= ' AND a.quiz_id = ?';
        $params[] = $quizId;
    }
    $sql .= ' ORDER BY q.id DESC, a.score DESC, a.finished_at ASC';

    $rows = $db->all($sql, $params);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'error: ' . $e->getMessage();
    exit;
}

$name = 'telebot-results-' . date('Ymd-Hi') . ($quizId > 0 ? '-quiz' . $quizId : '') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, array(
    'ردیف',
    'کد دانش‌آموزی',
    'نام',
    'نام خانوادگی',
    'کلاس',
    'آزمون',
    'شناسه آزمون',
    'نمره',
    'از',
    'درصد',
    'زمان شروع',
    'زمان پایان',
    'مدت (ثانیه)',
    'نحوه پایان',
));

$reasons = array(
    'student' => 'توسط دانش‌آموز',
    'timeout' => 'اتمام زمان',
    'admin' => 'توسط ادمین',
);

$i = 0;
foreach ($rows as $r) {
    $i++;
    $spent = 0;
    if (!empty($r['started_at']) && !empty($r['finished_at'])) {
        $spent = max(0, strtotime($r['finished_at']) - strtotime($r['started_at']));
    }
    $reason = isset($reasons[$r['finish_reason']]) ? $reasons[$r['finish_reason']] : '';

    fputcsv($out, array(
        $i,
        Farsi::csvSafe($r['code']),
        Farsi::csvSafe($r['first_name']),
        Farsi::csvSafe($r['last_name']),
        Farsi::csvSafe($r['class_name']),
        Farsi::csvSafe($r['quiz_title']),
        (int) $r['quiz_id'],
        (int) $r['score'],
        (int) $r['total'],
        Farsi::pct($r['score'], $r['total']),
        $r['started_at'],
        $r['finished_at'],
        $spent,
        $reason,
    ));
}

fclose($out);
