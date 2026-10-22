<?php
/**
 * Thin PDO wrapper for MySQL.
 */
class Db
{
    private $pdo;

    public function __construct(array $c)
    {
        $dsn = 'mysql:host=' . $c['host'] . ';dbname=' . $c['name'] . ';charset=utf8mb4';
        $opts = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        );
        $this->pdo = new PDO($dsn, $c['user'], $c['pass'], $opts);
    }

    public function pdo()
    {
        return $this->pdo;
    }

    public function q($sql, array $params = array())
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public function one($sql, array $params = array())
    {
        $r = $this->q($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public function all($sql, array $params = array())
    {
        return $this->q($sql, $params)->fetchAll();
    }

    public function val($sql, array $params = array(), $default = null)
    {
        $st = $this->q($sql, $params);
        $r = $st->fetch(PDO::FETCH_NUM);
        return $r === false ? $default : $r[0];
    }

    public function exec($sql, array $params = array())
    {
        return $this->q($sql, $params)->rowCount();
    }

    public function id()
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function migrate()
    {
        foreach (Schema::statements() as $sql) {
            $this->pdo->exec($sql);
        }
    }
}
