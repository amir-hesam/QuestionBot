<?php
/**
 * Turns an incoming Telegram update into handler calls.
 */
class Router
{
    public static function dispatch(array $update)
    {
        if (isset($update['callback_query'])) {
            self::onCallback($update['callback_query']);
            return;
        }
        if (isset($update['message'])) {
            self::onMessage($update['message']);
            return;
        }
    }

    private static function touchUser(array $from)
    {
        $db = App::db();
        $name = trim(
            (isset($from['first_name']) ? $from['first_name'] : '') . ' '
            . (isset($from['last_name']) ? $from['last_name'] : '')
        );
        $db->exec(
            'INSERT INTO bot_users (telegram_id, username, full_name, created_at, last_seen)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE username = VALUES(username), full_name = VALUES(full_name), last_seen = VALUES(last_seen)',
            array(
                (int) $from['id'],
                isset($from['username']) ? $from['username'] : null,
                $name !== '' ? $name : null,
                App::now(),
                App::now(),
            )
        );
    }

    private static function onMessage(array $m)
    {
        if (empty($m['from']) || !empty($m['from']['is_bot'])) {
            return;
        }
        $from = $m['from'];
        $uid = (int) $from['id'];
        $chatId = isset($m['chat']['id']) ? $m['chat']['id'] : $uid;
        self::touchUser($from);

        $text = isset($m['text']) ? trim($m['text']) : '';
        if ($text === '') {
            return;
        }

        if ($text[0] === '/') {
            $parts = preg_split('/\s+/u', $text);
            $cmd = strtolower($parts[0]);
            $at = strpos($cmd, '@');
            if ($at !== false) {
                $cmd = substr($cmd, 0, $at);
            }
            $args = array_slice($parts, 1);

            if ($cmd === '/start') {
                App::session()->clear($uid);
            }

            $state = App::session()->state($uid);
            if (App::isAdmin($uid) && $cmd === '/skip'
                && $state !== null && strpos($state, 'st_') !== 0) {
                AdminFlow::wizardInput($text, $uid, $chatId);
                return;
            }

            if (App::isAdmin($uid)) {
                if ($cmd === '/panel') {
                    AdminFlow::panelLink($uid, $chatId);
                    return;
                }
                AdminFlow::handle($cmd, $args, $uid, $chatId, $from);
                return;
            }

            if ($cmd === '/start') {
                StudentFlow::menu($uid, $chatId);
            } elseif ($cmd === '/help') {
                StudentFlow::help($chatId);
            } elseif ($cmd === '/cancel') {
                App::session()->clear($uid);
                App::tg()->send($chatId, '✅ لغو شد.');
            } else {
                App::tg()->send($chatId, '⛔️ دسترسی ندارید.');
            }
            return;
        }

        $state = App::session()->state($uid);
        if ($state !== null && strpos($state, 'st_') === 0) {
            StudentFlow::codeInput($text, $uid, $chatId);
            return;
        }
        if (App::isAdmin($uid) && $state !== null) {
            AdminFlow::wizardInput($text, $uid, $chatId);
            return;
        }
        if (!App::isAdmin($uid)) {
            App::tg()->send($chatId, 'برای شروع /start را بزن.');
            return;
        }
        App::tg()->send($chatId, 'دستور را از /help ببین.');
    }

    private static function onCallback(array $cq)
    {
        if (empty($cq['from'])) {
            return;
        }
        $from = $cq['from'];
        $uid = (int) $from['id'];
        $chatId = isset($cq['message']['chat']['id']) ? $cq['message']['chat']['id'] : $uid;
        $mid = isset($cq['message']['message_id']) ? $cq['message']['message_id'] : null;

        try {
            App::tg()->answerCb($cq['id']);
        } catch (Throwable $e) {
            // ignore
        }
        self::touchUser($from);

        $data = isset($cq['data']) ? (string) $cq['data'] : '';
        if ($data === '') {
            return;
        }
        $p = explode(':', $data);
        $kind = $p[0];
        if ($kind === 'noop') {
            return;
        }

        if (App::isAdmin($uid) && in_array($kind, array('yn', 'ap', 'eq', 'dc', 'pg', 'adm'), true)) {
            AdminFlow::callback($data, $uid, $chatId, $mid);
            return;
        }

        StudentFlow::callback($data, $uid, $chatId, $mid);
    }
}
