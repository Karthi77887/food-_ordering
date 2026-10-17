```php
<?php
session_start();
require_once "includes/db.php";

// Allow only logged-in users
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];

// Fetch this user's orders
$stmt = $conn->prepare(
    "SELECT id, total_amount, status, created_at
     FROM orders
     WHERE user_id = ?
     ORDER BY id DESC"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result();

function getStatusClass($status) {
    switch ($status) {
        case "Confirmed":
            return "confirmed";
        case "Preparing":
            return "preparing";
        case "Delivered":
            return "delivered";
        case "Cancelled":
            return "cancelled";
        default:
            return "pending";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Food Express</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .orders-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 20px;
        }

        .order-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 22px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 12px;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 13px;
        }

        .pending {
            background: #ffedd5;
            color: #9a3412;
        }

        .confirmed {
            background: #dcfce7;
            color: #166534;
        }

        .preparing {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .delivered {
            background: #d1fae5;
            color: #065f46;
        }

        .cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .order-items {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        .order-items th,
        .order-items td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .order-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .order-actions a {
            display: inline-block;
            padding: 10px 16px;
            background: #f97316;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }

        .empty-orders {
            text-align: center;
            padding: 35px 15px;
        }

        @media (max-width: 600px) {
            .orders-container {
                padding: 10px;
            }

            .order-card {
                padding: 14px;
                overflow-x: auto;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>Food Express</h1>
    <p>Track your food orders</p>
</header>

<nav>
    <a href="index.php">Home</a>
    <a href="cart.php">My Cart</a>
    <a href="my_orders.php">My Orders</a>
    <a href="logout.php">Logout</a>
</nav>

<main class="orders-container">
    <h2>My Orders</h2>

    <?php if ($orders->num_rows > 0): ?>

        <?php while ($order = $orders->fetch_assoc()): ?>

            <?php
            $order_id = (int) $order["id"];

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

            <section class="order-card">

                <div class="order-header">
                    <h3>Order #<?= $order_id ?></h3>

                    <span class="status <?= htmlspecialchars(
                        getStatusClass($order["status"])
                    ) ?>">
                        <?= htmlspecialchars($order["status"]) ?>
                    </span>
                </div>

                <p>
                    <strong>Order Date:</strong>
                    <?= htmlspecialchars($order["created_at"]) ?>
                </p>

                <h4>Food Items</h4>

                <table class="order-items">
                    <thead>
                        <tr>
                            <th>Food</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php while ($item = $items->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($item["name"]) ?>
                                </td>

                                <td>
                                    <?= (int) $item["quantity"] ?>
                                </td>

                                <td>
                                    ₹<?= number_format(
                                        (float) $item["price"], 2
                                    ) ?>
                                </td>

                                <td>
                                    ₹<?= number_format(
                                        (float) $item["price"] *
                                        (int) $item["quantity"], 2
                                    ) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <?php $item_stmt->close(); ?>

                <h3>
                    Total: ₹<?= number_format(
                        (float) $order["total_amount"], 2
                    ) ?>
                </h3>

                <div class="order-actions">
                    <a href="index.php">Order More Food</a>
                </div>

            </section>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="empty-orders">
            <h3>You haven't placed any orders yet.</h3>
            <p>Explore our menu and order your favourite food!</p>
            <a href="index.php">Browse Food Menu</a>
        </div>

    <?php endif; ?>

</main>

<footer>
    <p>&copy; <?= date("Y") ?> Food Express</p>
</footer>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
```
