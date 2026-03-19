# SAP Computers - Customer Website

A modern, responsive e-commerce website for SAP Computers showing products and providing a complete shopping experience.

## Features

### Frontend
- **Homepage** - Hero banner, featured products, and category showcase
- **Shop Page** - Product listing with filters, search, sorting, and pagination
- **Product Details** - Detailed product information, customer reviews, quantity selector
- **Shopping Cart** - Add/remove items, update quantities, view totals
- **Checkout** - Shipping information, payment method selection, order summary
- **User Account** - View orders, manage profile, delivery address, wishlist
- **Wishlist** - Save favorite products for later
- **Customer Reviews** - Leave and view product reviews

### Functionality
- **Product Search & Filters** - Search by name, category filter, price filter, in-stock status
- **Shopping Cart** - Session-based cart management (no login required for browsing)
- **User Authentication** - Register, login, logout
- **Order Management** - Create orders, view order history, track purchases
- **Wishlist Management** - Add/remove from wishlist (logged-in users only)
- **Customer Reviews** - Post and view product reviews
- **Responsive Design** - Mobile-friendly, works on all devices

## File Structure

```
customer/
├── index.php                    # Homepage
├── shop.php                     # Product listing page
├── product.php                  # Product details page
├── cart.php                     # Shopping cart
├── checkout.php                 # Checkout page
├── account.php                  # User account dashboard
├── login.php                    # Login page
├── register.php                 # Registration page
├── logout.php                   # Logout
├── order-success.php            # Order confirmation page
│
├── assets/
│   ├── css/
│   │   └── style.css           # Main stylesheet (modern, responsive design)
│   └── js/
│       └── app.js              # JavaScript functionality
│
├── includes/
│   ├── header.php              # Navigation header
│   ├── footer.php              # Footer with links
│   └── customer_functions.php   # Helper functions
│
└── api/
    ├── cart.php                # Cart operations (add, remove, update)
    ├── wishlist.php            # Wishlist management
    ├── process_order.php       # Order creation
    ├── update_profile.php      # Profile updates
    ├── change_password.php     # Password changes
    ├── update_address.php      # Address updates
    └── submit_review.php       # Review submission
```

## Database Requirements

The following tables are required in your database:

- `products` - Product information (id, name, description, price, cost_price, category_id, image, sku, quantity, status, views, created_at)
- `categories` - Product categories (id, name, status)
- `customers` - Customer accounts (id, name, email, phone, password, address, city, postal_code, country, dob, created_at)
- `orders` - Customer orders (id, customer_id, item_count, total_amount, tax, shipping, grand_total, payment_method, status, created_at)
- `order_items` - Items in orders (id, order_id, product_id, quantity, price)
- `wishlists` - Wishlist items (id, customer_id, product_id, created_at)
- `reviews` - Product reviews (id, product_id, customer_id, customer_name, rating, title, comment, created_at)

## Installation

1. Place the `customer` folder in your SAP Computers project directory
2. Ensure database password (hash) is configured properly
3. Create the required database tables (see Database Requirements above)
4. Access the website at `/customer/index.php`

## Configuration

- Uses existing `config/database.php` for database connection
- Tax rate: 15%
- Free shipping threshold: Rs. 2000
- Shipping cost: Rs. 200 (when applicable)

## Features Breakdown

### Shopping
- Browse products by category
- Search for specific products
- Filter by price range, category, stock status
- Sort by newest, price (low-high, high-low), popularity
- Pagination for product listings

### Cart & Checkout
- Add products to cart (no login required initially)
- Update item quantities
- Remove items from cart
- View cart summary (subtotal, tax, shipping, total)
- Complete checkout process
- Multiple payment methods (Card, Bank Transfer, Cash on Delivery)

### User Management
- User registration with email verification
- Secure login/logout
- Profile management (name, email, phone, date of birth)
- Password change
- Delivery address management

### Orders & Wishlist
- View order history
- Track order status
- Save products to wishlist
- View wishlist
- Move items from wishlist to cart

### Reviews
- Post product reviews with rating
- View customer reviews
- Rate products

## Styling

The design features:
- Modern, clean interface
- Responsive mobile-first design
- Consistent color scheme (primary blue #2563eb)
- Smooth animations and transitions
- Professional product cards and layouts
- Easy-to-use navigation

## Security Features

- Password hashing with bcrypt
- Session-based authentication
- Input validation and sanitization
- SQL prepared statements
- CSRF protection ready
- HTML escaping to prevent XSS

## Future Enhancements

- Email notifications for orders
- Payment gateway integration
- Advanced product recommendations
- Inventory management improvements
- Admin customer management interface
- Order tracking and updates
- Multi-language support
- Rating and review moderation

---

**Created for: SAP Computers**
**Last Updated: 2024**
