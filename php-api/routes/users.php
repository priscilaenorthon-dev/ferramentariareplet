<?php
declare(strict_types=1);

global $method, $path, $body;

if ($path === '/api/users' && $method === 'GET') {
    $user = requireAuth();
    requireRole($user, ['admin']);
    withDb(function (PDO $pdo) {
        $rows = $pdo->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll() ?: [];
        $rows = array_map('sanitizeUser', $rows);
        jsonResponse(200, $rows);
    });
}

if (preg_match('#^/api/users/([\\w-]+)$#', $path, $m) && $method === 'PATCH') {
    $user = requireAuth();
    requireRole($user, ['admin']);
    $id = $m[1];
    withDb(function (PDO $pdo) use ($id, $body, $user) {
        $existing = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $existing->execute([$id]);
        $prev = $existing->fetch();
        if (!$prev) {
            jsonResponse(404, ['message' => 'Usuario nao encontrado']);
        }
        $stmt = $pdo->prepare(
            'UPDATE users SET first_name = :first, last_name = :last, email = :email, department = :department, role = :role, matriculation = :matriculation, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            ':first' => $body['firstName'] ?? $prev['first_name'],
            ':last' => $body['lastName'] ?? $prev['last_name'],
            ':email' => $body['email'] ?? $prev['email'],
            ':department' => $body['department'] ?? $prev['department'],
            ':role' => $body['role'] ?? $prev['role'],
            ':matriculation' => $body['matriculation'] ?? $prev['matriculation'],
            ':id' => $id,
        ]);
        $updated = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $updated->execute([$id]);
        $after = $updated->fetch();
        recordAudit($pdo, [
            'user_id' => $user['id'],
            'target_type' => 'user',
            'target_id' => $id,
            'action' => 'update',
            'description' => 'Usuario ' . formatName($user) . ' atualizou o cadastro de ' . formatName($after),
            'before_data' => json_encode($prev),
            'after_data' => json_encode($after),
        ]);
        jsonResponse(200, sanitizeUser($after));
    });
}

if (preg_match('#^/api/users/([\\w-]+)$#', $path, $m) && $method === 'DELETE') {
    $user = requireAuth();
    requireRole($user, ['admin']);
    $id = $m[1];
    withDb(function (PDO $pdo) use ($id, $user) {
        $existing = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $existing->execute([$id]);
        $prev = $existing->fetch();
        if (!$prev) {
            jsonResponse(404, ['message' => 'Usuario nao encontrado']);
        }
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        recordAudit($pdo, [
            'user_id' => $user['id'],
            'target_type' => 'user',
            'target_id' => $id,
            'action' => 'delete',
            'description' => 'Usuario ' . formatName($user) . ' removeu o usuario ' . formatName($prev),
            'before_data' => json_encode($prev),
        ]);
        jsonResponse(200, ['success' => true]);
    });
}
