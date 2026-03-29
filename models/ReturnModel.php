<?php

class ReturnModel extends Model {

    public function getAll(array $filters = []): array {
        $sql = "SELECT r.*, p.product_name, p.brand, p.model, u.name as created_by_name,
                       CASE 
                           WHEN r.order_id IS NOT NULL THEN o.created_at 
                           WHEN r.sale_id IS NOT NULL THEN s.created_at 
                       END as purchase_date
                FROM returns r
                JOIN products p ON r.product_id=p.product_id
                JOIN users u ON r.created_by=u.id
                LEFT JOIN orders o ON r.order_id=o.order_id
                LEFT JOIN sales s ON r.sale_id=s.sale_id";

        $params = [];
        $where = [];

        if (!empty($filters['return_status'])) {
            $where[] = "r.return_status=?";
            $params[] = $filters['return_status'];
        }

        if (!empty($filters['product_id'])) {
            $where[] = "r.product_id=?";
            $params[] = $filters['product_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "r.return_date >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "r.return_date <= ?";
            $params[] = $filters['date_to'];
        }

        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY r.return_date DESC";

        return $this->fetchAll($sql, $params);
    }

    public function findById(int $id): array|false {
        return $this->fetchOne(
            "SELECT r.*, p.product_name, p.brand, p.model, u.name as created_by_name,
                    CASE 
                        WHEN r.order_id IS NOT NULL THEN o.created_at 
                        WHEN r.sale_id IS NOT NULL THEN s.created_at 
                    END as purchase_date
             FROM returns r
             JOIN products p ON r.product_id=p.product_id
             JOIN users u ON r.created_by=u.id
             LEFT JOIN orders o ON r.order_id=o.order_id
             LEFT JOIN sales s ON r.sale_id=s.sale_id
             WHERE r.return_id=?",
            [$id]
        );
    }

    // Check if item can be returned (within 3 days)
    public function canReturnItem(int $orderId, int $saleId): array {
        $purchaseDate = null;
        $daysSincePurchase = null;

        if ($orderId) {
            $order = $this->fetchOne("SELECT created_at FROM orders WHERE order_id=?", [$orderId]);
            $purchaseDate = $order['created_at'] ?? null;
        } elseif ($saleId) {
            $sale = $this->fetchOne("SELECT created_at FROM sales WHERE sale_id=?", [$saleId]);
            $purchaseDate = $sale['created_at'] ?? null;
        }

        if ($purchaseDate) {
            $purchaseTs = strtotime($purchaseDate);
            $today = time();
            $daysSincePurchase = (int)(($today - $purchaseTs) / (60 * 60 * 24));
        }

        return [
            'can_return' => $daysSincePurchase !== null && $daysSincePurchase <= 3,
            'days_since_purchase' => $daysSincePurchase,
            'purchase_date' => $purchaseDate,
            'expires_at' => $purchaseDate ? date('Y-m-d', strtotime('+3 days', strtotime($purchaseDate))) : null
        ];
    }

    public function create(array $data): int|false {
        try {
            $this->db->beginTransaction();

            $return_number = $this->generateReturnNumber();

            $this->execute(
                "INSERT INTO returns 
                 (return_number, order_id, sale_id, product_id, quantity, return_reason, return_date, created_by)
                 VALUES (?,?,?,?,?,?,?,?)",
                [
                    $return_number,
                    $data['order_id'] ?? null,
                    $data['sale_id'] ?? null,
                    $data['product_id'],
                    $data['quantity'] ?? 1,
                    $data['return_reason'] ?? null,
                    $data['return_date'] ?? date('Y-m-d'),
                    $data['created_by']
                ]
            );

            $return_id = (int)$this->lastInsertId();

            $this->db->commit();
            return $return_id;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Return Create Error: " . $e->getMessage());
            return false;
        }
    }

    public function approve(int $returnId, int $approvedBy): bool {
        try {
            $this->db->beginTransaction();

            // Get return info
            $returnInfo = $this->findById($returnId);
            if (!$returnInfo) {
                throw new Exception("Return not found");
            }

            // Update return status
            $this->execute(
                "UPDATE returns SET return_status='approved', updated_by=? WHERE return_id=?",
                [$approvedBy, $returnId]
            );

            // Add quantity back to stock
            $this->execute(
                "UPDATE products SET quantity = quantity + ? WHERE product_id=?",
                [(int)$returnInfo['quantity'], (int)$returnInfo['product_id']]
            );

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Return Approve Error: " . $e->getMessage());
            return false;
        }
    }

    public function reject(int $returnId, int $rejectedBy, string $reason = ''): bool {
        try {
            $this->execute(
                "UPDATE returns SET return_status='rejected', notes=?, updated_by=? WHERE return_id=?",
                [$reason, $rejectedBy, $returnId]
            );
            return true;
        } catch (Exception $e) {
            error_log("Return Reject Error: " . $e->getMessage());
            return false;
        }
    }

    public function complete(int $returnId, int $completedBy): bool {
        try {
            $this->execute(
                "UPDATE returns SET return_status='completed', updated_by=? WHERE return_id=?",
                [$completedBy, $returnId]
            );
            return true;
        } catch (Exception $e) {
            error_log("Return Complete Error: " . $e->getMessage());
            return false;
        }
    }

    private function generateReturnNumber(): string {
        $prefix = "RTN-" . date('Y');
        $lastReturn = $this->fetchOne(
            "SELECT return_number FROM returns WHERE return_number LIKE ? ORDER BY return_id DESC LIMIT 1",
            [$prefix . "%"]
        );

        if ($lastReturn) {
            preg_match('/RTN-\d+-(\d+)/', $lastReturn['return_number'], $matches);
            $nextNum = ((int)$matches[1] ?? 0) + 1;
        } else {
            $nextNum = 1;
        }

        return $prefix . "-" . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }
}
?>
