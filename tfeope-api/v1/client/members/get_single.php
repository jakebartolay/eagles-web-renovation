<?php
require_once '../../../bootstrap.php';
api_start();

$db = api_db();
api_member_regional_position_column($db);
$id = trim($_GET['id'] ?? '');

if ($id === '') {
    api_error('Member ID required.');
}

$row = api_fetch_one($db, "
    SELECT *
    FROM user_info
    WHERE eagles_id = :id
    LIMIT 1
", [':id' => $id]);

if (!$row) {
    api_error('Member not found.', 404);
}

api_json([
    'success' => true,
    'data'    => [
        'id'        => $row['eagles_id'],
        'status'    => $row['eagles_status'],
        'firstName' => $row['eagles_firstName'],
        'lastName'  => $row['eagles_lastName'],
        'fullName'  => $row['eagles_firstName'] . ' ' . $row['eagles_lastName'],
        'position'  => $row['eagles_position'],
        'regionalPosition' => api_member_regional_position_value($row),
        'regional_position' => api_member_regional_position_value($row),
        'club'      => $row['eagles_club'],
        'region'    => $row['eagles_region'],
        'picUrl'    => api_media_url('media', basename($row['eagles_pic'])),
        'dateAdded' => $row['eagles_dateAdded'],
    ]
]);
