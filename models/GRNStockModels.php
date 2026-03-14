<?php

class GRNModel extends Model {

    public function getAll(array $filters = []): array {
        $sql = "SELECT g.*, s.supplier_name, b.branch_name, u.name as created_by_name
                FROM grn g
                JOIN suppliers s ON g.supplier_id=s.supplier_id
                JOIN branches b ON g.branch_id=b.branch_id
                JOIN users u ON g.created_by=u.id";

        $params = [];
        $where = [];

        if (!empty($filters['supplier_id'])) {
            $where[] = "g.supplier_id=?";
            $params[] = $filters['supplier_id'];
        }

        if (!empty($filters['branch_id'])) {
            $where[] = "g.branch_id=?";
            $params[] = $filters['branch_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "g.grn_date >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "g.grn_date <= ?";
            $params[] = $filters['date_to'];
        }

        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY g.created_at DESC";

        return $this->fetchAll($sql, $params);
    }

    public function findById(int $id): array|false {
        return $this->fetchOne(
            "SELECT g.*, s.supplier_name, b.branch_name, u.name as created_by_name
             FROM grn g
             JOIN suppliers s ON g.supplier_id=s.supplier_id
             JOIN branches b ON g.branch_id=b.branch_id
             JOIN users u ON g.created_by=u.id
             WHERE g.grn_id=?",
            [$id]
        );
    }

    public function getItems(int $grn_id): array {
        return $this->fetchAll(
            "SELECT gi.*, p.product_name, p.brand, p.model
             FROM grn_items gi
             JOIN products p ON gi.product_id=p.product_id
             WHERE gi.grn_id=?",
            [$grn_id]
        );
    }

    public function create(array $header, array $items): int|false {
        try {

            $this->db->beginTransaction();

            $grn_number = $this->generateGRNNumber();
            $total = array_sum(array_column($items, 'total_cost'));

            $this->execute(
                "INSERT INTO grn (grn_number,supplier_id,branch_id,invoice_number,grn_date,total_amount,notes,created_by)
                 VALUES (?,?,?,?,?,?,?,?)",
                [
                    $grn_number,
                    $header['supplier_id'],
                    $header['branch_id'],
                    $header['invoice_number'],
                    $header['grn_date'],
                    $total,
                    $header['notes'] ?? '',
                    $header['created_by']
                ]
            );

            $grn_id = (int)$this->lastInsertId();

            foreach ($items as $item) {

                $this->execute(
                    "INSERT INTO grn_items (grn_id,product_id,quantity,unit_cost,total_cost)
                     VALUES (?,?,?,?,?)",
                    [
                        $grn_id,
                        $item['product_id'],
                        $item['quantity'],
                        $item['unit_cost'],
                        $item['total_cost']
                    ]
                );

                // Update product cost price
                $this->execute(
                    "UPDATE products SET cost_price=? WHERE product_id=?",
                    [$item['unit_cost'], $item['product_id']]
                );

                // Update stock
                $this->execute(
                    "INSERT INTO stock (product_id,branch_id,quantity)
                     VALUES (?,?,?)
                     ON DUPLICATE KEY UPDATE quantity=quantity+?",
                    [
                        $item['product_id'],
                        $header['branch_id'],
                        $item['quantity'],
                        $item['quantity']
                    ]
                );

                // Inventory movement
                $this->execute(
                    "INSERT INTO inventory_movements
                     (product_id,branch_id,type,quantity,reference_id,created_by)
                     VALUES (?,?,'GRN',?,?,?)",
                    [
                        $item['product_id'],
                        $header['branch_id'],
                        $item['quantity'],
                        $grn_id,
                        $header['created_by']
                    ]
                );
            }

            $this->db->commit();

            return $grn_id;

        } catch (Exception $e) {

            $this->db->rollBack();
            error_log("GRN Create Error: " . $e->getMessage());

            return false;
        }
    }

    public function generateGRNNumber(): string {

        $year = date('Y');

        $last = $this->fetchOne(
            "SELECT grn_number
             FROM grn
             WHERE grn_number LIKE 'GRN-{$year}-%'
             ORDER BY grn_id DESC
             LIMIT 1"
        );

        if ($last) {
            $num = (int)substr($last['grn_number'], -4) + 1;
        } else {
            $num = 1;
        }

        return "GRN-{$year}-" . str_pad($num, 4, '0', STR_PAD_LEFT);
    }

    public function getRecent(int $limit = 10): array {

        return $this->fetchAll(
            "SELECT g.*, s.supplier_name, b.branch_name
             FROM grn g
             JOIN suppliers s ON g.supplier_id=s.supplier_id
             JOIN branches b ON g.branch_id=b.branch_id
             ORDER BY g.created_at DESC
             LIMIT ?",
            [$limit]
        );
    }

    public function getMonthlyTotals(): array {

        return $this->fetchAll(
            "SELECT DATE_FORMAT(grn_date,'%Y-%m') as month,
                    SUM(total_amount) as total,
                    COUNT(*) as count
             FROM grn
             WHERE grn_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY month
             ORDER BY month"
        );
    }

    public function getBySupplier(int $sid): array {

        return $this->getAll(['supplier_id' => $sid]);
    }

    public function countAll(): int {

        return (int)$this->fetchOne("SELECT COUNT(*) as c FROM grn")['c'];
    }
}


/* ---------------------------------------------------------
   Sale Model (POS)
---------------------------------------------------------*/
class SaleModel extends Model {

    public function create(array $header, array $items): int|false {
        try {
            $this->db->beginTransaction();

            $sale_number = $this->generateSaleNumber();
            $total = array_sum(array_column($items, 'total_price'));

            $this->execute(
                "INSERT INTO sales (sale_number, branch_id, total_amount, notes, created_by)
                 VALUES (?,?,?,?,?)",
                [
                    $sale_number,
                    $header['branch_id'],
                    $total,
                    $header['notes'] ?? '',
                    $header['created_by']
                ]
            );

            $sale_id = (int)$this->lastInsertId();

            foreach ($items as $item) {
                $this->execute(
                    "INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, total_price)
                     VALUES (?,?,?,?,?)",
                    [
                        $sale_id,
                        $item['product_id'],
                        $item['quantity'],
                        $item['unit_price'],
                        $item['total_price']
                    ]
                );

                // Reduce stock
                $this->execute(
                    "UPDATE stock SET quantity = quantity - ?
                     WHERE product_id = ? AND branch_id = ?",
                    [
                        $item['quantity'],
                        $item['product_id'],
                        $header['branch_id']
                    ]
                );

                // Inventory movement
                $this->execute(
                    "INSERT INTO inventory_movements (product_id, branch_id, type, quantity, reference_id, created_by)
                     VALUES (?, ?, 'Sale', ?, ?, ?)",
                    [
                        $item['product_id'],
                        $header['branch_id'],
                        $item['quantity'],
                        $sale_id,
                        $header['created_by']
                    ]
                );
            }

            $this->db->commit();
            return $sale_id;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Sale Create Error: " . $e->getMessage());
            return false;
        }
    }

    // --- ADD THIS METHOD: IT WAS MISSING ---
    public function getById(int $id): array|false {
        return $this->fetchOne(
            "SELECT s.*, b.branch_name, u.name as created_by_name
             FROM sales s
             JOIN branches b ON s.branch_id = b.branch_id
             JOIN users u ON s.created_by = u.id
             WHERE s.sale_id = ?",
            [$id]
        );
    }

    public function generateSaleNumber(): string {
        $year = date('Y');
        $last = $this->fetchOne(
            "SELECT sale_number FROM sales
             WHERE sale_number LIKE 'SALE-{$year}-%'
             ORDER BY sale_id DESC LIMIT 1"
        );

        if ($last) {
            $num = (int)substr($last['sale_number'], -4) + 1;
        } else {
            $num = 1;
        }

        return "SALE-{$year}-" . str_pad($num, 4, '0', STR_PAD_LEFT);
    }

    public function getAll(array $filters = []): array {
        $sql = "SELECT s.*, b.branch_name, u.name as created_by_name
                FROM sales s
                JOIN branches b ON s.branch_id = b.branch_id
                JOIN users u ON s.created_by = u.id";

        $params = [];
        $where = [];

        if (!empty($filters['branch_id'])) {
            $where[] = "s.branch_id = ?";
            $params[] = $filters['branch_id'];
        }

        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY s.created_at DESC";
        return $this->fetchAll($sql, $params);
    }
}

/* ---------------------------------------------------------
   Stock Model
---------------------------------------------------------*/

class StockModel extends Model {

    public function getAll(array $filters = []): array {

        $sql = "SELECT st.*, p.product_name, p.brand, p.model,
                       p.reorder_level, p.cost_price, p.selling_price,
                       c.category_name, s.supplier_name, b.branch_name
                FROM stock st
                JOIN products p ON st.product_id=p.product_id
                JOIN categories c ON p.category_id=c.category_id
                JOIN suppliers s ON p.supplier_id=s.supplier_id
                JOIN branches b ON st.branch_id=b.branch_id";

        $params = [];
        $where = [];

        if (!empty($filters['branch_id'])) {
            $where[] = "st.branch_id=?";
            $params[] = $filters['branch_id'];
        }

        if (!empty($filters['supplier_id'])) {
            $where[] = "p.supplier_id=?";
            $params[] = $filters['supplier_id'];
        }

        if (!empty($filters['category_id'])) {
            $where[] = "p.category_id=?";
            $params[] = $filters['category_id'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(p.product_name LIKE ? OR p.brand LIKE ? OR p.model LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY p.product_name, b.branch_name";

        return $this->fetchAll($sql, $params);
    }

    public function getMovements(array $filters = []): array {

        $sql = "SELECT im.*, p.product_name, p.brand, b.branch_name, u.name as user_name
                FROM inventory_movements im
                JOIN products p ON im.product_id=p.product_id
                JOIN branches b ON im.branch_id=b.branch_id
                JOIN users u ON im.created_by=u.id";

        $params = [];
        $where = [];

        if (!empty($filters['product_id'])) {
            $where[] = "im.product_id=?";
            $params[] = $filters['product_id'];
        }

        if (!empty($filters['branch_id'])) {
            $where[] = "im.branch_id=?";
            $params[] = $filters['branch_id'];
        }

        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY im.created_at DESC LIMIT 200";

        return $this->fetchAll($sql, $params);
    }

    // summary by branch for dashboard charts
    public function getStockByBranch(): array {
        return $this->fetchAll("SELECT b.branch_name, SUM(st.quantity) as total_qty, 
            COUNT(DISTINCT st.product_id) as product_count
            FROM stock st JOIN branches b ON st.branch_id=b.branch_id
            GROUP BY b.branch_id ORDER BY total_qty DESC");
    }

    // summary by category for dashboard charts
    public function getStockByCategory(): array {
        return $this->fetchAll("SELECT c.category_name, SUM(st.quantity) as total_qty
            FROM stock st
            JOIN products p ON st.product_id=p.product_id
            JOIN categories c ON p.category_id=c.category_id
            GROUP BY c.category_id ORDER BY total_qty DESC");
    }
}

// ---------------------------------------------------------
// Branch order handling (branch staff)
// ---------------------------------------------------------
class BranchOrderModel extends Model {
    public function create(int $branchId, int $userId, array $items, string $notes = ''): int|false {
        try {
            $this->db->beginTransaction();
            $this->execute(
                "INSERT INTO branch_orders (branch_id,created_by,notes) VALUES (?,?,?)",
                [$branchId,$userId,$notes]
            );
            $order_id = (int)$this->lastInsertId();
            foreach($items as $it) {
                $this->execute(
                    "INSERT INTO branch_order_items (order_id,product_id,quantity) VALUES (?,?,?)",
                    [$order_id,$it['product_id'],$it['quantity']]
                );
            }
            $this->db->commit();
            return $order_id;
        } catch(Exception $e) {
            $this->db->rollBack();
            error_log("BranchOrder create error: " . $e->getMessage());
            return false;
        }
    }

    public function getByBranch(int $branchId): array {
        return $this->fetchAll(
            "SELECT o.*, u.name as requested_by
             FROM branch_orders o
             JOIN users u ON o.created_by=u.id
             WHERE o.branch_id=? ORDER BY o.created_at DESC",
            [$branchId]
        );
    }

    public function getAll(array $filters = []): array {
        // include a count of items per order for easier display
        $sql = "SELECT o.*, b.branch_name, u.name as requested_by,
                       (SELECT COUNT(*) FROM branch_order_items i WHERE i.order_id=o.order_id) as item_count
                FROM branch_orders o
                JOIN branches b ON o.branch_id=b.branch_id
                JOIN users u ON o.created_by=u.id";
        $params=[];
        $where=[];
        if (!empty($filters['status'])) { $where[]="o.status=?"; $params[]=$filters['status']; }
        if (!empty($filters['branch_id'])) { $where[]="o.branch_id=?"; $params[]=$filters['branch_id']; }
        if ($where) $sql .= " WHERE " . implode(" AND ", $where);
        $sql .= " ORDER BY o.created_at DESC";
        return $this->fetchAll($sql,$params);
    }

    public function getItems(int $orderId): array {
        return $this->fetchAll(
            "SELECT i.*, p.product_name, p.brand
             FROM branch_order_items i
             JOIN products p ON i.product_id=p.product_id
             WHERE i.order_id=?",
            [$orderId]
        );
    }

    public function updateItemStatus(int $itemId, string $status): bool {
        return (bool)$this->execute(
            "UPDATE branch_order_items SET status=? WHERE id=?",
            [$status,$itemId]
        );
    }

    public function refreshOrderStatus(int $orderId): void {
        // set order status based on items
        $row = $this->fetchOne("SELECT COUNT(*) as cnt,
            SUM(status='pending') as pend,
            SUM(status='accepted') as acc,
            SUM(status='rejected') as rej
            FROM branch_order_items WHERE order_id=?",[$orderId]);
        $new = 'pending';
        if ($row['cnt'] == 0) $new='cancelled';
        elseif ($row['pend'] == 0 && $row['rej']==0) $new='completed';
        elseif ($row['pend'] == 0) $new='partial';
        elseif ($row['acc']>0) $new='partial';
        $this->execute("UPDATE branch_orders SET status=? WHERE order_id=?",[$new,$orderId]);
    }

    // fetch a single order header by id
    public function getById(int $orderId): array|false {
        return $this->fetchOne(
            "SELECT o.*, b.branch_name, u.name as requested_by 
             FROM branch_orders o 
             JOIN branches b ON o.branch_id=b.branch_id 
             JOIN users u ON o.created_by=u.id 
             WHERE o.order_id=?",
            [$orderId]
        );
    }
}
