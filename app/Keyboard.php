<?php
/**
 * Inline keyboard builders.
 */
class Keyboard
{
    const LABELS = array('الف', 'ب', 'پ', 'ت');

    public static function kb(array $rows)
    {
        return array('inline_keyboard' => $rows);
    }

    public static function quizList(array $quizzes)
    {
        $rows = array();
        foreach ($quizzes as $q) {
            $label = '📝 ' . $q['title'];
            if ((int) $q['time_limit'] > 0) {
                $label .= ' · ⏱' . Farsi::digits((int) round($q['time_limit'] / 60)) . 'د';
            }
            if (!empty($q['access_code'])) {
                $label .= ' 🔒';
            }
            $rows[] = array(array(
                'text' => Farsi::clip($label, 60),
                'callback_data' => 'qz:' . (int) $q['id'],
            ));
        }
        if (count($rows) === 0) {
            return null;
        }
        return self::kb($rows);
    }

    public static function adminMenu()
    {
        return self::kb(array(
            array(array('text' => '📝 لیست آزمون‌ها', 'callback_data' => 'adm:quizzes')),
            array(array('text' => '👥 لیست دانش‌آموزان', 'callback_data' => 'pg:students:1')),
            array(array('text' => '➕ آزمون جدید', 'callback_data' => 'adm:newquiz')),
            array(array('text' => '🔐 پنل وب', 'callback_data' => 'adm:panel')),
        ));
    }

    public static function panelLink($url)
    {
        if (empty($url) || !preg_match('#^https?://#i', (string) $url)) {
            return null;
        }
        return self::kb(array(
            array(array('text' => '🌐 باز کردن پنل مدیریت', 'url' => $url)),
        ));
    }

    public static function question($attemptId, $qid, array $order, $selected, $showAnswer, $correct, $index, $total)
    {
        $rows = array();
        $row = array();
        foreach ($order as $i => $orig) {
            if ($i > 3) {
                break;
            }
            $mark = '';
            if ($selected !== null && $selected === $orig) {
                $mark = ' ✔️';
            }
            if ($showAnswer && $selected !== null) {
                if ($orig === $correct) {
                    $mark = ' ✅';
                } elseif ($selected === $orig) {
                    $mark = ' ❌';
                }
            }
            $row[] = array(
                'text' => self::LABELS[$i] . $mark,
                'callback_data' => 'an:' . $attemptId . ':' . $qid . ':' . $orig,
            );
            if (count($row) === 2) {
                $rows[] = $row;
                $row = array();
            }
        }
        if (count($row) > 0) {
            $rows[] = $row;
        }

        $nav = array();
        if ($index > 0) {
            $nav[] = array('text' => '◀️', 'callback_data' => 'nv:' . $attemptId . ':' . ($index - 1));
        }
        $nav[] = array(
            'text' => Farsi::digits($index + 1) . ' / ' . Farsi::digits($total),
            'callback_data' => 'noop',
        );
        if ($index < $total - 1) {
            $nav[] = array('text' => '▶️', 'callback_data' => 'nv:' . $attemptId . ':' . ($index + 1));
        }
        $rows[] = $nav;
        $rows[] = array(array('text' => '🔄 پایان آزمون', 'callback_data' => 'fn:' . $attemptId));

        return self::kb($rows);
    }

    public static function confirmFinish($attemptId, $index)
    {
        return self::kb(array(
            array(array('text' => '✅ بله، پایان آزمون', 'callback_data' => 'fy:' . $attemptId)),
            array(array('text' => '↩️ ادامه آزمون', 'callback_data' => 'nv:' . $attemptId . ':' . $index)),
        ));
    }

    public static function resultMenu()
    {
        return self::kb(array(
            array(array('text' => '🏠 منوی اصلی', 'callback_data' => 'mn')),
        ));
    }

    public static function yesNo()
    {
        return self::kb(array(
            array(
                array('text' => '✅ بله', 'callback_data' => 'yn:1'),
                array('text' => '❌ خیر', 'callback_data' => 'yn:0'),
            ),
        ));
    }

    public static function correctPicker()
    {
        $row = array();
        foreach (array('A', 'B', 'C', 'D') as $i => $letter) {
            $row[] = array(
                'text' => self::LABELS[$i],
                'callback_data' => 'ap:' . $letter,
            );
        }
        return self::kb(array($row));
    }

    public static function editMenu($quizId, array $q)
    {
        $on = '✅';
        $off = '❌';
        return self::kb(array(
            array(array('text' => '⏱ زمان', 'callback_data' => 'eq:' . $quizId . ':time')),
            array(array('text' => '🔐 کد ورود', 'callback_data' => 'eq:' . $quizId . ':code')),
            array(array(
                'text' => '🎲 تصادفی‌سازی ' . ((int) $q['shuffle'] ? $on : $off),
                'callback_data' => 'eq:' . $quizId . ':shuffle',
            )),
            array(array(
                'text' => '👁 نمایش فوری پاسخ ' . ((int) $q['show_answer'] ? $on : $off),
                'callback_data' => 'eq:' . $quizId . ':show',
            )),
            array(array(
                'text' => '📢 فعال ' . ((int) $q['is_active'] ? $on : $off),
                'callback_data' => 'eq:' . $quizId . ':active',
            )),
            array(array('text' => '🗑 حذف آزمون', 'callback_data' => 'eq:' . $quizId . ':del')),
            array(array('text' => '✖️ بستن', 'callback_data' => 'eq:' . $quizId . ':done')),
        ));
    }

    public static function pager($page, $pages)
    {
        if ($pages <= 1) {
            return null;
        }
        $row = array();
        if ($page > 1) {
            $row[] = array('text' => '◀️', 'callback_data' => 'pg:students:' . ($page - 1));
        }
        $row[] = array(
            'text' => 'صفحه ' . Farsi::digits($page) . ' از ' . Farsi::digits($pages),
            'callback_data' => 'noop',
        );
        if ($page < $pages) {
            $row[] = array('text' => '▶️', 'callback_data' => 'pg:students:' . ($page + 1));
        }
        return self::kb(array($row));
    }

    public static function confirmDelete($kind, $id)
    {
        return self::kb(array(
            array(
                array('text' => '🗑 بله، حذف کن', 'callback_data' => 'dc:' . $kind . ':' . $id),
                array('text' => '✖️ انصراف', 'callback_data' => 'mn'),
            ),
        ));
    }
}
