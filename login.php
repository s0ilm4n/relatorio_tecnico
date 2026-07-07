<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (login($username, $password)) {
        header('Location: dashboard.php');
        exit;
    }
    $erro = 'Credenciais inválidas.';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-card">
        <h1>📋 Relatório Técnico</h1>
        <p class="subtitle">EN 50131 - Alarmes & CCTV</p>

        <?php if ($erro): ?>
            <div class="alert alert-danger"><?= $erro ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Utilizador</label>
                <input type="text" id="username" name="username" class="form-control" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Palavra-passe</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Entrar</button>
        </form>
        <p style="text-align:center;margin-top:16px;font-size:0.85em;color:#888;">Norma EN 50131 - Grau 1 a 4</p>
    </div>
</body>
</html>
