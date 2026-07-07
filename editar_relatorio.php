<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db = getDB();
$id = $_GET['id'] ?? 0;
$erro = '';
$sucesso = '';

// Carregar relatório
$relatorio = $db->prepare("SELECT r.*, c.id as cliente_id, c.nome as cliente_nome, c.morada as cliente_morada, c.telefone as cliente_telefone, c.email as cliente_email, c.nif as cliente_nif, u.nome as tecnico_nome FROM relatorios r JOIN clientes c ON r.cliente_id = c.id JOIN users u ON r.user_id = u.id WHERE r.id = ?");
$relatorio->execute([$id]);
$r = $relatorio->fetch();

if (!$r) {
    header('Location: dashboard.php');
    exit;
}

$clientes = $db->query("SELECT id, nome FROM clientes ORDER BY nome")->fetchAll();

// Carregar checklist existente
$itens = $db->prepare("SELECT * FROM checklist_itens WHERE relatorio_id = ? ORDER BY id");
$itens->execute([$id]);
$todos_itens = $itens->fetchAll();

$secoes = [];
foreach ($todos_itens as $item) {
    $secoes[$item['secao']][] = $item;
}

// Títulos das secções
$titulos_secoes = [
    'inspecao_visual' => '1. INSPEÇÃO VISUAL & MECÂNICA',
    'ensaios_electricos' => '2. ENSAIOS ELÉCTRICOS & BATERIAS',
    'testes_funcionais' => '3. TESTES FUNCIONAIS (ZONAS & SABOTAGEM)',
    'dispositivos_aviso' => '4. DISPOSITIVOS DE AVISO & COMUNICAÇÃO (CRA)',
    'encerramento' => '5. ENCERRAMENTO DE OBRA & LEGAL',
    'info_sistema' => '1. INFORMAÇÃO DO SISTEMA',
    'infraestrutura' => '2. INFRAESTRUTURA E CABLAGEM',
    'camaras' => '3. UNIDADES DE CAPTURA (CÂMARAS)',
    'gravacao_rede' => '4. GRAVAÇÃO, PROCESSAMENTO E REDE',
    'rgpd' => '5. CONFORMIDADE LEGAL E RGPD',
    'testes_entrega' => '6. TESTES DE SISTEMA E ENTREGA',
    'leitores' => '3. LEITORES E CREDENCIAIS',
    'fechaduras' => '4. FECHADURAS E MECANISMOS',
    'software_config' => '5. SOFTWARE E CONFIGURAÇÃO',
];

// Guardar edição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->beginTransaction();
    try {
        verify_csrf();

        $cliente_id = $_POST['cliente_id'] ?? 0;
        if ($cliente_id == 0 && !empty($_POST['cliente_nome'])) {
            $stmt = $db->prepare("INSERT INTO clientes (nome, morada, telefone, email, nif) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['cliente_nome'],
                $_POST['cliente_morada'] ?? '',
                $_POST['cliente_telefone'] ?? '',
                $_POST['cliente_email'] ?? '',
                $_POST['cliente_nif'] ?? ''
            ]);
            $cliente_id = $db->lastInsertId();
        }
        if (!$cliente_id) throw new Exception('Selecione ou crie um cliente.');

        // Atualizar relatório
        $stmt = $db->prepare("UPDATE relatorios SET cliente_id=?, tipo=?, data=?, hora_inicio=?, hora_fim=?, central_modelo=?, grau_sistema=?, notas=?, material_substituido=? WHERE id=?");
        $stmt->execute([
            $cliente_id,
            $_POST['tipo'] ?? $r['tipo'],
            $_POST['data'],
            $_POST['hora_inicio'] ?? '',
            $_POST['hora_fim'] ?? '',
            $_POST['central_modelo'] ?? '',
            $_POST['grau_sistema'] ?? '',
            $_POST['notas'] ?? '',
            $_POST['material_substituido'] ?? '',
            $id
        ]);

        // Apagar itens antigos e reinserir
        $db->prepare("DELETE FROM checklist_itens WHERE relatorio_id = ?")->execute([$id]);

        // Reconstruir seções com base no tipo
        $secoes_def = [];
        if ($r['tipo'] === 'cctv') {
            $secoes_def = [
                'info_sistema' => [
                    ['gravador', 'Gravador (NVR/DVR): Marca/Modelo e Nº de Série'],
                    ['armazenamento', 'Capacidade de Armazenamento: [X] TB (Discos específicos para videovigilância)'],
                    ['camaras_total', 'Nº Total de Câmaras IP / Analógicas (HD-TVI/CVI)'],
                    ['software', 'Software/App de Acesso: Nome da plataforma'],
                ],
                'infraestrutura' => [
                    ['cablagem', 'Cablagem e Conexões: Verificação de cabos UTP/Coaxiais, fichas RJ45/BNC e isolamento contra humidades'],
                    ['fontes_poe', 'Fontes de Alimentação / PoE: Medição das tensões de alimentação e teste de carga nos switchs PoE'],
                    ['ups', 'Sistema de Backup (UPS): Teste de autonomia da UPS e estado da bateria'],
                    ['fixacoes', 'Fixações Mecânicas: Reaperto de suportes, braços e caixas de proteção'],
                ],
                'camaras' => [
                    ['limpeza', 'Limpeza Ótica: Limpeza profunda das cúpulas (domos) e lentes com produtos antiestáticos'],
                    ['focagem', 'Focagem e Enquadramento: Ajuste do campo de visão (FOV) e verificação de pontos cegos'],
                    ['visao_noturna', 'Visão Noturna (IV): Teste dos iluminadores infravermelhos e ativação do filtro ICR'],
                    ['config_imagem', 'Configurações de Imagem: Ajuste de WDR, brilho e contraste'],
                ],
                'gravacao_rede' => [
                    ['hdds', 'Discos Rígidos (HDD): Verificação do estado de saúde (S.M.A.R.T.) e ciclo de overwrite'],
                    ['firmware', 'Firmware: Atualização do firmware do NVR/DVR e das câmaras'],
                    ['ntp', 'Sincronização Horária: Configuração e validação do relógio via servidor NTP'],
                    ['seguranca_rede', 'Segurança de Rede: Alteração de passwords padrão e validação de firewall'],
                    ['canais', 'Configuração de Canais: Resolução de gravação, bitrate e fps contratados'],
                ],
                'rgpd' => [
                    ['sinaletica', 'Sinalética Obrigatória: Afixação de dísticos informativos visíveis nos acessos'],
                    ['retencao', 'Prazo de Retenção: Configuração para eliminação automática aos 30 dias'],
                    ['zonas', 'Zonas Privadas/Públicas: Validação das máscaras de privacidade (privacy masks)'],
                    ['controlo_acessos', 'Controlo de Acessos: Definição de perfis de utilizador com passwords fortes'],
                ],
                'testes_entrega' => [
                    ['playback', 'Teste de Reprodução (Playback): Exportação de clip de vídeo de teste'],
                    ['acesso_remoto', 'Acesso Remoto: Teste de visualização em tempo real via App/Software'],
                    ['limpeza_local', 'Limpeza do Local: Remoção de resíduos e resguardos'],
                ],
            ];
        } elseif ($r['tipo'] === 'acessos') {
            $secoes_def = [
                'info_sistema' => [
                    ['controladora', 'Central/Controladora de Acessos: Marca, Modelo e Nº de Série'],
                    ['software', 'Software de Gestão: Nome da plataforma e versão'],
                    ['pontos_acesso', 'Nº Total de Pontos de Acesso (portas controladas)'],
                    ['leitores_tipo', 'Tipo de Leitores: Proximidade / Biometrico / Teclado / PIN'],
                    ['fechaduras_tipo', 'Tipo de Fechaduras: Eletromagnéticas / Elétricas / Motrizes'],
                ],
                'infraestrutura' => [
                    ['cablagem', 'Cablagem e Conexões: Verificação de cabos RS485/TCP, fichas e isolamento contra humidades'],
                    ['alimentacao', 'Fontes de Alimentação: Medição das tensões de alimentação dos leitores e fechaduras'],
                    ['ups', 'Sistema de Backup (UPS): Teste de autonomia da UPS e estado da bateria'],
                    ['fixacoes', 'Fixações Mecânicas: Reaperto de leitores, fechaduras, REX e caixas de proteção'],
                ],
                'leitores' => [
                    ['teste_leitura', 'Teste de Leitura: Cartão/PIN/Biometria lido corretamente em cada leitor'],
                    ['distancia', 'Distância de Leitura: Conforme especificação técnica do fabricante'],
                    ['leds_buzzer', 'LEDs e Buzzer: Sinalização visual/sonora operacional em cada leitor'],
                    ['credenciais', 'Credenciais Atribuídas: Total de cartões/PINs/biometrias emitidos vs. atribuídos'],
                ],
                'fechaduras' => [
                    ['fecho_teste', 'Fecho Eletromagnético/Elétrico: Teste de força e retenção da fechadura'],
                    ['fail_safe', 'Modo Fail-Safe / Fail-Secure: Comportamento em falta de energia conforme projeto'],
                    ['rex', 'Botão de Saída (REX): Abertura confirmada em cada ponto de acesso'],
                    ['fecho_porta', 'Fecho de Porta: Fecho de batente e/ou fecho automático operacional'],
                ],
                'software_config' => [
                    ['perfis', 'Perfis de Utilizador: Níveis de acesso definidos (Administrador, Operador, Utilizador)'],
                    ['horarios', 'Horários e Calendários: Perfis horários configurados conforme necessidades do cliente'],
                    ['alarmes', 'Alarmes de Porta: Porta Forçada (Forced Door) e Porta Mantida Aberta (Door Held Open) ativos'],
                    ['incendio', 'Integração com Incêndio: Sinal de desbloqueio geral das portas em caso de incêndio'],
                    ['firmware', 'Firmware: Atualização do firmware da controladora e leitores'],
                ],
                'rgpd' => [
                    ['sinaletica', 'Sinalética Obrigatória: Afixação de dísticos informativos nos acessos vigiados'],
                    ['retencao', 'Prazo de Retenção: Configuração para eliminação automática de registos de acesso'],
                    ['dados_biometricos', 'Dados Biométricos: Consentimento e proteção dos dados biométricos armazenados'],
                    ['controlo_perfis', 'Controlo de Acessos Lógico: Perfis de utilizador com passwords fortes e autenticação'],
                ],
                'testes_entrega' => [
                    ['teste_pontos', 'Teste Funcional: Cada ponto de acesso testado individualmente (abertura/fecho)'],
                    ['alarmes_teste', 'Teste de Alarmes: Porta Forçada e Door Held Open geram evento no software'],
                    ['emergencia', 'Teste de Emergência: Desbloqueio geral via botão de incêndio/corte de energia'],
                    ['acesso_remoto', 'Acesso Remoto: Teste de gestão via software/app mobile'],
                    ['limpeza_local', 'Limpeza do Local: Remoção de resíduos e resguardos'],
                ],
            ];
        } else {
            // Alarme (instalacao/manutencao)
            $secoes_def = [
                'inspecao_visual' => [
                    ['1.1', 'Sensores Desimpedidos: Sem móveis, objetos ou obstáculos a tapar a cobertura.'],
                    ['1.2', 'Fixações e Tampers: Caixas bem fixas e interruptores de sabotagem (parede/tampa) operacionais.'],
                    ['1.3', 'Limpeza Geral: Interior da central, fontes e detetores limpos (sem pó ou insetos).'],
                ],
                'ensaios_electricos' => [
                    ['2.1', 'Tensão de Rede AC Ligada: Medido nos bornes AUX da central. (Alvo: 13.7V a 13.8V)'],
                    ['2.2', 'Teste da Bateria Principal (Sem AC): Desligar disjuntor AC e medir a bateria sob carga. (Chumba se < 11.5V)'],
                    ['2.3', 'Bateria da Sirene Exterior: Desligar alimentação da sirene. Toca com força?'],
                    ['2.4', 'Queda de Tensão na Zona Mais Distante: Medido no sensor mais longe em estado de alarme. (Mínimo: 11.5V)'],
                ],
                'testes_funcionais' => [
                    ['3.1', 'Walk Test Total: Todos os detetores abrem e transmitem sinal à central.'],
                    ['3.2', 'Teste de Tamper: Provocada abertura de 1 caixa (sensor/sirene) → Gerou alarme de sabotagem?'],
                    ['3.3', 'Linhas DEOL: Resistência óhmica na central em repouso está conforme o painel?'],
                ],
                'dispositivos_aviso' => [
                    ['4.1', 'Sirene Interior: Atua e desliga no tempo programado?'],
                    ['4.2', 'Sirene Exterior: Bloqueio funciona? Atua e desliga no tempo legal (3-5 min)?'],
                    ['4.3', 'Teste de Canais à CRA: Confirmar com o operador a receção dos eventos (Alarme, Restauro, Sabotagem, Falha AC, Teste Periódico)'],
                ],
                'encerramento' => [
                    ['5.1', 'Histórico de Eventos (Log): Memória lida, erros antigos analisados e contadores limpos.'],
                    ['5.2', 'Livro de Manutenção: Ação registada e assinada no documento físico que fica no cliente.'],
                    ['5.3', 'Sistema Operacional: Equipamento deixado em modo normal de funcionamento, sem avarias.'],
                ],
            ];
        }

        // Indexar itens existentes por (secao, codigo) para preservar valores
        $itens_existentes = [];
        foreach ($todos_itens as $item) {
            $itens_existentes[$item['secao'] . '_' . $item['item_codigo']] = $item;
        }

        $stmt = $db->prepare("INSERT INTO checklist_itens (relatorio_id, secao, item_codigo, item_descricao, verificado, valor_medido, observacao) VALUES (?, ?, ?, ?, ?, ?, ?)");

        foreach ($secoes_def as $secao_nome => $itens_def) {
            foreach ($itens_def as $idx => $item_info) {
                $codigo = $item_info[0];
                $descricao = $item_info[1];
                $post_key = "check_{$secao_nome}_{$idx}";
                $valor_key = "valor_{$secao_nome}_{$idx}";
                $obs_key = "obs_{$secao_nome}_{$idx}";

                $checked = isset($_POST[$post_key]) ? 1 : 0;
                $valor = $_POST[$valor_key] ?? '';
                $obs = $_POST[$obs_key] ?? '';

                $stmt->execute([$id, $secao_nome, $codigo, $descricao, $checked, $valor, $obs]);
            }
        }

        $db->commit();
        header("Location: ver_relatorio.php?id=$id&sucesso=2");
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        $erro = 'Erro ao guardar: ' . $e->getMessage();
    }
}

$tipo_label = $r['tipo'] === 'cctv' ? '📹 CCTV' : ($r['tipo'] === 'acessos' ? '🔐 Controlo Acessos' : '🚨 Alarme');

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
        <h2 style="border:none;margin:0;padding:0;">✏️ Editar Relatório #<?= $id ?></h2>
        <div style="display:flex;gap:8px;">
            <span class="badge-tipo <?= $r['tipo'] ?>"><?= $tipo_label ?></span>
            <a href="ver_relatorio.php?id=<?= $id ?>" class="btn btn-outline" style="color:#333;border-color:#ccc;">Cancelar</a>
        </div>
    </div>

    <?php if ($erro): ?>
        <div class="alert alert-danger"><?= e($erro) ?></div>
    <?php endif; ?>

    <form method="POST" id="form-editar">
        <?= csrf_field() ?>

        <!-- DADOS DO RELATÓRIO -->
        <div class="card" style="box-shadow:none;border:1px solid #eee;padding:20px;margin-bottom:20px;">
            <h3 style="margin-bottom:16px;font-size:1.1em;">📋 Dados do Relatório</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="data">Data *</label>
                    <input type="date" id="data" name="data" class="form-control" value="<?= $r['data'] ?>" required>
                </div>
                <div class="form-group">
                    <label for="tipo">Tipo de Relatório</label>
                    <select id="tipo" name="tipo" class="form-control">
                        <option value="instalacao" <?= $r['tipo'] === 'instalacao' ? 'selected' : '' ?>>Instalação</option>
                        <option value="manutencao" <?= $r['tipo'] === 'manutencao' ? 'selected' : '' ?>>Manutenção Preventiva</option>
                        <option value="cctv" <?= $r['tipo'] === 'cctv' ? 'selected' : '' ?>>📹 CCTV</option>
                        <option value="acessos" <?= $r['tipo'] === 'acessos' ? 'selected' : '' ?>>🔐 Controlo Acessos</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="hora_inicio">Hora Início</label>
                    <input type="time" id="hora_inicio" name="hora_inicio" class="form-control" value="<?= e($r['hora_inicio']) ?>">
                </div>
                <div class="form-group">
                    <label for="hora_fim">Hora Fim</label>
                    <input type="time" id="hora_fim" name="hora_fim" class="form-control" value="<?= e($r['hora_fim']) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="central_modelo">Central / Equipamento</label>
                    <input type="text" id="central_modelo" name="central_modelo" class="form-control" value="<?= e($r['central_modelo']) ?>">
                </div>
                <div class="form-group">
                    <label for="grau_sistema">Grau / Info</label>
                    <input type="text" id="grau_sistema" name="grau_sistema" class="form-control" value="<?= e($r['grau_sistema']) ?>">
                </div>
            </div>
            <p style="color:#888;font-size:0.85em;margin-top:4px;"><strong>Técnico:</strong> <?= htmlspecialchars($r['tecnico_nome']) ?></p>
        </div>

        <!-- CLIENTE -->
        <div class="card" style="box-shadow:none;border:1px solid #eee;padding:20px;margin-bottom:20px;">
            <h3 style="margin-bottom:16px;font-size:1.1em;">👤 Cliente</h3>
            <div class="form-group">
                <label for="cliente_id">Cliente</label>
                <select id="cliente_id" name="cliente_id" class="form-control" onchange="toggleClienteNovo(this.value)">
                    <option value="0">-- Novo Cliente --</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id'] == $r['cliente_id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="novo-cliente-fields" style="display:none;">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nome do Cliente *</label>
                        <input type="text" name="cliente_nome" class="form-control" value="<?= e($r['cliente_nome']) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Morada</label><input type="text" name="cliente_morada" class="form-control" value="<?= e($r['cliente_morada']) ?>"></div>
                    <div class="form-group"><label>NIF</label><input type="text" name="cliente_nif" class="form-control" value="<?= e($r['cliente_nif']) ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Telefone</label><input type="text" name="cliente_telefone" class="form-control" value="<?= e($r['cliente_telefone']) ?>"></div>
                    <div class="form-group"><label>Email</label><input type="email" name="cliente_email" class="form-control" value="<?= e($r['cliente_email']) ?>"></div>
                </div>
            </div>
        </div>

        <!-- CHECKLIST -->
        <div class="card" style="box-shadow:none;border:1px solid #eee;padding:20px;margin-bottom:20px;">
            <h3 style="margin-bottom:16px;font-size:1.1em;">✅ Checklist</h3>

            <?php foreach ($secoes as $secao_nome => $itens_secao): ?>
            <div class="checklist-section">
                <h3><?= $titulos_secoes[$secao_nome] ?? $secao_nome ?></h3>
                <?php foreach ($itens_secao as $idx => $item): ?>
                <div class="checklist-item">
                    <input type="checkbox" id="check_<?= $secao_nome ?>_<?= $idx ?>" name="check_<?= $secao_nome ?>_<?= $idx ?>" value="1" <?= $item['verificado'] ? 'checked' : '' ?>>
                    <div class="item-content">
                        <div class="item-code"><?= htmlspecialchars($item['item_codigo']) ?></div>
                        <div class="item-desc"><?= htmlspecialchars($item['item_descricao']) ?></div>
                        <?php if ($item['valor_medido'] !== null || $item['item_codigo'] === '2.1' || $item['item_codigo'] === '2.2' || $item['item_codigo'] === '2.4' || $item['item_codigo'] === 'armazenamento' || $item['item_codigo'] === 'fontes_poe' || $item['item_codigo'] === 'alimentacao' || $item['item_codigo'] === 'distancia' || $item['item_codigo'] === 'retencao' || $item['item_codigo'] === 'credenciais'): ?>
                        <div class="item-fields">
                            <span>Valor: <input type="text" name="valor_<?= $secao_nome ?>_<?= $idx ?>" value="<?= e($item['valor_medido'] ?? '') ?>" placeholder="Valor medido..."></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($item['observacao'] !== null || $item['item_codigo'] === '2.3' || $item['item_codigo'] === 'ups' || $item['item_codigo'] === 'visao_noturna' || $item['item_codigo'] === 'hdds' || $item['item_codigo'] === 'ntp' || $item['item_codigo'] === 'playback' || $item['item_codigo'] === 'fail_safe' || $item['item_codigo'] === 'incendio' || $item['item_codigo'] === 'leds_buzzer' || $item['item_codigo'] === 'alarmes_teste' || $item['item_codigo'] === 'emergencia' || $item['item_codigo'] === 'acesso_remoto'): ?>
                        <div class="item-fields">
                            <span>Obs: <input type="text" name="obs_<?= $secao_nome ?>_<?= $idx ?>" value="<?= e($item['observacao'] ?? '') ?>" placeholder="Observação..."></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- NOTAS E MATERIAL -->
        <div class="card" style="box-shadow:none;border:1px solid #eee;padding:20px;margin-bottom:20px;">
            <h3 style="margin-bottom:16px;font-size:1.1em;">📝 Observações / Anomalias Detetadas</h3>
            <div class="form-group">
                <label for="material_substituido">Material Substituído em Obra</label>
                <textarea id="material_substituido" name="material_substituido" class="form-control" placeholder="Descreva necessidades de substituição de material..."><?= e($r['material_substituido']) ?></textarea>
            </div>
            <div class="form-group">
                <label for="notas">Notas / Observações</label>
                <textarea id="notas" name="notas" class="form-control" placeholder="Observações adicionais..."><?= e($r['notas']) ?></textarea>
            </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;">
            <a href="ver_relatorio.php?id=<?= $id ?>" class="btn btn-outline" style="color:#333;border-color:#ccc;">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar Alterações</button>
        </div>
    </form>
</div>

<style>
.badge-tipo {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 14px;
    font-size: 0.85em;
    font-weight: 600;
}
.badge-tipo.cctv { background: #e3f2fd; color: #0f5b8a; }
.badge-tipo.acessos { background: #f3e5f5; color: #6a1b9a; }
.badge-tipo.instalacao,
.badge-tipo.manutencao { background: #e8f5e9; color: #1b5e20; }
</style>

<script>
function toggleClienteNovo(val) {
    document.getElementById('novo-cliente-fields').style.display = (val == 0) ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', function() {
    toggleClienteNovo(document.getElementById('cliente_id').value);
    // Disable value/obs fields when checkbox unchecked
    document.querySelectorAll('.checklist-item input[type="checkbox"]').forEach(function(cb) {
        cb.addEventListener('change', function() {
            var fields = this.closest('.checklist-item').querySelector('.item-fields');
            if (fields) {
                fields.querySelectorAll('input, select').forEach(function(inp) {
                    inp.disabled = !cb.checked;
                });
            }
        });
        cb.dispatchEvent(new Event('change'));
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
