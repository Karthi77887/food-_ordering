```php
<?php
session_start();
require_once "includes/db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare(
        "SELECT id, name, password, role
         FROM users
         WHERE email = ?"
    );

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user["password"])) {

        session_regenerate_id(true);

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["name"] = $user["name"];
        $_SESSION["role"] = $user["role"];

        // Redirect based on the user's role
        if ($user["role"] === "admin") {
            header("Location: admin/dashboard.php");
        } else {
            header("Location: index.php");
        }

        exit;

    } else {
        $message = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>User Login - Food Express</title>

    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header>
    <h1>Food Express</h1>
</header>

<h2>Login</h2>

<p style="text-align:center; color:red;">
    <?= htmlspecialchars($message) ?>
</p>

<form method="post" style="max-width:350px; margin:20px auto;">

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
        placeholder="Password"
        required
        style="width:100%; padding:10px; margin-bottom:10px;"
    >

    <button type="submit">Login</button>

</form>

<p style="text-align:center;">
    New user?
    <a href="register.php">Create an account</a>
</p>

</body>
</html>
```