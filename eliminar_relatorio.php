<?php
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

$db = getDB();
$id = $_GET['id'] ?? 0;

$stmt = $db->prepare("DELETE FROM relatorios WHERE id = ?");
$stmt->execute([$id]);

header('Location: historico.php');
exit;
