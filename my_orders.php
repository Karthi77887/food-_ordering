```php
<?php
session_start();
require_once "includes/db.php";

// Check login
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];

// Get this user's orders only
$stmt = $conn->prepare(
    "SELECT id, total_amount, status, created_at
     FROM orders
     WHERE user_id = ?
     ORDER BY id DESC"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Food Express</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header>
    <h1>Food Express</h1>
    <nav>
        <a href="index.php">Home</a>
        <a href="cart.php">My Cart</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main class="container">
    <h2>My Orders</h2>

    <?php if ($orders->num_rows === 0): ?>
        <p>You have not placed any orders yet.</p>
        <a href="index.php">Order Food Now</a>

    <?php else: ?>

        <?php while ($order = $orders->fetch_assoc()): ?>

            <div class="food-card">
                <h3>Order #<?= (int)$order["id"] ?></h3>

                <p>
                    <strong>Date:</strong>
                    <?= htmlspecialchars($order["created_at"]) ?>
                </p>

                <p>
                    <strong>Total:</strong>
                    ₹<?= number_format((float)$order["total_amount"], 2) ?>
                </p>

                <p>
                    <strong>Status:</strong>
                    <?= htmlspecialchars($order["status"] ?? "Pending") ?>
                </p>

                <h4>Ordered Items</h4>

                <?php
                $order_id = (int)$order["id"];

                $item_stmt = $conn->prepare(
                    "SELECT food_items.name,
                            order_items.quantity,
                            order_items.price
                     FROM order_items
                     INNER JOIN food_items
                         ON order_items.food_id = food_items.id
                     WHERE order_items.order_id = ?"
                );

                $item_stmt->bind_param("i", $order_id);
                $item_stmt->execute();
                $items = $item_stmt->get_result();
                ?>

                <?php if ($items->num_rows > 0): ?>
                    <ul>
                        <?php while ($item = $items->fetch_assoc()): ?>
                            <li>
                                <?= htmlspecialchars($item["name"]) ?>
                                × <?= (int)$item["quantity"] ?>
                                — ₹<?= number_format(
                                    (float)$item["price"] * (int)$item["quantity"],
                                    2
                                ) ?>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p>No item details found for this order.</p>
                <?php endif; ?>

                <?php $item_stmt->close(); ?>
            </div>

        <?php endwhile; ?>

    <?php endif; ?>

    <p>
        <a href="index.php">Continue Shopping</a>
    </p>
</main>

</body>
</html>

<?php
$stmt->close();
?>
```
