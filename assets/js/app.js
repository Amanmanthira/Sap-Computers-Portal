/* SAP Computers IMS — Main JavaScript */

document.addEventListener('DOMContentLoaded', function () {
    // ---- Sidebar Toggle ----
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.getElementById('menuToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const overlay = document.getElementById('sidebarOverlay');

function openSidebar() {
    sidebar?.classList.add('open');
    overlay?.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    sidebar?.classList.remove('open');
    overlay?.classList.remove('show');
    document.body.style.overflow = '';
}

menuToggle?.addEventListener('click', openSidebar);
sidebarClose?.addEventListener('click', closeSidebar);
overlay?.addEventListener('click', closeSidebar);

// ---- Auto dismiss flash alerts ----
setTimeout(() => {
    document.querySelectorAll('.flash-alert').forEach(el => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
        bsAlert.close();
    });
}, 4000);

// ---- Global Search (AJAX) ----
const searchInput = document.getElementById('globalSearch');
const searchResults = document.getElementById('searchResults');
let searchTimer = null;

searchInput?.addEventListener('input', function () {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 2) { searchResults.classList.remove('show'); return; }

    searchTimer = setTimeout(() => {
        fetch(`/sap-computers/ajax/search.php?q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(data => {
                if (!data.results || data.results.length === 0) {
                    searchResults.innerHTML = '<div class="search-result-item"><span class="text-muted small">No results found</span></div>';
                } else {
                    searchResults.innerHTML = data.results.map(item => `
                        <div class="search-result-item" onclick="window.location='${item.url}'">
                            <i class="bi ${item.icon} result-icon"></i>
                            <div>
                                <div class="result-title">${item.title}</div>
                                <div class="result-sub">${item.subtitle}</div>
                            </div>
                        </div>
                    `).join('');
                }
                searchResults.classList.add('show');
            })
            .catch(() => searchResults.classList.remove('show'));
    }, 300);
});

document.addEventListener('click', function (e) {
    if (!e.target.closest('#globalSearchWrapper')) {
        searchResults?.classList.remove('show');
    }
});

// ---- DataTables Init ----
if (typeof $.fn.DataTable !== 'undefined') {
    $('.datatable').DataTable({
        pageLength: 20,
        language: {
            search: '',
            searchPlaceholder: 'Search…',
            emptyTable: 'No records found',
            zeroRecords: 'No matching records',
        },
        responsive: true,
        dom: "<'row'<'col-sm-6'l><'col-sm-6'f>>rt<'row'<'col-sm-5'i><'col-sm-7'p>>",
    });
}

// ---- Confirm Delete ----
document.querySelectorAll('[data-confirm]').forEach(btn => {
    btn.addEventListener('click', function (e) {
        const msg = this.dataset.confirm || 'Are you sure you want to delete this record?';
        if (!confirm(msg)) e.preventDefault();
    });
});

// ---- AJAX Form Submissions ----
document.querySelectorAll('form[data-ajax]').forEach(form => {
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const submitBtn = this.querySelector('[type=submit]');
        const originalText = submitBtn?.innerHTML;
        if (submitBtn) submitBtn.innerHTML = '<span class="spinner-cyan"></span>';

        fetch(this.action, {
            method: 'POST',
            body: new FormData(this),
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('success', data.message || 'Saved successfully!');
                if (data.redirect) setTimeout(() => window.location = data.redirect, 800);
                else if (data.reload) setTimeout(() => window.location.reload(), 800);
                const modal = this.closest('.modal');
                if (modal) bootstrap.Modal.getInstance(modal)?.hide();
            } else {
                showToast('error', data.message || 'An error occurred.');
            }
        })
        .catch(() => showToast('error', 'Request failed. Please try again.'))
        .finally(() => { if (submitBtn) submitBtn.innerHTML = originalText; });
    });
});

// ---- Toast Notifications ----
function showToast(type, message) {
    const container = document.getElementById('toastContainer') || createToastContainer();
    const id = 'toast_' + Date.now();
    const icon = type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill';
    const color = type === 'success' ? '#00d68f' : '#ff4757';

    const toastEl = document.createElement('div');
    toastEl.id = id;
    toastEl.className = 'toast align-items-center border-0';
    toastEl.style.cssText = `background:#162236;color:#fff;border:1px solid #243550 !important;border-radius:10px;`;
    toastEl.setAttribute('role', 'alert');
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2">
                <i class="bi bi-${icon}" style="color:${color};font-size:16px;"></i>
                <span style="font-size:13.5px;">${message}</span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    container.appendChild(toastEl);
    new bootstrap.Toast(toastEl, { delay: 3500 }).show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}

function createToastContainer() {
    const c = document.createElement('div');
    c.id = 'toastContainer';
    c.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    c.style.zIndex = '9999';
    document.body.appendChild(c);
    return c;
}

// ---- GRN Item Management ----
let grnItemCount = 0;

function addGRNItem() {
    grnItemCount++;
    const container = document.getElementById('grnItems');
    const row = document.createElement('div');
    row.className = 'grn-item-row mb-3';
    row.id = `grnRow_${grnItemCount}`;
    row.innerHTML = `
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label-dark">Product</label>
                <select name="items[${grnItemCount}][product_id]" class="form-select-dark w-100 product-select" required onchange="fillUnitCost(this, ${grnItemCount})">
                    <option value="">Select product…</option>
                    ${window.productOptions || ''}
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label-dark">Quantity</label>
                <input type="number" name="items[${grnItemCount}][quantity]" class="form-control-dark w-100 qty-input"
                    min="1" value="1" required onchange="calcRowTotal(${grnItemCount})">
            </div>
            <div class="col-md-2">
                <label class="form-label-dark">Unit Cost (Rs.)</label>
                <input type="number" name="items[${grnItemCount}][unit_cost]" class="form-control-dark w-100 cost-input"
                    id="unitCost_${grnItemCount}" min="0" step="0.01" value="0.00" required onchange="calcRowTotal(${grnItemCount})">
            </div>
            <div class="col-md-2">
                <label class="form-label-dark">Total</label>
                <input type="text" id="rowTotal_${grnItemCount}" class="form-control-dark w-100" readonly value="Rs. 0.00">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn-ghost w-100" onclick="removeGRNItem(${grnItemCount})">
                    <i class="bi bi-trash3"></i> Remove
                </button>
            </div>
        </div>
    `;
    container.appendChild(row);
}

function removeGRNItem(id) {
    document.getElementById(`grnRow_${id}`)?.remove();
    calcGRNTotal();
}

function fillUnitCost(select, rowId) {
    const option = select.options[select.selectedIndex];
    const costPrice = option.dataset.cost || 0;
    const costInput = document.getElementById(`unitCost_${rowId}`);
    if (costInput) {
        costInput.value = costPrice;
        calcRowTotal(rowId);
    }
}

function calcRowTotal(rowId) {
    const qty = parseFloat(document.querySelector(`#grnRow_${rowId} .qty-input`)?.value || 0);
    const cost = parseFloat(document.getElementById(`unitCost_${rowId}`)?.value || 0);
    const total = qty * cost;
    const totalField = document.getElementById(`rowTotal_${rowId}`);
    if (totalField) totalField.value = 'Rs. ' + total.toLocaleString('en-LK', { minimumFractionDigits: 2 });
    calcGRNTotal();
}

function calcGRNTotal() {
    let total = 0;
    document.querySelectorAll('.qty-input').forEach((qtyInput, i) => {
        const row = qtyInput.closest('.grn-item-row');
        if (!row) return;
        const qty = parseFloat(qtyInput.value || 0);
        const cost = parseFloat(row.querySelector('.cost-input')?.value || 0);
        total += qty * cost;
    });
    const totalEl = document.getElementById('grnGrandTotal');
    if (totalEl) totalEl.textContent = 'Rs. ' + total.toLocaleString('en-LK', { minimumFractionDigits: 2 });
}

// ---- POS / Sale Item Management ----
let saleItemCount = 0;

function addSaleItem() {
    saleItemCount++;
    const container = document.getElementById('saleItems');
    const row = document.createElement('div');
    row.className = 'sale-item-row mb-3';
    row.id = `saleRow_${saleItemCount}`;
    row.innerHTML = `
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label-dark">Product</label>
                <select name="items[${saleItemCount}][product_id]" class="form-select-dark w-100 product-select" required onchange="fillSalePrice(this, ${saleItemCount})">
                    <option value="">Select product…</option>
                    ${window.productOptions || ''}
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label-dark">Quantity</label>
                <input type="number" name="items[${saleItemCount}][quantity]" class="form-control-dark w-100 qty-input"
                    min="1" value="1" required onchange="calcSaleRowTotal(${saleItemCount})">
            </div>
            <div class="col-md-2">
                <label class="form-label-dark">Unit Price (Rs.)</label>
                <input type="number" name="items[${saleItemCount}][unit_price]" class="form-control-dark w-100 price-input"
                    id="unitPrice_${saleItemCount}" min="0" step="0.01" value="0.00" required onchange="calcSaleRowTotal(${saleItemCount})">
            </div>
            <div class="col-md-2">
                <label class="form-label-dark">Total</label>
                <input type="text" id="saleRowTotal_${saleItemCount}" class="form-control-dark w-100" readonly value="Rs. 0.00">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn-ghost w-100" onclick="removeSaleItem(${saleItemCount})">
                    <i class="bi bi-trash3"></i> Remove
                </button>
            </div>
        </div>
    `;
    container.appendChild(row);
}

function removeSaleItem(id) {
    document.getElementById(`saleRow_${id}`)?.remove();
    calcSaleTotal();
}

function fillSalePrice(select, rowId) {
    const option = select.options[select.selectedIndex];
    const price = option.dataset.price || 0;
    const priceInput = document.getElementById(`unitPrice_${rowId}`);
    if (priceInput) {
        priceInput.value = price;
        calcSaleRowTotal(rowId);
    }
}

function calcSaleRowTotal(rowId) {
    const qty = parseFloat(document.querySelector(`#saleRow_${rowId} .qty-input`)?.value || 0);
    const price = parseFloat(document.getElementById(`unitPrice_${rowId}`)?.value || 0);
    const total = qty * price;
    const totalField = document.getElementById(`saleRowTotal_${rowId}`);
    if (totalField) totalField.value = 'Rs. ' + total.toLocaleString('en-LK', { minimumFractionDigits: 2 });
    calcSaleTotal();
}

function calcSaleTotal() {
    let total = 0;
    document.querySelectorAll('#saleItems .qty-input').forEach((qtyInput, i) => {
        const row = qtyInput.closest('.sale-item-row');
        if (!row) return;
        const qty = parseFloat(qtyInput.value || 0);
        const price = parseFloat(row.querySelector('.price-input')?.value || 0);
        total += qty * price;
    });
    const totalEl = document.getElementById('saleGrandTotal');
    if (totalEl) totalEl.textContent = 'Rs. ' + total.toLocaleString('en-LK', { minimumFractionDigits: 2 });
}
