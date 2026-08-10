<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="/assets/img/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>403 — Integra RMA</title>
  <link href="/assets/css/fonts.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: <?= app_font_stack() ?>;
           background: #f4f4f0; color: #2c2c2a; min-height: 100vh;
           display: flex; align-items: center; justify-content: center; padding: 1rem; }
    .card { background: #fff; border: 0.5px solid #d3d1c7; border-radius: 12px;
            padding: 2rem; width: 100%; max-width: 380px; text-align: center; }
    h2 { font-size: 18px; font-weight: 500; margin-bottom: 0.5rem; }
    p { font-size: 13px; color: #5f5e5a; margin-bottom: 1.25rem; }
    a { font-size: 13px; font-weight: 500; color: #1D9E75; text-decoration: none; }
    a:hover { color: #0F6E56; }
  </style>
</head>
<body>
  <div class="card">
    <h2><?= __('misc.access_denied') ?></h2>
    <p><?= __('misc.no_permission') ?></p>
    <a href="/"><?= __('misc.go_back') ?></a>
  </div>
</body>
</html>
