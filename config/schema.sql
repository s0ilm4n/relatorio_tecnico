-- ============================================================
-- Schema da Base de Dados - Relatório Técnico EN 50131
-- ============================================================
-- Uso: mysql -u root -p relatorio_tecnico < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS relatorio_tecnico
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE relatorio_tecnico;

-- Utilizadores
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    nome VARCHAR(100) NOT NULL,
    role ENUM('admin','tecnico') DEFAULT 'tecnico',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Clientes
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    morada TEXT,
    telefone VARCHAR(20),
    email VARCHAR(100),
    nif VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Relatórios
CREATE TABLE IF NOT EXISTS relatorios (
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
) ENGINE=InnoDB;

-- Itens da Checklist
CREATE TABLE IF NOT EXISTS checklist_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    relatorio_id INT NOT NULL,
    secao VARCHAR(100) NOT NULL,
    item_codigo VARCHAR(20) NOT NULL,
    item_descricao TEXT,
    verificado BOOLEAN DEFAULT FALSE,
    valor_medido VARCHAR(50),
    observacao TEXT,
    FOREIGN KEY (relatorio_id) REFERENCES relatorios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Utilizadores padrão (password: admin123 / tecnico123)
INSERT INTO users (username, password_hash, nome, role) VALUES
('admin', '$2y$10$v0BVhNJXLsF.Igkw7O2lqOVkpr0jfbOUajzewfWfy/rOxzKM5XSeS', 'Administrador', 'admin'),
('tecnico', '$2y$10$uhXuo.cUCp7BCm86N9Pi4.YmlKsaG9vuYc/znOJehoKnJYz3BskKO', 'Técnico', 'tecnico');
