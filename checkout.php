
<?php
session_start();

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';

require_once __DIR__ . '/config/database.php';

// Only logged-in buyers can check out.
if (!$isLoggedIn || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

// Get the buyer's existing cart.
$sessionCart = $_SESSION['cart'] ?? [];

if (!is_array($sessionCart)) {
    $sessionCart = [];
    $_SESSION['cart'] = [];
}

// Create a CSRF token to protect the checkout form.
if (empty($_SESSION['checkout_token'])) {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(32));
}

$message = '';
$errors = [];

// Check whether checkout was started using Buy Now.
$rawDirectProductId = $_GET['product_id'] ?? ($_POST['direct_product_id'] ?? null);
$rawDirectQuantity = $_GET['quantity'] ?? ($_POST['direct_quantity'] ?? null);

$directProductId = null;
$directQuantity = null;
$isDirectCheckout = false;

if ($rawDirectProductId !== null && $rawDirectProductId !== '') {

    $validatedDirectId = filter_var($rawDirectProductId, FILTER_VALIDATE_INT);

    if ($validatedDirectId === false || $validatedDirectId < 1) {

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            header('Location: products.php');
            exit;
        }

    } else {

        $directProductId = (int) $validatedDirectId;
        $isDirectCheckout = true;
    }
}

// Use either the selected Buy Now product or the buyer's entire cart.
if ($isDirectCheckout) {

    if ($rawDirectQuantity !== null && $rawDirectQuantity !== '') {

        // Use the quantity submitted by the product details page.
        $validatedQuantity = filter_var(
            $rawDirectQuantity,
            FILTER_VALIDATE_INT
        );

        if ($validatedQuantity === false || $validatedQuantity < 1) {

            $errors[] = 'Please select a valid product quantity.';
            $cart = [];

        } else {

            $directQuantity = (int) $validatedQuantity;

            $cart = [
                $directProductId => $directQuantity
            ];
        }

    } else {

        // Buy Now from the cart uses the quantity stored in the cart.
        $storedQuantity = filter_var(
            $sessionCart[$directProductId] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($storedQuantity !== false && $storedQuantity > 0) {

            $directQuantity = (int) $storedQuantity;

            $cart = [
                $directProductId => $directQuantity
            ];

        } else {

            $cart = [];
        }
    }

    // A cart-based Buy Now must have an existing cart item.
    if (
        empty($cart) &&
        $_SERVER['REQUEST_METHOD'] === 'GET' &&
        ($rawDirectQuantity === null || $rawDirectQuantity === '')
    ) {
        header('Location: cart.php');
        exit;
    }

} else {

    $cart = $sessionCart;
}

// Handle order submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['checkout_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['checkout_token'], $token)
    ) {
        $errors[] = 'Invalid form submission. Please refresh and try again.';
    }

    if (
        $rawDirectProductId !== null &&
        $rawDirectProductId !== '' &&
        !$isDirectCheckout
    ) {
        $errors[] = 'Invalid product selected.';
    }

    if ($isDirectCheckout && empty($cart)) {
        $errors[] = 'The selected product or quantity is invalid.';
    }

    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['delivery_address'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? '';

    // Validate full name.
    // Allows Unicode letters, spaces, hyphens, and apostrophes.
    if (
        $fullName === '' ||
        mb_strlen($fullName, 'UTF-8') > 50 ||
        !preg_match(
            "/^[\p{L}\p{M}]+(?:[ '\x{2019}-][\p{L}\p{M}]+)*$/u",
            $fullName
        )
    ) {
        $errors[] = 'Enter a valid full name using letters only.';
    }

    // Validate Philippine mobile number.
    // Must start with 09 and contain exactly 11 digits.
    if (!preg_match('/^09[0-9]{9}$/', $phone)) {
        $errors[] = 'Enter a valid Philippine mobile number (11 digits starting with 09).';
    }

    // Validate delivery address.
    if ($address === '' || mb_strlen($address, 'UTF-8') > 1000) {
        $errors[] = 'Enter a valid delivery address (maximum 1000 characters).';
    }

    // Validate payment method.
    if (!in_array($paymentMethod, ['Cash on Delivery'], true)) {
        $errors[] = 'Select a valid payment method.';
    }

    if (empty($cart)) {
        $errors[] = 'Your cart is empty.';
    }

    if (empty($errors)) {

        try {
            $pdo->beginTransaction();

            // Validate product IDs and quantities.
            $productIds = [];

            foreach ($cart as $productId => $quantity) {

                $validatedId = filter_var($productId, FILTER_VALIDATE_INT);
                $validatedQuantity = filter_var($quantity, FILTER_VALIDATE_INT);

                if (
                    $validatedId === false ||
                    $validatedId < 1 ||
                    $validatedQuantity === false ||
                    $validatedQuantity < 1
                ) {
                    throw new RuntimeException(
                        'Your cart contains an invalid product or quantity.'
                    );
                }

                $productIds[] = (int) $validatedId;
            }

            $productIds = array_values(array_unique($productIds));

            if (empty($productIds)) {
                throw new RuntimeException('Your cart is empty.');
            }

            $placeholders = implode(',', array_fill(0, count($productIds), '?'));

            // Lock eligible seller products while checking their stock.
            $stmt = $pdo->prepare(
                "SELECT id, name, price, stock
                 FROM products
                 WHERE id IN ($placeholders)
                   AND seller_id IS NOT NULL
                   AND seller_id <> ?
                 FOR UPDATE"
            );

            $stmt->execute(array_merge($productIds, [$userId]));

            $databaseProducts = $stmt->fetchAll();

            $productsById = [];

            foreach ($databaseProducts as $product) {
                $productsById[(int) $product['id']] = $product;
            }

            $orderItems = [];
            $total = 0;

            foreach ($cart as $productId => $quantity) {

                $productId = (int) $productId;
                $quantity = (int) $quantity;

                if (!isset($productsById[$productId])) {
                    throw new RuntimeException(
                        'A product in your order is no longer available.'
                    );
                }

                $product = $productsById[$productId];

                if ((int) $product['stock'] < 1) {
                    throw new RuntimeException(
                        $product['name'] . ' is out of stock.'
                    );
                }

                if ($quantity > (int) $product['stock']) {
                    throw new RuntimeException(
                        'Not enough stock for ' . $product['name'] .
                        '. Available quantity: ' . $product['stock'] . '.'
                    );
                }

                $price = (float) $product['price'];
                $subtotal = round($price * $quantity, 2);
                $total = round($total + $subtotal, 2);

                $orderItems[] = [
                    'product_id' => $productId,
                    'product_name' => $product['name'],
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $subtotal
                ];
            }

            // Make sure every requested product was found.
            if (count($productsById) !== count($productIds)) {
                throw new RuntimeException(
                    'One or more products in your order are no longer available.'
                );
            }

            // Save the main order.
            $stmt = $pdo->prepare(
                'INSERT INTO orders
                 (user_id, full_name, phone, delivery_address, payment_method, total_amount)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $userId,
                $fullName,
                $phone,
                $address,
                $paymentMethod,
                $total
            ]);

            $orderId = (int) $pdo->lastInsertId();

            // Save each item and deduct its stock.
            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items
                 (order_id, product_id, product_name, quantity, price, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            $stockStmt = $pdo->prepare(
                'UPDATE products
                 SET stock = stock - ?
                 WHERE id = ?
                   AND seller_id IS NOT NULL
                   AND seller_id <> ?
                   AND stock >= ?'
            );

            foreach ($orderItems as $item) {

                $itemStmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['product_name'],
                    $item['quantity'],
                    $item['price'],
                    $item['subtotal']
                ]);

                $stockStmt->execute([
                    $item['quantity'],
                    $item['product_id'],
                    $userId,
                    $item['quantity']
                ]);

                if ($stockStmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Stock changed while your order was being processed. Please try again.'
                    );
                }
            }

            // Complete the transaction before changing the session cart.
            $pdo->commit();

            if ($isDirectCheckout) {

                // Subtract the purchased quantity from the existing cart, if present.
                if (
                    isset($_SESSION['cart']) &&
                    is_array($_SESSION['cart']) &&
                    array_key_exists($directProductId, $_SESSION['cart'])
                ) {

                    $remainingQuantity = (int) $_SESSION['cart'][$directProductId];
                    $remainingQuantity -= $directQuantity;

                    if ($remainingQuantity > 0) {

                        $_SESSION['cart'][$directProductId] = $remainingQuantity;

                    } else {

                        unset($_SESSION['cart'][$directProductId]);

                    }
                }

                // Remove the cart session if no items remain.
                if (
                    isset($_SESSION['cart']) &&
                    is_array($_SESSION['cart']) &&
                    empty($_SESSION['cart'])
                ) {
                    unset($_SESSION['cart']);
                }

            } else {

                // Regular checkout removes the entire cart.
                unset($_SESSION['cart']);
            }

            unset($_SESSION['checkout_token']);

            header('Location: checkout.php?success=' . $orderId);
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof RuntimeException) {
                $errors[] = $e->getMessage();
            } else {
                error_log($e->getMessage());
                $errors[] = 'We could not place your order. Please try again.';
            }
        }
    }
}

// Show a confirmation only if this order belongs to the current buyer.
$successOrder = null;

if (isset($_GET['success'])) {

    $successId = filter_var($_GET['success'], FILTER_VALIDATE_INT);

    if ($successId !== false && $successId > 0) {

        $stmt = $pdo->prepare(
            'SELECT id, total_amount, status, created_at
             FROM orders
             WHERE id = ? AND user_id = ?'
        );

        $stmt->execute([$successId, $userId]);
        $successOrder = $stmt->fetch();
    }
}

// Retrieve fresh product information for the checkout page.
$checkoutItems = [];
$total = 0;

if (!$successOrder && !empty($cart)) {

    $productIds = array_map('intval', array_keys($cart));
    $productIds = array_values(array_unique($productIds));

    if (!empty($productIds) && !in_array(0, $productIds, true)) {

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));

        $stmt = $pdo->prepare(
            "SELECT id, name, price, image, stock
             FROM products
             WHERE id IN ($placeholders)
               AND seller_id IS NOT NULL
               AND seller_id <> ?"
        );

        $stmt->execute(array_merge($productIds, [$userId]));

        $databaseProducts = $stmt->fetchAll();

        foreach ($databaseProducts as $product) {

            $id = (int) $product['id'];
            $quantity = filter_var($cart[$id] ?? null, FILTER_VALIDATE_INT);

            if ($quantity === false || $quantity < 1) {
                continue;
            }

            $product['quantity'] = $quantity;
            $product['subtotal'] = round(
                (float) $product['price'] * $quantity,
                2
            );

            $checkoutItems[] = $product;
            $total = round($total + $product['subtotal'], 2);
        }
    }
}

function checkoutEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Checkout | Megatronix</title>

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap"
        rel="stylesheet">

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

    <main class="checkout-page">

        <div class="checkout-container">

            <?php if ($successOrder): ?>

                <section class="checkout-success">

                    <h1>Order Placed Successfully!</h1>
                    <p>Thank you for shopping with us.</p>

                    <div class="checkout-success-details">

                        <p>
                            <strong>Order Number:</strong>
                            #<?= (int) $successOrder['id'] ?>
                        </p>

                        <p>
                            <strong>Total:</strong>
                            ₱<?= number_format((float) $successOrder['total_amount'], 2) ?>
                        </p>

                        <p>
                            <strong>Payment:</strong>
                            Cash on Delivery
                        </p>

                        <p>
                            <strong>Status:</strong>
                            <?= checkoutEscape($successOrder['status']) ?>
                        </p>

                    </div>

                    <p class="checkout-note">
                        Please wait for your product to arrive.
                    </p>

                    <a href="products.php" class="checkout-primary-button">
                        Continue Shopping
                    </a>

                </section>

            <?php else: ?>

                <h1 class="checkout-title">Checkout</h1>

                <p class="checkout-subtitle">
                    Enter your delivery information to place your order.
                </p>

                <?php if (!empty($errors)): ?>

                    <div class="checkout-errors">

                        <?php foreach ($errors as $error): ?>

                            <p><?= checkoutEscape($error) ?></p>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

                <?php if (empty($cart) || empty($checkoutItems)): ?>

                    <section class="checkout-empty">

                        <h2>Your cart is empty</h2>

                        <p>Add products to your cart before checking out.</p>

                        <a href="products.php" class="checkout-primary-button">
                            Browse Products
                        </a>

                    </section>

                <?php else: ?>

                    <div class="checkout-layout">

                        <section class="checkout-form-section">

                            <h2>Delivery Information</h2>

                            <form
                                action="checkout.php<?= $isDirectCheckout ? '?product_id=' . (int) $directProductId : '' ?>"
                                method="POST"
                                class="checkout-form">

                                <input
                                    type="hidden"
                                    name="checkout_token"
                                    value="<?= checkoutEscape($_SESSION['checkout_token']) ?>">

                                <?php if ($isDirectCheckout): ?>

                                    <input
                                        type="hidden"
                                        name="direct_product_id"
                                        value="<?= (int) $directProductId ?>">

                                    <input
                                        type="hidden"
                                        name="direct_quantity"
                                        value="<?= (int) $directQuantity ?>">

                                <?php endif; ?>

                                <label for="full_name">Full Name</label>

                                <input
                                    type="text"
                                    id="full_name"
                                    name="full_name"
                                    maxlength="50"
                                    placeholder="Enter your full name"
                                    pattern="[\p{L}\p{M}]+(?:[ '\u2019-][\p{L}\p{M}]+)*"
                                    oninput="this.value = this.value.replace(/[^\p{L}\p{M} '\u2019-]/gu, '')"
                                    value="<?= checkoutEscape($_POST['full_name'] ?? $_SESSION['user_name'] ?? '') ?>"
                                    required>

                                <label for="phone">Phone Number</label>

                                <input
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    maxlength="11"
                                    minlength="11"
                                    pattern="09[0-9]{9}"
                                    inputmode="numeric"
                                    placeholder="09XXXXXXXXX"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
                                    value="<?= checkoutEscape($_POST['phone'] ?? '') ?>"
                                    required>

                                <label for="delivery_address">Delivery Address</label>

                                <textarea
                                    id="delivery_address"
                                    name="delivery_address"
                                    rows="4"
                                    maxlength="1000"
                                    style="resize: none;"
                                    required><?= checkoutEscape($_POST['delivery_address'] ?? '') ?></textarea>

                                <label for="payment_method">Payment Method</label>

                                <select id="payment_method" name="payment_method" required>

                                    <option value="Cash on Delivery"
                                        <?= ($_POST['payment_method'] ?? 'Cash on Delivery') === 'Cash on Delivery' ? 'selected' : '' ?>>
                                        Cash on Delivery
                                    </option>

                                </select>

                                <button type="submit" class="checkout-primary-button">
                                    Place Order
                                </button>

                            </form>

                        </section>

                        <aside class="checkout-summary">

                            <h2>Order Summary</h2>

                            <?php foreach ($checkoutItems as $item): ?>

                                <div class="checkout-summary-item">

                                    <div>

                                        <strong>
                                            <?= checkoutEscape($item['name']) ?>
                                        </strong>

                                        <p>
                                            Quantity: <?= (int) $item['quantity'] ?>
                                        </p>

                                        <?php if ((int) $item['stock'] < (int) $item['quantity']): ?>

                                            <p class="checkout-stock-warning">
                                                Only <?= (int) $item['stock'] ?> left in stock
                                            </p>

                                        <?php endif; ?>

                                    </div>

                                    <span>
                                        ₱<?= number_format($item['subtotal'], 2) ?>
                                    </span>

                                </div>

                            <?php endforeach; ?>

                            <div class="checkout-total">

                                <span>Total</span>

                                <strong>
                                    ₱<?= number_format($total, 2) ?>
                                </strong>

                            </div>

                            <p class="checkout-note">
                                Your order total is calculated using current product prices.
                                Stock is checked again when you place your order.
                            </p>

                        </aside>

                    </div>

                <?php endif; ?>

            <?php endif; ?>

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
