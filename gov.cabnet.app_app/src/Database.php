<?php

namespace Bridge;

use mysqli;

final class Database
{
    private mysqli $mysqli;

    public function __construct(Config $config)
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $this->mysqli = new mysqli(
            $config->get('db.host'),
            $config->get('db.username'),
            $config->get('db.password'),
            $config->get('db.database'),
            (int) $config->get('db.port', 3306)
        );

        $charset = $config->get('db.charset', 'utf8mb4');
        $this->mysqli->set_charset($charset);
    }

    public function connection(): mysqli
    {
        return $this->mysqli;
    }

    public function beginTransaction(): void
    {
        $this->mysqli->begin_transaction();
    }

    public function commit(): void
    {
        $this->mysqli->commit();
    }

    public function rollback(): void
    {
        $this->mysqli->rollback();
    }

    public function fetchOne(string $sql, array $params = [], string $types = ''): ?array
    {
        $rows = $this->fetchAll($sql, $params, $types);
        return $rows[0] ?? null;
    }

    public function fetchAll(string $sql, array $params = [], string $types = ''): array
    {
        $stmt = $this->prepareAndBind($sql, $params, $types);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result === false) {
            return [];
        }

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function execute(string $sql, array $params = [], string $types = ''): int
    {
        $stmt = $this->prepareAndBind($sql, $params, $types);
        $stmt->execute();
        return $stmt->affected_rows;
    }

    public function insert(string $sql, array $params = [], string $types = ''): int
    {
        $stmt = $this->prepareAndBind($sql, $params, $types);
        $stmt->execute();
        return (int) $this->mysqli->insert_id;
    }

    private function prepareAndBind(string $sql, array $params, string $types): \mysqli_stmt
    {
        $stmt = $this->mysqli->prepare($sql);

        if (!empty($params)) {
            if ($types === '') {
                foreach ($params as $param) {
                    $types .= is_int($param) ? 'i' : (is_float($param) ? 'd' : 's');
                }
            }

            $bind = [$types];
            foreach ($params as $index => $param) {
                $bind[] = &$params[$index];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);
        }

        return $stmt;
    }
}
