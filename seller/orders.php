
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

// Retrieve orders containing this seller's products.
// Totals and item counts include only this seller's products.
$stmt = $pdo->prepare("
    SELECT
        o.id,
        o.full_name,
        o.created_at,
        o.status,
        COUNT(oi.id) AS seller_item_count,
        COALESCE(SUM(oi.subtotal), 0) AS seller_order_total
    FROM orders AS o
    INNER JOIN order_items AS oi
        ON oi.order_id = o.id
    INNER JOIN products AS p
        ON p.id = oi.product_id
    WHERE p.seller_id = :seller_id
    GROUP BY
        o.id,
        o.full_name,
        o.created_at,
        o.status
    ORDER BY o.created_at DESC
");

$stmt->execute([
    ':seller_id' => $sellerId
]);

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalOrders = count($orders);
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
    <link rel="stylesheet" href="assets/orders.css">
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

    <main class="seller-orders-page">

        <section class="seller-page-heading">
            <div>
                <h1>Orders</h1>
                <p>View orders that contain your products.</p>
            </div>
        </section>

        <section class="seller-orders-summary">
            <div class="seller-orders-count">
                <span class="material-symbols-outlined">receipt_long</span>

                <div>
                    <h2><?= $totalOrders ?></h2>
                    <p><?= $totalOrders === 1 ? 'Order received' : 'Orders received' ?></p>
                </div>
            </div>
        </section>

        <section class="seller-orders-section">

            <div class="seller-section-heading">
                <h2>Customer Orders</h2>
            </div>

            <?php if (empty($orders)): ?>

                <div class="seller-orders-empty">
                    <span class="material-symbols-outlined">orders</span>

                    <h3>No orders yet</h3>

                    <p>Orders containing your products will appear here.</p>

                    <a href="products.php" class="seller-primary-btn">
                        View My Products
                    </a>
                </div>

            <?php else: ?>

                <div class="seller-orders-table-wrapper">

                    <table class="seller-orders-table">

                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Your Items</th>
                                <th>Your Total</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($orders as $order): ?>

                                <?php
                                $statusText = (string) ($order['status'] ?? 'Unknown');

                                $statusClass = strtolower(
                                    preg_replace('/[^a-zA-Z0-9]+/', '-', trim($statusText))
                                );

                                $customerName = htmlspecialchars(
                                    $order['full_name'] ?? 'Customer',
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>

                                <tr>

                                    <td data-label="Order ID">
                                        #<?= (int) $order['id'] ?>
                                    </td>

                                    <td data-label="Customer">
                                        <?= $customerName ?>
                                    </td>

                                    <td data-label="Date">
                                        <?= htmlspecialchars(
                                            date('M d, Y', strtotime($order['created_at'])),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td data-label="Your Items">
                                        <?= (int) $order['seller_item_count'] ?>
                                    </td>

                                    <td data-label="Your Total" class="seller-order-total">
                                        ₱<?= number_format((float) $order['seller_order_total'], 2) ?>
                                    </td>

                                    <td data-label="Status">
                                        <span class="seller-order-status status-<?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>

                                    <td data-label="Action">
                                        <a
                                            href="order-details.php?id=<?= (int) $order['id'] ?>"
                                            class="seller-view-order-btn"
                                        >
                                            View
                                        </a>
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
