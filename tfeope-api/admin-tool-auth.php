<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (!function_exists('api_tool_auth_h')) {
    function api_tool_auth_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('api_tool_auth_wants_json')) {
    function api_tool_auth_wants_json(): bool
    {
        $format = strtolower(trim((string) ($_GET['format'] ?? '')));
        $ajax = trim((string) ($_GET['ajax'] ?? ''));
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

        return $format === 'json'
            || $ajax !== ''
            || $requestedWith === 'xmlhttprequest'
            || str_contains($accept, 'application/json');
    }
}

if (!function_exists('api_tool_auth_payload')) {
    function api_tool_auth_payload(): array
    {
        if (!empty($_POST)) {
            return $_POST;
        }

        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            return api_request_data();
        }

        return [];
    }
}

if (!function_exists('api_tool_auth_request_uri')) {
    function api_tool_auth_request_uri(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if ($uri !== '') {
            return $uri;
        }

        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        return $scriptName !== '' ? $scriptName : './';
    }
}

if (!function_exists('api_tool_auth_redirect_to_get')) {
    function api_tool_auth_redirect_to_get(?string $target = null): never
    {
        $target = $target ?: api_tool_auth_request_uri();
        header('Location: ' . $target, true, 303);
        exit;
    }
}

if (!function_exists('api_tool_auth_clear_session')) {
    function api_tool_auth_clear_session(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool) ($params['secure'] ?? false),
                (bool) ($params['httponly'] ?? true)
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}

if (!function_exists('api_tool_auth_attempt_login')) {
    function api_tool_auth_attempt_login(PDO $db, array $payload, string $toolName): array
    {
        $username = trim((string) ($payload['username'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($username === '' || $password === '') {
            return [
                'ok' => false,
                'message' => 'Please enter your admin username and password.',
            ];
        }

        if (!api_table_exists($db, 'users')) {
            return [
                'ok' => false,
                'message' => 'Admin users table not found.',
            ];
        }

        $user = api_fetch_one($db, '
            SELECT id, name, username, password_hash, role_id
            FROM users
            WHERE username = :username
            LIMIT 1
        ', [':username' => $username]);

        $roleId = (int) ($user['role_id'] ?? 0);
        $passwordHash = (string) ($user['password_hash'] ?? '');
        $validRole = $roleId === 1;

        if (
            $user === null
            || !$validRole
            || $passwordHash === ''
            || !password_verify($password, $passwordHash)
        ) {
            return [
                'ok' => false,
                'message' => 'Only Super Admin accounts can access API tools.',
            ];
        }

        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) ($user['id'] ?? 0);
        $_SESSION['user_name'] = (string) ($user['name'] ?? '');
        $_SESSION['username'] = (string) ($user['username'] ?? '');
        $_SESSION['role_id'] = $roleId;

        $admin = api_current_admin($db);
        if ($admin === null) {
            return [
                'ok' => false,
                'message' => 'Admin session could not be created.',
            ];
        }

        if ((int) ($admin['role_id'] ?? 0) !== 1) {
            api_tool_auth_clear_session();

            return [
                'ok' => false,
                'message' => 'Only Super Admin accounts can access API tools.',
            ];
        }

        api_log_admin_action($db, $admin, 'LOGIN', 'Signed in to ' . $toolName . '.');

        return [
            'ok' => true,
            'admin' => $admin,
        ];
    }
}

if (!function_exists('api_tool_auth_render_login')) {
    function api_tool_auth_render_login(string $toolName, string $message = ''): never
    {
        http_response_code($message === '' ? 401 : 403);
        header('Content-Type: text/html; charset=utf-8');

        $action = api_tool_auth_request_uri();
        ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= api_tool_auth_h($toolName) ?> Login</title>
  <style>
    :root {
      color-scheme: light;
      --bg: #f4f6fa;
      --panel: #ffffff;
      --ink: #172033;
      --muted: #647084;
      --line: #d9e1ec;
      --brand: #8a640b;
      --brand-dark: #624709;
      --danger: #b42318;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 24px;
      font: 15px/1.5 "Inter", "Segoe UI", Arial, sans-serif;
      color: var(--ink);
      background:
        linear-gradient(120deg, rgba(138, 100, 11, 0.08), transparent 36%),
        linear-gradient(240deg, rgba(36, 95, 178, 0.08), transparent 38%),
        var(--bg);
    }
    main {
      width: min(440px, 100%);
      padding: 26px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: var(--panel);
      box-shadow: 0 18px 60px rgba(23, 32, 51, 0.12);
    }
    h1 {
      margin: 0;
      font-size: 25px;
      line-height: 1.15;
    }
    p {
      margin: 8px 0 22px;
      color: var(--muted);
    }
    label {
      display: block;
      margin-bottom: 14px;
      color: var(--muted);
      font-size: 12px;
      font-weight: 800;
      text-transform: uppercase;
    }
    input {
      width: 100%;
      min-height: 42px;
      margin-top: 6px;
      padding: 0 12px;
      border: 1px solid var(--line);
      border-radius: 6px;
      color: var(--ink);
      background: #fff;
      font: inherit;
    }
    input:focus {
      outline: 2px solid rgba(138, 100, 11, 0.22);
      border-color: var(--brand);
    }
    button {
      width: 100%;
      min-height: 42px;
      border: 0;
      border-radius: 6px;
      color: #fff;
      background: var(--brand);
      font: inherit;
      font-weight: 800;
      cursor: pointer;
    }
    button:hover { background: var(--brand-dark); }
    .alert {
      margin-bottom: 16px;
      padding: 10px 12px;
      border: 1px solid rgba(180, 35, 24, 0.22);
      border-radius: 6px;
      color: var(--danger);
      background: #fff0ee;
      font-weight: 700;
    }
    .footnote {
      margin: 16px 0 0;
      color: var(--muted);
      font-size: 12px;
    }
  </style>
</head>
<body>
  <main>
    <h1><?= api_tool_auth_h($toolName) ?></h1>
    <p>Sign in with an admin account to continue.</p>
    <?php if ($message !== ''): ?>
      <div class="alert"><?= api_tool_auth_h($message) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= api_tool_auth_h($action) ?>">
      <input type="hidden" name="tool_auth_action" value="login">
      <label>
        Username
        <input name="username" type="text" autocomplete="username" required autofocus>
      </label>
      <label>
        Password
        <input name="password" type="password" autocomplete="current-password" required>
      </label>
      <button type="submit">Sign in</button>
    </form>
    <p class="footnote">This protects API inspection pages. Public client endpoints remain available for the website.</p>
  </main>
</body>
</html>
        <?php
        exit;
    }
}

if (!function_exists('api_tool_require_admin')) {
    function api_tool_require_admin(PDO $db, string $toolName): array
    {
        $wantsJson = api_tool_auth_wants_json();
        $payload = api_tool_auth_payload();
        $action = trim((string) ($payload['tool_auth_action'] ?? ''));

        if (api_request_method() === 'POST' && $action === 'logout') {
            $admin = api_current_admin($db);
            if ($admin !== null) {
                api_log_admin_action($db, $admin, 'LOGOUT', 'Signed out from ' . $toolName . '.');
            }

            api_tool_auth_clear_session();

            if ($wantsJson) {
                api_json([
                    'authenticated' => false,
                    'message' => 'Signed out successfully.',
                ]);
            }

            api_tool_auth_redirect_to_get();
        }

        if (api_request_method() === 'POST' && $action === 'login') {
            $result = api_tool_auth_attempt_login($db, $payload, $toolName);

            if ((bool) ($result['ok'] ?? false)) {
                if ($wantsJson) {
                    api_json([
                        'authenticated' => true,
                        'message' => 'Signed in successfully.',
                    ]);
                }

                api_tool_auth_redirect_to_get();
            }

            if ($wantsJson) {
                api_json([
                    'success' => false,
                    'authenticated' => false,
                    'message' => (string) ($result['message'] ?? 'Unable to sign in.'),
                ], 401);
            }

            api_tool_auth_render_login($toolName, (string) ($result['message'] ?? 'Unable to sign in.'));
        }

        $admin = api_current_admin($db);
        if ($admin !== null && (int) ($admin['role_id'] ?? 0) === 1) {
            return $admin;
        }

        if ($admin !== null) {
            if ($wantsJson) {
                api_json([
                    'success' => false,
                    'authenticated' => true,
                    'authorized' => false,
                    'message' => 'Only Super Admin accounts can access API tools.',
                ], 403);
            }

            api_tool_auth_render_login($toolName, 'Only Super Admin accounts can access API tools.');
        }

        if ($wantsJson) {
            api_json([
                'success' => false,
                'authenticated' => false,
                'message' => 'Admin login required.',
            ], 401);
        }

        api_tool_auth_render_login($toolName);
    }
}

if (!function_exists('api_tool_logout_form')) {
    function api_tool_logout_form(string $className = 'button', string $label = 'Sign out'): string
    {
        $action = api_tool_auth_h(api_tool_auth_request_uri());
        $class = api_tool_auth_h($className);
        $label = api_tool_auth_h($label);

        return <<<HTML
<form method="post" action="{$action}" style="margin:0">
  <input type="hidden" name="tool_auth_action" value="logout">
  <button class="{$class}" type="submit">{$label}</button>
</form>
HTML;
    }
}

if (!function_exists('api_tool_base_path')) {
    function api_tool_base_path(): string
    {
        $requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if ($requestPath === '') {
            $requestPath = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        }

        foreach (['/database-diagram/index.php', '/database-diagram/', '/dashboard.php'] as $suffix) {
            if (str_ends_with($requestPath, $suffix)) {
                return rtrim(substr($requestPath, 0, -strlen($suffix)), '/');
            }
        }

        $position = strpos($requestPath, '/database-diagram');
        if ($position !== false) {
            return rtrim(substr($requestPath, 0, $position), '/');
        }

        return rtrim(api_base_path(), '/');
    }
}

if (!function_exists('api_tool_url')) {
    function api_tool_url(string $path): string
    {
        $base = api_tool_base_path();
        $path = '/' . ltrim($path, '/');

        return ($base === '' ? '' : $base) . $path;
    }
}

if (!function_exists('api_tool_sidebar_styles')) {
    function api_tool_sidebar_styles(): string
    {
        return <<<'CSS'
.api-tool-layout {
    display: grid;
    grid-template-columns: 270px minmax(0, 1fr);
    gap: 22px;
    align-items: start;
}

.api-tool-main {
    min-width: 0;
}

.api-tool-sidebar {
    position: sticky;
    top: 22px;
    max-height: calc(100vh - 44px);
    overflow: auto;
    border: 1px solid var(--api-sidebar-border, var(--line, var(--border)));
    border-radius: 12px;
    background: var(--api-sidebar-bg, var(--panel, var(--card-bg)));
    box-shadow: var(--api-sidebar-shadow, var(--shadow, var(--shadow-md)));
}

.api-tool-sidebar__brand {
    padding: 16px;
    border-bottom: 1px solid var(--api-sidebar-border, var(--line, var(--border)));
}

.api-tool-sidebar__brand strong {
    display: block;
    color: var(--api-sidebar-text, var(--ink, var(--text-primary)));
    font-size: 16px;
    line-height: 1.1;
}

.api-tool-sidebar__brand span {
    display: block;
    margin-top: 5px;
    color: var(--api-sidebar-muted, var(--muted, var(--text-muted)));
    font-size: 12px;
    font-weight: 700;
}

.api-tool-sidebar__nav {
    padding: 12px;
}

.api-tool-sidebar__group {
    margin-bottom: 14px;
}

.api-tool-sidebar__group:last-child {
    margin-bottom: 0;
}

.api-tool-sidebar__label {
    margin: 0 0 6px;
    padding: 0 8px;
    color: var(--api-sidebar-muted, var(--muted, var(--text-muted)));
    font-size: 10px;
    font-weight: 900;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.api-tool-sidebar__link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    min-height: 36px;
    padding: 8px 9px;
    border: 1px solid transparent;
    border-radius: 7px;
    color: var(--api-sidebar-link, var(--api-sidebar-text, inherit));
    text-decoration: none;
    font-size: 13px;
    font-weight: 750;
}

.api-tool-sidebar__link:hover {
    border-color: var(--api-sidebar-active-border, var(--brand, var(--accent-blue)));
    background: var(--api-sidebar-hover-bg, rgba(138, 100, 11, .08));
}

.api-tool-sidebar__link.active {
    border-color: var(--api-sidebar-active-border, var(--brand, var(--accent-blue)));
    color: var(--api-sidebar-active-text, #fff);
    background: var(--api-sidebar-active-bg, var(--brand, var(--accent-blue)));
}

.api-tool-sidebar__link small {
    color: var(--api-sidebar-muted, var(--muted, var(--text-muted)));
    font-size: 10px;
    font-weight: 900;
    text-transform: uppercase;
}

.api-tool-sidebar__link.active small {
    color: currentColor;
    opacity: .8;
}

.api-tool-sidebar__hint {
    margin: 12px;
    padding: 10px;
    border: 1px solid var(--api-sidebar-border, var(--line, var(--border)));
    border-radius: 8px;
    color: var(--api-sidebar-muted, var(--muted, var(--text-muted)));
    background: var(--api-sidebar-hint-bg, transparent);
    font-size: 12px;
    line-height: 1.45;
}

@media (max-width: 1020px) {
    .api-tool-layout {
        grid-template-columns: 1fr;
    }

    .api-tool-sidebar {
        position: static;
        max-height: none;
    }

    .api-tool-sidebar__nav {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .api-tool-sidebar__group {
        margin-bottom: 0;
    }
}

@media (max-width: 640px) {
    .api-tool-sidebar__nav {
        grid-template-columns: 1fr;
    }
}
CSS;
    }
}

if (!function_exists('api_tool_sidebar')) {
    function api_tool_sidebar(string $active = ''): string
    {
        $groups = [
            'Tools' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => api_tool_url('/dashboard.php'), 'tag' => 'HTML'],
                ['key' => 'database-diagram', 'label' => 'Database Diagram', 'url' => api_tool_url('/database-diagram/'), 'tag' => 'HTML'],
                ['key' => 'schema-json', 'label' => 'Schema JSON', 'url' => api_tool_url('/database-diagram/?format=json'), 'tag' => 'JSON'],
            ],
            'Public API' => [
                ['key' => 'home', 'label' => 'Home payload', 'url' => api_tool_url('/api/public/home.php'), 'tag' => 'GET'],
                ['key' => 'governors', 'label' => 'Governors', 'url' => api_tool_url('/v1/client/governors/get_all.php'), 'tag' => 'GET'],
                ['key' => 'clubs', 'label' => 'Clubs', 'url' => api_tool_url('/v1/client/clubs/get_all.php'), 'tag' => 'GET'],
                ['key' => 'members', 'label' => 'Members', 'url' => api_tool_url('/v1/client/members/get_all.php'), 'tag' => 'GET'],
                ['key' => 'news', 'label' => 'News', 'url' => api_tool_url('/v1/client/news/get_all.php'), 'tag' => 'GET'],
                ['key' => 'events', 'label' => 'Events', 'url' => api_tool_url('/v1/client/events/get_all.php'), 'tag' => 'GET'],
            ],
            'Admin API' => [
                ['key' => 'admin-session', 'label' => 'Admin Session', 'url' => api_tool_url('/api/admin/session.php'), 'tag' => 'GET'],
                ['key' => 'admin-dashboard', 'label' => 'Dashboard JSON', 'url' => api_tool_url('/api/admin/dashboard.php'), 'tag' => 'GET'],
                ['key' => 'admin-members', 'label' => 'Members Admin', 'url' => api_tool_url('/v1/admin/members/get_all.php'), 'tag' => 'GET'],
                ['key' => 'admin-governors', 'label' => 'Governors Admin', 'url' => api_tool_url('/v1/admin/governors/get_all.php'), 'tag' => 'GET'],
            ],
        ];

        ob_start();
        ?>
<aside class="api-tool-sidebar" aria-label="API links">
  <div class="api-tool-sidebar__brand">
    <strong>TFEOPE API</strong>
    <span>Tools and quick links</span>
  </div>
  <nav class="api-tool-sidebar__nav">
    <?php foreach ($groups as $groupLabel => $links): ?>
      <section class="api-tool-sidebar__group">
        <p class="api-tool-sidebar__label"><?= api_tool_auth_h($groupLabel) ?></p>
        <?php foreach ($links as $link): ?>
          <a
            class="api-tool-sidebar__link <?= $active === $link['key'] ? 'active' : '' ?>"
            href="<?= api_tool_auth_h($link['url']) ?>"
          >
            <span><?= api_tool_auth_h($link['label']) ?></span>
            <small><?= api_tool_auth_h($link['tag']) ?></small>
          </a>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
  </nav>
  <div class="api-tool-sidebar__hint">
    Public API links are left open for the website. Protected tools and admin endpoints require login.
  </div>
</aside>
        <?php

        return (string) ob_get_clean();
    }
}
