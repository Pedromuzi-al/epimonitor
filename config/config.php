<?php
// =====================================================
//  EpiMonitor — Configuração do Banco de Dados
//  api/config.php
// =====================================================

// ─── Sessão e CSRF ──────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── Credenciais ───────────────────────────────────
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'epidemio_monitor');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// ─── Headers padrão para API REST ──────────────────
function setApiHeaders(): void {
    header('Content-Type: application/json; charset=utf-8');
    $origens_permitidas = [
        'http://localhost',
        'http://localhost:8080',
        'http://127.0.0.1',
        'http://127.0.0.1:8080',
        // Adicione aqui outros domínios permitidos
    ];
    $origem = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origem, $origens_permitidas, true)) {
        header("Access-Control-Allow-Origin: $origem");
    }
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

// ─── CSRF Protection ────────────────────────────────
function generateCsrfToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(string $token): bool {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// ─── Rate Limiting ───────────────────────────────
function checkRateLimit(string $ip, int $limit = 10, int $window = 60): bool {
    $key = "rl_{$ip}_{$window}";
    
    // Tenta usar APCu se disponível, senão usa sessão
    if (extension_loaded('apcu')) {
        $count = apcu_fetch($key) ?: 0;
        if ($count >= $limit) {
            return false;
        }
        apcu_store($key, $count + 1, $window);
    } else {
        // Fallback para sessão
        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = [];
        }
        if (!isset($_SESSION['rate_limit'][$key])) {
            $_SESSION['rate_limit'][$key] = ['count' => 0, 'time' => time()];
        }
        
        $now = time();
        $data = $_SESSION['rate_limit'][$key];
        
        if ($now - $data['time'] > $window) {
            $_SESSION['rate_limit'][$key] = ['count' => 1, 'time' => $now];
        } else {
            if ($data['count'] >= $limit) {
                return false;
            }
            $_SESSION['rate_limit'][$key]['count']++;
        }
    }
    
    return true;
}
