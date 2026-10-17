
<?php
session_start();
require_once "../includes/db.php";

// Only admins can manage food.
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

// Create a security token for delete requests.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$message_type = "success";

// Handle food deletion.
if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "delete") {

    $token = $_POST["csrf_token"] ?? "";
    $food_id = filter_input(INPUT_POST, "food_id", FILTER_VALIDATE_INT);

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)
    ) {
        $message = "Invalid request. Please refresh the page and try again.";
        $message_type = "error";
    } elseif (!$food_id || $food_id < 1) {
        $message = "Invalid food item.";
        $message_type = "error";
    } else {
        // Check whether this food item is used in an existing order.
        $check = $conn->prepare(
            "SELECT COUNT(*) FROM order_items WHERE food_id = ?"
        );
        $check->bind_param("i", $food_id);
        $check->execute();
        $check->bind_result($order_item_count);
        $check->fetch();
        $check->close();

        if ($order_item_count > 0) {
            $message = "This food item cannot be deleted because it is linked to existing orders.";
            $message_type = "error";
        } else {
            // Get the image filename before deleting the food record.
            $find = $conn->prepare(
                "SELECT image FROM food_items WHERE id = ?"
            );
            $find->bind_param("i", $food_id);
            $find->execute();
            $find->bind_result($image_name);
            $found = $find->fetch();
            $find->close();

            if (!$found) {
                $message = "Food item not found.";
                $message_type = "error";
            } else {
                $delete = $conn->prepare(
                    "DELETE FROM food_items WHERE id = ?"
                );
                $delete->bind_param("i", $food_id);

                if ($delete->execute() && $delete->affected_rows === 1) {
                    $message = "Food item deleted successfully!";

                    // Delete the image only after the database deletion succeeds.
                    $safe_image = basename($image_name ?? "");
                    $image_path = __DIR__ . "/../images/" . $safe_image;

                    if ($safe_image !== "" && is_file($image_path)) {
                        unlink($image_path);
                    }
                } else {
                    $message = "Unable to delete this food item.";
                    $message_type = "error";
                }

                $delete->close();
            }
        }
    }
}

// Display messages after adding or editing food.
if (isset($_GET["added"])) {
    $message = "Food item added successfully!";
    $message_type = "success";
} elseif (isset($_GET["updated"])) {
    $message = "Food item updated successfully!";
    $message_type = "success";
}

// Load food items.
$result = $conn->query(
    "SELECT id, name, description, price, image
     FROM food_items
     ORDER BY id DESC"
);

if (!$result) {
    die("Unable to load food items.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Food - Food Express</title>
    <link rel="stylesheet" href="../css/style.css">

    <style>
        .manage-food {
            max-width: 1150px;
            margin: 30px auto;
            padding: 20px;
        }

        .manage-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .food-table-wrap {
            overflow-x: auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,.07);
        }

        .food-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        .food-table th,
        .food-table td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        .food-table th {
            background: #fff3e8;
        }

        .food-thumbnail {
            width: 85px;
            height: 70px;
            object-fit: cover;
            border-radius: 8px;
            display: block;
        }

        .no-image {
            width: 85px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
            border-radius: 8px;
            font-size: 12px;
            color: #666;
        }

        .food-description {
            max-width: 250px;
            overflow-wrap: anywhere;
        }

        .food-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .food-actions a,
        .food-actions button,
        .add-food-link {
            display: inline-block;
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .edit-link,
        .add-food-link {
            background: #f28c28;
            color: white;
        }

        .delete-button {
            background: #c0392b;
            color: white;
        }

        .notice {
            padding: 13px;
            border-radius: 7px;
            margin: 15px 0;
        }

        .notice.success {
            background: #e7f7e9;
            color: #216b2b;
        }

        .notice.error {
            background: #fff0ed;
            color: #a12b1e;
        }

        .empty-food {
            padding: 30px;
            text-align: center;
        }

        @media (max-width: 600px) {
            .manage-food {
                padding: 10px;
            }
        }
    </style>
</head>
<body>

<header>
    <h1>Food Express</h1>
    <p>Manage Your Food Menu</p>
</header>

<nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="add_food.php">Add Food</a>
    <a href="manage_food.php">Manage Food</a>
    <a href="orders.php">Manage Orders</a>
    <a href="../index.php">View Website</a>
    <a href="../logout.php">Logout</a>
</nav>

<main class="manage-food">
    <div class="manage-heading">
        <div>
            <h2>Manage Food Items</h2>
            <p>View, edit, and remove items from your menu.</p>
        </div>

        <a class="add-food-link" href="add_food.php">+ Add New Food</a>
    </div>

    <?php if ($message !== ""): ?>
        <div class="notice <?= htmlspecialchars($message_type) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="food-table-wrap">
        <table class="food-table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Food Name</th>
                    <th>Description</th>
                    <th>Price</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($food = $result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php
                            $image_name = basename($food["image"] ?? "");
                            $image_path = __DIR__ . "/../images/" . $image_name;
                            ?>

                            <?php if (
                                $image_name !== "" &&
                                is_file($image_path)
                            ): ?>
                                <img
                                    class="food-thumbnail"
                                    src="../images/<?= rawurlencode($image_name) ?>"
                                    alt="<?= htmlspecialchars($food["name"]) ?>"
                                >
                            <?php else: ?>
                                <div class="no-image">No image</div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <strong><?= htmlspecialchars($food["name"]) ?></strong>
                        </td>

                        <td class="food-description">
                            <?= htmlspecialchars($food["description"]) ?>
                        </td>

                        <td>
                            ₹<?= number_format((float)$food["price"], 2) ?>
                        </td>

                        <td>
                            <div class="food-actions">
                                <a
                                    class="edit-link"
                                    href="edit_food.php?id=<?= (int)$food["id"] ?>"
                                >Edit</a>

                                <form
                                    method="POST"
                                    onsubmit="return confirm('Are you sure you want to delete this food item?');"
                                >
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >
                                    <input
                                        type="hidden"
                                        name="food_id"
                                        value="<?= (int)$food["id"] ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>"
                                    >
                                    <button
                                        type="submit"
                                        class="delete-button"
                                    >Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="empty-food">
                        <h3>No food items found</h3>
                        <p>Add your first food item to get started.</p>
                        <a class="add-food-link" href="add_food.php">
                            Add Food
                        </a>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<footer>
    <p>&copy; <?= date("Y") ?> Food Express. All rights reserved.</p>
</footer>

</body>
</html>

