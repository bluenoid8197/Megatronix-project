
<?php
session_start();

$currentPage = basename($_SERVER['PHP_SELF']);

require_once __DIR__ . '/../config/database.php';

// Only logged-in sellers can access this page.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header('Location: ../login.php');
    exit;
}

$isLoggedIn = true;
$userName = $_SESSION['user_name'] ?? 'Seller';

$sellerId = (int) $_SESSION['user_id'];
$sellerName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');

$totalProducts = 0;
$totalOrders = 0;
$pendingOrders = 0;
$totalSales = 0;
$recentOrders = [];
$error = '';

try {
    // Count this seller's products.
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM products
        WHERE seller_id = :seller_id
    ");
    $stmt->execute(['seller_id' => $sellerId]);
    $totalProducts = (int) $stmt->fetchColumn();

    // Count orders containing this seller's products.
    $stmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT o.id) AS total_orders,
            COUNT(DISTINCT CASE
                WHEN o.status = 'Pending' THEN o.id
            END) AS pending_orders,
            COALESCE(SUM(oi.subtotal), 0) AS total_sales
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        INNER JOIN products p ON p.id = oi.product_id
        WHERE p.seller_id = :seller_id
    ");
    $stmt->execute(['seller_id' => $sellerId]);
    $orderStats = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($orderStats) {
        $totalOrders = (int) $orderStats['total_orders'];
        $pendingOrders = (int) $orderStats['pending_orders'];
        $totalSales = (float) $orderStats['total_sales'];
    }

    // Get the latest orders containing this seller's products.
    $stmt = $pdo->prepare("
        SELECT
            o.id,
            o.full_name,
            o.status,
            o.created_at,
            SUM(oi.subtotal) AS seller_total
        FROM orders o
        INNER JOIN order_items oi ON oi.order_id = o.id
        INNER JOIN products p ON p.id = oi.product_id
        WHERE p.seller_id = :seller_id
        GROUP BY
            o.id,
            o.full_name,
            o.status,
            o.created_at
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $stmt->execute(['seller_id' => $sellerId]);
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = 'Unable to load dashboard information. Please check your database configuration.';
}
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

    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"
        rel="stylesheet">

    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="assets/seller.css">
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

                <?php if ($isLoggedIn): ?>
                    <li class="profile-dropdown">
                        <a class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>" href="profile.php">
                            <span class="material-symbols-outlined">person</span>
                            <span><?= $sellerName ?></span>
                        </a>


                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main class="seller-dashboard">

        <section class="seller-welcome">
            <div>
                <p class="seller-eyebrow">SELLER PANEL</p>
                <h1><?= $sellerName ?></h1>
                <p>Manage your products and keep track of your orders.</p>
            </div>

            <a href="add-product.php" class="seller-primary-button">
                + Add Product
            </a>
        </section>

        <?php if ($error !== ''): ?>
            <div class="seller-error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <section class="seller-stats">

            <div class="seller-stat-card">
                <div class="seller-stat-icon">P</div>
                <div>
                    <p>Total Products</p>
                    <h2><?= number_format($totalProducts) ?></h2>
                </div>
            </div>

            <div class="seller-stat-card">
                <div class="seller-stat-icon">O</div>
                <div>
                    <p>Total Orders</p>
                    <h2><?= number_format($totalOrders) ?></h2>
                </div>
            </div>

            <div class="seller-stat-card">
                <div class="seller-stat-icon">!</div>
                <div>
                    <p>Pending Orders</p>
                    <h2><?= number_format($pendingOrders) ?></h2>
                </div>
            </div>

            <div class="seller-stat-card">
                <div class="seller-stat-icon">₱</div>
                <div>
                    <p>Sales from Your Items</p>
                    <h2>₱<?= number_format($totalSales, 2) ?></h2>
                </div>
            </div>

        </section>

        <section class="seller-content-card">

            <div class="seller-section-heading">
                <div>
                    <h2>Recent Orders</h2>
                    <p>Latest orders containing your products</p>
                </div>

                <a href="orders.php" class="seller-view-all">View All</a>
            </div>

            <?php if (empty($recentOrders)): ?>

                <div class="seller-empty-state">
                    <h3>No orders yet</h3>
                    <p>Orders containing your products will appear here.</p>
                </div>

            <?php else: ?>

                <div class="seller-table-wrapper">
                    <table class="seller-orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Your Items' Total</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                                <?php
                                $statusClass = strtolower(
                                    preg_replace('/[^a-zA-Z0-9]+/', '-', $order['status'])
                                );
                                ?>

                                <tr>
                                    <td>#<?= (int) $order['id'] ?></td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $order['full_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        ₱<?= number_format((float) $order['seller_total'], 2) ?>
                                    </td>

                                    <td>
                                        <span class="seller-status status-<?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= date('M d, Y', strtotime($order['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
