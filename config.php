<?php
// =====================================================
//  EpiMonitor — Configuração do Banco de Dados
//  api/config.php
// =====================================================

// ─── Credenciais ───────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'epidemio_monitor');
define('DB_USER', 'root');       // Altere para seu usuário
define('DB_PASS', '');           // Altere para sua senha
define('DB_CHARSET', 'utf8mb4');

// ─── Headers padrão para API REST ──────────────────
function setApiHeaders(): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ─── Conexão PDO ───────────────────────────────────
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['erro' => 'Falha na conexão com o banco de dados.']);
            exit;
        }
    }

    return $pdo;
}

// ─── Resposta JSON padronizada ──────────────────────
function resposta(bool $sucesso, mixed $dados = null, string $mensagem = '', int $codigo = 200): never {
    http_response_code($codigo);
    echo json_encode([
        'sucesso'  => $sucesso,
        'mensagem' => $mensagem,
        'dados'    => $dados,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ─── Sintomas válidos ───────────────────────────────
const SINTOMAS_VALIDOS = [
    'febre', 'tosse', 'dor_corpo', 'dor_cabeca',
    'diarreia', 'nausea', 'fadiga', 'manchas'
];
