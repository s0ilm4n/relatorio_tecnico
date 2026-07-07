<?php
// Migration: separar tipo em tipo_relatorio + tipo_obra
require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();

    // 1. Verificar estado atual
    $stmt = $db->query("SHOW COLUMNS FROM relatorios LIKE 'tipo'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_type = $col['Type'] ?? '';

    // 2. Ver se já foi migrado
    $check = $db->query("SHOW COLUMNS FROM relatorios LIKE 'tipo_obra'");
    if ($check->fetch()) {
        echo "✅ Migração já aplicada. Nada a fazer.\n";
        exit;
    }

    // 3. Adicionar coluna tipo_obra
    $db->exec("ALTER TABLE relatorios ADD COLUMN tipo_obra ENUM('instalacao','manutencao') DEFAULT 'instalacao' AFTER tipo");
    echo "✅ Coluna tipo_obra adicionada.\n";

    // 4. Migrar dados existentes
    //    'instalacao'/'manutencao' → tipo='alarme', tipo_obra conforme
    //    'cctv' → tipo='cctv', tipo_obra='instalacao'
    //    'acessos' → tipo='acessos', tipo_obra='instalacao'
    $db->exec("UPDATE relatorios SET tipo_obra = 'instalacao' WHERE tipo IN ('instalacao','cctv','acessos')");
    $db->exec("UPDATE relatorios SET tipo_obra = 'manutencao' WHERE tipo = 'manutencao'");
    $db->exec("UPDATE relatorios SET tipo = 'alarme' WHERE tipo IN ('instalacao','manutencao')");
    echo "✅ Dados existentes migrados.\n";

    // 5. Alterar ENUM do tipo
    $db->exec("ALTER TABLE relatorios MODIFY tipo ENUM('alarme','cctv','acessos') DEFAULT 'alarme'");
    echo "✅ Coluna tipo alterada para: alarme, cctv, acessos.\n";

    echo "\n🎯 Migração concluída com sucesso!\n";

} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
