<?php
/**
 * Fallback update fetcher for hosts where a webhook is not possible.
 * Run this from cron every minute (after deleting the webhook).
 * CLI:  php poll.php
 * Cron: wget -q -O /dev/null "https://SITE/telebot/poll.php?key=ADMIN_KEY"
 */

require __DIR__ . '/app/bootstrap.php';

Guard::authorize();
Guard::text();
set_time_limit(120);
ignore_user_abort(true);

$db = App::db();

try {
    $offset = (int) $db->val('SELECT v FROM kv WHERE k = ?', array('poll_offset'), 0);
} catch (Throwable $e) {
    http_response_code(500);
    Guard::line('error: run install.php?action=migrate first (' . $e->getMessage() . ')');
    exit;
}

if (App::cfg('mode') === 'webhook') {
    Guard::line('warning: mode is "webhook" in config.php — getUpdates will fail while a webhook is set.');
}

$started = time();
$batches = 0;
$processed = 0;
$lastError = '';

while ($batches < 5 && (time() - $started) < 50) {
    $res = App::tg()->getUpdates($offset, 20);
    if (empty($res['ok'])) {
        $lastError = isset($res['description']) ? (string) $res['description'] : 'getUpdates failed';
        break;
    }
    $updates = isset($res['result']) && is_array($res['result']) ? $res['result'] : array();
    if (count($updates) === 0) {
        break;
    }
    $batches++;
    foreach ($updates as $update) {
        $uid = isset($update['update_id']) ? (int) $update['update_id'] : 0;
        if ($uid <= 0) {
            continue;
        }
        try {
            $ins = $db->exec(
                'INSERT IGNORE INTO processed_updates (update_id, created_at) VALUES (?, ?)',
                array($uid, App::now())
            );
            if ((int) $ins === 1) {
                Router::dispatch($update);
                $processed++;
            }
        } catch (Throwable $e) {
            App::log('poll dispatch: ' . $e->getMessage());
            $lastError = $e->getMessage();
        }
        if ($uid >= $offset) {
            $offset = $uid + 1;
        }
    }
    $db->exec(
        'INSERT INTO kv (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)',
        array('poll_offset', (string) $offset)
    );
    if (count($updates) < 100) {
        break;
    }
}

try {
    $db->exec('DELETE FROM processed_updates WHERE created_at < ?', array(
        date('Y-m-d H:i:s', time() - 259200),
    ));
} catch (Throwable $e) {
    App::log('poll cleanup: ' . $e->getMessage());
}

Guard::line('processed: ' . $processed . ' batches: ' . $batches . ' offset: ' . $offset);
if ($lastError !== '') {
    Guard::line('last error: ' . $lastError);
}
