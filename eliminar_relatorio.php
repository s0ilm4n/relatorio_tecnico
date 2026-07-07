<?php
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

$db = getDB();
$id = $_POST['id'] ?? 0;

if ($id > 0 && isset($_POST['confirmar'])) {
    verify_csrf();
    $stmt = $db->prepare("DELETE FROM relatorios WHERE id = ?");
    $stmt->execute([$id]);
}

header('Location: historico.php');
exit;
