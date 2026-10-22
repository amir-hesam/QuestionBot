<?php
/**
 * Deterministic per-attempt ordering for questions and options.
 */
class Order
{
    public static function questions(array $ids, $shuffle = true)
    {
        $ids = array_values(array_map('intval', $ids));
        if ($shuffle) {
            for ($i = count($ids) - 1; $i > 0; $i--) {
                $j = random_int(0, $i);
                $tmp = $ids[$i];
                $ids[$i] = $ids[$j];
                $ids[$j] = $tmp;
            }
        }
        return $ids;
    }

    public static function options(array $ids, $shuffle = true)
    {
        $map = array();
        foreach ($ids as $id) {
            $letters = array('A', 'B', 'C', 'D');
            if ($shuffle) {
                for ($i = 3; $i > 0; $i--) {
                    $j = random_int(0, $i);
                    $tmp = $letters[$i];
                    $letters[$i] = $letters[$j];
                    $letters[$j] = $tmp;
                }
            }
            $map[(string) $id] = $letters;
        }
        return $map;
    }

    public static function encode($data)
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    public static function decode($json, $fallback)
    {
        $d = json_decode((string) $json, true);
        return is_array($d) ? $d : $fallback;
    }
}
