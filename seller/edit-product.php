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

// Get the product ID from the URL or submitted form.
$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
}

if (!$productId || $productId < 1) {
    header('Location: products.php');
    exit;
}

// Fetch only a product owned by this seller.
$stmt = $pdo->prepare("
    SELECT id, name, description, price, image, stock
    FROM products
    WHERE id = :product_id
      AND seller_id = :seller_id
    LIMIT 1
");

$stmt->execute([
    ':product_id' => $productId,
    ':seller_id' => $sellerId
]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

// Do not allow sellers to access another seller's product.
if (!$product) {
    http_response_code(404);
    exit('Product not found or you do not have permission to edit it.');
}

$error = '';

$name = $product['name'];
$description = $product['description'] ?? '';
$price = $product['price'];
$stock = $product['stock'];

// Generate a CSRF token.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Preserve submitted values if validation fails.
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priceInput = trim($_POST['price'] ?? '');
    $stockInput = trim($_POST['stock'] ?? '');

    $price = $priceInput;
    $stock = $stockInput;

    // Verify the CSRF token.
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = 'Invalid form submission. Please refresh the page and try again.';
    }

    // Validate product name.
    elseif ($name === '' || mb_strlen($name) > 150) {
        $error = 'Product name is required and must not exceed 150 characters.';
    }

    // Validate price.
    elseif (
        $priceInput === '' ||
        !is_numeric($priceInput) ||
        !is_finite((float) $priceInput) ||
        (float) $priceInput <= 0 ||
        (float) $priceInput > 99999999.99
    ) {
        $error = 'Please enter a valid product price.';
    }

    // Validate stock.
    elseif (
        $stockInput === '' ||
        filter_var($stockInput, FILTER_VALIDATE_INT) === false ||
        (int) $stockInput < 0
    ) {
        $error = 'Stock must be a whole number equal to or greater than zero.';
    }

    // Validate description length.
    elseif (mb_strlen($description) > 65535) {
        $error = 'The product description is too long.';
    }

    $imagePath = $product['image'];
    $uploadedFilePath = null;

    // Process an optional replacement image.
    if ($error === '' && isset($_FILES['image'])) {

        $image = $_FILES['image'];

        if ($image['error'] !== UPLOAD_ERR_NO_FILE) {

            if ($image['error'] !== UPLOAD_ERR_OK) {
                $error = 'The image upload failed. Please try again.';
            } elseif ($image['size'] > 3 * 1024 * 1024) {
                $error = 'The image must not exceed 3 MB.';
            } else {

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($image['tmp_name']);

                $allowedTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];

                if (!isset($allowedTypes[$mimeType])) {
                    $error = 'Only JPG, PNG, and WEBP images are allowed.';
                } elseif (@getimagesize($image['tmp_name']) === false) {
                    $error = 'The uploaded file is not a valid image.';
                } else {

                    $uploadDirectory = __DIR__ . '/uploads';

                    if (
                        !is_dir($uploadDirectory) &&
                        !mkdir($uploadDirectory, 0755, true)
                    ) {
                        $error = 'The image upload folder could not be created.';
                    } else {

                        $fileName = bin2hex(random_bytes(16))
                            . '.' . $allowedTypes[$mimeType];

                        $destination = $uploadDirectory . '/' . $fileName;

                        if (
                            move_uploaded_file(
                                $image['tmp_name'],
                                $destination
                            )
                        ) {
                            $imagePath = 'seller/uploads/' . $fileName;
                            $uploadedFilePath = $destination;
                        } else {
                            $error = 'The image could not be saved.';
                        }
                    }
                }
            }
        }
    }

    // Update the product.
    if ($error === '') {

        try {
            $stmt = $pdo->prepare("
                UPDATE products
                SET
                    name = :name,
                    description = :description,
                    price = :price,
                    image = :image,
                    stock = :stock
                WHERE id = :product_id
                  AND seller_id = :seller_id
            ");

            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':price' => number_format((float) $priceInput, 2, '.', ''),
                ':image' => $imagePath,
                ':stock' => (int) $stockInput,
                ':product_id' => $productId,
                ':seller_id' => $sellerId
            ]);

            // Delete the previous image only after a successful update.
            if (
                $uploadedFilePath !== null &&
                !empty($product['image']) &&
                str_starts_with($product['image'], 'seller/uploads/')
            ) {
                $oldFileName = basename($product['image']);
                $oldFilePath = __DIR__ . '/uploads/' . $oldFileName;

                $realUploadDirectory = realpath(__DIR__ . '/uploads');
                $realOldFilePath = realpath($oldFilePath);

                if (
                    $realUploadDirectory !== false &&
                    $realOldFilePath !== false &&
                    is_file($realOldFilePath) &&
                    str_starts_with(
                        $realOldFilePath,
                        $realUploadDirectory . DIRECTORY_SEPARATOR
                    )
                ) {
                    unlink($realOldFilePath);
                }
            }

            // Prevent the form from being submitted again on refresh.
            unset($_SESSION['csrf_token']);

            header('Location: products.php?updated=1');
            exit;

        } catch (PDOException $e) {

            // Remove the newly uploaded image if the database update fails.
            if (
                $uploadedFilePath !== null &&
                file_exists($uploadedFilePath)
            ) {
                unlink($uploadedFilePath);
            }

            $error = 'The product could not be updated. Please try again.';
        }
    }
}

// Prepare the current image for display.
$currentImage = '';

if (!empty($product['image'])) {
    $storedImage = $product['image'];

    if (
        filter_var($storedImage, FILTER_VALIDATE_URL) &&
        in_array(
            strtolower(parse_url($storedImage, PHP_URL_SCHEME) ?? ''),
            ['http', 'https'],
            true
        )
    ) {
        $currentImage = $storedImage;
    } else {
        $currentImage = '../' . ltrim($storedImage, '/');
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Product | Megatronix</title>

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
    <link rel="stylesheet" href="assets/add-product.css">
    <link rel="stylesheet" href="assets/edit-product.css">
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
                    <a href="index.php">
                        <span class="material-symbols-outlined">person</span>
                        <span><?= $sellerName ?></span>
                    </a>

               
                </li>
            </ul>
        </nav>
    </header>

    <main class="seller-add-product-page">

        <div class="seller-add-product-heading">
            <div>
                <h1>Edit Product</h1>
                <p>Update the details of your product.</p>
            </div>

            <a href="products.php" class="seller-back-link">
                <span class="material-symbols-outlined">arrow_back</span>
                Back to Products
            </a>
        </div>

        <section class="seller-add-product-card">

            <?php if ($error !== ''): ?>
            <div class="seller-form-error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">

                <input type="hidden" name="product_id" value="<?= (int) $productId ?>">

                <input type="hidden" name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                <div class="seller-form-group">
                    <label for="name">Product Name</label>

                    <input type="text" id="name" name="name" maxlength="150"
                        value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="seller-form-group">
                    <label for="description">Description</label>

                    <textarea id="description" name="description" rows="5" style="resize: none;"
                        placeholder="Describe your product..."><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <div class="seller-form-row">

                    <div class="seller-form-group">
                        <label for="price">Price (PHP)</label>

                        <input type="number" id="price" name="price" min="0.01" max="99999999.99" step="0.01"
                            value="<?= htmlspecialchars((string) $price, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="seller-form-group">
                        <label for="stock">Stock Quantity</label>

                        <input type="number" id="stock" name="stock" min="0" step="1"
                            value="<?= htmlspecialchars((string) $stock, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                </div>

                <div class="seller-form-group">
                    <label>Current Product Image</label>

                    <?php if ($currentImage !== ''): ?>
                    <div class="seller-edit-image-preview">
                        <img src="<?= htmlspecialchars($currentImage, ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <?php else: ?>
                    <p class="seller-no-image">No product image uploaded.</p>
                    <?php endif; ?>
                </div>

                <div class="seller-form-group">
                    <label for="image">Replace Product Image</label>

                    <input type="file" id="image" name="image"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

                    <small>
                        Leave this empty to keep the current image.
                        Accepted formats: JPG, PNG, WEBP. Maximum size: 3 MB.
                    </small>
                </div>

                <div class="seller-form-actions">
                    <a href="products.php" class="seller-cancel-btn">
                        Cancel
                    </a>

                    <button type="submit" class="seller-primary-btn">
                        <span class="material-symbols-outlined">save</span>
                        Save Changes
                    </button>
                </div>

            </form>

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