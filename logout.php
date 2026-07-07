<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) {
    logout();
}
header('Location: login.php');
exit;
