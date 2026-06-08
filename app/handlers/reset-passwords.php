<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Reset All Test Account Passwords
 * reset-passwords.php
 * 
 * Run this once to set all accounts to Password123
 */

// Database connection
$conn = mysqli_connect('localhost', 'root', '', 'bpc_ienroll', 3306);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// The password you want for all test accounts
$new_password = 'Password123';
$hash = password_hash($new_password, PASSWORD_DEFAULT);

echo "<h2>Password Reset Tool</h2>";
echo "<p>New Password: <strong>{$new_password}</strong></p>";
echo "<p>Generated Hash: <code>{$hash}</code></p>";
echo "<hr>";

// Update all users
$update_sql = "UPDATE users SET password = ?";
$stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($stmt, 's', $hash);
mysqli_stmt_execute($stmt);
$affected = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

echo "<h3>Updated {$affected} accounts</h3>";

// Show all users
$users_sql = "SELECT id, email FROM users ORDER BY id";
$result = mysqli_query($conn, $users_sql);

echo "<h3>All User Accounts:</h3>";
echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
echo "<tr><th>ID</th><th>Email</th><th>Password</th></tr>";

while ($user = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>{$user['id']}</td>";
    echo "<td>{$user['email']}</td>";
    echo "<td><strong>{$new_password}</strong></td>";
    echo "</tr>";
}

echo "</table>";

mysqli_close($conn);

echo "<hr>";
echo "<p><strong>Now try logging in with any email above using password: {$new_password}</strong></p>";
echo "<p><a href='../auth/login.php'>Go to Login Page</a></p>";
?>