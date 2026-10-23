<?php
/**
 * PHP session authentication for the web panel.
 */
class PanelAuth
{
    const SESSION_NAME = 'TELEBOTPANEL';
    private static $started = false;

    public static function isHttps()
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        return false;
    }

    public static function start()
    {
        if (self::$started) {
            return;
        }
        self::$started = true;

        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', '28800');

        session_name(self::SESSION_NAME);
        $params = array(
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        );
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params($params);
        } else {
            session_set_cookie_params(0, '/; samesite=Lax', '', self::isHttps(), true);
        }
        session_start();

        if (!is_array($_SESSION)) {
            $_SESSION = array();
        }

        if (!empty($_SESSION['panel_user'])) {
            $timeout = (int) PanelAccess::cfg('idle_timeout', 1800);
            $seen = isset($_SESSION['panel_seen_at']) ? (int) $_SESSION['panel_seen_at'] : 0;
            $version = isset($_SESSION['panel_ver']) ? (string) $_SESSION['panel_ver'] : '';
            $ua = isset($_SESSION['panel_ua']) ? (string) $_SESSION['panel_ua'] : '';

            $valid = true;
            if ($timeout > 0 && ($seen <= 0 || time() - $seen > $timeout)) {
                $valid = false;
            }
            if ($valid) {
                try {
                    if (!hash_equals($version, PanelAccess::version())) {
                        $valid = false;
                    }
                } catch (Throwable $e) {
                    App::log('panel session version: ' . $e->getMessage());
                    $valid = false;
                }
            }
            if ($valid && ($ua === '' || !hash_equals($ua, self::uaHash()))) {
                $valid = false;
            }
            if (!$valid) {
                self::clearAuth();
            }
        }

        if (!empty($_SESSION['panel_user'])) {
            $_SESSION['panel_seen_at'] = time();
        }
    }

    public static function check()
    {
        return !empty($_SESSION['panel_user']);
    }

    public static function user()
    {
        return self::check() ? (string) $_SESSION['panel_user'] : '';
    }

    public static function login($username, $password)
    {
        $ip = self::clientIp();
        $wait = PanelAccess::throttleWait($ip);
        if ($wait > 0) {
            $minutes = max(1, (int) ceil($wait / 60));
            return array(false, 'تعداد تلاش‌های ناموفق زیاد است. ' . Farsi::digits($minutes) . ' دقیقه دیگر تلاش کنید.');
        }

        $state = PanelAccess::state();
        if (!$state) {
            return array(false, 'پنل وب هنوز فعال نشده است. در تلگرام دستور /panel را بزنید.');
        }
        if (PanelAccess::expired($state)) {
            return array(false, 'رمز پنل منقضی شده است. در تلگرام دوباره /panel را بزنید.');
        }

        $expectedUser = PanelAccess::username();
        if (!hash_equals($expectedUser, (string) $username)
            || !PanelAccess::verifyPassword($password, isset($state['hash']) ? $state['hash'] : '')) {
            PanelAccess::recordFailure($ip);
            return array(false, 'نام کاربری یا رمز عبور درست نیست.');
        }

        PanelAccess::clearFailures($ip);
        session_regenerate_id(true);
        $_SESSION['panel_user'] = $expectedUser;
        $_SESSION['panel_login_at'] = time();
        $_SESSION['panel_seen_at'] = time();
        $_SESSION['panel_ver'] = PanelAccess::version();
        $_SESSION['panel_ua'] = self::uaHash();
        unset($_SESSION['panel_csrf']);
        return array(true, '');
    }

    public static function logout()
    {
        self::clearAuth();
        session_regenerate_id(true);
    }

    private static function clearAuth()
    {
        unset(
            $_SESSION['panel_user'],
            $_SESSION['panel_login_at'],
            $_SESSION['panel_seen_at'],
            $_SESSION['panel_ver'],
            $_SESSION['panel_ua'],
            $_SESSION['panel_csrf']
        );
    }

    public static function csrfToken()
    {
        if (empty($_SESSION['panel_csrf'])) {
            $_SESSION['panel_csrf'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['panel_csrf'];
    }

    public static function csrfField()
    {
        return '<input type="hidden" name="_csrf" value="'
            . htmlspecialchars(self::csrfToken(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
    }

    public static function csrfValid()
    {
        $sent = isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : '';
        $mine = isset($_SESSION['panel_csrf']) ? (string) $_SESSION['panel_csrf'] : '';
        return $sent !== '' && $mine !== '' && hash_equals($mine, $sent);
    }

    public static function verifyCsrf()
    {
        if (self::csrfValid()) {
            return;
        }
        http_response_code(419);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="fa" dir="rtl"><meta charset="utf-8">';
        echo '<title>نشست منقضی شده است</title>';
        echo '<body>';
        echo '<h1>نشست منقضی شده است</h1>';
        echo '<p>صفحه را دوباره بارگذاری کنید و از ابتدا وارد شوید.</p>';
        echo '<p><a href="index.php">بازگشت به پنل</a></p></body></html>';
        exit;
    }

    public static function flash($type, $message)
    {
        if (empty($_SESSION['panel_flash']) || !is_array($_SESSION['panel_flash'])) {
            $_SESSION['panel_flash'] = array();
        }
        $_SESSION['panel_flash'][] = array((string) $type, (string) $message);
    }

    public static function flashes()
    {
        if (empty($_SESSION['panel_flash']) || !is_array($_SESSION['panel_flash'])) {
            return array();
        }
        $out = $_SESSION['panel_flash'];
        unset($_SESSION['panel_flash']);
        return $out;
    }

    public static function clientIp()
    {
        return isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] !== ''
            ? (string) $_SERVER['REMOTE_ADDR']
            : '0.0.0.0';
    }

    private static function uaHash()
    {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        return hash('sha256', $ua);
    }
}
