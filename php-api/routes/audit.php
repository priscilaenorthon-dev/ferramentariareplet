<?php
declare(strict_types=1);

global $method, $path, $query;

if ($path === '/api/audit/logs' && $method === 'GET') {
    $user = requireAuth();
    requireRole($user, ['admin']);
    $where = [];
    $params = [];
    if (isset($query['targetType'])) {
        $where[] = 'target_type = :tt';
        $params[':tt'] = $query['targetType'];
    }
    if (isset($query['targetId'])) {
        $where[] = 'target_id = :tid';
        $params[':tid'] = $query['targetId'];
    }
    if (isset($query['userId'])) {
        $where[] = 'user_id = :uid';
        $params[':uid'] = $query['userId'];
    }
    $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    withDb(function (PDO $pdo) use ($whereClause, $params) {
        $stmt = $pdo->prepare(
            "SELECT l.*, u.username, u.first_name, u.last_name
             FROM audit_logs l
             LEFT JOIN users u ON u.id = l.user_id
             $whereClause
             ORDER BY l.created_at DESC
             LIMIT 200"
        );
        $stmt->execute($params);
        jsonResponse(200, $stmt->fetchAll() ?: []);
    });
}
