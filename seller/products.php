
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Only logged-in sellers can access this page.
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'seller'
) {
    header('Location: ../login.php');
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);
$userName = $_SESSION['user_name'] ?? 'Seller';
$sellerName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
$sellerId = (int) $_SESSION['user_id'];

// Generate a CSRF token if one does not already exist.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get only this seller's products.
$stmt = $pdo->prepare("
    SELECT id, name, description, price, image, stock, created_at
    FROM products
    WHERE seller_id = :seller_id
    ORDER BY created_at DESC
");

$stmt->execute([
    ':seller_id' => $sellerId
]);

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalProducts = count($products);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Seller Dashboard | Megatronix</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap"
        rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">

    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="assets/seller.css">
    <link rel="stylesheet" href="assets/products.css">
</head>

<body>

    <header>
        <nav>
            <h2><a href="../index.php">Megatronix</a></h2>

            <ul>
                <li>
                    <a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">dashboard</span>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li>
                    <a href="products.php" class="<?= $currentPage === 'products.php' ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">inventory_2</span>
                        <span>My Products</span>
                    </a>
                </li>

                <li>
                    <a href="orders.php" class="<?= $currentPage === 'orders.php' ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">receipt_long</span>
                        <span>Orders</span>
                    </a>
                </li>

                <li class="profile-dropdown">
                    <a href="profile.php">
                        <span class="material-symbols-outlined">person</span>
                        <span><?= $sellerName ?></span>
                    </a>

                 
                </li>
            </ul>
        </nav>
    </header>

    <main class="seller-products-page">

        <section class="seller-page-heading">
            <div>
                <h1>My Products</h1>
                <p>Manage the products you sell on Megatronix.</p>
            </div>

            <a href="add-product.php" class="seller-primary-btn">
                <span class="material-symbols-outlined">add</span>
                Add Product
            </a>
        </section>

        <section class="seller-products-summary">
            <div class="seller-products-count">
                <span class="material-symbols-outlined">inventory_2</span>

                <div>
                    <h2><?= $totalProducts ?></h2>
                    <p><?= $totalProducts === 1 ? 'Product listed' : 'Products listed' ?></p>
                </div>
            </div>
        </section>

        <section class="seller-products-section">

            <div class="seller-section-heading">
                <h2>Your Listings</h2>
            </div>

            <?php if (isset($_GET['deleted']) && $_GET['deleted'] === '1'): ?>
                <div class="seller-success-message">
                    Product deleted successfully.
                </div>
            <?php elseif (isset($_GET['updated']) && $_GET['updated'] === '1'): ?>
                <div class="seller-success-message">
                    Product updated successfully.
                </div>
            <?php elseif (isset($_GET['added']) && $_GET['added'] === '1'): ?>
                <div class="seller-success-message">
                    Product added successfully.
                </div>
            <?php elseif (isset($_GET['error'])): ?>
                <div class="seller-form-error">
                    The requested action could not be completed. Please try again.
                </div>
            <?php endif; ?>

            <?php if (empty($products)): ?>

                <div class="seller-empty-products">
                    <span class="material-symbols-outlined">inventory</span>

                    <h3>No products yet</h3>

                    <p>
                        You haven't listed any products.
                        Add your first product to start selling.
                    </p>

                    <a href="add-product.php" class="seller-primary-btn">
                        Add Your First Product
                    </a>
                </div>

            <?php else: ?>

                <div class="seller-products-grid">

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

                            // Support full URLs and project-root-relative image paths.
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
                                $imagePath = '../' . ltrim($storedImage, '/');
                            }
                        }
                        ?>

                        <article class="seller-product-card">

                            <div class="seller-product-image">
                                <?php if ($imagePath !== ''): ?>
                                    <img
                                        src="<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') ?>"
                                        alt="<?= $productName ?>">
                                <?php else: ?>
                                    <span class="material-symbols-outlined">image</span>
                                <?php endif; ?>
                            </div>

                            <div class="seller-product-info">
                                <h3><?= $productName ?></h3>

                                <p class="seller-product-description">
                                    <?= $description !== '' ? $description : 'No description provided.' ?>
                                </p>

                                <div class="seller-product-details">
                                    <span class="seller-product-price">
                                        ₱<?= number_format((float) $product['price'], 2) ?>
                                    </span>

                                    <span class="seller-product-stock">
                                        Stock: <?= (int) $product['stock'] ?>
                                    </span>
                                </div>

                                <div class="seller-product-status">
                                    <?php if ((int) $product['stock'] > 0): ?>
                                        <span class="seller-stock-available">In Stock</span>
                                    <?php else: ?>
                                        <span class="seller-stock-empty">Out of Stock</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="seller-product-actions">

                                <a
                                    href="product-details.php?id=<?= (int) $product['id'] ?>"
                                    class="seller-edit-btn">
                                    <span class="material-symbols-outlined">visibility</span>
                                    View Details
                                </a>

                                <a
                                    href="edit-product.php?id=<?= (int) $product['id'] ?>"
                                    class="seller-edit-btn">
                                    <span class="material-symbols-outlined">edit</span>
                                    Edit
                                </a>

                                <form
                                    action="delete-product.php"
                                    method="POST"
                                    onsubmit="return confirm('Are you sure you want to delete this product? This action cannot be undone.');">

                                    <input
                                        type="hidden"
                                        name="product_id"
                                        value="<?= (int) $product['id'] ?>">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                                    <button type="submit" class="seller-delete-btn">
                                        <span class="material-symbols-outlined">delete</span>
                                        Delete
                                    </button>
                                </form>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

    </main>

    <footer class="site-footer">

        <div class="footer-container">

            <div class="footer-brand">
                <h2>Megatronix</h2>
                <p>Your destination for electronics and gadgets.</p>
            </div>

            <div class="footer-links">
                <h3>Quick Links</h3>
                <a href="../index.php">Home</a>
                <a href="../about.php">About Us</a>
                <a href="../products.php">Browse Items</a>
                <a href="../contact.php">Contact Us</a>
            </div>

            <div class="footer-contact">
                <h3>Customer Support</h3>
                <p>Have questions about our products?</p>
                <a href="../contact.php">Get in touch with us</a>
            </div>

        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> Megatronix. All rights reserved.</p>
        </div>

    </footer>

</body>

</html>
