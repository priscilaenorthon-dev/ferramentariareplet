<?php
declare(strict_types=1);

global $method, $path, $body;

if ($path === '/api/loans' && $method === 'GET') {
    $user = requireAuth();
    withDb(function (PDO $pdo) use ($user) {
        $where = '';
        $params = [];
        if ($user['role'] === 'user') {
            $where = 'WHERE l.user_id = :uid AND l.status = "active"';
            $params[':uid'] = $user['id'];
        }
        $stmt = $pdo->prepare(
            "SELECT l.*, t.name AS tool_name, t.code AS tool_code, u.first_name, u.last_name
             FROM loans l
             JOIN tools t ON t.id = l.tool_id
             LEFT JOIN users u ON u.id = l.user_id
             $where
             ORDER BY l.loan_date DESC"
        );
        $stmt->execute($params);
        jsonResponse(200, $stmt->fetchAll() ?: []);
    });
}

if ($path === '/api/loans' && $method === 'POST') {
    $user = requireAuth();
    requireRole($user, ['operator', 'admin']);
    $tools = $body['tools'] ?? [];
    $userId = $body['userId'] ?? null;
    $confirmation = $body['userConfirmation'] ?? null;
    if (!$userId || !is_array($tools) || count($tools) === 0) {
        jsonResponse(400, ['message' => 'Dados do emprestimo invalidos']);
    }
    withDb(function (PDO $pdo) use ($user, $tools, $userId, $confirmation) {
        $borrower = fetchUserById($pdo, $userId);
        if (!$borrower) {
            jsonResponse(400, ['message' => 'Usuario nao encontrado']);
        }
        if (($confirmation['method'] ?? '') === 'qrcode') {
            $qrUser = fetchUserByQr($pdo, $confirmation['qrCode'] ?? '');
            if (!$qrUser || $qrUser['id'] !== $userId) {
                jsonResponse(400, ['message' => 'Confirmacao por QR code falhou']);
            }
        } else {
            $login = $confirmation['email'] ?? '';
            if ($borrower['email'] !== $login && $borrower['username'] !== $login) {
                jsonResponse(400, ['message' => 'Credenciais invalidas']);
            }
            if (!password_verify($confirmation['password'] ?? '', $borrower['password'])) {
                jsonResponse(400, ['message' => 'Senha invalida']);
            }
        }

        $pdo->beginTransaction();
        try {
            $batchId = bin2hex(random_bytes(8));
            $now = (new DateTime())->format('Y-m-d H:i:s');
            $createdLoans = [];
            foreach ($tools as $loanItem) {
                $toolId = $loanItem['toolId'] ?? null;
                $qty = (int)($loanItem['quantityLoaned'] ?? 0);
                if (!$toolId || $qty <= 0) {
                    throw new RuntimeException('Ferramenta invalida');
                }
                $toolStmt = $pdo->prepare('SELECT * FROM tools WHERE id = ? FOR UPDATE');
                $toolStmt->execute([$toolId]);
                $tool = $toolStmt->fetch();
                if (!$tool || $tool['available_quantity'] < $qty) {
                    throw new RuntimeException('Ferramenta indisponivel');
                }

                $loanStmt = $pdo->prepare(
                    'INSERT INTO loans (id, batch_id, tool_id, user_id, operator_id, quantity_loaned, loan_date, status, user_confirmation, user_confirmation_date, created_at)
                     VALUES (UUID(), :batch, :tool, :user, :operator, :qty, :loan_date, "active", 1, :confirm_date, NOW())'
                );
                $loanStmt->execute([
                    ':batch' => $batchId,
                    ':tool' => $toolId,
                    ':user' => $userId,
                    ':operator' => $user['id'],
                    ':qty' => $qty,
                    ':loan_date' => $now,
                    ':confirm_date' => $now,
                ]);
                $loanId = $pdo->lastInsertId() ?: $pdo->query('SELECT id FROM loans WHERE tool_id = ' . $pdo->quote($toolId) . ' ORDER BY created_at DESC LIMIT 1')->fetchColumn();

                $updateTool = $pdo->prepare('UPDATE tools SET available_quantity = available_quantity - :qty, status = CASE WHEN available_quantity - :qty = 0 THEN "loaned" ELSE status END WHERE id = :id');
                $updateTool->execute([':qty' => $qty, ':id' => $toolId]);

                recordAudit($pdo, [
                    'user_id' => $user['id'],
                    'target_type' => 'tool',
                    'target_id' => $toolId,
                    'action' => 'move',
                    'description' => 'Usuario ' . formatName($user) . " emprestou {$qty}x {$tool['name']} ({$tool['code']}) para " . formatName($borrower),
                    'before_data' => json_encode($tool),
                    'metadata' => json_encode(['loanId' => $loanId, 'batchId' => $batchId, 'quantity' => $qty, 'borrowerId' => $userId]),
                ]);
                $createdLoans[] = [
                    'id' => $loanId,
                    'toolId' => $toolId,
                    'batchId' => $batchId,
                    'quantityLoaned' => $qty,
                ];
            }
            $pdo->commit();
            jsonResponse(200, ['loans' => $createdLoans, 'batchId' => $batchId]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            jsonResponse(400, ['message' => $e->getMessage()]);
        }
    });
}

if (preg_match('#^/api/loans/([\\w-]+)/return$#', $path, $m) && $method === 'PATCH') {
    $user = requireAuth();
    requireRole($user, ['operator', 'admin']);
    $id = $m[1];
    withDb(function (PDO $pdo) use ($id, $user) {
        $pdo->beginTransaction();
        try {
            $loanStmt = $pdo->prepare('SELECT * FROM loans WHERE id = ? FOR UPDATE');
            $loanStmt->execute([$id]);
            $loan = $loanStmt->fetch();
            if (!$loan) {
                throw new RuntimeException('Loan nao encontrado');
            }
            $pdo->prepare('UPDATE loans SET status = "returned", return_date = NOW() WHERE id = ?')->execute([$id]);

            $toolStmt = $pdo->prepare('SELECT * FROM tools WHERE id = ? FOR UPDATE');
            $toolStmt->execute([$loan['tool_id']]);
            $tool = $toolStmt->fetch();
            if ($tool) {
                $pdo->prepare('UPDATE tools SET available_quantity = available_quantity + :qty, status = "available" WHERE id = :id')
                    ->execute([':qty' => $loan['quantity_loaned'], ':id' => $tool['id']]);
                recordAudit($pdo, [
                    'user_id' => $user['id'],
                    'target_type' => 'tool',
                    'target_id' => $tool['id'],
                    'action' => 'move',
                    'description' => 'Usuario ' . formatName($user) . ' registrou devolucao de ' . $loan['quantity_loaned'] . 'x ' . $tool['name'] . ' (' . $tool['code'] . ')',
                    'before_data' => json_encode($tool),
                    'metadata' => json_encode(['loanId' => $loan['id'], 'quantity' => $loan['quantity_loaned']]),
                ]);
            }
            $pdo->commit();
            jsonResponse(200, ['success' => true]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            jsonResponse(400, ['message' => $e->getMessage()]);
        }
    });
}
