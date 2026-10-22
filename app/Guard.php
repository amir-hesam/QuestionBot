<?php
/**
 * Access control + output helpers for the HTTP maintenance endpoints.
 */
class Guard
{
    public static function cli()
    {
        return PHP_SAPI === 'cli';
    }

    public static function authorize()
    {
        if (self::cli()) {
            return;
        }
        $key = isset($_GET['key']) ? (string) $_GET['key'] : '';
        if ($key === '' && isset($_POST['key'])) {
            $key = (string) $_POST['key'];
        }
        $expected = (string) App::cfg('admin_key', '');
        if ($expected === '' || $key === '' || !hash_equals($expected, $key)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo "403 Forbidden\n";
            exit;
        }
    }

    public static function text()
    {
        if (!self::cli()) {
            header('Content-Type: text/plain; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }
    }

    public static function line($s = '')
    {
        if (self::cli()) {
            echo $s . PHP_EOL;
        } else {
            echo $s . "\n";
        }
    }
}
