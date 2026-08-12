<?php
  // Customer-facing, so it follows THEIR language — the same rule as the
  // tracking page itself. $tt keeps the escaping $tt() applies.
  $track_lang = customer_lang($rma['customer_lang'] ?? null);
  $tt = fn(string $k, array $r = []): string =>
      htmlspecialchars(__in($track_lang, $k, $r), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($track_lang) ?>">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="/assets/img/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $tt('misc.access_locked') ?> — Integra RMA</title>
  <?php $font_slug = strtolower(str_replace(' ', '-', setting('app_font', 'Montserrat'))); ?>
  <link rel="preload" href="/assets/fonts/<?= $font_slug ?>-400-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="/assets/fonts/<?= $font_slug ?>-500-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link href="/assets/css/fonts.css" rel="stylesheet">
  <style>
    body { font-family: <?= app_font_stack() ?>;
           background: #f4f4f0; display: flex;
           -webkit-font-smoothing: antialiased;
           align-items: center; justify-content: center; min-height: 100vh; padding: 1.5rem; }
    .card { background: #fff; border: 0.5px solid #f09595; border-radius: 12px;
            padding: 2rem; max-width: 360px; text-align: center; }
    img { width: 90px; margin-bottom: 1rem; }
    h1 { font-size: 16px; font-weight: 500; margin-bottom: 6px; color: #a32d2d; }
    p { font-size: 13px; color: #5f5e5a; }
  </style>
</head>
<body>
<div class="card">
  <img src="/assets/integra.svg" alt="Integra">
  <h1><?= $tt('misc.access_locked') ?></h1>
  <p><?= $tt('track.locked') ?></p>
</div>
</body>
</html>
