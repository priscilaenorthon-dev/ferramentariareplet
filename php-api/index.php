<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

// Route modules will exit once a match is handled.
require __DIR__ . '/routes/auth.php';
require __DIR__ . '/routes/dashboard.php';
require __DIR__ . '/routes/classes.php';
require __DIR__ . '/routes/models.php';
require __DIR__ . '/routes/tools.php';
require __DIR__ . '/routes/loans.php';
require __DIR__ . '/routes/users.php';
require __DIR__ . '/routes/audit.php';

// Fallback if nothing matched.
jsonResponse(404, ['message' => 'Rota nao encontrada']);
