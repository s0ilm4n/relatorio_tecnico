<?php
// Configuração da Base de Dados
define('DB_HOST', 'localhost');
define('DB_USER', 'ngenhoca_relatorio');
define('DB_PASS', 'iGXybti0tF45M32t');
define('DB_NAME', 'ngenhoca_relatorio');

define('APP_NAME', 'Relatório Técnico - EN 50131');
define('APP_URL', 'https://drengenhocas.com/relatorio_tecnico');

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
            error_log('DB Error: ' . $e->getMessage());
            die('Erro de ligação à base de dados.');
        }
    }
    return $pdo;
}
