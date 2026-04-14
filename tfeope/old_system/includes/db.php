<?php
// db.php - Global database connection with error handling

// Enable strict mysqli error reporting (required for try/catch)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$DB_HOST = "localhost";
$DB_USER = "tfoepeinc_eagles";
$DB_PASS = "eagleseagles";
$DB_NAME = "tfoepeinc_eagles";

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {

    // Log the real error (recommended)
    error_log("DB Connection Error: " . $e->getMessage());

    // Show generic message to users
    http_response_code(500);
    die("Database connection error. Please try again later.");
}
