<?php
declare(strict_types=1);

global $method, $path;

if ($method === 'GET' && $path === '/api/dashboard/stats') {
    $user = requireAuth();
    withDb(function (PDO $pdo) use ($user) {
        $stats = [
            'totalTools' => 0,
            'availableTools' => 0,
            'loanedTools' => 0,
            'calibrationAlerts' => 0,
            'recentLoans' => [],
            'upcomingCalibrations' => [],
            'usageByDepartment' => [],
            'usageByClass' => [],
            'usageOverTime' => [],
            'topTools' => [],
            'lowAvailabilityTools' => [],
            'overdueLoans' => [],
        ];

        $loanWhere = '';
        $params = [];
        if ($user['role'] === 'user') {
            $loanWhere = 'WHERE l.user_id = :uid';
            $params[':uid'] = $user['id'];
        }

        $totals = $pdo->query('SELECT COUNT(*) AS total, SUM(available_quantity) AS available, SUM(quantity - available_quantity) AS loaned FROM tools')->fetch();
        $stats['totalTools'] = (int)($totals['total'] ?? 0);
        $stats['availableTools'] = (int)($totals['available'] ?? 0);
        $stats['loanedTools'] = (int)($totals['loaned'] ?? 0);

        $recent = $pdo->prepare(
            "SELECT l.*, t.name AS tool_name, t.code AS tool_code, u.first_name, u.last_name
             FROM loans l
             JOIN tools t ON t.id = l.tool_id
             LEFT JOIN users u ON u.id = l.user_id
             $loanWhere
             ORDER BY l.loan_date DESC
             LIMIT 10"
        );
        $recent->execute($params);
        $stats['recentLoans'] = array_map(function ($row) {
            return [
                'id' => $row['id'],
                'toolName' => $row['tool_name'],
                'toolCode' => $row['tool_code'],
                'userName' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'loanDate' => $row['loan_date'],
                'status' => $row['status'],
            ];
        }, $recent->fetchAll() ?: []);

        $upcoming = $pdo->query(
            "SELECT id, name, code, next_calibration_date
             FROM tools
             WHERE next_calibration_date IS NOT NULL
             ORDER BY next_calibration_date ASC
             LIMIT 5"
        );
        $stats['upcomingCalibrations'] = $upcoming->fetchAll() ?: [];

        $usageDept = $pdo->prepare(
            "SELECT COALESCE(u.department, 'Nao informado') AS department,
                    SUM(l.quantity_loaned) AS totalLoans,
                    SUM(CASE WHEN l.status IN ('active','overdue') THEN l.quantity_loaned ELSE 0 END) AS activeLoans
             FROM loans l
             LEFT JOIN users u ON u.id = l.user_id
             $loanWhere
             GROUP BY department
             ORDER BY totalLoans DESC
             LIMIT 8"
        );
        $usageDept->execute($params);
        $stats['usageByDepartment'] = $usageDept->fetchAll() ?: [];

        $usageClass = $pdo->prepare(
            "SELECT COALESCE(c.name, 'Outros') AS className,
                    SUM(l.quantity_loaned) AS totalLoans,
                    SUM(CASE WHEN l.status IN ('active','overdue') THEN l.quantity_loaned ELSE 0 END) AS activeLoans
             FROM loans l
             JOIN tools t ON t.id = l.tool_id
             LEFT JOIN tool_classes c ON c.id = t.class_id
             $loanWhere
             GROUP BY className
             ORDER BY totalLoans DESC
             LIMIT 8"
        );
        $usageClass->execute($params);
        $stats['usageByClass'] = $usageClass->fetchAll() ?: [];

        $topTools = $pdo->prepare(
            "SELECT l.tool_id AS toolId, t.name AS toolName, t.code AS toolCode, SUM(l.quantity_loaned) AS usageCount, MAX(l.loan_date) AS lastLoanDate
             FROM loans l
             JOIN tools t ON t.id = l.tool_id
             $loanWhere
             GROUP BY l.tool_id, t.name, t.code
             ORDER BY usageCount DESC
             LIMIT 5"
        );
        $topTools->execute($params);
        $stats['topTools'] = $topTools->fetchAll() ?: [];

        $lowAvailability = $pdo->query(
            "SELECT id AS toolId, name AS toolName, code AS toolCode, available_quantity AS available, quantity AS total
             FROM tools
             WHERE quantity > 0 AND (available_quantity <= 1 OR available_quantity/quantity <= 0.2)
             ORDER BY available/quantity ASC
             LIMIT 5"
        );
        $stats['lowAvailabilityTools'] = $lowAvailability->fetchAll() ?: [];

        $overdue = $pdo->query(
            "SELECT l.id AS loanId, t.name AS toolName, t.code AS toolCode, u.first_name, u.last_name, l.expected_return_date
             FROM loans l
             JOIN tools t ON t.id = l.tool_id
             LEFT JOIN users u ON u.id = l.user_id
             WHERE l.status <> 'returned' AND l.expected_return_date IS NOT NULL AND l.expected_return_date < NOW()
             ORDER BY l.expected_return_date ASC
             LIMIT 5"
        );
        $stats['overdueLoans'] = array_map(function ($row) {
            $due = new DateTime($row['expected_return_date']);
            $now = new DateTime();
            $days = max(0, (int)$now->diff($due)->format('%r%a') * -1);
            return [
                'loanId' => $row['loanId'],
                'toolName' => $row['toolName'],
                'toolCode' => $row['toolCode'],
                'userName' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'Usuario removido',
                'daysOverdue' => $days,
            ];
        }, $overdue->fetchAll() ?: []);

        $usage = [];
        $now = new DateTimeImmutable('first day of this month');
        for ($i = 5; $i >= 0; $i--) {
            $period = $now->modify("-{$i} months");
            $key = $period->format('Y-m');
            $usage[$key] = [
                'key' => $key,
                'label' => $period->format('M/Y'),
                'loans' => 0,
                'returns' => 0,
            ];
        }

        $usageStmt = $pdo->prepare("SELECT loan_date, return_date, quantity_loaned FROM loans l $loanWhere");
        $usageStmt->execute($params);
        foreach ($usageStmt->fetchAll() ?: [] as $row) {
            if ($row['loan_date']) {
                $key = (new DateTime($row['loan_date']))->format('Y-m');
                if (isset($usage[$key])) {
                    $usage[$key]['loans'] += (int)$row['quantity_loaned'];
                }
            }
            if ($row['return_date']) {
                $key = (new DateTime($row['return_date']))->format('Y-m');
                if (isset($usage[$key])) {
                    $usage[$key]['returns'] += (int)$row['quantity_loaned'];
                }
            }
        }
        $stats['usageOverTime'] = array_values($usage);

        jsonResponse(200, $stats);
    });
}
