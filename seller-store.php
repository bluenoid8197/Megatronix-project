
<?php
session_start();

$currentPage = basename($_SERVER['PHP_SELF']);

require_once __DIR__ . '/config/database.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';
$userRole = $_SESSION['role'] ?? '';

// Validate seller ID.
$sellerId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$sellerId || $sellerId < 1) {
    header('Location: products.php');
    exit;
}

// Retrieve seller information.
$sellerQuery = $pdo->prepare("
    SELECT id, name, profile_picture
    FROM users
    WHERE id = :seller_id
      AND role = 'seller'
    LIMIT 1
");

$sellerQuery->execute([
    ':seller_id' => $sellerId
]);

$seller = $sellerQuery->fetch(PDO::FETCH_ASSOC);

if (!$seller) {
    header('Location: products.php');
    exit;
}

// Retrieve this seller's available products.
$productQuery = $pdo->prepare("
    SELECT id, name, description, price, image, stock
    FROM products
    WHERE seller_id = :seller_id
      AND stock > 0
    ORDER BY created_at DESC
");

$productQuery->execute([
    ':seller_id' => $sellerId
]);

$products = $productQuery->fetchAll(PDO::FETCH_ASSOC);

// Prepare seller information.
$sellerName = htmlspecialchars(
    $seller['name'],
    ENT_QUOTES,
    'UTF-8'
);

$profilePicture = !empty($seller['profile_picture'])
    ? $seller['profile_picture']
    : 'assets/images/default-profile.png';

$profilePicture = htmlspecialchars(
    $profilePicture,
    ENT_QUOTES,
    'UTF-8'
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= $sellerName ?> | Megatronix</title>

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="styles.css">
</head>

<body class="products-page">

    <!-- Navbar -->
    <header>
        <nav>
            <h2><a href="index.php">Megatronix</a></h2>

            <ul>

                <?php if ($userRole === 'seller'): ?>

                <li>
                    <a href="seller/index.php">
                        <span class="material-symbols-outlined">dashboard</span>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li>
                    <a href="seller/products.php">
                        <span class="material-symbols-outlined">inventory_2</span>
                        <span>My Products</span>
                    </a>
                </li>

                <li>
                    <a href="seller/orders.php">
                        <span class="material-symbols-outlined">receipt_long</span>
                        <span>Orders</span>
                    </a>
                </li>

                <li class="profile-dropdown">
                    <a href="seller/profile.php">
                        <span class="material-symbols-outlined">person</span>
                        <span><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></span>
                    </a>

                    <ul class="profile-dropdown-menu">
                        <li><a href="seller/profile.php">Profile</a></li>
                        <li><a href="logout.php">Log Out</a></li>
                    </ul>
                </li>

                <?php else: ?>

                <li>
                    <a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">home</span>
                        <span>Home</span>
                    </a>
                </li>

                <li>
                    <a href="about.php">
                        <span class="material-symbols-outlined">info</span>
                        <span>About</span>
                    </a>
                </li>

                <li>
                    <a href="contact.php">
                        <span class="material-symbols-outlined">mail</span>
                        <span>Contact</span>
                    </a>
                </li>

                <li>
                    <a href="products.php">
                        <span class="material-symbols-outlined">storefront</span>
                        <span>Shop</span>
                    </a>
                </li>

                <?php if ($isLoggedIn): ?>

                <li class="profile-dropdown">
                    <a href="profile.php">
                        <span class="material-symbols-outlined">person</span>
                        <span><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></span>
                    </a>

                    <ul class="profile-dropdown-menu">
                        <li><a href="profile.php">Profile</a></li>
                        <li><a href="cart.php">My Cart</a></li>
                        <li><a href="my-orders.php">My Orders</a></li>
                        <li><a href="logout.php">Log Out</a></li>
                    </ul>
                </li>

                <?php else: ?>

                <li>
                    <a href="login.php">Log In</a>
                </li>

                <?php endif; ?>

                <?php endif; ?>

            </ul>
        </nav>
    </header>

    <!-- Seller Store -->
    <main class="products-container">

        <section class="seller-store-profile">

            <img
                src="<?= $profilePicture ?>"
                alt="<?= $sellerName ?>'s profile picture">

            <div class="seller-store-profile-info">
                <h1><?= $sellerName ?></h1>
                <p>Megatronix Seller</p>
            </div>

        </section>

        <h2 class="seller-store-heading">
            Available Products
        </h2>

        <?php if (empty($products)): ?>

        <div class="products-empty">
            <h3>No Available Products</h3>
            <p>This seller currently has no products in stock.</p>
        </div>

        <?php else: ?>

        <!-- Products using the homepage card design -->
        <div class="featured-items">

            <?php foreach ($products as $product): ?>

            <article class="featured-item-card">

                <a
                    href="product-details.php?id=<?= (int) $product['id'] ?>"
                    class="featured-item-image"
                    aria-label="View <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>">

                    <?php if (!empty($product['image'])): ?>

                    <img
                        src="<?= htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8') ?>"
                        alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"
                        loading="lazy">

                    <?php else: ?>

                    <div class="featured-item-placeholder">
                        No image available
                    </div>

                    <?php endif; ?>

                </a>

                <div class="featured-item-details">

                    <h3>
                        <?= htmlspecialchars(
                            $product['name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </h3>

                    <p class="featured-item-description">
                        <?= htmlspecialchars(
                            $product['description'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>

                    <p class="featured-item-price">
                        ₱<?= number_format((float) $product['price'], 2) ?>
                    </p>

                    <a
                        href="product-details.php?id=<?= (int) $product['id'] ?>"
                        class="button">
                        View Product
                    </a>

                </div>

            </article>

            <?php endforeach; ?>

        </div>

        <?php endif; ?>

    </main>

</body>

</html>
