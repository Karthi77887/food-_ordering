```php
<?php
session_start();
require_once "includes/db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (
        $name === "" ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($password) < 8
    ) {
        $message = "Enter valid details. Password must be at least 8 characters.";

    } else {

        // Check whether the email already exists
        $check = $conn->prepare(
            "SELECT id FROM users WHERE email = ?"
        );

        $check->bind_param("s", $email);
        $check->execute();

        $result = $check->get_result();

        if ($result->num_rows > 0) {

            $message = "This email is already registered. Please log in.";

        } else {

            // Encrypt the password
            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // Save the new user
            $stmt = $conn->prepare(
                "INSERT INTO users (name, email, password)
                 VALUES (?, ?, ?)"
            );

            $stmt->bind_param(
                "sss",
                $name,
                $email,
                $hashedPassword
            );

            try {
                $stmt->execute();

                header("Location: login.php");
                exit;

            } catch (mysqli_sql_exception $e) {
                // Handle duplicate email or another database error
                $message = "Registration failed. Please try again or log in.";
            }

            $stmt->close();
        }

        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration - Food Express</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header>
    <h1>Food Express</h1>
</header>

<h2>Create Your Account</h2>

<p style="text-align:center; color:red;">
    <?= htmlspecialchars($message) ?>
</p>

<form method="post" style="max-width:350px; margin:20px auto;">

    <input
        type="text"
        name="name"
        placeholder="Full Name"
        required
        style="width:100%; padding:10px; margin-bottom:10px;"
    >

    <input
        type="email"
        name="email"
        placeholder="Email Address"
        required
        style="width:100%; padding:10px; margin-bottom:10px;"
    >

    <input
        type="password"
        name="password"
        placeholder="Password (minimum 8 characters)"
        minlength="8"
        required
        style="width:100%; padding:10px; margin-bottom:10px;"
    >

    <button type="submit">Register</button>

</form>

<p style="text-align:center;">
    Already have an account?
    <a href="login.php">Login here</a>
</p>

</body>
</html>
```
