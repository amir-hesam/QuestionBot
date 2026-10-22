<?php
/**
 * DB backed session/state storage (one active wizard per user).
 */
class Session
{
    private $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    public function get($uid)
    {
        $row = $this->db->one('SELECT * FROM bot_sessions WHERE telegram_id = ?', array($uid));
        if (!$row) {
            return null;
        }
        if (strtotime($row['updated_at']) < time() - 1800) {
            $this->clear($uid);
            return null;
        }
        $row['payload'] = $row['payload'] ? json_decode($row['payload'], true) : array();
        if (!is_array($row['payload'])) {
            $row['payload'] = array();
        }
        return $row;
    }

    public function state($uid)
    {
        $s = $this->get($uid);
        return $s ? $s['state'] : null;
    }

    public function payload($uid)
    {
        $s = $this->get($uid);
        return $s ? $s['payload'] : array();
    }

    public function set($uid, $state, array $payload = array())
    {
        $this->db->exec(
            'INSERT INTO bot_sessions (telegram_id, state, payload, updated_at) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE state = VALUES(state), payload = VALUES(payload), updated_at = VALUES(updated_at)',
            array($uid, $state, json_encode($payload, JSON_UNESCAPED_UNICODE), App::now())
        );
    }

    public function clear($uid)
    {
        $this->db->exec('DELETE FROM bot_sessions WHERE telegram_id = ?', array($uid));
    }
}
