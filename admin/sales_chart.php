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

// Prepare the last 12 months, including months with zero sales
$labels = [];
$sales = [];

for ($i = 11; $i >= 0; $i--) {
    $month_date = strtotime("first day of this month - $i months");

    $key = date("Y-m", $month_date);
    $labels[$key] = date("M Y", $month_date);
    $sales[$key] = 0;
}

// Get actual sales from delivered orders
$sql = "
    SELECT
        DATE_FORMAT(created_at, '%Y-%m') AS month_key,
        SUM(total_amount) AS monthly_sales
    FROM orders
    WHERE status = 'Delivered'
      AND created_at >= DATE_FORMAT(
          DATE_SUB(CURDATE(), INTERVAL 11 MONTH),
          '%Y-%m-01'
      )
      AND created_at < DATE_FORMAT(
          DATE_ADD(CURDATE(), INTERVAL 1 MONTH),
          '%Y-%m-01'
      )
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month_key
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $key = $row["month_key"];

        if (isset($sales[$key])) {
            $sales[$key] = (float) $row["monthly_sales"];
        }
    }
}

// Prepare chart data
$chart_labels = array_values($labels);
$chart_sales = array_values($sales);

$total_sales = array_sum($chart_sales);
$months_with_sales = count(
    array_filter($chart_sales, function ($amount) {
        return $amount > 0;
    })
);

$average_sales = $total_sales / 12;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Monthly Sales Chart - Food Express</title>

    <link rel="stylesheet" href="../css/style.css">

    <!-- Chart.js library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        .chart-container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 20px;
        }

        .chart-card {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
            margin-top: 25px;
        }

        .summary-cards {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }

        .summary-card {
            background: #fff;
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

        .chart-wrapper {
            position: relative;
            width: 100%;
            height: 380px;
        }

        .chart-button {
            background: #f97316;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 10px 16px;
            cursor: pointer;
            margin: 5px 5px 10px 0;
        }

        .chart-button:hover {
            background: #ea580c;
        }

        .chart-note {
            color: #666;
            font-size: 14px;
        }

        @media (max-width: 600px) {
            .chart-container {
                padding: 10px;
            }

            .chart-card {
                padding: 12px;
            }

            .chart-wrapper {
                height: 300px;
            }
        }

        @media print {
            nav, footer, .chart-button {
                display: none;
            }

            .chart-container {
                margin: 0;
                max-width: 100%;
            }

            .chart-card, .summary-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>Food Express</h1>
    <p>Monthly Sales Analytics</p>
</header>

<nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="manage_food.php">Manage Food</a>
    <a href="manage_orders.php">Manage Orders</a>
    <a href="sales_report.php">Sales Report</a>
    <a href="sales_chart.php">Sales Chart</a>
    <a href="../index.php">View Website</a>
    <a href="../logout.php">Logout</a>
</nav>

<main class="chart-container">

    <h2>Monthly Sales Chart</h2>

    <p>
        View your monthly revenue from successfully delivered orders
        over the last 12 months.
    </p>

    <div class="summary-cards">

        <div class="summary-card">
            <h3>Total Sales (12 Months)</h3>
            <p>
                ₹<?= number_format($total_sales, 2) ?>
            </p>
        </div>

        <div class="summary-card">
            <h3>Average Monthly Sales</h3>
            <p>
                ₹<?= number_format($average_sales, 2) ?>
            </p>
        </div>

        <div class="summary-card">
            <h3>Months With Sales</h3>
            <p><?= $months_with_sales ?> / 12</p>
        </div>

    </div>

    <section class="chart-card">

        <h2>Sales by Month</h2>

        <button class="chart-button"
                type="button"
                onclick="showBarChart()">
            Bar Chart
        </button>

        <button class="chart-button"
                type="button"
                onclick="showLineChart()">
            Line Chart
        </button>

        <button class="chart-button"
                type="button"
                onclick="window.print()">
            Print / Save PDF
        </button>

        <p class="chart-note">
            Only orders with the Delivered status are counted.
        </p>

        <div class="chart-wrapper">
            <canvas id="salesChart"></canvas>
        </div>

    </section>

    <p>
        <a href="sales_report.php">Back to Sales Report</a>
    </p>

</main>

<footer>
    <p>&copy; <?= date("Y") ?> Food Express</p>
</footer>

<script>
    const monthLabels = <?= json_encode(
        $chart_labels,
        JSON_HEX_TAG | JSON_HEX_APOS |
        JSON_HEX_AMP | JSON_HEX_QUOT
    ) ?>;

    const monthlySales = <?= json_encode(
        $chart_sales,
        JSON_HEX_TAG | JSON_HEX_APOS |
        JSON_HEX_AMP | JSON_HEX_QUOT
    ) ?>;

    const ctx = document.getElementById("salesChart");

    let salesChart;

    function createChart(chartType) {
        if (salesChart) {
            salesChart.destroy();
        }

        salesChart = new Chart(ctx, {
            type: chartType,

            data: {
                labels: monthLabels,

                datasets: [{
                    label: "Monthly Sales (₹)",
                    data: monthlySales,

                    backgroundColor: chartType === "bar"
                        ? "rgba(249, 115, 22, 0.7)"
                        : "rgba(249, 115, 22, 0.15)",

                    borderColor: "rgb(249, 115, 22)",
                    borderWidth: 2,

                    borderRadius: chartType === "bar" ? 6 : 0,

                    fill: chartType === "line",
                    tension: 0.3
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                interaction: {
                    intersect: false,
                    mode: "index"
                },

                plugins: {
                    legend: {
                        display: true
                    },

                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return "Sales: ₹" +
                                    Number(context.raw).toLocaleString(
                                        "en-IN",
                                        {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        }
                                    );
                            }
                        }
                    }
                },

                scales: {
                    y: {
                        beginAtZero: true,

                        ticks: {
                            callback: function(value) {
                                return "₹" +
                                    Number(value).toLocaleString("en-IN");
                            }
                        }
                    }
                }
            }
        });
    }

    function showBarChart() {
        createChart("bar");
    }

    function showLineChart() {
        createChart("line");
    }

    // Display the bar chart when the page opens
    createChart("bar");
</script>

</body>
</html>

<?php
$conn->close();
?>
```