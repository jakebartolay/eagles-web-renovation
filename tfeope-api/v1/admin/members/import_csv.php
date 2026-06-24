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

function member_import_detect_delimiter(string $line): string
{
    $expectedHeaders = ['id', 'first_name', 'last_name', 'position', 'club', 'region', 'regional_position', 'status'];
    $bestDelimiter = ',';
    $bestScore = -1;

    foreach ([',', "\t", ';'] as $delimiter) {
        $headers = str_getcsv($line, $delimiter);
        $normalizedHeaders = array_map(
            static fn ($header): string => member_import_normalize_header((string) $header),
            is_array($headers) ? $headers : []
        );
        $score = count(array_intersect($expectedHeaders, $normalizedHeaders)) * 100 + count($normalizedHeaders);

        if ($score > $bestScore) {
            $bestDelimiter = $delimiter;
            $bestScore = $score;
        }
    }

    return $bestDelimiter;
}

function member_import_header_index(array $headerMap, array $aliases): ?int
{
    foreach ($aliases as $alias) {
        $normalizedAlias = member_import_normalize_header((string) $alias);
        if ($normalizedAlias !== '' && array_key_exists($normalizedAlias, $headerMap)) {
            return (int) $headerMap[$normalizedAlias];
        }
    }

    return null;
}

function member_import_lookup_key(string $value): string
{
    $normalized = strtoupper(trim($value));
    $normalized = preg_replace('/\s+/', ' ', $normalized);

    return (string) $normalized;
}

function member_import_add_catalog_skip(array &$report, string $region, string $club, string $reason): void
{
    foreach ($report['skipped'] as $item) {
        if (
            member_import_lookup_key((string) ($item['region'] ?? '')) === member_import_lookup_key($region)
            && member_import_lookup_key((string) ($item['club'] ?? '')) === member_import_lookup_key($club)
            && (string) ($item['reason'] ?? '') === $reason
        ) {
            return;
        }
    }

    $report['skipped'][] = [
        'region' => $region,
        'club' => $club,
        'reason' => $reason,
    ];
}

function member_import_ensure_region_club(PDO $db, string $region, string $club, array &$cache, array &$report): void
{
    $region = trim($region);
    $club = trim($club);
    if ($region === '' || $club === '') {
        return;
    }

    $cacheKey = member_import_lookup_key($region) . '::' . member_import_lookup_key($club);
    if (isset($cache[$cacheKey])) {
        return;
    }

    if (!api_table_exists($db, 'regions') || !api_table_exists($db, 'clubs')) {
        member_import_add_catalog_skip($report, $region, $club, 'Regions or clubs table is not available.');
        $cache[$cacheKey] = false;
        return;
    }

    $regionIdColumn = api_first_column($db, 'regions', ['region_id', 'id']) ?? 'region_id';
    $regionNameColumn = api_first_column($db, 'regions', ['region_name', 'name']) ?? 'region_name';
    $regionGovernorIdColumn = api_first_column($db, 'regions', ['governor_id']);
    $regionIdSql = api_quote_identifier($regionIdColumn);
    $regionNameSql = api_quote_identifier($regionNameColumn);
    $regionGovernorSql = $regionGovernorIdColumn !== null ? api_quote_identifier($regionGovernorIdColumn) : null;

    $regionRow = api_fetch_one(
        $db,
        'SELECT ' . $regionIdSql . ' AS api_region_id,
                ' . $regionNameSql . ' AS api_region_name,
                ' . ($regionGovernorSql !== null ? $regionGovernorSql : 'NULL') . ' AS api_governor_id
         FROM regions
         WHERE UPPER(' . $regionNameSql . ') = UPPER(:region_name)
         LIMIT 1',
        [':region_name' => $region]
    );

    if ($regionRow === null) {
        member_import_add_catalog_skip($report, $region, $club, 'Region is not encoded yet, so the club cannot be linked to a governor.');
        $cache[$cacheKey] = false;
        return;
    }

    $regionId = (int) ($regionRow['api_region_id'] ?? 0);
    $governorId = (int) ($regionRow['api_governor_id'] ?? 0);

    if ($regionId <= 0 || ($regionGovernorSql !== null && $governorId <= 0)) {
        member_import_add_catalog_skip($report, $region, $club, 'Region has no valid governor assignment.');
        $cache[$cacheKey] = false;
        return;
    }

    $clubIdColumn = api_first_column($db, 'clubs', ['club_id', 'id']) ?? 'club_id';
    $clubNameColumn = api_first_column($db, 'clubs', ['club_name', 'name']) ?? 'club_name';
    $clubRegionIdColumn = api_first_column($db, 'clubs', ['region_id']);
    $clubGovernorIdColumn = api_first_column($db, 'clubs', ['governor_id']);
    $clubIdSql = api_quote_identifier($clubIdColumn);
    $clubNameSql = api_quote_identifier($clubNameColumn);
    $clubRegionSql = $clubRegionIdColumn !== null ? api_quote_identifier($clubRegionIdColumn) : null;
    $clubGovernorSql = $clubGovernorIdColumn !== null ? api_quote_identifier($clubGovernorIdColumn) : null;

    $existingWhere = 'UPPER(' . $clubNameSql . ') = UPPER(:club_name)';
    $existingParams = [':club_name' => $club];
    if ($clubRegionSql !== null) {
        $existingWhere .= ' AND ' . $clubRegionSql . ' = :region_id';
        $existingParams[':region_id'] = $regionId;
    }

    $existing = api_fetch_one(
        $db,
        'SELECT ' . $clubIdSql . ' AS api_club_id
         FROM clubs
         WHERE ' . $existingWhere . '
         LIMIT 1',
        $existingParams
    );

    if ($existing !== null) {
        $report['existing']++;
        $cache[$cacheKey] = true;
        return;
    }

    $insertFields = [$clubNameSql];
    $insertValues = [':club_name'];
    $insertParams = [':club_name' => $club];

    if ($clubRegionSql !== null) {
        $insertFields[] = $clubRegionSql;
        $insertValues[] = ':region_id';
        $insertParams[':region_id'] = $regionId;
    }

    if ($clubGovernorSql !== null) {
        $insertFields[] = $clubGovernorSql;
        $insertValues[] = ':governor_id';
        $insertParams[':governor_id'] = $governorId;
    }

    api_execute(
        $db,
        'INSERT INTO clubs (' . implode(', ', $insertFields) . ')
         VALUES (' . implode(', ', $insertValues) . ')',
        $insertParams
    );

    $report['created']++;
    $report['createdClubs'][] = [
        'region' => (string) ($regionRow['api_region_name'] ?? $region),
        'club' => $club,
        'governorId' => $governorId,
    ];
    $cache[$cacheKey] = true;
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
    $regionalPositionColumn = api_member_regional_position_column($db);
    $regionalPositionSql = api_quote_identifier((string) $regionalPositionColumn);

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

    $headerLine = fgets($handle);
    if ($headerLine === false || trim($headerLine) === '') {
        fclose($handle);
        api_json([
            'ok' => false,
            'message' => 'The uploaded CSV file is empty.',
        ], 422);
    }
    $delimiter = member_import_detect_delimiter($headerLine);
    $headerRow = str_getcsv($headerLine, $delimiter);
    if (!is_array($headerRow) || $headerRow === []) {
        fclose($handle);
        api_json([
            'ok' => false,
            'message' => 'The uploaded CSV file is empty.',
        ], 422);
    }

    $headerMap = [];
    $normalizedHeaders = [];
    foreach ($headerRow as $index => $header) {
        $normalized = member_import_normalize_header((string) $header);
        if ($normalized !== '') {
            $headerMap[$normalized] = $index;
            $normalizedHeaders[] = $normalized;
        }
    }

    $headerAliases = [
        'id' => ['id', 'eagles_id', 'member_id'],
        'first_name' => ['first_name', 'firstname', 'given_name', 'given_names'],
        'last_name' => ['last_name', 'lastname', 'surname', 'family_name'],
        'position' => ['position', 'eagles_position', 'club_position'],
        'club' => ['club', 'eagles_club', 'club_name'],
        'region' => ['region', 'eagles_region', 'region_name'],
        'regional_position' => [
            'regional_position',
            'regional_postion',
            'regionalposition',
            'regional_pos',
            'regional_office',
            'regional_title',
            'regional_officer_position',
        ],
        'status' => ['status', 'eagles_status', 'member_status'],
    ];

    $headerIndexes = [];
    $missingHeaders = [];
    foreach ($headerAliases as $field => $aliases) {
        $index = member_import_header_index($headerMap, $aliases);
        if ($index === null) {
            $missingHeaders[] = $field;
            continue;
        }

        $headerIndexes[$field] = $index;
    }

    if ($missingHeaders !== []) {
        fclose($handle);
        api_json([
            'ok' => false,
            'message' => 'Invalid CSV format. Expected headers: ID, First Name, Last Name, Position, Club, Region, REGIONAL POSITION, Status. Detected: ' . implode(', ', $normalizedHeaders),
            'data' => [
                'missingHeaders' => $missingHeaders,
                'detectedHeaders' => $normalizedHeaders,
            ],
        ], 422);
    }

    $created = 0;
    $updated = 0;
    $skipped = 0;
    $photoAttached = 0;
    $duplicates = [];
    $missingPhotos = [];
    $existingPhotos = [];
    $photoErrors = [];
    $importItems = [];
    $regionalPositionSavedCount = 0;
    $regionalPositionSamples = [];
    $matchedPhotoKeys = [];
    $photoPayload = member_import_photo_payload();
    $photoUploadsById = $photoPayload['photos'];
    $invalidPhotos = $photoPayload['invalid'];
    $seenMemberIds = [];
    $catalogCache = [];
    $catalogReport = [
        'created' => 0,
        'existing' => 0,
        'skipped' => [],
        'createdClubs' => [],
    ];
    $rowNumber = 1;

    $db->beginTransaction();

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $rowNumber++;
        $row = array_map(static fn ($value) => trim((string) $value), $row);
        $joined = trim(implode('', $row));
        if ($joined === '') {
            continue;
        }

        $memberId = strtoupper((string) ($row[$headerIndexes['id']] ?? ''));
        $firstName = strtoupper((string) ($row[$headerIndexes['first_name']] ?? ''));
        $lastName = strtoupper((string) ($row[$headerIndexes['last_name']] ?? ''));
        $position = strtoupper((string) ($row[$headerIndexes['position']] ?? ''));
        $club = strtoupper((string) ($row[$headerIndexes['club']] ?? ''));
        $region = strtoupper((string) ($row[$headerIndexes['region']] ?? ''));
        $regionalPosition = strtoupper((string) ($row[$headerIndexes['regional_position']] ?? ''));
        $status = strtoupper((string) ($row[$headerIndexes['status']] ?? 'ACTIVE'));

        if ($memberId === '') {
            $memberId = member_import_generate_id();
        }

        $memberName = trim($firstName . ' ' . $lastName);

        if ($firstName === '' || $lastName === '' || $position === '' || $club === '' || $region === '' || $regionalPosition === '') {
            $importItems[] = [
                'row' => $rowNumber,
                'id' => $memberId,
                'name' => $memberName,
                'position' => $position,
                'regionalPosition' => $regionalPosition,
                'club' => $club,
                'region' => $region,
                'status' => 'skipped',
                'photoStatus' => 'not_checked',
                'photoFile' => '',
                'reason' => $regionalPosition === ''
                    ? 'Required member fields are incomplete. REGIONAL POSITION is empty or not mapped from the CSV row.'
                    : 'Required member fields are incomplete.',
            ];
            $skipped++;
            continue;
        }

        if ($status === '') {
            $status = 'ACTIVE';
        }

        member_import_ensure_region_club($db, $region, $club, $catalogCache, $catalogReport);

        $photoKey = member_import_photo_key($memberId);
        $photoUpload = $photoUploadsById[$photoKey] ?? null;

        $duplicateInFile = array_key_exists($memberId, $seenMemberIds);
        $seenMemberIds[$memberId] = true;

        $existing = api_fetch_one($db, '
            SELECT eagles_id, eagles_pic
            FROM user_info
            WHERE eagles_id = :eagles_id
            LIMIT 1
        ', [':eagles_id' => $memberId]);

        if ($duplicateInFile) {
            $duplicates[] = [
                'row' => $rowNumber,
                'id' => $memberId,
                'name' => $memberName,
                'position' => $position,
                'regionalPosition' => $regionalPosition,
                'club' => $club,
                'region' => $region,
                'reason' => 'Duplicate ID in this CSV file.',
            ];
            $importItems[] = [
                'row' => $rowNumber,
                'id' => $memberId,
                'name' => $memberName,
                'position' => $position,
                'regionalPosition' => $regionalPosition,
                'club' => $club,
                'region' => $region,
                'status' => 'duplicate',
                'photoStatus' => 'not_checked',
                'photoFile' => '',
                'reason' => 'Duplicate ID in this CSV file.',
            ];
            $skipped++;
            continue;
        }

        if ($existing !== null) {
            $photoStatus = 'missing';
            $photoFile = is_array($photoUpload) ? (string) ($photoUpload['name'] ?? '') : '';
            $photoReason = 'No matching photo uploaded.';
            $nextPhoto = basename(trim((string) ($existing['eagles_pic'] ?? '')));

            if (is_array($photoUpload) && !isset($matchedPhotoKeys[$photoKey])) {
                try {
                    $currentPhoto = basename(trim((string) ($existing['eagles_pic'] ?? '')));
                    $storedPhoto = api_store_uploaded_file_as($photoUpload, 'members', $memberId, api_image_extensions(), true);
                    $nextPhoto = (string) ($storedPhoto['filename'] ?? $nextPhoto);
                    $matchedPhotoKeys[$photoKey] = true;
                    $photoAttached++;
                    $photoStatus = 'attached_to_existing';
                    $photoFile = (string) ($storedPhoto['filename'] ?? $photoFile);
                    $photoReason = 'Photo attached to existing member.';

                    if ($currentPhoto !== '') {
                        $existingPhotos[] = [
                            'row' => $rowNumber,
                            'id' => $memberId,
                            'file' => (string) ($photoUpload['name'] ?? ''),
                            'currentFile' => $currentPhoto,
                            'reason' => 'Member already had an existing photo; uploaded photo replaced it.',
                        ];
                    }
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
                $photoStatus = $nextPhoto !== '' ? 'kept_existing' : 'missing';
                $photoReason = $nextPhoto !== ''
                    ? 'Existing member photo kept.'
                    : 'No matching photo uploaded.';
            }

            api_execute($db, '
                UPDATE user_info
                SET eagles_firstName = :first_name,
                    eagles_lastName = :last_name,
                    eagles_position = :position,
                    eagles_club = :club,
                    eagles_region = :region,
                    ' . $regionalPositionSql . ' = :regional_position,
                    eagles_status = :status,
                    eagles_pic = :pic
                WHERE eagles_id = :eagles_id
            ', [
                ':eagles_id' => $memberId,
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':position' => $position,
                ':club' => $club,
                ':region' => $region,
                ':regional_position' => $regionalPosition,
                ':status' => $status,
                ':pic' => $nextPhoto,
            ]);
            $regionalPositionSave = api_save_member_regional_position($db, $memberId, $regionalPosition);
            $regionalPositionSavedCount++;
            if (count($regionalPositionSamples) < 5) {
                $regionalPositionSamples[] = [
                    'id' => $memberId,
                    'value' => $regionalPositionSave['value'] ?? $regionalPosition,
                    'status' => 'updated',
                ];
            }

            $importItems[] = [
                'row' => $rowNumber,
                'id' => $memberId,
                'name' => $memberName,
                'position' => $position,
                'regionalPosition' => $regionalPosition,
                'club' => $club,
                'region' => $region,
                'status' => 'updated',
                'photoStatus' => $photoStatus,
                'photoFile' => $photoFile,
                'reason' => 'Existing member updated from CSV.',
                'photoReason' => $photoReason,
                'regionalPositionSaved' => $regionalPositionSave['value'] ?? $regionalPosition,
            ];
            $updated++;
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
                ' . $regionalPositionSql . ',
                eagles_club,
                eagles_region,
                eagles_status,
                eagles_pic
            ) VALUES (
                :eagles_id,
                :first_name,
                :last_name,
                :position,
                :regional_position,
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
            ':regional_position' => $regionalPosition,
            ':status' => $status,
            ':pic' => $storedPhoto['filename'] ?? '',
        ]);
        $regionalPositionSave = api_save_member_regional_position($db, $memberId, $regionalPosition);
        $regionalPositionSavedCount++;
        if (count($regionalPositionSamples) < 5) {
            $regionalPositionSamples[] = [
                'id' => $memberId,
                'value' => $regionalPositionSave['value'] ?? $regionalPosition,
                'status' => 'created',
            ];
        }

        $importItems[] = [
            'row' => $rowNumber,
            'id' => $memberId,
            'name' => $memberName,
            'position' => $position,
            'regionalPosition' => $regionalPosition,
            'club' => $club,
            'region' => $region,
            'status' => 'created',
            'photoStatus' => $photoStatus,
            'photoFile' => $photoFile,
            'reason' => 'Member created.',
            'photoReason' => $photoReason,
            'regionalPositionSaved' => $regionalPositionSave['value'] ?? $regionalPosition,
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

        $existingPhotoMember = api_fetch_one($db, '
            SELECT eagles_id, eagles_pic
            FROM user_info
            WHERE eagles_id = :eagles_id
            LIMIT 1
        ', [':eagles_id' => $key]);

        if ($existingPhotoMember !== null) {
            $currentPhoto = basename(trim((string) ($existingPhotoMember['eagles_pic'] ?? '')));
            $existingPhotos[] = [
                'row' => null,
                'id' => (string) ($existingPhotoMember['eagles_id'] ?? $key),
                'file' => (string) ($photo['name'] ?? ''),
                'currentFile' => $currentPhoto,
                'reason' => $currentPhoto !== ''
                    ? 'Member ID already exists and already has a photo.'
                    : 'Member ID already exists.',
            ];
        }

        $unmatchedPhotos[] = [
            'file' => (string) ($photo['name'] ?? ''),
            'expectedId' => $key,
            'reason' => $existingPhotoMember !== null
                ? 'Image filename matches an existing member ID, but no CSV row was imported for it.'
                : 'No CSV/member ID matched this image filename.',
        ];
    }

    api_log_admin_action(
        $db,
        $admin,
        'IMPORT',
        sprintf(
            'Imported members CSV "%s" (%d created, %d updated, %d duplicates, %d skipped, %d photos attached)',
            (string) ($csvFile['name'] ?? 'members.csv'),
            $created,
            $updated,
            count($duplicates),
            $skipped,
            $photoAttached
        )
    );

    api_json([
        'ok' => true,
        'message' => sprintf(
            'CSV import completed. %d created, %d updated, %d duplicate%s, %d skipped, %d photo%s attached.',
            $created,
            $updated,
            count($duplicates),
            count($duplicates) === 1 ? '' : 's',
            $skipped,
            $photoAttached,
            $photoAttached === 1 ? '' : 's'
        ),
        'data' => [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'photosAttached' => $photoAttached,
            'duplicates' => $duplicates,
            'duplicateCount' => count($duplicates),
            'missingPhotos' => $missingPhotos,
            'existingPhotos' => $existingPhotos,
            'unmatchedPhotos' => $unmatchedPhotos,
            'invalidPhotos' => $invalidPhotos,
            'photoErrors' => $photoErrors,
            'items' => $importItems,
            'catalog' => [
                'clubsCreated' => $catalogReport['created'],
                'clubsExisting' => $catalogReport['existing'],
                'clubsSkipped' => $catalogReport['skipped'],
                'createdClubs' => $catalogReport['createdClubs'],
            ],
            'importDiagnostics' => [
                'regionalPositionColumn' => $regionalPositionColumn,
                'regionalPositionHeader' => (string) ($headerRow[$headerIndexes['regional_position']] ?? ''),
                'regionalPositionSaved' => $regionalPositionSavedCount,
                'regionalPositionSamples' => $regionalPositionSamples,
                'detectedDelimiter' => $delimiter === "\t" ? 'tab' : $delimiter,
                'detectedHeaders' => $normalizedHeaders,
            ],
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
    $message = str_contains($error->getMessage(), 'Regional position')
        ? $error->getMessage()
        : 'Unable to import members right now.';
    api_json([
        'ok' => false,
        'message' => $message,
    ], 500);
}
