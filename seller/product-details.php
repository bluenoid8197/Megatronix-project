
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

// Validate product ID.
$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$productId || $productId < 1) {
    header('Location: products.php');
    exit;
}

// Retrieve only this seller's product.
$stmt = $pdo->prepare(
    'SELECT
        products.id,
        products.name,
        products.description,
        products.price,
        products.image,
        products.stock,
        products.created_at,
        products.seller_id,
        users.name AS seller_name,
        users.profile_picture AS seller_profile_picture
     FROM products
     INNER JOIN users ON products.seller_id = users.id
     WHERE products.id = :product_id
       AND products.seller_id = :seller_id
     LIMIT 1'
);

$stmt->execute([
    'product_id' => $productId,
    'seller_id' => $sellerId
]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

// Prevent sellers from viewing another seller's product.
if (!$product) {
    header('Location: products.php?error=not_found');
    exit;
}

// Retrieve all comments for this product.
$commentsStmt = $pdo->prepare(
    'SELECT
        product_comments.id,
        product_comments.user_id,
        product_comments.comment,
        product_comments.created_at,
        users.name
     FROM product_comments
     INNER JOIN users ON product_comments.user_id = users.id
     WHERE product_comments.product_id = :product_id
     ORDER BY product_comments.created_at DESC'
);

$commentsStmt->execute([
    'product_id' => $productId
]);

$comments = $commentsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalComments = count($comments);

// Prepare product information.
$productName = htmlspecialchars(
    $product['name'],
    ENT_QUOTES,
    'UTF-8'
);

$productDescription = htmlspecialchars(
    $product['description'] ?? '',
    ENT_QUOTES,
    'UTF-8'
);

$productImage = '';

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
        $productImage = $storedImage;
    } else {
        $productImage = '../' . ltrim($storedImage, '/');
    }
}

// Calculate seller-only inventory information.
$productPrice = (float) $product['price'];
$productStock = (int) $product['stock'];
$inventoryValue = $productPrice * $productStock;

$productStatus = $productStock > 0
    ? 'In Stock'
    : 'Out of Stock';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= $productName ?> | Seller Product Details | Megatronix</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap"
        rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">

    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="assets/seller.css">

    <style>
        .seller-private-product-info {
            margin-top: 25px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 10px;
        }

        .seller-private-product-info h2 {
            margin-bottom: 15px;
        }

        .seller-private-detail {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }

        .seller-private-detail:last-child {
            border-bottom: none;
        }

        .seller-private-detail span:first-child {
            font-weight: 500;
        }

        .seller-product-page-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        .seller-product-page-actions a {
            text-decoration: none;
        }

        .seller-product-comments-note {
            margin-bottom: 20px;
            color: #666;
        }

        .seller-product-comment {
            padding: 15px 0;
            border-bottom: 1px solid #ddd;
        }

        .seller-product-comment:last-child {
            border-bottom: none;
        }

        .seller-product-comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }

        .seller-product-comment-header h3 {
            margin: 0;
        }

        .seller-product-comment-header span {
            font-size: 0.85rem;
            color: #777;
        }

        .seller-product-comment p {
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        @media (max-width: 600px) {
            .seller-private-detail {
                flex-direction: column;
                gap: 5px;
            }

            .seller-product-comment-header {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>

<body class="product-details-page">

    <!-- Seller Navbar -->
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

    <main class="product-details-container">

        <a href="products.php" class="product-back-link">
            &larr; Back to My Products
        </a>

        <!-- Product Details -->
        <section class="product-details-card">

            <div class="product-details-image">

                <?php if ($productImage !== ''): ?>

                    <img
                        src="<?= htmlspecialchars($productImage, ENT_QUOTES, 'UTF-8') ?>"
                        alt="<?= $productName ?>">

                <?php else: ?>

                    <div class="product-image-placeholder">
                        No image available
                    </div>

                <?php endif; ?>

            </div>

            <div class="product-details-information">

                <h1><?= $productName ?></h1>

                <p class="product-details-price">
                    ₱<?= number_format($productPrice, 2) ?>
                </p>

                <p class="product-details-description">
                    <?= nl2br($productDescription) ?>
                </p>

                <!-- Seller Information -->
                <div class="product-seller-info">

                    <div class="product-seller-picture">

                        <?php if (!empty($product['seller_profile_picture'])): ?>

                            <img
                                src="<?= htmlspecialchars($product['seller_profile_picture'], ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($product['seller_name'], ENT_QUOTES, 'UTF-8') ?>">

                        <?php else: ?>

                            <span class="material-symbols-outlined">
                                account_circle
                            </span>

                        <?php endif; ?>

                    </div>

                    <div class="product-seller-details">

                        <span>Your Seller Account</span>

                        <h3>
                            <?= htmlspecialchars($product['seller_name'], ENT_QUOTES, 'UTF-8') ?>
                        </h3>

                    </div>

                </div>

                <!-- Product Status -->
                <?php if ($productStock > 0): ?>

                    <p class="product-stock available">
                        In stock (<?= $productStock ?> available)
                    </p>

                <?php else: ?>

                    <p class="product-stock unavailable">
                        Out of stock
                    </p>

                <?php endif; ?>

                <div class="seller-product-page-actions">

                    <a
                        href="edit-product.php?id=<?= (int) $product['id'] ?>"
                        class="seller-primary-btn">

                        <span class="material-symbols-outlined">edit</span>
                        Edit Product

                    </a>

                    <a href="products.php" class="seller-edit-btn">
                        <span class="material-symbols-outlined">arrow_back</span>
                        My Products
                    </a>

                </div>

            </div>

        </section>

        <!-- Seller-Only Product Information -->
        <section class="seller-private-product-info">

            <h2>Product Information</h2>

            <div class="seller-private-detail">
                <span>Product ID</span>
                <span>#<?= (int) $product['id'] ?></span>
            </div>

            <div class="seller-private-detail">
                <span>Available Stock</span>
                <span><?= $productStock ?> units</span>
            </div>

            <div class="seller-private-detail">
                <span>Product Status</span>
                <span><?= $productStatus ?></span>
            </div>

            <div class="seller-private-detail">
                <span>Date Listed</span>
                <span>
                    <?= !empty($product['created_at'])
                        ? date('M d, Y h:i A', strtotime($product['created_at']))
                        : 'Not available' ?>
                </span>
            </div>

            <div class="seller-private-detail">
                <span>Inventory Value</span>
                <span>₱<?= number_format($inventoryValue, 2) ?></span>
            </div>

        </section>

        <!-- Customer Comments -->
        <section class="product-comments" id="product-comments">

            <h2>Customer Comments</h2>

            <p class="product-comments-count">
                <?= $totalComments ?>
                <?= $totalComments === 1 ? 'comment' : 'comments' ?>
            </p>

            <p class="seller-product-comments-note">
                These are the comments customers have posted on your product.
            </p>

            <div class="product-comment-list">

                <?php if (!empty($comments)): ?>

                    <?php foreach ($comments as $comment): ?>

                        <article class="seller-product-comment">

                            <div class="seller-product-comment-header">

                                <h3>
                                    <?= htmlspecialchars(
                                        $comment['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </h3>

                                <span>
                                    <?= date(
                                        'M d, Y h:i A',
                                        strtotime($comment['created_at'])
                                    ) ?>
                                </span>

                            </div>

                            <p>
                                <?= nl2br(htmlspecialchars(
                                    $comment['comment'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )) ?>
                            </p>

                        </article>

                    <?php endforeach; ?>

                <?php else: ?>

                    <p class="no-product-comments">
                        No comments yet.
                    </p>

                <?php endif; ?>

            </div>

        </section>

    </main>

    <!-- Footer -->
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
