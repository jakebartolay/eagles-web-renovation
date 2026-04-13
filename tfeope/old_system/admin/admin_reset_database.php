<?php
// FORCE SESSION (so $_SESSION is never empty here)
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . "/../includes/admin_session.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/db.php";

header("Content-Type: application/json; charset=utf-8");

function json_out($ok, $message, $extra = [], $code = 200) {
  http_response_code($code);
  echo json_encode(array_merge(["ok" => $ok, "message" => $message], $extra));
  exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  json_out(false, "Method not allowed.", [], 405);
}

if (!isset($conn) || !($conn instanceof mysqli)) {
  json_out(false, "DB connection not found. Check db.php.", [], 500);
}

/* ===================== SESSION USER ID (ROBUST) ===================== */
$sessionUserId = 0;

if (isset($_SESSION['id'])) $sessionUserId = (int)$_SESSION['id'];
elseif (isset($_SESSION['user_id'])) $sessionUserId = (int)$_SESSION['user_id'];
elseif (isset($_SESSION['admin_id'])) $sessionUserId = (int)$_SESSION['admin_id'];
elseif (isset($_SESSION['uid'])) $sessionUserId = (int)$_SESSION['uid'];

if ($sessionUserId <= 0) {
  json_out(false, "Unauthorized. Missing session id.", [
    "debug_session_keys" => array_keys($_SESSION),
    "debug_cookie" => $_COOKIE['PHPSESSID'] ?? null
  ], 403);
}

/* ===================== READ PASSWORD FROM REQUEST ===================== */
$raw = file_get_contents("php://input");
$body = json_decode($raw, true);
$password = trim((string)($body["password"] ?? ""));

if ($password === "") {
  json_out(false, "Password is required.", [], 400);
}

/* ===================== CONFIG (MATCH YOUR USERS TABLE) ===================== */
$USERS_TABLE    = "users";
$USERS_ID_COL   = "id";
$USERS_ROLE_COL = "role_id";
$USERS_PASS_COL = "password_hash";

/* ===================== RESET TARGETS ===================== */
$TABLES_TO_CLEAR = [
  "user_info",
  "events",
  "news_info",
  "video_info",
  "memorandum_pages",
  "memorandum",
  "magna_carta_items",
];

$FOLDERS_TO_CLEAR = [
  "/event_media",
  "/magna_carta",
  "/memorandum",
  "/news_images",
  "/uploads",
  "/videos",
  "/videos_thumbnail",
];

$DELETE_SUBFOLDERS = true;

/* ===================== HELPERS ===================== */
function safe_realpath_under_root(string $path, string $root): ?string {
  $rootReal = realpath($root);
  if ($rootReal === false) return null;

  $real = realpath($path);
  if ($real === false) return null;

  $rootReal = rtrim(str_replace("\\", "/", $rootReal), "/") . "/";
  $realNorm = rtrim(str_replace("\\", "/", $real), "/") . "/";

  if (strpos($realNorm, $rootReal) !== 0) return null;
  return rtrim($real, "/\\");
}

function clear_directory(string $dir, bool $deleteSubfolders): array {
  $stats = [
    "dir" => $dir,
    "deleted_files" => 0,
    "deleted_dirs" => 0,
    "errors" => [],
    "skipped" => 0,
  ];

  if (!is_dir($dir)) {
    $stats["errors"][] = "Not a directory or does not exist.";
    return $stats;
  }

  try {
    $it = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($it as $item) {
      $path = $item->getPathname();

      if ($item->isLink()) { $stats["skipped"]++; continue; }

      if ($item->isFile()) {
        if (@unlink($path)) $stats["deleted_files"]++;
        else $stats["errors"][] = "Failed to delete file: $path";
        continue;
      }

      if ($item->isDir()) {
        if (!$deleteSubfolders) continue;
        if (@rmdir($path)) $stats["deleted_dirs"]++;
        else $stats["errors"][] = "Failed to remove dir: $path";
      }
    }
  } catch (Throwable $e) {
    $stats["errors"][] = "Iterator error: " . $e->getMessage();
  }

  return $stats;
}

/* ===================== LOAD USER + ROLE FROM DB ===================== */
$stmt = $conn->prepare(
  "SELECT `$USERS_ROLE_COL` AS role_id, `$USERS_PASS_COL` AS pass_hash
   FROM `$USERS_TABLE`
   WHERE `$USERS_ID_COL` = ?
   LIMIT 1"
);

if (!$stmt) {
  json_out(false, "Server error (users query).", ["mysql_error" => $conn->error], 500);
}

$stmt->bind_param("i", $sessionUserId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
  json_out(false, "Unauthorized. User not found in users table.", [
    "debug" => ["session_id" => $sessionUserId]
  ], 403);
}

$dbRoleId = (int)($row["role_id"] ?? 0);
$hash     = (string)($row["pass_hash"] ?? "");

/* ===================== SUPER ADMIN ONLY ===================== */
if ($dbRoleId !== 1) {
  json_out(false, "Unauthorized.", [
    "debug_role" => [
      "session_id" => $sessionUserId,
      "db_role_id" => $dbRoleId
    ]
  ], 403);
}

/* ===================== VERIFY PASSWORD ===================== */
if ($hash === "" || !password_verify($password, $hash)) {
  json_out(false, "Incorrect password.", [], 401);
}

/* ===================== RESET DB ===================== */
$conn->begin_transaction();

try {
  if (!$conn->query("SET FOREIGN_KEY_CHECKS=0")) {
    throw new Exception("Cannot disable FK checks: " . $conn->error);
  }

  foreach ($TABLES_TO_CLEAR as $t) {
    $t = trim($t);
    if ($t === "") continue;

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $t)) {
      throw new Exception("Invalid table name: " . $t);
    }

    if (!$conn->query("TRUNCATE TABLE `$t`")) {
      $truncateErr = $conn->error;

      if (!$conn->query("DELETE FROM `$t`")) {
        throw new Exception("Failed to clear `$t`. TRUNCATE: $truncateErr | DELETE: " . $conn->error);
      }

      $conn->query("ALTER TABLE `$t` AUTO_INCREMENT = 1");
    }
  }

  if (!$conn->query("SET FOREIGN_KEY_CHECKS=1")) {
    throw new Exception("Cannot re-enable FK checks: " . $conn->error);
  }

  $conn->commit();

} catch (Throwable $e) {
  $conn->query("SET FOREIGN_KEY_CHECKS=1");
  $conn->rollback();
  json_out(false, "Reset failed: " . $e->getMessage(), ["mysql_error" => $conn->error], 500);
}

/* ===================== CLEAR FOLDERS AFTER DB COMMIT (SAFE) ===================== */
$root = $_SERVER["DOCUMENT_ROOT"];
$rootReal = realpath($root);
$rootRealNorm = $rootReal ? rtrim(str_replace("\\", "/", $rootReal), "/") : "";

$folderReports = [];

// Allowlist: ONLY these top-level folders may ever be cleared
$ALLOWED_TOP_LEVEL = [
  "event_media",
  "magna_carta",
  "memorandum",
  "news_images",
  "uploads",
  "videos",
  "videos_thumbnail",
];

foreach ($FOLDERS_TO_CLEAR as $rel) {
  $rel = trim((string)$rel);

  // skip empty/bad entries
  if ($rel === "" || $rel === "/") {
    $folderReports[] = ["folder" => $rel, "status" => "skipped", "reason" => "Empty or root path"];
    continue;
  }

  $rel = "/" . ltrim($rel, "/");
  $target = $root . $rel;

  $safe = safe_realpath_under_root($target, $root);
  if ($safe === null) {
    $folderReports[] = [
      "folder" => $rel,
      "status" => "skipped",
      "reason" => "Path not found or not under DOCUMENT_ROOT",
      "resolved" => null
    ];
    continue;
  }

  $safeReal = realpath($safe);
  $safeNorm = $safeReal ? rtrim(str_replace("\\", "/", $safeReal), "/") : rtrim(str_replace("\\", "/", $safe), "/");

  // HARD STOP: never clear DOCUMENT_ROOT itself
  if ($rootRealNorm !== "" && $safeNorm === $rootRealNorm) {
    $folderReports[] = [
      "folder" => $rel,
      "status" => "skipped",
      "reason" => "Safety: refusing to clear DOCUMENT_ROOT",
      "resolved" => $safeNorm
    ];
    continue;
  }

  // Allowlist: only clear the exact top-level folders we expect
  $base = strtolower(basename($safeNorm));
  if (!in_array($base, $ALLOWED_TOP_LEVEL, true)) {
    $folderReports[] = [
      "folder" => $rel,
      "status" => "skipped",
      "reason" => "Safety: folder not in allowlist",
      "resolved" => $safeNorm
    ];
    continue;
  }

  $report = clear_directory($safeNorm, $DELETE_SUBFOLDERS);
  $report["folder"] = $rel;
  $report["resolved"] = $safeNorm;
  $report["status"] = (count($report["errors"]) === 0) ? "ok" : "partial";
  $folderReports[] = $report;
}

json_out(true, "Database reset completed. Folders cleared.", [
  "folders" => $folderReports,
  "delete_subfolders" => $DELETE_SUBFOLDERS,
  "debug_root" => $rootRealNorm
]);
