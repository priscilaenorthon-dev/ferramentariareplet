<?php
declare(strict_types=1);

global $method, $path, $body;

if ($method === 'POST' && $path === '/api/auth/login') {
    $username = trim((string)($body['username'] ?? ''));
    $password = (string)($body['password'] ?? '');

    if ($username === '' || $password === '') {
        jsonResponse(400, ['message' => 'Credenciais invalidas']);
    }

    withDb(function (PDO $pdo) use ($username, $password) {
        $user = fetchUserByUsername($pdo, $username);
        if (!$user || !password_verify($password, $user['password'])) {
            jsonResponse(401, ['message' => 'Credenciais invalidas']);
        }
        $_SESSION['user_id'] = $user['id'];
        jsonResponse(200, sanitizeUser($user));
    });
}

if ($method === 'POST' && $path === '/api/auth/logout') {
    session_destroy();
    jsonResponse(200, ['message' => 'Logout ok']);
}

if ($method === 'GET' && $path === '/api/auth/user') {
    $user = requireAuth();
    jsonResponse(200, sanitizeUser($user));
}

if ($method === 'POST' && $path === '/api/auth/validate-qrcode') {
    $qrCode = trim((string)($body['qrCode'] ?? ''));
    if ($qrCode === '') {
        jsonResponse(400, ['message' => 'QR Code obrigatorio']);
    }
    withDb(function (PDO $pdo) use ($qrCode) {
        $user = fetchUserByQr($pdo, $qrCode);
        if (!$user) {
            jsonResponse(404, ['message' => 'QR Code invalido']);
        }
        jsonResponse(200, sanitizeUser($user));
    });
}

if ($method === 'POST' && $path === '/api/auth/register') {
    $actor = requireAuth();
    requireRole($actor, ['admin']);

    $required = ['username', 'password', 'firstName', 'lastName', 'role'];
    foreach ($required as $field) {
        if (!isset($body[$field]) || trim((string)$body[$field]) === '') {
            jsonResponse(400, ['message' => "Campo {$field} obrigatorio"]);
        }
    }

    withDb(function (PDO $pdo) use ($body, $actor) {
        $exists = fetchUserByUsername($pdo, $body['username']);
        if ($exists) {
            jsonResponse(400, ['message' => 'Username ja existe']);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO users (id, username, password, first_name, last_name, email, matriculation, department, role, qr_code, created_at, updated_at)
             VALUES (UUID(), :username, :password, :first_name, :last_name, :email, :matriculation, :department, :role, :qr_code, NOW(), NOW())'
        );

        $qrCode = bin2hex(random_bytes(8));
        $stmt->execute([
            ':username' => $body['username'],
            ':password' => password_hash($body['password'], PASSWORD_BCRYPT),
            ':first_name' => $body['firstName'],
            ':last_name' => $body['lastName'],
            ':email' => $body['email'] ?? null,
            ':matriculation' => $body['matriculation'] ?? null,
            ':department' => $body['department'] ?? null,
            ':role' => $body['role'],
            ':qr_code' => $qrCode,
        ]);

        $user = fetchUserByUsername($pdo, $body['username']);
        if ($user) {
            recordAudit($pdo, [
                'user_id' => $actor['id'],
                'target_type' => 'user',
                'target_id' => $user['id'],
                'action' => 'create',
                'description' => 'Usuario ' . formatName($actor) . ' criou o usuario ' . formatName($user),
                'after_data' => json_encode($user),
            ]);
            jsonResponse(200, sanitizeUser($user));
        }
        jsonResponse(500, ['message' => 'Falha ao criar usuario']);
    });
}
