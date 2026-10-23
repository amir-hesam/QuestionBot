<?php
/**
 * Web panel credentials stored in the kv table and rotated from Telegram.
 */
class PanelAccess
{
    const KV_KEY = 'panel_auth';
    const MSG_KEY = 'panel_pass_msg';
    const URL_KEY = 'panel_url';
    const LOGIN_PREFIX = 'panel_login_';
    const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

    public static function generatePassword($len = 16)
    {
        $len = (int) $len;
        if ($len < 10) {
            $len = 10;
        }
        if ($len > 64) {
            $len = 64;
        }
        $max = strlen(self::ALPHABET) - 1;
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }
        return $out;
    }

    public static function hashPassword($password)
    {
        return password_hash((string) $password, PASSWORD_DEFAULT);
    }

    public static function verifyPassword($password, $hash)
    {
        if (!is_string($hash) || $hash === '') {
            return false;
        }
        return password_verify((string) $password, $hash);
    }

    public static function cfg($key, $default = null)
    {
        $panel = App::cfg('panel', array());
        if (!is_array($panel)) {
            return $default;
        }
        return array_key_exists($key, $panel) ? $panel[$key] : $default;
    }

    public static function username()
    {
        $user = trim((string) self::cfg('user', 'admin'));
        return $user === '' ? 'admin' : $user;
    }

    public static function state()
    {
        $db = App::db();
        $raw = $db->val('SELECT v FROM kv WHERE k = ?', array(self::KV_KEY));
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $state = json_decode($raw, true);
        if (!is_array($state) || empty($state['hash'])) {
            return null;
        }
        return $state;
    }

    public static function expired($state = null)
    {
        if ($state === null) {
            $state = self::state();
        }
        if (!$state || empty($state['hash'])) {
            return true;
        }
        $ttl = isset($state['ttl']) ? (int) $state['ttl'] : 0;
        if ($ttl <= 0) {
            return false;
        }
        $at = isset($state['at']) ? strtotime((string) $state['at']) : false;
        if ($at === false) {
            return true;
        }
        return ($at + $ttl) <= time();
    }

    public static function enabled()
    {
        $state = self::state();
        return $state !== null && !self::expired($state);
    }

    public static function version()
    {
        $state = self::state();
        if (!$state || empty($state['hash'])) {
            return '0';
        }
        return hash('sha256', (string) $state['hash']);
    }

    public static function rotate($telegramId)
    {
        $length = (int) self::cfg('password_length', 16);
        $password = self::generatePassword($length);
        $state = array(
            'hash' => self::hashPassword($password),
            'at' => App::now(),
            'by' => (int) $telegramId,
            'ttl' => (int) self::cfg('pass_ttl', 0),
        );
        $db = App::db();
        $db->exec(
            'INSERT INTO kv (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)',
            array(self::KV_KEY, json_encode($state, JSON_UNESCAPED_UNICODE))
        );
        return array('password' => $password, 'state' => $state);
    }

    public static function disable()
    {
        $db = App::db();
        $db->exec('DELETE FROM kv WHERE k IN (?, ?)', array(self::KV_KEY, self::MSG_KEY));
    }

    public static function lastMessage()
    {
        $db = App::db();
        $raw = $db->val('SELECT v FROM kv WHERE k = ?', array(self::MSG_KEY));
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $msg = json_decode($raw, true);
        if (!is_array($msg) || empty($msg['chat_id']) || empty($msg['message_id'])) {
            return null;
        }
        return $msg;
    }

    public static function rememberMessage($chatId, $messageId)
    {
        $db = App::db();
        $value = json_encode(array(
            'chat_id' => (string) $chatId,
            'message_id' => (int) $messageId,
        ));
        $db->exec(
            'INSERT INTO kv (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)',
            array(self::MSG_KEY, $value)
        );
    }

    public static function panelUrl()
    {
        $configured = trim((string) self::cfg('url', ''));
        if ($configured !== '') {
            return rtrim($configured, '/') . '/';
        }

        try {
            $stored = App::db()->val('SELECT v FROM kv WHERE k = ?', array(self::URL_KEY));
            if (is_string($stored) && trim($stored) !== '') {
                return rtrim(trim($stored), '/') . '/';
            }
        } catch (Throwable $e) {
            App::log('panelUrl kv: ' . $e->getMessage());
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
                    . ($dir === '' ? '' : $dir) . '/panel/';
            }
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
            if (basename($dir) === 'panel') {
                return $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir . '/';
            }
            return $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir . '/panel/';
        }

        return 'panel/';
    }

    public static function throttleKey($ip)
    {
        return self::LOGIN_PREFIX . hash('sha256', (string) $ip);
    }

    public static function throttleWait($ip)
    {
        $db = App::db();
        $raw = $db->val('SELECT v FROM kv WHERE k = ?', array(self::throttleKey($ip)));
        if (!is_string($raw) || $raw === '') {
            return 0;
        }
        $row = json_decode($raw, true);
        if (!is_array($row)) {
            return 0;
        }
        $count = isset($row['n']) ? (int) $row['n'] : 0;
        $first = isset($row['first']) ? (int) $row['first'] : 0;
        if ($count < 5 || $first <= 0) {
            return 0;
        }
        $left = ($first + 900) - time();
        return $left > 0 ? $left : 0;
    }

    public static function recordFailure($ip)
    {
        $db = App::db();
        $key = self::throttleKey($ip);
        $raw = $db->val('SELECT v FROM kv WHERE k = ?', array($key));
        $row = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($row) || empty($row['first']) || (int) $row['first'] < time() - 900) {
            $row = array('n' => 0, 'first' => time());
        }
        $row['n'] = (int) $row['n'] + 1;
        $db->exec(
            'INSERT INTO kv (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)',
            array($key, json_encode($row))
        );
    }

    public static function clearFailures($ip)
    {
        $db = App::db();
        $db->exec('DELETE FROM kv WHERE k = ?', array(self::throttleKey($ip)));
    }
}

