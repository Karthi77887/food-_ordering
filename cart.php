```php
<?php
session_start();
require_once "includes/db.php";

// Create an empty cart.
if (!isset($_SESSION["cart"]) || !is_array($_SESSION["cart"])) {
    $_SESSION["cart"] = [];
}

$message = $_SESSION["cart_message"] ?? "";
unset($_SESSION["cart_message"]);

// Handle all cart actions.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $food_id = filter_input(INPUT_POST, "food_id", FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, "quantity", FILTER_VALIDATE_INT);
    $action = $_POST["action"] ?? "";

    if (!$food_id || $food_id < 1) {
        $_SESSION["cart_message"] = "Invalid food item.";

    } elseif ($action === "remove") {

        if (isset($_SESSION["cart"][$food_id])) {
            unset($_SESSION["cart"][$food_id]);
            $_SESSION["cart_message"] = "Item removed from cart.";
        }

    } elseif ($action === "update") {

        if (!isset($_SESSION["cart"][$food_id])) {
            $_SESSION["cart_message"] = "Item not found in cart.";

        } elseif ($quantity === false || $quantity === null) {
            $_SESSION["cart_message"] = "Please enter a valid quantity.";

        } elseif ($quantity === 0) {
            unset($_SESSION["cart"][$food_id]);
            $_SESSION["cart_message"] = "Item removed from cart.";

        } elseif ($quantity < 1 || $quantity > 99) {
            $_SESSION["cart_message"] = "Quantity must be between 1 and 99.";

        } else {
            $_SESSION["cart"][$food_id] = $quantity;
            $_SESSION["cart_message"] = "Cart updated successfully!";
        }

    } elseif (isset($_POST["add_to_cart"])) {

        if ($quantity === false || $quantity === null ||
            $quantity < 1 || $quantity > 20) {

            $_SESSION["cart_message"] = "Please select a quantity between 1 and 20.";

        } else {

            // Check that the food item exists.
            $check = $conn->prepare(
                "SELECT id FROM food_items WHERE id = ?"
            );
            $check->bind_param("i", $food_id);
            $check->execute();
            $food_exists = $check->get_result()->num_rows > 0;
            $check->close();

            if (!$food_exists) {
                $_SESSION["cart_message"] = "Food item not found.";

            } else {
                $current_quantity = (int) (
                    $_SESSION["cart"][$food_id] ?? 0
                );

                if ($current_quantity + $quantity > 99) {
                    $_SESSION["cart_message"] =
                        "Maximum quantity per food item is 99.";

                } else {
                    $_SESSION["cart"][$food_id] =
                        $current_quantity + $quantity;

                    $_SESSION["cart_message"] =
                        "Food added to your cart successfully!";
                }
            }
        }

    } else {
        $_SESSION["cart_message"] = "Invalid cart action.";
    }

    // Prevent duplicate submissions when refreshing.
    header("Location: cart.php");
    exit;
}

// Load food details for cart items.
$cart_items = [];
$total = 0;

if (!empty($_SESSION["cart"])) {

    $food_ids = [];

    foreach ($_SESSION["cart"] as $id => $qty) {
        $id = (int) $id;

        if ($id > 0) {
            $food_ids[] = $id;
        }
    }

    if (!empty($food_ids)) {

        $placeholders = implode(
            ",",
            array_fill(0, count($food_ids), "?")
        );

        $types = str_repeat("i", count($food_ids));

        $stmt = $conn->prepare(
            "SELECT id, name, price, image
             FROM food_items
             WHERE id IN ($placeholders)"
        );

        $stmt->bind_param($types, ...$food_ids);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($food = $result->fetch_assoc()) {

            $id = (int) $food["id"];
            $quantity = max(
                1,
                min(99, (int) ($_SESSION["cart"][$id] ?? 1))
            );

            $food["quantity"] = $quantity;
            $food["subtotal"] = (float) $food["price"] * $quantity;

            $total += $food["subtotal"];
            $cart_items[] = $food;
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Food Express</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .cart-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 20px;
        }

        .cart-table-wrap {
            overflow-x: auto;
            background: white;
            border-radius: 10px;
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        .cart-table th,
        .cart-table td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .cart-table th {
            background: #fff3e8;
        }

        .cart-image {
            width: 75px;
            height: 65px;
            object-fit: cover;
            border-radius: 8px;
        }

        .quantity-input {
            width: 65px;
            padding: 8px;
        }

        .cart-button {
            border: none;
            border-radius: 5px;
            padding: 8px 12px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 3px;
        }

        .update-button,
        .checkout-button {
            background: #f28c28;
            color: white;
        }

        .remove-button {
            background: #c0392b;
            color: white;
        }

        .cart-summary {
            text-align: right;
            margin-top: 20px;
            padding: 20px;
            background: #fff3e8;
            border-radius: 10px;
        }

        .cart-message {
            padding: 12px;
            margin-bottom: 15px;
            background: #e7f7e9;
            border-radius: 6px;
        }

        .empty-cart {
            text-align: center;
            padding: 40px 15px;
        }

        @media (max-width: 600px) {
            .cart-container {
                padding: 10px;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>Food Express</h1>
    <p>Your Shopping Cart</p>
</header>

<nav>
    <a href="index.php">Home</a>
    <a href="cart.php">Cart</a>
    <a href="my_orders.php">My Orders</a>

    <?php if (isset($_SESSION["user_id"])): ?>
        <a href="logout.php">Logout</a>
    <?php else: ?>
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
    <?php endif; ?>
</nav>

<main class="cart-container">

    <h2>My Cart</h2>

    <?php if ($message !== ""): ?>
        <div class="cart-message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>

        <div class="empty-cart">
            <h3>Your cart is empty!</h3>
            <p>Add some delicious food to get started.</p>

            <a class="cart-button checkout-button" href="index.php">
                Browse Food
            </a>
        </div>

    <?php else: ?>

        <div class="cart-table-wrap">
            <table class="cart-table">

                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Food</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($cart_items as $item): ?>
                        <tr>

                            <td>
                                <?php
                                $image_name = basename($item["image"] ?? "");
                                $image_path = __DIR__ . "/images/" . $image_name;
                                ?>

                                <?php if (
                                    $image_name !== "" &&
                                    is_file($image_path)
                                ): ?>
                                    <img
                                        class="cart-image"
                                        src="images/<?= rawurlencode($image_name) ?>"
                                        alt="<?= htmlspecialchars($item["name"]) ?>"
                                    >
                                <?php else: ?>
                                    No image
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($item["name"]) ?>
                            </td>

                            <td>
                                ₹<?= number_format((float) $item["price"], 2) ?>
                            </td>

                            <td>
                                <form method="POST" action="cart.php">

                                    <input
                                        type="hidden"
                                        name="food_id"
                                        value="<?= (int) $item["id"] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="update"
                                    >

                                    <input
                                        class="quantity-input"
                                        type="number"
                                        name="quantity"
                                        min="0"
                                        max="99"
                                        value="<?= (int) $item["quantity"] ?>"
                                        required
                                    >

                                    <button
                                        class="cart-button update-button"
                                        type="submit"
                                    >
                                        Update
                                    </button>

                                </form>
                            </td>

                            <td>
                                ₹<?= number_format($item["subtotal"], 2) ?>
                            </td>

                            <td>
                                <form
                                    method="POST"
                                    action="cart.php"
                                    onsubmit="return confirm('Remove this item from your cart?');"
                                >
                                    <input
                                        type="hidden"
                                        name="food_id"
                                        value="<?= (int) $item["id"] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="remove"
                                    >

                                    <button
                                        class="cart-button remove-button"
                                        type="submit"
                                    >
                                        Remove
                                    </button>
                                </form>
                            </td>

                        </tr>
                    <?php endforeach; ?>

                </tbody>
            </table>
        </div>

        <div class="cart-summary">

            <h3>
                Total: ₹<?= number_format($total, 2) ?>
            </h3>

            <a class="cart-button checkout-button" href="place_order.php">
                Place Order
            </a>

            <a
                class="cart-button"
                style="background:#555;color:white;"
                href="index.php"
            >
                Continue Shopping
            </a>

        </div>

    <?php endif; ?>

</main>

<footer>
    <p>&copy; <?= date("Y") ?> Food Express. All rights reserved.</p>
</footer>

</body>
</html>
```
