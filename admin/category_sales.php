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

// Get sales grouped by food category.
// Only delivered orders are included.
$sql = "
    SELECT
        COALESCE(NULLIF(food_items.category, ''), 'Other') AS category,
        SUM(order_items.price * order_items.quantity) AS total_sales,
        SUM(order_items.quantity) AS items_sold
    FROM order_items
    INNER JOIN food_items
        ON order_items.food_id = food_items.id
    INNER JOIN orders
        ON order_items.order_id = orders.id
    WHERE orders.status = 'Delivered'
    GROUP BY COALESCE(NULLIF(food_items.category, ''), 'Other')
    ORDER BY total_sales DESC
";

$result = $conn->query($sql);

$categories = [];
$category_sales = [];
$items_sold = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row["category"];
        $category_sales[] = (float) $row["total_sales"];
        $items_sold[] = (int) $row["items_sold"];
    }
}

$total_sales = array_sum($category_sales);
$total_items = array_sum($items_sold);
$total_categories = count($categories);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Category Sales - Food Express</title>

    <link rel="stylesheet" href="../css/style.css">

    <!-- Chart.js library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        .report-container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 20px;
        }

        .summary-cards {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }

        .summary-card,
        .chart-card {
            background: white;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
        }

        .summary-card h3 {
            font-size: 15px;
            color: #666;
            margin-top: 0;
        }

        .summary-card p {
            font-size: 24px;
            font-weight: bold;
            color: #f97316;
            margin-bottom: 0;
        }

        .chart-card {
            margin-bottom: 25px;
        }

        .chart-wrapper {
            position: relative;
            width: 100%;
            height: 400px;
        }

        .chart-button {
            background: #f97316;
            color: white;
            border: none;
            padding: 10px 16px;
            margin: 5px 5px 15px 0;
            border-radius: 6px;
            cursor: pointer;
        }

        .chart-button:hover {
            background: #ea580c;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th,
        .report-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .report-table th {
            background: #fff1e6;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .note {
            color: #666;
            font-size: 14px;
        }

        @media (max-width: 600px) {
            .report-container {
                padding: 10px;
            }

            .chart-card,
            .summary-card {
                padding: 14px;
            }

            .chart-wrapper {
                height: 320px;
            }
        }

        @media print {
            nav, footer, .chart-button, .back-link {
                display: none;
            }

            .report-container {
                margin: 0;
                max-width: 100%;
            }

            .summary-card, .chart-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>Food Express</h1>
    <p>Food Category Sales Analytics</p>
</header>

<nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="manage_food.php">Manage Food</a>
    <a href="manage_orders.php">Manage Orders</a>
    <a href="sales_report.php">Sales Report</a>
    <a href="sales_chart.php">Monthly Chart</a>
    <a href="category_sales.php">Category Sales</a>
    <a href="../index.php">View Website</a>
    <a href="../logout.php">Logout</a>
</nav>

<main class="report-container">

    <h2>Food Category Sales</h2>

    <p>
        Compare revenue and the quantity sold in each food category.
    </p>

    <p class="note">
        This report includes delivered orders only.
    </p>

    <div class="summary-cards">

        <div class="summary-card">
            <h3>Total Delivered Sales</h3>
            <p>₹<?= number_format($total_sales, 2) ?></p>
        </div>

        <div class="summary-card">
            <h3>Food Categories Sold</h3>
            <p><?= $total_categories ?></p>
        </div>

        <div class="summary-card">
            <h3>Total Items Sold</h3>
            <p><?= $total_items ?></p>
        </div>

    </div>

    <section class="chart-card">

        <h2>Sales by Category</h2>

        <button class="chart-button"
                type="button"
                onclick="showChart('pie')">
            Pie Chart
        </button>

        <button class="chart-button"
                type="button"
                onclick="showChart('bar')">
            Bar Chart
        </button>

        <button class="chart-button"
                type="button"
                onclick="window.print()">
            Print / Save PDF
        </button>

        <div class="chart-wrapper">
            <canvas id="categoryChart"></canvas>
        </div>

        <p id="empty-message" class="note" hidden>
            No delivered orders are available yet.
            The chart will appear after an order is marked Delivered.
        </p>

    </section>

    <section class="chart-card">

        <h2>Category Sales Details</h2>

        <div class="table-wrapper">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Items Sold</th>
                        <th>Total Sales</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($total_categories > 0): ?>

                    <?php foreach ($categories as $index => $category): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($category) ?>
                            </td>

                            <td>
                                <?= $items_sold[$index] ?>
                            </td>

                            <td>
                                ₹<?= number_format(
                                    $category_sales[$index], 2
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="3">
                            No delivered sales found yet.
                        </td>
                    </tr>

                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </section>

    <p class="back-link">
        <a href="sales_report.php">Back to Sales Report</a>
    </p>

</main>

<footer>
    <p>&copy; <?= date("Y") ?> Food Express</p>
</footer>

<script>
    const categoryLabels = <?= json_encode(
        $categories,
        JSON_HEX_TAG | JSON_HEX_APOS |
        JSON_HEX_AMP | JSON_HEX_QUOT
    ) ?>;

    const categorySales = <?= json_encode(
        $category_sales,
        JSON_HEX_TAG | JSON_HEX_APOS |
        JSON_HEX_AMP | JSON_HEX_QUOT
    ) ?>;

    const canvas = document.getElementById("categoryChart");
    const emptyMessage = document.getElementById("empty-message");

    let categoryChart = null;

    function showChart(type) {
        if (categoryChart) {
            categoryChart.destroy();
        }

        if (categoryLabels.length === 0) {
            canvas.style.display = "none";
            emptyMessage.hidden = false;
            return;
        }

        canvas.style.display = "block";
        emptyMessage.hidden = true;

        const chartColors = [
            "#f97316",
            "#3b82f6",
            "#22c55e",
            "#eab308",
            "#a855f7",
            "#ef4444",
            "#14b8a6",
            "#ec4899"
        ];

        categoryChart = new Chart(canvas, {
            type: type,

            data: {
                labels: categoryLabels,

                datasets: [{
                    label: "Sales (₹)",
                    data: categorySales,
                    backgroundColor: categoryLabels.map(
                        (_, index) =>
                            chartColors[index % chartColors.length]
                    ),
                    borderColor: "#ffffff",
                    borderWidth: 2
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                plugins: {
                    legend: {
                        display: true,
                        position: "bottom"
                    },

                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = Number(
                                    context.raw
                                ).toLocaleString("en-IN", {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });

                                return context.label + ": ₹" + value;
                            }
                        }
                    }
                },

                scales: type === "bar" ? {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return "₹" +
                                    Number(value).toLocaleString("en-IN");
                            }
                        }
                    }
                } : {}
            }
        });
    }

    showChart("pie");
</script>

</body>
</html>

<?php
$conn->close();
?>
```