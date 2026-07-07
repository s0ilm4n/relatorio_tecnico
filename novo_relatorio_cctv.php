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

        $stmt = $db->prepare("INSERT INTO relatorios (user_id, cliente_id, tipo, tipo_obra, data, hora_inicio, hora_fim, central_modelo, grau_sistema, notas, material_substituido) VALUES (?, ?, 'cctv', ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $cliente_id,
            $_POST['tipo_obra'] ?? 'instalacao',
            $_POST['data'],
            $_POST['hora_inicio'],
            $_POST['hora_fim'],
            ($_POST['nvr_marca'] ?? '') . ' | S/N: ' . ($_POST['nvr_sn'] ?? ''),
            $_POST['armazenamento_tb'] ?? '',
            $_POST['notas'] ?? '',
            $_POST['material_substituido'] ?? ''
        ]);
        $relatorio_id = $db->lastInsertId();

        $secoes = [
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
    <h2>📹 Novo Relatório CCTV</h2>
    <p style="color:#888;font-size:0.9em;margin-bottom:16px;">Checklist de Instalação/Manutenção de Sistemas de Videovigilância</p>

    <?php if (isset($erro)): ?>
        <div class="alert alert-danger"><?= e($erro) ?></div>
    <?php endif; ?>

    <form method="POST" id="form-cctv">
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
                    <label for="tipo_obra">Tipo de Obra</label>
                    <select id="tipo_obra" name="tipo_obra" class="form-control">
                        <option value="instalacao" selected>Instalação</option>
                        <option value="manutencao">Manutenção</option>
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
                    <div class="item-code">Gravador</div>
                    <div class="item-desc">Gravador (NVR/DVR): Marca/Modelo e Nº de Série</div>
                    <div class="item-fields">
                        <span>Marca/Modelo: <input type="text" name="nvr_marca" placeholder="Ex: Hikvision DS-7608"></span>
                        <span>S/N: <input type="text" name="nvr_sn" placeholder="Nº de série"></span>
                    </div>
                </div>
            </div>
            <div class="checklist-item">
                <div class="item-content">
                    <div class="item-code">Armazenamento</div>
                    <div class="item-desc">Capacidade de Armazenamento</div>
                    <div class="item-fields">
                        <span><input type="text" name="armazenamento_tb" placeholder="Ex: 4 TB" style="width:100px;"> TB</span>
                    </div>
                </div>
            </div>
            <div class="checklist-item">
                <div class="item-content">
                    <div class="item-code">Câmaras</div>
                    <div class="item-desc">Nº Total de Câmaras</div>
                    <div class="item-fields">
                        <span>IP: <input type="text" name="camaras_ip" placeholder="0" style="width:60px;"></span>
                        <span>Analógicas: <input type="text" name="camaras_analog" placeholder="0" style="width:60px;"></span>
                    </div>
                </div>
            </div>
            <div class="checklist-item">
                <div class="item-content">
                    <div class="item-code">Software</div>
                    <div class="item-desc">Software/App de Acesso</div>
                    <div class="item-fields">
                        <input type="text" name="software_app" placeholder="Ex: Hik-Connect, Dahua DMSS" style="width:300px;">
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
                    <div class="item-desc">Cablagem e Conexões: Verificação de cabos UTP/Coaxiais, fichas RJ45/BNC e isolamento contra humidades (caixas de derivação estanques).</div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_infraestrutura_1" name="check_infraestrutura_1" value="1">
                <div class="item-content">
                    <div class="item-code">PoE</div>
                    <div class="item-desc">Fontes de Alimentação / PoE: Medição das tensões de alimentação. Teste de carga nos switchs PoE.</div>
                    <div class="item-fields">
                        <span>Tensão: <input type="text" name="valor_infraestrutura_1" placeholder="Ex: 48V"></span>
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
                    <div class="item-desc">Fixações Mecânicas: Reaperto de suportes, braços e caixas de proteção das câmaras contra vibrações ou vandalismo.</div>
                </div>
            </div>
        </div>

        <!-- SECÇÃO 3: CÂMARAS -->
        <div class="checklist-section">
            <h3>3. UNIDADES DE CAPTURA (CÂMARAS) 📷</h3>
            <div class="checklist-item">
                <input type="checkbox" id="check_camaras_0" name="check_camaras_0" value="1">
                <div class="item-content">
                    <div class="item-code">Limpeza</div>
                    <div class="item-desc">Limpeza Ótica: Limpeza profunda das cúpulas (domos) e lentes com produtos antiestáticos.</div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_camaras_1" name="check_camaras_1" value="1">
                <div class="item-content">
                    <div class="item-code">Focagem</div>
                    <div class="item-desc">Focagem e Enquadramento: Ajuste do campo de visão (FOV), focagem (manual/motorizada) e verificação de pontos cegos.</div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_camaras_2" name="check_camaras_2" value="1">
                <div class="item-content">
                    <div class="item-code">Visão Noturna</div>
                    <div class="item-desc">Visão Noturna (IV): Teste dos iluminadores infravermelhos e ativação do filtro ICR em ambiente escuro.</div>
                    <div class="item-fields">
                        <select name="obs_camaras_2">
                            <option value="">--</option>
                            <option value="OK">OK</option>
                            <option value="NOK">NOK</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_camaras_3" name="check_camaras_3" value="1">
                <div class="item-content">
                    <div class="item-code">Config. Imagem</div>
                    <div class="item-desc">Configurações de Imagem: Ajuste de WDR, brilho e contraste para condições de luz adversas (ex: contra-luz em acessos).</div>
                </div>
            </div>
        </div>

        <!-- SECÇÃO 4: GRAVAÇÃO, PROCESSAMENTO E REDE -->
        <div class="checklist-section">
            <h3>4. GRAVAÇÃO, PROCESSAMENTO E REDE 🖥</h3>
            <div class="checklist-item">
                <input type="checkbox" id="check_gravacao_rede_0" name="check_gravacao_rede_0" value="1">
                <div class="item-content">
                    <div class="item-code">HDDs</div>
                    <div class="item-desc">Discos Rígidos (HDD): Verificação do estado de saúde (S.M.A.R.T.) e confirmação do ciclo de overwrite (sobreposição).</div>
                    <div class="item-fields">
                        <select name="obs_gravacao_rede_0">
                            <option value="">--</option>
                            <option value="OK">OK</option>
                            <option value="Substituir">Substituir</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_gravacao_rede_1" name="check_gravacao_rede_1" value="1">
                <div class="item-content">
                    <div class="item-code">Firmware</div>
                    <div class="item-desc">Firmware: Atualização do firmware do NVR/DVR e das câmaras para as últimas versões estáveis de segurança.</div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_gravacao_rede_2" name="check_gravacao_rede_2" value="1">
                <div class="item-content">
                    <div class="item-code">NTP</div>
                    <div class="item-desc">Sincronização Horária: Configuração e validação do relógio via servidor NTP (fundamental para validade legal das imagens).</div>
                    <div class="item-fields">
                        <select name="obs_gravacao_rede_2">
                            <option value="">--</option>
                            <option value="Sincronizado">Sincronizado</option>
                            <option value="Nao sincronizado">Não sincronizado</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_gravacao_rede_3" name="check_gravacao_rede_3" value="1">
                <div class="item-content">
                    <div class="item-code">Seg. Rede</div>
                    <div class="item-desc">Segurança de Rede: Alteração de passwords padrão, desativação de UPnP desnecessário e validação de regras de firewall.</div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_gravacao_rede_4" name="check_gravacao_rede_4" value="1">
                <div class="item-content">
                    <div class="item-code">Canais</div>
                    <div class="item-desc">Configuração de Canais: Verificação da resolução de gravação, bitrate e fps (frames por segundo) contratados.</div>
                </div>
            </div>
        </div>

        <!-- SECÇÃO 5: RGPD -->
        <div class="checklist-section">
            <h3>5. CONFORMIDADE LEGAL E RGPD ⚠️</h3>
            <div class="checklist-item">
                <input type="checkbox" id="check_rgpd_0" name="check_rgpd_0" value="1">
                <div class="item-content">
                    <div class="item-code">Sinalética</div>
                    <div class="item-desc">Sinalética Obrigatória: Afixação de dísticos informativos visíveis nos acessos às zonas vigiadas (com indicação do responsável pelo tratamento de dados).</div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_rgpd_1" name="check_rgpd_1" value="1">
                <div class="item-content">
                    <div class="item-code">Retenção</div>
                    <div class="item-desc">Prazo de Retenção: Configuração do sistema para eliminação automática das imagens decorridos 30 dias (salvo pedidos judiciais).</div>
                    <div class="item-fields">
                        <span>Dias configurados: <input type="text" name="valor_rgpd_1" placeholder="30" style="width:60px;"></span>
                    </div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_rgpd_2" name="check_rgpd_2" value="1">
                <div class="item-content">
                    <div class="item-code">Zonas</div>
                    <div class="item-desc">Zonas Privadas/Públicas: Validação das máscaras de privacidade (privacy masks) para garantir que o sistema não filma via pública ou propriedades vizinhas.</div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_rgpd_3" name="check_rgpd_3" value="1">
                <div class="item-content">
                    <div class="item-code">Acessos</div>
                    <div class="item-desc">Controlo de Acessos: Definição de perfis de utilizador estritos (Administrador vs. Operador) com passwords fortes.</div>
                </div>
            </div>
        </div>

        <!-- SECÇÃO 6: TESTES -->
        <div class="checklist-section">
            <h3>6. TESTES DE SISTEMA E ENTREGA</h3>
            <div class="checklist-item">
                <input type="checkbox" id="check_testes_entrega_0" name="check_testes_entrega_0" value="1">
                <div class="item-content">
                    <div class="item-code">Playback</div>
                    <div class="item-desc">Teste de Reprodução (Playback): Procura e exportação de um clip de vídeo de teste para uma pen USB.</div>
                    <div class="item-fields">
                        <select name="obs_testes_entrega_0">
                            <option value="">--</option>
                            <option value="OK">OK</option>
                            <option value="NOK">NOK</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="checklist-item">
                <input type="checkbox" id="check_testes_entrega_1" name="check_testes_entrega_1" value="1">
                <div class="item-content">
                    <div class="item-code">Acesso Remoto</div>
                    <div class="item-desc">Acesso Remoto: Teste de visualização em tempo real e notificações através da App mobile/Software cliente.</div>
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
            <button type="submit" class="btn btn-success">Guardar Relatório CCTV</button>
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
    // Disable value fields when checkbox unchecked
    document.querySelectorAll('.checklist-item input[type=\"checkbox\"]').forEach(function(cb) {
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
