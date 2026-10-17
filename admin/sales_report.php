```php
<?php
session_start();
require_once "../includes/db.php";

// Allow only admins
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

// Total number of orders
$total_orders_result = $conn->query(
    "SELECT COUNT(*) AS total FROM orders"
);
$total_orders = $total_orders_result->fetch_assoc()["total"];

// Total sales from delivered orders only
$total_sales_result = $conn->query(
    "SELECT COALESCE(SUM(total_amount), 0) AS total
     FROM orders
     WHERE status = 'Delivered'"
);
$total_sales = $total_sales_result->fetch_assoc()["total"];

// Today's delivered sales
$today_sales_result = $conn->query(
    "SELECT COALESCE(SUM(total_amount), 0) AS total
     FROM orders
     WHERE status = 'Delivered'
     AND DATE(created_at) = CURDATE()"
);
$today_sales = $today_sales_result->fetch_assoc()["total"];

// This month's delivered sales
$monthly_sales_result = $conn->query(
    "SELECT COALESCE(SUM(total_amount), 0) AS total
     FROM orders
     WHERE status = 'Delivered'
     AND YEAR(created_at) = YEAR(CURDATE())
     AND MONTH(created_at) = MONTH(CURDATE())"
);
$monthly_sales = $monthly_sales_result->fetch_assoc()["total"];

// Most popular food items by quantity ordered
$popular_sql = "
    SELECT food_items.name,
           SUM(order_items.quantity) AS total_quantity
    FROM order_items
    INNER JOIN food_items
        ON order_items.food_id = food_items.id
    INNER JOIN orders
        ON order_items.order_id = orders.id
    WHERE orders.status = 'Delivered'
    GROUP BY food_items.id, food_items.name
    ORDER BY total_quantity DESC
    LIMIT 5
";

$popular_items = $conn->query($popular_sql);

// Recent orders
$recent_sql = "
    SELECT orders.id,
           users.name AS customer_name,
           orders.total_amount,
           orders.status,
           orders.created_at
    FROM orders
    INNER JOIN users
        ON orders.user_id = users.id
    ORDER BY orders.id DESC
    LIMIT 10
";

$recent_orders = $conn->query($recent_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sales Report - Food Express</title>

    <link rel="stylesheet" href="../css/style.css">

    <style>
        .report-container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 20px;
        }

        .report-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }

        .report-card {
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
        }

        .report-card h3 {
            color: #666;
            font-size: 15px;
            margin-top: 0;
        }

        .report-card p {
            color: #f97316;
            font-size: 26px;
            font-weight: bold;
            margin-bottom: 0;
        }

        .report-section {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            overflow-x: auto;
        }

        .report-section table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-section th,
        .report-section td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .report-section th {
            background: #fff1e6;
        }

        .status-label {
            font-weight: bold;
        }

        .print-button {
            padding: 10px 18px;
            background: #f97316;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-bottom: 20px;
        }

        @media print {
            nav, footer, .print-button {
                display: none;
            }

            .report-container {
                margin: 0;
                max-width: 100%;
            }

            .report-card, .report-section {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>Food Express</h1>
    <p>Admin Sales Report</p>
</header>

<nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="add_food.php">Add Food</a>
    <a href="manage_food.php">Manage Food</a>
    <a href="manage_orders.php">Manage Orders</a>
    <a href="sales_report.php">Sales Report</a>
    <a href="../index.php">View Website</a>
    <a href="../logout.php">Logout</a>
</nav>

<main class="report-container">

    <h2>Sales Overview</h2>
    <p>Sales figures below include delivered orders only.</p>

    <button class="print-button" onclick="window.print()">
        Print / Save Report
    </button>

    <div class="report-cards">

        <div class="report-card">
            <h3>Total Orders</h3>
            <p><?= (int) $total_orders ?></p>
        </div>

        <div class="report-card">
            <h3>Total Delivered Sales</h3>
            <p>₹<?= number_format((float) $total_sales, 2) ?></p>
        </div>

        <div class="report-card">
            <h3>Today's Delivered Sales</h3>
            <p>₹<?= number_format((float) $today_sales, 2) ?></p>
        </div>

        <div class="report-card">
            <h3>This Month's Delivered Sales</h3>
            <p>₹<?= number_format((float) $monthly_sales, 2) ?></p>
        </div>

    </div>

    <section class="report-section">
        <h2>Top 5 Popular Food Items</h2>
        <p>Ranked by quantity in delivered orders.</p>

        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Food Name</th>
                    <th>Quantity Sold</th>
                </tr>
            </thead>

            <tbody>
            <?php if ($popular_items && $popular_items->num_rows > 0): ?>
                <?php $rank = 1; ?>

                <?php while ($item = $popular_items->fetch_assoc()): ?>
                    <tr>
                        <td><?= $rank++ ?></td>
                        <td><?= htmlspecialchars($item["name"]) ?></td>
                        <td><?= (int) $item["total_quantity"] ?></td>
                    </tr>
                <?php endwhile; ?>

            <?php else: ?>
                <tr>
                    <td colspan="3">
                        No delivered food orders available yet.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="report-section">
        <h2>Recent Orders</h2>

        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Details</th>
                </tr>
            </thead>

            <tbody>
            <?php if ($recent_orders && $recent_orders->num_rows > 0): ?>

                <?php while ($order = $recent_orders->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= (int) $order["id"] ?></td>

                        <td>
                            <?= htmlspecialchars($order["customer_name"]) ?>
                        </td>

                        <td>
                            ₹<?= number_format(
                                (float) $order["total_amount"], 2
                            ) ?>
                        </td>

                        <td>
                            <span class="status-label">
                                <?= htmlspecialchars($order["status"]) ?>
                            </span>
                        </td>

                        <td>
                            <?= htmlspecialchars($order["created_at"]) ?>
                        </td>

                        <td>
                            <a href="order_details.php?id=<?= (int) $order["id"] ?>">
                                View Details
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>

            <?php else: ?>
                <tr>
                    <td colspan="6">No orders found.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

</main>

<footer>
    <p>&copy; <?= date("Y") ?> Food Express</p>
</footer>

</body>
</html>

<?php
$conn->close();
?>
```