<?php
// =====================================================
//  EpiMonitor — Registrar Sintomas
//  api/registrar.php  |  Método: POST
//
//  Body JSON esperado:
//  {
//    "bairro_id": 1,
//    "sintomas": ["febre", "tosse"],
//    "dias_sintomas": 2
//  }
// =====================================================

require_once __DIR__ . '/config.php';
setApiHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    resposta(false, null, 'Método não permitido. Use POST.', 405);
}

// ─── Validação de CSRF Token ───────────────────────
$corpo = json_decode(file_get_contents('php://input'), true);

if (!$corpo || !is_array($corpo)) {
    resposta(false, null, 'Corpo da requisição inválido. Envie JSON.', 400);
}

$csrfToken = $corpo['csrf_token'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    resposta(false, null, 'Token CSRF inválido. Tente novamente.', 403);
}

// ─── Rate Limiting ─────────────────────────────────
$ipReal = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!checkRateLimit($ipReal, limit: 5, window: 60)) {
    resposta(false, null, 'Muitas requisições. Tente novamente em alguns minutos.', 429);
}

// ─── Leitura e validação dos dados ─────────────────

$bairroId    = isset($corpo['bairro_id'])    ? (int) $corpo['bairro_id']    : 0;
$sintomas    = isset($corpo['sintomas'])     ? (array) $corpo['sintomas']   : [];
$diasSintomas = isset($corpo['dias_sintomas']) ? (int) $corpo['dias_sintomas'] : 0;

// ─── Validações ────────────────────────────────────
if ($bairroId <= 0) {
    resposta(false, null, 'Campo "bairro_id" é obrigatório e deve ser um número válido.', 422);
}

if (empty($sintomas)) {
    resposta(false, null, 'Selecione ao menos um sintoma.', 422);
}

if ($diasSintomas < 1 || $diasSintomas > 60) {
    resposta(false, null, 'O campo "dias_sintomas" deve estar entre 1 e 60.', 422);
}

// Filtra apenas sintomas válidos para evitar injeção de dados
$sintomasValidos = array_filter($sintomas, fn($s) => in_array($s, SINTOMAS_VALIDOS, true));

if (empty($sintomasValidos)) {
    resposta(false, null, 'Nenhum sintoma reconhecido foi enviado.', 422);
}

// ─── Verifica se o bairro existe ───────────────────
$db = getDB();

$stmtBairro = $db->prepare('SELECT id FROM bairros WHERE id = ?');
$stmtBairro->execute([$bairroId]);

if (!$stmtBairro->fetch()) {
    resposta(false, null, 'Bairro não encontrado.', 404);
}

// ─── Anonimização do IP ─────────────────────────────
$ipHash = hash('sha256', $ipReal . date('Y-m-d')); // Muda a cada dia (privacidade extra)

// ─── Inserção no banco ─────────────────────────────
try {
    $db->beginTransaction();

    // Insere o registro principal
    $stmtReg = $db->prepare(
        'INSERT INTO registros (bairro_id, dias_sintomas, ip_hash) VALUES (?, ?, ?)'
    );
    $stmtReg->execute([$bairroId, $diasSintomas, $ipHash]);
    $registroId = (int) $db->lastInsertId();

    // Insere cada sintoma
    $stmtSint = $db->prepare(
        'INSERT INTO registro_sintomas (registro_id, sintoma) VALUES (?, ?)'
    );
    foreach ($sintomasValidos as $sintoma) {
        $stmtSint->execute([$registroId, $sintoma]);
    }


    // ─── Verifica limiar de alerta para cada sintoma ─────────────
    $limiares = [
        'febre' => 5,
        'tosse' => 7,
        'diarreia' => 3,
        // Adicione outros sintomas e limiares conforme necessário
    ];
    foreach ($limiares as $sintomaAlerta => $limite) {
        $stmtContagem = $db->prepare('
            SELECT COUNT(*) AS total
            FROM registro_sintomas rs
            JOIN registros r ON r.id = rs.registro_id
            WHERE r.bairro_id = ?
              AND rs.sintoma = ?
              AND r.criado_em >= NOW() - INTERVAL 24 HOUR
        ');
        $stmtContagem->execute([$bairroId, $sintomaAlerta]);
        $totalSintoma = (int) $stmtContagem->fetchColumn();
        if ($totalSintoma >= $limite) {
            $stmtAlertaExiste = $db->prepare('SELECT id FROM alertas WHERE bairro_id = ? AND ativo = 1 AND mensagem LIKE ? LIMIT 1');
            $stmtAlertaExiste->execute([$bairroId, "%$sintomaAlerta%"]);
            if (!$stmtAlertaExiste->fetch()) {
                $nomeBairro = $db->query("SELECT nome FROM bairros WHERE id = $bairroId")->fetchColumn();
                $msg = "$nomeBairro: $totalSintoma registros de $sintomaAlerta nas últimas 24h. Possível surto em desenvolvimento.";
                $db->prepare('INSERT INTO alertas (bairro_id, nivel, mensagem) VALUES (?, "alto", ?)')
                   ->execute([$bairroId, $msg]);
            }
        }
    }

    $db->commit();

    resposta(true, [
        'registro_id' => $registroId,
        'sintomas'    => array_values($sintomasValidos),
        'alerta'      => $totalFebre >= 5,
    ], 'Sintomas registrados com sucesso. Obrigado por contribuir!', 201);

} catch (PDOException $e) {
    $db->rollBack();
    resposta(false, null, 'Erro ao salvar os dados. Tente novamente.', 500);
}
