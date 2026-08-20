<?php

declare(strict_types=1);

require_once __DIR__ . '/../admin-tool-auth.php';

api_start();

if (!in_array(api_request_method(), ['GET', 'HEAD', 'POST'], true)) {
    api_json([
        'success' => false,
        'message' => 'Method not allowed.',
        'allowedMethods' => ['GET', 'HEAD', 'POST'],
    ], 405);
}

$db = api_db();
$format = strtolower(trim((string) ($_GET['format'] ?? 'html')));
$admin = api_tool_require_admin($db, 'Database Diagram');
$access = [
    'label' => (string) ($admin['role_label'] ?? 'Admin'),
    'user' => (string) ($admin['username'] ?? $admin['name'] ?? ''),
];

function db_diagram_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function db_diagram_singular(string $name): string
{
    if (str_ends_with($name, 'ies')) {
        return substr($name, 0, -3) . 'y';
    }

    if (str_ends_with($name, 'ses')) {
        return substr($name, 0, -2);
    }

    if (str_ends_with($name, 's') && !str_ends_with($name, 'ss')) {
        return substr($name, 0, -1);
    }

    return $name;
}

function db_diagram_slug(string $value): string
{
    $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9_-]+/', '-', $value));
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'item';
}

function db_diagram_format_bytes(int|float|null $bytes): string
{
    $size = (float) ($bytes ?? 0);
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $index = 0;

    while ($size >= 1024 && $index < count($units) - 1) {
        $size /= 1024;
        $index++;
    }

    return $index === 0 ? (string) ((int) $size) . ' ' . $units[$index] : number_format($size, 1) . ' ' . $units[$index];
}

function db_diagram_column_badge(array $column): string
{
    $key = strtoupper((string) ($column['key'] ?? ''));
    if ($key === 'PRI') {
        return 'PK';
    }
    if ($key === 'MUL') {
        return 'IDX';
    }
    if ($key === 'UNI') {
        return 'UNQ';
    }

    return '';
}

function db_diagram_candidate_targets(string $columnName): array
{
    $aliases = [
        'admin' => ['admins', 'users'],
        'admin_user' => ['users', 'admins'],
        'appointed' => ['appointed'],
        'category' => ['forum_categories'],
        'club' => ['clubs'],
        'event' => ['events'],
        'governor' => ['governors'],
        'last_reply_user' => ['users'],
        'magna_carta' => ['magna_carta_items'],
        'member' => ['user_info', 'members'],
        'memo' => ['memorandum'],
        'memorandum' => ['memorandum'],
        'news' => ['news_info', 'news'],
        'officer' => ['officers'],
        'parent_post' => ['forum_posts'],
        'past_leader' => ['past_leaders'],
        'post' => ['forum_posts'],
        'president' => ['presidents'],
        'region' => ['regions'],
        'reply_user' => ['users'],
        'thread' => ['forum_threads'],
        'user' => ['users', 'user_info'],
        'video' => ['video_info', 'videos'],
    ];

    if (!str_ends_with($columnName, '_id') || $columnName === 'id') {
        return [];
    }

    $base = substr($columnName, 0, -3);
    $parts = explode('_', $base);
    $tail = (string) end($parts);
    $candidates = [];

    foreach ([$base, $tail] as $key) {
        foreach ($aliases[$key] ?? [] as $tableName) {
            $candidates[] = $tableName;
        }
    }

    $candidates[] = $base;
    $candidates[] = $base . 's';
    $candidates[] = $base . 'es';
    $candidates[] = $tail;
    $candidates[] = $tail . 's';
    $candidates[] = $tail . 'es';

    return array_values(array_unique(array_filter($candidates)));
}

function db_diagram_relationship_key(array $relationship): string
{
    return implode('|', [
        (string) ($relationship['sourceTable'] ?? ''),
        (string) ($relationship['sourceColumn'] ?? ''),
        (string) ($relationship['targetTable'] ?? ''),
        (string) ($relationship['targetColumn'] ?? ''),
    ]);
}

function db_diagram_fetch_schema(PDO $db): array
{
    $database = (string) (api_fetch_one($db, 'SELECT DATABASE() AS database_name')['database_name'] ?? '');

    $tableRows = api_fetch_all($db, '
        SELECT
            table_name,
            table_type,
            engine,
            table_rows,
            data_length,
            index_length,
            create_time,
            update_time,
            table_collation,
            table_comment
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        ORDER BY table_name ASC
    ');

    $columnRows = api_fetch_all($db, '
        SELECT
            table_name,
            column_name,
            ordinal_position,
            column_type,
            data_type,
            is_nullable,
            column_default,
            column_key,
            extra,
            character_maximum_length,
            numeric_precision,
            numeric_scale,
            column_comment
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
        ORDER BY table_name ASC, ordinal_position ASC
    ');

    $indexRows = api_fetch_all($db, '
        SELECT
            table_name,
            index_name,
            non_unique,
            seq_in_index,
            column_name,
            index_type
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
        ORDER BY table_name ASC, index_name ASC, seq_in_index ASC
    ');

    $foreignKeyRows = api_fetch_all($db, '
        SELECT
            k.constraint_name,
            k.table_name,
            k.column_name,
            k.referenced_table_name,
            k.referenced_column_name,
            rc.update_rule,
            rc.delete_rule
        FROM information_schema.key_column_usage k
        LEFT JOIN information_schema.referential_constraints rc
            ON rc.constraint_schema = k.constraint_schema
           AND rc.constraint_name = k.constraint_name
           AND rc.table_name = k.table_name
        WHERE k.table_schema = DATABASE()
          AND k.referenced_table_name IS NOT NULL
        ORDER BY k.table_name ASC, k.constraint_name ASC, k.ordinal_position ASC
    ');

    $columnsByTable = [];
    $primaryKeysByTable = [];
    $columnNamesByTable = [];

    foreach ($columnRows as $row) {
        $tableName = (string) ($row['table_name'] ?? '');
        $columnName = (string) ($row['column_name'] ?? '');
        if ($tableName === '' || $columnName === '') {
            continue;
        }

        $column = [
            'name' => $columnName,
            'type' => (string) ($row['column_type'] ?? ''),
            'dataType' => (string) ($row['data_type'] ?? ''),
            'nullable' => strtoupper((string) ($row['is_nullable'] ?? 'YES')) === 'YES',
            'default' => $row['column_default'],
            'key' => (string) ($row['column_key'] ?? ''),
            'extra' => (string) ($row['extra'] ?? ''),
            'comment' => (string) ($row['column_comment'] ?? ''),
            'ordinal' => (int) ($row['ordinal_position'] ?? 0),
        ];

        $columnsByTable[$tableName][] = $column;
        $columnNamesByTable[$tableName][$columnName] = true;

        if (strtoupper((string) ($row['column_key'] ?? '')) === 'PRI') {
            $primaryKeysByTable[$tableName][] = $columnName;
        }
    }

    $indexesByTable = [];
    foreach ($indexRows as $row) {
        $tableName = (string) ($row['table_name'] ?? '');
        $indexName = (string) ($row['index_name'] ?? '');
        $columnName = (string) ($row['column_name'] ?? '');
        if ($tableName === '' || $indexName === '' || $columnName === '') {
            continue;
        }

        if (!isset($indexesByTable[$tableName][$indexName])) {
            $indexesByTable[$tableName][$indexName] = [
                'name' => $indexName,
                'unique' => (int) ($row['non_unique'] ?? 1) === 0,
                'type' => (string) ($row['index_type'] ?? ''),
                'columns' => [],
            ];
        }

        $indexesByTable[$tableName][$indexName]['columns'][] = $columnName;
    }

    foreach ($indexesByTable as $tableName => $indexes) {
        $indexesByTable[$tableName] = array_values($indexes);
    }

    $tables = [];
    $tableNames = [];
    foreach ($tableRows as $row) {
        $tableName = (string) ($row['table_name'] ?? '');
        if ($tableName === '') {
            continue;
        }

        $tableNames[] = $tableName;
        $tables[] = [
            'name' => $tableName,
            'type' => (string) ($row['table_type'] ?? ''),
            'engine' => (string) ($row['engine'] ?? ''),
            'rows' => isset($row['table_rows']) ? (int) $row['table_rows'] : null,
            'dataLength' => isset($row['data_length']) ? (int) $row['data_length'] : 0,
            'indexLength' => isset($row['index_length']) ? (int) $row['index_length'] : 0,
            'createdAt' => $row['create_time'] !== null ? (string) $row['create_time'] : null,
            'updatedAt' => $row['update_time'] !== null ? (string) $row['update_time'] : null,
            'collation' => (string) ($row['table_collation'] ?? ''),
            'comment' => (string) ($row['table_comment'] ?? ''),
            'primaryKeys' => $primaryKeysByTable[$tableName] ?? [],
            'columns' => $columnsByTable[$tableName] ?? [],
            'indexes' => $indexesByTable[$tableName] ?? [],
        ];
    }

    $tableNameLookup = array_fill_keys($tableNames, true);
    $relationships = [];

    foreach ($foreignKeyRows as $row) {
        $relationship = [
            'kind' => 'foreign-key',
            'constraint' => (string) ($row['constraint_name'] ?? ''),
            'sourceTable' => (string) ($row['table_name'] ?? ''),
            'sourceColumn' => (string) ($row['column_name'] ?? ''),
            'targetTable' => (string) ($row['referenced_table_name'] ?? ''),
            'targetColumn' => (string) ($row['referenced_column_name'] ?? ''),
            'updateRule' => (string) ($row['update_rule'] ?? ''),
            'deleteRule' => (string) ($row['delete_rule'] ?? ''),
        ];

        if ($relationship['sourceTable'] !== '' && $relationship['targetTable'] !== '') {
            $relationships[db_diagram_relationship_key($relationship)] = $relationship;
        }
    }

    foreach ($columnsByTable as $sourceTable => $columns) {
        foreach ($columns as $column) {
            $sourceColumn = (string) ($column['name'] ?? '');
            if ($sourceColumn === '') {
                continue;
            }

            $candidateTables = db_diagram_candidate_targets($sourceColumn);
            foreach ($candidateTables as $targetTable) {
                if (!isset($tableNameLookup[$targetTable])) {
                    continue;
                }

                $targetColumns = array_keys($columnNamesByTable[$targetTable] ?? []);
                $targetSingular = db_diagram_singular($targetTable);
                $candidateColumns = array_values(array_unique(array_filter([
                    $sourceColumn,
                    'id',
                    $targetSingular . '_id',
                    $targetTable . '_id',
                    db_diagram_singular(str_replace('_info', '', $targetTable)) . '_id',
                ])));

                foreach ($candidateColumns as $targetColumn) {
                    if (!in_array($targetColumn, $targetColumns, true)) {
                        continue;
                    }

                    $relationship = [
                        'kind' => 'inferred',
                        'constraint' => '',
                        'sourceTable' => $sourceTable,
                        'sourceColumn' => $sourceColumn,
                        'targetTable' => $targetTable,
                        'targetColumn' => $targetColumn,
                        'updateRule' => '',
                        'deleteRule' => '',
                    ];

                    $key = db_diagram_relationship_key($relationship);
                    if (!isset($relationships[$key])) {
                        $relationships[$key] = $relationship;
                    }

                    break 2;
                }
            }
        }
    }

    $relationships = array_values($relationships);
    usort($relationships, static function (array $a, array $b): int {
        return [$a['sourceTable'], $a['sourceColumn'], $a['targetTable']]
            <=> [$b['sourceTable'], $b['sourceColumn'], $b['targetTable']];
    });

    $totalColumns = array_sum(array_map(static fn (array $table): int => count($table['columns']), $tables));
    $totalSize = array_sum(array_map(static fn (array $table): int => (int) $table['dataLength'] + (int) $table['indexLength'], $tables));

    return [
        'database' => $database,
        'generatedAt' => date(DATE_ATOM),
        'summary' => [
            'tables' => count($tables),
            'columns' => $totalColumns,
            'relationships' => count($relationships),
            'foreignKeys' => count(array_filter($relationships, static fn (array $item): bool => $item['kind'] === 'foreign-key')),
            'inferredLinks' => count(array_filter($relationships, static fn (array $item): bool => $item['kind'] === 'inferred')),
            'sizeBytes' => $totalSize,
        ],
        'tables' => $tables,
        'relationships' => $relationships,
    ];
}

try {
    $schema = db_diagram_fetch_schema($db);
} catch (Throwable $error) {
    if ($format === 'json') {
        api_json([
            'success' => false,
            'message' => 'Unable to inspect the database schema.',
        ], 500);
    }

    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Database Diagram Error</title>
  <style>
    body { margin: 0; padding: 32px; font: 15px/1.5 "Segoe UI", Arial, sans-serif; color: #172033; background: #f5f7fb; }
    main { max-width: 760px; margin: 0 auto; padding: 24px; border: 1px solid #dbe2ec; border-radius: 8px; background: #fff; }
    h1 { margin-top: 0; }
    code { color: #b42318; }
  </style>
</head>
<body>
  <main>
    <h1>Unable to inspect the database schema</h1>
    <p>The database connection is working, but schema inspection failed.</p>
    <p><code><?= db_diagram_h($error->getMessage()) ?></code></p>
  </main>
</body>
</html>
    <?php
    exit;
}

if ($format === 'json') {
    api_json([
        'database' => $schema['database'],
        'generatedAt' => $schema['generatedAt'],
        'access' => $access,
        'summary' => $schema['summary'],
        'tables' => $schema['tables'],
        'relationships' => $schema['relationships'],
    ]);
}

$tables = $schema['tables'];
$relationships = $schema['relationships'];
$summary = $schema['summary'];

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Database Diagram | <?= db_diagram_h($schema['database']) ?></title>
  <style>
    :root {
      color-scheme: light;
      --bg: #f4f6fa;
      --panel: #ffffff;
      --ink: #172033;
      --muted: #647084;
      --line: #d9e1ec;
      --soft-line: #edf1f6;
      --brand: #8a640b;
      --brand-dark: #624709;
      --green: #16794c;
      --red: #b42318;
      --blue: #245fb2;
      --shadow: 0 14px 45px rgba(23, 32, 51, 0.08);
      --api-sidebar-bg: #ffffff;
      --api-sidebar-border: var(--line);
      --api-sidebar-text: var(--ink);
      --api-sidebar-muted: var(--muted);
      --api-sidebar-link: #344054;
      --api-sidebar-hover-bg: rgba(138, 100, 11, 0.08);
      --api-sidebar-active-bg: var(--brand);
      --api-sidebar-active-border: var(--brand);
      --api-sidebar-active-text: #ffffff;
      --api-sidebar-shadow: var(--shadow);
      --api-sidebar-hint-bg: #f8fafc;
    }
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body {
      margin: 0;
      color: var(--ink);
      background: var(--bg);
      font: 14px/1.45 "Inter", "Segoe UI", Arial, sans-serif;
    }
    a { color: inherit; }
    .shell {
      width: min(1680px, calc(100% - 32px));
      margin: 0 auto;
      padding: 24px 0 40px;
    }
    <?= api_tool_sidebar_styles() ?>
    .topbar {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 18px;
    }
    .eyebrow {
      margin: 0 0 6px;
      color: var(--brand);
      font-size: 12px;
      font-weight: 800;
      letter-spacing: .08em;
      text-transform: uppercase;
    }
    h1 {
      margin: 0;
      font-size: clamp(28px, 4vw, 46px);
      line-height: 1.05;
      letter-spacing: 0;
    }
    .subtitle {
      max-width: 760px;
      margin: 10px 0 0;
      color: var(--muted);
      font-size: 15px;
    }
    .actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      justify-content: flex-end;
    }
    .button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 38px;
      padding: 0 12px;
      border: 1px solid var(--line);
      border-radius: 6px;
      color: var(--ink);
      background: var(--panel);
      text-decoration: none;
      font-weight: 700;
      cursor: pointer;
    }
    .button.primary {
      border-color: var(--brand);
      color: #fff;
      background: var(--brand);
    }
    .button:hover { border-color: var(--brand); }
    .meta-strip {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin: 18px 0;
    }
    .meta-chip {
      display: inline-flex;
      gap: 8px;
      align-items: center;
      min-height: 34px;
      padding: 0 11px;
      border: 1px solid var(--line);
      border-radius: 999px;
      color: var(--muted);
      background: var(--panel);
    }
    .meta-chip strong { color: var(--ink); }
    .stats {
      display: grid;
      grid-template-columns: repeat(6, minmax(0, 1fr));
      gap: 10px;
      margin-bottom: 18px;
    }
    .stat {
      min-height: 86px;
      padding: 14px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: var(--panel);
      box-shadow: var(--shadow);
    }
    .stat strong {
      display: block;
      font-size: 24px;
      line-height: 1.1;
    }
    .stat span {
      display: block;
      margin-top: 8px;
      color: var(--muted);
      font-size: 12px;
      font-weight: 800;
      text-transform: uppercase;
    }
    .tools {
      position: sticky;
      top: 0;
      z-index: 20;
      display: grid;
      grid-template-columns: minmax(220px, 1fr) auto;
      gap: 10px;
      margin: 0 -1px 18px;
      padding: 12px 0;
      background: rgba(244, 246, 250, 0.92);
      backdrop-filter: blur(10px);
    }
    .search {
      display: flex;
      align-items: center;
      min-height: 42px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: var(--panel);
      overflow: hidden;
    }
    .search span {
      padding-left: 13px;
      color: var(--muted);
      font-weight: 900;
    }
    .search input {
      width: 100%;
      min-width: 0;
      border: 0;
      outline: 0;
      padding: 0 13px;
      font: inherit;
      background: transparent;
    }
    .layout {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(320px, 380px);
      gap: 18px;
      align-items: start;
    }
    .panel {
      border: 1px solid var(--line);
      border-radius: 8px;
      background: var(--panel);
      box-shadow: var(--shadow);
    }
    .panel-head {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      padding: 14px 16px;
      border-bottom: 1px solid var(--line);
    }
    .panel-head h2 {
      margin: 0;
      font-size: 16px;
    }
    .panel-head span {
      color: var(--muted);
      font-size: 12px;
      font-weight: 800;
      text-transform: uppercase;
    }
    .diagram-canvas {
      position: relative;
      min-height: 520px;
      padding: 14px;
      overflow: hidden;
    }
    .relationship-lines {
      position: absolute;
      inset: 0;
      z-index: 1;
      width: 100%;
      height: 100%;
      pointer-events: none;
    }
    .table-grid {
      position: relative;
      z-index: 2;
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(275px, 1fr));
      gap: 12px;
    }
    .table-card {
      border: 1px solid var(--line);
      border-radius: 8px;
      background: rgba(255, 255, 255, 0.96);
      overflow: hidden;
      transition: border-color .15s ease, transform .15s ease, box-shadow .15s ease;
    }
    .table-card:hover {
      border-color: var(--brand);
      transform: translateY(-1px);
      box-shadow: 0 10px 28px rgba(23, 32, 51, 0.10);
    }
    .table-card.hidden,
    .relationship-row.hidden {
      display: none;
    }
    .table-title {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 10px;
      padding: 12px;
      border-bottom: 1px solid var(--soft-line);
      background: #fbfcfe;
    }
    .table-title h3 {
      margin: 0;
      font-size: 15px;
      overflow-wrap: anywhere;
    }
    .table-title small {
      display: block;
      margin-top: 4px;
      color: var(--muted);
    }
    .size {
      white-space: nowrap;
      color: var(--blue);
      font-size: 12px;
      font-weight: 800;
    }
    .columns {
      max-height: 270px;
      overflow: auto;
    }
    .column {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 8px;
      padding: 8px 12px;
      border-bottom: 1px solid var(--soft-line);
    }
    .column:last-child { border-bottom: 0; }
    .column-main {
      min-width: 0;
    }
    .column-name {
      display: flex;
      align-items: center;
      gap: 6px;
      min-width: 0;
      font-weight: 800;
      overflow-wrap: anywhere;
    }
    .badge {
      flex: 0 0 auto;
      min-width: 30px;
      padding: 2px 5px;
      border-radius: 5px;
      color: #fff;
      background: var(--green);
      font-size: 10px;
      line-height: 1.2;
      text-align: center;
    }
    .badge.idx { background: var(--blue); }
    .badge.unq { background: var(--brand); }
    .column-type {
      display: block;
      margin-top: 3px;
      color: var(--muted);
      font-size: 12px;
      overflow-wrap: anywhere;
    }
    .nullable {
      align-self: center;
      color: var(--muted);
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
    }
    .not-null { color: var(--red); }
    .relationship-list {
      max-height: calc(100vh - 220px);
      overflow: auto;
      padding: 10px;
    }
    .relationship-row {
      display: grid;
      gap: 7px;
      padding: 10px;
      border: 1px solid var(--soft-line);
      border-radius: 8px;
      margin-bottom: 8px;
      background: #fff;
    }
    .relationship-kind {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      color: var(--muted);
      font-size: 11px;
      font-weight: 900;
      text-transform: uppercase;
    }
    .kind-foreign-key { color: var(--green); }
    .kind-inferred { color: var(--brand); }
    .relationship-path {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
      gap: 8px;
      align-items: center;
      font-weight: 800;
    }
    .relationship-path code {
      display: block;
      padding: 7px;
      border-radius: 6px;
      background: #f5f7fb;
      color: var(--ink);
      overflow-wrap: anywhere;
      font: 12px/1.35 "Consolas", "SFMono-Regular", monospace;
    }
    .arrow {
      color: var(--muted);
      font-weight: 900;
    }
    .indexes {
      padding: 10px 12px 12px;
      border-top: 1px solid var(--soft-line);
      color: var(--muted);
      font-size: 12px;
    }
    .indexes strong {
      display: block;
      margin-bottom: 5px;
      color: var(--ink);
      font-size: 11px;
      text-transform: uppercase;
    }
    .index-line {
      margin-top: 4px;
      overflow-wrap: anywhere;
    }
    .empty {
      padding: 18px;
      color: var(--muted);
    }
    @media (max-width: 980px) {
      .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .layout { grid-template-columns: 1fr; }
      .relationship-list { max-height: none; }
      .tools { grid-template-columns: 1fr; }
      .topbar { display: block; }
      .actions { justify-content: flex-start; margin-top: 14px; }
    }
    @media print {
      .actions, .tools, .relationship-lines { display: none; }
      body { background: #fff; }
      .shell { width: 100%; padding: 0; }
      .layout { grid-template-columns: 1fr; }
      .panel, .stat { box-shadow: none; }
      .columns, .relationship-list { max-height: none; }
    }
  </style>
</head>
<body>
  <div class="shell">
    <div class="api-tool-layout">
      <?= api_tool_sidebar('database-diagram') ?>
      <div class="api-tool-main">
    <header class="topbar">
      <div>
        <p class="eyebrow">TFOEPE API Schema</p>
        <h1>Database Diagram</h1>
        <p class="subtitle">
          Live schema map for <strong><?= db_diagram_h($schema['database']) ?></strong>. Solid green links are real foreign keys; gold links are inferred from ID-style columns.
        </p>
      </div>
      <nav class="actions" aria-label="Page actions">
        <a class="button" href="../dashboard.php">Dashboard</a>
        <a class="button" href="?format=json">Export JSON</a>
        <button class="button" type="button" onclick="window.print()">Print</button>
        <?= api_tool_logout_form('button', 'Sign out') ?>
        <a class="button primary" href="./">Refresh</a>
      </nav>
    </header>

    <div class="meta-strip">
      <span class="meta-chip">Generated <strong><?= db_diagram_h(date('M j, Y g:i A', strtotime((string) $schema['generatedAt']))) ?></strong></span>
      <span class="meta-chip">Access <strong><?= db_diagram_h($access['label']) ?></strong></span>
      <?php if (!empty($access['user'])): ?>
        <span class="meta-chip">User <strong><?= db_diagram_h($access['user']) ?></strong></span>
      <?php endif; ?>
    </div>

    <section class="stats" aria-label="Database summary">
      <div class="stat"><strong><?= number_format((int) $summary['tables']) ?></strong><span>Tables</span></div>
      <div class="stat"><strong><?= number_format((int) $summary['columns']) ?></strong><span>Columns</span></div>
      <div class="stat"><strong><?= number_format((int) $summary['relationships']) ?></strong><span>Relationships</span></div>
      <div class="stat"><strong><?= number_format((int) $summary['foreignKeys']) ?></strong><span>Foreign keys</span></div>
      <div class="stat"><strong><?= number_format((int) $summary['inferredLinks']) ?></strong><span>Inferred links</span></div>
      <div class="stat"><strong><?= db_diagram_h(db_diagram_format_bytes((int) $summary['sizeBytes'])) ?></strong><span>Approx size</span></div>
    </section>

    <section class="tools" aria-label="Database diagram tools">
      <label class="search" for="schema-search">
        <span>Search</span>
        <input id="schema-search" type="search" placeholder="Table, column, or relationship..." autocomplete="off">
      </label>
      <button class="button" type="button" id="clear-search">Clear</button>
    </section>

    <main class="layout">
      <section class="panel">
        <div class="panel-head">
          <h2>Tables and Columns</h2>
          <span id="visible-table-count"><?= number_format(count($tables)) ?> visible</span>
        </div>
        <div class="diagram-canvas" id="diagram-canvas">
          <svg class="relationship-lines" id="relationship-lines" aria-hidden="true"></svg>
          <div class="table-grid" id="table-grid">
            <?php foreach ($tables as $table): ?>
              <?php
              $tableName = (string) $table['name'];
              $tableSlug = db_diagram_slug($tableName);
              $columnSearch = implode(' ', array_map(static fn (array $column): string => (string) $column['name'] . ' ' . (string) $column['type'], $table['columns']));
              ?>
              <article
                class="table-card"
                id="table-<?= db_diagram_h($tableSlug) ?>"
                data-table="<?= db_diagram_h($tableName) ?>"
                data-search="<?= db_diagram_h(strtolower($tableName . ' ' . $columnSearch)) ?>"
              >
                <header class="table-title">
                  <div>
                    <h3><?= db_diagram_h($tableName) ?></h3>
                    <small>
                      <?= number_format((int) ($table['rows'] ?? 0)) ?> rows,
                      <?= count($table['columns']) ?> columns
                      <?php if (!empty($table['engine'])): ?>
                        , <?= db_diagram_h($table['engine']) ?>
                      <?php endif; ?>
                    </small>
                  </div>
                  <span class="size"><?= db_diagram_h(db_diagram_format_bytes((int) $table['dataLength'] + (int) $table['indexLength'])) ?></span>
                </header>
                <div class="columns">
                  <?php foreach ($table['columns'] as $column): ?>
                    <?php
                    $badge = db_diagram_column_badge($column);
                    $badgeClass = strtolower($badge);
                    ?>
                    <div class="column">
                      <div class="column-main">
                        <span class="column-name">
                          <?php if ($badge !== ''): ?>
                            <span class="badge <?= db_diagram_h($badgeClass) ?>"><?= db_diagram_h($badge) ?></span>
                          <?php endif; ?>
                          <?= db_diagram_h($column['name']) ?>
                        </span>
                        <span class="column-type">
                          <?= db_diagram_h($column['type']) ?>
                          <?php if (!empty($column['extra'])): ?>
                            . <?= db_diagram_h($column['extra']) ?>
                          <?php endif; ?>
                        </span>
                      </div>
                      <span class="nullable <?= !$column['nullable'] ? 'not-null' : '' ?>">
                        <?= $column['nullable'] ? 'null' : 'not null' ?>
                      </span>
                    </div>
                  <?php endforeach; ?>
                </div>
                <?php if (!empty($table['indexes'])): ?>
                  <footer class="indexes">
                    <strong>Indexes</strong>
                    <?php foreach ($table['indexes'] as $index): ?>
                      <div class="index-line">
                        <?= db_diagram_h($index['unique'] ? 'unique' : 'index') ?>:
                        <code><?= db_diagram_h($index['name']) ?></code>
                        (<?= db_diagram_h(implode(', ', $index['columns'])) ?>)
                      </div>
                    <?php endforeach; ?>
                  </footer>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <aside class="panel">
        <div class="panel-head">
          <h2>Connectivity</h2>
          <span id="visible-relationship-count"><?= number_format(count($relationships)) ?> links</span>
        </div>
        <div class="relationship-list" id="relationship-list">
          <?php if (empty($relationships)): ?>
            <div class="empty">No foreign keys or inferred ID relationships were found.</div>
          <?php endif; ?>
          <?php foreach ($relationships as $relationship): ?>
            <?php
            $kind = (string) $relationship['kind'];
            $source = (string) $relationship['sourceTable'] . '.' . (string) $relationship['sourceColumn'];
            $target = (string) $relationship['targetTable'] . '.' . (string) $relationship['targetColumn'];
            $search = strtolower($kind . ' ' . $source . ' ' . $target . ' ' . (string) $relationship['constraint']);
            ?>
            <article
              class="relationship-row"
              data-source="<?= db_diagram_h($relationship['sourceTable']) ?>"
              data-target="<?= db_diagram_h($relationship['targetTable']) ?>"
              data-search="<?= db_diagram_h($search) ?>"
            >
              <div class="relationship-kind">
                <span class="kind-<?= db_diagram_h($kind) ?>"><?= db_diagram_h(str_replace('-', ' ', $kind)) ?></span>
                <?php if (!empty($relationship['constraint'])): ?>
                  <span><?= db_diagram_h($relationship['constraint']) ?></span>
                <?php endif; ?>
              </div>
              <div class="relationship-path">
                <code><?= db_diagram_h($source) ?></code>
                <span class="arrow">to</span>
                <code><?= db_diagram_h($target) ?></code>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </aside>
    </main>
      </div>
    </div>
  </div>

  <script>
    const relationships = <?= json_encode($relationships, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const slug = (value) => String(value || '').toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '') || 'item';
    const canvas = document.getElementById('diagram-canvas');
    const svg = document.getElementById('relationship-lines');
    const searchInput = document.getElementById('schema-search');
    const clearButton = document.getElementById('clear-search');
    const tableCards = Array.from(document.querySelectorAll('.table-card'));
    const relationshipRows = Array.from(document.querySelectorAll('.relationship-row'));
    const visibleTableCount = document.getElementById('visible-table-count');
    const visibleRelationshipCount = document.getElementById('visible-relationship-count');

    function cardForTable(tableName) {
      return document.getElementById(`table-${slug(tableName)}`);
    }

    function visible(element) {
      return element && !element.classList.contains('hidden');
    }

    function drawRelationshipLines() {
      if (!canvas || !svg) return;

      const width = Math.max(canvas.scrollWidth, canvas.clientWidth);
      const height = Math.max(canvas.scrollHeight, canvas.clientHeight);
      svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
      svg.setAttribute('width', String(width));
      svg.setAttribute('height', String(height));
      svg.replaceChildren();

      relationships.forEach((relationship) => {
        const source = cardForTable(relationship.sourceTable);
        const target = cardForTable(relationship.targetTable);
        if (!visible(source) || !visible(target)) return;

        const x1 = source.offsetLeft + source.offsetWidth / 2;
        const y1 = source.offsetTop + Math.min(source.offsetHeight / 2, 90);
        const x2 = target.offsetLeft + target.offsetWidth / 2;
        const y2 = target.offsetTop + Math.min(target.offsetHeight / 2, 90);
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        const isSelf = source === target;

        if (isSelf) {
          const right = source.offsetLeft + source.offsetWidth - 18;
          const top = source.offsetTop + 34;
          path.setAttribute('d', `M ${right} ${top} C ${right + 48} ${top - 18}, ${right + 48} ${top + 58}, ${right} ${top + 42}`);
        } else {
          const curve = Math.max(44, Math.abs(x2 - x1) * 0.25);
          path.setAttribute('d', `M ${x1} ${y1} C ${x1 + curve} ${y1}, ${x2 - curve} ${y2}, ${x2} ${y2}`);
        }

        path.setAttribute('fill', 'none');
        path.setAttribute('stroke', relationship.kind === 'foreign-key' ? '#16794c' : '#8a640b');
        path.setAttribute('stroke-width', relationship.kind === 'foreign-key' ? '2.1' : '1.4');
        path.setAttribute('stroke-linecap', 'round');
        path.setAttribute('opacity', relationship.kind === 'foreign-key' ? '0.68' : '0.42');
        path.setAttribute('stroke-dasharray', relationship.kind === 'foreign-key' ? '' : '6 7');
        svg.append(path);
      });
    }

    function applyFilter() {
      const query = searchInput.value.trim().toLowerCase();
      let tableCount = 0;
      let relationshipCount = 0;

      tableCards.forEach((card) => {
        const matched = !query || card.dataset.search.includes(query);
        card.classList.toggle('hidden', !matched);
        if (matched) tableCount++;
      });

      relationshipRows.forEach((row) => {
        const sourceVisible = visible(cardForTable(row.dataset.source));
        const targetVisible = visible(cardForTable(row.dataset.target));
        const matched = (!query || row.dataset.search.includes(query)) && sourceVisible && targetVisible;
        row.classList.toggle('hidden', !matched);
        if (matched) relationshipCount++;
      });

      visibleTableCount.textContent = `${tableCount.toLocaleString()} visible`;
      visibleRelationshipCount.textContent = `${relationshipCount.toLocaleString()} links`;
      window.requestAnimationFrame(drawRelationshipLines);
    }

    searchInput.addEventListener('input', applyFilter);
    clearButton.addEventListener('click', () => {
      searchInput.value = '';
      searchInput.focus();
      applyFilter();
    });
    window.addEventListener('resize', () => window.requestAnimationFrame(drawRelationshipLines));
    window.addEventListener('load', drawRelationshipLines);
    drawRelationshipLines();
  </script>
</body>
</html>
