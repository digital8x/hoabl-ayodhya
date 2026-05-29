<?php
session_start();
require_once __DIR__ . '/../config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
        $_SESSION['hoabl_ayodhya_logged_in'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | HOABL Ayodhya</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --saffron: #f26e22;
            --saffron-light: #ff8c3a;
        }
        body { margin: 0; font-family: 'Inter', sans-serif; background: #03070f; color: #e8eaf0; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); padding: 3rem; border-radius: 24px; width: 100%; max-width: 400px; text-align: center; backdrop-filter: blur(20px); box-shadow: 0 40px 100px rgba(0,0,0,0.6); }
        .logo { height: 48px; margin-bottom: 2rem; object-fit: contain; }
        h1 { font-size: 1.5rem; margin-bottom: 0.5rem; font-weight: 800; }
        p { color: #8898b5; font-size: 0.9rem; margin-bottom: 2rem; }
        .form-group { text-align: left; margin-bottom: 1.5rem; }
        label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--saffron-light); text-transform: uppercase; letter-spacing: 0.05em; }
        input { width: 100%; box-sizing: border-box; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); padding: 1rem; border-radius: 12px; color: #fff; font-size: 1rem; outline: none; transition: 0.3s; }
        input:focus { border-color: var(--saffron); background: rgba(242, 110, 34, 0.05); }
        .btn-login { width: 100%; background: linear-gradient(135deg, var(--saffron), var(--saffron-light)); color: #03070f; border: none; padding: 1.1rem; border-radius: 50px; font-weight: 800; font-size: 1rem; cursor: pointer; transition: 0.3s; box-shadow: 0 10px 30px rgba(242, 110, 34, 0.3); }
        .btn-login:hover { transform: translateY(-3px); box-shadow: 0 15px 40px rgba(242, 110, 34, 0.5); }
        .error { color: #fc8181; background: rgba(229,62,62,0.1); padding: 0.8rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="login-card">
        <img src="../../images/logo.png" alt="HOABL Ayodhya" class="logo">
        <h1>Backend Control Access</h1>
        <p>Authorize credentials to access leads directory.</p>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required autocomplete="username">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="••••••••" autocomplete="current-password">
            </div>
            <button type="submit" class="btn-login">AUTHORIZE ACCESS</button>
        </form>
    </div>
</body>
</html>
