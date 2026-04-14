<?php
require_once '../../../bootstrap.php';
api_start();
api_require_method('GET');

$db = api_db();
api_require_admin($db);

$members = api_fetch_all($db, "
    SELECT *
    FROM user_info
    ORDER BY eagles_dateAdded DESC
");

$data = array_map(function($row) {
    return [
        'id'        => $row['eagles_id'],
        'status'    => $row['eagles_status'],
        'firstName' => $row['eagles_firstName'],
        'lastName'  => $row['eagles_lastName'],
        'fullName'  => $row['eagles_firstName'] . ' ' . $row['eagles_lastName'],
        'position'  => $row['eagles_position'],
        'club'      => $row['eagles_club'],
        'region'    => $row['eagles_region'],
        'picUrl'    => api_media_url('media', basename($row['eagles_pic'])),
        'dateAdded' => $row['eagles_dateAdded'],
    ];
}, $members);

api_json([
    'success' => true,
    'total'   => count($data),
    'data'    => $data
]);
