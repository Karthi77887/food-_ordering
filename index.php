```php
<?php
session_start();
require_once "includes/db.php";

$search = trim($_GET["search"] ?? "");
$category = trim($_GET["category"] ?? "");

$categories = [
    "Pizza",
    "Burger",
    "Fried Rice",
    "French Fries",
    "Drinks",
    "Other"
];

$searchTerm = "%" . $search . "%";

if ($search !== "" && $category !== "") {
    $stmt = $conn->prepare(
        "SELECT * FROM food_items
         WHERE category = ?
         AND (name LIKE ? OR description LIKE ?)
         ORDER BY id DESC"
    );
    $stmt->bind_param("sss", $category, $searchTerm, $searchTerm);

} elseif ($search !== "") {
    $stmt = $conn->prepare(
        "SELECT * FROM food_items
         WHERE name LIKE ? OR description LIKE ?
         ORDER BY id DESC"
    );
    $stmt->bind_param("ss", $searchTerm, $searchTerm);

} elseif ($category !== "") {
    $stmt = $conn->prepare(
        "SELECT * FROM food_items
         WHERE category = ?
         ORDER BY id DESC"
    );
    $stmt->bind_param("s", $category);

} else {
    $stmt = $conn->prepare(
        "SELECT * FROM food_items ORDER BY id DESC"
    );
}

if (!$stmt || !$stmt->execute()) {
    die("Unable to load the food menu.");
}

$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Express - Online Food Ordering</title>

    <link rel="stylesheet" href="/food_ordering/css/style.css">

    <style>
        .filter-section {
            max-width: 1000px;
            margin: 25px auto;
            padding: 20px;
            text-align: center;
        }

        .filter-form {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .filter-form input,
        .filter-form select {
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
        }

        .filter-form input[type="text"] {
            width: 260px;
            max-width: 100%;
        }

        .filter-form button,
        .clear-filter {
            display: inline-block;
            padding: 12px 18px;
            border: none;
            border-radius: 6px;
            background: #ff7a00;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 15px;
        }

        .clear-filter {
            background: #555;
        }

        .food-category {
            color: #777;
            font-size: 14px;
        }

        .no-results {
            text-align: center;
            padding: 30px;
        }

        @media (max-width: 600px) {
            .filter-form {
                flex-direction: column;
            }

            .filter-form input,
            .filter-form select,
            .filter-form button,
            .filter-form a {
                width: 100%;
                box-sizing: border-box;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>Food Express</h1>
    <p>Order your favourite food online!</p>
</header>

<nav>
    <a href="index.php">Home</a>
    <a href="cart.php">Shopping Cart</a>
    <a href="my_orders.php">My Orders</a>

    <?php if (isset($_SESSION["user_id"])): ?>
        <a href="logout.php">Logout</a>
    <?php else: ?>
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
    <?php endif; ?>
</nav>

<h2>Our Food Menu</h2>

<section class="filter-section">
    <form action="index.php" method="get" class="filter-form">

        <input
            type="text"
            name="search"
            placeholder="Search food..."
            value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
        >

        <select name="category">
            <option value="">All Categories</option>

            <?php foreach ($categories as $cat): ?>
                <option
                    value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>"
                    <?= $category === $cat ? "selected" : "" ?>
                >
                    <?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Search / Filter</button>
        <a href="index.php" class="clear-filter">Clear</a>
    </form>
</section>

<div class="food-container">

    <?php if ($result->num_rows > 0): ?>

        <?php while ($food = $result->fetch_assoc()): ?>
            <div class="food-card">

                <?php
                $imageName = basename($food["image"] ?? "");
                $imagePath = __DIR__ . "/images/" . $imageName;
                ?>

                <?php if ($imageName !== "" && is_file($imagePath)): ?>
                    <img
                        src="images/<?= rawurlencode($imageName) ?>"
                        alt="<?= htmlspecialchars($food["name"], ENT_QUOTES, 'UTF-8') ?>"
                    >
                <?php else: ?>
                    <div style="padding: 30px; text-align: center;">
                        No image available
                    </div>
                <?php endif; ?>

                <h3><?= htmlspecialchars($food["name"]) ?></h3>

                <p class="food-category">
                    Category:
                    <?= htmlspecialchars($food["category"] ?? "Other") ?>
                </p>

                <p>
                    <?= htmlspecialchars($food["description"] ?? "") ?>
                </p>

                <p class="price">
                    ₹<?= number_format((float)$food["price"], 2) ?>
                </p>

                <form action="cart.php" method="post">
                    <input
                        type="hidden"
                        name="food_id"
                        value="<?= (int)$food["id"] ?>"
                    >

                    <label for="quantity-<?= (int)$food["id"] ?>">
                        Quantity:
                    </label>

                    <input
                        type="number"
                        id="quantity-<?= (int)$food["id"] ?>"
                        name="quantity"
                        value="1"
                        min="1"
                        max="20"
                        required
                    >

                    <button type="submit" name="add_to_cart">
                        Add to Cart
                    </button>
                </form>

            </div>
        <?php endwhile; ?>

    <?php else: ?>
        <div class="no-results">
            <h3>No food items found.</h3>
            <p>Try another food name or select a different category.</p>
            <a href="index.php">Show all food items</a>
        </div>
    <?php endif; ?>

</div>

<footer>
    <p>&copy; <?= date("Y") ?> Food Express. All rights reserved.</p>
</footer>

</body>
</html>
```