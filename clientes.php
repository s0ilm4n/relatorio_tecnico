<?php
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

$db = getDB();
$msg = '';

// Criar cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        verify_csrf();
        $stmt = $db->prepare("INSERT INTO clientes (nome, morada, telefone, email, nif) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['nome'], $_POST['morada'] ?? '', $_POST['telefone'] ?? '', $_POST['email'] ?? '', $_POST['nif'] ?? '']);
        $msg = 'Cliente adicionado.';
    } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        verify_csrf();
        $stmt = $db->prepare("UPDATE clientes SET nome=?, morada=?, telefone=?, email=?, nif=? WHERE id=?");
        $stmt->execute([$_POST['nome'], $_POST['morada'] ?? '', $_POST['telefone'] ?? '', $_POST['email'] ?? '', $_POST['nif'] ?? '', $_POST['id']]);
        $msg = 'Cliente atualizado.';
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        verify_csrf();
        $stmt = $db->prepare("DELETE FROM clientes WHERE id=?");
        $stmt->execute([$_POST['id']]);
        $msg = 'Cliente eliminado.';
    }
}

$clientes = $db->query("SELECT c.*, (SELECT COUNT(*) FROM relatorios WHERE cliente_id = c.id) as total_relatorios FROM clientes c ORDER BY c.nome")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <h2>👤 Gestão de Clientes</h2>

    <?php if ($msg): ?>
        <div class="alert alert-success"><?= $msg ?></div>
    <?php endif; ?>

    <div style="margin-bottom:20px;">
        <button onclick="toggleForm()" class="btn btn-success">+ Novo Cliente</button>
        <a href="dashboard.php" class="btn btn-outline" style="color:#333;border-color:#ccc;">Voltar</a>
    </div>

    <div id="client-form" style="display:none;background:#f8f9fa;padding:20px;border-radius:8px;margin-bottom:20px;">
        <h3 style="margin-bottom:12px;">Novo Cliente</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Nome *</label>
                <input type="text" name="nome" class="form-control" required>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Morada</label><input type="text" name="morada" class="form-control"></div>
                <div class="form-group"><label>NIF</label><input type="text" name="nif" class="form-control"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Telefone</label><input type="text" name="telefone" class="form-control"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control"></div>
            </div>
            <button type="submit" class="btn btn-primary">Adicionar</button>
        </form>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr><th>Nome</th><th>NIF</th><th>Telefone</th><th>Email</th><th>Relatórios</th><th>Ações</th></tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['nome']) ?></td>
                    <td><?= htmlspecialchars($c['nif']) ?></td>
                    <td><?= htmlspecialchars($c['telefone']) ?></td>
                    <td><?= htmlspecialchars($c['email']) ?></td>
                    <td><?= $c['total_relatorios'] ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Eliminar <?= e($c['nome']) ?>?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleForm() {
    var f = document.getElementById('client-form');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
