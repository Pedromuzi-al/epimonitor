<?php
// =====================================================
//  EpiMonitor — Alertas Ativos
//  api/alertas.php  |  Método: GET
// =====================================================

require_once __DIR__ . '/config.php';
setApiHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    resposta(false, null, 'Método não permitido. Use GET.', 405);
}

$db = getDB();

$stmt = $db->query('
    SELECT
        a.id,
        b.nome      AS bairro,
        a.nivel,
        a.mensagem,
        a.ativo,
        a.criado_em,
        a.atualizado
    FROM alertas a
    JOIN bairros b ON b.id = a.bairro_id
    WHERE a.ativo = 1
    ORDER BY
        FIELD(a.nivel, "critico","alto","medio","baixo"),
        a.atualizado DESC
');

$alertas = $stmt->fetchAll();

resposta(true, $alertas, count($alertas) . ' alerta(s) ativo(s).');
