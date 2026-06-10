<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Xero Invoice App</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 80px auto; text-align: center; }
        a.btn { display: inline-block; padding: 12px 24px; background: #13B5EA; color: #fff;
                text-decoration: none; border-radius: 4px; font-size: 16px; }
        a.btn:hover { background: #0e9bc7; }
    </style>
</head>
<body>
    <h1>Xero Invoice App</h1>

    <?php if (isset($_SESSION['xero_access_token'])): ?>
        <p>Logged in as: <strong><?= htmlspecialchars($_SESSION['xero_user']['name'] ?? 'Unknown') ?></strong></p>
        <p>
            <a class="btn" href="create_invoice.php">Create Invoice</a>
            &nbsp;
            <a class="btn" href="bulk_import.php">Bulk Import (Test)</a>
        </p>
        <p><a href="logout.php">Logout</a></p>
    <?php else: ?>
        <a class="btn" href="login.php">Sign in with Xero</a>
    <?php endif; ?>
</body>
</html>
<?php

