<?php
/**
 * Persian helpers: digits, escaping, clipping, time formatting, code normalization.
 */
class Farsi
{
    public static function digits($s)
    {
        return strtr((string) $s, array(
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ));
    }

    public static function esc($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function len($s)
    {
        $s = (string) $s;
        if (function_exists('mb_strlen')) {
            return mb_strlen($s, 'UTF-8');
        }
        $n = preg_match_all('/./us', $s);
        return $n === false ? strlen($s) : $n;
    }

    public static function clip($s, $max)
    {
        $s = (string) $s;
        if (self::len($s) <= $max) {
            return $s;
        }
        $cut = max(0, $max - 1);
        if (function_exists('mb_substr')) {
            $out = mb_substr($s, 0, $cut, 'UTF-8');
        } elseif (preg_match('/^.{0,' . $cut . '}/us', $s, $m)) {
            $out = $m[0];
        } else {
            $out = substr($s, 0, $cut);
        }
        return $out . '…';
    }

    public static function split($text, $max = 4000)
    {
        $text = (string) $text;
        $max = max(1, (int) $max);
        if (self::len($text) <= $max) {
            return array($text);
        }
        if (preg_match_all('/.{1,' . $max . '}/us', $text, $m)) {
            return $m[0];
        }
        $out = array();
        $total = strlen($text);
        $pos = 0;
        while ($pos < $total) {
            $out[] = substr($text, $pos, $max);
            $pos += $max;
        }
        return $out;
    }

    public static function dur($seconds)
    {
        $s = max(0, (int) $seconds);
        $h = intdiv($s, 3600);
        $m = intdiv($s % 3600, 60);
        $sec = $s % 60;
        if ($h > 0) {
            return sprintf('%02d:%02d:%02d', $h, $m, $sec);
        }
        return sprintf('%02d:%02d', $m, $sec);
    }

    public static function pct($a, $b)
    {
        $a = (int) $a;
        $b = (int) $b;
        if ($b <= 0) {
            return 0;
        }
        return (int) round($a * 100 / $b);
    }

    public static function normalizeCode($s)
    {
        $s = trim((string) $s);
        $map = array(
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        );
        $s = strtr($s, $map);
        $s = preg_replace('/[\s\-_]+/u', '', $s);
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($s, 'UTF-8');
        }
        return strtoupper($s);
    }

    public static function csvSafe($s)
    {
        $s = (string) $s;
        if ($s !== '' && strpos('=+-@', $s[0]) !== false) {
            return "'" . $s;
        }
        return $s;
    }
}
