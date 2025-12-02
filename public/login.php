<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';

ensure_session();
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (login($email, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>WritersBlocks - Login</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body class="auth">
    <div class="auth-container">
        <h1>WritersBlocks</h1>
        <form method="post">
            <label>Email<br>
                <input type="email" name="email" required value="<?php echo h($_POST['email'] ?? ''); ?>">
            </label>
            <label>Password<br>
                <input type="password" name="password" required>
            </label>
            <?php if ($error): ?>
                <div class="error"><?php echo h($error); ?></div>
            <?php endif; ?>
            <button type="submit">Login</button>
        </form>
    </div>
</body>

</html>