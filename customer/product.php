<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/Helper.php';
require_once 'includes/customer_functions.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$is_logged_in = isset($_SESSION['customer_id']);

if (!$product_id) {
    header('Location: shop.php');
    exit;
}

try {
    $pdo = Database::getInstance();
    
    // Get product details
    $stmt = $pdo->prepare("SELECT p.*, c.category_name
                          FROM products p
                          LEFT JOIN categories c ON p.category_id = c.category_id
                          WHERE p.product_id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        header('Location: shop.php');
        exit;
    }
    
    // Get stock for this product
    $stock_stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) as total_qty FROM stock WHERE product_id = ?");
    $stock_stmt->execute([$product_id]);
    $stock_data = $stock_stmt->fetch(PDO::FETCH_ASSOC);
    $product['total_qty'] = (int)($stock_data['total_qty'] ?? 0);
    
    // Get related products
    $stmt = $pdo->prepare("SELECT p.product_id, p.product_name, p.brand, p.model, p.selling_price, p.category_id
                          FROM products p
                          WHERE p.category_id = ? AND p.product_id != ?
                          LIMIT 4");
    $stmt->execute([$product['category_id'], $product_id]);
    $related_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add stock for each related product
    if (!empty($related_products)) {
        foreach ($related_products as &$rprod) {
            $rstock_stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) as total_qty FROM stock WHERE product_id = ?");
            $rstock_stmt->execute([$rprod['product_id']]);
            $rstock_data = $rstock_stmt->fetch(PDO::FETCH_ASSOC);
            $rprod['total_qty'] = (int)($rstock_data['total_qty'] ?? 0);
        }
    }
    
    // Get reviews
    $stmt = $pdo->prepare("SELECT * FROM reviews WHERE product_id = ? ORDER BY created_at DESC");
    $stmt->execute([$product_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Product Page Error: " . $e->getMessage());
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['product_name']) ?> - SAP Computers</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        // Set global variable for customer login status
        window.customerLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
    </script>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="product-detail-container">
        <div class="container">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="index.php">Home</a> / <a href="shop.php">Shop</a> / <span><?= htmlspecialchars($product['product_name']) ?></span>
            </div>

            <!-- Product Details -->
            <div class="product-detail">
                <div class="product-images">
                    <div class="main-image">
                        <div class="product-placeholder">
                            <i class="fas fa-laptop"></i>
                        </div>
                    </div>
                    <div class="image-thumbnails">
                        <div class="product-placeholder-thumb">
                            <i class="fas fa-laptop"></i>
                        </div>
                    </div>
                </div>

                <div class="product-details-info">
                    <h1><?= htmlspecialchars($product['product_name']) ?></h1>
                    
                    <div class="product-meta">
                        <span class="category"><?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?></span>
                        <span class="model">Model: <?= htmlspecialchars($product['model'] ?? 'N/A') ?></span>
                        <span class="brand">Brand: <?= htmlspecialchars($product['brand'] ?? 'N/A') ?></span>
                    </div>

                    <div class="product-rating">
                        <div class="stars">★★★★★</div>
                        <span class="rating-count">(24 customer reviews)</span>
                    </div>

                    <div class="product-price">
                        <h2>Rs. <?= number_format($product['selling_price'], 2) ?></h2>
                        <?php if (isset($product['cost_price']) && $product['cost_price'] > 0): ?>
                            <p class="original-price">Wholesale Cost: Rs. <?= number_format($product['cost_price'], 2) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="stock-status-detail">
                        <?php if ($product['total_qty'] > 0): ?>
                            <span class="in-stock"><i class="fas fa-check-circle"></i> In Stock (<?= (int)$product['total_qty'] ?> available)</span>
                        <?php else: ?>
                            <span class="out-of-stock"><i class="fas fa-times-circle"></i> Out of Stock</span>
                        <?php endif; ?>
                    </div>

                    <div class="product-description">
                        <h3>Description</h3>
                        <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                    </div>

                    <!-- Quantity and Add to Cart -->
                    <div class="purchase-section">
                        <div class="quantity-selector">
                            <label>Quantity:</label>
                            <input type="number" id="quantity" min="1" max="<?= (int)$product['total_qty'] ?>" value="1" <?= $product['total_qty'] <= 0 ? 'disabled' : '' ?>>
                        </div>
                        
                        <?php if ($product['total_qty'] > 0): ?>
                            <button class="btn btn-success btn-lg add-to-cart-detailed" data-product-id="<?= (int)$product['product_id'] ?>">
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                        <?php else: ?>
                            <button class="btn btn-disabled btn-lg" disabled>Out of Stock</button>
                        <?php endif; ?>
                        
                        <button class="btn btn-outline wishlist-btn-detail" data-product-id="<?= (int)$product['product_id'] ?>">
                            <i class="far fa-heart"></i> Add to Wishlist
                        </button>
                    </div>

                    <!-- Features -->
                    <div class="product-features">
                        <div class="feature">
                            <i class="fas fa-shipping-fast"></i>
                            <span>Free Shipping on orders over Rs. 2000</span>
                        </div>
                        <div class="feature">
                            <i class="fas fa-shield-alt"></i>
                            <span>Secure Checkout</span>
                        </div>
                        <div class="feature">
                            <i class="fas fa-undo"></i>
                            <span>Easy Returns</span>
                        </div>
                        <div class="feature">
                            <i class="fas fa-headset"></i>
                            <span>24/7 Customer Support</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reviews Section -->
            <section class="reviews-section">
                <h2>Customer Reviews</h2>
                <div class="reviews-container">
                    <?php if (count($reviews) > 0): ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-item">
                                <div class="review-header">
                                    <div class="reviewer-info">
                                        <strong><?= htmlspecialchars($review['customer_name'] ?? 'Anonymous') ?></strong>
                                        <div class="review-rating">
                                            <?php for ($i = 0; $i < ($review['rating'] ?? 5); $i++): ?>★<?php endfor; ?>
                                        </div>
                                    </div>
                                    <small><?= date('M d, Y', strtotime($review['created_at'])) ?></small>
                                </div>
                                <div class="review-text">
                                    <h4><?= htmlspecialchars($review['title'] ?? '') ?></h4>
                                    <p><?= htmlspecialchars($review['comment']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-reviews">No reviews yet. Be the first to review this product!</p>
                    <?php endif; ?>
                </div>

                <!-- Leave Review Form -->
                <?php if (isset($_SESSION['customer_id'])): ?>
                    <div class="review-form">
                        <h3>Leave a Review</h3>
                        <form method="POST" action="api/submit_review.php">
                            <input type="hidden" name="product_id" value="<?= $product_id ?>">
                            
                            <div class="form-group">
                                <label>Rating:</label>
                                <select name="rating" required>
                                    <option value="">Select rating...</option>
                                    <option value="5">★★★★★ Excellent</option>
                                    <option value="4">★★★★ Good</option>
                                    <option value="3">★★★ Average</option>
                                    <option value="2">★★ Poor</option>
                                    <option value="1">★ Very Poor</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Review Title:</label>
                                <input type="text" name="title" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Your Review:</label>
                                <textarea name="comment" rows="5" required></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Submit Review</button>
                        </form>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Related Products -->
            <?php if (count($related_products) > 0): ?>
                <section class="related-products">
                    <h2>Related Products</h2>
                    <div class="products-grid">
                        <?php foreach ($related_products as $related): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <div class="product-placeholder">
                                        <i class="fas fa-laptop"></i>
                                    </div>
                                    <div class="product-overlay">
                                        <a href="product.php?id=<?= (int)$related['product_id'] ?>" class="btn btn-primary">View Details</a>
                                    </div>
                                </div>
                                <div class="product-info">
                                    <h3><?= htmlspecialchars($related['product_name']) ?></h3>
                                    <p class="brand-tag"><?= htmlspecialchars($related['brand'] ?? 'Generic') ?></p>
                                    <p class="product-price">Rs. <?= number_format($related['selling_price'], 2) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/app.js"></script>
</body>
</html>
