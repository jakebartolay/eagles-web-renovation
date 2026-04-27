<?php

declare(strict_types=1);

$jsFiles = glob(__DIR__ . '/dist/assets/index-*.js') ?: [];
$cssFiles = glob(__DIR__ . '/dist/assets/index-*.css') ?: [];

usort($jsFiles, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
usort($cssFiles, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

$entryJs = $jsFiles[0] ?? null;
$entryCss = $cssFiles[0] ?? null;

function asset_href(string $path): string
{
    return htmlspecialchars('dist/assets/' . basename($path), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TFEOPE</title>
  <?php if (is_string($entryCss)): ?>
    <link rel="stylesheet" href="<?= asset_href($entryCss) ?>">
  <?php endif; ?>
</head>
<body>
  <div id="root">
    <?php if (!is_string($entryJs)): ?>
      <p style="font-family: Arial, sans-serif; padding: 24px;">
        Frontend build not found. Run <code>npm run build</code> inside <code>tfeope</code>.
      </p>
    <?php endif; ?>
  </div>
  <?php if (is_string($entryJs)): ?>
    <script type="module" src="<?= asset_href($entryJs) ?>"></script>
  <?php endif; ?>
</body>
</html>
