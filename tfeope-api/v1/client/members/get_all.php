<?php
require_once '../../../bootstrap.php';
api_start();

$db = api_db();

$members = api_fetch_all($db, "
    SELECT 
        eagles_id,
        eagles_status,
        eagles_firstName,
        eagles_lastName,
        eagles_position,
        eagles_club,
        eagles_region,
        eagles_pic,
        eagles_dateAdded
    FROM user_info
    WHERE eagles_status = 'ACTIVE'
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