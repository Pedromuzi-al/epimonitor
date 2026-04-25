<?php
// =====================================================
//  EpiMonitor — Listar Bairros
//  api/bairros.php  |  Método: GET
// =====================================================

require_once __DIR__ . '/config.php';
setApiHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    resposta(false, null, 'Método não permitido. Use GET.', 405);
}

$db = getDB();

$stmt = $db->query('SELECT id, nome, cidade, estado FROM bairros ORDER BY nome ASC');
$bairros = $stmt->fetchAll();

resposta(true, $bairros, 'Bairros carregados com sucesso.');
