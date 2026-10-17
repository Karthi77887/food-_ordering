
<?php
session_start();
require_once "../includes/db.php";

// Allow only admin users.
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

// Get total food items.
$food_result = $conn->query("SELECT COUNT(*) AS total FROM food_items");
$total_food = $food_result->fetch_assoc()["total"];

// Get total registered users.
$user_result = $conn->query(
    "SELECT COUNT(*) AS total FROM users WHERE role = 'user'"
);
$total_users = $user_result->fetch_assoc()["total"];

// Get total orders.
$order_result = $conn->query("SELECT COUNT(*) AS total FROM orders");
$total_orders = $order_result->fetch_assoc()["total"];

// Get pending orders.
$pending_result = $conn->query(
    "SELECT COUNT(*) AS total FROM orders WHERE status = 'Pending'"
);
$pending_orders = $pending_result->fetch_assoc()["total"];

// Get delivered orders.
$delivered_result = $conn->query(
    "SELECT COUNT(*) AS total FROM orders WHERE status = 'Delivered'"
);
$delivered_orders = $delivered_result->fetch_assoc()["total"];

// Get total sales from delivered orders.
$sales_result = $conn->query(
    "SELECT COALESCE(SUM(total_amount), 0) AS total
     FROM orders
     WHERE status = 'Delivered'"
);
$total_sales = $sales_result->fetch_assoc()["total"];

// Get the latest 5 orders.
$recent_orders = $conn->query(
    "SELECT orders.id, orders.total_amount, orders.status,
            orders.created_at, users.name AS customer_name
     FROM orders
     INNER JOIN users ON orders.user_id = users.id
     ORDER BY orders.id DESC
     LIMIT 5"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard - Food Express</title>

    <link rel="stylesheet" href="../css/style.css">

    <style>
        .dashboard {
            max-width: 1150px;
            margin: 30px auto;
            padding: 20px;
        }

        .welcome {
            margin-bottom: 25px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.07);
            border-left: 5px solid #f28c28;
        }

        .stat-card h3 {
            margin: 0 0 12px;
            color: #555;
            font-size: 16px;
        }

        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }

        .dashboard-section {
            background: white;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.07);
        }

        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .quick-links a {
            background: #f28c28;
            color: white;
            padding: 11px 16px;
            border-radius: 7px;
            text-decoration: none;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        .orders-table th,
        .orders-table td {
            text-align: left;
            padding: 13px;
            border-bottom: 1px solid #eee;
        }

        .orders-table th {
            background: #fff3e8;
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 5px;
            background: #fff0d9;
            color: #8a5200;
            font-size: 13px;
        }

        .status-delivered {
            background: #e4f7e8;
            color: #216b2b;
        }

        .status-cancelled {
            background: #ffe6e3;
            color: #a12b1e;
        }

        .view-link {
            color: #d96b00;
            font-weight: bold;
            text-decoration: none;
        }

        .empty-message {
            padding: 20px;
            text-align: center;
        }

        @media (max-width: 600px) {
            .dashboard {
                padding: 10px;
            }
        }
    </style>
</head>
<body>

<header>
    <h1>Food Express</h1>
    <p>Admin Dashboard</p>
</header>

<nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="add_food.php">Add Food</a>
    <a href="manage_food.php">Manage Food</a>
    <a href="manage_orders.php">Manage Orders</a>
    <a href="../index.php">View Website</a>
    <a href="sales_report.php">Sales Report</a>
    <a href="sales_chart.php">Sales Chart</a>
    <a href="category_sales.php">Category Sales</a>
    <a href="../logout.php">Logout</a>
</nav>

<main class="dashboard">

    <div class="welcome">
        <h2>Welcome, <?= htmlspecialchars($_SESSION["name"] ?? "Admin") ?>!</h2>
        <p>Here is your Food Express business overview.</p>
    </div>

    <section class="stats-grid">

        <div class="stat-card">
            <h3>Total Food Items</h3>
            <div class="stat-number"><?= (int) $total_food ?></div>
        </div>

        <div class="stat-card">
            <h3>Registered Customers</h3>
            <div class="stat-number"><?= (int) $total_users ?></div>
        </div>

        <div class="stat-card">
            <h3>Total Orders</h3>
            <div class="stat-number"><?= (int) $total_orders ?></div>
        </div>

        <div class="stat-card">
            <h3>Pending Orders</h3>
            <div class="stat-number"><?= (int) $pending_orders ?></div>
        </div>

        <div class="stat-card">
            <h3>Delivered Orders</h3>
            <div class="stat-number"><?= (int) $delivered_orders ?></div>
        </div>

        <div class="stat-card">
            <h3>Delivered Sales</h3>
            <div class="stat-number">
                ₹<?= number_format((float) $total_sales, 2) ?>
            </div>
        </div>

    </section>

    <section class="dashboard-section">
        <h2>Quick Actions</h2>

        <div class="quick-links">
            <a href="add_food.php">+ Add Food</a>
            <a href="manage_food.php">Manage Food</a>
            <a href="manage_orders.php">Manage Orders</a>
            <a href="../index.php">View Website</a>
        </div>
    </section>

    <section class="dashboard-section">
        <h2>Recent Orders</h2>

        <?php if ($recent_orders && $recent_orders->num_rows > 0): ?>

            <div class="table-wrap">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php while ($order = $recent_orders->fetch_assoc()): ?>
                        <?php
                        $status_class = "";

                        if ($order["status"] === "Delivered") {
                            $status_class = "status-delivered";
                        } elseif ($order["status"] === "Cancelled") {
                            $status_class = "status-cancelled";
                        }
                        ?>

                        <tr>
                            <td>#<?= (int) $order["id"] ?></td>

                            <td>
                                <?= htmlspecialchars($order["customer_name"]) ?>
                            </td>

                            <td>
                                ₹<?= number_format(
                                    (float) $order["total_amount"],
                                    2
                                ) ?>
                            </td>

                            <td>
                                <span class="status <?= $status_class ?>">
                                    <?= htmlspecialchars($order["status"]) ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars($order["created_at"]) ?>
                            </td>

                            <td>
                                <a class="view-link"
                                   href="order_details.php?id=<?= (int) $order["id"] ?>">
                                    View
                                </a>
                            </td>
                        </tr>

                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>

            <div class="empty-message">
                <p>No orders have been placed yet.</p>
            </div>

        <?php endif; ?>
    </section>

</main>

<footer>
    <p>&copy; <?= date("Y") ?> Food Express. All rights reserved.</p>
</footer>

</body>
</html>
