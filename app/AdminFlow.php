<?php
/**
 * Admin commands, wizards and reports.
 */
class AdminFlow
{
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

    private static function len($s)
    {
        return Farsi::len($s);
    }

    public static function handle($cmd, array $args, $uid, $chatId, $from)
    {
        switch ($cmd) {
            case '/start':
                return self::menu($uid, $chatId);
            case '/help':
                return self::help($chatId);
            case '/cancel':
                App::session()->clear($uid);
                return App::tg()->send($chatId, '✅ عملیات لغو شد.');
            case '/addstudent':
                return self::addStudentStart($uid, $chatId);
            case '/student':
                return self::showStudent($args, $chatId);
            case '/find':
                return self::find($args, $chatId);
            case '/students':
                $page = isset($args[0]) ? (int) $args[0] : 1;
                return self::studentsList($chatId, $page);
            case '/unlock':
                return self::unlock($args, $chatId);
            case '/resetattempt':
                return self::resetAttempt($args, $chatId);
            case '/delstudent':
                return self::delStudent($args, $chatId);
            case '/newquiz':
                return self::newQuizStart($uid, $chatId);
            case '/addq':
                return self::addQuestionStart($args, $uid, $chatId);
            case '/questions':
                return self::questionsList($args, $chatId);
            case '/editquiz':
                return self::editQuizMenu($args, $chatId);
            case '/quizzes':
                return self::quizzesList($chatId);
            case '/delq':
                return self::delQuestion($args, $chatId);
            case '/delquiz':
                return self::delQuiz($args, $chatId);
            case '/toggle':
                return self::toggle($args, $chatId);
            case '/stats':
                return self::stats($args, $chatId);
            case '/results':
                return self::results($args, $chatId);
            case '/broadcast':
                return self::broadcast($args, $chatId);
            default:
                return self::help($chatId);
        }
    }

    public static function menu($uid, $chatId)
    {
        $t = "🤖 <b>پنل مدیریت</b>\n\n";
        $t .= "<b>دانش‌آموزان</b>\n";
        $t .= "/addstudent — ثبت دانش‌آموز و ساخت کد\n";
        $t .= "/student &lt;کد&gt; — مشاهده دانش‌آموز\n";
        $t .= "/find &lt;متن&gt; — جستجو\n";
        $t .= "/students — لیست دانش‌آموزان\n";
        $t .= "/unlock &lt;کد&gt; — آزادسازی قفل\n";
        $t .= "/resetattempt &lt;کد&gt; &lt;آزمون&gt; — اجازه شرکت مجدد\n";
        $t .= "/delstudent &lt;کد&gt; — حذف دانش‌آموز\n\n";
        $t .= "<b>آزمون‌ها</b>\n";
        $t .= "/newquiz — ساخت آزمون\n";
        $t .= "/addq &lt;آزمون&gt; — افزودن سوال\n";
        $t .= "/questions &lt;آزمون&gt; — سوالات\n";
        $t .= "/editquiz &lt;آزمون&gt; — تنظیمات\n";
        $t .= "/quizzes — لیست آزمون‌ها\n";
        $t .= "/delq &lt;سوال&gt; — حذف سوال\n";
        $t .= "/delquiz &lt;آزمون&gt; — حذف آزمون\n";
        $t .= "/toggle &lt;آزمون&gt; — فعال/غیرفعال\n";
        $t .= "/stats &lt;آزمون&gt; — آمار\n";
        $t .= "/results &lt;آزمون&gt; — نتایج اخیر\n";
        $t .= "/broadcast &lt;متن&gt; — پیام همگانی\n\n";
        $t .= "/cancel — لغو عملیات جاری";
        App::tg()->send($chatId, $t, Keyboard::adminMenu());
    }

    public static function help($chatId)
    {
        App::tg()->send(
            $chatId,
            "راهنما:\n/start پنل\n/addstudent ثبت دانش‌آموز\n/newquiz ساخت آزمون\n/addq افزودن سوال\n/quizzes لیست آزمون‌ها\n/cancel لغو"
        );
    }

    /* ==================== Students ==================== */

    private static function addStudentStart($uid, $chatId)
    {
        App::session()->set($uid, 'as_first', array());
        App::tg()->send($chatId, "👤 نام دانش‌آموز را بفرست:\nبرای لغو: /cancel");
    }

    private static function showStudent(array $args, $chatId)
    {
        if (!isset($args[0])) {
            return App::tg()->send($chatId, 'استفاده: /student &lt;کد&gt;');
        }
        $db = App::db();
        $code = Codes::normalize($args[0]);
        $st = Codes::findByCode($db, $code);
        if (!$st) {
            return App::tg()->send($chatId, '❌ دانش‌آموزی با این کد پیدا نشد.');
        }
        $lock = 'آزاد';
        if (!empty($st['locked_telegram_id'])) {
            $u = $db->one('SELECT full_name, username FROM bot_users WHERE telegram_id = ?', array($st['locked_telegram_id']));
            $lock = $u && $u['full_name'] ? $u['full_name'] : ('اکانت ' . $st['locked_telegram_id']);
        }
        $t = "👤 <b>" . Farsi::esc($st['first_name'] . ' ' . $st['last_name']) . "</b>\n";
        $t .= 'کد: <code>' . Farsi::esc($st['code']) . "</code>\n";
        if (!empty($st['class_name'])) {
            $t .= 'کلاس: ' . Farsi::esc($st['class_name']) . "\n";
        }
        $t .= 'وضعیت: ' . ((int) $st['is_active'] ? 'فعال' : 'غیرفعال') . "\n";
        $t .= 'قفل: ' . Farsi::esc($lock) . "\n";

        $rows = $db->all(
            'SELECT a.*, q.title FROM attempts a JOIN quizzes q ON q.id = a.quiz_id
             WHERE a.student_id = ? ORDER BY a.id DESC',
            array($st['id'])
        );
        if (count($rows) > 0) {
            $t .= "\n<b>آزمون‌ها:</b>\n";
            foreach ($rows as $r) {
                $status = $r['status'] === 'finished'
                    ? (Farsi::digits((int) $r['score']) . '/' . Farsi::digits((int) $r['total']))
                    : 'در حال اجرا';
                $t .= '• ' . Farsi::esc($r['title']) . ' — ' . $status . "\n";
            }
        }
        App::tg()->send($chatId, $t);
    }

    private static function find(array $args, $chatId)
    {
        $q = trim(implode(' ', $args));
        if ($q === '') {
            return App::tg()->send($chatId, 'استفاده: /find &lt;نام یا کد&gt;');
        }
        $db = App::db();
        $like = '%' . $q . '%';
        $rows = $db->all(
            'SELECT * FROM student_codes
             WHERE code LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR class_name LIKE ?
             ORDER BY id DESC LIMIT 20',
            array($like, $like, $like, $like)
        );
        if (count($rows) === 0) {
            return App::tg()->send($chatId, 'نتیجه‌ای پیدا نشد.');
        }
        $t = "🔎 <b>نتایج جستجو</b>\n";
        foreach ($rows as $r) {
            $t .= '<code>' . Farsi::esc($r['code']) . '</code> — '
                . Farsi::esc($r['first_name'] . ' ' . $r['last_name']);
            if (!empty($r['class_name'])) {
                $t .= ' (' . Farsi::esc($r['class_name']) . ')';
            }
            $t .= "\n";
        }
        App::tg()->send($chatId, $t);
    }

    private static function studentsList($chatId, $page)
    {
        $db = App::db();
        $page = max(1, (int) $page);
        $per = 20;
        $total = (int) $db->val('SELECT COUNT(*) FROM student_codes', array(), 0);
        $pages = max(1, (int) ceil($total / $per));
        if ($page > $pages) {
            $page = $pages;
        }
        $off = ($page - 1) * $per;
        $rows = $db->all(
            'SELECT * FROM student_codes ORDER BY id DESC LIMIT ' . $per . ' OFFSET ' . $off
        );
        if (count($rows) === 0) {
            return App::tg()->send($chatId, 'لیست دانش‌آموزان خالی است.');
        }
        $t = "👥 <b>دانش‌آموزان</b> (" . Farsi::digits($total) . ")\n\n";
        foreach ($rows as $r) {
            $t .= '<code>' . Farsi::esc($r['code']) . '</code> — '
                . Farsi::esc($r['first_name'] . ' ' . $r['last_name']);
            if (!empty($r['class_name'])) {
                $t .= ' (' . Farsi::esc($r['class_name']) . ')';
            }
            if (!(int) $r['is_active']) {
                $t .= ' ⛔️';
            }
            $t .= "\n";
        }
        App::tg()->send($chatId, $t, Keyboard::pager($page, $pages));
    }

    private static function unlock(array $args, $chatId)
    {
        if (!isset($args[0])) {
            return App::tg()->send($chatId, 'استفاده: /unlock &lt;کد&gt;');
        }
        $db = App::db();
        $st = Codes::findByCode($db, Codes::normalize($args[0]));
        if (!$st) {
            return App::tg()->send($chatId, '❌ کد پیدا نشد.');
        }
        $db->exec('UPDATE student_codes SET locked_telegram_id = NULL WHERE id = ?', array($st['id']));
        App::tg()->send($chatId, '✅ قفل کد ' . Farsi::esc($st['code']) . ' آزاد شد.');
    }

    private static function resetAttempt(array $args, $chatId)
    {
        if (count($args) < 2) {
            return App::tg()->send($chatId, 'استفاده: /resetattempt &lt;کد&gt; &lt;شناسه آزمون&gt;');
        }
        $db = App::db();
        $st = Codes::findByCode($db, Codes::normalize($args[0]));
        if (!$st) {
            return App::tg()->send($chatId, '❌ کد پیدا نشد.');
        }
        $quizId = (int) $args[1];
        $n = $db->exec('DELETE FROM attempts WHERE student_id = ? AND quiz_id = ?', array($st['id'], $quizId));
        if ($n > 0) {
            App::tg()->send($chatId, '✅ تلاش حذف شد. دانش‌آموز می‌تواند دوباره شرکت کند.');
        } else {
            App::tg()->send($chatId, 'تلاشی برای حذف پیدا نشد.');
        }
    }

    private static function delStudent(array $args, $chatId)
    {
        if (!isset($args[0])) {
            return App::tg()->send($chatId, 'استفاده: /delstudent &lt;کد&gt;');
        }
        $db = App::db();
        $st = Codes::findByCode($db, Codes::normalize($args[0]));
        if (!$st) {
            return App::tg()->send($chatId, '❌ کد پیدا نشد.');
        }
        App::tg()->send(
            $chatId,
            'حذف دانش‌آموز «' . Farsi::esc($st['first_name'] . ' ' . $st['last_name']) . '» و همه نتایج؟',
            Keyboard::confirmDelete('st', $st['id'])
        );
    }

    /* ==================== Quizzes ==================== */

    private static function newQuizStart($uid, $chatId)
    {
        App::session()->set($uid, 'nq_title', array());
        App::tg()->send($chatId, "📝 عنوان آزمون را بفرست:\nبرای لغو: /cancel");
    }

    private static function quizzesList($chatId)
    {
        $db = App::db();
        $rows = $db->all(
            'SELECT q.*,
                    (SELECT COUNT(*) FROM questions x WHERE x.quiz_id = q.id) AS qc,
                    (SELECT COUNT(*) FROM attempts a WHERE a.quiz_id = q.id AND a.status = ?) AS ac
             FROM quizzes q ORDER BY q.id DESC',
            array('finished')
        );
        if (count($rows) === 0) {
            return App::tg()->send($chatId, 'هیچ آزمونی ساخته نشده. /newquiz');
        }
        $t = "📝 <b>آزمون‌ها</b>\n\n";
        foreach ($rows as $r) {
            $t .= '<b>#' . Farsi::digits($r['id']) . '</b> ' . Farsi::esc($r['title']) . "\n";
            $t .= '  سوال: ' . Farsi::digits((int) $r['qc']);
            $t .= ' · شرکت‌کننده: ' . Farsi::digits((int) $r['ac']);
            if ((int) $r['time_limit'] > 0) {
                $t .= ' · ⏱' . Farsi::digits((int) round($r['time_limit'] / 60)) . 'د';
            }
            if (!empty($r['access_code'])) {
                $t .= ' · 🔒';
            }
            $t .= ' · ' . ((int) $r['is_active'] ? '✅ فعال' : '⛔️ غیرفعال') . "\n";
        }
        App::tg()->send($chatId, $t);
    }

    private static function addQuestionStart(array $args, $uid, $chatId)
    {
        if (!isset($args[0])) {
            return App::tg()->send($chatId, 'استفاده: /addq &lt;شناسه آزمون&gt;');
        }
        $db = App::db();
        $quiz = $db->one('SELECT * FROM quizzes WHERE id = ?', array((int) $args[0]));
        if (!$quiz) {
            return App::tg()->send($chatId, '❌ آزمون پیدا نشد.');
        }
        App::session()->set($uid, 'aq_text', array('quiz_id' => (int) $quiz['id']));
        App::tg()->send(
            $chatId,
            '✍️ متن سوال را برای آزمون «' . Farsi::esc($quiz['title']) . "» بفرست:\nبرای لغو: /cancel"
        );
    }

    private static function questionsList(array $args, $chatId)
    {
        if (!isset($args[0])) {
            return App::tg()->send($chatId, 'استفاده: /questions &lt;شناسه آزمون&gt;');
        }
        $db = App::db();
        $quiz = $db->one('SELECT * FROM quizzes WHERE id = ?', array((int) $args[0]));
        if (!$quiz) {
            return App::tg()->send($chatId, '❌ آزمون پیدا نشد.');
        }
        $rows = $db->all(
            'SELECT * FROM questions WHERE quiz_id = ? ORDER BY position ASC, id ASC',
            array($quiz['id'])
        );
        if (count($rows) === 0) {
            return App::tg()->send($chatId, 'این آزمون سوالی ندارد. /addq ' . $quiz['id']);
        }
        $labels = array('A' => 'الف', 'B' => 'ب', 'C' => 'پ', 'D' => 'ت');
        $t = '📝 سوالات «' . Farsi::esc($quiz['title']) . "\"\n\n";
        foreach ($rows as $i => $r) {
            $t .= Farsi::digits($i + 1) . ') ' . Farsi::esc(Farsi::clip($r['text'], 120)) . "\n";
            $t .= '   الف) ' . Farsi::esc(Farsi::clip($r['option_a'], 40));
            $t .= ' | ب) ' . Farsi::esc(Farsi::clip($r['option_b'], 40)) . "\n";
            $t .= '   پ) ' . Farsi::esc(Farsi::clip($r['option_c'], 40));
            $t .= ' | ت) ' . Farsi::esc(Farsi::clip($r['option_d'], 40)) . "\n";
            $correct = isset($labels[$r['correct']]) ? $labels[$r['correct']] : $r['correct'];
            $t .= '   ✅ پاسخ: ' . $correct . ' · شناسه: ' . Farsi::digits($r['id']) . "\n\n";
        }
        App::tg()->send($chatId, $t);
    }

    private static function editQuizMenu(array $args, $chatId)
    {
        if (!isset($args[0])) {
            return App::tg()->send($chatId, 'استفاده: /editquiz &lt;شناسه آزمون&gt;');
        }
        $db = App::db();
        $quiz = $db->one('SELECT * FROM quizzes WHERE id = ?', array((int) $args[0]));
        if (!$quiz) {
            return App::tg()->send($chatId, '❌ آزمون پیدا نشد.');
        }
        $t = '⚙️ تنظیمات «' . Farsi::esc($quiz['title']) . "\"\nشناسه: " . Farsi::digits($quiz['id']);
        App::tg()->send($chatId, $t, Keyboard::editMenu((int) $quiz['id'], $quiz));
    }

    private static function delQuestion(array $args, $chatId)
    {
        if (!isset($args[0])) {
            return App::tg()->send($chatId, 'استفاده: /delq &lt;شناسه سوال&gt;');
        }
        $db = App::db();
        $q = $db->one('SELECT * FROM questions WHERE id = ?', array((int) $args[0]));
        if (!$q) {
            return App::tg()->send($chatId, '❌ سوال پیدا نشد.');
        }
        App::tg()->send(
            $chatId,
            'حذف سوال «' . Farsi::esc(Farsi::clip($q['text'], 100)) . '»؟',
            Keyboard::confirmDelete('q', $q['id'])
        );
    }

    private static function delQuiz(array $args, $chatId)
    {
        if (!isset($args[0])) {
            return App::tg()->send($chatId, 'استفاده: /delquiz &lt;شناسه آزمون&gt;');
        }
        $db = App::db();
        $quiz = $db->one('SELECT * FROM quizzes WHERE id = ?', array((int) $args[0]));
        if (!$quiz) {
            return App::tg()->send($chatId, '❌ آزمون پیدا نشد.');
        }
        App::tg()->send(
            $chatId,
            '⚠️ حذف آزمون «' . Farsi::esc($quiz['title']) . '» همراه با همه سوال‌ها و نتایج؟',
            Keyboard::confirmDelete('quiz', $quiz['id'])
        );
    }

    private static function toggle(array $args, $chatId)
    {
        if (!isset($args[0])) {
            return App::tg()->send($chatId, 'استفاده: /toggle &lt;شناسه آزمون&gt;');
        }
        $db = App::db();
        $quiz = $db->one('SELECT * FROM quizzes WHERE id = ?', array((int) $args[0]));
        if (!$quiz) {
            return App::tg()->send($chatId, '❌ آزمون پیدا نشد.');
        }
        $new = (int) $quiz['is_active'] ? 0 : 1;
        $db->exec('UPDATE quizzes SET is_active = ? WHERE id = ?', array($new, $quiz['id']));
        App::tg()->send($chatId, $new ? '✅ آزمون فعال شد.' : '⛔️ آزمون غیرفعال شد.');
    }

    private static function stats(array $args, $chatId)
    {
        if (!isset($args[0])) {
            return App::tg()->send($chatId, 'استفاده: /stats &lt;شناسه آزمون&gt;');
        }
        $db = App::db();
        $quiz = $db->one('SELECT * FROM quizzes WHERE id = ?', array((int) $args[0]));
        if (!$quiz) {
            return App::tg()->send($chatId, '❌ آزمون پیدا نشد.');
        }
        $participants = (int) $db->val(
            'SELECT COUNT(*) FROM attempts WHERE quiz_id = ? AND status = ?',
            array($quiz['id'], 'finished'),
            0
        );
        $attempts = (int) $db->val('SELECT COUNT(*) FROM attempts WHERE quiz_id = ?', array($quiz['id']), 0);
        $avg = $db->val(
            'SELECT AVG(score / NULLIF(total, 0) * 100) FROM attempts WHERE quiz_id = ? AND status = ?',
            array($quiz['id'], 'finished')
        );
        $best = (int) $db->val(
            'SELECT MAX(score) FROM attempts WHERE quiz_id = ? AND status = ?',
            array($quiz['id'], 'finished'),
            0
        );
        $avgTime = $db->val(
            'SELECT AVG(TIMESTAMPDIFF(SECOND, started_at, finished_at)) FROM attempts
             WHERE quiz_id = ? AND status = ?',
            array($quiz['id'], 'finished')
        );

        $t = '📊 <b>آمار «' . Farsi::esc($quiz['title']) . "\"</b>\n";
        $t .= 'شرکت‌کننده: ' . Farsi::digits($participants) . "\n";
        $t .= 'تعداد تلاش: ' . Farsi::digits($attempts) . "\n";
        $t .= 'میانگین: ' . Farsi::digits($avg !== null ? (int) round($avg) : 0) . "٪\n";
        $t .= 'بهترین نمره: ' . Farsi::digits($best) . "\n";
        $t .= 'میانگین زمان: ' . Farsi::digits(Timer::fmt($avgTime !== null ? (int) $avgTime : 0));
        App::tg()->send($chatId, $t);
    }

    private static function results(array $args, $chatId)
    {
        if (!isset($args[0])) {
            return App::tg()->send($chatId, 'استفاده: /results &lt;شناسه آزمون&gt;');
        }
        $db = App::db();
        $quiz = $db->one('SELECT * FROM quizzes WHERE id = ?', array((int) $args[0]));
        if (!$quiz) {
            return App::tg()->send($chatId, '❌ آزمون پیدا نشد.');
        }
        $rows = $db->all(
            'SELECT a.*, s.code, s.first_name, s.last_name, s.class_name
             FROM attempts a JOIN student_codes s ON s.id = a.student_id
             WHERE a.quiz_id = ? AND a.status = ? ORDER BY a.finished_at DESC LIMIT 15',
            array($quiz['id'], 'finished')
        );
        if (count($rows) === 0) {
            return App::tg()->send($chatId, 'هنوز نتیجه‌ای ثبت نشده.');
        }
        $t = '📋 <b>نتایج «' . Farsi::esc($quiz['title']) . "\"</b>\n\n";
        foreach ($rows as $r) {
            $t .= '<code>' . Farsi::esc($r['code']) . '</code> ';
            $t .= Farsi::esc($r['first_name'] . ' ' . $r['last_name']);
            $t .= ' — ' . Farsi::digits((int) $r['score']) . '/' . Farsi::digits((int) $r['total']);
            $t .= ' (' . Farsi::digits(Farsi::pct($r['score'], $r['total'])) . "٪)";
            if (!empty($r['finished_at'])) {
                $t .= ' · ' . Farsi::digits($r['finished_at']);
            }
            $t .= "\n";
        }
        App::tg()->send($chatId, $t);
    }

    private static function broadcast(array $args, $chatId)
    {
        $text = trim(implode(' ', $args));
        if ($text === '') {
            return App::tg()->send($chatId, 'استفاده: /broadcast &lt;متن پیام&gt;');
        }
        $db = App::db();
        $users = $db->all('SELECT telegram_id FROM bot_users');
        $ok = 0;
        $fail = 0;
        foreach ($users as $u) {
            $r = App::tg()->send($u['telegram_id'], Farsi::esc($text));
            if (!empty($r['ok'])) {
                $ok++;
            } else {
                $fail++;
            }
            usleep(50000);
        }
        App::tg()->send($chatId, '📤 ارسال شد. موفق: ' . Farsi::digits($ok) . ' · ناموفق: ' . Farsi::digits($fail));
    }

    /* ==================== Wizards ==================== */

    public static function wizardInput($text, $uid, $chatId)
    {
        $s = App::session();
        $st = $s->state($uid);
        $p = $s->payload($uid);
        $tg = App::tg();
        $db = App::db();

        switch ($st) {
            case 'as_first':
                if (self::len($text) < 1 || self::len($text) > 100) {
                    return $tg->send($chatId, 'نام باید بین ۱ تا ۱۰۰ کاراکتر باشد. دوباره بفرست:');
                }
                $s->set($uid, 'as_last', array('first' => $text));
                return $tg->send($chatId, 'نام خانوادگی را بفرست:');

            case 'as_last':
                if (self::len($text) < 1 || self::len($text) > 100) {
                    return $tg->send($chatId, 'نام خانوادگی باید بین ۱ تا ۱۰۰ کاراکتر باشد. دوباره بفرست:');
                }
                $s->set($uid, 'as_class', array('first' => $p['first'], 'last' => $text));
                return $tg->send($chatId, 'کلاس/پایه را بفرست یا /skip بزن:');

            case 'as_class':
                $class = '';
                if ($text !== '/skip' && $text !== '-') {
                    if (self::len($text) > 100) {
                        return $tg->send($chatId, 'کلاس حداکثر ۱۰۰ کاراکتر. دوباره بفرست یا /skip:');
                    }
                    $class = $text;
                }
                $s->set($uid, 'as_code', array('first' => $p['first'], 'last' => $p['last'], 'class' => $class));
                return $tg->send($chatId, 'کد دلخواه را بفرست یا /skip بزن تا خودکار ساخته شود:');

            case 'as_code':
                $custom = ($text !== '/skip' && $text !== '-');
                if ($custom) {
                    $code = Codes::normalize($text);
                    if (!Codes::isValidCustom($code)) {
                        return $tg->send($chatId, 'کد باید ۲ تا ۱۶ کاراکتر و فقط حروف انگلیسی و عدد باشد. دوباره بفرست یا /skip:');
                    }
                    if (Codes::findByCode($db, $code)) {
                        return $tg->send($chatId, '❌ این کد قبلاً استفاده شده. کد دیگری بفرست یا /skip:');
                    }
                } else {
                    $code = Codes::generateUnique($db, (int) App::cfg('code_length', 6));
                }
                $db->exec(
                    'INSERT INTO student_codes (code, first_name, last_name, class_name, is_active, created_by, created_at)
                     VALUES (?, ?, ?, ?, 1, ?, ?)',
                    array(
                        $code,
                        $p['first'],
                        $p['last'],
                        !empty($p['class']) ? $p['class'] : null,
                        $uid,
                        App::now(),
                    )
                );
                $s->clear($uid);
                $msg = "✅ دانش‌آموز ثبت شد\n\n";
                $msg .= 'کد: <code>' . Farsi::esc($code) . "</code>\n";
                $msg .= 'نام: ' . Farsi::esc($p['first'] . ' ' . $p['last']);
                if (!empty($p['class'])) {
                    $msg .= "\nکلاس: " . Farsi::esc($p['class']);
                }
                $msg .= "\n\nاین کد را به دانش‌آموز بدهید.";
                return $tg->send($chatId, $msg);

            case 'nq_title':
                if (self::len($text) < 1 || self::len($text) > 200) {
                    return $tg->send($chatId, 'عنوان باید بین ۱ تا ۲۰۰ کاراکتر باشد. دوباره بفرست:');
                }
                $s->set($uid, 'nq_desc', array('title' => $text));
                return $tg->send($chatId, 'توضیح آزمون را بفرست یا /skip بزن:');

            case 'nq_desc':
                $desc = ($text === '/skip' || $text === '-') ? '' : $text;
                if (self::len($desc) > 1000) {
                    return $tg->send($chatId, 'توضیح حداکثر ۱۰۰۰ کاراکتر. دوباره بفرست یا /skip:');
                }
                $s->set($uid, 'nq_time', array('title' => $p['title'], 'desc' => $desc));
                return $tg->send($chatId, 'زمان آزمون را به دقیقه بفرست (۰ = بدون محدودیت):');

            case 'nq_time':
                $num = Farsi::normalizeCode($text);
                if (!preg_match('/^\d{1,3}$/', $num)) {
                    return $tg->send($chatId, 'عدد دقیقه را بفرست (۰ تا ۶۰۰):');
                }
                $min = (int) $num;
                if ($min > 600) {
                    return $tg->send($chatId, 'حداکثر ۶۰۰ دقیقه. دوباره بفرست:');
                }
                $s->set($uid, 'nq_code', array(
                    'title' => $p['title'],
                    'desc' => $p['desc'],
                    'time' => $min * 60,
                ));
                return $tg->send($chatId, 'کد ورود آزمون را بفرست یا /skip بزن:');

            case 'nq_code':
                $code = '';
                if ($text !== '/skip' && $text !== '-') {
                    $code = Codes::normalize($text);
                    if (!Codes::isValidAccessCode($code)) {
                        return $tg->send($chatId, 'کد آزمون باید ۲ تا ۳۲ کاراکتر و فقط حروف انگلیسی و عدد باشد. دوباره بفرست یا /skip:');
                    }
                }
                $p['code'] = $code;
                $s->set($uid, 'nq_shuffle', $p);
                return $tg->send($chatId, 'سوالات و گزینه‌ها تصادفی شوند؟', Keyboard::yesNo());

            case 'nq_shuffle':
            case 'nq_show':
            case 'aq_correct':
                return self::resendPending($st, $chatId);

            case 'eq_time':
                $num = Farsi::normalizeCode($text);
                if (!preg_match('/^\d{1,3}$/', $num)) {
                    return $tg->send($chatId, 'عدد دقیقه را بفرست (۰ تا ۶۰۰):');
                }
                $min = (int) $num;
                if ($min > 600) {
                    return $tg->send($chatId, 'حداکثر ۶۰۰ دقیقه. دوباره بفرست:');
                }
                $db->exec('UPDATE quizzes SET time_limit = ? WHERE id = ?', array($min * 60, $p['quiz_id']));
                $s->clear($uid);
                return $tg->send($chatId, '✅ زمان آزمون به ' . Farsi::digits($min) . ' دقیقه تغییر کرد.');

            case 'eq_code':
                if ($text === '/skip' || $text === '-') {
                    $code = null;
                } else {
                    $code = Codes::normalize($text);
                    if (!Codes::isValidAccessCode($code)) {
                        return $tg->send($chatId, 'کد آزمون باید ۲ تا ۳۲ کاراکتر و فقط حروف انگلیسی و عدد باشد. دوباره بفرست یا /skip:');
                    }
                }
                $db->exec('UPDATE quizzes SET access_code = ? WHERE id = ?', array($code, $p['quiz_id']));
                $s->clear($uid);
                return $tg->send($chatId, $code === null ? '✅ کد ورود حذف شد.' : '✅ کد ورود تنظیم شد.');

            case 'aq_text':
                if (self::len($text) < 1 || self::len($text) > 3000) {
                    return $tg->send($chatId, 'متن سوال باید بین ۱ تا ۳۰۰۰ کاراکتر باشد. دوباره بفرست:');
                }
                $s->set($uid, 'aq_a', array('quiz_id' => $p['quiz_id'], 'text' => $text));
                return $tg->send($chatId, 'گزینه «الف» را بفرست:');

            case 'aq_a':
            case 'aq_b':
            case 'aq_c':
                if (self::len($text) < 1 || self::len($text) > 500) {
                    return $tg->send($chatId, 'گزینه باید بین ۱ تا ۵۰۰ کاراکتر باشد. دوباره بفرست:');
                }
                $map = array('aq_a' => 'b', 'aq_b' => 'c', 'aq_c' => 'd');
                $nextKey = $map[$st];
                $p[$st === 'aq_a' ? 'a' : ($st === 'aq_b' ? 'b' : 'c')] = $text;
                $labels = array('aq_a' => 'ب', 'aq_b' => 'پ', 'aq_c' => 'ت');
                $s->set($uid, 'aq_' . $nextKey, $p);
                return $tg->send($chatId, 'گزینه «' . $labels[$st] . '» را بفرست:');

            case 'aq_d':
                if (self::len($text) < 1 || self::len($text) > 500) {
                    return $tg->send($chatId, 'گزینه باید بین ۱ تا ۵۰۰ کاراکتر باشد. دوباره بفرست:');
                }
                $p['d'] = $text;
                $s->set($uid, 'aq_correct', $p);
                $preview = "پیش‌نمایش سوال:\n\n";
                $preview .= '<b>' . Farsi::esc($p['text']) . "</b>\n\n";
                $preview .= 'الف) ' . Farsi::esc($p['a']) . "\n";
                $preview .= 'ب) ' . Farsi::esc($p['b']) . "\n";
                $preview .= 'پ) ' . Farsi::esc($p['c']) . "\n";
                $preview .= 'ت) ' . Farsi::esc($p['d']) . "\n\n";
                $preview .= 'کدام گزینه درست است؟';
                return $tg->send($chatId, $preview, Keyboard::correctPicker());

            default:
                $s->clear($uid);
                return $tg->send($chatId, 'عملیات لغو شد. /help');
        }
    }

    private static function resendPending($st, $chatId)
    {
        if ($st === 'aq_correct') {
            return App::tg()->send($chatId, 'لطفاً گزینه درست را از دکمه‌ها انتخاب کن:', Keyboard::correctPicker());
        }
        return App::tg()->send($chatId, 'لطفاً یکی از دکمه‌های بله/خیر را بزن:', Keyboard::yesNo());
    }

    /* ==================== Callbacks ==================== */

    public static function callback($data, $uid, $chatId, $mid)
    {
        $parts = explode(':', $data);
        $kind = $parts[0];

        if ($kind === 'adm') {
            $what = isset($parts[1]) ? $parts[1] : '';
            if ($what === 'quizzes') {
                return self::quizzesList($chatId);
            }
            if ($what === 'newquiz') {
                return self::newQuizStart($uid, $chatId);
            }
            return self::menu($uid, $chatId);
        }
        if ($kind === 'pg') {
            $page = isset($parts[2]) ? (int) $parts[2] : 1;
            return self::studentsList($chatId, $page);
        }
        if ($kind === 'yn' && isset($parts[1])) {
            return self::wizardYesNo((int) $parts[1], $uid, $chatId);
        }
        if ($kind === 'ap' && isset($parts[1])) {
            return self::correctPicked(strtoupper($parts[1]), $uid, $chatId);
        }
        if ($kind === 'eq' && isset($parts[1]) && isset($parts[2])) {
            return self::editField((int) $parts[1], $parts[2], $uid, $chatId);
        }
        if ($kind === 'dc') {
            return self::doDelete($parts, $chatId);
        }
    }

    private static function wizardYesNo($val, $uid, $chatId)
    {
        $s = App::session();
        $st = $s->state($uid);
        $p = $s->payload($uid);
        $val = $val ? 1 : 0;
        $tg = App::tg();

        if ($st === 'nq_shuffle') {
            $p['shuffle'] = $val;
            $s->set($uid, 'nq_show', $p);
            return $tg->send($chatId, 'آیا پاسخ درست بعد از هر سوال به دانش‌آموز نشان داده شود؟', Keyboard::yesNo());
        }
        if ($st === 'nq_show') {
            $db = App::db();
            $db->exec(
                'INSERT INTO quizzes (title, description, access_code, time_limit, shuffle, show_answer, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 1, ?)',
                array(
                    $p['title'],
                    !empty($p['desc']) ? $p['desc'] : null,
                    !empty($p['code']) ? $p['code'] : null,
                    (int) $p['time'],
                    (int) $p['shuffle'],
                    $val,
                    App::now(),
                )
            );
            $id = $db->id();
            $s->clear($uid);
            $msg = "✅ آزمون ساخته شد\n";
            $msg .= 'شناسه: <b>' . Farsi::digits($id) . "</b>\n";
            $msg .= 'برای افزودن سوال: /addq ' . $id;
            return $tg->send($chatId, $msg);
        }
        return $tg->send($chatId, '⚠️ این انتخاب معتبر نیست.');
    }

    private static function correctPicked($letter, $uid, $chatId)
    {
        $s = App::session();
        $st = $s->state($uid);
        $p = $s->payload($uid);
        if ($st !== 'aq_correct') {
            return App::tg()->send($chatId, '⚠️ عملیات معتبر نیست.');
        }
        if (!in_array($letter, array('A', 'B', 'C', 'D'), true)) {
            return App::tg()->send($chatId, '⚠️ گزینه نامعتبر.');
        }
        $db = App::db();
        $pos = (int) $db->val(
            'SELECT COALESCE(MAX(position), 0) + 1 FROM questions WHERE quiz_id = ?',
            array($p['quiz_id']),
            1
        );
        $db->exec(
            'INSERT INTO questions (quiz_id, position, text, option_a, option_b, option_c, option_d, correct, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array($p['quiz_id'], $pos, $p['text'], $p['a'], $p['b'], $p['c'], $p['d'], $letter, App::now())
        );
        $s->clear($uid);
        $labels = array('A' => 'الف', 'B' => 'ب', 'C' => 'پ', 'D' => 'ت');
        $msg = "✅ سوال ثبت شد.\n\n";
        $msg .= '<b>' . Farsi::esc($p['text']) . "</b>\n\n";
        $msg .= 'الف) ' . Farsi::esc($p['a']) . "\n";
        $msg .= 'ب) ' . Farsi::esc($p['b']) . "\n";
        $msg .= 'پ) ' . Farsi::esc($p['c']) . "\n";
        $msg .= 'ت) ' . Farsi::esc($p['d']) . "\n\n";
        $msg .= 'پاسخ درست: ' . $labels[$letter] . "\n\n";
        $msg .= 'برای سوال بعدی: /addq ' . $p['quiz_id'];
        return App::tg()->send($chatId, $msg);
    }

    private static function editField($quizId, $field, $uid, $chatId)
    {
        $db = App::db();
        $quiz = $db->one('SELECT * FROM quizzes WHERE id = ?', array($quizId));
        if (!$quiz) {
            return App::tg()->send($chatId, '❌ آزمون پیدا نشد.');
        }
        $s = App::session();

        if ($field === 'done') {
            $s->clear($uid);
            return App::tg()->send($chatId, '✖️ بسته شد.');
        }
        if ($field === 'del') {
            return App::tg()->send(
                $chatId,
                '⚠️ حذف آزمون «' . Farsi::esc($quiz['title']) . '» و همه داده‌هایش؟',
                Keyboard::confirmDelete('quiz', $quiz['id'])
            );
        }
        if ($field === 'shuffle') {
            $db->exec('UPDATE quizzes SET shuffle = ? WHERE id = ?', array((int) $quiz['shuffle'] ? 0 : 1, $quizId));
            $quiz['shuffle'] = (int) $quiz['shuffle'] ? 0 : 1;
            return App::tg()->send($chatId, '✅ تغییر کرد.', Keyboard::editMenu($quizId, $quiz));
        }
        if ($field === 'show') {
            $db->exec('UPDATE quizzes SET show_answer = ? WHERE id = ?', array((int) $quiz['show_answer'] ? 0 : 1, $quizId));
            $quiz['show_answer'] = (int) $quiz['show_answer'] ? 0 : 1;
            return App::tg()->send($chatId, '✅ تغییر کرد.', Keyboard::editMenu($quizId, $quiz));
        }
        if ($field === 'active') {
            $db->exec('UPDATE quizzes SET is_active = ? WHERE id = ?', array((int) $quiz['is_active'] ? 0 : 1, $quizId));
            $quiz['is_active'] = (int) $quiz['is_active'] ? 0 : 1;
            return App::tg()->send($chatId, '✅ تغییر کرد.', Keyboard::editMenu($quizId, $quiz));
        }
        if ($field === 'time') {
            $s->set($uid, 'eq_time', array('quiz_id' => $quizId));
            return App::tg()->send($chatId, 'زمان جدید را به دقیقه بفرست (۰ = بدون محدودیت):');
        }
        if ($field === 'code') {
            $s->set($uid, 'eq_code', array('quiz_id' => $quizId));
            return App::tg()->send($chatId, 'کد ورود جدید را بفرست یا /skip بزن تا حذف شود:');
        }
        return App::tg()->send($chatId, '⚠️ گزینه نامعتبر.');
    }

    private static function doDelete($parts, $chatId)
    {
        if (!isset($parts[1]) || !isset($parts[2])) {
            return;
        }
        $db = App::db();
        $id = (int) $parts[2];
        if ($parts[1] === 'q') {
            $db->exec('DELETE FROM questions WHERE id = ?', array($id));
            return App::tg()->send($chatId, '🗑 سوال حذف شد.');
        }
        if ($parts[1] === 'quiz') {
            $db->exec('DELETE FROM quizzes WHERE id = ?', array($id));
            return App::tg()->send($chatId, '🗑 آزمون حذف شد.');
        }
        if ($parts[1] === 'st') {
            $db->exec('DELETE FROM student_codes WHERE id = ?', array($id));
            return App::tg()->send($chatId, '🗑 دانش‌آموز حذف شد.');
        }
    }
}
