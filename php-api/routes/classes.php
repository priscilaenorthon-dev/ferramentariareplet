<?php
declare(strict_types=1);

global $method, $path, $body;

if ($path === '/api/classes' && $method === 'GET') {
    $user = requireAuth();
    requireRole($user, ['operator', 'admin']);
    withDb(function (PDO $pdo) {
        $rows = $pdo->query('SELECT * FROM tool_classes ORDER BY name ASC')->fetchAll() ?: [];
        jsonResponse(200, $rows);
    });
}

if ($path === '/api/classes' && $method === 'POST') {
    $user = requireAuth();
    requireRole($user, ['operator', 'admin']);
    $name = trim((string)($body['name'] ?? ''));
    if ($name === '') {
        jsonResponse(400, ['message' => 'Nome obrigatorio']);
    }
    withDb(function (PDO $pdo) use ($body, $user) {
        $stmt = $pdo->prepare('INSERT INTO tool_classes (id, name, description, created_at) VALUES (UUID(), ?, ?, NOW())');
        $stmt->execute([$body['name'], $body['description'] ?? null]);
        $id = $pdo->lastInsertId() ?: $pdo->query('SELECT id FROM tool_classes WHERE name = ' . $pdo->quote($body['name']))->fetchColumn();
        recordAudit($pdo, [
            'user_id' => $user['id'],
            'target_type' => 'toolClass',
            'target_id' => $id,
            'action' => 'create',
            'description' => 'Usuario ' . formatName($user) . ' criou a classe ' . $body['name'],
            'after_data' => json_encode($body),
        ]);
        $created = $pdo->prepare('SELECT * FROM tool_classes WHERE id = ?');
        $created->execute([$id]);
        jsonResponse(200, $created->fetch());
    });
}

if (preg_match('#^/api/classes/([\\w-]+)$#', $path, $m) && $method === 'PATCH') {
    $user = requireAuth();
    requireRole($user, ['operator', 'admin']);
    $id = $m[1];
    withDb(function (PDO $pdo) use ($id, $body, $user) {
        $existing = $pdo->prepare('SELECT * FROM tool_classes WHERE id = ?');
        $existing->execute([$id]);
        $prev = $existing->fetch();
        if (!$prev) {
            jsonResponse(404, ['message' => 'Classe nao encontrada']);
        }
        $stmt = $pdo->prepare('UPDATE tool_classes SET name = :name, description = :description WHERE id = :id');
        $stmt->execute([
            ':name' => $body['name'] ?? $prev['name'],
            ':description' => $body['description'] ?? $prev['description'],
            ':id' => $id,
        ]);
        $updated = $pdo->prepare('SELECT * FROM tool_classes WHERE id = ?');
        $updated->execute([$id]);
        $after = $updated->fetch();
        recordAudit($pdo, [
            'user_id' => $user['id'],
            'target_type' => 'toolClass',
            'target_id' => $id,
            'action' => 'update',
            'description' => 'Usuario ' . formatName($user) . ' atualizou a classe ' . ($after['name'] ?? ''),
            'before_data' => json_encode($prev),
            'after_data' => json_encode($after),
        ]);
        jsonResponse(200, $after);
    });
}

if (preg_match('#^/api/classes/([\\w-]+)$#', $path, $m) && $method === 'DELETE') {
    $user = requireAuth();
    requireRole($user, ['operator', 'admin']);
    $id = $m[1];
    withDb(function (PDO $pdo) use ($id, $user) {
        $existing = $pdo->prepare('SELECT * FROM tool_classes WHERE id = ?');
        $existing->execute([$id]);
        $prev = $existing->fetch();
        if (!$prev) {
            jsonResponse(404, ['message' => 'Classe nao encontrada']);
        }
        $pdo->prepare('DELETE FROM tool_classes WHERE id = ?')->execute([$id]);
        recordAudit($pdo, [
            'user_id' => $user['id'],
            'target_type' => 'toolClass',
            'target_id' => $id,
            'action' => 'delete',
            'description' => 'Usuario ' . formatName($user) . ' removeu a classe ' . $prev['name'],
            'before_data' => json_encode($prev),
        ]);
        jsonResponse(200, ['success' => true]);
    });
}
