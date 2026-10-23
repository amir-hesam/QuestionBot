<?php
/**
 * Telegram Mini App authentication using signed WebApp initData.
 */
class MiniAppAuth
{
    public static function enabled()
    {
        $cfg = App::cfg('miniapp', array());
        if (!is_array($cfg) || (isset($cfg['enabled']) && !$cfg['enabled'])) {
            return false;
        }
        return true;
    }

    public static function ttl()
    {
        $cfg = App::cfg('miniapp', array());
        $ttl = is_array($cfg) && isset($cfg['auth_ttl']) ? (int) $cfg['auth_ttl'] : 86400;
        return $ttl > 0 ? $ttl : 86400;
    }

    /**
     * Validate initData without touching the database. Returns the Telegram user.
     */
    public static function validateInitData($initData, $token, $ttl = 0)
    {
        $initData = trim((string) $initData);
        $token = (string) $token;
        if ($initData === '' || $token === '') {
            throw new MiniAppError('اطلاعات ورود تلگرام موجود نیست. مینی‌اپ را از داخل ربات باز کنید.', 401, 'unauthorized');
        }

        parse_str($initData, $data);
        if (!is_array($data) || empty($data['hash']) || empty($data['auth_date'])) {
            throw new MiniAppError('اطلاعات ورود تلگرام ناقص است.', 401, 'unauthorized');
        }

        $hash = strtolower((string) $data['hash']);
        $authDate = (int) $data['auth_date'];
        $ttl = (int) $ttl;
        if ($ttl > 0 && ($authDate <= 0 || time() - $authDate > $ttl)) {
            throw new MiniAppError('نشست ورود منقضی شده است. مینی‌اپ را دوباره باز کنید.', 401, 'expired');
        }
        if ($authDate > time() + 120) {
            throw new MiniAppError('زمان نشست ورود معتبر نیست.', 401, 'unauthorized');
        }

        if (!isset($data['user'])) {
            throw new MiniAppError('اطلاعات کاربر تلگرام موجود نیست.', 401, 'unauthorized');
        }
        $user = json_decode((string) $data['user'], true);
        if (!is_array($user) || empty($user['id'])) {
            throw new MiniAppError('اطلاعات کاربر تلگرام معتبر نیست.', 401, 'unauthorized');
        }

        $secret = hash_hmac('sha256', $token, 'WebAppData', true);
        $valid = false;
        foreach (array(false, true) as $skipSignature) {
            $pairs = array();
            foreach ($data as $k => $v) {
                if ($k === 'hash' || ($skipSignature && $k === 'signature')) {
                    continue;
                }
                if (is_array($v)) {
                    continue;
                }
                $pairs[] = $k . '=' . $v;
            }
            sort($pairs, SORT_STRING);
            $calc = hash_hmac('sha256', implode("\n", $pairs), $secret);
            if (hash_equals($calc, $hash)) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            throw new MiniAppError('امضای ورود تلگرام معتبر نیست.', 401, 'unauthorized');
        }

        $user['id'] = (int) $user['id'];
        return $user;
    }

    /**
     * Validate initData and keep bot_users up to date.
     */
    public static function authenticate($initData)
    {
        $user = self::validateInitData(
            $initData,
            (string) App::cfg('token', ''),
            self::ttl()
        );

        $db = App::db();
        $first = isset($user['first_name']) ? (string) $user['first_name'] : '';
        $last = isset($user['last_name']) ? (string) $user['last_name'] : '';
        $name = trim($first . ' ' . $last);
        $db->exec(
            'INSERT INTO bot_users (telegram_id, username, full_name, created_at, last_seen)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE username = VALUES(username), full_name = VALUES(full_name), last_seen = VALUES(last_seen)',
            array(
                (int) $user['id'],
                isset($user['username']) ? (string) $user['username'] : null,
                $name !== '' ? $name : null,
                App::now(),
                App::now(),
            )
        );

        return $user;
    }
}
