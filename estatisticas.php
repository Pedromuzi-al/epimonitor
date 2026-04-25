<?php
// =====================================================
//  EpiMonitor — Estatísticas
//  api/estatisticas.php  |  Método: GET
//
//  Query params:
//    ?bairro_id=1        (opcional) filtra por bairro
//    ?periodo=7          (opcional) dias anteriores, padrão = 7
// =====================================================

require_once __DIR__ . '/config.php';
setApiHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    resposta(false, null, 'Método não permitido. Use GET.', 405);
}

$db      = getDB();
$bairroId = isset($_GET['bairro_id']) ? (int) $_GET['bairro_id'] : null;
$periodo  = isset($_GET['periodo'])   ? max(1, min((int) $_GET['periodo'], 90)) : 7;

// ─── Total de registros por bairro ─────────────────
$sqlResumo = '
    SELECT
        b.id,
        b.nome                       AS bairro,
        COUNT(r.id)                  AS total_registros,
        ROUND(AVG(r.dias_sintomas), 1) AS media_dias,
        MAX(r.criado_em)             AS ultimo_registro
    FROM bairros b
    LEFT JOIN registros r
        ON r.bairro_id = b.id
        AND r.criado_em >= NOW() - INTERVAL :periodo DAY
    GROUP BY b.id, b.nome
    ORDER BY total_registros DESC
';
$stmtResumo = $db->prepare($sqlResumo);
$stmtResumo->execute([':periodo' => $periodo]);
$resumoBairros = $stmtResumo->fetchAll();

// ─── Contagem de sintomas geral ou por bairro ──────
$paramsSintomas = [':periodo' => $periodo];
$whereBairro    = '';

if ($bairroId) {
    $whereBairro = 'AND r.bairro_id = :bairro_id';
    $paramsSintomas[':bairro_id'] = $bairroId;
}

$sqlSintomas = "
    SELECT
        rs.sintoma,
        COUNT(*) AS quantidade
    FROM registro_sintomas rs
    JOIN registros r ON r.id = rs.registro_id
    WHERE r.criado_em >= NOW() - INTERVAL :periodo DAY
    $whereBairro
    GROUP BY rs.sintoma
    ORDER BY quantidade DESC
";
$stmtSintomas = $db->prepare($sqlSintomas);
$stmtSintomas->execute($paramsSintomas);
$contagemSintomas = $stmtSintomas->fetchAll();

// ─── Evolução diária (últimos N dias) ──────────────
$sqlEvolucao = "
    SELECT
        DATE(r.criado_em)  AS dia,
        COUNT(r.id)        AS registros
    FROM registros r
    WHERE r.criado_em >= NOW() - INTERVAL :periodo DAY
    $whereBairro
    GROUP BY DATE(r.criado_em)
    ORDER BY dia ASC
";
$stmtEvolucao = $db->prepare($sqlEvolucao);
$stmtEvolucao->execute($paramsSintomas);
$evolucaoDiaria = $stmtEvolucao->fetchAll();

// ─── Totais gerais ──────────────────────────────────
$sqlTotais = '
    SELECT
        COUNT(DISTINCT r.id)   AS total_registros,
        COUNT(DISTINCT r.bairro_id) AS bairros_ativos,
        (SELECT COUNT(*) FROM alertas WHERE ativo = 1) AS alertas_ativos
    FROM registros r
    WHERE r.criado_em >= NOW() - INTERVAL :periodo DAY
';
$stmtTotais = $db->prepare($sqlTotais);
$stmtTotais->execute([':periodo' => $periodo]);
$totais = $stmtTotais->fetch();

resposta(true, [
    'periodo_dias'    => $periodo,
    'totais'          => $totais,
    'resumo_bairros'  => $resumoBairros,
    'sintomas'        => $contagemSintomas,
    'evolucao_diaria' => $evolucaoDiaria,
], 'Estatísticas carregadas.');
