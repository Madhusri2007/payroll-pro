 <?php
session_start();
if (!extension_loaded('mongodb')) {
    die('MongoDB extension is not loaded!');
}

require_once("config/db.php");

echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payroll Setup</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width:700px;">
<div class="card shadow-sm">
<div class="card-header bg-primary text-white fw-bold">Payroll System Setup</div>
<div class="card-body">';

function ok($msg)  { echo '<div class="alert alert-success py-2 mb-2">&#x2705; ' . htmlspecialchars($msg) . '</div>'; }
function err($msg) { echo '<div class="alert alert-danger  py-2 mb-2">&#x274C; ' . htmlspecialchars($msg) . '</div>'; }
function inf($msg) { echo '<div class="alert alert-info   py-2 mb-2">&#x2139;&#xFE0F; ' . htmlspecialchars($msg) . '</div>'; }

// Default users with correct roles
$defaultUsers = [
    ['username' => 'admin',      'password' => 'admin123',   'role' => 'admin'],
    ['username' => 'hr_user',    'password' => 'hr123',      'role' => 'hr'],
    ['username' => 'accountant', 'password' => 'account123', 'role' => 'accountant'],
    ['username' => 'staff',      'password' => 'staff123',   'role' => 'staff'],
];

echo '<h6 class="fw-semibold mb-3">Default Users — Creating / Updating roles</h6>';

foreach ($defaultUsers as $u) {
    $exists = findOne($manager, $dbName, $usersCollection, ['username' => $u['username']]);
    if ($exists) {
        // Force-update the role to ensure it is correct
        updateDoc($manager, $dbName, $usersCollection,
            ['username' => $u['username']],
            ['role' => $u['role'], 'password' => password_hash($u['password'], PASSWORD_DEFAULT)]
        );
        ok("Updated user: {$u['username']} — role set to '{$u['role']}'");
    } else {
        insertDoc($manager, $dbName, $usersCollection, [
            'username'   => $u['username'],
            'password'   => password_hash($u['password'], PASSWORD_DEFAULT),
            'role'       => $u['role'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
        ok("Created user: {$u['username']} (role: {$u['role']})");
    }
}

echo '<hr>';
echo '<div class="alert alert-success fw-semibold">
    Setup complete! <a href="login.php" class="alert-link">Go to Login &rarr;</a>
</div>';

echo '</div></div>';

echo '
<div class="card shadow-sm mt-4">
<div class="card-header fw-semibold">Login Credentials</div>
<div class="card-body p-0">
<table class="table table-sm mb-0">
<thead class="table-light"><tr><th>Username</th><th>Password</th><th>Role</th></tr></thead>
<tbody>';
foreach ($defaultUsers as $u) {
    echo "<tr><td><strong>{$u['username']}</strong></td><td>{$u['password']}</td><td><span class='badge bg-secondary'>{$u['role']}</span></td></tr>";
}
echo '</tbody></table></div></div>';

echo '<p class="text-muted small mt-3 text-center">
    After running setup, go to Login and sign in again with fresh credentials.
</p>
</div></body></html>';