<?php
require_once __DIR__ . '/includes/bootstrap.php';
Session::requireSeller();

$pageTitle = 'Customers & Orders';
$activePage = 'customers';

$action = $_GET['action'] ?? '';
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $pdo = Database::getInstance();
    
    // Get all customers with order count
    $customersQuery = "SELECT 
        c.customer_id,
        c.name,
        c.email,
        c.phone,
        c.city,
        c.country,
        c.created_at,
        COUNT(o.order_id) as total_orders,
        COALESCE(SUM(o.grand_total), 0) as total_spent
    FROM customers c
    LEFT JOIN orders o ON c.customer_id = o.customer_id
    GROUP BY c.customer_id
    ORDER BY c.created_at DESC";
    
    $customers = $pdo->query($customersQuery)->fetchAll(PDO::FETCH_ASSOC);
    
    // Get detailed view if customer is selected
    $customerDetail = null;
    $customerOrders = [];
    
    if ($customer_id && $action === 'view') {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        $customerDetail = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($customerDetail) {
            // Get customer orders with items
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC");
            $stmt->execute([$customer_id]);
            $customerOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
} catch (PDOException $e) {
    error_log("Customer Query Error: " . $e->getMessage());
    $customers = [];
}

require_once __DIR__ . '/views/layouts/header.php';
?>

<?php if ($action === 'view' && $customerDetail): ?>
    <!-- CUSTOMER DETAIL VIEW -->
    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
        <a href="customers.php" class="btn-cyan" style="padding: 8px 16px; text-decoration: none;">
            <i class="bi bi-arrow-left"></i> Back to Customers
        </a>
    </div>

    <div class="content-card" style="margin-bottom: 20px;">
        <div class="content-card-header">
            <h2>Customer Details</h2>
        </div>
        <div class="content-card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px;">
                <div>
                    <label style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">Full Name</label>
                    <div style="font-size: 18px; font-weight: 600; margin-top: 8px; color: var(--text-primary);">
                        <?= Helper::e($customerDetail['name']) ?>
                    </div>
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">Email</label>
                    <div style="font-size: 16px; margin-top: 8px; color: var(--text-primary);">
                        <a href="mailto:<?= Helper::e($customerDetail['email']) ?>" style="color: var(--cyan); text-decoration: none;">
                            <?= Helper::e($customerDetail['email']) ?>
                        </a>
                    </div>
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">Phone</label>
                    <div style="font-size: 16px; margin-top: 8px; color: var(--text-primary);">
                        <?= Helper::e($customerDetail['phone'] ?? 'N/A') ?>
                    </div>
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">City</label>
                    <div style="font-size: 16px; margin-top: 8px; color: var(--text-primary);">
                        <?= Helper::e($customerDetail['city'] ?? 'N/A') ?>
                    </div>
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">Country</label>
                    <div style="font-size: 16px; margin-top: 8px; color: var(--text-primary);">
                        <?= Helper::e($customerDetail['country'] ?? 'N/A') ?>
                    </div>
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">Member Since</label>
                    <div style="font-size: 16px; margin-top: 8px; color: var(--text-primary);">
                        <?= date('M d, Y', strtotime($customerDetail['created_at'])) ?>
                    </div>
                </div>
            </div>
            <?php if ($customerDetail['address']): ?>
                <div style="margin-top: 30px; padding-top: 30px; border-top: 1px solid var(--navy-border);">
                    <label style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">Address</label>
                    <div style="font-size: 14px; margin-top: 8px; color: var(--text-primary); line-height: 1.6;">
                        <?= nl2br(Helper::e($customerDetail['address'])) ?>
                        <?php if ($customerDetail['postal_code']): ?>
                            <br><strong>Postal Code:</strong> <?= Helper::e($customerDetail['postal_code']) ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- CUSTOMER ORDERS -->
    <div class="content-card">
        <div class="content-card-header">
            <h2>Purchase History (<?= count($customerOrders) ?> orders)</h2>
        </div>
        <div class="content-card-body">
            <?php if (count($customerOrders) > 0): ?>
                <table class="table-dark-custom w-100">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Subtotal</th>
                            <th>Tax</th>
                            <th>Shipping</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Payment Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customerOrders as $order): ?>
                            <tr>
                                <td style="color: var(--cyan); font-weight: 600;">#<?= $order['order_id'] ?></td>
                                <td><?= date('M d, Y h:i A', strtotime($order['created_at'])) ?></td>
                                <td style="text-align: center;"><?= $order['item_count'] ?></td>
                                <td>Rs. <?= number_format($order['total_amount'], 2) ?></td>
                                <td>Rs. <?= number_format($order['tax'], 2) ?></td>
                                <td><?= $order['shipping'] == 0 ? 'FREE' : 'Rs. ' . number_format($order['shipping'], 2) ?></td>
                                <td style="font-weight: 600; color: var(--green);">Rs. <?= number_format($order['grand_total'], 2) ?></td>
                                <td>
                                    <span style="padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600;
                                        <?php
                                            $statusColors = [
                                                'pending' => 'background-color: var(--gold); color: var(--text-primary);',
                                                'processing' => 'background-color: var(--cyan); color: var(--navy-dark);',
                                                'completed' => 'background-color: var(--green); color: white;',
                                                'cancelled' => 'background-color: #d32f2f; color: white;'
                                            ];
                                            echo $statusColors[$order['status']] ?? '';
                                        ?>
                                    ">
                                        <?= ucfirst($order['status']) ?>
                                    </span>
                                </td>
                                <td><?= Helper::e($order['payment_method'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <p><i class="bi bi-inbox" style="font-size: 32px; display: block; margin-bottom: 10px;"></i></p>
                    <p>No orders yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- CUSTOMERS LIST VIEW -->
    <div class="page-header">
        <div>
            <div class="page-header-title">Customers & Orders</div>
            <div class="page-header-sub"><?= count($customers) ?> customers registered</div>
        </div>
    </div>

    <div class="content-card">
        <div class="content-card-body">
            <?php if (count($customers) > 0): ?>
                <table class="table-dark-custom datatable w-100">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>City</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Member Since</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $i => $customer): ?>
                            <tr>
                                <td style="color: var(--text-muted)"><?= $i + 1 ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="width: 30px; height: 30px; background: var(--navy-light); border: 1px solid var(--navy-border); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: var(--cyan);">
                                            <?= strtoupper(substr($customer['name'], 0, 2)) ?>
                                        </div>
                                        <strong><?= Helper::e($customer['name']) ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <a href="mailto:<?= Helper::e($customer['email']) ?>" style="color: var(--cyan); text-decoration: none;">
                                        <?= Helper::e($customer['email']) ?>
                                    </a>
                                </td>
                                <td><?= Helper::e($customer['phone'] ?? '-') ?></td>
                                <td><?= Helper::e($customer['city'] ?? '-') ?></td>
                                <td style="text-align: center; font-weight: 600;">
                                    <span style="background: var(--navy-light); padding: 4px 10px; border-radius: 4px; color: var(--cyan);">
                                        <?= $customer['total_orders'] ?>
                                    </span>
                                </td>
                                <td style="color: var(--green); font-weight: 600;">Rs. <?= number_format($customer['total_spent'], 2) ?></td>
                                <td style="font-size: 13px; color: var(--text-muted);"><?= date('M d, Y', strtotime($customer['created_at'])) ?></td>
                                <td>
                                    <a href="customers.php?action=view&id=<?= $customer['customer_id'] ?>" class="btn-small-cyan" style="padding: 6px 12px; font-size: 12px; text-decoration: none; display: inline-block;">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 60px; color: var(--text-muted);">
                    <p><i class="bi bi-people" style="font-size: 48px; display: block; margin-bottom: 10px;"></i></p>
                    <p>No customers yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<style>
    .btn-small-cyan {
        background: var(--cyan);
        color: var(--navy-dark);
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: opacity 0.2s;
    }
    .btn-small-cyan:hover {
        opacity: 0.8;
    }
</style>

<?php require_once __DIR__ . '/views/layouts/footer.php'; ?>
