<?php
// filepath: c:\xampp\htdocs\addwise\verify.php
$host = "localhost";
$user = "root";
$password = "";
$dbname = "addwise";
$conn = new mysqli($host, $user, $password, $dbname);

$verified = false;
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $stmt = $conn->prepare("SELECT id FROM users WHERE verification_code = ? AND is_verified = 0");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $update = $conn->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE verification_code = ?");
        $update->bind_param("s", $code);
        $update->execute();
        $verified = true;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Email Verification</title>
</head>
<body>
    <?php if ($verified): ?>
        <h2>Your email has been verified! You can now <a href="login.php">sign in</a>.</h2>
    <?php else: ?>
        <h2>Invalid or expired verification link.</h2>
    <?php endif; ?>
</body>
</html>