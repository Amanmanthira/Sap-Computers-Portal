<?php
require_once __DIR__ . '/includes/bootstrap.php';
Session::requireSeller();

$saleModel    = new SaleModel();
$branchModel  = new BranchModel();
$productModel = new ProductModel();

// --- BACKEND: SAVE SALE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'save_sale') {
    ob_clean();
    header('Content-Type: application/json');
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Final Backend Security Check
        if (!$data || empty($data['items'])) throw new Exception("Cart is empty.");
        if (empty(trim($data['cust_name']))) throw new Exception("Customer Name is required.");
        if (strlen(trim($data['cust_phone'])) !== 10) throw new Exception("Valid 10-digit Phone Number is required.");

        $customNotes = "Cust: " . trim($data['cust_name']) . " | Ph: " . trim($data['cust_phone']) . " | Pay: " . $data['pay_method'];

        $header = [
            'branch_id'  => (int)$data['branch_id'],
            'created_by' => Session::get('user_id'),
            'notes'      => $customNotes
        ];

        $items = [];
        foreach ($data['items'] as $item) {
            $items[] = [
                'product_id'  => (int)$item['id'],
                'quantity'    => (int)$item['qty'],
                'unit_price'  => (float)$item['price'],
                'total_price' => (float)$item['price'] * (int)$item['qty']
            ];
        }

        $sale_id = $saleModel->create($header, $items);
        if ($sale_id) {
            $sale = $saleModel->getById($sale_id);
            echo json_encode(['success' => true, 'sale_id' => $sale_id, 'sale_number' => $sale['sale_number'] ?? 'N/A']);
        } else {
            throw new Exception("Database error.");
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$branches = $branchModel->getActive();
$products = $productModel->getAll(['status' => 'active']);
require_once __DIR__ . '/views/layouts/header.php';
?>

<style>
    :root { 
        --pos-dark: #0f172a; 
        --pos-card: #1e293b; 
        --accent: #38bdf8; 
        --border: #334155; 
        --success: #22c55e; 
        --danger: #ef4444; 
    }
    
    body { background: var(--pos-dark); color: #f1f5f9; overflow: hidden; font-family: 'Inter', sans-serif; }

    /* Layout Structure */
    .pos-grid { display: flex; height: calc(100vh - 70px); gap: 15px; padding: 15px; }
    
    /* Catalog Styling */
    .catalog-container { flex: 1; display: flex; flex-direction: column; background: var(--pos-card); border-radius: 24px; padding: 25px; border: 1px solid var(--border); }
    .search-bar { background: #0f172a !important; border: 1px solid var(--border) !important; color: white !important; border-radius: 12px; padding: 12px 20px; margin-bottom: 20px; transition: 0.3s; }
    .search-bar:focus { border-color: var(--accent) !important; box-shadow: 0 0 15px rgba(56, 189, 248, 0.2); }
    
    .p-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 18px; overflow-y: auto; padding-right: 10px; }
    .p-card { background: #334155; border-radius: 18px; padding: 18px; cursor: pointer; border: 1px solid transparent; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .p-card:hover { border-color: var(--accent); transform: translateY(-5px); background: #3e4e63; box-shadow: 0 12px 24px rgba(0,0,0,0.3); }
    .brand-tag { font-size: 10px; text-transform: uppercase; color: var(--accent); font-weight: 800; letter-spacing: 1.5px; margin-bottom: 5px; }
    .p-name { font-weight: 600; font-size: 15px; margin-bottom: 10px; height: 42px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .p-price { font-weight: 800; color: #fff; font-size: 17px; }

    /* Right Side Checkout */
    .cart-pane { width: 460px; background: #0f172a; border: 1px solid var(--border); border-radius: 24px; display: flex; flex-direction: column; box-shadow: -10px 0 30px rgba(0,0,0,0.2); }

    /* Input Zone (Premium Styling) */
    .customer-zone { background: rgba(30, 41, 59, 0.5); border-radius: 20px; padding: 20px; margin: 15px; border: 1px solid var(--border); }
    .input-label { font-size: 11px; font-weight: 700; color: var(--accent); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
    .cool-input { background: #0f172a !important; border: 1px solid #334155 !important; color: #f1f5f9 !important; border-radius: 12px !important; padding: 10px 14px !important; font-size: 14px !important; transition: 0.3s !important; }
    .cool-input:focus { border-color: var(--accent) !important; box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2) !important; }
    .is-valid { border-color: var(--success) !important; box-shadow: 0 0 8px rgba(34, 197, 94, 0.2) !important; }
    .is-invalid { border-color: var(--danger) !important; box-shadow: 0 0 8px rgba(239, 68, 68, 0.2) !important; }

    /* Cart Items Zone */
    .cart-items { flex: 1; overflow-y: auto; padding: 0 15px; }
    .cart-row { background: #1e293b; padding: 14px; border-radius: 14px; margin-bottom: 10px; border: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
    .qty-btn { width: 32px; height: 32px; border-radius: 8px; border: none; background: #475569; color: white; font-weight: bold; transition: 0.2s; }
    .qty-btn:hover { background: var(--accent); color: #000; }

    /* Footer Zone */
    .total-box { padding: 25px; background: #1e293b; border-top: 1px solid var(--border); border-radius: 0 0 24px 24px; }
    .btn-finalize { background: var(--accent); color: #000; font-weight: 800; width: 100%; padding: 16px; border-radius: 14px; border: none; font-size: 1.1rem; letter-spacing: 1px; transition: 0.3s; }
    .btn-finalize:hover { transform: scale(1.02); filter: brightness(1.1); }

    /* Real-Time Invoice */
    @media print {
        body * { visibility: hidden; }
        #printSection, #printSection * { visibility: visible; }
        #printSection { position: absolute; left: 0; top: 0; width: 80mm; font-family: 'Courier New', monospace; line-height: 1.3; color: #000; background: #fff; padding: 4mm; }
        .print-sep { border-top: 1px dashed #000; margin: 8px 0; }
    }
</style>

<div class="pos-grid">
    <div class="catalog-container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">📦 Product Catalog</h4>
            <input type="text" id="search" class="form-control search-bar w-50" placeholder="Search by name or brand...">
        </div>
        
        <div class="p-grid" id="productGrid">
            <?php foreach ($products as $p): ?>
                <div class="p-card" onclick="add(<?= $p['product_id'] ?>, '<?= addslashes($p['product_name']) ?>', <?= $p['selling_price'] ?>)">
                    <div class="brand-tag"><?= $p['brand'] ?? 'General' ?></div>
                    <div class="p-name"><?= $p['product_name'] ?></div>
                    <div class="p-price">Rs. <?= number_format($p['selling_price'], 2) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="cart-pane">
        <div class="customer-zone">
            <div class="input-label mb-3" style="font-size: 13px; color: #fff;">
                <span style="width:12px; height:12px; background:var(--accent); border-radius:3px;"></span> 
                Checkout Information
            </div>
            
            <div class="row g-2">
                <div class="col-12">
                    <label class="input-label">👤 Customer Name <span class="text-danger">*</span></label>
                    <input type="text" id="cust_name" class="form-control cool-input" placeholder="Full Name" maxlength="50">
                </div>
                <div class="col-12">
                    <label class="input-label">📞 Mobile Number <span class="text-danger">*</span></label>
                    <input type="text" id="cust_phone" class="form-control cool-input" placeholder="07XXXXXXXX" maxlength="10">
                </div>
                <div class="col-6 mt-2">
                    <label class="input-label">📍 Branch</label>
                    <select id="branch_id" class="form-select cool-input">
                        <?php foreach($branches as $b): ?>
                            <option value="<?= $b['branch_id'] ?>"><?= $b['branch_name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 mt-2">
                    <label class="input-label">💳 Payment</label>
                    <select id="pay_method" class="form-select cool-input">
                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="cart-items" id="cart">
            <div class="text-center text-muted mt-5">Your cart is empty</div>
        </div>

        <div class="total-box">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="h6 text-muted mb-0">Net Amount:</span>
                <span class="h3 fw-bold text-info mb-0" id="totalDisplay">Rs. 0.00</span>
            </div>
            <button class="btn-finalize" onclick="save()">FINALIZE & PRINT BILL</button>
        </div>
    </div>
</div>

<div id="printSection" style="display:none;"></div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let items = [];
const nameInp = document.getElementById('cust_name');
const phoneInp = document.getElementById('cust_phone');

// Real-time Input Validation UI
function updateValidation(el, isValid) {
    if (el.value.length === 0) {
        el.classList.remove('is-valid', 'is-invalid');
    } else if (isValid) {
        el.classList.add('is-valid');
        el.classList.remove('is-invalid');
    } else {
        el.classList.add('is-invalid');
        el.classList.remove('is-valid');
    }
}

nameInp.addEventListener('input', () => updateValidation(nameInp, nameInp.value.trim().length >= 3));
phoneInp.addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, ''); // Numbers only
    updateValidation(phoneInp, this.value.length === 10);
});

function add(id, name, price) {
    let exist = items.find(x => x.id === id);
    if(exist) exist.qty++; else items.push({id, name, price, qty: 1});
    render();
}

function updateQty(id, delta) {
    let i = items.find(x => x.id === id);
    if(i) {
        i.qty += delta;
        if(i.qty <= 0) items = items.filter(x => x.id !== id);
    }
    render();
}

function render() {
    const box = document.getElementById('cart');
    if(items.length === 0) {
        box.innerHTML = '<div class="text-center text-muted mt-5">Your cart is empty</div>';
        document.getElementById('totalDisplay').innerText = "Rs. 0.00";
        return;
    }
    let total = 0;
    box.innerHTML = items.map(i => {
        total += (i.price * i.qty);
        return `
        <div class="cart-row animate__animated animate__fadeIn">
            <div>
                <div class="fw-bold text-truncate" style="max-width:200px">${i.name}</div>
                <small class="text-info">Rs. ${i.price.toLocaleString()} x ${i.qty}</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="qty-btn" onclick="updateQty(${i.id}, -1)">-</button>
                <span class="fw-bold" style="min-width:20px; text-align:center">${i.qty}</span>
                <button class="qty-btn" onclick="updateQty(${i.id}, 1)">+</button>
            </div>
        </div>`;
    }).join('');
    document.getElementById('totalDisplay').innerText = "Rs. " + total.toLocaleString(undefined, {minimumFractionDigits: 2});
}

async function save() {
    if(items.length === 0) return Swal.fire('Wait!', 'The cart is empty.', 'warning');
    if(nameInp.value.trim().length < 3) return Swal.fire('Name Required', 'Please enter a valid customer name.', 'error');
    if(phoneInp.value.length !== 10) return Swal.fire('Invalid Phone', 'Phone number must be exactly 10 digits.', 'error');

    const data = {
        branch_id: document.getElementById('branch_id').value,
        cust_name: nameInp.value.trim(),
        cust_phone: phoneInp.value.trim(),
        pay_method: document.getElementById('pay_method').value,
        items: items
    };

    Swal.showLoading();

    try {
        const res = await fetch('pos.php?action=save_sale', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const out = await res.json();
        if(out.success) {
            preparePrint(data, out.sale_number);
            items = []; render();
            nameInp.value = ''; phoneInp.value = '';
            nameInp.classList.remove('is-valid'); phoneInp.classList.remove('is-valid');
            Swal.fire('Success', 'Sale saved and printing...', 'success');
        } else {
            Swal.fire('Error', out.message, 'error');
        }
    } catch(e) { Swal.fire('Error', 'Server Connection Failed', 'error'); }
}

function preparePrint(data, saleNo) {
    const printArea = document.getElementById('printSection');
    let total = items.reduce((sum, i) => sum + (i.price * i.qty), 0);
    
    let html = `
        <div style="text-align:center; font-weight: bold; font-size: 18px;">SAP COMPUTERS</div>
        <div style="text-align:center; font-size: 11px;">123 Tech Avenue, Colombo, Sri Lanka<br>Hotline: +94 77 123 4567</div>
        <div class="print-sep"></div>
        <div style="font-size: 11px;">
            <b>Invoice:</b> ${saleNo}<br>
            <b>Date:</b> ${new Date().toLocaleString()}<br>
            <b>Customer:</b> ${data.cust_name}<br>
            <b>Contact:</b> ${data.cust_phone}
        </div>
        <div class="print-sep"></div>
        <table style="width:100%; font-size: 11px; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 1px solid #000;">
                    <th align="left">Description</th>
                    <th align="center">Qty</th>
                    <th align="right">Amount</th>
                </tr>
            </thead>
            <tbody>
                ${items.map(i => `
                    <tr>
                        <td style="padding-top:5px;">${i.name}</td>
                        <td align="center">${i.qty}</td>
                        <td align="right">${(i.price * i.qty).toFixed(2)}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
        <div class="print-sep" style="margin-top:10px;"></div>
        <div style="display:flex; justify-content:space-between; font-weight:bold; font-size: 15px;">
            <span>TOTAL DUE</span><span>Rs. ${total.toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
        </div>
        <div style="font-size: 11px; margin-top:5px;">
            <b>Payment Mode:</b> ${data.pay_method}
        </div>
        <div class="print-sep"></div>
        <div style="text-align:center; font-size: 10px; margin-top:15px;">
            *** Thank You for Shopping! ***<br>
            Goods sold are not returnable.<br>
            Software by Gemini AI
        </div>
    `;
    printArea.innerHTML = html;
    printArea.style.display = 'block';
    window.print();
    printArea.style.display = 'none';
}

// Search Logic
document.getElementById('search').addEventListener('input', e => {
    let v = e.target.value.toLowerCase();
    document.querySelectorAll('.p-card').forEach(el => {
        el.style.display = el.innerText.toLowerCase().includes(v) ? 'block' : 'none';
    });
});
</script>

<?php require_once __DIR__ . '/views/layouts/footer.php'; ?>