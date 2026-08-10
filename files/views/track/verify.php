<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="/assets/img/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= __('track.title') ?> — Integra RMA</title>
  <?php $font_slug = strtolower(str_replace(' ', '-', setting('app_font', 'Montserrat'))); ?>
  <link rel="preload" href="/assets/fonts/<?= $font_slug ?>-400-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="/assets/fonts/<?= $font_slug ?>-500-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link href="/assets/css/fonts.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: <?= app_font_stack() ?>;
           background: #f4f4f0; min-height: 100vh;
           -webkit-font-smoothing: antialiased;
           display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.5rem; }
    .card { background: #fff; border: 0.5px solid #d3d1c7; border-radius: 12px;
            padding: 2.5rem; width: 100%; max-width: 480px; min-height: 400px;
            display: flex; flex-direction: column; justify-content: center;
            text-align: center; }
    /* Phones: less side padding so the form isn't cramped on narrow screens. */
    @media (max-width: 420px) {
      .card { padding: 1.75rem; min-height: 360px; }
    }
    .logo { margin-bottom: 48px; text-align: center; }
    .logo img { height: 40px; width: auto; }
    .logo p { font-size: 12px; color: #888780; margin-top: 4px; }
    h1 { font-size: 18px; font-weight: 500; margin-bottom: 6px; color: #2c2c2a; }
    .subtitle { font-size: 13px; color: #5f5e5a; margin-bottom: 1.5rem; line-height: 1.5; }
    label { display: block; font-size: 12px; color: #5f5e5a; margin-bottom: 4px; }
    input { width: 100%; padding: 9px 12px; font-size: 14px; border: 0.5px solid #d3d1c7;
            border-radius: 8px; outline: none; transition: border-color .15s; }
    input:focus { border-color: #1D9E75; }
    .btn { width: 100%; padding: 10px; font-size: 14px; font-weight: 500; background: #1D9E75;
           color: #fff; border: none; border-radius: 8px; cursor: pointer; margin-top: 20px;
           transition: background .15s; }
    .btn:hover { background: #0F6E56; }
    .error { background: #fcebeb; color: #791f1f; border: 0.5px solid #f09595;
             border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 1rem; }
    .footer { margin-top: 2rem; font-size: 12px; color: #888780; }
  </style>
</head>
<body>
<div class="card">
  <div class="logo">
    <img src="/assets/integra.svg" alt="Integra">
  </div>

  <h1><?= __('track.title') ?></h1>
  <p class="subtitle"><?= __('track.verify') ?></p>

  <?php if ($error ?? null): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="/track/<?= htmlspecialchars($token ?? '') ?>">
      <?= csrf_field() ?>
    <input type="text" name="identifier" required autofocus>
    <button type="submit" class="btn"><?= __('track.submit') ?></button>
  </form>
</div>

<p class="footer">&copy; <?= date('Y') ?> Integra</p>
</body>
</html>
