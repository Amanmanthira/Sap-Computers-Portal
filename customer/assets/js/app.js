/**
 * SAP Computers - Customer Website JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize event listeners
    initAddToCart();
    initRemoveFromCart();
    initQuantityUpdate();
    initWishlist();
    initSearch();
    initUserMenu();
    initCheckout();
});

/**
 * Add to Cart functionality
 */
function initAddToCart() {
    const addButtons = document.querySelectorAll('.add-to-cart, .add-to-cart-detailed');
    
    addButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.getAttribute('data-product-id');
            let quantity = 1;
            
            // Check if we're on product detail page
            const quantityInput = document.getElementById('quantity');
            if (quantityInput) {
                quantity = parseInt(quantityInput.value) || 1;
            }
            
            addToCart(productId, quantity);
        });
    });
}

function addToCart(productId, quantity = 1) {
    const formData = new FormData();
    formData.append('action', 'add_to_cart');
    formData.append('product_id', productId);
    formData.append('quantity', quantity);
    
    fetch('api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=add_to_cart&product_id=${productId}&quantity=${quantity}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Product added to cart!', 'success');
            updateCartCount();
        } else {
            showNotification(data.message || 'Error adding product to cart', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error adding product to cart', 'error');
    });
}

/**
 * Remove from Cart
 */
function initRemoveFromCart() {
    const removeButtons = document.querySelectorAll('.remove-item');
    
    removeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            removeFromCart(productId);
        });
    });
}

function removeFromCart(productId) {
    if (confirm('Are you sure you want to remove this item?')) {
        const formData = new FormData();
        formData.append('action', 'remove_from_cart');
        formData.append('product_id', productId);
        
        fetch('api/cart.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => console.error('Error:', error));
    }
}

/**
 * Update Quantity
 */
function initQuantityUpdate() {
    const quantityInputs = document.querySelectorAll('.quantity-input');
    
    quantityInputs.forEach(input => {
        input.addEventListener('change', function() {
            const productId = this.getAttribute('data-product-id');
            const quantity = parseInt(this.value) || 1;
            
            const formData = new FormData();
            formData.append('action', 'update_quantity');
            formData.append('product_id', productId);
            formData.append('quantity', quantity);
            
            fetch('api/cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        });
    });
}

/**
 * Wishlist functionality
 */
function initWishlist() {
    const wishlistButtons = document.querySelectorAll('.wishlist-btn, .wishlist-btn-detail');
    
    wishlistButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Check if user is logged in
            if (!isUserLoggedIn()) {
                window.location.href = 'login.php';
                return;
            }
            
            const productId = this.getAttribute('data-product-id');
            toggleWishlist(productId, this);
        });
    });
}

function toggleWishlist(productId, button) {
    const formData = new FormData();
    formData.append('action', 'toggle_wishlist');
    formData.append('product_id', productId);
    
    fetch('api/wishlist.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.added) {
                button.classList.add('active');
                showNotification('Added to wishlist!', 'success');
            } else {
                button.classList.remove('active');
                showNotification('Removed from wishlist!', 'info');
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

function initRemoveWishlist() {
    const removeButtons = document.querySelectorAll('.remove-wishlist');
    
    removeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            toggleWishlist(productId, this);
        });
    });
}

/**
 * Search functionality
 */
function initSearch() {
    const searchForm = document.querySelector('.search-bar form');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            const searchInput = this.querySelector('input[name="search"]');
            if (!searchInput.value.trim()) {
                e.preventDefault();
            }
        });
    }
}

/**
 * User Menu Toggle
 */
function initUserMenu() {
    const userToggle = document.querySelector('.user-toggle');
    if (userToggle) {
        userToggle.addEventListener('click', function() {
            const menu = this.nextElementSibling;
            if (menu) {
                menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
            }
        });
    }
}

/**
 * Checkout form validation
 */
function initCheckout() {
    const checkoutForm = document.getElementById('checkoutForm');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            // Validate form
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                } else {
                    field.classList.remove('error');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showNotification('Please fill in all required fields', 'error');
            }
        });
    }
}

/**
 * Utility Functions
 */

function isUserLoggedIn() {
    // Check if user menu exists (indicates logged in state)
    return document.querySelector('.user-menu') !== null;
}

function updateCartCount() {
    const cartCount = document.querySelector('.cart-count');
    if (cartCount) {
        fetch('api/cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=get_cart_count`
        })
        .then(response => response.json())
        .then(data => {
            cartCount.textContent = data.count || 0;
        });
    }
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background-color: ${getNotificationColor(type)};
        color: white;
        border-radius: 5px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        z-index: 9999;
        animation: slideIn 0.3s ease;
    `;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

function getNotificationColor(type) {
    const colors = {
        'success': '#10b981',
        'error': '#ef4444',
        'info': '#3b82f6',
        'warning': '#f59e0b'
    };
    return colors[type] || colors['info'];
}

/**
 * Price formatting
 */
function formatPrice(price) {
    return 'Rs. ' + parseFloat(price).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

/**
 * Filter and Sort Functions
 */
function updateFilters() {
    const form = document.querySelector('.filter-group form') || {};
    const category = document.getElementById('categoryFilter')?.value;
    const inStock = document.getElementById('inStockFilter')?.checked;
    
    let url = 'shop.php?';
    if (category) url += 'category=' + category;
    if (inStock) url += '&stock=1';
    
    window.location.href = url;
}

function updatePriceRange() {
    const priceSlider = document.getElementById('priceSlider');
    if (priceSlider) {
        document.getElementById('priceValue').textContent = 
            parseInt(priceSlider.value).toLocaleString();
    }
}

/**
 * Add animation styles
 */
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    input.error {
        border-color: #ef4444 !important;
    }
    
    .wishlist-btn.active i {
        color: #ef4444;
        fill: #ef4444;
    }
`;
document.head.appendChild(style);
