<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    exit("Unauthorized");
}

require_once $_SERVER['DOCUMENT_ROOT'] . "/db.php";


// Get POST data
$id         = $_POST['id'] ?? '';
$first_name = $_POST['first_name'] ?? '';
$last_name  = $_POST['last_name'] ?? '';
$position   = $_POST['position'] ?? '';
$club       = $_POST['club'] ?? '';
$region     = $_POST['region'] ?? '';
$status     = $_POST['status'] ?? 'Active';

if ($id === '' || $first_name === '' || $last_name === '') {
    http_response_code(400);
    exit("Invalid data");
}

// Optional photo upload
$photoPath = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $filename = "member_" . time() . rand(100,999) . "." . $ext;
    $uploadDir = "C:/xampp/htdocs/Eagles/Main/uploads/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $fullPath = $uploadDir . $filename;
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $fullPath)) {
        $photoPath = "Main/uploads/" . $filename;
    }
}

// Prepare SQL
if ($photoPath) {
    $stmt = $conn->prepare(
        "UPDATE user_info 
         SET eagles_firstName=?, eagles_lastName=?, eagles_position=?, eagles_club=?, eagles_region=?, eagles_status=?, eagles_photo=? 
         WHERE eagles_id=?"
    );
    $stmt->bind_param("ssssssss", $first_name, $last_name, $position, $club, $region, $status, $photoPath, $id);
} else {
    $stmt = $conn->prepare(
        "UPDATE user_info 
         SET eagles_firstName=?, eagles_lastName=?, eagles_position=?, eagles_club=?, eagles_region=?, eagles_status=? 
         WHERE eagles_id=?"
    );
    $stmt->bind_param("sssssss", $first_name, $last_name, $position, $club, $region, $status, $id);
}

// Execute
if ($stmt->execute()) {
    echo "OK"; // success
} else {
    http_response_code(500);
    echo "Update failed: " . $stmt->error;
}

$stmt->close();
$conn->close();
