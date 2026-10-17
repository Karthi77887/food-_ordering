```php
<?php
session_start();
require_once "includes/db.php";

// Check if the user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Check if the cart is empty
$cart = $_SESSION["cart"] ?? [];

if (empty($cart)) {
    header("Location: cart.php");
    exit;
}

try {
    // Start database transaction
    $conn->begin_transaction();

    $total = 0;
    $items = [];

    // Check food items and calculate total
    foreach ($cart as $id => $quantity) {
        $id = (int)$id;
        $quantity = (int)$quantity;

        if ($id < 1 || $quantity < 1 || $quantity > 20) {
            throw new Exception("Invalid cart item.");
        }

        $stmt = $conn->prepare(
            "SELECT id, price FROM food_items WHERE id = ?"
        );

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $result = $stmt->get_result();
        $food = $result->fetch_assoc();
        $stmt->close();

        if (!$food) {
            throw new Exception("Food item not found.");
        }

        $price = (float)$food["price"];
        $total += $price * $quantity;

        $items[] = [
            "food_id" => $id,
            "quantity" => $quantity,
            "price" => $price
        ];
    }

    // Get logged-in user's ID
    $user_id = (int)$_SESSION["user_id"];

    // Create the order
    $stmt = $conn->prepare(
        "INSERT INTO orders (user_id, total_amount)
         VALUES (?, ?)"
    );

    $stmt->bind_param("id", $user_id, $total);
    $stmt->execute();

    $order_id = $conn->insert_id;
    $stmt->close();

    // Save the ordered food items
    $stmt = $conn->prepare(
        "INSERT INTO order_items
         (order_id, food_id, quantity, price)
         VALUES (?, ?, ?, ?)"
    );

    foreach ($items as $item) {
        $food_id = $item["food_id"];
        $quantity = $item["quantity"];
        $price = $item["price"];

        $stmt->bind_param(
            "iiid",
            $order_id,
            $food_id,
            $quantity,
            $price
        );

        $stmt->execute();
    }

    $stmt->close();

    // Save all database changes
    $conn->commit();

    // Clear cart after successful order
    unset($_SESSION["cart"]);

    // Show success message
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Order Successful - Food Express</title>
        <link rel='stylesheet' href='css/style.css'>
    </head>
    <body>
        <div class='container'>
            <h1>Order Placed Successfully!</h1>
            <p>Thank you for ordering with Food Express.</p>
            <p>Your Order ID is: <strong>" . (int)$order_id . "</strong></p>
            <p>
                <a href='my_orders.php'>View My Orders</a>
            </p>
            <p>
                <a href='index.php'>Continue Shopping</a>
            </p>
        </div>
    </body>
    </html>";

} catch (Throwable $e) {
    // Undo database changes if something goes wrong
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
        // Ignore rollback errors
    }

    http_response_code(400);
    echo "Unable to place your order. Please return to your cart and try again.";
    echo "<br><a href='cart.php'>Back to Cart</a>";
}
?>
```
