<?php
/**
 * Customer Website Header
 */
?>
<header class="customer-header">
    <div class="header-top">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="index.php">
                        <img src="https://sapcomputers.lk/storage/2025/05/cropped-site-logo-WHITE.png" alt="SAP Computers" class="logo-img">
                    </a>
                </div>

                <div class="search-bar">
                    <form method="GET" action="shop.php">
                        <input type="text" name="search" placeholder="Search for products..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                        <button type="submit" class="btn btn-search"><i class="fas fa-search"></i></button>
                    </form>
                </div>

                <nav class="header-nav">
                    <div class="cart-icon">
                        <a href="cart.php">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="cart-count"><?= count(isset($_SESSION['cart']) ? $_SESSION['cart'] : []) ?></span>
                        </a>
                    </div>

                    <div class="wishlist-icon">
                        <a href="<?= isset($_SESSION['customer_id']) ? 'account.php?tab=wishlist' : 'login.php' ?>">
                            <i class="fas fa-heart"></i>
                        </a>
                    </div>

                    <?php if (isset($_SESSION['customer_id'])): ?>
                        <div class="user-menu">
                            <button class="user-toggle"><i class="fas fa-user"></i></button>
                            <div class="dropdown-menu">
                                <a href="account.php">My Account</a>
                                <a href="account.php?tab=orders">My Orders</a>
                                <a href="account.php?tab=wishlist">Wishlist</a>
                                <hr>
                                <a href="logout.php">Logout</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline">Login</a>
                        <a href="register.php" class="btn btn-primary">Sign Up</a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </div>

    <!-- Category Navigation -->
    <div class="category-nav">
        <div class="container">
            <button class="menu-toggle"><i class="fas fa-bars"></i> Categories</button>
            <div class="categories-list">
                <a href="shop.php">All Products</a>
                <a href="shop.php?category=1">Laptops</a>
                <a href="shop.php?category=2">Desktops</a>
                <a href="shop.php?category=3">Peripherals</a>
                <a href="shop.php?category=4">Networking</a>
            </div>
        </div>
    </div>
</header>
