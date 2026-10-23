<?php
/**
 * Small helpers for the web panel.
 */
class Panel
{
    public static function h($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function url($page = 'dashboard', array $params = array())
    {
        if ($page === 'dashboard' || $page === '') {
            $base = 'index.php';
        } else {
            $base = 'index.php?page=' . rawurlencode((string) $page);
        }
        if (count($params) > 0) {
            $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
            if ($query !== '') {
                $base .= (strpos($base, '?') === false ? '?' : '&') . $query;
            }
        }
        return $base;
    }

    public static function redirect($url)
    {
        header('Location: ' . $url);
        exit;
    }

    public static function isPost()
    {
        return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    public static function requirePost()
    {
        if (!self::isPost()) {
            http_response_code(405);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'POST only';
            exit;
        }
    }

    public static function get($key, $default = '')
    {
        return isset($_GET[$key]) && !is_array($_GET[$key]) ? (string) $_GET[$key] : $default;
    }

    public static function post($key, $default = '')
    {
        return isset($_POST[$key]) && !is_array($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
    }

    public static function rawPost($key, $default = '')
    {
        return isset($_POST[$key]) && !is_array($_POST[$key]) ? (string) $_POST[$key] : $default;
    }

    public static function intGet($key, $default = 0)
    {
        return isset($_GET[$key]) && !is_array($_GET[$key]) ? (int) $_GET[$key] : (int) $default;
    }

    public static function intPost($key, $default = 0)
    {
        return isset($_POST[$key]) && !is_array($_POST[$key]) ? (int) $_POST[$key] : (int) $default;
    }

    public static function boolPost($key)
    {
        return isset($_POST[$key]) && (string) $_POST[$key] !== '0';
    }

    public static function pageNumber($total, $per, $page)
    {
        $per = max(1, (int) $per);
        $pages = max(1, (int) ceil((int) $total / $per));
        $page = max(1, (int) $page);
        if ($page > $pages) {
            $page = $pages;
        }
        return array($page, $pages, ($page - 1) * $per);
    }

    public static function pager($pageName, $page, $pages, array $params = array())
    {
        if ((int) $pages <= 1) {
            return '';
        }
        $page = (int) $page;
        $pages = (int) $pages;
        $out = '<nav class="pager">';
        if ($page > 1) {
            $params['page'] = $page - 1;
            $out .= '<a class="btn ghost" href="' . self::h(self::url($pageName, $params)) . '">قبلی</a>';
        }
        $out .= '<span class="muted">صفحه ' . Farsi::digits($page) . ' از ' . Farsi::digits($pages) . '</span>';
        if ($page < $pages) {
            $params['page'] = $page + 1;
            $out .= '<a class="btn ghost" href="' . self::h(self::url($pageName, $params)) . '">بعدی</a>';
        }
        $out .= '</nav>';
        return $out;
    }

    public static function like($text)
    {
        return addcslashes((string) $text, '%_\\');
    }

    public static function dt($value)
    {
        if ($value === null || $value === '') {
            return '—';
        }
        return Farsi::digits((string) $value);
    }

    public static function dur($start, $end)
    {
        if (empty($start) || empty($end)) {
            return '—';
        }
        $seconds = max(0, strtotime((string) $end) - strtotime((string) $start));
        return Farsi::digits(Timer::fmt($seconds));
    }

    public static function optionLabel($letter)
    {
        $labels = array('A' => 'الف', 'B' => 'ب', 'C' => 'پ', 'D' => 'ت');
        return isset($labels[$letter]) ? $labels[$letter] : (string) $letter;
    }

    public static function attemptStatus($status)
    {
        return $status === 'finished' ? 'تمام‌شده' : 'در حال اجرا';
    }

    public static function finishReason($reason)
    {
        $map = array(
            'student' => 'توسط دانش‌آموز',
            'timeout' => 'اتمام زمان',
            'admin' => 'توسط ادمین',
        );
        return isset($map[$reason]) ? $map[$reason] : '—';
    }

    public static function flashRedirect($type, $message, $url)
    {
        PanelAuth::flash($type, $message);
        self::redirect($url);
    }

    public static function absoluteUrl($url)
    {
        return preg_match('#^https?://#i', (string) $url) === 1;
    }
}
