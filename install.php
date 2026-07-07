<?php
// Script de instalação automática da BD
// ATENÇÃO: Remover este ficheiro após a instalação!

// Bloquear se já existem utilizadores
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $db = getDB();

    // Verificar se já está instalado
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('users', $tables)) {
        $count = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($count > 0) {
            echo "<h1>Instalação - Relatório Técnico EN 50131</h1>";
            echo "<p style='color:orange;font-size:1.1em;'>⚠️ A base de dados já está instalada ({$count} utilizadores encontrados).</p>";
            echo "<p>Se precisar de reinstalar, apague as tabelas manualmente no phpMyAdmin e aceda novamente.</p>";
            echo "<br><a href='login.php' style='padding:10px 20px;background:#1a1a2e;color:#fff;text-decoration:none;border-radius:6px;'>Ir para o Login</a>";
            exit;
        }
    }

    // Criar tabelas
    $queries = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            nome VARCHAR(100) NOT NULL,
            role ENUM('admin','tecnico') DEFAULT 'tecnico',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS clientes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(200) NOT NULL,
            morada TEXT,
            telefone VARCHAR(20),
            email VARCHAR(100),
            nif VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS relatorios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            cliente_id INT NOT NULL,
            tipo ENUM('alarme','cctv','acessos') DEFAULT 'alarme',
            tipo_obra ENUM('instalacao','manutencao') DEFAULT 'instalacao',
            data DATE NOT NULL,
            hora_inicio TIME,
            hora_fim TIME,
            central_modelo VARCHAR(200),
            grau_sistema VARCHAR(10),
            notas TEXT,
            material_substituido TEXT,
            assinatura_tecnico VARCHAR(255),
            assinatura_cliente VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS checklist_itens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            relatorio_id INT NOT NULL,
            secao VARCHAR(100) NOT NULL,
            item_codigo VARCHAR(20) NOT NULL,
            item_descricao TEXT,
            verificado BOOLEAN DEFAULT FALSE,
            valor_medido VARCHAR(50),
            observacao TEXT,
            FOREIGN KEY (relatorio_id) REFERENCES relatorios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB",
    ];

    foreach ($queries as $sql) {
        $db->exec($sql);
    }

    // Inserir users padrão
    $stmt = $db->prepare("INSERT INTO users (username, password_hash, nome, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['admin', '$2y$10$v0BVhNJXLsF.Igkw7O2lqOVkpr0jfbOUajzewfWfy/rOxzKM5XSeS', 'Administrador', 'admin']);
    $stmt->execute(['tecnico', '$2y$10$uhXuo.cUCp7BCm86N9Pi4.YmlKsaG9vuYc/znOJehoKnJYz3BskKO', 'Técnico', 'tecnico']);

    echo "<h1>✅ Instalação concluída!</h1>";
    echo "<p><strong>⚠️ IMPORTANTE: Elimine este ficheiro (install.php) do servidor por segurança!</strong></p>";
    echo "<br><a href='login.php' style='padding:10px 20px;background:#1a1a2e;color:#fff;text-decoration:none;border-radius:6px;'>Ir para o Login</a>";

} catch (Exception $e) {
    error_log('Install Error: ' . $e->getMessage());
    echo "<h1>❌ Erro na instalação</h1>";
    echo "<p>Não foi possível completar a instalação. Verifique o ficheiro de log do servidor.</p>";
}
