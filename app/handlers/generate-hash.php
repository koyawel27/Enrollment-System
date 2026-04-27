<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Password Hash Generator
 * generate-hash.php
 * 
 * Use this to generate password hashes for resetting admin passwords
 */

// The password you want to set
$password = 'Admin@BPC2024';

// Generate hash
$hash = password_hash($password, PASSWORD_DEFAULT);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Password Hash Generator</title>
    <style>
        body { font-family: monospace; padding: 2rem; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; }
        h1 { color: #006400; }
        .hash-box { background: #f0f0f0; padding: 1rem; border-radius: 6px; word-break: break-all; margin: 1rem 0; }
        .sql-box { background: #1a1a1a; color: #0f0; padding: 1rem; border-radius: 6px; margin: 1rem 0; overflow-x: auto; }
        pre { margin: 0; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔑 Password Hash Generator</h1>
        
        <p><strong>Password:</strong> <code><?php
require_once dirname(__DIR__, 2) . '/config/paths.php';

echo htmlspecialchars($password); ?></code></p>
        
        <p><strong>Hash:</strong></p>
        <div class="hash-box">
            <?php
echo $hash; ?>
        </div>
        
        <p><strong>SQL to run in phpMyAdmin:</strong></p>
        <div class="sql-box">
            <pre>UPDATE admins 
SET password = '<?php
echo $hash; ?>' 
WHERE email = 'admin@bpc.edu.ph';</pre>
        </div>
        
        <hr style="margin: 2rem 0;">
        
        <h2>Or check current admin credentials:</h2>
        <div class="sql-box">
            <pre>SELECT id, name, email, created_at 
FROM admins;</pre>
        </div>
        
        <p><small>After running the UPDATE query, you can login with email: <code>admin@bpc.edu.ph</code> and password: <code><?php
echo htmlspecialchars($password); ?></code></small></p>
    </div>
</body>
</html>