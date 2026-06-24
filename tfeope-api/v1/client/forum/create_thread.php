<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/_helpers.php';
api_start();
api_require_method('POST');

$db = api_db();
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId === 0) { api_error('Unauthorized.', 401); }

$payload     = api_request_data();
$categoryId  = (int)($payload['categoryId'] ?? 0);
$title       = trim((string)($payload['title'] ?? ''));
$body        = trim((string)($payload['body'] ?? ''));

if ($categoryId === 0) { api_error('Category is required.', 400); }
if ($title === '')     { api_error('Title is required.', 400); }
if (strlen($title) > 255) { api_error('Title must be 255 characters or less.', 400); }
if ($body === '')      { api_error('Body is required.', 400); }

$category = api_fetch_one($db, "
    SELECT id, name, slug FROM forum_categories WHERE id = :id AND is_private = 0 LIMIT 1
", [':id' => $categoryId]);
if (!$category) { api_error('Category not found.', 404); }

forum_guard_thread_category_permission($db, $userId, $category);

$slug = forum_unique_thread_slug($db, $categoryId, $title);

// Optional image upload (multipart). Stored under the shared "media" group.
$imageFilename = null;
$uploadFile = $_FILES['image'] ?? null;
if (is_array($uploadFile) && (int) ($uploadFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    forum_ensure_thread_image_column($db);
    try {
        $stored = api_store_uploaded_file_as(
            $uploadFile,
            'media',
            'forum_' . date('Ymd'),
            api_image_extensions()
        );
        $imageFilename = $stored['filename'] ?? null;
    } catch (Throwable $error) {
        api_error('Unable to upload image: ' . $error->getMessage(), 422);
    }
}

if ($imageFilename !== null) {
    api_execute($db, "
        INSERT INTO forum_threads (category_id, user_id, title, slug, body, image, last_reply_at)
        VALUES (:cid, :uid, :title, :slug, :body, :image, NOW())
    ", [':cid' => $categoryId, ':uid' => $userId, ':title' => $title, ':slug' => $slug, ':body' => $body, ':image' => $imageFilename]);
} else {
    api_execute($db, "
        INSERT INTO forum_threads (category_id, user_id, title, slug, body, last_reply_at)
        VALUES (:cid, :uid, :title, :slug, :body, NOW())
    ", [':cid' => $categoryId, ':uid' => $userId, ':title' => $title, ':slug' => $slug, ':body' => $body]);
}

$threadId = (int)$db->lastInsertId();

// Update category thread count
api_execute($db, "
    UPDATE forum_categories SET thread_count = thread_count + 1 WHERE id = :id
", [':id' => $categoryId]);

api_json(['success' => true, 'threadId' => $threadId], 201);
