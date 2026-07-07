<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db = getDB();

// Estatísticas
$total = $db->query("SELECT COUNT(*) FROM relatorios")->fetchColumn();
$mes = $db->query("SELECT COUNT(*) FROM relatorios WHERE MONTH(data) = MONTH(CURDATE()) AND YEAR(data) = YEAR(CURDATE())")->fetchColumn();
$clientes = $db->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
$ultimos = $db->query("SELECT r.*, c.nome as cliente_nome, u.nome as tecnico_nome FROM relatorios r JOIN clientes c ON r.cliente_id = c.id JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC LIMIT 10")->fetchAll();

$include_header = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?= $total ?></div>
        <div class="stat-label">Total de Relatórios</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $mes ?></div>
        <div class="stat-label">Este Mês</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $clientes ?></div>
        <div class="stat-label">Clientes Registados</div>
    </div>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
        <h2 style="border:none;margin:0;padding:0;">Últimos Relatórios</h2>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="novo_relatorio.php" class="btn btn-success">+ Alarme (EN 50131)</a>
            <a href="novo_relatorio_cctv.php" class="btn btn-success" style="background:#0f5b8a;">+ CCTV</a>
            <?php if (isAdmin()): ?>
                <a href="clientes.php" class="btn btn-primary">Clientes</a>
                <a href="utilizadores.php" class="btn btn-outline" style="color:#333;border-color:#ccc;">Utilizadores</a>
            <?php endif; ?>
            <a href="historico.php" class="btn btn-outline" style="color:#333;border-color:#ccc;">Histórico</a>
        </div>
    </div>

    <?php if (count($ultimos) === 0): ?>
        <p style="text-align:center;color:#888;padding:30px;">Nenhum relatório ainda. <a href="novo_relatorio.php">Criar o primeiro</a></p>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Data</th>
                        <th>Cliente</th>
                        <th>Técnico</th>
                        <th>Tipo</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ultimos as $r): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td><?= date('d/m/Y', strtotime($r['data'])) ?></td>
                        <td><?= htmlspecialchars($r['cliente_nome']) ?></td>
                        <td><?= htmlspecialchars($r['tecnico_nome']) ?></td>
                        <td><?= $r['tipo'] === 'instalacao' ? 'Instalação' : ($r['tipo'] === 'cctv' ? '📹 CCTV' : 'Manutenção') ?></td>
                        <td class="actions">
                            <a href="ver_relatorio.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-primary">Ver</a>
                            <a href="relatorio_pdf.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline" style="color:#333;border-color:#ccc;">PDF</a>
                            <a href="minuta.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline" style="color:#333;border-color:#ccc;">Minuta</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
