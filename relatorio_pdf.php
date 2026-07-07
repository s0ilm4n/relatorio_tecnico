<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

use Dompdf\Dompdf;
use Dompdf\Options;

$id = $_GET['id'] ?? 0;
if (!$id) { die('ID inválido.'); }

$db = getDB();
$stmt = $db->prepare("SELECT r.*, c.nome as cliente_nome, c.morada as cliente_morada, c.telefone as cliente_telefone, c.email as cliente_email, c.nif as cliente_nif, u.nome as tecnico_nome FROM relatorios r JOIN clientes c ON r.cliente_id = c.id JOIN users u ON r.user_id = u.id WHERE r.id = ?");
$stmt->execute([$id]);
$r = $stmt->fetch();

if (!$r) { die('Relatório não encontrado.'); }

$itens = $db->prepare("SELECT * FROM checklist_itens WHERE relatorio_id = ? ORDER BY id");
$itens->execute([$id]);
$todos_itens = $itens->fetchAll();

$secoes = [];
foreach ($todos_itens as $item) {
    $secoes[$item['secao']][] = $item;
}

$titulos_secoes = [
    'inspecao_visual' => '1. INSPEÇÃO VISUAL & MECÂNICA',
    'ensaios_electricos' => '2. ENSAIOS ELÉCTRICOS & BATERIAS',
    'testes_funcionais' => '3. TESTES FUNCIONAIS (ZONAS & SABOTAGEM)',
    'dispositivos_aviso' => '4. DISPOSITIVOS DE AVISO & COMUNICAÇÃO (CRA)',
    'encerramento' => '5. ENCERRAMENTO DE OBRA & LEGAL',
];

// Build HTML
function buildChecklistHTML($secoes, $titulos_secoes) {
    $html = '';
    foreach ($secoes as $secao_nome => $itens_secao) {
        $titulo = $titulos_secoes[$secao_nome] ?? $secao_nome;
        $html .= '<div class="checklist-section">';
        $html .= '<h3>' . $titulo . '</h3>';
        foreach ($itens_secao as $item) {
            $opacity = $item['verificado'] ? '' : ' style="opacity:0.5;"';
            $html .= '<div class="checklist-item"' . $opacity . '>';
            $html .= '<div class="check-box">' . ($item['verificado'] ? '✓' : '☐') . '</div>';
            $html .= '<div class="item-content">';
            $html .= '<strong>' . htmlspecialchars($item['item_codigo']) . '</strong> — ' . htmlspecialchars($item['item_descricao']);
            if ($item['valor_medido']) {
                $html .= '<div class="item-valor"><em>Valor:</em> ' . htmlspecialchars($item['valor_medido']) . '</div>';
            }
            if ($item['observacao']) {
                $html .= '<div class="item-obs"><em>Obs:</em> ' . htmlspecialchars($item['observacao']) . '</div>';
            }
            $html .= '</div></div>';
        }
        $html .= '</div>';
    }
    return $html;
}

$checklist_html = buildChecklistHTML($secoes, $titulos_secoes);

$html = <<<HTML
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 20mm 15mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1a1a2e; line-height: 1.5; }
    h1 { font-size: 16pt; text-align: center; color: #1a1a2e; margin-bottom: 5px; }
    h2 { font-size: 12pt; text-align: center; color: #555; margin-top: 0; margin-bottom: 15px; font-weight: normal; }
    .header-info { border: 1px solid #ccc; padding: 10px; margin-bottom: 15px; font-size: 9pt; }
    .header-info table { width: 100%; border-collapse: collapse; }
    .header-info td { padding: 3px 5px; vertical-align: top; }
    .header-info .label { font-weight: bold; width: 100px; }
    .checklist-section { margin-bottom: 12px; page-break-inside: avoid; }
    .checklist-section h3 { background: #1a1a2e; color: #fff; padding: 6px 10px; font-size: 10pt; margin: 0; }
    .checklist-item { display: flex; padding: 4px 8px; border-bottom: 1px solid #eee; gap: 6px; align-items: flex-start; }
    .check-box { font-size: 12pt; width: 18px; text-align: center; flex-shrink: 0; }
    .item-content { flex: 1; font-size: 9pt; }
    .item-valor, .item-obs { font-size: 8.5pt; color: #555; margin-top: 2px; }
    .notas-box { border: 1px solid #ccc; padding: 10px; margin-top: 10px; font-size: 9pt; }
    .assinaturas { margin-top: 25px; display: flex; justify-content: space-between; }
    .assinatura-box { width: 45%; border-top: 1px solid #333; padding-top: 5px; text-align: center; font-size: 9pt; }
    .footer { margin-top: 15px; text-align: center; font-size: 8pt; color: #888; }
    .badge { display: inline-block; background: #1a1a2e; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 8pt; }
</style>
</head>
<body>
    <h1>RELATÓRIO TÉCNICO</h1>
    <h2>Alarmes & CCTV — EN 50131</h2>

    <div class="header-info">
        <table>
            <tr><td class="label">Cliente:</td><td>{$r['cliente_nome']}</td><td class="label">Data:</td><td>{$r['data']}</td></tr>
            <tr><td class="label">Morada:</td><td>{$r['cliente_morada']}</td><td class="label">NIF:</td><td>{$r['cliente_nif']}</td></tr>
            <tr><td class="label">Técnico:</td><td>{$r['tecnico_nome']}</td><td class="label">Tipo:</td><td>{$r['tipo']}</td></tr>
            <tr><td class="label">Central:</td><td>{$r['central_modelo']}</td><td class="label">Grau:</td><td>{$r['grau_sistema']}</td></tr>
        </table>
    </div>

    $checklist_html

HTML;

if ($r['material_substituido']) {
    $mat = nl2br(htmlspecialchars($r['material_substituido']));
    $html .= "<div class=\"notas-box\"><strong>Material Substituído:</strong><br>$mat</div>";
}

if ($r['notas']) {
    $notas = nl2br(htmlspecialchars($r['notas']));
    $html .= "<div class=\"notas-box\"><strong>Observações:</strong><br>$notas</div>";
}

$html .= <<<HTML
    <div class="assinaturas">
        <div class="assinatura-box">O Técnico<br>{$r['tecnico_nome']}</div>
        <div class="assinatura-box">O Cliente<br>{$r['cliente_nome']}</div>
    </div>
    <div class="footer">Documento gerado em {$r['created_at']} — Norma EN 50131</div>
</body>
</html>
HTML;

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Relatorio_Tecnico_' . $r['id'] . '_' . date('Ymd', strtotime($r['data'])) . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
