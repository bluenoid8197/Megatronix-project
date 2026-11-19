
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

// Deletion must use POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php');
    exit;
}

$sellerId = (int) $_SESSION['user_id'];

// Verify CSRF token.
if (
    !isset($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    header('Location: products.php?error=invalid_request');
    exit;
}

// Validate product ID.
$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

if (!$productId || $productId < 1) {
    header('Location: products.php?error=invalid_product');
    exit;
}

// Find the product and verify ownership.
$stmt = $pdo->prepare("
    SELECT id, image
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

if (!$product) {
    header('Location: products.php?error=not_found');
    exit;
}

try {
    $pdo->beginTransaction();

    // Preserve order history while removing the product relationship.
    // order_items already stores the product name and price.
    $stmt = $pdo->prepare("
        UPDATE order_items
        SET product_id = NULL
        WHERE product_id = :product_id
    ");

    $stmt->execute([
        ':product_id' => $productId
    ]);

    // Delete only the seller's own product.
    $stmt = $pdo->prepare("
        DELETE FROM products
        WHERE id = :product_id
          AND seller_id = :seller_id
    ");

    $stmt->execute([
        ':product_id' => $productId,
        ':seller_id' => $sellerId
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Product deletion failed.');
    }

    $pdo->commit();

    // Remove the uploaded image after the database deletion succeeds.
    if (
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

    // Prevent reuse of the submitted form token.
    unset($_SESSION['csrf_token']);

    header('Location: products.php?deleted=1');
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: products.php?error=delete_failed');
    exit;
}
