<?php
require_once __DIR__ . '/includes/bootstrap.php';
Session::requireSeller();

$pageTitle = 'Advanced Reports';
$activePage = 'reports';

// Handle exports
if ($_GET['export'] ?? false) {
    $exportType = $_GET['export'];
    $reportType = $_GET['type'] ?? 'stock';
    
    if ($exportType === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $reportType . '_report_' . date('Y-m-d_His') . '.csv"');
        // CSV export will be handled below
    } elseif ($exportType === 'pdf') {
        // PDF export would require a library like TCPDF or similar
        // For now we'll note it in the UI
    }
}

$reportType = $_GET['type'] ?? 'stock';
$dateFrom   = $_GET['date_from'] ?? date('Y-m-01');
$dateTo     = $_GET['date_to'] ?? date('Y-m-d');
$branchId   = (int)($_GET['branch_id'] ?? 0);
$supplierId = (int)($_GET['supplier_id'] ?? 0);
$productId  = (int)($_GET['product_id'] ?? 0);

$db = Database::getInstance();
$branchModel   = new BranchModel();
$supplierModel = new SupplierModel();
$branches      = $branchModel->getActive();
$suppliers     = $supplierModel->getActive();

// Report data
$data = [];
$summaryStats = [];
$detailedBreakdown = [];

switch ($reportType) {
    case 'daily_sales':
        $sql = "SELECT DATE(s.created_at) as sale_date, COUNT(s.sale_id) as total_transactions,
                COALESCE(SUM(s.total_amount),0) as total_sales, b.branch_name, b.branch_id,
                MIN(s.total_amount) as min_sale, MAX(s.total_amount) as max_sale,
                COALESCE(AVG(s.total_amount),0) as avg_sale,
                COUNT(DISTINCT s.created_by) as staff_count
                FROM sales s
                JOIN branches b ON s.branch_id=b.branch_id
                WHERE s.created_at BETWEEN ? AND ? " .
                ($branchId ? "AND s.branch_id=$branchId " : "") .
                "GROUP BY DATE(s.created_at), s.branch_id
                ORDER BY s.created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
        $data = $stmt->fetchAll();
        
        // Detailed product breakdown per day
        $productSQL = "SELECT DATE(s.created_at) as sale_date, p.product_name, p.brand,
                      SUM(si.quantity) as qty_sold, SUM(si.total_price) as revenue,
                      ROUND(SUM(si.total_price)/SUM(si.quantity),2) as avg_price_per_unit
                      FROM sales s
                      JOIN sale_items si ON s.sale_id=si.sale_id
                      JOIN products p ON si.product_id=p.product_id
                      WHERE s.created_at BETWEEN ? AND ? " .
                      ($branchId ? "AND s.branch_id=$branchId " : "") .
                      "GROUP BY DATE(s.created_at), p.product_id
                      ORDER BY DATE(s.created_at) DESC, revenue DESC";
        $stmt = $db->prepare($productSQL);
        $stmt->execute([$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
        $detailedBreakdown = $stmt->fetchAll();
        
        $summaryStats = [
            'total_sales' => array_sum(array_column($data, 'total_sales')),
            'total_transactions' => array_sum(array_column($data, 'total_transactions')),
            'avg_transaction' => count($data) > 0 ? array_sum(array_column($data, 'total_sales')) / max(1, array_sum(array_column($data, 'total_transactions'))) : 0,
            'best_day' => $data[0] ?? [],
            'days_count' => count($data),
            'avg_daily' => count($data) > 0 ? array_sum(array_column($data, 'total_sales')) / count($data) : 0
        ];
        break;

    case 'reorder_level':
        $sql = "SELECT p.product_id, p.product_name, p.brand, p.model, c.category_name,
                p.reorder_level, p.supplier_id, s.supplier_name, s.contact_person, s.phone,
                b.branch_id, b.branch_name, COALESCE(st.quantity,0) as current_stock,
                p.cost_price, p.selling_price,
                CASE WHEN COALESCE(st.quantity,0) <= p.reorder_level THEN 'URGENT' 
                     WHEN COALESCE(st.quantity,0) <= (p.reorder_level * 1.5) THEN 'LOW' 
                     ELSE 'OK' END as status,
                ROUND(((COALESCE(st.quantity,0) - p.reorder_level) / p.reorder_level * 100), 2) as stock_variance_percent
                FROM products p
                JOIN categories c ON p.category_id=c.category_id
                JOIN suppliers s ON p.supplier_id=s.supplier_id
                LEFT JOIN stock st ON p.product_id=st.product_id " .
                ($branchId ? "AND st.branch_id=$branchId " : "") .
                "LEFT JOIN branches b ON st.branch_id=b.branch_id
                WHERE (COALESCE(st.quantity,0) <= p.reorder_level OR st.product_id IS NULL)
                ORDER BY COALESCE(st.quantity,0) ASC, p.product_name";
        $data = $db->query($sql)->fetchAll();
        
        // Supplier impact summary
        $supplierImpact = [];
        foreach ($data as $item) {
            $suppId = $item['supplier_id'];
            if (!isset($supplierImpact[$suppId])) {
                $supplierImpact[$suppId] = [
                    'supplier_name' => $item['supplier_name'],
                    'contact' => $item['contact_person'],
                    'phone' => $item['phone'],
                    'urgent_items' => 0,
                    'low_items' => 0,
                    'total_value' => 0
                ];
            }
            if ($item['status'] === 'URGENT') $supplierImpact[$suppId]['urgent_items']++;
            if ($item['status'] === 'LOW') $supplierImpact[$suppId]['low_items']++;
            $supplierImpact[$suppId]['total_value'] += $item['current_stock'] * $item['cost_price'];
        }
        
        $summaryStats = [
            'urgent_count' => count(array_filter($data, fn($d) => $d['status'] === 'URGENT')),
            'low_count' => count(array_filter($data, fn($d) => $d['status'] === 'LOW')),
            'total_items' => count($data),
            'affected_suppliers' => count(array_unique(array_column($data, 'supplier_id'))),
            'total_value_at_risk' => array_sum(array_column($data, 'current_stock')) * array_sum(array_column($data, 'cost_price')) / count($data),
            'supplier_impact' => array_slice($supplierImpact, 0, 5)
        ];
        break;

    case 'branch_sales':
        $sql = "SELECT b.branch_id, b.branch_name, b.city, b.contact_person, b.phone,
                COUNT(DISTINCT s.sale_id) as total_sales, COUNT(DISTINCT DATE(s.created_at)) as transaction_days,
                COALESCE(SUM(s.total_amount),0) as total_revenue,
                COUNT(DISTINCT si.product_id) as unique_products_sold,
                COUNT(DISTINCT s.created_by) as staff_active,
                COALESCE(AVG(s.total_amount),0) as avg_sale_amount,
                MIN(s.total_amount) as min_sale,
                MAX(s.total_amount) as max_sale,
                COALESCE(MAX(s.created_at),'-') as last_sale
                FROM branches b
                LEFT JOIN sales s ON b.branch_id=s.branch_id AND s.created_at BETWEEN ? AND ?
                LEFT JOIN sale_items si ON s.sale_id=si.sale_id
                " . ($branchId ? "WHERE b.branch_id=$branchId" : "") . "
                GROUP BY b.branch_id
                ORDER BY total_revenue DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
        $data = $stmt->fetchAll();
        
        // Top products per branch
        $topProductsSQL = "SELECT b.branch_id, b.branch_name, p.product_name, p.brand,
                          SUM(si.quantity) as qty, SUM(si.total_price) as revenue
                          FROM branches b
                          LEFT JOIN sales s ON b.branch_id=s.branch_id AND s.created_at BETWEEN ? AND ?
                          LEFT JOIN sale_items si ON s.sale_id=si.sale_id
                          LEFT JOIN products p ON si.product_id=p.product_id
                          " . ($branchId ? "WHERE b.branch_id=$branchId" : "") . "
                          GROUP BY b.branch_id, p.product_id
                          ORDER BY b.branch_id, revenue DESC";
        $stmt = $db->prepare($topProductsSQL);
        $stmt->execute([$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
        $detailedBreakdown = $stmt->fetchAll();
        
        $summaryStats = [
            'total_branches' => count($data),
            'total_revenue' => array_sum(array_column($data, 'total_revenue')),
            'total_transactions' => array_sum(array_column($data, 'total_sales')),
            'avg_branch_revenue' => count($data) > 0 ? array_sum(array_column($data, 'total_revenue')) / count($data) : 0,
            'best_branch' => $data[0] ?? [],
            'avg_transaction' => array_sum(array_column($data, 'total_sales')) > 0 ? 
                                 array_sum(array_column($data, 'total_revenue')) / array_sum(array_column($data, 'total_sales')) : 0,
            'staff_count' => count(array_unique(array_column($data, 'staff_active')))
        ];
        break;

    case 'stock':
        $sql = "SELECT p.product_name, p.brand, p.model, c.category_name, s.supplier_name,
                COALESCE(SUM(st.quantity),0) as total_qty, p.cost_price, p.selling_price, p.reorder_level,
                COALESCE(SUM(st.quantity * p.cost_price),0) as stock_value,
                COALESCE(SUM(st.quantity * p.selling_price),0) as potential_revenue,
                ROUND((SUM(st.quantity * p.selling_price) - SUM(st.quantity * p.cost_price)), 2) as potential_profit,
                COUNT(DISTINCT st.branch_id) as branches_stocked
                FROM products p
                LEFT JOIN categories c ON p.category_id=c.category_id
                LEFT JOIN suppliers s ON p.supplier_id=s.supplier_id
                LEFT JOIN stock st ON p.product_id=st.product_id " .
                ($branchId ? "AND st.branch_id=$branchId " : "") . "
                GROUP BY p.product_id ORDER BY stock_value DESC";
        $data = $db->query($sql)->fetchAll();
        
        $summaryStats = [
            'total_items' => count($data),
            'total_stock_value' => array_sum(array_column($data, 'stock_value')),
            'potential_revenue' => array_sum(array_column($data, 'potential_revenue')),
            'total_profit_margin' => array_sum(array_column($data, 'potential_profit')),
            'avg_stock_per_item' => count($data) > 0 ? array_sum(array_column($data, 'total_qty')) / count($data) : 0
        ];
        break;

    case 'supplier':
        $sql = "SELECT s.supplier_name, s.contact_person, s.phone, s.email, s.address,
                COUNT(DISTINCT p.product_id) as product_count,
                COUNT(DISTINCT g.grn_id) as grn_count,
                COALESCE(SUM(g.total_amount),0) as total_purchased,
                AVG(g.total_amount) as avg_grn_amount,
                MIN(g.grn_date) as first_purchase,
                MAX(g.grn_date) as last_purchase,
                DATEDIFF(NOW(), MAX(g.grn_date)) as days_since_purchase
                FROM suppliers s
                LEFT JOIN products p ON s.supplier_id=p.supplier_id
                LEFT JOIN grn g ON s.supplier_id=g.supplier_id AND g.grn_date BETWEEN ? AND ?
                GROUP BY s.supplier_id ORDER BY total_purchased DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$dateFrom, $dateTo]);
        $data = $stmt->fetchAll();
        
        $summaryStats = [
            'active_suppliers' => count($data),
            'total_purchased' => array_sum(array_column($data, 'total_purchased')),
            'avg_supplier_value' => count($data) > 0 ? array_sum(array_column($data, 'total_purchased')) / count($data) : 0,
            'top_supplier' => $data[0] ?? []
        ];
        break;

    case 'grn':
        $sql = "SELECT g.grn_number, s.supplier_name, b.branch_name, g.invoice_number,
                g.grn_date, g.total_amount, u.name as created_by,
                COUNT(gi.grn_item_id) as line_items
                FROM grn g
                JOIN suppliers s ON g.supplier_id=s.supplier_id
                JOIN branches b ON g.branch_id=b.branch_id
                JOIN users u ON g.created_by=u.id
                LEFT JOIN grn_items gi ON g.grn_id=gi.grn_id
                WHERE g.grn_date BETWEEN ? AND ?" .
                ($supplierId ? " AND g.supplier_id=$supplierId" : "") .
                ($branchId ? " AND g.branch_id=$branchId" : "") .
                " GROUP BY g.grn_id ORDER BY g.grn_date DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$dateFrom, $dateTo]);
        $data = $stmt->fetchAll();
        
        $summaryStats = [
            'total_grns' => count($data),
            'total_amount' => array_sum(array_column($data, 'total_amount')),
            'avg_grn_amount' => count($data) > 0 ? array_sum(array_column($data, 'total_amount')) / count($data) : 0,
            'total_items' => array_sum(array_column($data, 'line_items'))
        ];
        break;

    case 'low_stock':
        $sql = "SELECT p.*, c.category_name, s.supplier_name, 
                COALESCE(SUM(st.quantity),0) as total_stock, p.reorder_level as reorder_qty
                FROM products p
                LEFT JOIN categories c ON p.category_id=c.category_id
                LEFT JOIN suppliers s ON p.supplier_id=s.supplier_id
                LEFT JOIN stock st ON p.product_id=st.product_id
                WHERE p.status='active'
                GROUP BY p.product_id
                HAVING total_stock <= p.reorder_level
                ORDER BY total_stock ASC";
        $data = $db->query($sql)->fetchAll();
        
        $summaryStats = [
            'low_stock_items' => count($data),
            'total_qty_low' => array_sum(array_column($data, 'total_stock')),
            'total_reorder_qty' => array_sum(array_column($data, 'reorder_qty'))
        ];
        break;
}

require_once __DIR__ . '/views/layouts/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
    .report-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .export-buttons { display: flex; gap: 8px; }
    .export-btn { padding: 8px 16px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; font-size: 12px; transition: 0.3s; }
    .export-btn-csv { background: #10b981; color: white; }
    .export-btn-csv:hover { background: #059669; }
    .export-btn-pdf { background: #ef4444; color: white; }
    .export-btn-pdf:hover { background: #dc2626; }
    .export-btn-excel { background: #3b82f6; color: white; }
    .export-btn-excel:hover { background: #2563eb; }
    .metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin: 20px 0; }
    .metric-card { background: linear-gradient(135deg, rgba(56,189,248,0.1), rgba(56,189,248,0.05)); padding: 20px; border-radius: 14px; border: 1px solid rgba(56,189,248,0.2); }
    .metric-label { font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 8px; font-weight: 700; }
    .metric-value { font-size: 28px; font-weight: 800; color: #38bdf8; margin-bottom: 6px; }
    .metric-change { font-size: 12px; color: #22c55e; }
    .breakdown-section { margin-top: 32px; }
    .breakdown-title { font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid var(--navy-border); }
    .chart-container { background: var(--navy-card); padding: 20px; border-radius: 14px; margin: 20px 0; border: 1px solid var(--navy-border); position: relative; height: 320px; }
    .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin: 20px 0; }
    @media (max-width: 768px) { .charts-grid { grid-template-columns: 1fr; } .chart-container { height: 280px; } }
</style>

<div class="report-header">
    <div>
        <h1 style="margin: 0; font-size: 28px; font-weight: 800;">Advanced Reports & Analytics</h1>
        <p style="margin: 4px 0 0 0; color: var(--text-muted); font-size: 14px;">Comprehensive business intelligence dashboard</p>
    </div>
    <div class="export-buttons">
        <a href="?type=<?= $reportType ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>&branch_id=<?= $branchId ?>&export=csv" class="export-btn export-btn-csv"><i class="bi bi-cloud-download"></i> CSV</a>
        <a href="?type=<?= $reportType ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>&branch_id=<?= $branchId ?>&export=pdf" class="export-btn export-btn-pdf"><i class="bi bi-file-pdf"></i> PDF</a>
        <button class="export-btn export-btn-excel" onclick="exportToExcel()"><i class="bi bi-file-earmark-excel"></i> Excel</button>
        <button class="btn-cyan" onclick="window.print()"><i class="bi bi-printer-fill"></i> Print</button>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php
    $reportTypes = [
        'stock'       => ['icon'=>'bi-archive-fill','label'=>'Stock Report','color'=>'cyan'],
        'grn'         => ['icon'=>'bi-receipt-cutoff','label'=>'GRN Report','color'=>'green'],
        'low_stock'   => ['icon'=>'bi-exclamation-triangle-fill','label'=>'Low Stock Alert','color'=>'orange'],
        'supplier'    => ['icon'=>'bi-truck','label'=>'Supplier Report','color'=>'purple'],
        'daily_sales' => ['icon'=>'bi-graph-up-arrow','label'=>'Daily Sales','color'=>'success'],
        'reorder_level' => ['icon'=>'bi-exclamation-diamond-fill','label'=>'Reorder Level','color'=>'warning'],
        'branch_sales' => ['icon'=>'bi-shop','label'=>'Branch Performance','color'=>'info'],
    ];
    foreach ($reportTypes as $type => $info):
        $active = $reportType === $type;
    ?>
    <div class="col-6 col-md-3">
        <a href="?type=<?= $type ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>"
           class="stat-card" style="<?= $active ? 'border-color:var(--cyan);background:var(--cyan-dim);' : '' ?>">
            <div class="stat-icon <?= $info['color'] ?>"><i class="bi <?= $info['icon'] ?>"></i></div>
            <div>
                <div style="font-weight:700;font-size:13px;"><?= $info['label'] ?></div>
                <?php if($active): ?><div style="font-size:11px;color:var(--cyan);">Currently viewing</div><?php endif; ?>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="content-card mb-4">
    <form method="GET" class="filter-bar">
        <input type="hidden" name="type" value="<?= Helper::e($reportType) ?>">
        <?php if(in_array($reportType,['daily_sales','branch_sales','reorder_level','grn','supplier'])): ?>
        <input type="date" name="date_from" class="form-control-dark" value="<?= Helper::e($dateFrom) ?>" style="width:140px;" title="Start Date">
        <input type="date" name="date_to" class="form-control-dark" value="<?= Helper::e($dateTo) ?>" style="width:140px;" title="End Date">
        <?php endif; ?>
        <?php if(in_array($reportType,['stock','grn','daily_sales','branch_sales','reorder_level'])): ?>
        <select name="branch_id" class="form-select-dark" style="width:160px;">
            <option value="">All Branches</option>
            <?php foreach($branches as $b): ?>
            <option value="<?= $b['branch_id'] ?>" <?= $branchId==$b['branch_id']?'selected':'' ?>><?= Helper::e($b['branch_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <?php if($reportType==='grn'): ?>
        <select name="supplier_id" class="form-select-dark" style="width:160px;">
            <option value="">All Suppliers</option>
            <?php foreach($suppliers as $s): ?>
            <option value="<?= $s['supplier_id'] ?>" <?= $supplierId==$s['supplier_id']?'selected':'' ?>><?= Helper::e($s['supplier_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <button type="submit" class="btn-cyan"><i class="bi bi-play-fill"></i> Generate</button>
        <a href="?type=<?= $reportType ?>" class="btn-ghost" style="margin-left:auto;"><i class="bi bi-arrow-clockwise"></i> Reset</a>
    </form>
</div>

<!-- Report Output -->
<div class="content-card">
    <div class="content-card-header">
        <div>
            <div class="content-card-title"><i class="bi bi-table"></i> <?= $reportTypes[$reportType]['label'] ?> — <?= count($data) ?> records</div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Generated: <?= date('d M Y, h:i A') ?> | Period: <?= Helper::formatDate($dateFrom) ?> to <?= Helper::formatDate($dateTo) ?></div>
        </div>
        <button class="btn-cyan" onclick="window.print()" style="margin-left:auto;"><i class="bi bi-printer-fill"></i> Print</button>
    </div>

    <!-- Summary Stats Section -->
    <?php if($reportType === 'daily_sales' && !empty($summaryStats)): ?>
    <div class="metric-grid">
        <div class="metric-card">
            <div class="metric-label">💰 Total Revenue</div>
            <div class="metric-value"><?= Helper::formatCurrency($summaryStats['total_sales']) ?></div>
            <div class="metric-change">📊 <?= $summaryStats['days_count'] ?> days of sales data</div>
        </div>
        <div class="metric-card" style="background: linear-gradient(135deg, rgba(34,197,94,0.1), rgba(34,197,94,0.05)); border-color: rgba(34,197,94,0.2);">
            <div class="metric-label" style="color: #22c55e;">📦 Transactions</div>
            <div style="font-size:28px;font-weight:800;color:#22c55e;"><?= number_format($summaryStats['total_transactions']) ?></div>
            <div class="metric-change" style="color: var(--text-muted);">Avg <?= number_format($summaryStats['total_transactions'] / max(1, $summaryStats['days_count'])) ?> per day</div>
        </div>
        <div class="metric-card" style="background: linear-gradient(135deg, rgba(168,85,247,0.1), rgba(168,85,247,0.05)); border-color: rgba(168,85,247,0.2);">
            <div class="metric-label" style="color: #a855f7;">💵 Per Transaction</div>
            <div style="font-size:28px;font-weight:800;color:#a855f7;"><?= Helper::formatCurrency($summaryStats['avg_transaction']) ?></div>
            <div class="metric-change" style="color: var(--text-muted);">Daily Avg: <?= Helper::formatCurrency($summaryStats['avg_daily']) ?></div>
        </div>
        <div class="metric-card" style="background: linear-gradient(135deg, rgba(59,130,246,0.1), rgba(59,130,246,0.05)); border-color: rgba(59,130,246,0.2);">
            <div class="metric-label" style="color: #3b82f6;">🏆 Best Day</div>
            <div style="font-size:24px;font-weight:800;color:#3b82f6;"><?= Helper::formatCurrency($summaryStats['best_day']['total_sales'] ?? 0) ?></div>
            <div class="metric-change" style="color: var(--text-muted);">📅 <?= Helper::formatDate($summaryStats['best_day']['sale_date'] ?? date('Y-m-d')) ?></div>
        </div>
    </div>

    <!-- Top Products Breakdown -->
    <?php if(!empty($detailedBreakdown)): ?>
    <div class="breakdown-section">
        <div class="content-card">
            <div class="breakdown-title">Top Products by Revenue</div>
            <div style="overflow-x:auto;">
                <table class="table-dark-custom w-100">
                    <thead><tr><th>Date</th><th>Product</th><th>Brand</th><th>Qty Sold</th><th>Revenue</th><th>Avg Price</th></tr></thead>
                    <tbody>
                    <?php foreach(array_slice($detailedBreakdown, 0, 20) as $row): ?>
                    <tr>
                        <td style="font-size:12px;color:var(--text-muted)"><?= Helper::formatDate($row['sale_date']) ?></td>
                        <td><strong><?= Helper::e($row['product_name']) ?></strong></td>
                        <td><?= Helper::e($row['brand']) ?></td>
                        <td><span style="background:rgba(56,189,248,0.15);padding:4px 8px;border-radius:6px;"><?= $row['qty_sold'] ?></span></td>
                        <td style="color:var(--success);font-weight:700;"><?= Helper::formatCurrency($row['revenue']) ?></td>
                        <td style="color:var(--warning)"><?= Helper::formatCurrency($row['avg_price_per_unit']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Charts for Daily Sales -->
    <div class="charts-grid">
        <div class="chart-container">
            <canvas id="dailySalesChart"></canvas>
        </div>
        <div class="chart-container">
            <canvas id="transactionsTrendChart"></canvas>
        </div>
    </div>

    <script>
        const dailySalesData = <?= json_encode(array_map(fn($d) => ['date' => $d['sale_date'], 'amount' => (float)$d['total_sales']], $data)) ?>;
        const transactionData = <?= json_encode(array_map(fn($d) => ['date' => $d['sale_date'], 'count' => (int)$d['total_transactions']], $data)) ?>;
        
        if (dailySalesData.length > 0) {
            new Chart(document.getElementById('dailySalesChart'), {
                type: 'line',
                data: {
                    labels: dailySalesData.map(d => new Date(d.date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'})),
                    datasets: [{
                        label: 'Daily Sales Revenue',
                        data: dailySalesData.map(d => d.amount),
                        borderColor: '#38bdf8', backgroundColor: 'rgba(56, 189, 248, 0.1)',
                        borderWidth: 2.5, fill: true, tension: 0.4, radius: 4
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: true, labels: { color: '#9ca3af', font: {size: 12, weight: '700'} } } },
                    scales: {
                        y: { beginAtZero: true, grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'}, title: {display: true, text: 'Amount'} },
                        x: { grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} }
                    }
                }
            });
            
            new Chart(document.getElementById('transactionsTrendChart'), {
                type: 'bar',
                data: {
                    labels: transactionData.map(d => new Date(d.date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'})),
                    datasets: [{
                        label: 'Transactions',
                        data: transactionData.map(d => d.count),
                        backgroundColor: 'rgba(34, 197, 94, 0.8)', borderColor: '#22c55e', borderWidth: 1.5
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: true, labels: { color: '#9ca3af', font: {size: 12, weight: '700'} } } },
                    scales: {
                        y: { beginAtZero: true, grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} },
                        x: { grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} }
                    }
                }
            });
        }
    </script>

    <?php elseif($reportType === 'reorder_level' && !empty($summaryStats)): ?>
    <div class="metric-grid">
        <div class="metric-card" style="background: linear-gradient(135deg, rgba(239,68,68,0.1), rgba(239,68,68,0.05)); border-color: rgba(239,68,68,0.2);">
            <div class="metric-label" style="color: #ef4444;">🔴 URGENT - Critical Stock</div>
            <div style="font-size:28px;font-weight:800;color:#ef4444;"><?= $summaryStats['urgent_count'] ?></div>
            <div class="metric-change" style="color: #ef4444;">Immediate action required</div>
        </div>
        <div class="metric-card" style="background: linear-gradient(135deg, rgba(250,204,21,0.1), rgba(250,204,21,0.05)); border-color: rgba(250,204,21,0.2);">
            <div class="metric-label" style="color: #facc15;">🟡 LOW - Monitor Closely</div>
            <div style="font-size:28px;font-weight:800;color:#facc15;"><?= $summaryStats['low_count'] ?></div>
            <div class="metric-change" style="color: #facc15;">Within 50% of reorder level</div>
        </div>
        <div class="metric-card" style="background: linear-gradient(135deg, rgba(56,189,248,0.1), rgba(56,189,248,0.05)); border-color: rgba(56,189,248,0.2);">
            <div class="metric-label" style="color: #38bdf8;">👥 Affected Suppliers</div>
            <div style="font-size:28px;font-weight:800;color:#38bdf8;"><?= $summaryStats['affected_suppliers'] ?></div>
            <div class="metric-change" style="color: var(--text-muted);">Need to place orders</div>
        </div>
        <div class="metric-card" style="background: linear-gradient(135deg, rgba(34,197,94,0.1), rgba(34,197,94,0.05)); border-color: rgba(34,197,94,0.2);">
            <div class="metric-label" style="color: #22c55e;">📊 Total Items Below Level</div>
            <div style="font-size:28px;font-weight:800;color:#22c55e;"><?= $summaryStats['total_items'] ?></div>
            <div class="metric-change" style="color: var(--text-muted);">Requires attention</div>
        </div>
    </div>

    <!-- Supplier Impact Summary -->
    <?php if(!empty($summaryStats['supplier_impact'])): ?>
    <div class="breakdown-section">
        <div class="content-card">
            <div class="breakdown-title">Supplier Impact Analysis</div>
            <div style="overflow-x:auto;">
                <table class="table-dark-custom w-100">
                    <thead><tr><th>Supplier</th><th>Contact</th><th>Phone</th><th>🔴 Urgent Items</th><th>🟡 Low Items</th><th>Stock Value Risk</th></tr></thead>
                    <tbody>
                    <?php foreach($summaryStats['supplier_impact'] as $supp): ?>
                    <tr>
                        <td><strong><?= Helper::e($supp['supplier_name']) ?></strong></td>
                        <td><?= Helper::e($supp['contact']) ?></td>
                        <td style="font-size:12px;color:var(--text-muted)"><?= Helper::e($supp['phone']) ?></td>
                        <td><span style="background:rgba(239,68,68,0.2);color:#ef4444;padding:6px 12px;border-radius:6px;font-weight:700;"><?= $supp['urgent_items'] ?></span></td>
                        <td><span style="background:rgba(250,204,21,0.2);color:#facc15;padding:6px 12px;border-radius:6px;font-weight:700;"><?= $supp['low_items'] ?></span></td>
                        <td style="color:var(--success);font-weight:700;"><?= Helper::formatCurrency($supp['total_value']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Charts for Reorder Level -->
    <div class="charts-grid">
        <div class="chart-container">
            <canvas id="reorderStatusChart"></canvas>
        </div>
        <div class="chart-container">
            <canvas id="suppliersImpactChart"></canvas>
        </div>
    </div>
    
    <script>
        new Chart(document.getElementById('reorderStatusChart'), {
            type: 'doughnut',
            data: {
                labels: ['URGENT', 'LOW', 'OK'],
                datasets: [{
                    data: [<?= $summaryStats['urgent_count'] ?>, <?= $summaryStats['low_count'] ?>, <?= $summaryStats['total_items'] - $summaryStats['urgent_count'] - $summaryStats['low_count'] ?>],
                    backgroundColor: ['#ef4444', '#facc15', '#22c55e'],
                    borderColor: '#111827', borderWidth: 2
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { color: '#9ca3af', font: {size: 12, weight: '700'}, padding: 15 } } }
            }
        });
        
        <?php if(!empty($summaryStats['supplier_impact'])): ?>
        const supplierNames = <?= json_encode(array_map(fn($s) => $s['supplier_name'], array_slice($summaryStats['supplier_impact'], 0, 5))) ?>;
        const urgentCounts = <?= json_encode(array_map(fn($s) => $s['urgent_items'], array_slice($summaryStats['supplier_impact'], 0, 5))) ?>;
        new Chart(document.getElementById('suppliersImpactChart'), {
            type: 'bar',
            data: {
                labels: supplierNames,
                datasets: [{
                    label: 'Items Needing Reorder',
                    data: urgentCounts,
                    backgroundColor: 'rgba(168, 85, 247, 0.8)', borderColor: '#a855f7', borderWidth: 1.5
                }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: true, labels: { color: '#9ca3af', font: {size: 11} } } },
                scales: {
                    x: { beginAtZero: true, grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} },
                    y: { grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} }
                }
            }
        });
        <?php endif; ?>
    </script>

    <?php elseif($reportType === 'branch_sales' && !empty($summaryStats)): ?>
    <div class="metric-grid">
        <div class="metric-card">
            <div class="metric-label">💰 Total Revenue</div>
            <div class="metric-value"><?= Helper::formatCurrency($summaryStats['total_revenue']) ?></div>
            <div class="metric-change">📈 Across <?= $summaryStats['total_branches'] ?> branches</div>
        </div>
        <div class="metric-card" style="background: linear-gradient(135deg, rgba(34,197,94,0.1), rgba(34,197,94,0.05)); border-color: rgba(34,197,94,0.2);">
            <div class="metric-label" style="color: #22c55e;">🎯 Transactions</div>
            <div style="font-size:28px;font-weight:800;color:#22c55e;"><?= number_format($summaryStats['total_transactions']) ?></div>
            <div class="metric-change" style="color: var(--text-muted);">Avg <?= Helper::formatCurrency($summaryStats['avg_transaction']) ?> per sale</div>
        </div>
        <div class="metric-card" style="background: linear-gradient(135deg, rgba(168,85,247,0.1), rgba(168,85,247,0.05)); border-color: rgba(168,85,247,0.2);">
            <div class="metric-label" style="color: #a855f7;">📊 Avg Branch Revenue</div>
            <div style="font-size:28px;font-weight:800;color:#a855f7;"><?= Helper::formatCurrency($summaryStats['avg_branch_revenue']) ?></div>
            <div class="metric-change" style="color: var(--text-muted);">Staff active: <?= $summaryStats['staff_count'] ?></div>
        </div>
        <div class="metric-card" style="background: linear-gradient(135deg, rgba(59,130,246,0.1), rgba(59,130,246,0.05)); border-color: rgba(59,130,246,0.2);">
            <div class="metric-label" style="color: #3b82f6;">🏆 Best Branch</div>
            <div style="font-size:24px;font-weight:800;color:#3b82f6;"><?= Helper::e($summaryStats['best_branch']['branch_name'] ?? 'N/A') ?></div>
            <div class="metric-change" style="color: var(--text-muted);">💵 <?= Helper::formatCurrency($summaryStats['best_branch']['total_revenue'] ?? 0) ?></div>
        </div>
    </div>

    <!-- Top Products Per Branch -->
    <?php if(!empty($detailedBreakdown)): 
        $groupedByBranch = [];
        foreach($detailedBreakdown as $item) {
            $branchId = $item['branch_id'];
            if (!isset($groupedByBranch[$branchId])) {
                $groupedByBranch[$branchId] = ['branch_name' => $item['branch_name'], 'products' => []];
            }
            $groupedByBranch[$branchId]['products'][] = $item;
        }
    ?>
    <div class="breakdown-section">
        <div class="content-card">
            <div class="breakdown-title">Top Products by Branch</div>
            <div style="overflow-x:auto;">
                <table class="table-dark-custom w-100">
                    <thead><tr><th>Branch</th><th>Product</th><th>Brand</th><th>Qty Sold</th><th>Revenue</th></tr></thead>
                    <tbody>
                    <?php 
                    $count = 0;
                    foreach($groupedByBranch as $branch):
                        foreach(array_slice($branch['products'], 0, 3) as $product):
                            if($count++ >= 20) break 2;
                    ?>
                    <tr>
                        <td><strong style="color:var(--cyan)"><?= Helper::e($branch['branch_name']) ?></strong></td>
                        <td><?= Helper::e($product['product_name'] ?? 'N/A') ?></td>
                        <td><?= Helper::e($product['brand'] ?? '-') ?></td>
                        <td><?= $product['qty'] ?></td>
                        <td style="color:var(--success);font-weight:700;"><?= Helper::formatCurrency($product['revenue'] ?? 0) ?></td>
                    </tr>
                    <?php endforeach; endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Charts for Branch Sales -->
    <div class="charts-grid">
        <div class="chart-container">
            <canvas id="branchRevenueChart"></canvas>
        </div>
        <div class="chart-container">
            <canvas id="branchTransactionsChart"></canvas>
        </div>
    </div>
    <script>
        const branchData = <?= json_encode(array_map(fn($d) => ['name' => $d['branch_name'], 'revenue' => (float)$d['total_revenue'], 'transactions' => (int)$d['total_sales']], $data)) ?>;
        if (branchData.length > 0) {
            new Chart(document.getElementById('branchRevenueChart'), {
                type: 'bar',
                data: {
                    labels: branchData.map(b => b.name),
                    datasets: [{
                        label: 'Revenue',
                        data: branchData.map(b => b.revenue),
                        backgroundColor: 'rgba(56, 189, 248, 0.8)', borderColor: '#38bdf8', borderWidth: 1.5
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: true, labels: { color: '#9ca3af' } } },
                    scales: {
                        y: { beginAtZero: true, grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} },
                        x: { grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} }
                    }
                }
            });
            new Chart(document.getElementById('branchTransactionsChart'), {
                type: 'bar',
                data: {
                    labels: branchData.map(b => b.name),
                    datasets: [{
                        label: 'Transactions',
                        data: branchData.map(b => b.transactions),
                        backgroundColor: 'rgba(34, 197, 94, 0.8)', borderColor: '#22c55e', borderWidth: 1.5
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: true, labels: { color: '#9ca3af' } } },
                    scales: {
                        y: { beginAtZero: true, grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} },
                        x: { grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} }
                    }
                }
            });
        }
    </script>

    <?php elseif($reportType === 'stock' && !empty($summaryStats)): ?>
    <div class="metric-grid">
        <div class="metric-card">
            <div class="metric-label">💰 Stock Value</div>
            <div class="metric-value"><?= Helper::formatCurrency($summaryStats['total_stock_value']) ?></div>
            <div class="metric-change">Total Cost Value</div>
        </div>
        <div class="metric-card" style="background: linear-gradient(135deg, rgba(34,197,94,0.1), rgba(34,197,94,0.05)); border-color: rgba(34,197,94,0.2);">
            <div class="metric-label" style="color: #22c55e;">🎯 Potential Revenue</div>
            <div style="font-size:28px;font-weight:800;color:#22c55e;"><?= Helper::formatCurrency($summaryStats['potential_revenue']) ?></div>
            <div class="metric-change" style="color: var(--text-muted);">If all stock sold</div>
        </div>
        <div class="metric-card" style="background: linear-gradient(135deg, rgba(168,85,247,0.1), rgba(168,85,247,0.05)); border-color: rgba(168,85,247,0.2);">
            <div class="metric-label" style="color: #a855f7;">💵 Profit Margin</div>
            <div style="font-size:28px;font-weight:800;color:#a855f7;"><?= Helper::formatCurrency($summaryStats['total_profit_margin']) ?></div>
            <div class="metric-change" style="color: #a855f7;">Total Potential Profit</div>
        </div>
        <div class="metric-card" style="background: linear-gradient(135deg, rgba(59,130,246,0.1), rgba(59,130,246,0.05)); border-color: rgba(59,130,246,0.2);">
            <div class="metric-label" style="color: #3b82f6;">📦 Items</div>
            <div style="font-size:28px;font-weight:800;color:#3b82f6;"><?= $summaryStats['total_items'] ?></div>
            <div class="metric-change" style="color: var(--text-muted);">Avg Stock: <?= number_format($summaryStats['avg_stock_per_item'], 2) ?></div>
        </div>
    </div>

    <!-- Charts for Stock -->
    <div class="charts-grid">
        <div class="chart-container">
            <canvas id="stockValueChart"></canvas>
        </div>
        <div class="chart-container">
            <canvas id="profitMarginChart"></canvas>
        </div>
    </div>
    <script>
        const stockDataTop = <?= json_encode(array_map(fn($s) => ['name' => substr($s['product_name'], 0, 15), 'value' => (float)$s['stock_value']], array_slice($data, 0, 8))) ?>;
        new Chart(document.getElementById('stockValueChart'), {
            type: 'bar',
            data: {
                labels: stockDataTop.map(s => s.name),
                datasets: [{
                    label: 'Stock Value (Cost)',
                    data: stockDataTop.map(s => s.value),
                    backgroundColor: 'rgba(56, 189, 248, 0.8)', borderColor: '#38bdf8', borderWidth: 1.5
                }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: true, labels: { color: '#9ca3af' } } },
                scales: {
                    x: { beginAtZero: true, grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} },
                    y: { grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} }
                }
            }
        });
        new Chart(document.getElementById('profitMarginChart'), {
            type: 'doughnut',
            data: {
                labels: ['Profit Potential', 'Cost Value'],
                datasets: [{
                    data: [<?= max(0, $summaryStats['total_profit_margin']) ?>, <?= $summaryStats['total_stock_value'] ?>],
                    backgroundColor: ['#22c55e', '#ef4444'],
                    borderColor: '#111827', borderWidth: 2
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { color: '#9ca3af', font: {size: 11}, padding: 15 } } }
            }
        });
    </script>

    <?php elseif($reportType === 'supplier' && !empty($summaryStats)): ?>
    <div class="metric-grid">
        <div class="metric-card">
            <div class="metric-label">🤝 Active Suppliers</div>
            <div class="metric-value"><?= $summaryStats['active_suppliers'] ?></div>
            <div class="metric-change">In selected period</div>
        </div>
        <div class="metric-card" style="background: linear-gradient(135deg, rgba(34,197,94,0.1), rgba(34,197,94,0.05)); border-color: rgba(34,197,94,0.2);">
            <div class="metric-label" style="color: #22c55e;">💰 Total Purchased</div>
            <div style="font-size:28px;font-weight:800;color:#22c55e;"><?= Helper::formatCurrency($summaryStats['total_purchased']) ?></div>
            <div class="metric-change" style="color: var(--text-muted);">Total spending</div>
        </div>
        <div class="metric-card" style="background: linear-gradient(135deg, rgba(168,85,247,0.1), rgba(168,85,247,0.05)); border-color: rgba(168,85,247,0.2);">
            <div class="metric-label" style="color: #a855f7;">📊 Avg Per Supplier</div>
            <div style="font-size:28px;font-weight:800;color:#a855f7;"><?= Helper::formatCurrency($summaryStats['avg_supplier_value']) ?></div>
            <div class="metric-change" style="color: var(--text-muted);">Average purchase value</div>
        </div>
        <div class="metric-card" style="background: linear-gradient(135deg, rgba(59,130,246,0.1), rgba(59,130,246,0.05)); border-color: rgba(59,130,246,0.2);">
            <div class="metric-label" style="color: #3b82f6;">⭐ Top Supplier</div>
            <div style="font-size:24px;font-weight:800;color:#3b82f6;"><?= Helper::e($summaryStats['top_supplier']['supplier_name'] ?? 'N/A') ?></div>
            <div class="metric-change" style="color: var(--text-muted);">💵 <?= Helper::formatCurrency($summaryStats['top_supplier']['total_purchased'] ?? 0) ?></div>
        </div>
    </div>

    <!-- Charts for Supplier -->
    <div class="charts-grid">
        <div class="chart-container">
            <canvas id="supplierPurchaseChart"></canvas>
        </div>
        <div class="chart-container">
            <canvas id="supplierProductsChart"></canvas>
        </div>
    </div>
    <script>
        const supplierDataTop = <?= json_encode(array_map(fn($s) => ['name' => substr($s['supplier_name'], 0, 12), 'amount' => (float)$s['total_purchased']], array_slice($data, 0, 6))) ?>;
        new Chart(document.getElementById('supplierPurchaseChart'), {
            type: 'bar',
            data: {
                labels: supplierDataTop.map(s => s.name),
                datasets: [{
                    label: 'Total Purchased',
                    data: supplierDataTop.map(s => s.amount),
                    backgroundColor: 'rgba(168, 85, 247, 0.8)', borderColor: '#a855f7', borderWidth: 1.5
                }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: true, labels: { color: '#9ca3af' } } },
                scales: {
                    x: { beginAtZero: true, grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} },
                    y: { grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} }
                }
            }
        });
        const supplierProducts = <?= json_encode(array_map(fn($s) => $s['product_count'] ?? 0, array_slice($data, 0, 6))) ?>;
        new Chart(document.getElementById('supplierProductsChart'), {
            type: 'bar',
            data: {
                labels: supplierDataTop.map(s => s.name),
                datasets: [{
                    label: 'Product Count',
                    data: supplierProducts,
                    backgroundColor: 'rgba(34, 197, 94, 0.8)', borderColor: '#22c55e', borderWidth: 1.5
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: true, labels: { color: '#9ca3af' } } },
                scales: {
                    y: { beginAtZero: true, grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} },
                    x: { grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} }
                }
            }
        });
    </script>

    <?php elseif($reportType === 'grn' && !empty($summaryStats)): ?>
    <div class="metric-grid">
        <div class="metric-card">
            <div class="metric-label">📦 Total GRNs</div>
            <div class="metric-value"><?= $summaryStats['total_grns'] ?></div>
            <div class="metric-change">In selected period</div>
        </div>
        <div class="metric-card" style="background: linear-gradient(135deg, rgba(34,197,94,0.1), rgba(34,197,94,0.05)); border-color: rgba(34,197,94,0.2);">
            <div class="metric-label" style="color: #22c55e;">💰 Total Amount</div>
            <div style="font-size:28px;font-weight:800;color:#22c55e;"><?= Helper::formatCurrency($summaryStats['total_amount']) ?></div>
            <div class="metric-change" style="color: var(--text-muted);">Total purchased value</div>
        </div>
        <div class="metric-card" style="background: linear-gradient(135deg, rgba(168,85,247,0.1), rgba(168,85,247,0.05)); border-color: rgba(168,85,247,0.2);">
            <div class="metric-label" style="color: #a855f7;">📊 Avg GRN Amount</div>
            <div style="font-size:28px;font-weight:800;color:#a855f7;"><?= Helper::formatCurrency($summaryStats['avg_grn_amount']) ?></div>
            <div class="metric-change" style="color: var(--text-muted);">Average per GRN</div>
        </div>
        <div class="metric-card" style="background: linear-gradient(135deg, rgba(59,130,246,0.1), rgba(59,130,246,0.05)); border-color: rgba(59,130,246,0.2);">
            <div class="metric-label" style="color: #3b82f6;">📋 Line Items</div>
            <div style="font-size:28px;font-weight:800;color:#3b82f6;"><?= $summaryStats['total_items'] ?></div>
            <div class="metric-change" style="color: var(--text-muted);">Total products received</div>
        </div>
    </div>

    <!-- Charts for GRN -->
    <div class="charts-grid">
        <div class="chart-container">
            <canvas id="grnAmountTrendChart"></canvas>
        </div>
        <div class="chart-container">
            <canvas id="grnLineItemsChart"></canvas>
        </div>
    </div>
    <script>
        const grnData = <?= json_encode(array_map(fn($g) => ['date' => $g['grn_date'], 'amount' => (float)$g['total_amount'], 'items' => (int)($g['line_items'] ?? 0)], array_slice($data, 0, 12))) ?>;
        if (grnData.length > 0) {
            new Chart(document.getElementById('grnAmountTrendChart'), {
                type: 'line',
                data: {
                    labels: grnData.map(g => new Date(g.date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'})),
                    datasets: [{
                        label: 'GRN Amount',
                        data: grnData.map(g => g.amount),
                        borderColor: '#22c55e', backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        borderWidth: 2.5, fill: true, tension: 0.4, radius: 4
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: true, labels: { color: '#9ca3af' } } },
                    scales: {
                        y: { beginAtZero: true, grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} },
                        x: { grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} }
                    }
                }
            });
            new Chart(document.getElementById('grnLineItemsChart'), {
                type: 'bar',
                data: {
                    labels: grnData.map(g => new Date(g.date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'})),
                    datasets: [{
                        label: 'Line Items',
                        data: grnData.map(g => g.items),
                        backgroundColor: 'rgba(56, 189, 248, 0.8)', borderColor: '#38bdf8', borderWidth: 1.5
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: true, labels: { color: '#9ca3af' } } },
                    scales: {
                        y: { beginAtZero: true, grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} },
                        x: { grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} }
                    }
                }
            });
        }
    </script>

    <?php elseif($reportType === 'low_stock' && !empty($summaryStats)): ?>
    <div class="metric-grid">
        <div class="metric-card" style="background: linear-gradient(135deg, rgba(239,68,68,0.1), rgba(239,68,68,0.05)); border-color: rgba(239,68,68,0.2);">
            <div class="metric-label" style="color: #ef4444;">⚠️ Critical Items</div>
            <div style="font-size:28px;font-weight:800;color:#ef4444;"><?= $summaryStats['low_stock_items'] ?></div>
            <div class="metric-change" style="color: #ef4444;">Below reorder level</div>
        </div>
        <div class="metric-card" style="background: linear-gradient(135deg, rgba(250,204,21,0.1), rgba(250,204,21,0.05)); border-color: rgba(250,204,21,0.2);">
            <div class="metric-label" style="color: #facc15;">📦 Current Qty</div>
            <div style="font-size:28px;font-weight:800;color:#facc15;"><?= number_format($summaryStats['total_qty_low']) ?></div>
            <div class="metric-change" style="color: var(--text-muted);">Units in stock</div>
        </div>
        <div class="metric-card" style="background: linear-gradient(135deg, rgba(56,189,248,0.1), rgba(56,189,248,0.05)); border-color: rgba(56,189,248,0.2);">
            <div class="metric-label" style="color: #38bdf8;">📋 To Reorder</div>
            <div style="font-size:28px;font-weight:800;color:#38bdf8;"><?= number_format($summaryStats['total_reorder_qty']) ?></div>
            <div class="metric-change" style="color: var(--text-muted);">Total to restore</div>
        </div>
    </div>

    <!-- Charts for Low Stock -->
    <div class="charts-grid">
        <div class="chart-container">
            <canvas id="lowStockCategoriesChart"></canvas>
        </div>
        <div class="chart-container">
            <canvas id="lowStockBySupplierChart"></canvas>
        </div>
    </div>
    <script>
        const lowStockByCategory = {};
        const lowStockBySupplier = {};
        const allLowStockData = <?= json_encode($data) ?>;
        
        allLowStockData.forEach(item => {
            lowStockByCategory[item.category_name] = (lowStockByCategory[item.category_name] || 0) + 1;
            lowStockBySupplier[item.supplier_name] = (lowStockBySupplier[item.supplier_name] || 0) + 1;
        });
        
        new Chart(document.getElementById('lowStockCategoriesChart'), {
            type: 'bar',
            data: {
                labels: Object.keys(lowStockByCategory).slice(0, 8),
                datasets: [{
                    label: 'Items Below Reorder',
                    data: Object.values(lowStockByCategory).slice(0, 8),
                    backgroundColor: 'rgba(239, 68, 68, 0.8)', borderColor: '#ef4444', borderWidth: 1.5
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: true, labels: { color: '#9ca3af' } } },
                scales: {
                    y: { beginAtZero: true, grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} },
                    x: { grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} }
                }
            }
        });
        
        new Chart(document.getElementById('lowStockBySupplierChart'), {
            type: 'bar',
            data: {
                labels: Object.keys(lowStockBySupplier).slice(0, 8),
                datasets: [{
                    label: 'Urgent Items Per Supplier',
                    data: Object.values(lowStockBySupplier).slice(0, 8),
                    backgroundColor: 'rgba(250, 204, 21, 0.8)', borderColor: '#facc15', borderWidth: 1.5
                }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: true, labels: { color: '#9ca3af' } } },
                scales: {
                    x: { beginAtZero: true, grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} },
                    y: { grid: {color: '#1f2937'}, ticks: {color: '#9ca3af'} }
                }
            }
        });
    </script>
    <?php endif; ?>

    <div style="overflow-x:auto;">
    <?php if($reportType==='stock'): ?>
        <table class="table-dark-custom datatable w-100">
            <thead><tr><th>Product</th><th>Brand</th><th>Category</th><th>Supplier</th><th>Stock Qty</th><th>Cost</th><th>Selling</th><th>Stock Value</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach($data as $r): ?>
            <tr>
                <td><?= Helper::e($r['product_name']) ?></td>
                <td><?= Helper::e($r['brand']) ?></td>
                <td><span class="badge bg-info"><?= Helper::e($r['category_name']) ?></span></td>
                <td><?= Helper::e($r['supplier_name']) ?></td>
                <td><strong style="color:var(--cyan)"><?= $r['total_qty'] ?></strong></td>
                <td><?= Helper::formatCurrency($r['cost_price']) ?></td>
                <td style="color:var(--success)"><?= Helper::formatCurrency($r['selling_price']) ?></td>
                <td style="color:var(--warning)"><?= Helper::formatCurrency($r['stock_value']) ?></td>
                <td><?= Helper::stockBadge((int)$r['total_qty'],(int)$r['reorder_level']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif($reportType==='grn'): ?>
        <?php $total = array_sum(array_column($data,'total_amount')); ?>
        <div style="padding:12px 20px;background:var(--cyan-dim);display:flex;gap:24px;">
            <span>Total GRNs: <strong style="color:var(--cyan)"><?= count($data) ?></strong></span>
            <span>Total Amount: <strong style="color:var(--cyan)"><?= Helper::formatCurrency($total) ?></strong></span>
        </div>
        <table class="table-dark-custom datatable w-100">
            <thead><tr><th>GRN #</th><th>Supplier</th><th>Branch</th><th>Invoice</th><th>Date</th><th>Amount</th><th>Created By</th></tr></thead>
            <tbody>
            <?php foreach($data as $r): ?>
            <tr>
                <td class="text-cyan"><?= Helper::e($r['grn_number']) ?></td>
                <td><?= Helper::e($r['supplier_name']) ?></td>
                <td><?= Helper::e($r['branch_name']) ?></td>
                <td style="color:var(--text-muted)"><?= Helper::e($r['invoice_number']?:'—') ?></td>
                <td><?= Helper::formatDate($r['grn_date']) ?></td>
                <td style="color:var(--success);font-weight:600"><?= Helper::formatCurrency($r['total_amount']) ?></td>
                <td style="color:var(--text-muted)"><?= Helper::e($r['created_by']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif($reportType==='low_stock'): ?>
        <table class="table-dark-custom datatable w-100">
            <thead><tr><th>Product</th><th>Brand</th><th>Category</th><th>Supplier</th><th>Stock</th><th>Reorder Level</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach($data as $r): ?>
            <tr>
                <td><?= Helper::e($r['product_name']) ?></td>
                <td><?= Helper::e($r['brand']) ?></td>
                <td><span class="badge bg-info"><?= Helper::e($r['category_name']) ?></span></td>
                <td><?= Helper::e($r['supplier_name']) ?></td>
                <td><strong style="color:var(--warning)"><?= $r['total_stock'] ?></strong></td>
                <td><?= $r['reorder_level'] ?></td>
                <td><?= Helper::stockBadge((int)$r['total_stock'],(int)$r['reorder_level']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif($reportType==='supplier'): ?>
        <table class="table-dark-custom datatable w-100">
            <thead><tr><th>Supplier</th><th>Contact</th><th>Phone</th><th>Products</th><th>GRNs</th><th>Total Purchased</th></tr></thead>
            <tbody>
            <?php foreach($data as $r): ?>
            <tr>
                <td><strong><?= Helper::e($r['supplier_name']) ?></strong></td>
                <td><?= Helper::e($r['contact_person']) ?></td>
                <td><?= Helper::e($r['phone']) ?></td>
                <td><span style="color:var(--cyan)"><?= $r['product_count'] ?></span></td>
                <td><span style="color:var(--success)"><?= $r['grn_count'] ?></span></td>
                <td style="color:var(--warning);font-weight:700"><?= Helper::formatCurrency($r['total_purchased']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif($reportType==='daily_sales'): ?>
        <table class="table-dark-custom datatable w-100">
            <thead><tr><th>Date</th><th>Branch</th><th>Transactions</th><th>Total Sales</th><th>Avg per Transaction</th></tr></thead>
            <tbody>
            <?php foreach($data as $r): ?>
            <tr>
                <td style="font-weight:600;color:var(--cyan)"><?= Helper::formatDate($r['sale_date']) ?></td>
                <td><?= Helper::e($r['branch_name']) ?></td>
                <td><span style="background:rgba(56,189,248,0.15);padding:4px 12px;border-radius:6px;font-weight:700;"><?= $r['total_transactions'] ?></span></td>
                <td style="color:var(--success);font-weight:700;font-size:15px;"><?= Helper::formatCurrency($r['total_sales']) ?></td>
                <td style="color:var(--warning)"><?= Helper::formatCurrency($r['total_sales'] / $r['total_transactions']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif($reportType==='reorder_level'): ?>
        <table class="table-dark-custom datatable w-100">
            <thead><tr><th>Product</th><th>Brand</th><th>Category</th><th>Branch</th><th>Current Stock</th><th>Reorder Level</th><th>Supplier</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach($data as $r): ?>
            <tr style="<?= $r['status']==='URGENT'?'background:rgba(239,68,68,0.1);':($r['status']==='LOW'?'background:rgba(250,204,21,0.1);':'') ?>">
                <td><strong><?= Helper::e($r['product_name']) ?></strong></td>
                <td><?= Helper::e($r['brand']) ?></td>
                <td><span class="badge bg-info" style="font-size:11px;"><?= Helper::e($r['category_name']) ?></span></td>
                <td><?= Helper::e($r['branch_name'] ?? 'N/A') ?></td>
                <td style="font-weight:700;color:<?= $r['current_stock'] <= ($r['reorder_level']*0.5) ? '#ef4444' : ($r['current_stock'] <= $r['reorder_level'] ? '#facc15' : '#22c55e') ?>"><?= $r['current_stock'] ?></td>
                <td style="color:var(--text-muted)"><?= $r['reorder_level'] ?></td>
                <td><?= Helper::e($r['supplier_name']) ?></td>
                <td>
                    <?php if($r['status']==='URGENT'): ?>
                        <span style="background:#ef4444;color:white;padding:4px 12px;border-radius:6px;font-size:11px;font-weight:700;">🔴 URGENT</span>
                    <?php elseif($r['status']==='LOW'): ?>
                        <span style="background:#facc15;color:#000;padding:4px 12px;border-radius:6px;font-size:11px;font-weight:700;">🟡 LOW</span>
                    <?php else: ?>
                        <span style="background:#22c55e;color:white;padding:4px 12px;border-radius:6px;font-size:11px;font-weight:700;">✓ OK</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif($reportType==='branch_sales'): ?>
        <table class="table-dark-custom datatable w-100">
            <thead><tr><th>Branch</th><th>City</th><th>Total Sales</th><th>Revenue</th><th>Avg Sale</th><th>Products Sold</th><th>Days Active</th><th>Last Sale</th></tr></thead>
            <tbody>
            <?php foreach($data as $r): ?>
            <tr>
                <td><strong style="color:var(--cyan)"><?= Helper::e($r['branch_name']) ?></strong></td>
                <td><?= Helper::e($r['city']) ?></td>
                <td><span style="background:rgba(56,189,248,0.15);padding:4px 12px;border-radius:6px;font-weight:700;"><?= $r['total_sales'] ?></span></td>
                <td style="color:var(--success);font-weight:700;font-size:15px;"><?= Helper::formatCurrency($r['total_revenue']) ?></td>
                <td style="color:var(--warning)"><?= Helper::formatCurrency($r['avg_sale_amount']) ?></td>
                <td style="color:var(--info)"><?= $r['unique_products_sold'] ?></td>
                <td><?= $r['transaction_days'] ?></td>
                <td style="font-size:12px;color:var(--text-muted);"><?= $r['last_sale'] && $r['last_sale'] !== '-' ? Helper::formatDateTime($r['last_sale']) : 'No sales' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.min.js"></script>
<script>
function exportToExcel() {
    const table = document.querySelector('table.table-dark-custom');
    if (!table) {
        alert('No data to export');
        return;
    }
    
    const ws = XLSX.utils.table_to_sheet(table);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Report');
    
    const fileName = '<?= $reportType ?>_report_<?= date('Y-m-d_His') ?>.xlsx';
    XLSX.writeFile(wb, fileName);
}

// CSV Export
function exportToCSV() {
    const table = document.querySelector('table.table-dark-custom');
    if (!table) {
        alert('No data to export');
        return;
    }
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach((row, index) => {
        const cols = row.querySelectorAll('td, th');
        let csvRow = [];
        cols.forEach(col => {
            csvRow.push('"' + col.innerText.replace(/"/g, '""') + '"');
        });
        csv.push(csvRow.join(','));
    });
    
    const csvContent = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv.join('\n'));
    const link = document.createElement('a');
    link.setAttribute('href', csvContent);
    link.setAttribute('download', '<?= $reportType ?>_report_<?= date('Y-m-d_His') ?>.csv');
    link.click();
}

// Print functionality
window.addEventListener('beforeprint', function() {
    document.querySelectorAll('.export-buttons, .filter-bar').forEach(el => {
        el.style.display = 'none';
    });
});

window.addEventListener('afterprint', function() {
    document.querySelectorAll('.export-buttons, .filter-bar').forEach(el => {
        el.style.display = '';
    });
});
</script>

<?php require_once __DIR__ . '/views/layouts/footer.php'; ?>
