```php
<?php
session_start();
require_once "../includes/db.php";

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header("Location: manage_food.php");
    exit;
}

$stmt = $conn->prepare(
    "SELECT id, name, description, price, image, category
     FROM food_items WHERE id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$food = $result->fetch_assoc();
$stmt->close();

if (!$food) {
    header("Location: manage_food.php");
    exit;
}

$categories = [
    "Pizza",
    "Burger",
    "Fried Rice",
    "French Fries",
    "Drinks",
    "Other"
];

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $price_input = trim($_POST["price"] ?? "");
    $category = trim($_POST["category"] ?? "");

    if (
        $name === "" ||
        $description === "" ||
        $price_input === "" ||
        !is_numeric($price_input) ||
        (float)$price_input < 0 ||
        !in_array($category, $categories, true)
    ) {
        $message = "Please enter valid food details, price, and category.";
    } else {
        $image_name = $food["image"];
        $new_image_path = null;
        $upload_error = "";

        $has_new_upload =
            isset($_FILES["image_file"]) &&
            $_FILES["image_file"]["error"] !== UPLOAD_ERR_NO_FILE;

        if ($has_new_upload) {
            if ($_FILES["image_file"]["error"] !== UPLOAD_ERR_OK) {
                $upload_error = "The image upload failed. Please try again.";
            } elseif ($_FILES["image_file"]["size"] > 3 * 1024 * 1024) {
                $upload_error = "Image size must be 3 MB or less.";
            } else {
                $tmp_file = $_FILES["image_file"]["tmp_name"];
                $image_info = @getimagesize($tmp_file);
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime_type = $finfo->file($tmp_file);

                $allowed_types = [
                    "image/jpeg" => "jpg",
                    "image/png"  => "png",
                    "image/webp" => "webp",
                    "image/gif"  => "gif"
                ];

                if (
                    $image_info === false ||
                    !isset($allowed_types[$mime_type])
                ) {
                    $upload_error =
                        "Choose a valid JPG, PNG, WebP, or GIF image.";
                } else {
                    $upload_dir = __DIR__ . "/../images/";

                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    try {
                        $image_name = bin2hex(random_bytes(16))
                            . "." . $allowed_types[$mime_type];

                        $new_image_path = $upload_dir . $image_name;

                        if (!move_uploaded_file($tmp_file, $new_image_path)) {
                            $upload_error = "Unable to save the new image.";
                            $new_image_path = null;
                        }
                    } catch (Throwable $e) {
                        $upload_error = "An error occurred while uploading.";
                        $new_image_path = null;
                    }
                }
            }
        }

        if ($upload_error !== "") {
            $message = $upload_error;
        } else {
            $price = (float)$price_input;

            $update = $conn->prepare(
                "UPDATE food_items
                 SET name = ?, description = ?, price = ?,
                     image = ?, category = ?
                 WHERE id = ?"
            );

            $update->bind_param(
                "ssdssi",
                $name,
                $description,
                $price,
                $image_name,
                $category,
                $id
            );

            if ($update->execute()) {
                $update->close();
                header("Location: manage_food.php?updated=1");
                exit;
            } else {
                $message = "Unable to update food details.";
                $update->close();

                if ($new_image_path && is_file($new_image_path)) {
                    unlink($new_image_path);
                }
            }
        }
    }
}

$selected_category = $_POST["category"] ?? $food["category"];

if (!in_array($selected_category, $categories, true)) {
    $selected_category = "Other";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Food - Food Express</title>
    <link rel="stylesheet" href="../css/style.css">

    <style>
        .food-form {
            max-width: 650px;
            margin: 30px auto;
            padding: 25px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,.08);
        }

        .food-form label {
            display: block;
            margin: 15px 0 7px;
            font-weight: 600;
        }

        .food-form input,
        .food-form textarea,
        .food-form select {
            width: 100%;
            box-sizing: border-box;
            padding: 11px;
        }

        .food-form button {
            margin-top: 20px;
        }

        .current-image {
            display: block;
            width: 180px;
            max-width: 100%;
            height: 130px;
            object-fit: cover;
            border-radius: 8px;
            margin: 10px 0;
        }

        .form-message {
            padding: 12px;
            border-radius: 6px;
            margin: 15px 0;
            background: #fff0ed;
            color: #a12b1e;
        }

        .upload-help {
            display: block;
            margin-top: 6px;
            color: #666;
            font-size: 13px;
        }
    </style>
</head>
<body>

<header>
    <h1>Food Express</h1>
    <p>Edit Food Item</p>
</header>

<nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="add_food.php">Add Food</a>
    <a href="manage_food.php">Manage Food</a>
    <a href="orders.php">Manage Orders</a>
    <a href="../index.php">View Website</a>
    <a href="../logout.php">Logout</a>
</nav>

<main class="food-form">
    <h2>Edit Food</h2>

    <?php if ($message !== ""): ?>
        <div class="form-message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <label for="name">Food Name</label>
        <input
            type="text"
            id="name"
            name="name"
            maxlength="150"
            required
            value="<?= htmlspecialchars($_POST["name"] ?? $food["name"]) ?>"
        >

        <label for="description">Description</label>
        <textarea
            id="description"
            name="description"
            rows="4"
            required
        ><?= htmlspecialchars($_POST["description"] ?? $food["description"]) ?></textarea>

        <label for="price">Price (₹)</label>
        <input
            type="number"
            id="price"
            name="price"
            min="0"
            step="0.01"
            required
            value="<?= htmlspecialchars($_POST["price"] ?? $food["price"]) ?>"
        >

        <label for="category">Food Category</label>
        <select id="category" name="category" required>
            <?php foreach ($categories as $item): ?>
                <option
                    value="<?= htmlspecialchars($item) ?>"
                    <?= $selected_category === $item ? "selected" : "" ?>
                >
                    <?= htmlspecialchars($item) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Current Image</label>

        <?php
        $current_image = basename($food["image"] ?? "");
        $current_image_path = __DIR__ . "/../images/" . $current_image;
        ?>

        <?php if ($current_image !== "" && is_file($current_image_path)): ?>
            <img
                class="current-image"
                src="../images/<?= rawurlencode($current_image) ?>"
                alt="<?= htmlspecialchars($food["name"]) ?>"
            >
        <?php else: ?>
            <p>No current image found.</p>
        <?php endif; ?>

        <label for="image_file">Upload a Replacement Image (Optional)</label>
        <input
            type="file"
            id="image_file"
            name="image_file"
            accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif"
        >

        <small class="upload-help">
            Leave empty to keep the current image. Maximum size: 3 MB.
            JPG, PNG, WebP, and GIF are supported.
        </small>

        <button type="submit">Save Changes</button>
    </form>

    <p><a href="manage_food.php">Back to Manage Food</a></p>
</main>

<footer>
    <p>&copy; <?= date("Y") ?> Food Express. All rights reserved.</p>
</footer>

</body>
</html>
```
