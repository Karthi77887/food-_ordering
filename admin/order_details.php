```php
<?php
session_start();
require_once "../includes/db.php";

// Admin access check
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

$order_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$order_id || $order_id < 1) {
    exit("Invalid order ID.");
}

// Get order and customer details
$stmt = $conn->prepare(
    "SELECT orders.*, users.name AS customer_name,
            users.email AS customer_email
     FROM orders
     JOIN users ON orders.user_id = users.id
     WHERE orders.id = ?"
);

$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    exit("Order not found.");
}

// Get the food items in this order
$stmt = $conn->prepare(
    "SELECT food_items.name, order_items.quantity,
            order_items.price
     FROM order_items
     JOIN food_items ON order_items.food_id = food_items.id
     WHERE order_items.order_id = ?"
);

$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Food Express</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<header>
    <h1>Food Express Admin</h1>
    <p>Order Details</p>
</header>

<p style="text-align:center;">
    <a href="dashboard.php">Dashboard</a> |
    <a href="orders.php">Back to Orders</a> |
    <a href="../logout.php">Logout</a>
</p>

<h2 style="text-align:center;">
    Order #<?= (int)$order["id"] ?>
</h2>

<div style="max-width:800px; margin:20px auto; padding:15px;">

    <p>
        <strong>Customer:</strong>
        <?= htmlspecialchars($order["customer_name"]) ?>
    </p>

    <p>
        <strong>Email:</strong>
        <?= htmlspecialchars($order["customer_email"]) ?>
    </p>

    <p>
        <strong>Order Date:</strong>
        <?= htmlspecialchars($order["created_at"]) ?>
    </p>

    <p>
        <strong>Status:</strong>
        <?= htmlspecialchars($order["status"]) ?>
    </p>

    <h3>Ordered Food Items</h3>

    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="padding:10px; border:1px solid #ccc;">
                        Food Name
                    </th>
                    <th style="padding:10px; border:1px solid #ccc;">
                        Quantity
                    </th>
                    <th style="padding:10px; border:1px solid #ccc;">
                        Unit Price
                    </th>
                    <th style="padding:10px; border:1px solid #ccc;">
                        Subtotal
                    </th>
                </tr>
            </thead>

            <tbody>
                <?php
                $calculated_total = 0;

                while ($item = $items->fetch_assoc()):
                    $subtotal = (float)$item["price"] * (int)$item["quantity"];
                    $calculated_total += $subtotal;
                ?>
                    <tr>
                        <td style="padding:10px; border:1px solid #ccc;">
                            <?= htmlspecialchars($item["name"]) ?>
                        </td>

                        <td style="padding:10px; border:1px solid #ccc;">
                            <?= (int)$item["quantity"] ?>
                        </td>

                        <td style="padding:10px; border:1px solid #ccc;">
                            ₹<?= number_format((float)$item["price"], 2) ?>
                        </td>

                        <td style="padding:10px; border:1px solid #ccc;">
                            ₹<?= number_format($subtotal, 2) ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>

            <tfoot>
                <tr>
                    <th colspan="3"
                        style="padding:10px; border:1px solid #ccc; text-align:right;">
                        Total
                    </th>
                    <th style="padding:10px; border:1px solid #ccc;">
                        ₹<?= number_format($calculated_total, 2) ?>
                    </th>
                </tr>
            </tfoot>
        </table>
    </div>

</div>

</body>
</html>
```