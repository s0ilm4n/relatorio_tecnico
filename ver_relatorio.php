<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db = getDB();
$id = $_GET['id'] ?? 0;
$sucesso = $_GET['sucesso'] ?? 0;

$relatorio = $db->prepare("SELECT r.*, c.nome as cliente_nome, c.morada as cliente_morada, c.telefone as cliente_telefone, c.email as cliente_email, c.nif as cliente_nif, u.nome as tecnico_nome FROM relatorios r JOIN clientes c ON r.cliente_id = c.id JOIN users u ON r.user_id = u.id WHERE r.id = ?");
$relatorio->execute([$id]);
$r = $relatorio->fetch();

if (!$r) {
    header('Location: dashboard.php');
    exit;
}

$itens = $db->prepare("SELECT * FROM checklist_itens WHERE relatorio_id = ? ORDER BY id");
$itens->execute([$id]);
$todos_itens = $itens->fetchAll();

$secoes = [];
foreach ($todos_itens as $item) {
    $secoes[$item['secao']][] = $item;
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($sucesso): ?>
<div class="alert alert-success">Relatório guardado com sucesso!</div>
<?php endif; ?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
        <h2 style="border:none;margin:0;padding:0;">Relatório #<?= $r['id'] ?></h2>
        <div style="display:flex;gap:8px;">
            <a href="relatorio_pdf.php?id=<?= $r['id'] ?>" class="btn btn-primary">📄 Descarregar PDF</a>
            <a href="minuta.php?id=<?= $r['id'] ?>" class="btn btn-outline" style="color:#333;border-color:#ccc;">🖨️ Minuta</a>
        </div>
    </div>

    <!-- Cabeçalho -->
    <div style="background:#f8f9fa;border-radius:8px;padding:16px;margin-bottom:20px;">
        <div class="form-row">
            <div><strong>Cliente:</strong> <?= htmlspecialchars($r['cliente_nome']) ?></div>
            <div><strong>Data:</strong> <?= date('d/m/Y', strtotime($r['data'])) ?></div>
        </div>
        <div class="form-row">
            <div><strong>Morada:</strong> <?= htmlspecialchars($r['cliente_morada']) ?></div>
            <div><strong>NIF:</strong> <?= htmlspecialchars($r['cliente_nif']) ?></div>
        </div>
        <div class="form-row">
            <div><strong>Técnico:</strong> <?= htmlspecialchars($r['tecnico_nome']) ?></div>
            <div><strong>Tipo:</strong> <?= $r['tipo'] === 'cctv' ? '📹 CCTV' : ($r['tipo'] === 'acessos' ? '🔐 Controlo Acessos' : ($r['tipo'] === 'instalacao' ? 'Instalação' : 'Manutenção Preventiva')) ?></div>
        </div>
        <div class="form-row">
            <div><strong>Central:</strong> <?= htmlspecialchars($r['central_modelo']) ?></div>
            <div><strong>Grau:</strong> <?= htmlspecialchars($r['grau_sistema']) ?></div>
        </div>
        <?php if ($r['hora_inicio']): ?>
        <div class="form-row">
            <div><strong>Hora Início:</strong> <?= $r['hora_inicio'] ?></div>
            <div><strong>Hora Fim:</strong> <?= $r['hora_fim'] ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Checklist -->
    <?php foreach ($secoes as $secao_nome => $itens_secao): ?>
    <div class="checklist-section">
        <?php
        $titulos = [
            'inspecao_visual' => '1. INSPEÇÃO VISUAL & MECÂNICA',
            'ensaios_electricos' => '2. ENSAIOS ELÉCTRICOS & BATERIAS',
            'testes_funcionais' => '3. TESTES FUNCIONAIS (ZONAS & SABOTAGEM)',
            'dispositivos_aviso' => '4. DISPOSITIVOS DE AVISO & COMUNICAÇÃO (CRA)',
            'encerramento' => '5. ENCERRAMENTO DE OBRA & LEGAL',
            // CCTV sections
            'info_sistema' => '1. INFORMAÇÃO DO SISTEMA',
            'infraestrutura' => '2. INFRAESTRUTURA E CABLAGEM',
            'camaras' => '3. UNIDADES DE CAPTURA (CÂMARAS)',
            'gravacao_rede' => '4. GRAVAÇÃO, PROCESSAMENTO E REDE',
            'rgpd' => '5. CONFORMIDADE LEGAL E RGPD',
            'testes_entrega' => '6. TESTES DE SISTEMA E ENTREGA',
            // Access Control sections
            'leitores' => '3. LEITORES E CREDENCIAIS',
            'fechaduras' => '4. FECHADURAS E MECANISMOS',
            'software_config' => '5. SOFTWARE E CONFIGURAÇÃO',
        ];
        ?>
        <h3><?= $titulos[$secao_nome] ?? $secao_nome ?></h3>
        <?php foreach ($itens_secao as $item): ?>
        <div class="checklist-item" style="<?= $item['verificado'] ? '' : 'opacity:0.6;' ?>">
            <input type="checkbox" disabled <?= $item['verificado'] ? 'checked' : '' ?>>
            <div class="item-content">
                <div class="item-code"><?= htmlspecialchars($item['item_codigo']) ?></div>
                <div class="item-desc"><?= htmlspecialchars($item['item_descricao']) ?></div>
                <?php if ($item['valor_medido']): ?>
                <div style="margin-top:4px;font-size:0.85em;color:#1a1a2e;">
                    <strong>Valor:</strong> <?= htmlspecialchars($item['valor_medido']) ?>
                </div>
                <?php endif; ?>
                <?php if ($item['observacao']): ?>
                <div style="margin-top:4px;font-size:0.85em;color:#555;">
                    <strong>Obs:</strong> <?= htmlspecialchars($item['observacao']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <!-- Material Substituído -->
    <?php if ($r['material_substituido']): ?>
    <div class="card" style="box-shadow:none;border:1px solid #eee;">
        <h3 style="font-size:1em;margin-bottom:8px;">🔧 Material Substituído em Obra</h3>
        <p style="font-size:0.9em;"><?= nl2br(htmlspecialchars($r['material_substituido'])) ?></p>
    </div>
    <?php endif; ?>

    <!-- Notas -->
    <?php if ($r['notas']): ?>
    <div class="card" style="box-shadow:none;border:1px solid #eee;">
        <h3 style="font-size:1em;margin-bottom:8px;">📝 Notas / Observações</h3>
        <p style="font-size:0.9em;"><?= nl2br(htmlspecialchars($r['notas'])) ?></p>
    </div>
    <?php endif; ?>

    <!-- Assinaturas -->
    <div style="margin-top:30px;display:grid;grid-template-columns:1fr 1fr;gap:30px;">
        <div style="border-top:1px solid #333;padding-top:8px;text-align:center;">
            <strong>Técnico:</strong> <?= htmlspecialchars($r['tecnico_nome']) ?>
        </div>
        <div style="border-top:1px solid #333;padding-top:8px;text-align:center;">
            <strong>Cliente:</strong> <?= htmlspecialchars($r['cliente_nome']) ?>
        </div>
    </div>

    <div style="margin-top:20px;text-align:right;font-size:0.8em;color:#888;">
        Documento gerado a <?= date('d/m/Y H:i', strtotime($r['created_at'])) ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
