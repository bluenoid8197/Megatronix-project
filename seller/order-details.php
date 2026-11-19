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
$isLoggedIn = isset($_SESSION['user_id']);

$userName = $_SESSION['user_name'] ?? 'Seller';
$sellerName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
$sellerId = (int) $_SESSION['user_id'];

// Generate a CSRF token.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate the order ID.
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$orderId || $orderId <= 0) {
    header('Location: orders.php');
    exit;
}

// Retrieve the order only if it contains this seller's products.
$orderStmt = $pdo->prepare("
    SELECT
        o.id,
        o.full_name,
        o.phone,
        o.delivery_address,
        o.payment_method,
        o.status,
        o.created_at
    FROM orders AS o
    WHERE o.id = :order_id
      AND EXISTS (
          SELECT 1
          FROM order_items AS oi
          INNER JOIN products AS p ON p.id = oi.product_id
          WHERE oi.order_id = o.id
            AND p.seller_id = :seller_id
      )
    LIMIT 1
");

$orderStmt->execute([
    ':order_id' => $orderId,
    ':seller_id' => $sellerId
]);

$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: orders.php?error=not_found');
    exit;
}

// Allowed order statuses from the database ENUM.
$allowedStatuses = [
    'Pending',
    'Processing',
    'Shipped',
    'Delivered',
    'Cancelled'
];

// Handle status update.
$statusError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $newStatus = $_POST['status'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        $statusError = 'Invalid request. Please refresh the page and try again.';
    } elseif (!in_array($newStatus, $allowedStatuses, true)) {
        $statusError = 'Please select a valid order status.';
    } else {
        try {
            // Update only if this order still contains this seller's products.
            $updateStmt = $pdo->prepare("
                UPDATE orders
                SET status = :status
                WHERE id = :order_id
                  AND EXISTS (
                      SELECT 1
                      FROM order_items AS oi
                      INNER JOIN products AS p ON p.id = oi.product_id
                      WHERE oi.order_id = orders.id
                        AND p.seller_id = :seller_id
                  )
            ");

            $updateStmt->execute([
                ':status' => $newStatus,
                ':order_id' => $orderId,
                ':seller_id' => $sellerId
            ]);

            header('Location: order-details.php?id=' . $orderId . '&updated=1');
            exit;

        } catch (PDOException $e) {
            $statusError = 'Unable to update the order status. Please try again.';
        }
    }
}

// Retrieve only the products belonging to this seller in this order.
$itemsStmt = $pdo->prepare("
    SELECT
        oi.product_name,
        oi.quantity,
        oi.price,
        oi.subtotal,
        p.image
    FROM order_items AS oi
    INNER JOIN products AS p ON p.id = oi.product_id
    WHERE oi.order_id = :order_id
      AND p.seller_id = :seller_id
    ORDER BY oi.id ASC
");

$itemsStmt->execute([
    ':order_id' => $orderId,
    ':seller_id' => $sellerId
]);

$orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate only this seller's portion of the order.
$sellerTotal = 0;
$totalItems = 0;

foreach ($orderItems as $item) {
    $sellerTotal += (float) $item['subtotal'];
    $totalItems += (int) $item['quantity'];
}

$statusText = (string) ($order['status'] ?? 'Unknown');

$statusClass = strtolower(
    preg_replace('/[^a-zA-Z0-9]+/', '-', trim($statusText))
);

$customerName = htmlspecialchars(
    $order['full_name'] ?? 'Customer',
    ENT_QUOTES,
    'UTF-8'
);

$customerPhone = htmlspecialchars(
    $order['phone'] ?? 'Not provided',
    ENT_QUOTES,
    'UTF-8'
);

$deliveryAddress = htmlspecialchars(
    $order['delivery_address'] ?? 'Not provided',
    ENT_QUOTES,
    'UTF-8'
);

$paymentMethod = htmlspecialchars(
    $order['payment_method'] ?? 'Not specified',
    ENT_QUOTES,
    'UTF-8'
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Order #<?= (int) $order['id'] ?> | Megatronix</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap"
        rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="assets/seller.css">
    <link rel="stylesheet" href="assets/order-details.css">
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
                    <a href="orders.php"
                        class="<?= in_array($currentPage, ['orders.php', 'order-details.php'], true) ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">receipt_long</span>
                        <span>Orders</span>
                    </a>
                </li>

                <?php if ($isLoggedIn): ?>
                <li class="profile-dropdown">
                    <a href="profile.php" class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">person</span>
                        <span><?= $sellerName ?></span>
                    </a>

             
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>


    <main class="seller-order-details-page">

        <a href="orders.php" class="seller-order-back-link">
            <span class="material-symbols-outlined">arrow_back</span>
            Back to Orders
        </a>

        <section class="seller-order-details-heading">
            <div>
                <h1>Order #<?= (int) $order['id'] ?></h1>

                <p>
                    Placed on
                    <?= htmlspecialchars(
                        date('F d, Y \a\t h:i A', strtotime($order['created_at'])),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>
            </div>

            <span class="seller-order-status status-<?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') ?>
            </span>
        </section>

        <div class="seller-order-details-layout">

            <section class="seller-order-items-section">

                <div class="seller-order-card">
                    <div class="seller-order-card-heading">
                        <h2>Your Ordered Products</h2>
                        <span><?= $totalItems ?> item(s)</span>
                    </div>

                    <?php foreach ($orderItems as $item): ?>

                    <?php
                        $itemName = htmlspecialchars(
                            $item['product_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        );

                        $itemImage = '';

                        if (!empty($item['image'])) {
                            $storedImage = $item['image'];

                            if (
                                filter_var($storedImage, FILTER_VALIDATE_URL) &&
                                in_array(
                                    strtolower(parse_url($storedImage, PHP_URL_SCHEME) ?? ''),
                                    ['http', 'https'],
                                    true
                                )
                            ) {
                                $itemImage = $storedImage;
                            } else {
                                $itemImage = '../' . ltrim($storedImage, '/');
                            }
                        }
                        ?>

                    <article class="seller-order-item">

                        <div class="seller-order-item-image">
                            <?php if ($itemImage !== ''): ?>
                            <img src="<?= htmlspecialchars($itemImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $itemName ?>">
                            <?php else: ?>
                            <span class="material-symbols-outlined">image</span>
                            <?php endif; ?>
                        </div>

                        <div class="seller-order-item-info">
                            <h3><?= $itemName ?></h3>

                            <p>
                                Quantity: <?= (int) $item['quantity'] ?>
                            </p>

                            <p>
                                Unit Price:
                                ₱<?= number_format((float) $item['price'], 2) ?>
                            </p>
                        </div>

                        <div class="seller-order-item-subtotal">
                            ₱<?= number_format((float) $item['subtotal'], 2) ?>
                        </div>

                    </article>

                    <?php endforeach; ?>

                    <div class="seller-order-total-row">
                        <span>Your Order Subtotal</span>
                        <strong>₱<?= number_format($sellerTotal, 2) ?></strong>
                    </div>
                </div>

            </section>

            <aside class="seller-order-information">

                <!-- Order Status Management -->
                <section class="seller-order-card seller-status-management">
                    <h2>Manage Order Status</h2>

                    <?php if (isset($_GET['updated']) && $_GET['updated'] === '1'): ?>
                    <div class="seller-status-success">
                        Order status updated successfully.
                    </div>
                    <?php endif; ?>

                    <?php if ($statusError !== ''): ?>
                    <div class="seller-status-error">
                        <?= htmlspecialchars($statusError, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="order-details.php?id=<?= (int) $order['id'] ?>">
                        <input type="hidden" name="csrf_token"
                            value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                        <label for="order-status">Change Status</label>

                        <select name="status" id="order-status" required>
                            <?php foreach ($allowedStatuses as $status): ?>
                            <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $statusText === $status ? 'selected' : '' ?>>
                                <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="seller-status-update-btn">
                            <span class="material-symbols-outlined">save</span>
                            Update Status
                        </button>
                    </form>
                </section>

                <section class="seller-order-card">
                    <h2>Customer Information</h2>

                    <div class="seller-order-info-group">
                        <span class="material-symbols-outlined">person</span>

                        <div>
                            <label>Customer Name</label>
                            <p><?= $customerName ?></p>
                        </div>
                    </div>

                    <div class="seller-order-info-group">
                        <span class="material-symbols-outlined">call</span>

                        <div>
                            <label>Phone Number</label>
                            <p><?= $customerPhone ?></p>
                        </div>
                    </div>
                </section>

                <section class="seller-order-card">
                    <h2>Delivery Information</h2>

                    <div class="seller-order-info-group">
                        <span class="material-symbols-outlined">location_on</span>

                        <div>
                            <label>Delivery Address</label>
                            <p><?= nl2br($deliveryAddress) ?></p>
                        </div>
                    </div>
                </section>

                <section class="seller-order-card">
                    <h2>Payment Information</h2>

                    <div class="seller-order-info-group">
                        <span class="material-symbols-outlined">payments</span>

                        <div>
                            <label>Payment Method</label>
                            <p><?= $paymentMethod ?></p>
                        </div>
                    </div>
                </section>

            </aside>

        </div>

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