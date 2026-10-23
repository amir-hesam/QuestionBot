<?php
/**
 * JSON API for the student Telegram Mini App.
 *
 * Every request is authenticated with Telegram WebApp initData.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, no-cache, must-revalidate');

function miniapp_respond($data, $status = 200)
{
    http_response_code((int) $status);
    echo json_encode(array('ok' => true, 'data' => $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function miniapp_fail($message, $status = 400, $code = 'bad_request', array $extra = array())
{
    http_response_code((int) $status);
    $body = array(
        'ok' => false,
        'error' => array(
            'code' => (string) $code,
            'message' => (string) $message,
        ),
    );
    if (count($extra) > 0) {
        $body['meta'] = $extra;
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function miniapp_input(array $payload, $key, $default = null)
{
    return array_key_exists($key, $payload) ? $payload[$key] : $default;
}

try {
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        miniapp_fail('درخواست باید POST باشد.', 405, 'method_not_allowed');
    }

    $raw = file_get_contents('php://input');
    $payload = array();
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }
    if (count($payload) === 0 && count($_POST) > 0) {
        $payload = $_POST;
    }

    $action = isset($_GET['action']) ? trim((string) $_GET['action']) : '';
    if ($action === '' && isset($payload['action'])) {
        $action = trim((string) $payload['action']);
    }
    if (!preg_match('/^[a-z_]+$/', $action)) {
        miniapp_fail('عملیات نامعتبر است.', 400, 'bad_action');
    }

    if (!MiniApp::enabled()) {
        miniapp_fail('مینی‌اپ غیرفعال است.', 404, 'disabled');
    }

    $initData = '';
    if (isset($_SERVER['HTTP_X_TELEGRAM_INIT_DATA'])) {
        $initData = (string) $_SERVER['HTTP_X_TELEGRAM_INIT_DATA'];
    } elseif (isset($payload['init_data'])) {
        $initData = (string) $payload['init_data'];
    }

    $user = MiniAppAuth::authenticate($initData);
    $uid = (int) $user['id'];

    switch ($action) {
        case 'home':
            miniapp_respond(MiniApp::home($uid));

        case 'enter':
            $quizId = (int) miniapp_input($payload, 'quiz_id', 0);
            $examCode = (string) miniapp_input($payload, 'exam_code', '');
            $studentCode = (string) miniapp_input($payload, 'student_code', '');
            miniapp_respond(MiniApp::enter($uid, $quizId, $examCode, $studentCode));

        case 'state':
            $attemptId = (int) miniapp_input($payload, 'attempt_id', 0);
            miniapp_respond(MiniApp::examState($uid, $attemptId));

        case 'answer':
            $attemptId = (int) miniapp_input($payload, 'attempt_id', 0);
            $questionId = (int) miniapp_input($payload, 'question_id', 0);
            $option = (string) miniapp_input($payload, 'option', '');
            miniapp_respond(MiniApp::answer($uid, $attemptId, $questionId, $option));

        case 'navigate':
            $attemptId = (int) miniapp_input($payload, 'attempt_id', 0);
            $index = (int) miniapp_input($payload, 'index', 0);
            miniapp_respond(MiniApp::navigate($uid, $attemptId, $index));

        case 'finish':
            $attemptId = (int) miniapp_input($payload, 'attempt_id', 0);
            miniapp_respond(MiniApp::finish($uid, $attemptId));

        case 'result':
            $attemptId = (int) miniapp_input($payload, 'attempt_id', 0);
            miniapp_respond(MiniApp::result($uid, $attemptId));

        default:
            miniapp_fail('عملیات ناشناخته است.', 404, 'unknown_action');
    }
} catch (MiniAppError $e) {
    miniapp_fail($e->getMessage(), $e->status(), $e->errorCode(), $e->extra());
} catch (Throwable $e) {
    App::log('miniapp api: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    miniapp_fail('خطای سرور رخ داد. دوباره تلاش کنید.', 500, 'server_error');
}
