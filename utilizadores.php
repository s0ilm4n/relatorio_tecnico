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
        <button onclick="toggleForm('novo')" class="btn btn-success">+ Novo Utilizador</button>
        <a href="dashboard.php" class="btn btn-outline" style="color:#333;border-color:#ccc;">Voltar</a>
    </div>

    <!-- Modal Novo Utilizador -->
    <div id="modal-novo" class="modal-overlay" style="display:none;">
        <div class="modal-box">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <h3 style="margin:0;">+ Novo Utilizador</h3>
                <button onclick="fecharModal('modal-novo')" style="background:none;border:none;font-size:1.5em;cursor:pointer;color:#888;">&times;</button>
            </div>
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
                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
                    <button type="button" onclick="fecharModal('modal-novo')" class="btn btn-outline" style="color:#333;border-color:#ccc;">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Utilizador -->
    <div id="modal-editar" class="modal-overlay" style="display:none;">
        <div class="modal-box">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <h3 style="margin:0;">✏️ Editar Utilizador</h3>
                <button onclick="fecharModal('modal-editar')" style="background:none;border:none;font-size:1.5em;cursor:pointer;color:#888;">&times;</button>
            </div>
            <form method="POST" id="form-editar">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit-id">
                <?= csrf_field() ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" id="edit-username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Nome *</label>
                        <input type="text" name="nome" id="edit-nome" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nova Password <span style="color:#888;font-weight:normal;">(deixar vazio para manter)</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Nova password...">
                    </div>
                    <div class="form-group">
                        <label>Função</label>
                        <select name="role" id="edit-role" class="form-control">
                            <option value="tecnico">Técnico</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
                    <button type="button" onclick="fecharModal('modal-editar')" class="btn btn-outline" style="color:#333;border-color:#ccc;">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Alterações</button>
                </div>
            </form>
        </div>
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
                    <td><span class="badge-role <?= $u['role'] ?>"><?= $u['role'] ?></span></td>
                    <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <button onclick="editarUtilizador(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['nome'], ENT_QUOTES) ?>', '<?= $u['role'] ?>')" class="btn btn-sm btn-primary">Editar</button>
                        <?php if ($u['role'] !== 'admin' || $u['id'] !== $_SESSION['user_id']): ?>
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

<style>
.modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.modal-box {
    background: #fff;
    border-radius: 12px;
    padding: 28px;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    max-height: 90vh;
    overflow-y: auto;
}
.badge-role {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: 600;
}
.badge-role.admin {
    background: #1a1a2e;
    color: #fff;
}
.badge-role.tecnico {
    background: #e8eaf6;
    color: #1a1a2e;
}
</style>

<script>
function toggleForm(tipo) {
    if (tipo === 'novo') {
        document.getElementById('modal-novo').style.display = 'flex';
    }
}

function fecharModal(id) {
    document.getElementById(id).style.display = 'none';
}

function editarUtilizador(id, username, nome, role) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-username').value = username;
    document.getElementById('edit-nome').value = nome;
    document.getElementById('edit-role').value = role;
    document.getElementById('modal-editar').style.display = 'flex';
}

// Fechar modal ao clicar fora
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
    }
});

// Fechar com Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay').forEach(function(m) {
            m.style.display = 'none';
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
