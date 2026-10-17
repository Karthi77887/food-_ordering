```php
<?php
session_start();

// Remove all session data
$_SESSION = array();

// Destroy the current session
session_destroy();

// Redirect to the login page
header("Location: login.php");
exit;
?>
```