<?php
/**
 * Telegram webhook endpoint.
 * Configure this URL with install.php?key=...&action=webhook
 */

require __DIR__ . '/app/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function telebot_header($name)
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    if (isset($_SERVER[$key])) {
        return (string) $_SERVER[$key];
    }
    $alt = 'REDIRECT_' . $key;
    if (isset($_SERVER[$alt])) {
        return (string) $_SERVER[$alt];
    }
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strcasecmp($k, $name) === 0) {
                return (string) $v;
            }
        }
    }
    return '';
}

if (App::cfg('mode') !== 'webhook') {
    echo 'poll mode';
    exit;
}

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'POST only';
    exit;
}

$secret = (string) App::cfg('secret_token', '');
if ($secret !== '') {
    $got = telebot_header('X-Telegram-Bot-Api-Secret-Token');
    if (!hash_equals($secret, $got)) {
        http_response_code(403);
        echo 'forbidden';
        exit;
    }
}

$raw = file_get_contents('php://input');
$update = json_decode((string) $raw, true);
if (!is_array($update)) {
    echo 'ok';
    exit;
}

$updateId = isset($update['update_id']) ? (int) $update['update_id'] : 0;
if ($updateId > 0) {
    try {
        $db = App::db();
        $inserted = $db->exec(
            'INSERT IGNORE INTO processed_updates (update_id, created_at) VALUES (?, ?)',
            array($updateId, App::now())
        );
        if ((int) $inserted === 0) {
            echo 'duplicate';
            exit;
        }
        if (random_int(1, 100) === 1) {
            $db->exec('DELETE FROM processed_updates WHERE created_at < ?', array(
                date('Y-m-d H:i:s', time() - 259200),
            ));
        }
    } catch (Throwable $e) {
        App::log('dedupe failed: ' . $e->getMessage());
    }
}

try {
    Router::dispatch($update);
} catch (Throwable $e) {
    App::log('dispatch failed: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
}

echo 'ok';
