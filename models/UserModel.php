<?php
class UserModel extends Model {

    public function findByEmail(string $email): array|false {
        return $this->fetchOne("SELECT u.*, s.supplier_name, b.branch_name FROM users u 
            LEFT JOIN suppliers s ON u.supplier_id = s.supplier_id 
            LEFT JOIN branches b ON u.branch_id = b.branch_id 
            WHERE u.email = ? AND u.status = 'active'", [$email]);
    }

    public function findById(int $id): array|false {
        return $this->fetchOne("SELECT u.*, s.supplier_name, b.branch_name FROM users u 
            LEFT JOIN suppliers s ON u.supplier_id = s.supplier_id 
            LEFT JOIN branches b ON u.branch_id = b.branch_id 
            WHERE u.id = ?", [$id]);
    }

    public function getAll(): array {
        return $this->fetchAll("SELECT u.*, s.supplier_name, b.branch_name FROM users u 
            LEFT JOIN suppliers s ON u.supplier_id = s.supplier_id 
            LEFT JOIN branches b ON u.branch_id = b.branch_id 
            ORDER BY u.created_at DESC");
    }

    public function create(array $data): int {
        $this->execute(
            "INSERT INTO users (name, email, password, role, supplier_id, branch_id, status) VALUES (?,?,?,?,?,?,?)",
            [$data['name'], $data['email'], password_hash($data['password'], PASSWORD_BCRYPT, ['cost'=>12]),
             $data['role'], $data['supplier_id'] ?? null, $data['branch_id'] ?? null, $data['status'] ?? 'active']
        );
        return (int)$this->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $fields = ['name=?','email=?','role=?','supplier_id=?','branch_id=?','status=?'];
        $params = [$data['name'],$data['email'],$data['role'],$data['supplier_id'] ?? null, $data['branch_id'] ?? null, $data['status']];
        if (!empty($data['password'])) {
            $fields[] = 'password=?';
            $params[] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost'=>12]);
        }
        $params[] = $id;
        return (bool)$this->execute("UPDATE users SET " . implode(',', $fields) . " WHERE id=?", $params);
    }

    public function delete(int $id): bool {
        return (bool)$this->execute("DELETE FROM users WHERE id=?", [$id]);
    }

    public function updateLastLogin(int $id): void {
        $this->execute("UPDATE users SET last_login=NOW() WHERE id=?", [$id]);
    }

    public function countAll(): int {
        return (int)$this->fetchOne("SELECT COUNT(*) as c FROM users")['c'];
    }
}
