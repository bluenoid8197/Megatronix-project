<?php
session_start();

// Identify the current page.
$currentPage = basename($_SERVER['PHP_SELF']);

// Check login status.
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';
$userRole = $_SESSION['role'] ?? '';

// Redirect sellers to their own dashboard.
if ($isLoggedIn && $userRole === 'seller') {
    header('Location: seller/index.php');
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Fresh Tech Within Reach | Megatronix</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap"
        rel="stylesheet">

    <!-- CSS stylesheet -->
    <link rel="stylesheet" href="styles.css">


</head>

<body>

    <header>
        <nav>
            <h2><a href="index.php">Megatronix</a></h2>

            <ul>
               <li>
                    <a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">home</span>
                        <span>Home</span>
                    </a>
                </li>

                <li>
                    <a href="products.php" class="<?= $currentPage === 'products.php' ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">storefront</span>
                        <span>Shop</span>
                    </a>
                </li>

                <li>
                    <a href="about.php" class="<?= $currentPage === 'about.php' ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">info</span>
                        <span>About</span>
                    </a>
                </li>

                <li>
                    <a href="contact.php" class="<?= $currentPage === 'contact.php' ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">mail</span>
                        <span>Contact</span>
                    </a>
                </li>

                <?php if ($isLoggedIn): ?>
                <li class="profile-dropdown">
                    <a class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>" href="profile.php">
                        <span class="material-symbols-outlined">person</span>
                        <span><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></span>
                    </a>

                    <ul class="profile-dropdown-menu">
                       
                        <li><a href="cart.php">My Cart</a></li>
                        <li><a href="my-orders.php">My Orders</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>


    <main>
        <section class="hero first-hero">
            <?php if ($isLoggedIn): ?>
            <h1>Welcome, <a href="profile.php"><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>!</a></h1>

            <p class="hero-description">
                Discover electronics and gadgets designed to keep you connected,
                productive, and entertained.
            </p>

            <a href="products.php" class="button">Browse Items</a>

            <?php if ($userRole === 'buyer'): ?>
            <p class="signup-text">
                Ready to shop?
                <a href="cart.php">View Your Cart</a>
            </p>
            <?php endif; ?>

            <?php else: ?>
            <h1>Fresh Tech Within Reach</h1>

            <p class="hero-description">
                Discover the latest electronics and gadgets
                designed to keep you connected, productive,
                and entertained—all in one place.
            </p>

            <a href="login.php" class="button">Log In</a>

            <p class="signup-text">
                Don't have an account?
                <a href="signup.php">Sign up here</a>
            </p>

            <p class="signup-text">
                Want to sell your products?
                <a href="seller-signup.php">Become a Seller</a>
            </p>
            <?php endif; ?>

            </div>
        </section>

        <!-- About Megatronix -->

        <section class="featured-section">
            <div class="featured-section-heading">
                <h2>Why Megatronix?</h2>

                <p>
                    Your one-stop destination for electronics and gadgets.
                </p>
            </div>

            <div class="megatronix-description">
                <p>
                    Megatronix is an online shopping platform created to make
                    discovering and purchasing electronics easier and more convenient.
                    From everyday gadgets to useful tech accessories, our goal is to
                    bring a variety of products together in one place.
                </p>

                <p>
                    We also provide opportunities for sellers to showcase their
                    products and reach more customers through our platform.
                    Whether you're looking for your next gadget or planning to
                    sell your own products, Megatronix brings buyers and sellers
                    together through a simple online shopping experience.
                </p>
            </div>
        </section>

        <!-- Featured Items -->
        <section class="featured-section featured-items-section">
            <div class="featured-section-heading">
                <h2>Featured Items</h2>
                <p>Check out some of the electronics and gadgets in our store.</p>
            </div>

            <div class="featured-items">
                <?php
                require_once __DIR__ . '/config/database.php';

                $featuredStmt = $pdo->query(
                    'SELECT id, name, description, price, image, stock
             FROM products
             ORDER BY created_at DESC
             LIMIT 4'
                );

                $featuredProducts = $featuredStmt->fetchAll();
                ?>

                <?php if (!empty($featuredProducts)): ?>
                <?php foreach ($featuredProducts as $product): ?>
                <article class="featured-item-card">
                    <a href="products.php" class="featured-item-image" aria-label="Browse products">
                        <?php if (!empty($product['image'])): ?>
                        <img src="<?= htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                        <?php else: ?>
                        <div class="featured-item-placeholder">
                            No image available
                        </div>
                        <?php endif; ?>
                    </a>

                    <div class="featured-item-details">
                        <h3>
                            <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
                        </h3>

                        <p class="featured-item-description">
                            <?= htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8') ?>
                        </p>

                        <p class="featured-item-price">
                            ₱<?= number_format((float) $product['price'], 2) ?>
                        </p>

                        <a href="products.php" class="button">
                            View Products
                        </a>
                    </div>
                </article>
                <?php endforeach; ?>
                <?php else: ?>
                <p class="featured-items-empty">
                    No products are available yet. Check back soon!
                </p>
                <?php endif; ?>
            </div>

            <div class="featured-items-footer">
                <a href="products.php" class="button-secondary">
                    Browse All Products
                </a>
            </div>
        </section>
    </main>

    <div class="main-page-parallax"></div>

    <footer class="site-footer">
        <div class="footer-container">

            <!-- Brand Information -->
            <div class="footer-brand">
                <h2>Megatronix</h2>
                <p>Your destination for electronics and gadgets.</p>
            </div>

            <!-- Quick Links -->
            <div class="footer-links">
                <h3>Quick Links</h3>
                <a href="index.php">Home</a>
                <a href="about.php">About Us</a>
                <a href="products.php">Browse Items</a>
                <a href="contact.php">Contact Us</a>
            </div>

            <!-- Customer Support -->
            <div class="footer-contact">
                <h3>Customer Support</h3>
                <p>Have questions about our products?</p>
                <a href="contact.php">Get in touch with us</a>
            </div>

        </div>

        <!-- Copyright -->
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> Megatronix. All rights reserved.</p>
        </div>
    </footer>


</body>

</html>