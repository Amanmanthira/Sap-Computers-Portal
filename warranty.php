<?php
require_once __DIR__ . '/includes/bootstrap.php';
Session::requireSeller();

$pageTitle = 'Warranty Management';
$activePage = 'warranty';

$warrantyModel = new WarrantyModel();
$productModel = new ProductModel();

// IMPORTANT: Handle POST requests FIRST before rendering any view
// This ensures form submissions are processed before the page displays

// Handle POST: Update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'update_status') {
    $warranty_id = (int)$_GET['view'];
    $warrantyModel->update($warranty_id, [
        'warranty_status' => $_POST['warranty_status'],
        'created_by' => Session::get('user_id')
    ]);
    Session::setFlash('success', 'Warranty status updated successfully!');
    Helper::redirect('/sap-computers/warranty.php?view=' . $warranty_id);
}

// Handle POST: Add movement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'add_movement') {
    $warranty_id = (int)($_GET['view'] ?? 0);
    
    // Validate warranty_id
    if (!$warranty_id) {
        Session::setFlash('error', 'Invalid warranty ID');
        Helper::redirect('/sap-computers/warranty.php');
    }
    
    $movement_type = $_POST['movement_type'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (!$movement_type || !$description) {
        Session::setFlash('error', 'Movement type and description are required');
        Helper::redirect('/sap-computers/warranty.php?view=' . $warranty_id);
    }
    
    // Get dispatch method and location from simple text inputs
    $dispatch_method = $_POST['dispatch_method'] ?? '';
    $location = $_POST['location'] ?? '';
    
    error_log("Processing add_movement: warranty_id={$warranty_id}, type={$movement_type}");
    
    // Add the movement
    $result = $warrantyModel->addMovement(
        $warranty_id,
        $movement_type,
        $description,
        Session::get('user_id'),
        !empty($location) ? $location : null,
        !empty($dispatch_method) ? $dispatch_method : null,
        $_POST['tracking_number'] ?? null
    );
    
    error_log("addMovement returned: " . ($result ? $result : 'false'));
    
    // Auto-update warranty status based on movement type
    $statusMap = [
        'intake' => 'pending',
        'diagnosis' => 'in-progress',
        'repair' => 'in-progress',
        'waiting_parts' => 'in-progress',
        'dispatch' => 'in-progress',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
    ];
    
    if (isset($statusMap[$movement_type])) {
        $completedDate = ($movement_type === 'completed') ? date('Y-m-d H:i:s') : null;
        $warrantyModel->update($warranty_id, [
            'warranty_status' => $statusMap[$movement_type],
            'created_by' => Session::get('user_id'),
            'completed_date' => $completedDate
        ]);
    }
    
    Session::setFlash('success', 'Movement recorded successfully!');
    Helper::redirect('/sap-computers/warranty.php?view=' . $warranty_id);
}

// Handle POST: Update notes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'update_notes') {
    $warranty_id = (int)$_GET['view'];
    $warrantyModel->update($warranty_id, [
        'notes' => Helper::sanitize($_POST['notes'] ?? ''),
        'created_by' => Session::get('user_id')
    ]);
    Session::setFlash('success', 'Notes updated successfully!');
    Helper::redirect('/sap-computers/warranty.php?view=' . $warranty_id);
}

// Handle POST: Create warranty
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['action'])) {
    // This is a create warranty request (no action parameter)
    error_log("Warranty POST received. POST data: " . json_encode($_POST));
    
    if (isset($_POST['product_id']) && !empty($_POST['product_id'])) {
        try {
            // Get warranty months for the product
            $product = $productModel->findById((int)$_POST['product_id']);
            error_log("Product found: " . json_encode($product));
            
            $warranty_months = $product['warranty_months'] ?? 12;
            
            $create_data = [
                'product_id' => (int)$_POST['product_id'],
                'order_id' => !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null,
                'sale_id' => !empty($_POST['sale_id']) ? (int)$_POST['sale_id'] : null,
                'serial_number' => Helper::sanitize($_POST['serial_number'] ?? ''),
                'customer_name' => Helper::sanitize($_POST['customer_name']),
                'customer_phone' => Helper::sanitize($_POST['customer_phone']),
                'customer_email' => Helper::sanitize($_POST['customer_email'] ?? ''),
                'issue_description' => Helper::sanitize($_POST['issue_description']),
                'expected_completion' => $_POST['expected_completion'] ?? null,
                'purchase_date' => !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null,
                'warranty_months' => $warranty_months,
                'created_by' => Session::get('user_id'),
            ];
            
            error_log("Creating warranty with data: " . json_encode($create_data));
            
            $result = $warrantyModel->create($create_data);
            
            error_log("Warranty create result: " . ($result ? "ID=$result" : "FALSE"));
            
            if ($result) {
                $message = !empty($_POST['order_id']) ? 'Warranty claim created with verified purchase!' : 'Warranty claim created - Manual verification pending';
                Session::setFlash('success', $message);
                Helper::redirect('/sap-computers/warranty.php?view=' . $result);
            } else {
                Session::setFlash('error', 'Failed to create warranty claim - check server logs');
                error_log("Warranty creation failed for product: " . $_POST['product_id']);
                Helper::redirect('/sap-computers/warranty.php');
            }
        } catch (Exception $e) {
            error_log("Warranty creation exception: " . $e->getMessage());
            Session::setFlash('error', 'Error: ' . $e->getMessage());
            Helper::redirect('/sap-computers/warranty.php');
        }
    } else {
        error_log("Warranty POST missing product_id. POST: " . json_encode($_POST));
        Session::setFlash('error', 'Product ID is required');
        Helper::redirect('/sap-computers/warranty.php');
    }
}

// View a single warranty
if (isset($_GET['view'])) {
    $warranty_id = (int)$_GET['view'];
    $warranty = $warrantyModel->findById($warranty_id);
    $movements = $warrantyModel->getMovements($warranty_id);
    $parts = $warrantyModel->getParts($warranty_id);
    error_log("Warranty view page - warranty_id: {$warranty_id}, movements count: " . count($movements));
    $pageTitle = 'Warranty Detail — ' . ($warranty['warranty_number'] ?? '');
    require_once __DIR__ . '/views/layouts/header.php';
    ?>
    <div class="page-header">
        <div>
            <div class="page-header-title"><?= Helper::e($warranty['warranty_number']) ?></div>
            <div class="page-header-sub">Warranty Claim Management</div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="/sap-computers/warranty.php" class="btn-ghost"><i class="bi bi-arrow-left"></i> Back</a>
            <button class="btn-cyan" onclick="window.print()"><i class="bi bi-printer-fill"></i> Print</button>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="content-card">
                <div class="content-card-header"><div class="content-card-title"><i class="bi bi-info-circle-fill"></i> Claim Info</div></div>
                <div class="content-card-body">
                    <?php
                    $statusBadge = [
                        'pending' => '<span style="background:var(--warning-dim);color:var(--warning);padding:4px 12px;border-radius:4px;font-size:11px;font-weight:600;">PENDING</span>',
                        'in-progress' => '<span style="background:var(--cyan-dim);color:var(--cyan);padding:4px 12px;border-radius:4px;font-size:11px;font-weight:600;">IN PROGRESS</span>',
                        'completed' => '<span style="background:var(--success-dim);color:var(--success);padding:4px 12px;border-radius:4px;font-size:11px;font-weight:600;">COMPLETED</span>',
                        'cancelled' => '<span style="background:var(--danger-dim);color:var(--danger);padding:4px 12px;border-radius:4px;font-size:11px;font-weight:600;">CANCELLED</span>',
                    ];

                    $info = [
                        'Status' => $statusBadge[$warranty['warranty_status']] ?? '',
                        'Product' => Helper::e($warranty['product_name'] . ' — ' . $warranty['brand']),
                        'Serial Number' => Helper::e($warranty['serial_number'] ?: '—'),
                        'Intake Date' => $warranty['intake_date'] ? Helper::formatDate($warranty['intake_date']) : '—',
                        'Expected Completion' => Helper::formatDate($warranty['expected_completion']) ?: '—',
                        'Completed Date' => $warranty['completed_date'] ? Helper::formatDate($warranty['completed_date']) : '—',
                        'Created By' => Helper::e($warranty['created_by_name']),
                    ];
                    
                    // Add warranty verification info
                    if ($warranty['purchase_date']) {
                        $info['Purchase Date'] = Helper::formatDate($warranty['purchase_date']);
                        $info['Warranty Expires'] = Helper::formatDate($warranty['warranty_expiry_date']);
                        
                        $isExpired = strtotime($warranty['warranty_expiry_date']) < time();
                        $daysLeft = (int)((strtotime($warranty['warranty_expiry_date']) - time()) / (60*60*24));
                        $expiryStatus = $isExpired ? 
                            '<span style="color:var(--danger);">EXPIRED ('.$daysLeft.' days ago)</span>' : 
                            '<span style="color:var(--success);">VALID ('.$daysLeft.' days remaining)</span>';
                        $info['Warranty Status'] = $expiryStatus;
                    }
                    
                    foreach ($info as $label => $val):
                    ?>
                    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--navy-border);align-items:center;">
                        <span style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;"><?= $label ?></span>
                        <span style="font-size:13px;font-weight:500;"><?= $val ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="content-card">
                <div class="content-card-header"><div class="content-card-title"><i class="bi bi-person-fill"></i> Customer Info</div></div>
                <div class="content-card-body">
                    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--navy-border);">
                        <span style="font-size:11px;color:var(--text-muted);text-transform:uppercase;">Name</span>
                        <span style="font-size:13px;font-weight:500;"><?= Helper::e($warranty['customer_name']) ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--navy-border);">
                        <span style="font-size:11px;color:var(--text-muted);text-transform:uppercase;">Phone</span>
                        <span style="font-size:13px;font-weight:500;"><?= Helper::e($warranty['customer_phone']) ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:8px 0;">
                        <span style="font-size:11px;color:var(--text-muted);text-transform:uppercase;">Email</span>
                        <span style="font-size:13px;font-weight:500;"><?= Helper::e($warranty['customer_email']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <!-- Issue Description -->
            <div class="content-card mb-3">
                <div class="content-card-header"><div class="content-card-title"><i class="bi bi-chat-left-text-fill"></i> Issue Description</div></div>
                <div class="content-card-body">
                    <p style="margin:0;color:var(--text-muted);"><?= nl2br(Helper::e($warranty['issue_description'])) ?></p>
                </div>
            </div>

            <div class="content-card">
                <div class="content-card-header"><div class="content-card-title"><i class="bi bi-shield-check"></i> Warranty Verification</div></div>
                <div class="content-card-body">
                    <?php if ($warranty['order_id']): ?>
                        <div style="padding:12px;background:var(--success-dim);border-radius:4px;border:1px solid var(--success);margin-bottom:12px;">
                            <div style="color:var(--success);font-weight:600;margin-bottom:8px;">✓ Purchase Verified</div>
                            <div style="font-size:12px;color:var(--text-muted);">
                                <div>Order ID: #<?= $warranty['order_id'] ?></div>
                                <div>Verified By: <?= Helper::e($warranty['verified_by'] ? $warranty['verified_by'] : 'System') ?></div>
                                <div>Verified At: <?= Helper::formatDateTime($warranty['verified_at']) ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="padding:12px;background:var(--warning-dim);border-radius:4px;border:1px solid var(--warning);margin-bottom:12px;">
                            <div style="color:var(--warning);font-weight:600;margin-bottom:8px;">⚠ Manual Verification Needed</div>
                            <div style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">
                                This warranty claim has not been verified against a purchase order. Manual verification is required.
                            </div>
                            <button class="btn-sm btn-outline-warning" onclick="document.getElementById('verifyModal').classList.add('show'); document.getElementById('verifyModal').style.display='block';">
                                <i class="bi bi-search"></i> Verify Purchase
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
                <div class="content-card-header"><div class="content-card-title"><i class="bi bi-sliders"></i> Update Status</div></div>
                <div class="content-card-body">
                    <form method="POST" action="/sap-computers/warranty.php?view=<?= $warranty_id ?>&action=update_status">
                        <div style="display:flex;gap:12px;align-items:flex-end;">
                            <div style="flex:1;">
                                <label class="form-label-dark" style="font-size:12px;margin-bottom:6px;">Change Status</label>
                                <select name="warranty_status" class="form-select-dark w-100">
                                    <option value="pending" <?= $warranty['warranty_status']==='pending'?'selected':'' ?>>Pending</option>
                                    <option value="in-progress" <?= $warranty['warranty_status']==='in-progress'?'selected':'' ?>>In Progress</option>
                                    <option value="completed" <?= $warranty['warranty_status']==='completed'?'selected':'' ?>>Completed</option>
                                    <option value="cancelled" <?= $warranty['warranty_status']==='cancelled'?'selected':'' ?>>Cancelled</option>
                                </select>
                            </div>
                            <button type="submit" class="btn-cyan" style="padding:8px 20px;"><i class="bi bi-save-fill"></i> Save</button>
                        </div>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:8px;">
                            <i class="bi bi-info-circle"></i> Tip: Status updates automatically when you add movements
                        </div>
                    </form>
                </div>
            </div>

            <!-- Notes -->
            <div class="content-card mb-3">
                <div class="content-card-header"><div class="content-card-title"><i class="bi bi-sticky-fill"></i> Internal Notes</div></div>
                <div class="content-card-body">
                    <form method="POST" onsubmit="return updateNotes();">
                        <textarea name="notes" class="form-control-dark w-100" rows="3" style="margin-bottom:12px;"><?= Helper::e($warranty['notes'] ?? '') ?></textarea>
                        <button type="submit" class="btn-cyan btn-sm"><i class="bi bi-save-fill"></i> Update Notes</button>
                    </form>
                </div>
            </div>

            <!-- Movement Timeline -->
            <div class="content-card mb-3">
                <div class="content-card-header">
                    <div class="content-card-title"><i class="bi bi-clock-history"></i> Movement History (<?= count($movements) ?>)</div>
                    <button class="btn-outline-cyan btn-sm" data-bs-toggle="modal" data-bs-target="#movementModal"><i class="bi bi-plus-lg"></i> Add Movement</button>
                </div>
                <div class="content-card-body" style="padding:0;">
                    <?php if (empty($movements)): ?>
                    <div style="padding:16px;text-align:center;color:var(--text-muted);">
                        <i class="bi bi-inbox" style="font-size:32px;display:block;margin-bottom:8px;opacity:0.5;"></i>
                        <p style="margin:0;">No movements recorded yet. Click "Add Movement" to start tracking.</p>
                    </div>
                    <?php else: ?>
                    <div style="padding:16px;">
                        <?php foreach ($movements as $movement): ?>
                        <div style="display:flex;gap:16px;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--navy-border);">
                            <div style="flex-shrink:0;">
                                <div style="width:40px;height:40px;border-radius:50%;background:var(--cyan-dim);display:flex;align-items:center;justify-content:center;color:var(--cyan);font-weight:600;">
                                    <?php
                                    $icons = [
                                        'intake' => 'box-seam',
                                        'diagnosis' => 'search',
                                        'repair' => 'tools',
                                        'waiting_parts' => 'hourglass-split',
                                        'dispatch' => 'truck',
                                        'completed' => 'check-circle-fill',
                                        'cancelled' => 'x-circle-fill',
                                    ];
                                    $icon = $icons[$movement['movement_type']] ?? 'circle';
                                    ?>
                                    <i class="bi bi-<?= $icon ?>"></i>
                                </div>
                            </div>
                            <div style="flex:1;">
                                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                                    <span style="font-weight:600;color:var(--cyan);">
                                        <?= ucfirst(str_replace('_', ' ', $movement['movement_type'])) ?>
                                    </span>
                                    <span style="font-size:12px;color:var(--text-muted);">
                                        <?= Helper::formatDateTime($movement['movement_date']) ?>
                                    </span>
                                </div>
                                <p style="margin:0;color:var(--text-muted);font-size:13px;"><?= Helper::e($movement['description']) ?></p>
                                <?php if (!empty($movement['location'])): ?>
                                <div style="margin-top:8px;padding:8px;background:var(--navy-mid);border-radius:4px;font-size:12px;">
                                    <strong><i class="bi bi-geo-alt-fill"></i> Location:</strong> <?= Helper::e($movement['location']) ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($movement['dispatch_method'])): ?>
                                <div style="margin-top:4px;padding:8px;background:var(--navy-mid);border-radius:4px;font-size:12px;">
                                    <strong><i class="bi bi-truck"></i> Method:</strong> <?= Helper::e($movement['dispatch_method']) ?> 
                                    <?php if (!empty($movement['tracking_number'])): ?>
                                    — <strong><i class="bi bi-qr-code"></i> Tracking:</strong> <?= Helper::e($movement['tracking_number']) ?>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <div style="margin-top:8px;font-size:11px;color:var(--text-muted);">
                                    <i class="bi bi-person-circle"></i> By: <?= Helper::e($movement['created_by_name'] ?? 'Unknown') ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Parts Used -->
            <?php if (!empty($parts)): ?>
            <div class="content-card">
                <div class="content-card-header">
                    <div class="content-card-title"><i class="bi bi-hammer"></i> Parts Used</div>
                </div>
                <div style="overflow-x:auto;">
                    <table class="table-dark-custom">
                        <thead><tr><th>Part Name</th><th>Product</th><th>Qty</th><th>Notes</th></tr></thead>
                        <tbody>
                        <?php foreach ($parts as $part): ?>
                        <tr>
                            <td><strong><?= Helper::e($part['part_name']) ?></strong></td>
                            <td><?= Helper::e($part['product_name'] . ' — ' . $part['brand']) ?></td>
                            <td style="text-align:center;color:var(--cyan)"><?= $part['quantity'] ?></td>
                            <td style="color:var(--text-muted);font-size:12px;"><?= Helper::e($part['notes'] ?: '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Movement Modal -->
    <div class="modal fade modal-dark" id="movementModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Movement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="/sap-computers/warranty.php?view=<?= $warranty_id ?>&action=add_movement">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label-dark">Movement Type *</label>
                            <select name="movement_type" class="form-select-dark w-100" required>
                                <option value="">Select type…</option>
                                <option value="diagnosis">Diagnosis</option>
                                <option value="repair">Repair</option>
                                <option value="waiting_parts">Waiting for Parts</option>
                                <option value="dispatch">Dispatch</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-dark">Description *</label>
                            <textarea name="description" class="form-control-dark w-100" rows="3" required placeholder="Details of this movement…"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-dark">Location</label>
                            <input type="text" name="location" class="form-control-dark w-100" placeholder="e.g., Main Service Center (Gampaha)">
                        </div>

                        <div class="mb-3">
                            <label class="form-label-dark">Dispatch Method</label>
                            <input type="text" name="dispatch_method" class="form-control-dark w-100" placeholder="e.g., Courier (Sri Lanka Couriers)">
                        </div>

                        <div class="mb-3">
                            <label class="form-label-dark">Tracking Number</label>
                            <input type="text" name="tracking_number" class="form-control-dark w-100" placeholder="e.g., SL123456789">
                        </div>

                        <div style="background:var(--cyan-dim);border:1px solid var(--cyan);color:var(--cyan);padding:12px;border-radius:8px;font-size:12px;">
                            <i class="bi bi-info-circle"></i> <strong>Auto-Status Update:</strong><br>
                            Warranty status updates automatically based on movement type.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-cyan"><i class="bi bi-save-fill"></i> Add Movement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Warranty Verification Modal -->
    <div class="modal fade modal-dark" id="verifyModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Verify Warranty Purchase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label-dark">Search Customer</label>
                        <div style="display:flex;gap:8px;margin-bottom:12px;">
                            <input type="text" id="verifyCustomerSearch" class="form-control-dark" style="flex:1;" placeholder="Enter customer name or phone...">
                            <button type="button" class="btn-cyan btn-sm" onclick="searchVerifyCustomer()"><i class="bi bi-search"></i></button>
                        </div>
                        <div id="verifySearchResults" style="display:none;max-height:250px;overflow-y:auto;border:1px solid var(--navy-border);border-radius:4px;padding:8px;"></div>
                    </div>
                    <div id="verifyOrderDetails" style="display:none;padding:12px;background:var(--success-dim);border-radius:4px;border:1px solid var(--success);">
                        <div style="color:var(--success);font-weight:600;margin-bottom:8px;">✓ Select Order Below</div>
                        <div id="verifyOrderInfo" style="font-size:12px;color:var(--text-muted);"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-cyan" id="confirmVerifyBtn" onclick="confirmVerifyWarranty()" style="display:none;">
                        <i class="bi bi-check-circle"></i> Verify Purchase
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php
    require_once __DIR__ . '/views/layouts/footer.php';
    exit;
}

// Filters for warranty list
$filters = [
    'warranty_status' => $_GET['warranty_status'] ?? '',
    'serial_number' => $_GET['serial_number'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
];

$warranties = $warrantyModel->getAll(array_filter($filters));
$products = $productModel->getAll(['status' => 'active']);

require_once __DIR__ . '/views/layouts/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-header-title">Warranty Management</div>
        <div class="page-header-sub"><?= count($warranties) ?> warranty claims</div>
    </div>
    <button class="btn-cyan" data-bs-toggle="modal" data-bs-target="#warrantyModal">
        <i class="bi bi-plus-lg"></i> New Warranty Claim
    </button>
</div>

<!-- Filter Bar -->
<div class="content-card mb-4">
    <form method="GET" class="filter-bar">
        <select name="warranty_status" class="form-select-dark" style="width:160px;">
            <option value="">All Status</option>
            <option value="pending" <?= $filters['warranty_status']==='pending'?'selected':'' ?>>Pending</option>
            <option value="in-progress" <?= $filters['warranty_status']==='in-progress'?'selected':'' ?>>In Progress</option>
            <option value="completed" <?= $filters['warranty_status']==='completed'?'selected':'' ?>>Completed</option>
            <option value="cancelled" <?= $filters['warranty_status']==='cancelled'?'selected':'' ?>>Cancelled</option>
        </select>
        <input type="text" name="serial_number" class="form-control-dark" style="width:150px;" placeholder="Serial #" value="<?= Helper::e($filters['serial_number']) ?>">
        <input type="date" name="date_from" class="form-control-dark" style="width:150px;" value="<?= Helper::e($filters['date_from']) ?>">
        <input type="date" name="date_to" class="form-control-dark" style="width:150px;" value="<?= Helper::e($filters['date_to']) ?>">
        <button type="submit" class="btn-cyan"><i class="bi bi-funnel-fill"></i> Filter</button>
        <a href="/sap-computers/warranty.php" class="btn-ghost"><i class="bi bi-x-circle"></i> Clear</a>
    </form>
</div>

<!-- Warranty List -->
<div class="content-card">
    <div class="content-card-body">
        <table class="table-dark-custom datatable w-100">
            <thead>
                <tr>
                    <th>Claim #</th><th>Product</th><th>Serial #</th><th>Customer</th><th>Intake</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($warranties as $w): 
                $statusColor = [
                    'pending' => 'warning',
                    'in-progress' => 'cyan',
                    'completed' => 'success',
                    'cancelled' => 'danger',
                ];
                $color = $statusColor[$w['warranty_status']] ?? 'gray';
            ?>
                <tr>
                    <td><span class="text-cyan"><?= Helper::e($w['warranty_number']) ?></span></td>
                    <td><?= Helper::e($w['product_name'] . ' — ' . $w['brand']) ?></td>
                    <td style="color:var(--text-muted);font-size:12px;"><?= Helper::e($w['serial_number'] ?: '—') ?></td>
                    <td><?= Helper::e($w['customer_name']) ?></td>
                    <td><?= Helper::formatDate($w['intake_date']) ?></td>
                    <td><span style="background:var(--<?= $color ?>-dim);color:var(--<?= $color ?>);padding:4px 12px;border-radius:4px;font-size:11px;font-weight:600;"><?= strtoupper(str_replace('-', ' ', $w['warranty_status'])) ?></span></td>
                    <td>
                        <a href="/sap-computers/warranty.php?view=<?= $w['warranty_id'] ?>" class="action-btn view"><i class="bi bi-eye-fill"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Warranty Modal -->
<div class="modal fade modal-dark" id="warrantyModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-shield-check"></i> New Warranty Claim</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/sap-computers/warranty.php">
                <div class="modal-body" style="max-height: 600px; overflow-y: auto;">
                    
                    <!-- STEP 1: Customer Search -->
                    <div style="background:var(--navy-mid);padding:16px;border-radius:12px;margin-bottom:20px;border:1px solid var(--navy-border);">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                            <span style="background:var(--cyan);color:#000;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:14px;">1</span>
                            <span style="font-weight:600;font-size:14px;">Select Customer</span>
                        </div>
                        
                        <div class="mb-3" style="margin:0;">
                            <label class="form-label-dark" style="font-size:12px;">Search by Name or Phone</label>
                            <div style="display:flex;gap:8px;">
                                <input type="text" id="customerSearchBox" class="form-control-dark" style="flex:1;" placeholder="Type customer name or phone..." autocomplete="off">
                                <button type="button" class="btn-cyan" onclick="triggerCustomerSearch()"><i class="bi bi-search"></i></button>
                            </div>
                        </div>
                        
                        <!-- Search Results Dropdown -->
                        <div id="customerDropdown" style="display:none;max-height:250px;overflow-y:auto;border:1px solid var(--cyan);border-radius:8px;background:#0f172a;">
                        </div>
                    </div>

                    <!-- STEP 2: Purchase Selection -->
                    <div id="purchaseSection" style="background:var(--navy-mid);padding:16px;border-radius:12px;margin-bottom:20px;border:1px solid var(--navy-border);display:none;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                            <span style="background:var(--cyan);color:#000;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:14px;">2</span>
                            <span style="font-weight:600;font-size:14px;">Select Purchase</span>
                        </div>
                        
                        <div id="purchaseList" style="display:grid;gap:10px;">
                            <!-- Populated by JS -->
                        </div>
                    </div>

                    <!-- STEP 2.5: Product Selection (if multiple products in order) -->
                    <div id="productSelectionSection" style="background:var(--navy-mid);padding:16px;border-radius:12px;margin-bottom:20px;border:1px solid var(--navy-border);display:none;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                            <span style="background:var(--warning);color:#000;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:14px;">2.5</span>
                            <span style="font-weight:600;font-size:14px;">Select Product to Warranty</span>
                        </div>
                        
                        <div style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">This purchase contains multiple products. Please select which one needs warranty:</div>
                        <div id="productList" style="display:grid;gap:10px;">
                            <!-- Populated by JS -->
                        </div>
                    </div>

                    <!-- STEP 3: Selected Purchase Details -->
                    <div id="selectedPurchaseDetails" style="background:var(--success-dim);padding:16px;border-radius:12px;margin-bottom:20px;border:2px solid var(--success);display:none;">
                        <div style="margin-bottom:12px;">
                            <strong style="color:var(--success);display:flex;align-items:center;gap:6px;"><i class="bi bi-check-circle"></i> Selected for Warranty</strong>
                        </div>
                        <div style="font-size:13px;color:var(--text-muted);line-height:1.8;">
                            <div><strong>Product:</strong> <span id="selectedProductDisplay"></span></div>
                            <div><strong>SKU/Model:</strong> <span id="selectedProductModel"></span></div>
                            <div><strong>Purchase Date:</strong> <span id="selectedPurchaseDateDisplay"></span></div>
                            <div><strong>Customer:</strong> <span id="selectedCustomerDisplay"></span></div>
                            <div style="margin-top:12px;padding:12px;background:rgba(0,0,0,0.3);border-radius:6px;">
                                <strong><i class="bi bi-calendar-check"></i> Warranty Status:</strong><br>
                                <span id="warrantyStatusDisplay" style="font-size:14px;font-weight:600;margin-top:4px;display:block;"></span>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 4: Warranty Details Form -->
                    <div style="background:var(--navy-mid);padding:16px;border-radius:12px;border:1px solid var(--navy-border);">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
                            <span style="background:var(--cyan);color:#000;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:14px;">3</span>
                            <span style="font-weight:600;font-size:14px;">Warranty Details</span>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-dark">Product Name (from purchase)</label>
                            <input type="text" id="productNameDisplay" class="form-control-dark w-100" readonly style="opacity:0.7;cursor:not-allowed;">
                        </div>

                        <div class="mb-3">
                            <label class="form-label-dark">Serial Number *</label>
                            <input type="text" id="serialNumberInput" name="serial_number" class="form-control-dark w-100" placeholder="Device serial number" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-dark">Issue Description *</label>
                            <textarea id="issueDescriptionInput" name="issue_description" class="form-control-dark w-100" rows="4" required placeholder="Describe the problem with the device..."></textarea>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label-dark">Expected Completion</label>
                                <input type="date" name="expected_completion" class="form-control-dark w-100">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-dark">Payment Method</label>
                                <input type="text" id="paymentMethodDisplay" class="form-control-dark w-100" readonly style="opacity:0.7;cursor:not-allowed;">
                            </div>
                        </div>
                    </div>

                    <!-- Hidden fields for form submission -->
                    <input type="hidden" id="order_id" name="order_id" value="">
                    <input type="hidden" id="sale_id" name="sale_id" value="">
                    <input type="hidden" id="order_type" name="order_type" value="">
                    <input type="hidden" id="customer_id" name="customer_id" value="">
                    <input type="hidden" id="purchase_date" name="purchase_date" value="">
                    <input type="hidden" id="customer_name" name="customer_name" value="">
                    <input type="hidden" id="customer_phone" name="customer_phone" value="">
                    <input type="hidden" id="customer_email" name="customer_email" value="">
                    <input type="hidden" id="product_id" name="product_id" value="">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-outline-cyan" onclick="clearWarrantyForm()"><i class="bi bi-arrow-counterclockwise"></i> Clear</button>
                    <button type="submit" id="submitBtn" class="btn-cyan" disabled style="opacity:0.5;cursor:not-allowed;">
                        <i class="bi bi-save-fill"></i> Create Warranty Claim
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ========== ENHANCED WARRANTY FORM FUNCTIONS ==========

console.log('[WARRANTY] JavaScript loaded and executed');

let selectedOrder = null;
let selectedCustomer = null;
let allCustomers = [];

// Load all customers on modal open
function loadAllCustomers() {
    console.log('[WARRANTY] loadAllCustomers() called');
    // Try with encoded empty query first
    fetch(`/sap-computers/ajax/search_customer_purchases.php?q=`)
        .then(response => {
            console.log('[WARRANTY] Search response:', response.status);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            console.log('[WARRANTY] Loaded customers:', data.results?.length || 0, 'customers');
            allCustomers = data.results || [];
            displayCustomerDropdown(allCustomers);
        })
        .catch(e => {
            console.error('[WARRANTY] Load customers error:', e);
            // If that fails, try showing a message
            const dropdown = document.getElementById('customerDropdown');
            if (dropdown) {
                dropdown.innerHTML = '<div style="padding:16px;text-align:center;color:var(--danger);"><i class="bi bi-exclamation-circle"></i><p>Failed to load customers</p></div>';
                dropdown.style.display = 'block';
            }
        });
}

function triggerCustomerSearch() {
    const query = document.getElementById('customerSearchBox').value.trim();
    
    if (query === '') {
        displayCustomerDropdown(allCustomers);
        return;
    }
    
    searchCustomers(query);
}

function searchCustomers(query) {
    if (query.length < 1) {
        displayCustomerDropdown(allCustomers);
        return;
    }
    
    // Filter from already-loaded customers (faster, no AJAX needed)
    const filtered = allCustomers.filter(customer => 
        (customer.name && customer.name.toLowerCase().includes(query.toLowerCase())) || 
        (customer.phone && customer.phone.toLowerCase().includes(query.toLowerCase()))
    );
    
    console.log('Filtered customers:', filtered);
    displayCustomerDropdown(filtered);
}

function displayCustomerDropdown(customers) {
    const dropdown = document.getElementById('customerDropdown');
    
    if (!customers || customers.length === 0) {
        dropdown.innerHTML = '<div style="padding:16px;text-align:center;color:var(--text-muted);"><i class="bi bi-inbox"></i><p>No customers found</p></div>';
        dropdown.style.display = 'block';
        return;
    }

    let html = '';
    customers.forEach(customer => {
        html += `<div style="padding:12px;border-bottom:1px solid var(--navy-border);cursor:pointer;transition:0.2s;" 
                onmouseover="this.style.background='var(--navy-mid)'" 
                onmouseout="this.style.background='transparent'"
                onclick="selectCustomer(${JSON.stringify(customer).replace(/"/g, '&quot;')})">
            <div style="font-weight:600;color:var(--cyan);font-size:13px;">👤 ${customer.name}</div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">${customer.phone} | ${customer.email || 'N/A'}</div>
            <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;">`;
        
        customer.orders.forEach(order => {
            const statusColor = order.in_warranty ? 'success' : 'warning';
            const statusText = order.in_warranty ? '✓ IN WARRANTY' : '✕ EXPIRED';
            html += `<button type="button" class="btn-sm" 
                    onclick="event.stopPropagation(); selectPurchase(${JSON.stringify(order).replace(/"/g, '&quot;')}, ${JSON.stringify(customer).replace(/"/g, '&quot;')})"
                    style="padding:6px 10px;background:var(--${statusColor}-dim);color:var(--${statusColor});border:1px solid var(--${statusColor});border-radius:3px;font-size:10px;cursor:pointer;white-space:nowrap;">
                <strong>${order.items.map(i => i.product_name).join(', ')}</strong><br>${order.order_date} ${statusText}
            </button>`;
        });
        
        html += '</div></div>';
    });
    
    dropdown.innerHTML = html;
    dropdown.style.display = 'block';
}

function selectCustomer(customer) {
    selectedCustomer = customer;
    document.getElementById('customerSearchBox').value = customer.name;
    document.getElementById('customerDropdown').style.display = 'none';
    
    // Show purchase section
    if (customer.orders.length > 0) {
        document.getElementById('purchaseSection').style.display = 'block';
        
        let html = '';
        customer.orders.forEach(order => {
            const daysLeft = Math.max(0, Math.floor((new Date(order.expiry_date) - new Date()) / (1000 * 60 * 60 * 24)));
            const isExpired = daysLeft === 0 && new Date(order.expiry_date) < new Date();
            const statusColor = order.in_warranty ? 'success' : 'danger';
            const statusText = order.in_warranty ? `${daysLeft} days left` : 'EXPIRED';
            
            html += `<div style="padding:12px;background:var(--navy-dark);border:2px solid var(--${statusColor}-dim);border-radius:8px;cursor:pointer;transition:0.2s;"
                    onmouseover="this.style.background='var(--navy-mid)'"
                    onmouseout="this.style.background='var(--navy-dark)'"
                    onclick="selectPurchase(${JSON.stringify(order).replace(/"/g, '&quot;')}, ${JSON.stringify(customer).replace(/"/g, '&quot;')})">
                <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px;">
                    <div style="font-weight:600;color:var(--cyan);">${order.items.length > 1 ? `${order.items.length} products` : order.items.map(i => i.product_name).join(', ')}</div>
                    <span style="background:var(--${statusColor}-dim);color:var(--${statusColor});padding:4px 8px;border-radius:4px;font-size:11px;font-weight:600;">${statusText}</span>
                </div>
                <div style="font-size:12px;color:var(--text-muted);">
                    <div>Purchase Date: ${order.order_date}</div>
                    <div>Warranty Expires: <strong style="color:var(--${statusColor});">${order.expiry_date}</strong></div>
                    <div>Amount: PKR ${order.total_amount.toLocaleString()}</div>
                </div>
            </div>`;
        });
        
        document.getElementById('purchaseList').innerHTML = html;
    }
}

function selectPurchase(order, customer) {
    selectedOrder = order;
    selectedCustomer = customer;
    
    // Hide purchase section
    document.getElementById('purchaseSection').style.display = 'none';
    
    // If order has multiple products, show product selection
    if (order.items && order.items.length > 1) {
        document.getElementById('productSelectionSection').style.display = 'block';
        
        let html = '';
        order.items.forEach((item, idx) => {
            html += `<div style="padding:12px;background:var(--navy-dark);border:1px solid var(--navy-border);border-radius:8px;cursor:pointer;transition:0.2s;"
                    onmouseover="this.style.background='var(--navy-mid)'"
                    onmouseout="this.style.background='var(--navy-dark)'"
                    onclick="selectProduct(${JSON.stringify(item).replace(/"/g, '&quot;')}, ${JSON.stringify(order).replace(/"/g, '&quot;')}, ${JSON.stringify(customer).replace(/"/g, '&quot;')})">
                <div style="display:flex;align-items:start;gap:12px;">
                    <div style="flex:1;">
                        <div style="font-weight:600;color:var(--cyan);margin-bottom:4px;">${item.product_name}</div>
                        <div style="font-size:11px;color:var(--text-muted);">
                            <div>Brand: ${item.brand} | Model: ${item.model}</div>
                            <div>Warranty: ${item.warranty_months} months</div>
                            <div>Qty: ${item.quantity}</div>
                        </div>
                    </div>
                    <button type="button" class="btn-cyan" style="padding:8px 16px;white-space:nowrap;margin-top:0;">Select</button>
                </div>
            </div>`;
        });
        
        document.getElementById('productList').innerHTML = html;
        return; // Don't continue to show details yet
    } else if (order.items && order.items.length === 1) {
        // Only one product, auto-select it
        selectProduct(order.items[0], order, customer);
    }
}

function selectProduct(product, order, customer) {
    console.log('selectProduct called with:', { product_id: product.product_id, order_type: order.order_type, order_id: order.order_id, customer_name: customer.name });
    
    // Calculate warranty status
    const expiryDate = new Date(order.expiry_date);
    const today = new Date();
    const daysLeft = Math.floor((expiryDate - today) / (1000 * 60 * 60 * 24));
    const isValid = daysLeft > 0;
    
    let statusHtml = '';
    if (isValid) {
        statusHtml = `<span style="color:var(--success);font-weight:600;">✓ WARRANTY VALID</span><br><span style="font-size:12px;color:var(--text-muted);">${daysLeft} days remaining</span>`;
    } else {
        statusHtml = `<span style="color:var(--danger);font-weight:600;">✕ WARRANTY EXPIRED</span><br><span style="font-size:12px;color:var(--text-muted);">${Math.abs(daysLeft)} days ago</span>`;
    }
    
    // Update display fields
    document.getElementById('selectedProductDisplay').textContent = product.product_name;
    document.getElementById('selectedProductModel').textContent = `${product.brand} ${product.model} (Qty: ${product.quantity})`;
    document.getElementById('selectedPurchaseDateDisplay').textContent = order.order_date;
    document.getElementById('selectedCustomerDisplay').textContent = `${customer.name} (${customer.phone})`;
    document.getElementById('warrantyStatusDisplay').innerHTML = statusHtml;
    document.getElementById('productNameDisplay').value = product.product_name;
    document.getElementById('paymentMethodDisplay').value = order.order_type === 'pos' ? 'POS Sale' : 'Online Order';
    
    // Set hidden fields
    // For POS sales, the order_id is actually sale_id, so separate them
    if (order.order_type === 'pos') {
        document.getElementById('sale_id').value = order.order_id;
        document.getElementById('order_id').value = '';
    } else {
        document.getElementById('order_id').value = order.order_id;
        document.getElementById('sale_id').value = '';
    }
    document.getElementById('order_type').value = order.order_type || 'online';
    document.getElementById('customer_id').value = customer.customer_id || 0;
    document.getElementById('purchase_date').value = order.order_date;
    document.getElementById('customer_name').value = customer.name || '';
    document.getElementById('customer_phone').value = customer.phone || '';
    document.getElementById('customer_email').value = customer.email || '';
    document.getElementById('product_id').value = product.product_id;
    
    // Verify fields were set
    console.log('Fields after setting:', {
        order_id: document.getElementById('order_id').value,
        sale_id: document.getElementById('sale_id').value,
        order_type: document.getElementById('order_type').value,
        product_id: document.getElementById('product_id').value
    });
    
    // Hide selection sections, show details
    document.getElementById('productSelectionSection').style.display = 'none';
    document.getElementById('purchaseSection').style.display = 'none';
    document.getElementById('selectedPurchaseDetails').style.display = 'block';
    
    // Enable submit button
    document.getElementById('submitBtn').disabled = false;
    document.getElementById('submitBtn').style.opacity = '1';
    document.getElementById('submitBtn').style.cursor = 'pointer';
}

function clearWarrantyForm() {
    document.getElementById('customerSearchBox').value = '';
    document.getElementById('serialNumberInput').value = '';
    document.getElementById('issueDescriptionInput').value = '';
    document.getElementById('purchaseSection').style.display = 'none';
    document.getElementById('productSelectionSection').style.display = 'none';
    document.getElementById('selectedPurchaseDetails').style.display = 'none';
    document.getElementById('customerDropdown').style.display = 'none';
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('submitBtn').style.opacity = '0.5';
    document.getElementById('submitBtn').style.cursor = 'not-allowed';
    selectedOrder = null;
    selectedCustomer = null;
}

// Enable search on Enter key and show dropdown on focus
document.addEventListener('DOMContentLoaded', function() {
    console.log('[WARRANTY] DOMContentLoaded fired');
    const searchBox = document.getElementById('customerSearchBox');
    console.log('[WARRANTY] customerSearchBox found:', !!searchBox);
    if (searchBox) {
        // Load customers when search box gets focus
        searchBox.addEventListener('focus', function() {
            if (allCustomers.length === 0) {
                loadAllCustomers();
            } else {
                displayCustomerDropdown(allCustomers);
            }
        });
        
        // Search as user types
        searchBox.addEventListener('input', function(e) {
            if (e.target.value === '') {
                displayCustomerDropdown(allCustomers);
            } else {
                searchCustomers(e.target.value);
            }
        });
        
        // Trigger search on Enter
        searchBox.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                triggerCustomerSearch();
            }
        });
    }
    
    // Load all customers on page load
    console.log('[WARRANTY] Calling loadAllCustomers()');
    loadAllCustomers();
});

// Validate form before submit
document.addEventListener('DOMContentLoaded', function() {
    console.log('[WARRANTY] Form validation DOMContentLoaded fired');
    // Find form - try multiple selectors
    let form = document.querySelector('#warrantyModal form') || 
               document.querySelector('form[action*="warranty.php"]') ||
               document.querySelector('div[id*="Modal"] form') ||
               document.querySelectorAll('form[method="POST"]')[0];
    
    console.log('[WARRANTY] Form found:', !!form, form?.action || form?.id || 'unknown');
    
    if (!form) {
        console.error('[WARRANTY] WARNING: Could not find warranty form!');
        return;
    }
    
    console.log('[WARRANTY] Attaching submit listener to form');
    form.addEventListener('submit', function(e) {
        console.log('[WARRANTY] Form submit event fired!');
        if (!selectedOrder || !selectedCustomer) {
            e.preventDefault();
            alert('Please select a customer and purchase');
            return false;
        }
        
        const serialNumber = document.getElementById('serialNumberInput').value.trim();
        const issueDescription = document.getElementById('issueDescriptionInput').value.trim();
        
        if (!serialNumber && !issueDescription) {
            e.preventDefault();
            alert('Please fill in at least Serial Number or Issue Description');
            return false;
        }
        
        // Log hidden fields for debugging
        const orderId = document.getElementById('order_id').value;
        const saleId = document.getElementById('sale_id').value;
        const orderType = document.getElementById('order_type').value;
        const productId = document.getElementById('product_id').value;
        
        console.log('[WARRANTY] Form submit - Hidden fields:', {
            order_id: orderId,
            sale_id: saleId,
            order_type: orderType,
            product_id: productId,
            purchase_date: document.getElementById('purchase_date').value,
            customer_name: document.getElementById('customer_name').value
        });
        
        // Verify at least one ID is set
        if (!orderId && !saleId) {
            e.preventDefault();
            alert('ERROR: Neither order_id nor sale_id was set. Please try again.');
            return false;
        }
        
        return true;
    });
});
</script>

<?php
require_once __DIR__ . '/views/layouts/footer.php';
?>
