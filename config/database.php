<?php
// Configuração da Base de Dados
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '13071990');
define('DB_NAME', 'relatorio_tecnico');

// Configurações da app
define('APP_NAME', 'Relatório Técnico - EN 50131');
define('APP_URL', 'http://192.168.1.142/relatorio_tecnico');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            die('Erro de ligação à BD: ' . $e->getMessage());
        }
    }
    return $pdo;
}
