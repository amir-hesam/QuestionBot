<?php
/**
 * Minimal Telegram Bot API client (cURL with file_get_contents fallback).
 */
class Telegram
{
    private $token;
    private $base;

    public function __construct($token)
    {
        $this->token = $token;
        $this->base = 'https://api.telegram.org/bot' . $token . '/';
    }

    public function call($method, array $params = array(), $timeout = 25)
    {
        $url = $this->base . $method;
        $body = array();
        foreach ($params as $k => $v) {
            if ($v === null) {
                continue;
            }
            if ($k === 'reply_markup' || $k === 'allowed_updates') {
                $body[$k] = is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($v)) {
                $body[$k] = $v ? '1' : '0';
            } else {
                $body[$k] = (string) $v;
            }
        }

        $raw = $this->request($url, $body, $timeout);
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            App::log('Bad API response for ' . $method . ': ' . substr((string) $raw, 0, 180));
            return array('ok' => false, 'description' => 'invalid response');
        }

        if (empty($json['ok'])) {
            $desc = isset($json['description']) ? $json['description'] : 'error';
            if (isset($json['parameters']['retry_after']) && $json['parameters']['retry_after'] <= 5) {
                usleep((int) $json['parameters']['retry_after'] * 1000000);
                $raw = $this->request($url, $body, $timeout);
                $json = json_decode($raw, true);
                if (is_array($json) && !empty($json['ok'])) {
                    return $json;
                }
            }
            if (stripos($desc, 'message is not modified') === false
                && stripos($desc, 'query is too old') === false
                && stripos($desc, 'message to edit not found') === false) {
                App::log('API error ' . $method . ': ' . $desc);
            }
        }
        return $json;
    }

    private function request($url, array $body, $timeout)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            $out = curl_exec($ch);
            if ($out === false) {
                App::log('curl error: ' . $this->mask(curl_error($ch)));
                curl_close($ch);
                return '';
            }
            curl_close($ch);
            return $out;
        }

        $ctx = stream_context_create(array('http' => array(
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($body),
            'timeout' => $timeout,
            'ignore_errors' => true,
        )));
        $out = @file_get_contents($url, false, $ctx);
        return $out === false ? '' : $out;
    }

    private function mask($s)
    {
        return str_replace($this->token, '***', (string) $s);
    }

    public function send($chatId, $text, $kb = null, array $options = array())
    {
        $text = (string) $text;
        if (trim($text) === '') {
            return array('ok' => false, 'description' => 'empty text');
        }
        $chunks = Farsi::split($text, 4000);
        $last = count($chunks) - 1;
        $res = array('ok' => false);
        foreach ($chunks as $i => $chunk) {
            $p = array_merge($options, array(
                'chat_id' => $chatId,
                'text' => $chunk,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ));
            if ($i === $last && $kb !== null) {
                $p['reply_markup'] = $kb;
            }
            $res = $this->call('sendMessage', $p);
            if (empty($res['ok']) && isset($res['description'])
                && stripos($res['description'], 'parse') !== false) {
                unset($p['parse_mode']);
                $p['text'] = strip_tags($chunk);
                $res = $this->call('sendMessage', $p);
            }
        }
        return $res;
    }

    public function sendSecret($chatId, $text, $kb = null)
    {
        $res = $this->send($chatId, $text, $kb, array('protect_content' => true));
        if (empty($res['ok']) && isset($res['description'])
            && stripos($res['description'], 'protect') !== false) {
            $res = $this->send($chatId, $text, $kb);
        }
        return $res;
    }

    public function deleteMessage($chatId, $messageId)
    {
        return $this->call('deleteMessage', array(
            'chat_id' => $chatId,
            'message_id' => (int) $messageId,
        ), 10);
    }

    public function edit($chatId, $mid, $text, $kb = null)
    {
        $p = array(
            'chat_id' => $chatId,
            'message_id' => $mid,
            'text' => Farsi::clip((string) $text, 4096),
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        );
        if ($kb !== null) {
            $p['reply_markup'] = $kb;
        }
        $res = $this->call('editMessageText', $p);
        if (empty($res['ok']) && isset($res['description'])
            && stripos($res['description'], 'parse') !== false) {
            unset($p['parse_mode']);
            $p['text'] = strip_tags((string) $text);
            $res = $this->call('editMessageText', $p);
        }
        return $res;
    }

    public function answerCb($id, $text = '', $alert = false)
    {
        $p = array('callback_query_id' => $id);
        if ($text !== '') {
            $p['text'] = $text;
            $p['show_alert'] = (bool) $alert;
        }
        return $this->call('answerCallbackQuery', $p, 10);
    }

    public function sendMany($ids, $text)
    {
        foreach ($ids as $id) {
            try {
                $this->send($id, $text);
            } catch (Throwable $e) {
                App::log('sendMany: ' . $e->getMessage());
            }
        }
    }

    public function getMe()
    {
        return $this->call('getMe');
    }

    public function setWebhook($url, $secret = null)
    {
        $p = array(
            'url' => $url,
            'allowed_updates' => array('message', 'callback_query'),
            'drop_pending_updates' => true,
        );
        if ($secret !== null && $secret !== '') {
            $p['secret_token'] = $secret;
        }
        return $this->call('setWebhook', $p);
    }

    public function deleteWebhook()
    {
        return $this->call('deleteWebhook', array('drop_pending_updates' => false));
    }

    public function getWebhookInfo()
    {
        return $this->call('getWebhookInfo');
    }

    public function setChatMenuButton($url, $text = 'آزمون‌ها')
    {
        if (!preg_match('#^https://#i', (string) $url)) {
            return array('ok' => false, 'description' => 'web_app URL must be HTTPS');
        }
        $button = array(
            'type' => 'web_app',
            'text' => Farsi::clip((string) $text, 32),
            'web_app' => array('url' => (string) $url),
        );
        return $this->call('setChatMenuButton', array(
            'menu_button' => json_encode($button, JSON_UNESCAPED_UNICODE),
        ));
    }

    public function getUpdates($offset = 0, $timeout = 0)
    {
        return $this->call('getUpdates', array(
            'offset' => (int) $offset,
            'timeout' => (int) $timeout,
            'limit' => 100,
            'allowed_updates' => array('message', 'callback_query'),
        ), $timeout + 15);
    }
}
