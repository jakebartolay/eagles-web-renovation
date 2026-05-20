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

function member_import_photo_key(string $value): string
{
    $baseName = pathinfo(basename($value), PATHINFO_FILENAME);
    $normalized = strtoupper(trim((string) $baseName));
    $normalized = preg_replace('/[^A-Z0-9]+/', '_', $normalized);

    return trim((string) $normalized, '_');
}

function member_import_photo_payload(): array
{
    $uploads = $_FILES['photos'] ?? $_FILES['images'] ?? $_FILES['pictures'] ?? null;
    if (!is_array($uploads)) {
        return [
            'photos' => [],
            'invalid' => [],
        ];
    }

    $photos = [];
    $invalid = [];
    $allowedExtensions = api_image_extensions();

    foreach (api_normalize_uploaded_files($uploads) as $photo) {
        $errorCode = (int) ($photo['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $name = (string) ($photo['name'] ?? 'image');
        $key = member_import_photo_key($name);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($errorCode !== UPLOAD_ERR_OK) {
            $invalid[] = [
                'file' => $name,
                'reason' => api_upload_error_message($errorCode),
            ];
            continue;
        }

        if ($key === '') {
            $invalid[] = [
                'file' => $name,
                'reason' => 'Image filename must match a member ID.',
            ];
            continue;
        }

        if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
            $invalid[] = [
                'file' => $name,
                'reason' => 'Unsupported image type.',
            ];
            continue;
        }

        if (isset($photos[$key])) {
            $invalid[] = [
                'file' => $name,
                'reason' => 'Duplicate image filename/member ID in upload.',
            ];
            continue;
        }

        $photos[$key] = $photo;
    }

    return [
        'photos' => $photos,
        'invalid' => $invalid,
    ];
}

try {
    $db = api_db();
    $admin = api_require_admin($db);

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
    $skipped = 0;
    $photoAttached = 0;
    $duplicates = [];
    $missingPhotos = [];
    $photoErrors = [];
    $importItems = [];
    $matchedPhotoKeys = [];
    $photoPayload = member_import_photo_payload();
    $photoUploadsById = $photoPayload['photos'];
    $invalidPhotos = $photoPayload['invalid'];
    $seenMemberIds = [];
    $rowNumber = 1;

    $db->beginTransaction();

    while (($row = fgetcsv($handle)) !== false) {
        $rowNumber++;
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

        $memberName = trim($firstName . ' ' . $lastName);

        if ($firstName === '' || $lastName === '' || $position === '' || $club === '' || $region === '') {
            $importItems[] = [
                'row' => $rowNumber,
                'id' => $memberId,
                'name' => $memberName,
                'position' => $position,
                'club' => $club,
                'region' => $region,
                'status' => 'skipped',
                'photoStatus' => 'not_checked',
                'photoFile' => '',
                'reason' => 'Required member fields are incomplete.',
            ];
            $skipped++;
            continue;
        }

        if ($status === '') {
            $status = 'ACTIVE';
        }

        $photoKey = member_import_photo_key($memberId);
        $photoUpload = $photoUploadsById[$photoKey] ?? null;

        $duplicateInFile = array_key_exists($memberId, $seenMemberIds);
        $seenMemberIds[$memberId] = true;

        $existing = api_fetch_one($db, '
            SELECT eagles_id
            FROM user_info
            WHERE eagles_id = :eagles_id
            LIMIT 1
        ', [':eagles_id' => $memberId]);

        if ($existing !== null || $duplicateInFile) {
            $photoStatus = 'missing';
            $photoFile = is_array($photoUpload) ? (string) ($photoUpload['name'] ?? '') : '';
            $photoReason = 'No matching photo uploaded.';

            if ($existing !== null && is_array($photoUpload) && !isset($matchedPhotoKeys[$photoKey])) {
                try {
                    $storedPhoto = api_store_uploaded_file_as($photoUpload, 'members', $memberId, api_image_extensions(), true);
                    api_execute($db, '
                        UPDATE user_info
                        SET eagles_pic = :pic
                        WHERE eagles_id = :eagles_id
                    ', [
                        ':eagles_id' => $memberId,
                        ':pic' => $storedPhoto['filename'] ?? '',
                    ]);
                    $matchedPhotoKeys[$photoKey] = true;
                    $photoAttached++;
                    $photoStatus = 'attached_to_existing';
                    $photoFile = (string) ($storedPhoto['filename'] ?? $photoFile);
                    $photoReason = 'Photo attached to existing member.';
                } catch (Throwable $photoError) {
                    $photoStatus = 'error';
                    $photoReason = $photoError->getMessage();
                    $photoErrors[] = [
                        'row' => $rowNumber,
                        'id' => $memberId,
                        'file' => (string) ($photoUpload['name'] ?? ''),
                        'reason' => $photoError->getMessage(),
                    ];
                }
            } elseif (!is_array($photoUpload)) {
                $missingPhotos[] = [
                    'row' => $rowNumber,
                    'id' => $memberId,
                    'name' => trim($firstName . ' ' . $lastName),
                ];
            }

            $duplicates[] = [
                'row' => $rowNumber,
                'id' => $memberId,
                'name' => $memberName,
                'position' => $position,
                'club' => $club,
                'region' => $region,
                'reason' => $duplicateInFile ? 'Duplicate ID in this CSV file.' : 'ID already exists in members.',
            ];
            $importItems[] = [
                'row' => $rowNumber,
                'id' => $memberId,
                'name' => $memberName,
                'position' => $position,
                'club' => $club,
                'region' => $region,
                'status' => 'duplicate',
                'photoStatus' => $photoStatus,
                'photoFile' => $photoFile,
                'reason' => $duplicateInFile ? 'Duplicate ID in this CSV file.' : 'ID already exists in members.',
                'photoReason' => $photoReason,
            ];
            $skipped++;
            continue;
        }

        $storedPhoto = null;
        $photoStatus = 'missing';
        $photoFile = '';
        $photoReason = 'No matching photo uploaded.';
        if (is_array($photoUpload)) {
            try {
                $storedPhoto = api_store_uploaded_file_as($photoUpload, 'members', $memberId, api_image_extensions(), true);
                $matchedPhotoKeys[$photoKey] = true;
                $photoAttached++;
                $photoStatus = 'attached';
                $photoFile = (string) ($storedPhoto['filename'] ?? ($photoUpload['name'] ?? ''));
                $photoReason = 'Photo attached.';
            } catch (Throwable $photoError) {
                $photoStatus = 'error';
                $photoFile = (string) ($photoUpload['name'] ?? '');
                $photoReason = $photoError->getMessage();
                $photoErrors[] = [
                    'row' => $rowNumber,
                    'id' => $memberId,
                    'file' => (string) ($photoUpload['name'] ?? ''),
                    'reason' => $photoError->getMessage(),
                ];
            }
        } else {
            $missingPhotos[] = [
                'row' => $rowNumber,
                'id' => $memberId,
                'name' => trim($firstName . ' ' . $lastName),
            ];
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
                :pic
            )
        ', [
            ':eagles_id' => $memberId,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':position' => $position,
            ':club' => $club,
            ':region' => $region,
            ':status' => $status,
            ':pic' => $storedPhoto['filename'] ?? '',
        ]);

        $importItems[] = [
            'row' => $rowNumber,
            'id' => $memberId,
            'name' => $memberName,
            'position' => $position,
            'club' => $club,
            'region' => $region,
            'status' => 'created',
            'photoStatus' => $photoStatus,
            'photoFile' => $photoFile,
            'reason' => 'Member created.',
            'photoReason' => $photoReason,
        ];

        $created++;
    }

    fclose($handle);
    $db->commit();

    $unmatchedPhotos = [];
    foreach ($photoUploadsById as $key => $photo) {
        if (isset($matchedPhotoKeys[$key])) {
            continue;
        }

        $unmatchedPhotos[] = [
            'file' => (string) ($photo['name'] ?? ''),
            'expectedId' => $key,
            'reason' => 'No CSV/member ID matched this image filename.',
        ];
    }

    api_log_admin_action(
        $db,
        $admin,
        'IMPORT',
        sprintf(
            'Imported members CSV "%s" (%d created, %d duplicates, %d skipped, %d photos attached)',
            (string) ($csvFile['name'] ?? 'members.csv'),
            $created,
            count($duplicates),
            $skipped,
            $photoAttached
        )
    );

    api_json([
        'ok' => true,
        'message' => sprintf(
            'CSV import completed. %d created, %d duplicate%s, %d skipped, %d photo%s attached.',
            $created,
            count($duplicates),
            count($duplicates) === 1 ? '' : 's',
            $skipped,
            $photoAttached,
            $photoAttached === 1 ? '' : 's'
        ),
        'data' => [
            'created' => $created,
            'updated' => 0,
            'skipped' => $skipped,
            'photosAttached' => $photoAttached,
            'duplicates' => $duplicates,
            'duplicateCount' => count($duplicates),
            'missingPhotos' => $missingPhotos,
            'unmatchedPhotos' => $unmatchedPhotos,
            'invalidPhotos' => $invalidPhotos,
            'photoErrors' => $photoErrors,
            'items' => $importItems,
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
