<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db = getDB();

// Buscar clientes para o select
$clientes = $db->query("SELECT id, nome FROM clientes ORDER BY nome")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->beginTransaction();
    try {
        // 1. Criar ou selecionar cliente
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

        if (!$cliente_id) {
            throw new Exception('Selecione ou crie um cliente.');
        }

        verify_csrf();

        // 2. Criar relatório
        $stmt = $db->prepare("INSERT INTO relatorios (user_id, cliente_id, tipo, tipo_obra, data, hora_inicio, hora_fim, central_modelo, grau_sistema, notas, material_substituido) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $cliente_id,
            'alarme',
            $_POST['tipo_obra'] ?? 'manutencao',
            $_POST['data'],
            $_POST['hora_inicio'],
            $_POST['hora_fim'],
            $_POST['central_modelo'] ?? '',
            $_POST['grau_sistema'] ?? '',
            $_POST['notas'] ?? '',
            $_POST['material_substituido'] ?? ''
        ]);
        $relatorio_id = $db->lastInsertId();

        // 3. Inserir itens da checklist
        $secoes = [
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

        $stmt = $db->prepare("INSERT INTO checklist_itens (relatorio_id, secao, item_codigo, item_descricao, verificado, valor_medido, observacao) VALUES (?, ?, ?, ?, ?, ?, ?)");

        foreach ($secoes as $secao_nome => $itens) {
            foreach ($itens as $idx => $item_info) {
                $codigo = $item_info[0];
                $descricao = $item_info[1];
                $post_key = "check_{$secao_nome}_{$idx}";
                $valor_key = "valor_{$secao_nome}_{$idx}";
                $obs_key = "obs_{$secao_nome}_{$idx}";

                $checked = isset($_POST[$post_key]) ? 1 : 0;
                $valor = $_POST[$valor_key] ?? '';
                $obs = $_POST[$obs_key] ?? '';

                $stmt->execute([$relatorio_id, $secao_nome, $codigo, $descricao, $checked, $valor, $obs]);
            }
        }

        $db->commit();
        header("Location: ver_relatorio.php?id=$relatorio_id&sucesso=1");
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        $erro = 'Erro ao guardar: ocorreu um erro inesperado.';
    }
}

$include_header = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <h2>🚨 Novo Relatório — Alarme</h2>
    <p style="color:#888;font-size:0.9em;margin-bottom:16px;">Checklist de Manutenção/Instalação de Sistemas de Alarme</p>

    <?php if (isset($erro)): ?>
        <div class="alert alert-danger"><?= e($erro) ?></div>
    <?php endif; ?>

    <form method="POST" id="form-relatorio">
        <?= csrf_field() ?>
        <!-- DADOS DO RELATÓRIO -->
        <div class="card" style="box-shadow:none;border:1px solid #eee;padding:20px;margin-bottom:20px;">
            <h3 style="margin-bottom:16px;font-size:1.1em;">📋 Dados do Relatório</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="data">Data *</label>
                    <input type="date" id="data" name="data" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="tipo_obra">Tipo de Obra *</label>
                    <select id="tipo_obra" name="tipo_obra" class="form-control" required>
                        <option value="manutencao">Manutenção</option>
                        <option value="instalacao">Instalação</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="hora_inicio">Hora Início</label>
                    <input type="time" id="hora_inicio" name="hora_inicio" class="form-control">
                </div>
                <div class="form-group">
                    <label for="hora_fim">Hora Fim</label>
                    <input type="time" id="hora_fim" name="hora_fim" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="central_modelo">Central / Modelo</label>
                    <input type="text" id="central_modelo" name="central_modelo" class="form-control" placeholder="Ex: Paradox EVO192">
                </div>
                <div class="form-group">
                    <label for="grau_sistema">Grau do Sistema</label>
                    <select id="grau_sistema" name="grau_sistema" class="form-control">
                        <option value="">-- Selecione --</option>
                        <option value="G1">G1 - Baixo Risco</option>
                        <option value="G2">G2 - Médio Risco</option>
                        <option value="G3">G3 - Alto Risco</option>
                        <option value="G4">G4 - Muito Alto Risco</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- CLIENTE -->
        <div class="card" style="box-shadow:none;border:1px solid #eee;padding:20px;margin-bottom:20px;">
            <h3 style="margin-bottom:16px;font-size:1.1em;">👤 Cliente</h3>
            <div class="form-group">
                <label for="cliente_id">Cliente Existente</label>
                <select id="cliente_id" name="cliente_id" class="form-control" onchange="toggleClienteNovo(this.value)">
                    <option value="0">-- Novo Cliente --</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="novo-cliente-fields">
                <div class="form-group">
                    <label for="cliente_nome">Nome do Cliente *</label>
                    <input type="text" id="cliente_nome" name="cliente_nome" class="form-control" placeholder="Nome / Empresa">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="cliente_morada">Morada</label>
                        <input type="text" id="cliente_morada" name="cliente_morada" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="cliente_nif">NIF</label>
                        <input type="text" id="cliente_nif" name="cliente_nif" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="cliente_telefone">Telefone</label>
                        <input type="text" id="cliente_telefone" name="cliente_telefone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="cliente_email">Email</label>
                        <input type="email" id="cliente_email" name="cliente_email" class="form-control">
                    </div>
                </div>
            </div>
        </div>

        <!-- CHECKLIST -->
        <div class="card" style="box-shadow:none;border:1px solid #eee;padding:20px;margin-bottom:20px;">
            <h3 style="margin-bottom:16px;font-size:1.1em;">✅ Checklist EN 50131</h3>
            <p style="font-size:0.9em;color:#888;margin-bottom:12px;">Assinale os itens verificados e preencha os valores medidos.</p>

            <!-- SECÇÃO 1 -->
            <div class="checklist-section">
                <h3>1. INSPEÇÃO VISUAL & MECÂNICA</h3>
                <div class="checklist-item">
                    <input type="checkbox" id="check_inspecao_visual_0" name="check_inspecao_visual_0" value="1">
                    <div class="item-content">
                        <div class="item-code">1.1</div>
                        <div class="item-desc">Sensores Desimpedidos: Sem móveis, objetos ou obstáculos a tapar a cobertura.</div>
                    </div>
                </div>
                <div class="checklist-item">
                    <input type="checkbox" id="check_inspecao_visual_1" name="check_inspecao_visual_1" value="1">
                    <div class="item-content">
                        <div class="item-code">1.2</div>
                        <div class="item-desc">Fixações e Tampers: Caixas bem fixas e interruptores de sabotagem (parede/tampa) operacionais.</div>
                    </div>
                </div>
                <div class="checklist-item">
                    <input type="checkbox" id="check_inspecao_visual_2" name="check_inspecao_visual_2" value="1">
                    <div class="item-content">
                        <div class="item-code">1.3</div>
                        <div class="item-desc">Limpeza Geral: Interior da central, fontes e detetores limpos (sem pó ou insetos).</div>
                    </div>
                </div>
            </div>

            <!-- SECÇÃO 2 -->
            <div class="checklist-section">
                <h3>2. ENSAIOS ELÉCTRICOS & BATERIAS</h3>
                <div class="checklist-item">
                    <input type="checkbox" id="check_ensaios_electricos_0" name="check_ensaios_electricos_0" value="1">
                    <div class="item-content">
                        <div class="item-code">2.1</div>
                        <div class="item-desc">Tensão de Rede AC Ligada: Medido nos bornes AUX da central.</div>
                        <div class="item-fields">
                            <span>Valor: <input type="text" name="valor_ensaios_electricos_0" placeholder="Ex: 13.75 V DC"></span>
                            <span class="form-hint">Alvo: 13.7V a 13.8V</span>
                        </div>
                    </div>
                </div>
                <div class="checklist-item">
                    <input type="checkbox" id="check_ensaios_electricos_1" name="check_ensaios_electricos_1" value="1">
                    <div class="item-content">
                        <div class="item-code">2.2</div>
                        <div class="item-desc">Teste da Bateria Principal (Sem AC).</div>
                        <div class="item-fields">
                            <span>Tensão: <input type="text" name="valor_ensaios_electricos_1" placeholder="Ex: 12.8 V"></span>
                            <span class="form-hint">Chumba se < 11.5V na transição</span>
                        </div>
                    </div>
                </div>
                <div class="checklist-item">
                    <input type="checkbox" id="check_ensaios_electricos_2" name="check_ensaios_electricos_2" value="1">
                    <div class="item-content">
                        <div class="item-code">2.3</div>
                        <div class="item-desc">Bateria da Sirene Exterior: Desligar alimentação da sirene. Toca com força?</div>
                        <div class="item-fields">
                            <select name="valor_ensaios_electricos_2">
                                <option value="">--</option>
                                <option value="Sim">Sim</option>
                                <option value="Não">Não</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="checklist-item">
                    <input type="checkbox" id="check_ensaios_electricos_3" name="check_ensaios_electricos_3" value="1">
                    <div class="item-content">
                        <div class="item-code">2.4</div>
                        <div class="item-desc">Queda de Tensão na Zona Mais Distante: Medido no sensor mais longe em alarme (LED ON).</div>
                        <div class="item-fields">
                            <span>Valor: <input type="text" name="valor_ensaios_electricos_3" placeholder="Ex: 12.1 V"></span>
                            <span class="form-hint">Mínimo: 11.5V</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECÇÃO 3 -->
            <div class="checklist-section">
                <h3>3. TESTES FUNCIONAIS (ZONAS & SABOTAGEM)</h3>
                <div class="checklist-item">
                    <input type="checkbox" id="check_testes_funcionais_0" name="check_testes_funcionais_0" value="1">
                    <div class="item-content">
                        <div class="item-code">3.1</div>
                        <div class="item-desc">Walk Test Total: Todos os detetores abrem e transmitem sinal à central.</div>
                        <div class="item-fields">
                            <select name="obs_testes_funcionais_0">
                                <option value="OK">OK</option>
                                <option value="NOK">NOK</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="checklist-item">
                    <input type="checkbox" id="check_testes_funcionais_1" name="check_testes_funcionais_1" value="1">
                    <div class="item-content">
                        <div class="item-code">3.2</div>
                        <div class="item-desc">Teste de Tamper: Provocada abertura de 1 caixa (sensor/sirene) → Gerou alarme de sabotagem?</div>
                        <div class="item-fields">
                            <select name="obs_testes_funcionais_1">
                                <option value="Sim">Sim</option>
                                <option value="Não">Não</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="checklist-item">
                    <input type="checkbox" id="check_testes_funcionais_2" name="check_testes_funcionais_2" value="1">
                    <div class="item-content">
                        <div class="item-code">3.3</div>
                        <div class="item-desc">Linhas DEOL: Resistência óhmica na central em repouso está conforme o painel?</div>
                        <div class="item-fields">
                            <select name="obs_testes_funcionais_2">
                                <option value="Sim">Sim</option>
                                <option value="Não">Não</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECÇÃO 4 -->
            <div class="checklist-section">
                <h3>4. DISPOSITIVOS DE AVISO & COMUNICAÇÃO (CRA)</h3>
                <div class="checklist-item">
                    <input type="checkbox" id="check_dispositivos_aviso_0" name="check_dispositivos_aviso_0" value="1">
                    <div class="item-content">
                        <div class="item-code">4.1</div>
                        <div class="item-desc">Sirene Interior: Atua e desliga no tempo programado?</div>
                        <div class="item-fields">
                            <select name="obs_dispositivos_aviso_0">
                                <option value="Sim">Sim</option>
                                <option value="Não">Não</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="checklist-item">
                    <input type="checkbox" id="check_dispositivos_aviso_1" name="check_dispositivos_aviso_1" value="1">
                    <div class="item-content">
                        <div class="item-code">4.2</div>
                        <div class="item-desc">Sirene Exterior: Bloqueio funciona? Atua e desliga no tempo legal (3-5 min)?</div>
                        <div class="item-fields">
                            <select name="obs_dispositivos_aviso_1">
                                <option value="Sim">Sim</option>
                                <option value="Não">Não</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="checklist-item">
                    <input type="checkbox" id="check_dispositivos_aviso_2" name="check_dispositivos_aviso_2" value="1">
                    <div class="item-content">
                        <div class="item-code">4.3</div>
                        <div class="item-desc">Teste de Canais à CRA: Confirmar com o operador a receção dos eventos.</div>
                        <div class="item-fields" style="display:flex;flex-direction:column;gap:4px;">
                            <div>Eventos testados:</div>
                            <label><input type="checkbox" name="valor_dispositivos_aviso_2_ok[]" value="alarme"> Alarme</label>
                            <label><input type="checkbox" name="valor_dispositivos_aviso_2_ok[]" value="restauro"> Restauro</label>
                            <label><input type="checkbox" name="valor_dispositivos_aviso_2_ok[]" value="sabotagem"> Sabotagem</label>
                            <label><input type="checkbox" name="valor_dispositivos_aviso_2_ok[]" value="falha_ac"> Falha AC</label>
                            <label><input type="checkbox" name="valor_dispositivos_aviso_2_ok[]" value="teste"> Teste Periódico</label>
                        </div>
                        <div class="item-fields">
                            <span>Canais:</span>
                            <label><input type="checkbox" name="canais[]" value="ip"> IP/Internet</label>
                            <label><input type="checkbox" name="canais[]" value="gprs"> GPRS/4G</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECÇÃO 5 -->
            <div class="checklist-section">
                <h3>5. ENCERRAMENTO DE OBRA & LEGAL</h3>
                <div class="checklist-item">
                    <input type="checkbox" id="check_encerramento_0" name="check_encerramento_0" value="1">
                    <div class="item-content">
                        <div class="item-code">5.1</div>
                        <div class="item-desc">Histórico de Eventos (Log): Memória lida, erros antigos analisados e contadores limpos.</div>
                    </div>
                </div>
                <div class="checklist-item">
                    <input type="checkbox" id="check_encerramento_1" name="check_encerramento_1" value="1">
                    <div class="item-content">
                        <div class="item-code">5.2</div>
                        <div class="item-desc">Livro de Manutenção: Ação registada e assinada no documento físico que fica no cliente.</div>
                    </div>
                </div>
                <div class="checklist-item">
                    <input type="checkbox" id="check_encerramento_2" name="check_encerramento_2" value="1">
                    <div class="item-content">
                        <div class="item-code">5.3</div>
                        <div class="item-desc">Sistema Operacional: Equipamento deixado em modo normal de funcionamento, sem avarias.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- NOTAS E ASSINATURAS -->
        <div class="card" style="box-shadow:none;border:1px solid #eee;padding:20px;margin-bottom:20px;">
            <h3 style="margin-bottom:16px;font-size:1.1em;">📝 Notas & Encerramento</h3>
            <div class="form-group">
                <label for="material_substituido">Material Substituído em Obra</label>
                <textarea id="material_substituido" name="material_substituido" class="form-control" placeholder="Descreva o material substituído (caso aplicável)"></textarea>
            </div>
            <div class="form-group">
                <label for="notas">Notas / Observações</label>
                <textarea id="notas" name="notas" class="form-control" placeholder="Observações adicionais..."></textarea>
            </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;">
            <a href="dashboard.php" class="btn btn-outline" style="color:#333;border-color:#ccc;">Cancelar</a>
            <button type="submit" class="btn btn-success">Guardar Relatório</button>
        </div>
    </form>
</div>

<script>
function toggleClienteNovo(val) {
    var fields = document.getElementById('novo-cliente-fields');
    fields.style.display = (val == 0) ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', function() {
    toggleClienteNovo(document.getElementById('cliente_id').value);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
