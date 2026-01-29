<?php
session_start();
if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}
$error = '';
// path to the users file (same as register.php)
$userFile = __DIR__ . '/users.txt';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        $found = false;
        if (file_exists($userFile)) {
            $lines = file($userFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // each line stored as username:password
                $parts = explode(':', $line, 2);
                if (count($parts) < 2) continue;
                $u = trim($parts[0]);
                $p = isset($parts[1]) ? trim($parts[1]) : '';
                // if password stored as hash, use password_verify; otherwise allow legacy plaintext
                if ($u === $username) {
                    if ($p !== '' && (password_verify($password, $p) || $p === $password)) {
                        $found = true;
                        break;
                    }
                }
            }
        }
        if ($found) {
            $_SESSION['user'] = $username;
            setcookie('user', $username, time() + 3600, '/'); // 1 hour cookie
            header('Location: index.php');
            exit();
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Login - bhooklagihai.com 😋</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(120deg, #f6d365cc 0%, #fda085cc 100%);
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px #0002;
            padding: 32px 28px;
            max-width: 340px;
            width: 100%;
            text-align: center;
        }
        h2 {
            color: #f76b1c;
            margin-bottom: 18px;
        }
        input[type="text"], input[type="password"] {
            width: 90%;
            padding: 10px;
            margin: 10px 0;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 1rem;
        }
        button {
            background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%);
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 10px 22px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
            box-shadow: 0 2px 8px #38f9d755;
            transition: background 0.2s, transform 0.2s;
        }
        button:hover {
            background: linear-gradient(90deg, #38f9d7 0%, #43e97b 100%);
            transform: translateY(-2px) scale(1.04);
        }
        .error {
            color: #d32f2f;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login to Raw To Recipe</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <input type="text" name="username" placeholder="Username" required autofocus><br>
            <input type="password" name="password" placeholder="Password" required><br>
            <button type="submit">Login</button>
        </form>
        <a href="register.php" style="margin-top:18px;display:block;color:#f76b1c;text-decoration:underline;">New user? Register here</a>
    </div>
</body>
</html>
