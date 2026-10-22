<?php
/**
 * Reads schema.sql and returns individual SQL statements.
 */
class Schema
{
    public static function statements()
    {
        $file = ROOT_DIR . '/schema.sql';
        if (!is_file($file)) {
            throw new RuntimeException('schema.sql not found');
        }
        $sql = file_get_contents($file);

        // remove comment-only lines
        $lines = preg_split('/\r\n|\r|\n/', $sql);
        $clean = array();
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '' || strpos($t, '--') === 0) {
                continue;
            }
            $clean[] = $line;
        }
        $sql = implode("\n", $clean);

        $parts = explode(';', $sql);
        $out = array();
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $out[] = $part;
            }
        }
        return $out;
    }
}
