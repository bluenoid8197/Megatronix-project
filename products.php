
<?php
session_start();

$currentPage = basename($_SERVER['PHP_SELF']);

require_once __DIR__ . '/config/database.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';
$userRole = $_SESSION['role'] ?? '';

// Redirect sellers to their own product management page.
if ($isLoggedIn && $userRole === 'seller') {
    header('Location: seller/products.php');
    exit;
}

// Get the search term.
$search = trim($_GET['search'] ?? '');

// Retrieve seller-listed products.
if ($search !== '') {
    $stmt = $pdo->prepare("
        SELECT id, name, description, price, image, stock
        FROM products
        WHERE seller_id IS NOT NULL
          AND name LIKE :search
        ORDER BY created_at DESC
    ");

    $stmt->execute([
        ':search' => '%' . $search . '%'
    ]);
} else {
    $stmt = $pdo->query("
        SELECT id, name, description, price, image, stock
        FROM products
        WHERE seller_id IS NOT NULL
        ORDER BY created_at DESC
    ");
}

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Browse Products | Megatronix</title>

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="styles.css">
</head>

<body class="products-page">

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
                        <a
                            class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>"
                            href="profile.php">
                            <span class="material-symbols-outlined">person</span>
                            <span><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></span>
                        </a>

                        <ul class="profile-dropdown-menu">
                            <?php if ($userRole === 'seller'): ?>
                                <li><a href="seller/index.php">Seller Dashboard</a></li>
                                <li><a href="seller/products.php">My Products</a></li>
                                <li><a href="seller/orders.php">My Orders</a></li>
                                <li><a href="seller/profile.php">My Profile</a></li>
                            <?php else: ?>
                 
                                <li><a href="cart.php">My Cart</a></li>
                                <li><a href="my-orders.php">My Orders</a></li>
                            <?php endif; ?>


                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <form action="products.php" method="GET" class="product-search">
        <input
            type="search"
            name="search"
            placeholder="Search for electronics and gadgets..."
            value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
        >

        <button type="submit">Search</button>
    </form>

    <main class="products-container">

        <?php if (count($products) > 0): ?>

            <section class="product-grid">

                <?php foreach ($products as $product): ?>

                    <?php
                    $productName = htmlspecialchars(
                        $product['name'],
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    $description = htmlspecialchars(
                        $product['description'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    $imagePath = '';

                    if (!empty($product['image'])) {
                        $storedImage = $product['image'];

                        // Allow only HTTP and HTTPS external image URLs.
                        if (
                            filter_var($storedImage, FILTER_VALIDATE_URL) &&
                            in_array(
                                strtolower(parse_url($storedImage, PHP_URL_SCHEME) ?? ''),
                                ['http', 'https'],
                                true
                            )
                        ) {
                            $imagePath = $storedImage;
                        } else {
                            // Seller uploads are stored as seller/uploads/filename.
                            $imagePath = ltrim($storedImage, '/');
                        }
                    }
                    ?>

                    <article class="product-card">

                        <a
                            href="product-details.php?id=<?= (int) $product['id'] ?>"
                            class="product-card-link">

                            <?php if ($imagePath !== ''): ?>

                                <img
                                    class="product-image"
                                    src="<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= $productName ?>"
                                >

                            <?php else: ?>

                                <div class="product-image-placeholder">
                                    No image available
                                </div>

                            <?php endif; ?>

                            <div class="product-information">

                                <h2><?= $productName ?></h2>

                                <p class="product-description">
                                    <?= $description !== '' ? $description : 'No description provided.' ?>
                                </p>

                                <p class="product-price">
                                    ₱<?= number_format((float) $product['price'], 2) ?>
                                </p>

                                <?php if ((int) $product['stock'] > 0): ?>
                                    <p class="product-stock available">
                                        In stock
                                    </p>
                                <?php else: ?>
                                    <p class="product-stock unavailable">
                                        Out of stock
                                    </p>
                                <?php endif; ?>

                            </div>

                        </a>

                        <?php if ($isLoggedIn && $userRole === 'buyer' && (int) $product['stock'] > 0): ?>

                            <div class="product-actions">

                                <form action="cart.php" method="POST">
                                    <input
                                        type="hidden"
                                        name="product_id"
                                        value="<?= (int) $product['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="quantity"
                                        value="1"
                                    >

                                    <button
                                        type="submit"
                                        name="add_to_cart"
                                        class="button product-button">
                                        Add to cart
                                    </button>
                                </form>

                                <form action="checkout.php" method="GET">
                                    <input
                                        type="hidden"
                                        name="product_id"
                                        value="<?= (int) $product['id'] ?>"
                                    >
                                </form>

                            </div>

                        <?php elseif (!$isLoggedIn): ?>

                            <a href="login.php" class="button product-button">
                                Log In to Shop
                            </a>

                        <?php elseif ($userRole === 'buyer'): ?>

                            <button
                                type="button"
                                class="button product-button"
                                disabled>
                                Unavailable
                            </button>

                        <?php endif; ?>

                    </article>

                <?php endforeach; ?>

            </section>

        <?php else: ?>

            <div class="products-empty">

                <?php if ($search !== ''): ?>

                    <h2>No matching products found</h2>

                    <p>
                        No products matched
                        "<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>".
                        Try another search term.
                    </p>

                    <a href="products.php" class="button">
                        View All Products
                    </a>

                <?php else: ?>

                    <h2>No products yet</h2>

                    <p>
                        Products will appear here once sellers have listed
                        their products.
                    </p>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </main>

    <footer class="site-footer">

        <div class="footer-container">

            <div class="footer-brand">
                <h2>Megatronix</h2>
                <p>Your destination for electronics and gadgets.</p>
            </div>

            <div class="footer-links">
                <h3>Quick Links</h3>
                <a href="index.php">Home</a>
                <a href="about.php">About Us</a>
                <a href="products.php">Browse Items</a>
                <a href="contact.php">Contact Us</a>
            </div>

            <div class="footer-contact">
                <h3>Customer Support</h3>
                <p>Have questions about our products?</p>
                <a href="contact.php">Get in touch with us</a>
            </div>

        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> Megatronix. All rights reserved.</p>
        </div>

    </footer>

</body>

</html>
