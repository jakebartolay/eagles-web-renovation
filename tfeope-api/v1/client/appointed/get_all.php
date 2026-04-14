<?php
require_once '../../../bootstrap.php';
api_start();

$db = api_db();

if (!api_table_exists($db, 'appointed')) {
    api_json([
        'success' => true,
        'data' => [],
    ]);
}

$rows = api_fetch_all($db, '
    SELECT id, region, committee, position, name
    FROM appointed
    ORDER BY region ASC, committee ASC, position ASC, name ASC
');

$regions = [];

foreach ($rows as $row) {
    $regionName = trim((string) ($row['region'] ?? ''));
    $committeeName = trim((string) ($row['committee'] ?? ''));

    if ($regionName === '') {
        $regionName = 'Unassigned Region';
    }

    if ($committeeName === '') {
        $committeeName = 'Unassigned Committee';
    }

    $regionKey = mb_strtolower($regionName, 'UTF-8');
    $committeeKey = mb_strtolower($committeeName, 'UTF-8');

    if (!isset($regions[$regionKey])) {
        $regions[$regionKey] = [
            'id' => $regionKey,
            'name' => $regionName,
            'committees' => [],
        ];
    }

    if (!isset($regions[$regionKey]['committees'][$committeeKey])) {
        $regions[$regionKey]['committees'][$committeeKey] = [
            'id' => $committeeKey,
            'name' => $committeeName,
            'officers' => [],
        ];
    }

    $regions[$regionKey]['committees'][$committeeKey]['officers'][] = [
        'id' => (int) ($row['id'] ?? 0),
        'region' => $regionName,
        'committee' => $committeeName,
        'position' => (string) ($row['position'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
    ];
}

$data = array_values(array_map(static function (array $region): array {
    $region['committees'] = array_values($region['committees']);
    return $region;
}, $regions));

api_json([
    'success' => true,
    'data' => $data,
]);
