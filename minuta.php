<?php
// Minuta para papel — versão imprimível para preenchimento manual (offline)
require_once __DIR__ . '/config/database.php';

$id = $_GET['id'] ?? 0;
$dados_preenchidos = false;

if ($id > 0) {
    $db = getDB();
    $stmt = $db->prepare("SELECT r.*, c.nome as cliente_nome, c.morada as cliente_morada, c.telefone as cliente_telefone, c.nif as cliente_nif, u.nome as tecnico_nome FROM relatorios r JOIN clientes c ON r.cliente_id = c.id JOIN users u ON r.user_id = u.id WHERE r.id = ?");
    $stmt->execute([$id]);
    $r = $stmt->fetch();

    if ($r) {
        $dados_preenchidos = true;
        $itens = $db->prepare("SELECT * FROM checklist_itens WHERE relatorio_id = ? ORDER BY id");
        $itens->execute([$id]);
        $todos_itens = $itens->fetchAll();

        $secoes = [];
        foreach ($todos_itens as $item) {
            $secoes[$item['secao']][] = $item;
        }
    }
}

$titulos_secoes = [
    'inspecao_visual' => '1. INSPEÇÃO VISUAL & MECÂNICA',
    'ensaios_electricos' => '2. ENSAIOS ELÉCTRICOS & BATERIAS',
    'testes_funcionais' => '3. TESTES FUNCIONAIS (ZONAS & SABOTAGEM)',
    'dispositivos_aviso' => '4. DISPOSITIVOS DE AVISO & COMUNICAÇÃO (CRA)',
    'encerramento' => '5. ENCERRAMENTO DE OBRA & LEGAL',
];

?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minuta - Relatório Técnico EN 50131</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="minuta-page">
    <div class="container">
        <div style="text-align:right;margin-bottom:10px;">
            <button onclick="window.print()" class="btn btn-primary">🖨️ Imprimir</button>
            <?php if ($id): ?>
                <a href="ver_relatorio.php?id=<?= $id ?>" class="btn btn-outline" style="color:#333;border-color:#ccc;">Voltar</a>
            <?php endif; ?>
        </div>

        <div class="minuta-header">
            <h1>RELATÓRIO TÉCNICO</h1>
            <p>Alarmes & CCTV — Norma EN 50131</p>
            <p style="font-size:0.8em;margin-top:4px;color:#999;">📌 Documento para preenchimento manual em obra (sem necessidade de internet)</p>
        </div>

        <!-- DADOS DO CLIENTE -->
        <div class="minuta-cliente">
            <table>
                <tr>
                    <td>Cliente:</td>
                    <td><?= $dados_preenchidos ? htmlspecialchars($r['cliente_nome']) : '____________________________' ?></td>
                    <td>Data:</td>
                    <td><?= $dados_preenchidos ? date('d/m/Y', strtotime($r['data'])) : '___/___/2026' ?></td>
                </tr>
                <tr>
                    <td>Morada:</td>
                    <td><?= $dados_preenchidos ? htmlspecialchars($r['cliente_morada']) : '____________________________' ?></td>
                    <td>NIF:</td>
                    <td><?= $dados_preenchidos ? htmlspecialchars($r['cliente_nif']) : '___________' ?></td>
                </tr>
                <tr>
                    <td>Central/Modelo:</td>
                    <td><?= $dados_preenchidos ? htmlspecialchars($r['central_modelo']) : '____________________________' ?></td>
                    <td>Grau:</td>
                    <td><?= $dados_preenchidos ? htmlspecialchars($r['grau_sistema']) : '[ ] G1  [ ] G2  [ ] G3  [ ] G4' ?></td>
                </tr>
                <tr>
                    <td>Técnico:</td>
                    <td><?= $dados_preenchidos ? htmlspecialchars($r['tecnico_nome']) : '____________________________' ?></td>
                    <td>H.Início/Fim:</td>
                    <td><?= $dados_preenchidos ? ($r['hora_inicio'] ?? '___:___') . ' / ' . ($r['hora_fim'] ?? '___:___') : '___:___ / ___:___' ?></td>
                </tr>
            </table>
        </div>

        <!-- CHECKLIST -->
        <?php if ($dados_preenchidos): ?>
            <?php foreach ($secoes as $secao_nome => $itens_secao): ?>
            <div class="minuta-section">
                <h3><?= $titulos_secoes[$secao_nome] ?? $secao_nome ?></h3>
                <?php foreach ($itens_secao as $item): ?>
                <div class="minuta-item">
                    <div class="item-check"><?= $item['verificado'] ? '✓' : '☐' ?></div>
                    <div class="item-content">
                        <strong><?= htmlspecialchars($item['item_codigo']) ?></strong> — <?= htmlspecialchars($item['item_descricao']) ?>
                    </div>
                    <?php if (in_array($item['item_codigo'], ['2.1','2.2','2.3','2.4'])): ?>
                    <div class="item-valor"><?= htmlspecialchars($item['valor_medido'] ?? '________') ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- Versão em branco para preencher manualmente -->
            <div class="minuta-section">
                <h3>1. INSPEÇÃO VISUAL & MECÂNICA</h3>
                <div class="minuta-item"><div class="item-check"></div><div class="item-content"><strong>1.1</strong> — Sensores Desimpedidos: Sem móveis, objetos ou obstáculos a tapar a cobertura.</div></div>
                <div class="minuta-item"><div class="item-check"></div><div class="item-content"><strong>1.2</strong> — Fixações e Tampers: Caixas bem fixas e interruptores de sabotagem (parede/tampa) operacionais.</div></div>
                <div class="minuta-item"><div class="item-check"></div><div class="item-content"><strong>1.3</strong> — Limpeza Geral: Interior da central, fontes e detetores limpos (sem pó ou insetos).</div></div>
            </div>

            <div class="minuta-section">
                <h3>2. ENSAIOS ELÉCTRICOS & BATERIAS (Valores de Referência)</h3>
                <div class="minuta-item"><div class="item-check"></div><div class="item-content"><strong>2.1</strong> — Tensão de Rede AC Ligada: Medido nos bornes AUX da central.</div><div class="item-valor">____ V DC</div></div>
                <div class="minuta-item"><div class="item-check"></div><div class="item-content"><strong>2.2</strong> — Teste da Bateria Principal (Sem AC).</div><div class="item-valor">____ V DC</div></div>
                <div class="minuta-item"><div class="item-check"></div><div class="item-content"><strong>2.3</strong> — Bateria da Sirene Exterior: Toca com força?</div><div class="item-valor">[ ]Sim [ ]Não</div></div>
                <div class="minuta-item"><div class="item-check"></div><div class="item-content"><strong>2.4</strong> — Queda de Tensão na Zona Mais Distante.</div><div class="item-valor">____ V DC</div></div>
            </div>

            <div class="minuta-section">
                <h3>3. TESTES FUNCIONAIS (ZONAS & SABOTAGEM)</h3>
                <div class="minuta-item"><div class="item-check"></div><div class="item-content"><strong>3.1</strong> — Walk Test Total: Todos os detetores abrem e transmitem sinal à central.</div><div class="item-valor">[ ]OK [ ]NOK</div></div>
                <div class="minuta-item"><div class="item-check"></div><div class="item-content"><strong>3.2</strong> — Teste de Tamper: Gerou alarme de sabotagem?</div><div class="item-valor">[ ]Sim [ ]Não</div></div>
                <div class="minuta-item"><div class="item-check"></div><div class="item-content"><strong>3.3</strong> — Linhas DEOL: Resistência conforme o painel?</div><div class="item-valor">[ ]Sim [ ]Não</div></div>
            </div>

            <div class="minuta-section">
                <h3>4. DISPOSITIVOS DE AVISO & COMUNICAÇÃO (CRA)</h3>
                <div class="minuta-item"><div class="item-check"></div><div class="item-content"><strong>4.1</strong> — Sirene Interior: Atua e desliga no tempo?</div><div class="item-valor">[ ]Sim [ ]Não</div></div>
                <div class="minuta-item"><div class="item-check"></div><div class="item-content"><strong>4.2</strong> — Sirene Exterior: Bloqueio+tempo legal (3-5 min)?</div><div class="item-valor">[ ]Sim [ ]Não</div></div>
                <div class="minuta-item"><div class="item-check"></div><div class="item-content"><strong>4.3</strong> — Teste de Canais à CRA: Eventos recebidos?</div><div class="item-valor">[ ]Alarme [ ]Restauro [ ]Sabotagem [ ]Falha AC [ ]Teste</div></div>
            </div>

            <div class="minuta-section">
                <h3>5. ENCERRAMENTO DE OBRA & LEGAL</h3>
                <div class="minuta-item"><div class="item-check"></div><div class="item-content"><strong>5.1</strong> — Histórico de Eventos (Log): Memória lida, erros analisados, contadores limpos.</div></div>
                <div class="minuta-item"><div class="item-check"></div><div class="item-content"><strong>5.2</strong> — Livro de Manutenção: Ação registada e assinada.</div></div>
                <div class="minuta-item"><div class="item-check"></div><div class="item-content"><strong>5.3</strong> — Sistema Operacional: Equipamento deixado em modo normal, sem avarias.</div></div>
            </div>
        <?php endif; ?>

        <!-- Material Substituído -->
        <div class="minuta-section">
            <h3>🔧 Material Substituído em Obra</h3>
            <p style="padding:8px 12px;min-height:40px;font-size:0.9em;">
                <?= ($dados_preenchidos && $r['material_substituido']) ? nl2br(htmlspecialchars($r['material_substituido'])) : '_________________________________________________________________________' ?>
            </p>
        </div>

        <!-- Notas -->
        <div class="minuta-section">
            <h3>📝 Notas / Observações</h3>
            <p style="padding:8px 12px;min-height:40px;font-size:0.9em;">
                <?= ($dados_preenchidos && $r['notas']) ? nl2br(htmlspecialchars($r['notas'])) : '_________________________________________________________________________' ?>
            </p>
        </div>

        <!-- Assinaturas -->
        <div class="minuta-assinaturas">
            <div class="assinatura-box">
                <strong>O Técnico</strong><br>
                <?= $dados_preenchidos ? htmlspecialchars($r['tecnico_nome']) : '____________________________' ?>
            </div>
            <div class="assinatura-box">
                <strong>O Cliente</strong><br>
                <?= $dados_preenchidos ? htmlspecialchars($r['cliente_nome']) : '____________________________' ?>
            </div>
        </div>

        <div class="minuta-footer">
            Relatório Técnico conforme Norma EN 50131 — <?= $dados_preenchidos ? date('d/m/Y', strtotime($r['created_at'])) : date('d/m/Y') ?>
        </div>
    </div>
</body>
</html>
