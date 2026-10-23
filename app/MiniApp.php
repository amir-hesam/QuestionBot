<?php
/**
 * Student Telegram Mini App: quizzes, codes, attempts, answers and results.
 *
 * All methods receive the Telegram user id that was verified from initData.
 */
class MiniApp
{
    const TRY_LIMIT = 5;
    const TRY_WINDOW = 600;

    public static function enabled()
    {
        return MiniAppAuth::enabled();
    }

    public static function url()
    {
        $cfg = App::cfg('miniapp', array());
        $configured = is_array($cfg) && isset($cfg['url']) ? trim((string) $cfg['url']) : '';
        if ($configured !== '') {
            return rtrim($configured, '/') . '/';
        }

        $webhook = trim((string) App::cfg('webhook_url', ''));
        if ($webhook !== '') {
            $parts = parse_url($webhook);
            if (is_array($parts) && !empty($parts['scheme']) && !empty($parts['host'])) {
                $path = isset($parts['path']) ? $parts['path'] : '/';
                $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
                if ($dir === '.' || $dir === '\\') {
                    $dir = '';
                }
                return $parts['scheme'] . '://' . $parts['host']
                    . ($dir === '' ? '' : $dir) . '/miniapp/';
            }
        }

        try {
            $panel = PanelAccess::panelUrl();
        } catch (Throwable $e) {
            App::log('miniapp url: ' . $e->getMessage());
            $panel = '';
        }

        if ($panel !== '' && preg_match('#(^|/)panel/?$#', $panel)) {
            return preg_replace('#(^|/)panel/?$#', '$1miniapp/', $panel);
        }

        if (!empty($_SERVER['HTTP_HOST'])) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
                    && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
            $scheme = $secure ? 'https' : 'http';
            $script = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '/index.php';
            $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
            if ($dir === '.' || $dir === '\\') {
                $dir = '';
            }
            if (basename($dir) === 'miniapp') {
                return $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir . '/';
            }
            return $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir . '/miniapp/';
        }

        return 'miniapp/';
    }

    /**
     * Absolute HTTPS URL required by Telegram web_app buttons.
     */
    public static function webAppUrl()
    {
        $url = self::url();
        return preg_match('#^https://#i', $url) ? $url : null;
    }

    public static function labels()
    {
        return array('الف', 'ب', 'پ', 'ت');
    }

    public static function home($uid)
    {
        $db = App::db();
        $quizzes = $db->all(
            'SELECT q.*, (SELECT COUNT(*) FROM questions x WHERE x.quiz_id = q.id) AS qc
             FROM quizzes q WHERE q.is_active = 1 ORDER BY q.id DESC'
        );

        $actRows = $db->all(
            'SELECT a.*, q.title, q.time_limit, s.code AS student_code,
                    s.first_name, s.last_name, s.class_name
             FROM attempts a
             JOIN quizzes q ON q.id = a.quiz_id
             JOIN student_codes s ON s.id = a.student_id
             WHERE a.telegram_id = ? AND a.status = \'in_progress\'
             ORDER BY a.id DESC',
            array($uid)
        );

        $active = array();
        $activeByQuiz = array();
        foreach ($actRows as $a) {
            if (Timer::expired($a['deadline'])) {
                Timer::finalize($a['id'], 'timeout');
                continue;
            }
            $remaining = Timer::remaining($a['deadline']);
            $item = array(
                'attempt_id' => (int) $a['id'],
                'quiz_id' => (int) $a['quiz_id'],
                'title' => (string) $a['title'],
                'student_code' => (string) $a['student_code'],
                'student_name' => trim($a['first_name'] . ' ' . $a['last_name']),
                'class_name' => (string) $a['class_name'],
                'current_index' => (int) $a['current_index'],
                'total' => (int) $a['total'],
                'remaining' => $remaining,
                'deadline' => $a['deadline'],
            );
            $active[] = $item;
            if (!isset($activeByQuiz[(int) $a['quiz_id']])) {
                $activeByQuiz[(int) $a['quiz_id']] = $item;
            }
        }

        $list = array();
        foreach ($quizzes as $q) {
            $count = (int) $q['qc'];
            if ($count <= 0) {
                continue;
            }
            $attempt = isset($activeByQuiz[(int) $q['id']]) ? $activeByQuiz[(int) $q['id']] : null;
            $list[] = array(
                'id' => (int) $q['id'],
                'title' => (string) $q['title'],
                'description' => (string) $q['description'],
                'time_limit' => (int) $q['time_limit'],
                'question_count' => $count,
                'has_code' => trim((string) $q['access_code']) !== '',
                'attempt_id' => $attempt ? $attempt['attempt_id'] : 0,
                'remaining' => $attempt ? $attempt['remaining'] : null,
            );
        }

        $history = $db->all(
            'SELECT a.id, a.quiz_id, a.score, a.total, a.finished_at, a.finish_reason,
                    q.title, s.code AS student_code, s.first_name, s.last_name
             FROM attempts a
             JOIN quizzes q ON q.id = a.quiz_id
             JOIN student_codes s ON s.id = a.student_id
             WHERE a.telegram_id = ? AND a.status = \'finished\'
             ORDER BY a.finished_at DESC, a.id DESC
             LIMIT 20',
            array($uid)
        );

        $hist = array();
        foreach ($history as $h) {
            $total = (int) $h['total'];
            $score = (int) $h['score'];
            $hist[] = array(
                'attempt_id' => (int) $h['id'],
                'quiz_id' => (int) $h['quiz_id'],
                'title' => (string) $h['title'],
                'student_code' => (string) $h['student_code'],
                'student_name' => trim($h['first_name'] . ' ' . $h['last_name']),
                'score' => $score,
                'total' => $total,
                'percent' => Farsi::pct($score, $total),
                'finished_at' => $h['finished_at'],
                'finish_reason' => $h['finish_reason'],
            );
        }

        return array(
            'server_time' => time(),
            'quizzes' => $list,
            'active' => $active,
            'history' => $hist,
        );
    }

    public static function enter($uid, $quizId, $examCode, $studentCode)
    {
        $db = App::db();
        $quiz = $db->one('SELECT * FROM quizzes WHERE id = ?', array((int) $quizId));
        if (!$quiz || !(int) $quiz['is_active']) {
            throw new MiniAppError('این آزمون در دسترس نیست.', 404, 'quiz_not_found');
        }

        $questionCount = (int) $db->val(
            'SELECT COUNT(*) FROM questions WHERE quiz_id = ?',
            array((int) $quiz['id'])
        );
        if ($questionCount === 0) {
            throw new MiniAppError('این آزمون هنوز سوالی ندارد.', 422, 'no_questions');
        }

        $access = trim((string) $quiz['access_code']);
        if ($access !== '') {
            $key = self::throttleKey($uid, (int) $quiz['id']);
            $wait = self::throttleWait($key);
            if ($wait > 0) {
                $minutes = max(1, (int) ceil($wait / 60));
                throw new MiniAppError(
                    'تعداد تلاش‌های ناموفق زیاد است. ' . Farsi::digits($minutes) . ' دقیقه دیگر تلاش کنید.',
                    429,
                    'throttled',
                    array('retry_after' => $wait)
                );
            }

            $given = Farsi::normalizeCode($examCode);
            $expected = Farsi::normalizeCode($access);
            if ($given === '' || !hash_equals($expected, $given)) {
                self::recordFailure($key);
                throw new MiniAppError('کد آزمون اشتباه است.', 422, 'bad_exam_code');
            }
            self::clearFailures($key);
        }

        $code = Codes::normalize($studentCode);
        if ($code === '') {
            throw new MiniAppError('کد دانش‌آموزی را وارد کنید.', 422, 'bad_student_code');
        }
        $student = Codes::findByCode($db, $code);
        if (!$student) {
            throw new MiniAppError('کد دانش‌آموزی پیدا نشد.', 422, 'bad_student_code');
        }
        if (!(int) $student['is_active']) {
            throw new MiniAppError('این کد دانش‌آموزی غیرفعال شده است. با ادمین تماس بگیرید.', 403, 'student_disabled');
        }
        if (!empty($student['locked_telegram_id'])
            && (string) $student['locked_telegram_id'] !== (string) $uid) {
            throw new MiniAppError('این کد روی اکانت تلگرام دیگری فعال شده است.', 403, 'student_locked');
        }
        if (empty($student['locked_telegram_id'])) {
            Codes::lock($db, $student['id'], $uid);
            $student = $db->one('SELECT * FROM student_codes WHERE id = ?', array($student['id']));
            if (!$student || (string) $student['locked_telegram_id'] !== (string) $uid) {
                throw new MiniAppError('این کد روی اکانت دیگری فعال شد. دوباره تلاش کنید.', 409, 'student_locked');
            }
        }

        $attempt = self::startOrResume($student, $quiz, $uid);
        if (!is_array($attempt)) {
            throw new MiniAppError('خطا در شروع آزمون. دوباره تلاش کنید.', 500, 'start_failed');
        }

        return array(
            'attempt_id' => (int) $attempt['id'],
            'status' => (string) $attempt['status'],
            'finished' => $attempt['status'] !== 'in_progress',
            'quiz_title' => (string) $quiz['title'],
        );
    }

    public static function examState($uid, $attemptId = 0)
    {
        $att = self::ownedAttempt($uid, (int) $attemptId);
        if ($att['status'] !== 'in_progress') {
            return self::finishedResponse($att);
        }
        if (Timer::expired($att['deadline'])) {
            $att = Timer::finalize($att['id'], 'timeout');
            return self::finishedResponse($att);
        }

        $exam = self::buildExam($att);
        if ($exam === null) {
            $att = Timer::finalize($att['id'], 'student');
            return self::finishedResponse($att);
        }
        return array('finished' => false, 'exam' => $exam);
    }

    public static function answer($uid, $attemptId, $questionId, $letter)
    {
        $db = App::db();
        $att = self::ownedAttempt($uid, (int) $attemptId);
        if ($att['status'] !== 'in_progress') {
            return self::finishedResponse($att);
        }
        if (Timer::expired($att['deadline'])) {
            $att = Timer::finalize($att['id'], 'timeout');
            return self::finishedResponse($att);
        }

        $qOrder = Order::decode($att['question_order'], array());
        $qids = array_map('intval', $qOrder);
        $questionId = (int) $questionId;
        if (!in_array($questionId, $qids, true)) {
            throw new MiniAppError('این سوال متعلق به آزمون نیست.', 422, 'bad_question');
        }

        $letter = strtoupper(trim((string) $letter));
        if (!in_array($letter, array('A', 'B', 'C', 'D'), true)) {
            throw new MiniAppError('گزینه انتخاب‌شده معتبر نیست.', 422, 'bad_option');
        }

        $q = $db->one('SELECT * FROM questions WHERE id = ?', array($questionId));
        if (!$q) {
            throw new MiniAppError('این سوال پیدا نشد.', 404, 'bad_question');
        }
        if ($q['correct'] === null || !in_array(strtoupper((string) $q['correct']), array('A', 'B', 'C', 'D'), true)) {
            throw new MiniAppError('پاسخ درست این سوال تنظیم نشده است.', 422, 'bad_question');
        }

        $isCorrect = $letter === strtoupper((string) $q['correct']) ? 1 : 0;
        $db->exec(
            'INSERT INTO answers (attempt_id, question_id, selected, is_correct, updated_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE selected = VALUES(selected), is_correct = VALUES(is_correct), updated_at = VALUES(updated_at)',
            array($att['id'], $questionId, $letter, $isCorrect, App::now())
        );

        $pos = array_search($questionId, $qids, true);
        if ($pos !== false) {
            $db->exec('UPDATE attempts SET current_index = ? WHERE id = ?', array($pos, $att['id']));
        }

        return self::examState($uid, (int) $att['id']);
    }

    public static function navigate($uid, $attemptId, $index)
    {
        $db = App::db();
        $att = self::ownedAttempt($uid, (int) $attemptId);
        if ($att['status'] !== 'in_progress') {
            return self::finishedResponse($att);
        }
        if (Timer::expired($att['deadline'])) {
            $att = Timer::finalize($att['id'], 'timeout');
            return self::finishedResponse($att);
        }

        $qOrder = Order::decode($att['question_order'], array());
        $total = count($qOrder);
        if ($total === 0) {
            return self::finishedResponse(Timer::finalize($att['id'], 'student'));
        }
        $index = (int) $index;
        if ($index < 0) {
            $index = 0;
        }
        if ($index > $total - 1) {
            $index = $total - 1;
        }
        $db->exec('UPDATE attempts SET current_index = ? WHERE id = ?', array($index, $att['id']));
        return self::examState($uid, (int) $att['id']);
    }

    public static function finish($uid, $attemptId)
    {
        $att = self::ownedAttempt($uid, (int) $attemptId);
        if ($att['status'] !== 'in_progress') {
            return self::finishedResponse($att);
        }
        $reason = Timer::expired($att['deadline']) ? 'timeout' : 'student';
        $att = Timer::finalize($att['id'], $reason);
        return self::finishedResponse($att);
    }

    public static function result($uid, $attemptId)
    {
        $att = self::ownedAttempt($uid, (int) $attemptId);
        if ($att['status'] === 'in_progress') {
            if (!Timer::expired($att['deadline'])) {
                throw new MiniAppError('این آزمون هنوز تمام نشده است.', 409, 'in_progress');
            }
            $att = Timer::finalize($att['id'], 'timeout');
        }
        return self::finishedResponse($att);
    }

    private static function startOrResume($student, $quiz, $uid)
    {
        $db = App::db();
        $att = $db->one(
            'SELECT * FROM attempts WHERE student_id = ? AND quiz_id = ?',
            array($student['id'], $quiz['id'])
        );

        if ($att) {
            if ($att['status'] === 'in_progress') {
                if (Timer::expired($att['deadline'])) {
                    return Timer::finalize($att['id'], 'timeout');
                }
                if ((string) $att['telegram_id'] !== (string) $uid) {
                    $db->exec(
                        'UPDATE attempts SET telegram_id = ? WHERE id = ?',
                        array($uid, $att['id'])
                    );
                    $att['telegram_id'] = $uid;
                }
                return $att;
            }
            return $att;
        }

        $rows = $db->all(
            'SELECT id FROM questions WHERE quiz_id = ? ORDER BY position ASC, id ASC',
            array($quiz['id'])
        );
        if (count($rows) === 0) {
            return null;
        }
        $ids = array();
        foreach ($rows as $r) {
            $ids[] = (int) $r['id'];
        }
        $shuffle = (int) $quiz['shuffle'] === 1;
        $qOrder = Order::questions($ids, $shuffle);
        $oOrder = Order::options($ids, $shuffle);
        $started = App::now();
        $deadline = Timer::deadline($started, (int) $quiz['time_limit']);

        try {
            $db->exec(
                'INSERT INTO attempts
                    (quiz_id, student_id, telegram_id, status, current_index, score, total,
                     question_order, option_order, started_at, deadline)
                 VALUES (?, ?, ?, ?, 0, 0, ?, ?, ?, ?, ?)',
                array(
                    $quiz['id'],
                    $student['id'],
                    $uid,
                    'in_progress',
                    count($qOrder),
                    Order::encode($qOrder),
                    Order::encode($oOrder),
                    $started,
                    $deadline,
                )
            );
            return $db->one('SELECT * FROM attempts WHERE id = ?', array($db->id()));
        } catch (PDOException $e) {
            $att = $db->one(
                'SELECT * FROM attempts WHERE student_id = ? AND quiz_id = ?',
                array($student['id'], $quiz['id'])
            );
            return $att ? $att : null;
        }
    }

    private static function ownedAttempt($uid, $attemptId)
    {
        $db = App::db();
        if ($attemptId > 0) {
            $att = $db->one(
                'SELECT * FROM attempts WHERE id = ? AND telegram_id = ?',
                array($attemptId, $uid)
            );
        } else {
            $att = $db->one(
                'SELECT * FROM attempts WHERE telegram_id = ? AND status = \'in_progress\'
                 ORDER BY id DESC LIMIT 1',
                array($uid)
            );
        }
        if (!$att) {
            throw new MiniAppError('این آزمون پیدا نشد یا به حساب شما تعلق ندارد.', 404, 'attempt_not_found');
        }
        return $att;
    }

    private static function finishedResponse($att)
    {
        if (!$att) {
            throw new MiniAppError('نتیجه آزمون پیدا نشد.', 404, 'attempt_not_found');
        }
        return array(
            'finished' => true,
            'attempt_id' => (int) $att['id'],
            'result' => self::resultData($att),
        );
    }

    private static function buildExam($att)
    {
        $db = App::db();
        $quiz = $db->one('SELECT * FROM quizzes WHERE id = ?', array($att['quiz_id']));
        if (!$quiz) {
            return null;
        }
        $student = $db->one('SELECT * FROM student_codes WHERE id = ?', array($att['student_id']));
        $qOrder = Order::decode($att['question_order'], array());
        $oOrder = Order::decode($att['option_order'], array());
        $total = count($qOrder);
        if ($total === 0) {
            return null;
        }

        $idx = (int) $att['current_index'];
        if ($idx < 0) {
            $idx = 0;
        }
        if ($idx >= $total) {
            $idx = $total - 1;
        }

        $q = null;
        $qid = 0;
        while ($idx < $total) {
            $qid = (int) $qOrder[$idx];
            $q = $db->one('SELECT * FROM questions WHERE id = ?', array($qid));
            if ($q) {
                break;
            }
            $q = null;
            $idx++;
        }
        if (!$q) {
            return null;
        }
        if ($idx !== (int) $att['current_index']) {
            $db->exec('UPDATE attempts SET current_index = ? WHERE id = ?', array($idx, $att['id']));
        }

        $order = isset($oOrder[(string) $qid]) ? $oOrder[(string) $qid] : array('A', 'B', 'C', 'D');
        if (!is_array($order) || count($order) !== 4) {
            $order = array('A', 'B', 'C', 'D');
        }

        $ans = $db->one(
            'SELECT * FROM answers WHERE attempt_id = ? AND question_id = ?',
            array($att['id'], $qid)
        );
        $selected = $ans && $ans['selected'] !== null ? strtoupper((string) $ans['selected']) : null;
        $showAnswer = (int) $quiz['show_answer'] === 1;
        $reveal = $showAnswer && $selected !== null;
        $correct = strtoupper((string) $q['correct']);
        $labels = self::labels();

        $options = array();
        foreach ($order as $i => $orig) {
            if ($i > 3) {
                break;
            }
            $key = strtoupper((string) $orig);
            $opt = array(
                'key' => $key,
                'label' => $labels[$i],
                'text' => (string) $q['option_' . strtolower($key)],
                'selected' => $selected !== null && $selected === $key,
            );
            if ($reveal) {
                $opt['correct'] = $key === $correct;
                $opt['wrong'] = $selected === $key && $key !== $correct;
            }
            $options[] = $opt;
        }

        $answeredRows = $db->all(
            'SELECT question_id FROM answers WHERE attempt_id = ? AND selected IS NOT NULL',
            array($att['id'])
        );
        $answeredIds = array();
        foreach ($answeredRows as $r) {
            $answeredIds[] = (int) $r['question_id'];
        }

        $correctLabel = '';
        if ($reveal) {
            $ci = array_search($correct, $order, true);
            $correctLabel = $ci === false ? $correct : $labels[$ci];
        }

        return array(
            'attempt_id' => (int) $att['id'],
            'quiz' => array(
                'id' => (int) $quiz['id'],
                'title' => (string) $quiz['title'],
                'description' => (string) $quiz['description'],
                'time_limit' => (int) $quiz['time_limit'],
                'show_answer' => $showAnswer,
            ),
            'student' => array(
                'code' => $student ? (string) $student['code'] : '',
                'name' => $student ? trim($student['first_name'] . ' ' . $student['last_name']) : '',
                'class_name' => $student ? (string) $student['class_name'] : '',
            ),
            'index' => $idx,
            'total' => $total,
            'deadline' => $att['deadline'],
            'remaining' => Timer::remaining($att['deadline']),
            'server_time' => time(),
            'answered_count' => count($answeredIds),
            'answered_ids' => $answeredIds,
            'question_ids' => array_map('intval', $qOrder),
            'can_prev' => $idx > 0,
            'can_next' => $idx < $total - 1,
            'question' => array(
                'id' => $qid,
                'text' => (string) $q['text'],
                'selected' => $selected,
                'revealed' => $reveal,
                'options' => $options,
                'correct' => $reveal ? $correct : null,
                'correct_label' => $reveal ? $correctLabel : '',
            ),
        );
    }

    private static function resultData($att)
    {
        $db = App::db();
        $quiz = $db->one('SELECT * FROM quizzes WHERE id = ?', array($att['quiz_id']));
        $student = $db->one('SELECT * FROM student_codes WHERE id = ?', array($att['student_id']));
        $qOrder = Order::decode($att['question_order'], array());
        $oOrder = Order::decode($att['option_order'], array());
        $qids = array_map('intval', $qOrder);

        $questions = array();
        if (count($qids) > 0) {
            $in = implode(',', array_fill(0, count($qids), '?'));
            $rows = $db->all('SELECT * FROM questions WHERE id IN (' . $in . ')', $qids);
            foreach ($rows as $r) {
                $questions[(int) $r['id']] = $r;
            }
        }
        $ansRows = $db->all('SELECT * FROM answers WHERE attempt_id = ?', array($att['id']));
        $ansMap = array();
        foreach ($ansRows as $r) {
            $ansMap[(int) $r['question_id']] = $r;
        }

        $total = (int) $att['total'];
        $score = (int) $att['score'];
        $percent = Farsi::pct($score, $total);
        $spent = 0;
        if (!empty($att['started_at']) && !empty($att['finished_at'])) {
            $spent = max(0, strtotime($att['finished_at']) - strtotime($att['started_at']));
        }

        $showDetails = $quiz && (int) $quiz['show_answer'] === 1;
        $wrong = array();
        if ($showDetails) {
            $labels = self::labels();
            $n = 0;
            foreach ($qids as $qid) {
                $n++;
                if (!isset($questions[$qid])) {
                    continue;
                }
                $a = isset($ansMap[$qid]) ? $ansMap[$qid] : null;
                if ($a && (int) $a['is_correct'] === 1) {
                    continue;
                }
                $q = $questions[$qid];
                $order = isset($oOrder[(string) $qid]) ? $oOrder[(string) $qid] : array('A', 'B', 'C', 'D');
                if (!is_array($order) || count($order) !== 4) {
                    $order = array('A', 'B', 'C', 'D');
                }

                $sel = null;
                if ($a && $a['selected'] !== null) {
                    $selKey = strtoupper((string) $a['selected']);
                    $si = array_search($selKey, $order, true);
                    $sel = array(
                        'label' => $si === false ? $selKey : $labels[$si],
                        'text' => (string) $q['option_' . strtolower($selKey)],
                    );
                }
                $corrKey = strtoupper((string) $q['correct']);
                $ci = array_search($corrKey, $order, true);
                $wrong[] = array(
                    'n' => $n,
                    'question' => (string) $q['text'],
                    'selected' => $sel,
                    'correct' => array(
                        'label' => $ci === false ? $corrKey : $labels[$ci],
                        'text' => (string) $q['option_' . strtolower($corrKey)],
                    ),
                );
            }
        }

        return array(
            'attempt_id' => (int) $att['id'],
            'status' => (string) $att['status'],
            'finish_reason' => (string) $att['finish_reason'],
            'expired' => (string) $att['finish_reason'] === 'timeout',
            'quiz' => array(
                'id' => (int) $att['quiz_id'],
                'title' => $quiz ? (string) $quiz['title'] : '',
            ),
            'student' => array(
                'code' => $student ? (string) $student['code'] : '',
                'name' => $student ? trim($student['first_name'] . ' ' . $student['last_name']) : '',
                'class_name' => $student ? (string) $student['class_name'] : '',
            ),
            'score' => $score,
            'total' => $total,
            'percent' => $percent,
            'spent' => $spent,
            'finished_at' => $att['finished_at'],
            'show_details' => $showDetails,
            'wrong' => $wrong,
        );
    }

    private static function throttleKey($uid, $quizId)
    {
        return 'ma_try_' . substr(hash('sha256', $uid . '|' . $quizId), 0, 40);
    }

    private static function throttleWait($key)
    {
        $db = App::db();
        $raw = $db->val('SELECT v FROM kv WHERE k = ?', array($key));
        if (!is_string($raw) || $raw === '') {
            return 0;
        }
        $row = json_decode($raw, true);
        if (!is_array($row)) {
            return 0;
        }
        $count = isset($row['n']) ? (int) $row['n'] : 0;
        $first = isset($row['first']) ? (int) $row['first'] : 0;
        if ($count < self::TRY_LIMIT || $first <= 0 || $first < time() - self::TRY_WINDOW) {
            return 0;
        }
        $left = ($first + self::TRY_WINDOW) - time();
        return $left > 0 ? $left : 0;
    }

    private static function recordFailure($key)
    {
        $db = App::db();
        $raw = $db->val('SELECT v FROM kv WHERE k = ?', array($key));
        $row = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($row) || empty($row['first']) || (int) $row['first'] < time() - self::TRY_WINDOW) {
            $row = array('n' => 0, 'first' => time());
        }
        $row['n'] = isset($row['n']) ? (int) $row['n'] + 1 : 1;
        $db->exec(
            'REPLACE INTO kv (k, v) VALUES (?, ?)',
            array($key, json_encode($row, JSON_UNESCAPED_UNICODE))
        );
    }

    private static function clearFailures($key)
    {
        App::db()->exec('DELETE FROM kv WHERE k = ?', array($key));
    }
}
