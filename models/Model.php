<?php
abstract class Model {
    protected PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    protected function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    protected function fetchAll(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }

    protected function fetchOne(string $sql, array $params = []): array|false {
        return $this->query($sql, $params)->fetch();
    }

    protected function lastInsertId(): string {
        return $this->db->lastInsertId();
    }

    protected function execute(string $sql, array $params = []): int {
        return $this->query($sql, $params)->rowCount();
    }
}
