<?php
/**
 * Shared CSV export for finished attempts.
 */
class ResultsExport
{
    public static function rows($quizId = 0, $search = '')
    {
        $db = App::db();
        $quizId = (int) $quizId;
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

        $search = trim((string) $search);
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $sql .= ' AND (s.code LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?
                      OR s.class_name LIKE ? OR q.title LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY q.id DESC, a.score DESC, a.finished_at ASC';
        return $db->all($sql, $params);
    }

    public static function filename($quizId = 0)
    {
        $quizId = (int) $quizId;
        return 'telebot-results-' . date('Ymd-Hi') . ($quizId > 0 ? '-quiz' . $quizId : '') . '.csv';
    }

    public static function stream($quizId = 0, $search = '')
    {
        $rows = self::rows($quizId, $search);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . self::filename($quizId) . '"');
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
    }
}
