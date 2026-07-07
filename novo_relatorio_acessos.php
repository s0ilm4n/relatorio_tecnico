<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db = getDB();
$clientes = $db->query("SELECT id, nome FROM clientes ORDER BY nome")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->beginTransaction();
    try {
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

        verify_csrf();

        $stmt = $db->prepare("INSERT INTO relatorios (user_id, cliente_id, tipo, data, hora_inicio, hora_fim, central_modelo, grau_sistema, notas, material_substituido) VALUES (?, ?, 'acessos', ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $cliente_id,
            $_POST['data'],
            $_POST['hora_inicio'],
            $_POST['hora_fim'],
            ($_POST['central_marca'] ?? '') . ' | S/N: ' . ($_POST['central_sn'] ?? ''),
            $_POST['total_pontos'] ?? '',
            $_POST['notas'] ?? '',
            $_POST['material_substituido'] ?? ''
        ]);
        $relatorio_id = $db->lastInsertId();

        $secoes = [
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

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <h2>🔐 Novo Relatório — Controlo de Acessos</h2>
    <p style="color:#888;font-size:0.9em;margin-bottom:16px;">Checklist de Instalação/Manutenção de Sistemas de Controlo de Acessos</p>

    <?php if (isset($erro)): ?>
        <div class="alert alert-danger"><?= e($erro) ?></div>
    <?php endif; ?>

    <form method="POST" id="form-acessos">
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
                    <label for="tipo_intervencao">Tipo de Intervenção</label>
                    <select id="tipo_intervencao" name="tipo" class="form-control">
                        <option value="instalacao">Instalação Inicial</option>
                        <option value="manutencao" selected>Manutenção Preventiva</option>
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
        </div>

        <!-- CLIENTE -->
        <div class="card" style="box-shadow:none;border:1px solid #eee;padding:20px;margin-bottom:20px;">
            <h3 style="margin-bottom:16px;font-size:1.1em;">👤 Cliente</h3>
            <div class="form-group">
                <label for="cliente_id">Cliente Existente</label>
                <select id="cliente_id" name="cliente_id" class="form-control" onchange="toggleClienteNovo(this.value)">
                    <option value="0">-- Novo Cliente --</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= e($c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="novo-cliente-fields">
                <div class="form-group">
                    <label for="cliente_nome">Nome do Cliente *</label>
                    <input type="text" id="cliente_nome" name="cliente_nome" class="form-control" placeholder="Nome / Empresa">
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Morada</label><input type="text" name="cliente_morada" class="form-control"></div>
                    <div class="form-group"><label>NIF</label><input type="text" name="cliente_nif" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Telefone</label><input type="text" name="cliente_telefone" class="form-control"></div>
                    <div class="form-group"><label>Email</label><input type="email" name="cliente_email" class="form-control"></div>
                </div>
            </div>
        </div>

        <!-- SECÇÃO 1: INFORMAÇÃO DO SISTEMA -->
        <div class="checklist-section">
            <h3>1. INFORMAÇÃO DO SISTEMA</h3>
            <div class="checklist-item">
                <div class="item-content">
                    <div class="item-code">Controladora</div>
                    <div class="item-desc">Central/Controladora de Acessos: Marca, Modelo e Nº de Série</div>
                    <div class="item-fields">
                        <span>Marca/Modelo: <input type="text" name="central_marca" placeholder="Ex: ACTi, ZKTeco, Hikvision"></span>
                        <span>S/N: <input type="text" name="central_sn" placeholder="Nº de série"></span>
                    </div>
                </div>
            </div>
            <div class="checklist-item">
                <div class="item-content">
                    <div class="item-code">Software</div>
                    <div class="item-desc">Software de Gestão: Nome da plataforma e versão</div>
                    <div class="item-fields">
                        <input type="text" name="software_app" placeholder="Ex: ZKBio, iVMS-4200, ACTi ACM" style="width:300px;">
                    </div>
                </div>
            </div>
            <div class="checklist-item">
                <div class="item-content">
                    <div class="item-code">Pontos</div>
                    <div class="item-desc">Nº Total de Pontos de Acesso (portas controladas)</div>
                    <div class="item-fields">
                        <span><input type="text" name="total_pontos" placeholder="Ex: 8" style="width:80px;"> portas</span>
                    </div>
                </div>
            </div>
            <div class="checklist-item">
                <div class="item-content">
                    <div class="item-code">Leitores</div>
                    <div class="item-desc">Tipo de Leitores</div>
                    <div class="item-fields">
                        <span><input type="text" name="leitores_tipo" placeholder="Ex: Proximidade, Biometrico, PIN" style="width:300px;"></span>
                    </div>
                </div>
            </div>
            <div class="checklist-item">
                <div class="item-content">
                    <div class="item-code">Fechaduras</div>
                    <div class="item-desc">Tipo de Fechaduras</div>
                    <div class="item-fields">
                        <span><input type="text" name="fechaduras_tipo" placeholder="Ex: Eletromagnéticas, Elétricas, Motrizes" style="width:300px;"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECÇÃO 2: INFRAESTRUTURA E CABLAGEM -->
        <div class="checklist-section">
            <h3>2. INFRAESTRUTURA E CABLAGEM 🛠</h3>
            <div class="checklist-item">
                <input type="checkbox" id="check_infraestrutura_0" name="check_infraestrutura_0" value="1">
                <div class="item-content">
                    <div class="item-code">Cablagem</div>
                    <div class="item-desc">Cablagem e Conexões: Verificação de cabos RS485/TCP, fichas e isolamento contra humidades (caixas de derivação estanques).</div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_infraestrutura_1" name="check_infraestrutura_1" value="1">
                <div class="item-content">
                    <div class="item-code">Alimentação</div>
                    <div class="item-desc">Fontes de Alimentação: Medição das tensões de alimentação dos leitores e fechaduras.</div>
                    <div class="item-fields">
                        <span>Tensão: <input type="text" name="valor_infraestrutura_1" placeholder="Ex: 12V / 24V"></span>
                    </div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_infraestrutura_2" name="check_infraestrutura_2" value="1">
                <div class="item-content">
                    <div class="item-code">UPS</div>
                    <div class="item-desc">Sistema de Backup (UPS): Teste de autonomia da UPS (simulação de falha de energia) e estado da bateria.</div>
                    <div class="item-fields">
                        <select name="obs_infraestrutura_2">
                            <option value="">--</option>
                            <option value="OK">OK</option>
                            <option value="NOK">NOK - Substituir</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_infraestrutura_3" name="check_infraestrutura_3" value="1">
                <div class="item-content">
                    <div class="item-code">Fixações</div>
                    <div class="item-desc">Fixações Mecânicas: Reaperto de leitores, fechaduras, botões REX e caixas de proteção.</div>
                </div>
            </div>
        </div>

        <!-- SECÇÃO 3: LEITORES E CREDENCIAIS -->
        <div class="checklist-section">
            <h3>3. LEITORES E CREDENCIAIS 📇</h3>
            <div class="checklist-item">
                <input type="checkbox" id="check_leitores_0" name="check_leitores_0" value="1">
                <div class="item-content">
                    <div class="item-code">Teste Leitura</div>
                    <div class="item-desc">Teste de Leitura: Cartão/PIN/Biometria lido corretamente em cada leitor.</div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_leitores_1" name="check_leitores_1" value="1">
                <div class="item-content">
                    <div class="item-code">Distância</div>
                    <div class="item-desc">Distância de Leitura: Conforme especificação técnica do fabricante.</div>
                    <div class="item-fields">
                        <span>Distância medida: <input type="text" name="valor_leitores_1" placeholder="Ex: 5 cm" style="width:80px;"></span>
                    </div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_leitores_2" name="check_leitores_2" value="1">
                <div class="item-content">
                    <div class="item-code">LEDs/Buzzer</div>
                    <div class="item-desc">LEDs e Buzzer: Sinalização visual (verde/vermelho) e sonora operacional em cada leitor.</div>
                    <div class="item-fields">
                        <select name="obs_leitores_2">
                            <option value="">--</option>
                            <option value="OK">OK</option>
                            <option value="NOK">NOK</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_leitores_3" name="check_leitores_3" value="1">
                <div class="item-content">
                    <div class="item-code">Credenciais</div>
                    <div class="item-desc">Credenciais Atribuídas: Total de cartões/PINs/biometrias emitidos vs. atribuídos a utilizadores.</div>
                    <div class="item-fields">
                        <span>Emitidas: <input type="text" name="valor_leitores_3" placeholder="0" style="width:60px;"></span>
                        <span>Atribuídas: <input type="text" name="obs_leitores_3" placeholder="0" style="width:60px;"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECÇÃO 4: FECHADURAS E MECANISMOS -->
        <div class="checklist-section">
            <h3>4. FECHADURAS E MECANISMOS 🔒</h3>
            <div class="checklist-item">
                <input type="checkbox" id="check_fechaduras_0" name="check_fechaduras_0" value="1">
                <div class="item-content">
                    <div class="item-code">Fecho</div>
                    <div class="item-desc">Fecho Eletromagnético/Elétrico: Teste de força e retenção da fechadura em cada porta.</div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_fechaduras_1" name="check_fechaduras_1" value="1">
                <div class="item-content">
                    <div class="item-code">Fail-Safe</div>
                    <div class="item-desc">Modo Fail-Safe / Fail-Secure: Comportamento em falta de energia conforme projeto (Fail-Safe = porta abre sem energia).</div>
                    <div class="item-fields">
                        <select name="obs_fechaduras_1">
                            <option value="">--</option>
                            <option value="Fail-Safe">Fail-Safe (abre sem energia)</option>
                            <option value="Fail-Secure">Fail-Secure (fecha sem energia)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_fechaduras_2" name="check_fechaduras_2" value="1">
                <div class="item-content">
                    <div class="item-code">REX</div>
                    <div class="item-desc">Botão de Saída (REX): Abertura confirmada em cada ponto de acesso (porta abre ao pressionar).</div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_fechaduras_3" name="check_fechaduras_3" value="1">
                <div class="item-content">
                    <div class="item-code">Fecho Porta</div>
                    <div class="item-desc">Fecho de Porta: Fecho de batente e/ou fecho automático operacional e regulado.</div>
                </div>
            </div>
        </div>

        <!-- SECÇÃO 5: SOFTWARE E CONFIGURAÇÃO -->
        <div class="checklist-section">
            <h3>5. SOFTWARE E CONFIGURAÇÃO 🖥</h3>
            <div class="checklist-item">
                <input type="checkbox" id="check_software_config_0" name="check_software_config_0" value="1">
                <div class="item-content">
                    <div class="item-code">Perfis</div>
                    <div class="item-desc">Perfis de Utilizador: Níveis de acesso definidos (Administrador, Operador, Utilizador).</div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_software_config_1" name="check_software_config_1" value="1">
                <div class="item-content">
                    <div class="item-code">Horários</div>
                    <div class="item-desc">Horários e Calendários: Perfis horários configurados conforme necessidades do cliente.</div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_software_config_2" name="check_software_config_2" value="1">
                <div class="item-content">
                    <div class="item-code">Alarmes</div>
                    <div class="item-desc">Alarmes de Porta: Porta Forçada (Forced Door) e Porta Mantida Aberta (Door Held Open) ativos.</div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_software_config_3" name="check_software_config_3" value="1">
                <div class="item-content">
                    <div class="item-code">Incêndio</div>
                    <div class="item-desc">Integração com Incêndio: Sinal de desbloqueio geral das portas em caso de deteção de incêndio.</div>
                    <div class="item-fields">
                        <select name="obs_software_config_3">
                            <option value="">--</option>
                            <option value="Integrado">Integrado e testado</option>
                            <option value="Nao integrado">Não integrado</option>
                            <option value="Nao aplicavel">Não aplicável</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_software_config_4" name="check_software_config_4" value="1">
                <div class="item-content">
                    <div class="item-code">Firmware</div>
                    <div class="item-desc">Firmware: Atualização do firmware da controladora e leitores para as últimas versões.</div>
                </div>
            </div>
        </div>

        <!-- SECÇÃO 6: RGPD -->
        <div class="checklist-section">
            <h3>6. CONFORMIDADE LEGAL E RGPD ⚠️</h3>
            <div class="checklist-item">
                <input type="checkbox" id="check_rgpd_0" name="check_rgpd_0" value="1">
                <div class="item-content">
                    <div class="item-code">Sinalética</div>
                    <div class="item-desc">Sinalética Obrigatória: Afixação de dísticos informativos visíveis nos acessos vigiados (indicação do responsável pelo tratamento de dados).</div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_rgpd_1" name="check_rgpd_1" value="1">
                <div class="item-content">
                    <div class="item-code">Retenção</div>
                    <div class="item-desc">Prazo de Retenção: Configuração para eliminação automática de registos de acesso conforme RGPD.</div>
                    <div class="item-fields">
                        <span>Dias configurados: <input type="text" name="valor_rgpd_1" placeholder="30" style="width:60px;"></span>
                    </div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_rgpd_2" name="check_rgpd_2" value="1">
                <div class="item-content">
                    <div class="item-code">Biometria</div>
                    <div class="item-desc">Dados Biométricos: Consentimento dos utilizadores e proteção dos dados biométricos armazenados conforme RGPD.</div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_rgpd_3" name="check_rgpd_3" value="1">
                <div class="item-content">
                    <div class="item-code">Acessos</div>
                    <div class="item-desc">Controlo de Acessos Lógico: Perfis de utilizador do sistema com passwords fortes e autenticação segura.</div>
                </div>
            </div>
        </div>

        <!-- SECÇÃO 7: TESTES -->
        <div class="checklist-section">
            <h3>7. TESTES DE SISTEMA E ENTREGA</h3>
            <div class="checklist-item">
                <input type="checkbox" id="check_testes_entrega_0" name="check_testes_entrega_0" value="1">
                <div class="item-content">
                    <div class="item-code">Teste Pontos</div>
                    <div class="item-desc">Teste Funcional: Cada ponto de acesso testado individualmente (abertura/fecho autorizado e negação).</div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_testes_entrega_1" name="check_testes_entrega_1" value="1">
                <div class="item-content">
                    <div class="item-code">Alarmes</div>
                    <div class="item-desc">Teste de Alarmes: Porta Forçada e Door Held Open geram evento/alarme no software de gestão.</div>
                    <div class="item-fields">
                        <select name="obs_testes_entrega_1">
                            <option value="">--</option>
                            <option value="OK">OK</option>
                            <option value="NOK">NOK</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_testes_entrega_2" name="check_testes_entrega_2" value="1">
                <div class="item-content">
                    <div class="item-code">Emergência</div>
                    <div class="item-desc">Teste de Emergência: Desbloqueio geral das portas via botão de incêndio / corte de energia.</div>
                    <div class="item-fields">
                        <select name="obs_testes_entrega_2">
                            <option value="">--</option>
                            <option value="OK">OK</option>
                            <option value="NOK">NOK</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_testes_entrega_3" name="check_testes_entrega_3" value="1">
                <div class="item-content">
                    <div class="item-code">Acesso Remoto</div>
                    <div class="item-desc">Acesso Remoto: Teste de gestão e monitorização via software cliente/app mobile.</div>
                    <div class="item-fields">
                        <select name="obs_testes_entrega_3">
                            <option value="">--</option>
                            <option value="OK">OK</option>
                            <option value="NOK">NOK</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_testes_entrega_4" name="check_testes_entrega_4" value="1">
                <div class="item-content">
                    <div class="item-code">Limpeza</div>
                    <div class="item-desc">Limpeza do Local: Remoção de resíduos e resguardos resultantes dos trabalhos.</div>
                </div>
            </div>
        </div>

        <!-- NOTAS E MATERIAL -->
        <div class="card" style="box-shadow:none;border:1px solid #eee;padding:20px;margin-bottom:20px;">
            <h3 style="margin-bottom:16px;font-size:1.1em;">📝 Observações / Anomalias Detetadas</h3>
            <div class="form-group">
                <label for="material_substituido">Material Substituído em Obra</label>
                <textarea id="material_substituido" name="material_substituido" class="form-control" placeholder="Descreva necessidades de substituição de material, fragilidades na infraestrutura..."></textarea>
            </div>
            <div class="form-group">
                <label for="notas">Notas / Observações</label>
                <textarea id="notas" name="notas" class="form-control" placeholder="Observações adicionais..."></textarea>
            </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;">
            <a href="dashboard.php" class="btn btn-outline" style="color:#333;border-color:#ccc;">Cancelar</a>
            <button type="submit" class="btn btn-success" style="background:#6a1b9a;">Guardar Relatório — Controlo Acessos</button>
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
    var dataField = document.getElementById('data');
    if (dataField && !dataField.value) {
        dataField.value = new Date().toISOString().split('T')[0];
    }
    var horaInicio = document.getElementById('hora_inicio');
    if (horaInicio && !horaInicio.value) {
        var now = new Date();
        horaInicio.value = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
    }
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
