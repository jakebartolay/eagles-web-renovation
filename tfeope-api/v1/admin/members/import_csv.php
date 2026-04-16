<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

function member_import_normalize_header(string $header): string
{
    $normalized = preg_replace('/^\xEF\xBB\xBF/', '', trim($header));
    $normalized = strtolower((string) $normalized);
    $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized);

    return trim((string) $normalized, '_');
}

function member_import_generate_id(): string
{
    return 'EAG_' . strtoupper(substr(str_replace('.', '', uniqid('', true)), -12));
}

try {
    $db = api_db();
    $admin = api_require_admin($db);

    if ((int) ($admin['role_id'] ?? 0) !== 1) {
        api_json([
            'ok' => false,
            'message' => 'Only super admins can import members.',
        ], 403);
    }

    if (!api_table_exists($db, 'user_info')) {
        api_json([
            'ok' => false,
            'message' => 'Members table is not available.',
        ], 500);
    }

    $csvFile = $_FILES['file'] ?? $_FILES['csv'] ?? null;
    if (!is_array($csvFile) || (int) ($csvFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        api_json([
            'ok' => false,
            'message' => 'Please upload a CSV file first.',
        ], 422);
    }

    if ((int) ($csvFile['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        api_json([
            'ok' => false,
            'message' => 'The CSV file could not be uploaded.',
        ], 422);
    }

    $handle = fopen((string) ($csvFile['tmp_name'] ?? ''), 'rb');
    if ($handle === false) {
        api_json([
            'ok' => false,
            'message' => 'Unable to read the uploaded CSV file.',
        ], 422);
    }

    $headerRow = fgetcsv($handle);
    if (!is_array($headerRow) || $headerRow === []) {
        fclose($handle);
        api_json([
            'ok' => false,
            'message' => 'The uploaded CSV file is empty.',
        ], 422);
    }

    $headerMap = [];
    foreach ($headerRow as $index => $header) {
        $normalized = member_import_normalize_header((string) $header);
        if ($normalized !== '') {
            $headerMap[$normalized] = $index;
        }
    }

    $requiredHeaders = ['id', 'first_name', 'last_name', 'position', 'club', 'region'];
    foreach ($requiredHeaders as $requiredHeader) {
        if (!array_key_exists($requiredHeader, $headerMap)) {
            fclose($handle);
            api_json([
                'ok' => false,
                'message' => 'Invalid CSV format. Expected headers: ID, First Name, Last Name, Position, Club, Region, Status.',
            ], 422);
        }
    }

    $created = 0;
    $updated = 0;
    $skipped = 0;

    $db->beginTransaction();

    while (($row = fgetcsv($handle)) !== false) {
        $row = array_map(static fn ($value) => trim((string) $value), $row);
        $joined = trim(implode('', $row));
        if ($joined === '') {
            continue;
        }

        $memberId = strtoupper((string) ($row[$headerMap['id']] ?? ''));
        $firstName = strtoupper((string) ($row[$headerMap['first_name']] ?? ''));
        $lastName = strtoupper((string) ($row[$headerMap['last_name']] ?? ''));
        $position = strtoupper((string) ($row[$headerMap['position']] ?? ''));
        $club = strtoupper((string) ($row[$headerMap['club']] ?? ''));
        $region = strtoupper((string) ($row[$headerMap['region']] ?? ''));
        $status = strtoupper((string) ($row[$headerMap['status']] ?? 'ACTIVE'));

        if ($memberId === '') {
            $memberId = member_import_generate_id();
        }

        if ($firstName === '' || $lastName === '' || $position === '' || $club === '' || $region === '') {
            $skipped++;
            continue;
        }

        if ($status === '') {
            $status = 'ACTIVE';
        }

        $existing = api_fetch_one($db, '
            SELECT eagles_id
            FROM user_info
            WHERE eagles_id = :eagles_id
            LIMIT 1
        ', [':eagles_id' => $memberId]);

        if ($existing !== null) {
            api_execute($db, '
                UPDATE user_info
                SET eagles_firstName = :first_name,
                    eagles_lastName = :last_name,
                    eagles_position = :position,
                    eagles_club = :club,
                    eagles_region = :region,
                    eagles_status = :status
                WHERE eagles_id = :eagles_id
            ', [
                ':eagles_id' => $memberId,
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':position' => $position,
                ':club' => $club,
                ':region' => $region,
                ':status' => $status,
            ]);
            $updated++;
            continue;
        }

        api_execute($db, '
            INSERT INTO user_info (
                eagles_id,
                eagles_firstName,
                eagles_lastName,
                eagles_position,
                eagles_club,
                eagles_region,
                eagles_status,
                eagles_pic
            ) VALUES (
                :eagles_id,
                :first_name,
                :last_name,
                :position,
                :club,
                :region,
                :status,
                NULL
            )
        ', [
            ':eagles_id' => $memberId,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':position' => $position,
            ':club' => $club,
            ':region' => $region,
            ':status' => $status,
        ]);

        $created++;
    }

    fclose($handle);
    $db->commit();

    api_log_admin_action(
        $db,
        $admin,
        'IMPORT',
        sprintf(
            'Imported members CSV "%s" (%d created, %d updated, %d skipped)',
            (string) ($csvFile['name'] ?? 'members.csv'),
            $created,
            $updated,
            $skipped
        )
    );

    api_json([
        'ok' => true,
        'message' => sprintf(
            'CSV import completed. %d created, %d updated, %d skipped.',
            $created,
            $updated,
            $skipped
        ),
        'data' => [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ],
    ]);
} catch (Throwable $error) {
    if (isset($handle) && is_resource($handle)) {
        fclose($handle);
    }

    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log('Admin member CSV import API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to import members right now.',
    ], 500);
}
