```php
<?php
session_start();
require_once "../includes/db.php";

// Only admin can add food
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $price_input = trim($_POST["price"] ?? "");
    $category = trim($_POST["category"] ?? "Other");

    $allowed_categories = [
        "Pizza",
        "Burger",
        "Fried Rice",
        "French Fries",
        "Drinks",
        "Other"
    ];

    if (
        $name === "" ||
        $description === "" ||
        $price_input === "" ||
        !is_numeric($price_input) ||
        (float)$price_input < 0 ||
        !in_array($category, $allowed_categories, true)
    ) {
        $message = "Please enter valid food details.";
    } elseif (
        !isset($_FILES["image_file"]) ||
        $_FILES["image_file"]["error"] !== UPLOAD_ERR_OK
    ) {
        $message = "Please select an image to upload.";
    } elseif ($_FILES["image_file"]["size"] > 3 * 1024 * 1024) {
        $message = "Image size must be 3 MB or less.";
    } else {
        $upload_dir = __DIR__ . "/../images/";

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $allowed_types = [
            "image/jpeg" => "jpg",
            "image/png"  => "png",
            "image/webp" => "webp",
            "image/gif"  => "gif"
        ];

        $tmp_file = $_FILES["image_file"]["tmp_name"];
        $image_info = @getimagesize($tmp_file);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($tmp_file);

        if (
            $image_info === false ||
            !isset($allowed_types[$mime_type])
        ) {
            $message = "Choose a valid JPG, PNG, WebP, or GIF image.";
        } else {
            $image_name = bin2hex(random_bytes(16))
                . "." . $allowed_types[$mime_type];

            $destination = $upload_dir . $image_name;

            if (!move_uploaded_file($tmp_file, $destination)) {
                $message = "Unable to save the uploaded image.";
            } else {
                $price = (float)$price_input;

                $stmt = $conn->prepare(
                    "INSERT INTO food_items
                    (name, description, price, image, category)
                    VALUES (?, ?, ?, ?, ?)"
                );

                if (!$stmt) {
                    $message = "Unable to prepare the database query.";
                    if (is_file($destination)) {
                        unlink($destination);
                    }
                } else {
                    $stmt->bind_param(
                        "ssdss",
                        $name,
                        $description,
                        $price,
                        $image_name,
                        $category
                    );

                    if ($stmt->execute()) {
                        $stmt->close();
                        header("Location: manage_food.php?added=1");
                        exit;
                    } else {
                        $message = "Unable to add food to the database.";
                        $stmt->close();

                        if (is_file($destination)) {
                            unlink($destination);
                        }
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Food - Food Express</title>

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
    <p>Add a New Food Item</p>
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
    <h2>Add Food Item</h2>

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
            value="<?= htmlspecialchars($_POST["name"] ?? "", ENT_QUOTES, "UTF-8") ?>"
        >

        <label for="description">Description</label>
        <textarea
            id="description"
            name="description"
            rows="4"
            required
        ><?= htmlspecialchars($_POST["description"] ?? "", ENT_QUOTES, "UTF-8") ?></textarea>

        <label for="price">Price (₹)</label>
        <input
            type="number"
            id="price"
            name="price"
            min="0"
            step="0.01"
            required
            value="<?= htmlspecialchars($_POST["price"] ?? "", ENT_QUOTES, "UTF-8") ?>"
        >

        <label for="category">Food Category</label>
        <select id="category" name="category" required>
            <?php
            $selected_category = $_POST["category"] ?? "Other";

            $categories = [
                "Pizza",
                "Burger",
                "Fried Rice",
                "French Fries",
                "Drinks",
                "Other"
            ];

            foreach ($categories as $cat):
            ?>
                <option
                    value="<?= htmlspecialchars($cat, ENT_QUOTES, "UTF-8") ?>"
                    <?= $selected_category === $cat ? "selected" : "" ?>
                >
                    <?= htmlspecialchars($cat, ENT_QUOTES, "UTF-8") ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="image_file">Food Image</label>
        <input
            type="file"
            id="image_file"
            name="image_file"
            accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif"
            required
        >

        <small class="upload-help">
            Allowed: JPG, PNG, WebP, GIF. Maximum size: 3 MB.
        </small>

        <button type="submit">Upload Image &amp; Add Food</button>

    </form>

    <p><a href="manage_food.php">Back to Manage Food</a></p>
</main>

<footer>
    <p>&copy; <?= date("Y") ?> Food Express. All rights reserved.</p>
</footer>

</body>
</html>
```
