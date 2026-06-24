<?php
require_once '../../../bootstrap.php';
api_start();
header('Content-Type: application/json');

$db = api_db();
$userId = (int)($_SESSION['user_id'] ?? 0);

$tables = [];
foreach (['forum_categories','forum_threads','forum_posts','forum_reactions'] as $t) {
    $exists = api_table_exists($db, $t);
    $count = 0;
    if ($exists) {
        $row = api_fetch_one($db, "SELECT COUNT(*) AS c FROM `$t`");
        $count = (int)($row['c'] ?? 0);
    }
    $tables[$t] = ['exists' => $exists, 'rows' => $count];
}

echo json_encode([
    'session_user_id' => $userId,
    'logged_in' => $userId > 0,
    'tables' => $tables,
], JSON_PRETTY_PRINT);
