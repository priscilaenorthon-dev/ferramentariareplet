<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';
session_name($config['session_name']);
session_start();
header('Content-Type: application/json');

/** @var PDO $pdo */
$pdo = require __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$rawPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Normaliza o caminho removendo o prefixo da pasta (ex.: /replit/php-api) para que as rotas fiquem em /api/...
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$path = $rawPath;
if ($basePath && str_starts_with($rawPath, $basePath)) {
    $path = substr($rawPath, strlen($basePath));
    if ($path === '') {
        $path = '/';
    }
}
$query = $_GET;
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    $body = [];
}

function jsonResponse(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function withDb(callable $fn)
{
    global $pdo;
    return $fn($pdo);
}

function fetchUserById(PDO $pdo, string $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function fetchUserByUsername(PDO $pdo, string $username): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function fetchUserByQr(PDO $pdo, string $qr): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE qr_code = ?');
    $stmt->execute([$qr]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function sanitizeUser(array $user): array
{
    unset($user['password']);
    return $user;
}

function currentUser(): ?array
{
    return isset($_SESSION['user_id'])
        ? withDb(fn(PDO $pdo) => fetchUserById($pdo, $_SESSION['user_id']))
        : null;
}

function requireAuth(): array
{
    $user = currentUser();
    if (!$user) {
        jsonResponse(401, ['message' => 'Nao autorizado']);
    }
    return $user;
}

function requireRole(array $user, array $roles): void
{
    if (!in_array($user['role'] ?? '', $roles, true)) {
        jsonResponse(403, ['message' => 'Permissao negada']);
    }
}

function recordAudit(PDO $pdo, array $entry): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO audit_logs (id, user_id, target_type, target_id, action, description, before_data, after_data, metadata, created_at)
         VALUES (UUID(), :user_id, :target_type, :target_id, :action, :description, :before_data, :after_data, :metadata, NOW())'
    );
    $stmt->execute([
        ':user_id' => $entry['user_id'] ?? null,
        ':target_type' => $entry['target_type'],
        ':target_id' => $entry['target_id'],
        ':action' => $entry['action'],
        ':description' => $entry['description'],
        ':before_data' => $entry['before_data'] ?? null,
        ':after_data' => $entry['after_data'] ?? null,
        ':metadata' => $entry['metadata'] ?? null,
    ]);
}

function formatName(array $user = null): string
{
    if (!$user) {
        return 'Usuario desconhecido';
    }
    $parts = array_filter([trim((string)($user['first_name'] ?? '')), trim((string)($user['last_name'] ?? ''))]);
    if ($parts) {
        return implode(' ', $parts);
    }
    return $user['username'] ?? 'Usuario';
}
