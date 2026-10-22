<?php
/**
 * ============================================================
 *  Telegram exam bot - bootstrap
 *  Loads config, autoloads classes and provides the App service
 *  container. No database connection is opened until needed.
 * ============================================================
 */

if (!defined('TELEBOT')) {
    define('TELEBOT', true);
}
if (!defined('APP_DIR')) {
    define('APP_DIR', __DIR__);
}
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__));
}

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (is_dir(ROOT_DIR) && is_writable(ROOT_DIR)) {
    @ini_set('error_log', ROOT_DIR . '/error.log');
}

spl_autoload_register(function ($class) {
    $file = APP_DIR . DIRECTORY_SEPARATOR . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

$__cfg = require ROOT_DIR . '/config.php';
if (!is_array($__cfg)) {
    exit('config.php must return an array');
}

date_default_timezone_set(isset($__cfg['timezone']) ? $__cfg['timezone'] : 'Asia/Tehran');

final class App
{
    private static $cfg = array();
    private static $db = null;
    private static $tg = null;
    private static $session = null;

    public static function init(array $cfg)
    {
        self::$cfg = $cfg;
    }

    public static function cfg($key = null, $default = null)
    {
        if ($key === null) {
            return self::$cfg;
        }
        return array_key_exists($key, self::$cfg) ? self::$cfg[$key] : $default;
    }

    public static function db()
    {
        if (self::$db === null) {
            self::$db = new Db(self::$cfg['db']);
        }
        return self::$db;
    }

    public static function tg()
    {
        if (self::$tg === null) {
            self::$tg = new Telegram(self::$cfg['token']);
        }
        return self::$tg;
    }

    public static function session()
    {
        if (self::$session === null) {
            self::$session = new Session(self::db());
        }
        return self::$session;
    }

    public static function isAdmin($uid)
    {
        $ids = isset(self::$cfg['admin_ids']) ? self::$cfg['admin_ids'] : array();
        foreach ($ids as $id) {
            if ((string) $id === (string) $uid) {
                return true;
            }
        }
        return false;
    }

    public static function now()
    {
        return date('Y-m-d H:i:s');
    }

    public static function log($msg)
    {
        error_log('[telebot] ' . $msg);
    }
}

App::init($__cfg);
