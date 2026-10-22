<?php
/**
 * Cron job: finalize timed-out attempts.
 * CLI:  php cron_finish.php
 * Cron: wget -q -O /dev/null "https://SITE/telebot/cron_finish.php?key=ADMIN_KEY"
 */

require __DIR__ . '/app/bootstrap.php';

Guard::authorize();
Guard::text();
set_time_limit(120);

try {
    $db = App::db();
    $now = App::now();

    $rows = $db->all(
        "SELECT id FROM attempts
          WHERE status = 'in_progress' AND deadline IS NOT NULL AND deadline <= ?
          ORDER BY deadline ASC LIMIT 100",
        array($now)
    );

    $done = 0;
    foreach ($rows as $r) {
        $att = Timer::finalize((int) $r['id'], 'timeout');
        if ($att && $att['status'] === 'finished') {
            $done++;
        }
    }

    $db->exec('DELETE FROM processed_updates WHERE created_at < ?', array(
        date('Y-m-d H:i:s', time() - 259200),
    ));
    $db->exec('DELETE FROM bot_sessions WHERE updated_at < ?', array(
        date('Y-m-d H:i:s', time() - 604800),
    ));

    Guard::line('finished: ' . $done . ' / candidates: ' . count($rows) . ' at ' . $now);
} catch (Throwable $e) {
    App::log('cron_finish: ' . $e->getMessage());
    http_response_code(500);
    Guard::line('error: ' . $e->getMessage());
}
