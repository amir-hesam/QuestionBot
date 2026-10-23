<?php
/**
 * Dependency-free smoke tests (no database, no network).
 * Run: php tests/smoke.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$GLOBALS['pass'] = 0;
$GLOBALS['fail'] = 0;

function ok($cond, $label)
{
    if ($cond) {
        $GLOBALS['pass']++;
        echo "PASS  $label\n";
    } else {
        $GLOBALS['fail']++;
        echo "FAIL  $label\n";
    }
}

function callbacks_of($kb)
{
    $out = array();
    if (!is_array($kb) || !isset($kb['inline_keyboard'])) {
        return $out;
    }
    foreach ($kb['inline_keyboard'] as $row) {
        foreach ($row as $btn) {
            if (isset($btn['callback_data'])) {
                $out[] = $btn['callback_data'];
            }
        }
    }
    return $out;
}

foreach (array('Farsi', 'Codes', 'Order', 'Timer', 'Keyboard', 'Schema', 'Db', 'Telegram', 'Session', 'Router', 'AdminFlow', 'StudentFlow', 'Guard', 'Panel', 'PanelAccess', 'PanelAuth', 'ResultsExport') as $cls) {
    ok(class_exists($cls), 'class loads: ' . $cls);
}

ok(Farsi::digits('0123456789') === '۰۱۲۳۴۵۶۷۸۹', 'Farsi::digits converts all digits');
ok(Farsi::len('سلام') === 4, 'Farsi::len is multibyte aware');
ok(Farsi::normalizeCode(" ab-۱۲ _cd ") === 'AB12CD', 'Farsi::normalizeCode cleans Persian digits and separators');
ok(Codes::normalize("ab ۱۲") === 'AB12', 'Codes::normalize aliases Farsi::normalizeCode');
ok(Farsi::clip('abcdef', 4) === 'abc…', 'Farsi::clip truncates with ellipsis');
ok(Farsi::pct(3, 4) === 75, 'Farsi::pct computes percentage');
ok(Farsi::pct(1, 0) === 0, 'Farsi::pct guards division by zero');
ok(Farsi::csvSafe('=cmd') === "'=cmd", 'Farsi::csvSafe neutralizes formulas');
ok(Farsi::csvSafe('hello') === 'hello', 'Farsi::csvSafe leaves normal text');
$long = str_repeat('x', 9000);
$parts = Farsi::split($long, 4000);
ok(count($parts) === 3 && implode('', $parts) === $long, 'Farsi::split preserves content');

$panelPass = PanelAccess::generatePassword(24);
ok(strlen($panelPass) === 24, 'PanelAccess::generatePassword length');
ok(strspn($panelPass, PanelAccess::ALPHABET) === 24, 'PanelAccess::generatePassword uses safe alphabet');
$panelHash = PanelAccess::hashPassword('Secret-123');
ok(PanelAccess::verifyPassword('Secret-123', $panelHash) === true, 'PanelAccess::verifyPassword accepts correct password');
ok(PanelAccess::verifyPassword('wrong', $panelHash) === false, 'PanelAccess::verifyPassword rejects wrong password');
ok(PanelAccess::verifyPassword('Secret-123', '') === false, 'PanelAccess::verifyPassword rejects empty hash');
ok(PanelAccess::username() === 'admin', 'PanelAccess::username defaults to admin');
ok(Panel::url('quiz', array('id' => 3)) === 'index.php?page=quiz&id=3', 'Panel::url builds query string');
ok(Panel::url() === 'index.php', 'Panel::url defaults to dashboard');
ok(Panel::absoluteUrl('https://example.com/panel/') === true, 'Panel::absoluteUrl accepts https');
ok(Panel::absoluteUrl('javascript:alert(1)') === false, 'Panel::absoluteUrl rejects javascript');
ok(strpos(Panel::like('50%_x'), '\\%') !== false && strpos(Panel::like('50%_x'), '\\_') !== false, 'Panel::like escapes LIKE wildcards');
list($p, $pages, $off) = Panel::pageNumber(101, 20, 9);
ok($p === 6 && $pages === 6 && $off === 100, 'Panel::pageNumber clamps and offsets');
ok(is_array(Keyboard::panelLink('https://example.com/panel/')), 'Keyboard::panelLink builds URL keyboard');
ok(Keyboard::panelLink('javascript:alert(1)') === null, 'Keyboard::panelLink rejects unsafe URL');
$kbs = callbacks_of(Keyboard::adminMenu());
ok(in_array('adm:panel', $kbs, true), 'admin menu includes panel button');
ok(ResultsExport::filename(0) !== ResultsExport::filename(7), 'ResultsExport filename distinguishes quizzes');

$code = Codes::generate(6);
ok(strlen($code) === 6, 'Codes::generate length');
ok(strspn($code, Codes::ALPHABET) === 6, 'Codes::generate uses safe alphabet');
ok(Codes::isValidCustom('AB12') === true, 'Codes::isValidCustom accepts uppercase alnum');
ok(Codes::isValidCustom('A') === false, 'Codes::isValidCustom rejects short codes');
ok(Codes::isValidCustom('ab12') === false, 'Codes::isValidCustom rejects lowercase');
ok(Codes::isValidAccessCode('EXAM99') === true, 'Codes::isValidAccessCode accepts alnum');
ok(Codes::isValidAccessCode('ex am') === false, 'Codes::isValidAccessCode rejects spaces');

$ids = array(11, 22, 33, 44, 55);
ok(Order::questions($ids, false) === $ids, 'Order::questions keeps order when shuffle off');
$shuffled = Order::questions($ids, true);
sort($shuffled);
$expect = $ids;
sort($expect);
ok($shuffled === $expect, 'Order::questions is a permutation');
$map = Order::options(array(7, 8), false);
ok($map['7'] === array('A', 'B', 'C', 'D'), 'Order::options default order');
$map = Order::options(array(7, 8), true);
$flat = array_merge($map['7'], $map['8']);
sort($flat);
ok($flat === array('A', 'A', 'B', 'B', 'C', 'C', 'D', 'D'), 'Order::options keeps all letters');
ok(Order::decode(Order::encode(array('x' => 1)), array()) === array('x' => 1), 'Order encode/decode roundtrip');
ok(Order::decode('not json', array('f')) === array('f'), 'Order::decode falls back');

ok(Timer::deadline('2026-01-01 10:00:00', 3600) === '2026-01-01 11:00:00', 'Timer::deadline adds seconds');
ok(Timer::deadline('2026-01-01 10:00:00', 0) === null, 'Timer::deadline unlimited is null');
$r = Timer::remaining(date('Y-m-d H:i:s', time() + 60));
ok($r !== null && $r > 50 && $r <= 60, 'Timer::remaining returns positive seconds');
ok(Timer::expired(date('Y-m-d H:i:s', time() - 5)) === true, 'Timer::expired past deadline');
ok(Timer::expired(null) === false, 'Timer::expired null deadline');
ok(Timer::fmt(65) === '01:05', 'Timer::fmt minutes');
ok(Timer::fmt(3661) === '01:01:01', 'Timer::fmt hours');

$kbs = array(
    'quizList' => Keyboard::quizList(array(array('id' => 1, 'title' => 'آزمون نمونه', 'time_limit' => 600, 'access_code' => 'X'))),
    'question' => Keyboard::question(999999, 888888, array('A', 'B', 'C', 'D'), 'B', true, 'D', 0, 5),
    'confirmFinish' => Keyboard::confirmFinish(999999, 4),
    'resultMenu' => Keyboard::resultMenu(),
    'adminMenu' => Keyboard::adminMenu(),
    'yesNo' => Keyboard::yesNo(),
    'correctPicker' => Keyboard::correctPicker(),
    'editMenu' => Keyboard::editMenu(12345, array('shuffle' => 1, 'show_answer' => 1, 'is_active' => 1)),
    'pager' => Keyboard::pager(3, 9),
    'confirmDelete' => Keyboard::confirmDelete('student', 12345),
);
foreach ($kbs as $label => $kb) {
    $cbs = callbacks_of($kb);
    $ok = count($cbs) > 0;
    foreach ($cbs as $cb) {
        if (strlen($cb) > 64) {
            $ok = false;
        }
    }
    ok($ok, 'callback_data <= 64 bytes: ' . $label . ' (' . count($cbs) . ')');
}

$stmts = Schema::statements();
ok(count($stmts) >= 9, 'Schema has at least 9 statements');
$allCreate = true;
foreach ($stmts as $s) {
    if (stripos(ltrim($s), 'CREATE TABLE') !== 0) {
        $allCreate = false;
    }
}
ok($allCreate, 'Schema statements are CREATE TABLE only');

$roots = array('index.php', 'install.php', 'poll.php', 'cron_finish.php', 'export.php', 'config.php', 'panel/index.php', 'panel/views/layout.php', 'panel/views/login.php', 'panel/pages/dashboard.php', 'panel/pages/students.php', 'panel/pages/student.php', 'panel/pages/quizzes.php', 'panel/pages/quiz.php', 'panel/pages/question.php', 'panel/pages/results.php', 'panel/pages/attempt.php', 'panel/pages/broadcast.php', 'panel/pages/tools.php');
foreach ($roots as $f) {
    ok(is_file(dirname(__DIR__) . '/' . $f), 'entry file exists: ' . $f);
}
ok(is_array(App::cfg('panel')), 'config has panel block');

echo "\n" . $GLOBALS['pass'] . ' passed, ' . $GLOBALS['fail'] . " failed\n";
exit($GLOBALS['fail'] === 0 ? 0 : 1);
