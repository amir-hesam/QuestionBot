<?php
/**
 * Student code generation and validation.
 */
class Codes
{
    const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function generate($len = 6)
    {
        $len = (int) $len;
        if ($len < 4) {
            $len = 4;
        }
        if ($len > 16) {
            $len = 16;
        }
        $a = self::ALPHABET;
        $n = strlen($a);
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $a[random_int(0, $n - 1)];
        }
        return $out;
    }

    public static function generateUnique(Db $db, $len = 6)
    {
        for ($i = 0; $i < 50; $i++) {
            $code = self::generate($len);
            $exists = $db->val('SELECT id FROM student_codes WHERE code = ?', array($code));
            if (!$exists) {
                return $code;
            }
        }
        throw new RuntimeException('could not generate a unique code');
    }

    public static function normalize($s)
    {
        return Farsi::normalizeCode($s);
    }

    public static function isValidCustom($code)
    {
        return (bool) preg_match('/^[A-Z0-9]{2,16}$/', (string) $code);
    }

    public static function isValidAccessCode($code)
    {
        return (bool) preg_match('/^[A-Z0-9]{2,32}$/', (string) $code);
    }

    public static function findByCode(Db $db, $code)
    {
        return $db->one('SELECT * FROM student_codes WHERE code = ?', array($code));
    }

    public static function lock(Db $db, $studentId, $telegramId)
    {
        $db->exec(
            'UPDATE student_codes SET locked_telegram_id = ? WHERE id = ? AND locked_telegram_id IS NULL',
            array($telegramId, $studentId)
        );
        return $db->val('SELECT locked_telegram_id FROM student_codes WHERE id = ?', array($studentId));
    }
}
