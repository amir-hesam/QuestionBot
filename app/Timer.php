<?php
/**
 * Deadline math + attempt finalization + admin notification.
 */
class Timer
{
    public static function deadline($startedAt, $timeLimit)
    {
        $limit = (int) $timeLimit;
        if ($limit <= 0) {
            return null;
        }
        $ts = strtotime((string) $startedAt);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $ts + $limit);
    }

    public static function remaining($deadline)
    {
        if ($deadline === null || $deadline === '') {
            return null;
        }
        $ts = strtotime((string) $deadline);
        if ($ts === false) {
            return null;
        }
        return $ts - time();
    }

    public static function expired($deadline)
    {
        $r = self::remaining($deadline);
        return $r !== null && $r <= 0;
    }

    public static function fmt($seconds)
    {
        return Farsi::dur($seconds);
    }

    /**
     * Mark an attempt as finished exactly once and notify admins.
     * Returns the fresh attempt row.
     */
    public static function finalize($attemptId, $reason = 'student')
    {
        $db = App::db();
        $a = $db->one('SELECT * FROM attempts WHERE id = ?', array($attemptId));
        if (!$a) {
            return null;
        }
        if ($a['status'] !== 'in_progress') {
            return $a;
        }

        $qOrder = Order::decode($a['question_order'], array());
        $qids = array_values(array_map('intval', $qOrder));
        $total = 0;
        if (count($qids) > 0) {
            $in = implode(',', array_fill(0, count($qids), '?'));
            $total = (int) $db->val('SELECT COUNT(*) FROM questions WHERE id IN (' . $in . ')', $qids);
        }
        $score = (int) $db->val(
            'SELECT COUNT(*) FROM answers WHERE attempt_id = ? AND is_correct = 1',
            array($attemptId)
        );

        $changed = $db->exec(
            "UPDATE attempts
                SET status = 'finished', finish_reason = ?, score = ?, total = ?, finished_at = ?
              WHERE id = ? AND status = 'in_progress'",
            array($reason, $score, $total, App::now(), $attemptId)
        );

        $fresh = $db->one('SELECT * FROM attempts WHERE id = ?', array($attemptId));
        if ((int) $changed === 1) {
            self::notify($fresh, $reason);
        }
        return $fresh;
    }

    private static function notify($a, $reason)
    {
        $db = App::db();
        $quiz = $db->one('SELECT * FROM quizzes WHERE id = ?', array($a['quiz_id']));
        $st = $db->one('SELECT * FROM student_codes WHERE id = ?', array($a['student_id']));
        if (!$quiz || !$st) {
            return;
        }

        $total = (int) $a['total'];
        $score = (int) $a['score'];
        $pct = Farsi::pct($score, $total);
        $spent = 0;
        if (!empty($a['started_at']) && !empty($a['finished_at'])) {
            $spent = max(0, strtotime($a['finished_at']) - strtotime($a['started_at']));
        }

        if ($reason === 'timeout') {
            $reasonFa = '⏰ اتمام زمان';
        } elseif ($reason === 'admin') {
            $reasonFa = 'توسط ادمین';
        } else {
            $reasonFa = 'توسط دانش‌آموز';
        }

        $lines = array();
        $lines[] = '📊 <b>نتیجه آزمون</b>';
        $lines[] = 'آزمون: ' . Farsi::esc($quiz['title']);
        $lines[] = 'کد دانش‌آموزی: <code>' . Farsi::esc($st['code']) . '</code>';
        $name = $st['first_name'] . ' ' . $st['last_name'];
        if (!empty($st['class_name'])) {
            $name .= ' — کلاس ' . $st['class_name'];
        }
        $lines[] = 'نام: ' . Farsi::esc($name);
        $lines[] = 'نمره: ' . Farsi::digits($score) . ' از ' . Farsi::digits($total)
            . ' (' . Farsi::digits($pct) . '٪)';
        if ((int) $quiz['time_limit'] > 0) {
            $lines[] = 'زمان: ' . Farsi::digits(self::fmt($spent)) . ' از '
                . Farsi::digits(self::fmt((int) $quiz['time_limit']));
        } else {
            $lines[] = 'زمان: ' . Farsi::digits(self::fmt($spent));
        }
        $lines[] = 'پایان: ' . $reasonFa;

        App::tg()->sendMany(App::cfg('admin_ids'), implode("\n", $lines));
    }
}
