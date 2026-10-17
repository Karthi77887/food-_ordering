```php
<?php
session_start();
require_once "../includes/db.php";

// Allow only logged-in admins
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

$message = "";

// Update order status
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $order_id = (int)($_POST["order_id"] ?? 0);
    $status = $_POST["status"] ?? "";

    $allowed_statuses = [
        "Pending",
        "Confirmed",
        "Preparing",
        "Delivered",
        "Cancelled"
    ];

    if ($order_id < 1 || !in_array($status, $allowed_statuses, true)) {
        $message = "Invalid order or status.";
    } else {

        $stmt = $conn->prepare(
            "UPDATE orders SET status = ? WHERE id = ?"
        );

        $stmt->bind_param("si", $status, $order_id);
        $stmt->execute();

        if ($stmt->affected_rows >= 0) {
            header("Location: manage_orders.php?updated=1");
            exit;
        } else {
            $message = "Unable to update order status.";
        }

        $stmt->close();
    }
}

if (isset($_GET["updated"])) {
    $message = "Order status updated successfully!";
}

// Get all orders and customer names
$sql = "
    SELECT orders.id,
           orders.total_amount,
           orders.status,
           orders.created_at,
           users.name,
           users.email
    FROM orders
    INNER JOIN users ON orders.user_id = users.id
    ORDER BY orders.id DESC
";

$orders = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Orders - Food Express</title>

    <link rel="stylesheet" href="../css/style.css">

    <style>
        .orders-container {
            width: 95%;
            max-width: 1100px;
            margin: 25px auto;
        }

        .order-card {
            background: #fff;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .order-card p {
            margin: 8px 0;
        }

        .status-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            margin-top: 15px;
        }

        .status-form select {
            padding: 10px;
            border-radius: 5px;
        }

        .status-form button {
            padding: 10px 15px;
            cursor: pointer;
        }

        .message {
            text-align: center;
            color: green;
            font-weight: bold;
        }

        .empty-message {
            text-align: center;
            padding: 20px;
        }
    </style>
</head>

<body>

<header>
    <h1>Food Express - Admin</h1>

    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="manage_food.php">Manage Food</a>
        <a href="manage_orders.php">Manage Orders</a>
        <a href="../index.php">View Website</a>
        <a href="../logout.php">Logout</a>
    </nav>
</header>

<div class="orders-container">

    <h2>Manage Customer Orders</h2>

    <?php if ($message !== ""): ?>
        <p class="message">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>

    <?php if ($orders->num_rows === 0): ?>

        <p class="empty-message">
            No customer orders found yet.
        </p>

    <?php else: ?>

        <?php while ($order = $orders->fetch_assoc()): ?>

            <div class="order-card">

                <h3>
                    Order #<?= (int)$order["id"] ?>
                </h3>

                <p>
                    <strong>Customer:</strong>
                    <?= htmlspecialchars($order["name"]) ?>
                </p>

                <p>
                    <strong>Email:</strong>
                    <?= htmlspecialchars($order["email"]) ?>
                </p>

                <p>
                    <strong>Total Amount:</strong>
                    ₹<?= number_format(
                        (float)$order["total_amount"],
                        2
                    ) ?>
                </p>

                <p>
                    <strong>Order Date:</strong>
                    <?= htmlspecialchars($order["created_at"]) ?>
                </p>

                <p>
                    <strong>Current Status:</strong>
                    <?= htmlspecialchars($order["status"] ?? "Pending") ?>
                </p>
                <h4>Ordered Food Items</h4>

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

<?php $item_stmt->close(); ?>

                <form method="post" class="status-form">

                    <input
                        type="hidden"
                        name="order_id"
                        value="<?= (int)$order["id"] ?>"
                    >

                    <label for="status<?= (int)$order["id"] ?>">
                        Change Status:
                    </label>

                    <select
                        name="status"
                        id="status<?= (int)$order["id"] ?>"
                        required
                    >
                        <?php
                        $statuses = [
                            "Pending",
                            "Confirmed",
                            "Preparing",
                            "Delivered",
                            "Cancelled"
                        ];
                        ?>

                        <?php foreach ($statuses as $status): ?>
                            <option
                                value="<?= htmlspecialchars($status) ?>"
                                <?= $order["status"] === $status
                                    ? "selected"
                                    : "" ?>
                            >
                                <?= htmlspecialchars($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit">
                        Update Status
                    </button>

                </form>

            </div>

        <?php endwhile; ?>

    <?php endif; ?>

    <p>
        <a href="dashboard.php">Back to Admin Dashboard</a>
    </p>

</div>

</body>
</html>
```