<?php

declare(strict_types=1);

$manifestPath = __DIR__ . '/dist/.vite/manifest.json';
$manifest = is_file($manifestPath)
    ? json_decode((string) file_get_contents($manifestPath), true)
    : null;
$entry = is_array($manifest) ? ($manifest['index.html'] ?? null) : null;

function asset_href(string $path): string
{
    return htmlspecialchars('dist/' . ltrim($path, '/'), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TFEOPE Admin</title>
  <?php if (is_array($entry)): ?>
    <?php foreach (($entry['css'] ?? []) as $cssFile): ?>
      <link rel="stylesheet" href="<?= asset_href((string) $cssFile) ?>">
    <?php endforeach; ?>
  <?php endif; ?>
</head>
<body>
  <div id="root">
    <?php if (!is_array($entry)): ?>
      <p style="font-family: Arial, sans-serif; padding: 24px;">
        Frontend build not found. Run <code>npm run build</code> inside <code>tfeope-admin</code>.
      </p>
    <?php endif; ?>
  </div>
  <?php if (is_array($entry)): ?>
    <script type="module" src="<?= asset_href((string) $entry['file']) ?>"></script>
  <?php endif; ?>
</body>
</html>
