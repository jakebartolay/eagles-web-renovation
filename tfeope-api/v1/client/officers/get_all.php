<?php
require_once '../../../bootstrap.php';
api_start();

$db = api_db();
$category = trim($_GET['category'] ?? '');

$officers = api_officer_list($db);

if ($category !== '') {
    $officers = array_values(array_filter(
        $officers,
        fn($o) => ($o['category'] ?? '') === $category
    ));
}

api_json([
    'success' => true,
    'data'    => $officers
]);