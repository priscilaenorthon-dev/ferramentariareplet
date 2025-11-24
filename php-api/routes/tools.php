<?php
declare(strict_types=1);

global $method, $path, $body;

if ($path === '/api/tools' && $method === 'GET') {
    $user = requireAuth();
    requireRole($user, ['operator', 'admin']);
    withDb(function (PDO $pdo) {
        $rows = $pdo->query(
            'SELECT t.*,
                    c.name AS class_name,
                    m.name AS model_name,
                    m.requires_calibration,
                    m.calibration_interval_days
             FROM tools t
             LEFT JOIN tool_classes c ON t.class_id = c.id
             LEFT JOIN tool_models m ON t.model_id = m.id
             ORDER BY t.name ASC'
        )->fetchAll() ?: [];
        jsonResponse(200, $rows);
    });
}

if ($path === '/api/tools' && $method === 'POST') {
    $user = requireAuth();
    requireRole($user, ['operator', 'admin']);
    $name = trim((string)($body['name'] ?? ''));
    $code = trim((string)($body['code'] ?? ''));
    $quantity = (int)($body['quantity'] ?? 1);
    if ($name === '' || $code === '') {
        jsonResponse(400, ['message' => 'Nome e codigo sao obrigatorios']);
    }
    withDb(function (PDO $pdo) use ($body, $user, $name, $code, $quantity) {
        $modelId = $body['modelId'] ?? null;
        $nextCalibration = null;
        if (!empty($body['lastCalibrationDate']) && $modelId) {
            $modelStmt = $pdo->prepare('SELECT * FROM tool_models WHERE id = ?');
            $modelStmt->execute([$modelId]);
            $model = $modelStmt->fetch();
            if ($model && $model['requires_calibration'] && $model['calibration_interval_days']) {
                $date = new DateTime($body['lastCalibrationDate']);
                $date->modify('+' . (int)$model['calibration_interval_days'] . ' days');
                $nextCalibration = $date->format('Y-m-d H:i:s');
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO tools (id, name, code, class_id, model_id, quantity, available_quantity, status, last_calibration_date, next_calibration_date, created_at, updated_at)
             VALUES (UUID(), :name, :code, :class_id, :model_id, :quantity, :available, :status, :last_calibration, :next_calibration, NOW(), NOW())'
        );
        $stmt->execute([
            ':name' => $name,
            ':code' => $code,
            ':class_id' => $body['classId'] ?? null,
            ':model_id' => $modelId,
            ':quantity' => $quantity,
            ':available' => $body['availableQuantity'] ?? $quantity,
            ':status' => $body['status'] ?? 'available',
            ':last_calibration' => $body['lastCalibrationDate'] ?? null,
            ':next_calibration' => $nextCalibration,
        ]);

        $toolStmt = $pdo->prepare('SELECT * FROM tools WHERE code = ?');
        $toolStmt->execute([$code]);
        $tool = $toolStmt->fetch();
        recordAudit($pdo, [
            'user_id' => $user['id'],
            'target_type' => 'tool',
            'target_id' => $tool['id'],
            'action' => 'create',
            'description' => 'Usuario ' . formatName($user) . ' registrou a ferramenta ' . $tool['name'] . ' (' . $tool['code'] . ')',
            'after_data' => json_encode($tool),
        ]);
        jsonResponse(200, $tool);
    });
}

if (preg_match('#^/api/tools/([\\w-]+)$#', $path, $m) && $method === 'PATCH') {
    $user = requireAuth();
    requireRole($user, ['operator', 'admin']);
    $id = $m[1];
    withDb(function (PDO $pdo) use ($id, $body, $user) {
        $existing = $pdo->prepare('SELECT * FROM tools WHERE id = ?');
        $existing->execute([$id]);
        $prev = $existing->fetch();
        if (!$prev) {
            jsonResponse(404, ['message' => 'Ferramenta nao encontrada']);
        }

        $nextCalibration = $prev['next_calibration_date'];
        $modelId = $body['modelId'] ?? $prev['model_id'];
        if (array_key_exists('lastCalibrationDate', $body) || array_key_exists('modelId', $body)) {
            if (($body['lastCalibrationDate'] ?? null) === null) {
                $nextCalibration = null;
            } elseif ($modelId) {
                $modelStmt = $pdo->prepare('SELECT * FROM tool_models WHERE id = ?');
                $modelStmt->execute([$modelId]);
                $model = $modelStmt->fetch();
                if ($model && $model['requires_calibration'] && $model['calibration_interval_days']) {
                    $base = $body['lastCalibrationDate'] ?? $prev['last_calibration_date'];
                    $date = new DateTime($base);
                    $date->modify('+' . (int)$model['calibration_interval_days'] . ' days');
                    $nextCalibration = $date->format('Y-m-d H:i:s');
                } elseif ($model && !$model['requires_calibration']) {
                    $nextCalibration = null;
                }
            }
        }

        $stmt = $pdo->prepare(
            'UPDATE tools SET
                name = :name,
                code = :code,
                class_id = :class_id,
                model_id = :model_id,
                quantity = :quantity,
                available_quantity = :available,
                status = :status,
                last_calibration_date = :last_calibration,
                next_calibration_date = :next_calibration,
                updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            ':name' => $body['name'] ?? $prev['name'],
            ':code' => $body['code'] ?? $prev['code'],
            ':class_id' => $body['classId'] ?? $prev['class_id'],
            ':model_id' => $modelId,
            ':quantity' => $body['quantity'] ?? $prev['quantity'],
            ':available' => $body['availableQuantity'] ?? $prev['available_quantity'],
            ':status' => $body['status'] ?? $prev['status'],
            ':last_calibration' => $body['lastCalibrationDate'] ?? $prev['last_calibration_date'],
            ':next_calibration' => $nextCalibration,
            ':id' => $id,
        ]);
        $updated = $pdo->prepare('SELECT * FROM tools WHERE id = ?');
        $updated->execute([$id]);
        $tool = $updated->fetch();
        recordAudit($pdo, [
            'user_id' => $user['id'],
            'target_type' => 'tool',
            'target_id' => $id,
            'action' => 'update',
            'description' => 'Usuario ' . formatName($user) . ' atualizou a ferramenta ' . ($tool['name'] ?? ''),
            'before_data' => json_encode($prev),
            'after_data' => json_encode($tool),
        ]);
        jsonResponse(200, $tool);
    });
}

if (preg_match('#^/api/tools/([\\w-]+)$#', $path, $m) && $method === 'DELETE') {
    $user = requireAuth();
    requireRole($user, ['operator', 'admin']);
    $id = $m[1];
    withDb(function (PDO $pdo) use ($id, $user) {
        $existing = $pdo->prepare('SELECT * FROM tools WHERE id = ?');
        $existing->execute([$id]);
        $prev = $existing->fetch();
        if (!$prev) {
            jsonResponse(404, ['message' => 'Ferramenta nao encontrada']);
        }
        $pdo->prepare('DELETE FROM tools WHERE id = ?')->execute([$id]);
        recordAudit($pdo, [
            'user_id' => $user['id'],
            'target_type' => 'tool',
            'target_id' => $id,
            'action' => 'delete',
            'description' => 'Usuario ' . formatName($user) . ' removeu a ferramenta ' . $prev['name'] . ' (' . $prev['code'] . ')',
            'before_data' => json_encode($prev),
        ]);
        jsonResponse(200, ['success' => true]);
    });
}
