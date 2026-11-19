
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Require seller login.
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'seller'
) {
    header('Location: ../login.php');
    exit;
}

$sellerId = (int) $_SESSION['user_id'];

// Generate CSRF token.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];

$name = '';
$description = '';
$price = '';
$stock = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate CSRF token.
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    // Retrieve form values.
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $stock = trim($_POST['stock'] ?? '');

    // Validate product name.
    if ($name === '') {
        $errors[] = 'Product name is required.';
    } elseif (strlen($name) > 255) {
        $errors[] = 'Product name must not exceed 255 characters.';
    }

    // Validate description.
    if ($description === '') {
        $errors[] = 'Product description is required.';
    }

    // Validate price.
    if (
        $price === '' ||
        !is_numeric($price) ||
        (float) $price <= 0
    ) {
        $errors[] = 'Please enter a valid product price.';
    }

    // Validate stock.
    if (
        $stock === '' ||
        filter_var($stock, FILTER_VALIDATE_INT) === false ||
        (int) $stock < 0
    ) {
        $errors[] = 'Please enter a valid stock quantity.';
    }

    $imageName = null;
    $uploadedImagePath = null;

    // Handle optional product image.
    if (
        isset($_FILES['image']) &&
        $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        $image = $_FILES['image'];

        if ($image['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'There was a problem uploading the image.';
        } elseif ($image['size'] > 3 * 1024 * 1024) {
            $errors[] = 'Image size must not exceed 3 MB.';
        } else {

            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($image['tmp_name']);

            $imageInfo = @getimagesize($image['tmp_name']);

            if (
                !isset($allowedTypes[$mimeType]) ||
                $imageInfo === false ||
                $imageInfo['mime'] !== $mimeType
            ) {
                $errors[] = 'Only JPG, PNG, and WEBP images are allowed.';
            } else {

                $uploadDirectory = __DIR__ . '/uploads/';

                if (
                    !is_dir($uploadDirectory) &&
                    !mkdir($uploadDirectory, 0755, true)
                ) {
                    $errors[] = 'Unable to create the upload directory.';
                } else {

                    $imageName = bin2hex(random_bytes(16))
                        . '.'
                        . $allowedTypes[$mimeType];

                    $uploadedImagePath = $uploadDirectory . $imageName;

                    if (
                        !move_uploaded_file(
                            $image['tmp_name'],
                            $uploadedImagePath
                        )
                    ) {
                        $errors[] = 'Failed to save the uploaded image.';
                        $imageName = null;
                        $uploadedImagePath = null;
                    }
                }
            }
        }
    }

    // Insert product into database.
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO products
                (seller_id, name, description, price, image, stock)
                VALUES
                (:seller_id, :name, :description, :price, :image, :stock)'
            );

            $stmt->execute([
                ':seller_id' => $sellerId,
                ':name' => $name,
                ':description' => $description,
                ':price' => $price,
                ':image' => $imageName,
                ':stock' => (int) $stock
            ]);

            // Regenerate CSRF token.
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: products.php?added=1');
            exit;

        } catch (PDOException $e) {

            // Remove uploaded image if database insertion fails.
            if (
                $uploadedImagePath !== null &&
                file_exists($uploadedImagePath)
            ) {
                unlink($uploadedImagePath);
            }

            $errors[] = 'Unable to add product. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Product | Megatronix</title>

    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="assets/seller.css">
    <link rel="stylesheet" href="assets/add-product.css">
</head>

<body>

    <main class="seller-add-product-page">

        <div class="seller-add-product-heading">

            <div>
                <h1>Add Product</h1>
                <p>Add a new product to your Megatronix store.</p>
            </div>

            <a href="products.php" class="seller-back-link">
                Back to Products
            </a>

        </div>

        <div class="seller-add-product-card">

            <?php if (!empty($errors)): ?>
                <div class="seller-form-error">
                    <?php foreach ($errors as $error): ?>
                        <p>
                            <?= htmlspecialchars(
                                $error,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form
                action="add-product.php"
                method="POST"
                enctype="multipart/form-data"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['csrf_token'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <div class="seller-form-group">
                    <label for="name">Product Name</label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        maxlength="255"
                        placeholder="Enter product name"
                        value="<?= htmlspecialchars(
                            $name,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >
                </div>

                <div class="seller-form-group">
                    <label for="description">Product Description</label>

                    <textarea
                        id="description"
                        name="description"
                        rows="5"
                        placeholder="Enter product description"
                        required
                    ><?= htmlspecialchars(
                        $description,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?></textarea>
                </div>

                <div class="seller-form-row">

                    <div class="seller-form-group">
                        <label for="price">Price (₱)</label>

                        <input
                            type="number"
                            id="price"
                            name="price"
                            min="0.01"
                            step="0.01"
                            placeholder="Enter price"
                            value="<?= htmlspecialchars(
                                $price,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required
                        >
                    </div>

                    <div class="seller-form-group">
                        <label for="stock">Stock Quantity</label>

                        <input
                            type="number"
                            id="stock"
                            name="stock"
                            min="0"
                            step="1"
                            placeholder="Enter stock quantity"
                            value="<?= htmlspecialchars(
                                $stock,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required
                        >
                    </div>

                </div>

                <div class="seller-form-group">
                    <label for="image">Product Image</label>

                    <input
                        type="file"
                        id="image"
                        name="image"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    >

                    <small>
                        Accepted formats: JPG, PNG, WEBP. Maximum size: 3 MB.
                    </small>
                </div>

                <div class="seller-form-actions">

                    <a
                        href="products.php"
                        class="seller-cancel-btn"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="seller-primary-btn"
                    >
                        Add Product
                    </button>

                </div>

            </form>

        </div>

    </main>

</body>
</html>
