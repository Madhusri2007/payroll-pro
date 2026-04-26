 <?php
session_start();
require_once("config/db.php");

// Already logged in
if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $query   = new MongoDB\Driver\Query(['username' => $username], ['limit' => 1]);
    $cursor  = $manager->executeQuery("$dbName.$usersCollection", $query);
    $results = $cursor->toArray();

    if (count($results) > 0) {
        $dbUser = $results[0];
        $storedPassword = $dbUser->password ?? '';
        $valid = password_verify($password, $storedPassword) || $password === $storedPassword;

        if ($valid) {
            // Destroy any old session first
            session_unset();
            session_destroy();
            session_start();

            $userRole = (isset($dbUser->role) && $dbUser->role !== '')
                        ? (string)$dbUser->role
                        : 'staff';

            $_SESSION['user'] = [
                'username' => (string)$dbUser->username,
                'role'     => $userRole,
            ];

            header("Location: dashboard.php");
            exit();
        }
    }
    $error = "Invalid username or password!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayRoll Pro - Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
        }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo h1 { font-size: 28px; color: #1e3c72; }
        .logo p { color: #666; font-size: 14px; margin-top: 5px; }
        .form-group { margin-bottom: 20px; }
        label {
            display: block;
            margin-bottom: 6px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.3s;
            outline: none;
        }
        input:focus { border-color: #2a5298; }
        .btn-login {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: opacity 0.2s;
        }
        .btn-login:hover { opacity: 0.9; }
        .error {
            background: #ffe0e0;
            color: #c0392b;
            padding: 10px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #c0392b;
        }
        .footer-note {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: #aaa;
        }
        .footer-note span {
            display: block;
            margin-top: 4px;
            color: #bbb;
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="logo">
        <h1>&#x1F4BC; PayRoll Pro</h1>
        <p>Employee Payroll Management System</p>
    </div>

    <?php if ($error): ?>
        <div class="error">&#x26A0; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" placeholder="Enter your username" required
                   autocomplete="username"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="Enter your password"
                   autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn-login">Sign In &rarr;</button>
    </form>

    <div class="footer-note">
        &#x1F512; Authorized personnel only.
        <span>Contact your administrator if you cannot log in.</span>
    </div>
</div>
</body>
</html>