<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-container">
    <?php if (isLoggedIn()): ?>
    <nav class="navbar">
        <div class="nav-brand">
            <a href="dashboard.php">📋 <?= APP_NAME ?></a>
        </div>
        <div class="nav-user">
            <span><?= getUserName() ?> (<?= $_SESSION['user_role'] ?>)</span>
            <a href="logout.php" class="btn btn-sm btn-outline">Sair</a>
        </div>
    </nav>
    <?php endif; ?>
    <main class="container">
