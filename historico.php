<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db = getDB();

$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];
if ($search) {
    $where = "WHERE c.nome LIKE ? OR r.tipo LIKE ? OR u.nome LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$count = $db->prepare("SELECT COUNT(*) FROM relatorios r JOIN clientes c ON r.cliente_id = c.id JOIN users u ON r.user_id = u.id $where");
$count->execute($params);
$total = $count->fetchColumn();
$totalPages = ceil($total / $perPage);

$sql = "SELECT r.*, c.nome as cliente_nome, u.nome as tecnico_nome FROM relatorios r JOIN clientes c ON r.cliente_id = c.id JOIN users u ON r.user_id = u.id $where ORDER BY r.created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$relatorios = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
        <h2 style="border:none;margin:0;padding:0;">Histórico de Relatórios</h2>
        <a href="novo_relatorio.php" class="btn btn-success">+ Novo Relatório</a>
    </div>

    <form method="GET" style="margin-bottom:16px;">
        <div style="display:flex;gap:8px;">
            <input type="text" name="search" class="form-control" placeholder="Pesquisar por cliente, técnico ou tipo..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-primary">Pesquisar</button>
            <?php if ($search): ?>
                <a href="historico.php" class="btn btn-outline" style="color:#333;border-color:#ccc;">Limpar</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (count($relatorios) === 0): ?>
        <p style="text-align:center;color:#888;padding:30px;">Nenhum relatório encontrado.</p>
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
                    <?php foreach ($relatorios as $r): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td><?= date('d/m/Y', strtotime($r['data'])) ?></td>
                        <td><?= htmlspecialchars($r['cliente_nome']) ?></td>
                        <td><?= htmlspecialchars($r['tecnico_nome']) ?></td>
                        <td><?= $r['tipo'] === 'instalacao' ? 'Instalação' : 'Manutenção' ?></td>
                        <td class="actions">
                            <a href="ver_relatorio.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-primary">Ver</a>
                            <a href="relatorio_pdf.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline" style="color:#333;border-color:#ccc;">PDF</a>
                            <a href="minuta.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline" style="color:#333;border-color:#ccc;">Minuta</a>
                            <?php if (isAdmin()): ?>
                            <a href="eliminar_relatorio.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirmDelete('Eliminar relatório #<?= $r['id'] ?>?')">Eliminar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div style="margin-top:16px;display:flex;justify-content:center;gap:8px;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>" style="<?= $i === $page ? '' : 'color:#333;border-color:#ccc;' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
