<?php
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

$db = getDB();
$msg = '';

// Criar/editar utilizador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        verify_csrf();
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password_hash, nome, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['username'], $hash, $_POST['nome'], $_POST['role']]);
        $msg = 'Utilizador criado.';
    } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        verify_csrf();
        if (!empty($_POST['password'])) {
            $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET username=?, nome=?, role=?, password_hash=? WHERE id=?");
            $stmt->execute([$_POST['username'], $_POST['nome'], $_POST['role'], $hash, $_POST['id']]);
        } else {
            $stmt = $db->prepare("UPDATE users SET username=?, nome=?, role=? WHERE id=?");
            $stmt->execute([$_POST['username'], $_POST['nome'], $_POST['role'], $_POST['id']]);
        }
        $msg = 'Utilizador atualizado.';
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        verify_csrf();
        $stmt = $db->prepare("DELETE FROM users WHERE id=? AND role != 'admin'");
        $stmt->execute([$_POST['id']]);
        $msg = 'Utilizador eliminado.';
    }
}

$users = $db->query("SELECT id, username, nome, role, created_at FROM users ORDER BY role, nome")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <h2>👥 Gestão de Utilizadores</h2>

    <?php if ($msg): ?>
        <div class="alert alert-success"><?= $msg ?></div>
    <?php endif; ?>

    <div style="margin-bottom:20px;">
        <button onclick="toggleForm()" class="btn btn-success">+ Novo Utilizador</button>
        <a href="dashboard.php" class="btn btn-outline" style="color:#333;border-color:#ccc;">Voltar</a>
    </div>

    <div id="user-form" style="display:none;background:#f8f9fa;padding:20px;border-radius:8px;margin-bottom:20px;">
        <h3 style="margin-bottom:12px;">Novo Utilizador</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Nome *</label>
                    <input type="text" name="nome" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Função</label>
                    <select name="role" class="form-control">
                        <option value="tecnico">Técnico</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Criar</button>
        </form>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr><th>Username</th><th>Nome</th><th>Função</th><th>Criado em</th><th>Ações</th></tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['nome']) ?></td>
                    <td><?= $u['role'] ?></td>
                    <td><?= $u['created_at'] ?></td>
                    <td>
                            <?php if (isAdmin()): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Eliminar <?= htmlspecialchars($u['nome']) ?>?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                            </form>
                            <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleForm() {
    var f = document.getElementById('user-form');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
