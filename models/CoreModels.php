<?php
class BranchModel extends Model {
    public function getAll(): array {
        return $this->fetchAll("SELECT * FROM branches ORDER BY branch_name");
    }
    public function getActive(): array {
        return $this->fetchAll("SELECT * FROM branches WHERE status='active' ORDER BY branch_name");
    }
    public function findById(int $id): array|false {
        return $this->fetchOne("SELECT * FROM branches WHERE branch_id=?", [$id]);
    }
    public function create(array $d): int {
        $this->execute("INSERT INTO branches (branch_name,address,phone,manager_name,status) VALUES (?,?,?,?,?)",
            [$d['branch_name'],$d['address'],$d['phone'],$d['manager_name'],$d['status']??'active']);
        return (int)$this->lastInsertId();
    }
    public function update(int $id, array $d): bool {
        return (bool)$this->execute("UPDATE branches SET branch_name=?,address=?,phone=?,manager_name=?,status=? WHERE branch_id=?",
            [$d['branch_name'],$d['address'],$d['phone'],$d['manager_name'],$d['status'],$id]);
    }
    public function delete(int $id): bool {
        return (bool)$this->execute("DELETE FROM branches WHERE branch_id=?", [$id]);
    }
    public function count(): int {
        return (int)$this->fetchOne("SELECT COUNT(*) as c FROM branches WHERE status='active'")['c'];
    }
}

class SupplierModel extends Model {
    public function getAll(): array {
        return $this->fetchAll("SELECT * FROM suppliers ORDER BY supplier_name");
    }
    public function getActive(): array {
        return $this->fetchAll("SELECT * FROM suppliers WHERE status='active' ORDER BY supplier_name");
    }
    public function findById(int $id): array|false {
        return $this->fetchOne("SELECT * FROM suppliers WHERE supplier_id=?", [$id]);
    }
    public function create(array $d): int {
        $this->execute("INSERT INTO suppliers (supplier_name,contact_person,phone,email,address,company_registration,status) VALUES (?,?,?,?,?,?,?)",
            [$d['supplier_name'],$d['contact_person'],$d['phone'],$d['email'],$d['address'],$d['company_registration'],$d['status']??'active']);
        return (int)$this->lastInsertId();
    }
    public function update(int $id, array $d): bool {
        return (bool)$this->execute("UPDATE suppliers SET supplier_name=?,contact_person=?,phone=?,email=?,address=?,company_registration=?,status=? WHERE supplier_id=?",
            [$d['supplier_name'],$d['contact_person'],$d['phone'],$d['email'],$d['address'],$d['company_registration'],$d['status'],$id]);
    }
    public function delete(int $id): bool {
        return (bool)$this->execute("DELETE FROM suppliers WHERE supplier_id=?", [$id]);
    }
    public function count(): int {
        return (int)$this->fetchOne("SELECT COUNT(*) as c FROM suppliers WHERE status='active'")['c'];
    }
    public function getSupplierStats(int $sid): array {
        $total_products = $this->fetchOne("SELECT COUNT(*) as c FROM products WHERE supplier_id=?",[$sid])['c'];
        $total_grns = $this->fetchOne("SELECT COUNT(*) as c FROM grn WHERE supplier_id=?",[$sid])['c'];
        $total_value = $this->fetchOne("SELECT COALESCE(SUM(total_amount),0) as v FROM grn WHERE supplier_id=?",[$sid])['v'];
        return ['total_products'=>$total_products,'total_grns'=>$total_grns,'total_value'=>$total_value];
    }
}

class CategoryModel extends Model {
    public function getAll(): array {
        return $this->fetchAll("SELECT c.*, COUNT(p.product_id) as product_count FROM categories c 
            LEFT JOIN products p ON c.category_id=p.category_id GROUP BY c.category_id ORDER BY c.category_name");
    }
    public function findById(int $id): array|false {
        return $this->fetchOne("SELECT * FROM categories WHERE category_id=?", [$id]);
    }
    public function create(array $d): int {
        $this->execute("INSERT INTO categories (category_name,description) VALUES (?,?)", [$d['category_name'],$d['description']]);
        return (int)$this->lastInsertId();
    }
    public function update(int $id, array $d): bool {
        return (bool)$this->execute("UPDATE categories SET category_name=?,description=? WHERE category_id=?",
            [$d['category_name'],$d['description'],$id]);
    }
    public function delete(int $id): bool {
        return (bool)$this->execute("DELETE FROM categories WHERE category_id=?", [$id]);
    }
}

class ProductModel extends Model {
    public function getAll(array $filters = []): array {
        $sql = "SELECT p.*, c.category_name, s.supplier_name, 
                COALESCE(SUM(st.quantity),0) as total_stock
                FROM products p
                LEFT JOIN categories c ON p.category_id=c.category_id
                LEFT JOIN suppliers s ON p.supplier_id=s.supplier_id
                LEFT JOIN stock st ON p.product_id=st.product_id";
        $params = [];
        $where = [];
        if (!empty($filters['supplier_id'])) { $where[] = "p.supplier_id=?"; $params[] = $filters['supplier_id']; }
        if (!empty($filters['category_id'])) { $where[] = "p.category_id=?"; $params[] = $filters['category_id']; }
        if (!empty($filters['brand'])) { $where[] = "p.brand=?"; $params[] = $filters['brand']; }
        if (!empty($filters['search'])) { $where[] = "(p.product_name LIKE ? OR p.brand LIKE ? OR p.model LIKE ?)"; $params[] = "%{$filters['search']}%"; $params[] = "%{$filters['search']}%"; $params[] = "%{$filters['search']}%"; }
        if ($where) $sql .= " WHERE " . implode(" AND ", $where);
        $sql .= " GROUP BY p.product_id ORDER BY p.product_name";
        return $this->fetchAll($sql, $params);
    }

    public function findById(int $id): array|false {
        return $this->fetchOne("SELECT p.*, c.category_name, s.supplier_name FROM products p
            LEFT JOIN categories c ON p.category_id=c.category_id
            LEFT JOIN suppliers s ON p.supplier_id=s.supplier_id
            WHERE p.product_id=?", [$id]);
    }

    public function create(array $d): int {
        $this->execute("INSERT INTO products (product_name,brand,model,category_id,supplier_id,cost_price,selling_price,reorder_level,status) VALUES (?,?,?,?,?,?,?,?,?)",
            [$d['product_name'],$d['brand'],$d['model'],$d['category_id'],$d['supplier_id'],
             $d['cost_price'],$d['selling_price'],$d['reorder_level'],$d['status']??'active']);
        return (int)$this->lastInsertId();
    }

    public function update(int $id, array $d): bool {
        return (bool)$this->execute("UPDATE products SET product_name=?,brand=?,model=?,category_id=?,supplier_id=?,cost_price=?,selling_price=?,reorder_level=?,status=? WHERE product_id=?",
            [$d['product_name'],$d['brand'],$d['model'],$d['category_id'],$d['supplier_id'],
             $d['cost_price'],$d['selling_price'],$d['reorder_level'],$d['status'],$id]);
    }

    public function delete(int $id): bool {
        return (bool)$this->execute("DELETE FROM products WHERE product_id=?", [$id]);
    }

    public function count(): int {
        return (int)$this->fetchOne("SELECT COUNT(*) as c FROM products WHERE status='active'")['c'];
    }

    public function getLowStock(): array {
        return $this->fetchAll("SELECT p.*, c.category_name, s.supplier_name, 
            COALESCE(SUM(st.quantity),0) as total_stock
            FROM products p
            LEFT JOIN categories c ON p.category_id=c.category_id
            LEFT JOIN suppliers s ON p.supplier_id=s.supplier_id
            LEFT JOIN stock st ON p.product_id=st.product_id
            WHERE p.status='active'
            GROUP BY p.product_id
            HAVING total_stock <= p.reorder_level
            ORDER BY total_stock ASC");
    }

    public function getBrands(): array {
        return $this->fetchAll("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand!='' ORDER BY brand");
    }

    public function getBySupplier(int $sid): array {
        return $this->getAll(['supplier_id' => $sid]);
    }

    public function totalStockValue(): float {
        $r = $this->fetchOne("SELECT COALESCE(SUM(p.cost_price * st.quantity),0) as v 
            FROM stock st JOIN products p ON st.product_id=p.product_id");
        return (float)$r['v'];
    }

    public function totalStockQuantity(): int {
        $r = $this->fetchOne("SELECT COALESCE(SUM(quantity),0) as v FROM stock");
        return (int)$r['v'];
    }
}
