<?php
declare(strict_types=1);

global $method, $path, $body;

if ($path === '/api/models' && $method === 'GET') {
    $user = requireAuth();
    requireRole($user, ['operator', 'admin']);
    withDb(function (PDO $pdo) {
        $rows = $pdo->query('SELECT * FROM tool_models ORDER BY name ASC')->fetchAll() ?: [];
        jsonResponse(200, $rows);
    });
}

if ($path === '/api/models' && $method === 'POST') {
    $user = requireAuth();
    requireRole($user, ['operator', 'admin']);
    $name = trim((string)($body['name'] ?? ''));
    if ($name === '') {
        jsonResponse(400, ['message' => 'Nome obrigatorio']);
    }
    withDb(function (PDO $pdo) use ($body, $user) {
        $stmt = $pdo->prepare(
            'INSERT INTO tool_models (id, name, requires_calibration, calibration_interval_days, created_at)
             VALUES (UUID(), :name, :requires, :interval, NOW())'
        );
        $stmt->execute([
            ':name' => $body['name'],
            ':requires' => !empty($body['requiresCalibration']) ? 1 : 0,
            ':interval' => $body['calibrationIntervalDays'] ?? null,
        ]);
        $id = $pdo->lastInsertId() ?: $pdo->query('SELECT id FROM tool_models WHERE name = ' . $pdo->quote($body['name']))->fetchColumn();
        $created = $pdo->prepare('SELECT * FROM tool_models WHERE id = ?');
        $created->execute([$id]);
        $model = $created->fetch();
        recordAudit($pdo, [
            'user_id' => $user['id'],
            'target_type' => 'toolModel',
            'target_id' => $id,
            'action' => 'create',
            'description' => 'Usuario ' . formatName($user) . ' criou o modelo ' . ($model['name'] ?? ''),
            'after_data' => json_encode($model),
        ]);
        jsonResponse(200, $model);
    });
}

if (preg_match('#^/api/models/([\\w-]+)$#', $path, $m) && $method === 'PATCH') {
    $user = requireAuth();
    requireRole($user, ['operator', 'admin']);
    $id = $m[1];
    withDb(function (PDO $pdo) use ($id, $body, $user) {
        $existing = $pdo->prepare('SELECT * FROM tool_models WHERE id = ?');
        $existing->execute([$id]);
        $prev = $existing->fetch();
        if (!$prev) {
            jsonResponse(404, ['message' => 'Modelo nao encontrado']);
        }
        $stmt = $pdo->prepare(
            'UPDATE tool_models SET name = :name, requires_calibration = :requires, calibration_interval_days = :interval WHERE id = :id'
        );
        $stmt->execute([
            ':name' => $body['name'] ?? $prev['name'],
            ':requires' => isset($body['requiresCalibration']) ? ($body['requiresCalibration'] ? 1 : 0) : $prev['requires_calibration'],
            ':interval' => $body['calibrationIntervalDays'] ?? $prev['calibration_interval_days'],
            ':id' => $id,
        ]);
        $updated = $pdo->prepare('SELECT * FROM tool_models WHERE id = ?');
        $updated->execute([$id]);
        $model = $updated->fetch();
        recordAudit($pdo, [
            'user_id' => $user['id'],
            'target_type' => 'toolModel',
            'target_id' => $id,
            'action' => 'update',
            'description' => 'Usuario ' . formatName($user) . ' atualizou o modelo ' . ($model['name'] ?? ''),
            'before_data' => json_encode($prev),
            'after_data' => json_encode($model),
        ]);
        jsonResponse(200, $model);
    });
}

if (preg_match('#^/api/models/([\\w-]+)$#', $path, $m) && $method === 'DELETE') {
    $user = requireAuth();
    requireRole($user, ['operator', 'admin']);
    $id = $m[1];
    withDb(function (PDO $pdo) use ($id, $user) {
        $existing = $pdo->prepare('SELECT * FROM tool_models WHERE id = ?');
        $existing->execute([$id]);
        $prev = $existing->fetch();
        if (!$prev) {
            jsonResponse(404, ['message' => 'Modelo nao encontrado']);
        }
        $pdo->prepare('DELETE FROM tool_models WHERE id = ?')->execute([$id]);
        recordAudit($pdo, [
            'user_id' => $user['id'],
            'target_type' => 'toolModel',
            'target_id' => $id,
            'action' => 'delete',
            'description' => 'Usuario ' . formatName($user) . ' removeu o modelo ' . $prev['name'],
            'before_data' => json_encode($prev),
        ]);
        jsonResponse(200, ['success' => true]);
    });
}
