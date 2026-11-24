<?php
session_start();
require_once __DIR__ . '/config/database.php';
$db = getDBConnection();

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        try {
            $stmt = $db->prepare("
                SELECT *
                FROM users
                WHERE username = ?
                LIMIT 1
            ");
            $stmt->execute([
                $username
            ]);
            $user = $stmt->fetch();
            if ($user) {
                $passwordValid = false;
                if (!empty($user['password'])) {
                    $passwordValid = password_verify(
                        $password,
                        $user['password']
                    );
                }
                if (
                    !$passwordValid &&
                    isset($user['password']) &&
                    hash_equals(
                        (string)$user['password'],
                        (string)$password
                    )
                ) {
                    $passwordValid = true;
                    $newHash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );
                    $update = $db->prepare("
                        UPDATE users
                        SET password = ?
                        WHERE id = ?
                    ");
                    $update->execute([
                        $newHash,
                        $user['id']
                    ]);
                }
                if ($passwordValid) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = strtolower($user['role'] ?? 'staff');
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Invalid username or password.';
                }
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $error = 'Login database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaCentral - Login</title>
    <style>
        * {
            box-sizing:border-box;
        }
        body {
            margin:0;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            font-family:Arial,Helvetica,sans-serif;
            background:
                linear-gradient(
                    135deg,
                    #172554,
                    #1d4ed8,
                    #0284c7);
                padding:20px;}
        .login-box {width:100%;max-width:450px;background:#ffffff;border-radius:20px;padding:35px;box-shadow: 0 20px 50px rgba(0,0,0,.25);}
        .logo {text-align:center;margin-bottom:25px;}
        .logo-icon {font-size:55px;margin-bottom:5px;}
        .logo h1 {margin:0;font-size:32px;color:#1565c0;}
        .logo p {margin:5px 0 0;color:#64748b;}
        .error {background:#fee2e2;border:1px solid #fecaca;color:#b91c1c;padding:14px;border-radius:9px;margin-bottom:20px;text-align:center;}
        label {display:block;margin-bottom:7px;font-weight:bold;color:#334155;}
        input {width:100%;padding:14px;margin-bottom:18px;border:1px solid #cbd5e1;border-radius:9px;font-size:16px;outline:none;}
        input:focus {border-color:#1976d2;box-shadow: 0 0 0 3px rgba(25,118,210,.12);}
        button {width:100%;border:none;border-radius:9px;padding:14px;font-size:17px;font-weight:bold;background:#1976d2;color:white;cursor:pointer;}
        button:hover {background:#125ca8;}
        .demo {margin-top:25px;padding:17px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;}
        .demo-title {font-weight:bold; margin-bottom:10px;}
        .account {padding:4px 0;color:#475569,}
        .account strong {color:#0f172a;}
    </style>
</head>

<body>
<div class="login-box">
    <div class="logo">
        <div class="logo-icon">💊</div>
        <h1>PharmaCentral</h1>
        <p>Pharmacy Management System</p>
    </div>
    <?php if ($error !== ''): ?>
      <div class="error"> ❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Username</label>
        <input type="text" name="username" placeholder="Enter username" autocomplete="username" required autofocus>
        <label>Password</label>
        <input type="password" name="password" placeholder="Enter password" autocomplete="current-password" required>
        <button type="submit">🔐 Login</button>
    </form>
</div>
</body>
</html>