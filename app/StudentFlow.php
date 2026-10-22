<?php
/**
 * Student exam flow: identity by codes, navigation, timers, results.
 */
class StudentFlow
{
    private static function labels()
    {
        return array('الف', 'ب', 'پ', 'ت');
    }

    private static function say($chatId, $text, $mid = null, $kb = null)
    {
        $tg = App::tg();
        if ($mid) {
            $r = $tg->edit($chatId, $mid, $text, $kb);
            if (!empty($r['ok'])) {
                return;
            }
        }
        $tg->send($chatId, $text, $kb);
    }

    public static function help($chatId)
    {
        App::tg()->send(
            $chatId,
            "🎓 <b>راهنمای دانش‌آموز</b>\n\n"
            . "/start — نمایش آزمون‌ها\n"
            . "برای شرکت در آزمون، آزمون را انتخاب کن، سپس کد آزمون و کد دانش‌آموزی خودت را وارد کن.\n"
            . "در طول آزمون می‌توانی با دکمه‌های ◀️ و ▶️ بین سوال‌ها جابه‌جا شوی."
        );
    }

    public static function menu($uid, $chatId, $mid = null)
    {
        $db = App::db();
        $quizzes = $db->all(
            'SELECT q.*, (SELECT COUNT(*) FROM questions x WHERE x.quiz_id = q.id) AS qc
             FROM quizzes q WHERE q.is_active = 1 ORDER BY q.id DESC'
        );
        $list = array();
        foreach ($quizzes as $q) {
            if ((int) $q['qc'] > 0) {
                $list[] = $q;
            }
        }
        if (count($list) === 0) {
            self::say($chatId, '😔 هیچ آزمون فعالی وجود ندارد.', $mid);
            return;
        }
        $text = "👋 <b>خوش آمدی</b>\nیک آزمون را انتخاب کن:\n\n";
        $text .= "برای شرکت، روی آزمون بزن.";
        self::say($chatId, $text, $mid, Keyboard::quizList($list));
    }

    public static function callback($data, $uid, $chatId, $mid)
    {
        $p = explode(':', $data);
        $kind = $p[0];

        if ($kind === 'qz' && isset($p[1])) {
            return self::pickQuiz((int) $p[1], $uid, $chatId);
        }
        if ($kind === 'rs' && isset($p[1])) {
            return self::resume((int) $p[1], $uid, $chatId);
        }
        if ($kind === 'an' && count($p) >= 4) {
            return self::answer((int) $p[1], (int) $p[2], $p[3], $uid, $chatId, $mid);
        }
        if ($kind === 'nv' && isset($p[2])) {
            return self::navigate((int) $p[1], (int) $p[2], $uid, $chatId, $mid);
        }
        if ($kind === 'fn' && isset($p[1])) {
            return self::askFinish((int) $p[1], $uid, $chatId, $mid);
        }
        if ($kind === 'fy' && isset($p[1])) {
            return self::finish((int) $p[1], $uid, $chatId);
        }
        if ($kind === 'mn') {
            return self::menu($uid, $chatId, $mid);
        }
    }

    private static function pickQuiz($quizId, $uid, $chatId)
    {
        $db = App::db();
        $quiz = $db->one('SELECT * FROM quizzes WHERE id = ?', array($quizId));
        if (!$quiz || !(int) $quiz['is_active']) {
            return App::tg()->send($chatId, '⛔️ این آزمون در دسترس نیست.');
        }
        if (!empty($quiz['access_code'])) {
            App::session()->set($uid, 'st_exam_code', array(
                'quiz_id' => (int) $quiz['id'],
                'tries' => 0,
                'first' => time(),
            ));
            return App::tg()->send(
                $chatId,
                '🔐 کد ورود آزمون «' . Farsi::esc($quiz['title']) . '» را وارد کن:'
            );
        }
        App::session()->set($uid, 'st_student_code', array('quiz_id' => (int) $quiz['id']));
        return App::tg()->send($chatId, '🎫 کد دانش‌آموزی خودت را وارد کن:');
    }

    private static function resume($attemptId, $uid, $chatId)
    {
        $att = App::db()->one('SELECT * FROM attempts WHERE id = ?', array($attemptId));
        if (!$att || (string) $att['telegram_id'] !== (string) $uid) {
            return App::tg()->send($chatId, '⚠️ این آزمون معتبر نیست.');
        }
        if ($att['status'] !== 'in_progress') {
            return self::showResult($att, $uid, $chatId, false, true);
        }
        if (Timer::expired($att['deadline'])) {
            $att = Timer::finalize($att['id'], 'timeout');
            return self::showResult($att, $uid, $chatId, true);
        }
        return self::renderCard($att, $uid, $chatId, null);
    }

    public static function codeInput($text, $uid, $chatId)
    {
        $s = App::session();
        $state = $s->state($uid);
        $p = $s->payload($uid);
        $db = App::db();

        if (!isset($p['quiz_id'])) {
            $s->clear($uid);
            return App::tg()->send($chatId, '⚠️ دوباره /start را بزن.');
        }
        $quiz = $db->one('SELECT * FROM quizzes WHERE id = ?', array((int) $p['quiz_id']));
        if (!$quiz || !(int) $quiz['is_active']) {
            $s->clear($uid);
            return App::tg()->send($chatId, '⛔️ این آزمون در دسترس نیست.');
        }

        if ($state === 'st_exam_code') {
            $tries = isset($p['tries']) ? (int) $p['tries'] : 0;
            $first = isset($p['first']) ? (int) $p['first'] : time();
            if (time() - $first > 600) {
                $tries = 0;
                $first = time();
            }
            if ($tries >= 5) {
                return App::tg()->send($chatId, '⛔️ تعداد تلاش بیش از حد. چند دقیقه بعد دوباره امتحان کن.');
            }
            if (Farsi::normalizeCode($text) === Farsi::normalizeCode($quiz['access_code'])) {
                $s->set($uid, 'st_student_code', array('quiz_id' => (int) $quiz['id']));
                return App::tg()->send($chatId, '🎫 حالا کد دانش‌آموزی خودت را وارد کن:');
            }
            $p['tries'] = $tries + 1;
            $p['first'] = $first;
            $s->set($uid, 'st_exam_code', $p);
            return App::tg()->send($chatId, '❌ کد آزمون اشتباه است. دوباره تلاش کن:');
        }

        if ($state === 'st_student_code') {
            $code = Codes::normalize($text);
            $student = Codes::findByCode($db, $code);
            if (!$student) {
                return App::tg()->send($chatId, '❌ چنین کدی وجود ندارد. کد را دقیق وارد کن:');
            }
            if (!(int) $student['is_active']) {
                return App::tg()->send($chatId, '⛔️ این کد غیرفعال شده است. با ادمین تماس بگیر.');
            }
            if (!empty($student['locked_telegram_id'])
                && (string) $student['locked_telegram_id'] !== (string) $uid) {
                return App::tg()->send($chatId, '⛔️ این کد روی اکانت تلگرام دیگری فعال شده است. با ادمین تماس بگیر.');
            }
            if (empty($student['locked_telegram_id'])) {
                Codes::lock($db, $student['id'], $uid);
            }
            $s->clear($uid);
            return self::startOrResume($student, $quiz, $uid, $chatId);
        }

        $s->clear($uid);
        return App::tg()->send($chatId, '⚠️ دوباره /start را بزن.');
    }

    private static function startOrResume($student, $quiz, $uid, $chatId)
    {
        $db = App::db();
        $att = $db->one(
            'SELECT * FROM attempts WHERE student_id = ? AND quiz_id = ?',
            array($student['id'], $quiz['id'])
        );

        if ($att) {
            if ($att['status'] === 'in_progress') {
                if (Timer::expired($att['deadline'])) {
                    $att = Timer::finalize($att['id'], 'timeout');
                    return self::showResult($att, $uid, $chatId, true);
                }
                App::tg()->send(
                    $chatId,
                    '⏳ ادامه آزمون «' . Farsi::esc($quiz['title']) . '» — سوال '
                    . Farsi::digits((int) $att['current_index'] + 1)
                );
                return self::renderCard($att, $uid, $chatId, null);
            }
            return self::showResult($att, $uid, $chatId, false, true);
        }

        $rows = $db->all(
            'SELECT id FROM questions WHERE quiz_id = ? ORDER BY position ASC, id ASC',
            array($quiz['id'])
        );
        if (count($rows) === 0) {
            return App::tg()->send($chatId, '⚠️ این آزمون هنوز سوالی ندارد.');
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
        } catch (PDOException $e) {
            // concurrent insert: resume the existing attempt
            $att = $db->one(
                'SELECT * FROM attempts WHERE student_id = ? AND quiz_id = ?',
                array($student['id'], $quiz['id'])
            );
            if ($att) {
                return self::renderCard($att, $uid, $chatId, null);
            }
            return App::tg()->send($chatId, '⚠️ خطا در شروع آزمون.');
        }

        $att = $db->one('SELECT * FROM attempts WHERE id = ?', array($db->id()));
        return self::renderCard($att, $uid, $chatId, null);
    }

    private static function renderCard($att, $uid, $chatId, $mid)
    {
        if (!$att) {
            return App::tg()->send($chatId, '⚠️ آزمون پیدا نشد.');
        }
        if ($att['status'] !== 'in_progress') {
            return self::showResult($att, $uid, $chatId, false, true);
        }
        if (Timer::expired($att['deadline'])) {
            $att = Timer::finalize($att['id'], 'timeout');
            return self::showResult($att, $uid, $chatId, true);
        }

        $db = App::db();
        $quiz = $db->one('SELECT * FROM quizzes WHERE id = ?', array($att['quiz_id']));
        $student = $db->one('SELECT * FROM student_codes WHERE id = ?', array($att['student_id']));
        if (!$quiz) {
            return App::tg()->send($chatId, '⚠️ آزمون پیدا نشد.');
        }
        $qOrder = Order::decode($att['question_order'], array());
        $oOrder = Order::decode($att['option_order'], array());
        $total = count($qOrder);
        if ($total === 0) {
            return App::tg()->send($chatId, '⚠️ سوالی برای این آزمون نیست.');
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
            $att = Timer::finalize($att['id'], 'student');
            return self::showResult($att, $uid, $chatId);
        }
        if ($idx !== (int) $att['current_index']) {
            $db->exec('UPDATE attempts SET current_index = ? WHERE id = ?', array($idx, $att['id']));
            $att['current_index'] = $idx;
        }

        $order = isset($oOrder[(string) $qid]) ? $oOrder[(string) $qid] : array('A', 'B', 'C', 'D');
        if (!is_array($order) || count($order) !== 4) {
            $order = array('A', 'B', 'C', 'D');
        }
        $ans = $db->one(
            'SELECT * FROM answers WHERE attempt_id = ? AND question_id = ?',
            array($att['id'], $qid)
        );
        $selected = $ans && $ans['selected'] !== null ? $ans['selected'] : null;
        $showAnswer = (int) $quiz['show_answer'] === 1;
        $remaining = Timer::remaining($att['deadline']);
        $labels = self::labels();

        $lines = array();
        $lines[] = '📘 <b>' . Farsi::esc($quiz['title']) . '</b>';
        $meta = array();
        if ($remaining !== null) {
            $meta[] = '⏱ ' . Farsi::digits(Timer::fmt($remaining));
        }
        if ($student) {
            $meta[] = Farsi::esc($student['first_name'] . ' ' . $student['last_name']);
        }
        if (count($meta) > 0) {
            $lines[] = implode(' · ', $meta);
        }
        $lines[] = '';
        $lines[] = '<b>سوال ' . Farsi::digits($idx + 1) . ' از ' . Farsi::digits($total) . '</b>';
        if ($remaining !== null && $remaining <= 60) {
            $lines[] = '⏳ زمان رو به پایان است';
        }
        $lines[] = '';
        $lines[] = Farsi::esc($q['text']);
        $lines[] = '';

        foreach ($order as $i => $orig) {
            if ($i > 3) {
                break;
            }
            $opt = $q['option_' . strtolower($orig)];
            $mark = '';
            if ($selected !== null && $selected === $orig) {
                $mark = ' ✔️';
            }
            if ($showAnswer && $selected !== null) {
                if ($orig === $q['correct']) {
                    $mark = ' ✅';
                } elseif ($selected === $orig) {
                    $mark = ' ❌';
                }
            }
            $lines[] = $labels[$i] . ') ' . Farsi::esc($opt) . $mark;
        }

        if ($showAnswer && $selected !== null) {
            $lines[] = '';
            if ($selected === $q['correct']) {
                $lines[] = '✅ پاسخ درست بود.';
            } else {
                $ci = array_search($q['correct'], $order, true);
                $clabel = $ci === false ? $q['correct'] : $labels[$ci];
                $lines[] = '❌ پاسخ درست: ' . $clabel;
            }
        }

        $text = implode("\n", $lines);
        $kb = Keyboard::question(
            (int) $att['id'],
            (int) $qid,
            $order,
            $selected,
            $showAnswer,
            $q['correct'],
            $idx,
            $total
        );
        self::say($chatId, $text, $mid, $kb);
    }

    private static function answer($attemptId, $qid, $letter, $uid, $chatId, $mid)
    {
        $db = App::db();
        $att = $db->one('SELECT * FROM attempts WHERE id = ?', array($attemptId));
        if (!$att || (string) $att['telegram_id'] !== (string) $uid) {
            return App::tg()->send($chatId, '⚠️ این آزمون معتبر نیست.');
        }
        if ($att['status'] !== 'in_progress') {
            return self::showResult($att, $uid, $chatId, false, true);
        }
        if (Timer::expired($att['deadline'])) {
            $att = Timer::finalize($att['id'], 'timeout');
            return self::showResult($att, $uid, $chatId, true);
        }

        $qOrder = Order::decode($att['question_order'], array());
        $qids = array_map('intval', $qOrder);
        if (!in_array((int) $qid, $qids, true)) {
            return App::tg()->send($chatId, '⚠️ سوال معتبر نیست.');
        }
        $letter = strtoupper((string) $letter);
        if (!in_array($letter, array('A', 'B', 'C', 'D'), true)) {
            return;
        }
        $q = $db->one('SELECT * FROM questions WHERE id = ?', array($qid));
        if (!$q) {
            return App::tg()->send($chatId, '⚠️ سوال پیدا نشد.');
        }

        $isCorrect = $letter === $q['correct'] ? 1 : 0;
        $db->exec(
            'INSERT INTO answers (attempt_id, question_id, selected, is_correct, updated_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE selected = VALUES(selected), is_correct = VALUES(is_correct), updated_at = VALUES(updated_at)',
            array($att['id'], $qid, $letter, $isCorrect, App::now())
        );

        $pos = array_search((int) $qid, $qids, true);
        if ($pos !== false) {
            $db->exec('UPDATE attempts SET current_index = ? WHERE id = ?', array($pos, $att['id']));
            $att['current_index'] = $pos;
        }

        return self::renderCard($att, $uid, $chatId, $mid);
    }

    private static function navigate($attemptId, $index, $uid, $chatId, $mid)
    {
        $db = App::db();
        $att = $db->one('SELECT * FROM attempts WHERE id = ?', array($attemptId));
        if (!$att || (string) $att['telegram_id'] !== (string) $uid) {
            return App::tg()->send($chatId, '⚠️ این آزمون معتبر نیست.');
        }
        if ($att['status'] !== 'in_progress') {
            return self::showResult($att, $uid, $chatId, false, true);
        }
        if (Timer::expired($att['deadline'])) {
            $att = Timer::finalize($att['id'], 'timeout');
            return self::showResult($att, $uid, $chatId, true);
        }
        $qOrder = Order::decode($att['question_order'], array());
        $total = count($qOrder);
        if ($total === 0) {
            return;
        }
        $index = (int) $index;
        if ($index < 0) {
            $index = 0;
        }
        if ($index > $total - 1) {
            $index = $total - 1;
        }
        $db->exec('UPDATE attempts SET current_index = ? WHERE id = ?', array($index, $att['id']));
        $att['current_index'] = $index;
        return self::renderCard($att, $uid, $chatId, $mid);
    }

    private static function askFinish($attemptId, $uid, $chatId, $mid)
    {
        $db = App::db();
        $att = $db->one('SELECT * FROM attempts WHERE id = ?', array($attemptId));
        if (!$att || (string) $att['telegram_id'] !== (string) $uid) {
            return App::tg()->send($chatId, '⚠️ این آزمون معتبر نیست.');
        }
        if ($att['status'] !== 'in_progress') {
            return self::showResult($att, $uid, $chatId, false, true);
        }
        if (Timer::expired($att['deadline'])) {
            $att = Timer::finalize($att['id'], 'timeout');
            return self::showResult($att, $uid, $chatId, true);
        }
        $text = "آیا آزمون را تمام می‌کنی؟\nسوالات بی‌پاسخ غلط حساب می‌شوند.";
        self::say($chatId, $text, $mid, Keyboard::confirmFinish((int) $att['id'], (int) $att['current_index']));
    }

    private static function finish($attemptId, $uid, $chatId)
    {
        $db = App::db();
        $att = $db->one('SELECT * FROM attempts WHERE id = ?', array($attemptId));
        if (!$att || (string) $att['telegram_id'] !== (string) $uid) {
            return App::tg()->send($chatId, '⚠️ این آزمون معتبر نیست.');
        }
        if ($att['status'] !== 'in_progress') {
            return self::showResult($att, $uid, $chatId, false, true);
        }
        $expired = Timer::expired($att['deadline']);
        $att = Timer::finalize($att['id'], $expired ? 'timeout' : 'student');
        return self::showResult($att, $uid, $chatId, $expired);
    }

    private static function labelForDisplay(array $order, $letter)
    {
        $labels = self::labels();
        $i = array_search($letter, $order, true);
        if ($i === false) {
            return (string) $letter;
        }
        return $labels[$i];
    }

    public static function showResult($att, $uid, $chatId, $expired = false, $already = false)
    {
        if (!$att) {
            return App::tg()->send($chatId, '⚠️ نتیجه‌ای پیدا نشد.');
        }
        $db = App::db();
        $quiz = $db->one('SELECT * FROM quizzes WHERE id = ?', array($att['quiz_id']));
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
        $pct = Farsi::pct($score, $total);

        $lines = array();
        if ($expired) {
            $lines[] = '⏰ <b>زمان آزمون تمام شد</b>';
        } else {
            $lines[] = '🏁 <b>آزمون تمام شد</b>';
        }
        if ($quiz) {
            $lines[] = '📘 ' . Farsi::esc($quiz['title']);
        }
        $lines[] = '';
        $lines[] = '✅ درست: ' . Farsi::digits($score) . ' از ' . Farsi::digits($total);
        $lines[] = '📊 درصد: ' . Farsi::digits($pct) . '٪';
        if (!empty($att['started_at']) && !empty($att['finished_at'])) {
            $spent = max(0, strtotime($att['finished_at']) - strtotime($att['started_at']));
            $lines[] = '⏱ زمان: ' . Farsi::digits(Timer::fmt($spent));
        }

        $showDetails = $quiz && (int) $quiz['show_answer'] === 1;
        if ($showDetails) {
            $wrong = array();
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
                if ($a && $a['selected'] !== null) {
                    $selLabel = self::labelForDisplay($order, $a['selected']);
                    $selText = $q['option_' . strtolower($a['selected'])];
                    $sel = $selLabel . ') ' . Farsi::esc(Farsi::clip($selText, 30));
                } else {
                    $sel = 'بدون پاسخ';
                }
                $corrLabel = self::labelForDisplay($order, $q['correct']);
                $corrText = $q['option_' . strtolower($q['correct'])];
                $corr = $corrLabel . ') ' . Farsi::esc(Farsi::clip($corrText, 30));
                $wrong[] = Farsi::digits($n) . ') ' . Farsi::esc(Farsi::clip($q['text'], 70))
                    . "\n   پاسخ تو: " . $sel . ' | درست: ' . $corr;
            }

            if (count($wrong) > 0) {
                $lines[] = '';
                $lines[] = '<b>❌ سوالات غلط:</b>';
                foreach ($wrong as $w) {
                    $lines[] = $w;
                }
            }
        }
        if ($already) {
            $lines[] = '';
            $lines[] = 'ℹ️ شما قبلاً در این آزمون شرکت کرده‌اید.';
        }

        $text = implode("\n", $lines);
        $chunks = Farsi::split($text, 4000);
        $last = count($chunks) - 1;
        foreach ($chunks as $i => $chunk) {
            App::tg()->send($chatId, $chunk, $i === $last ? Keyboard::resultMenu() : null);
        }
    }
}
