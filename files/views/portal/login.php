<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="/assets/img/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= __('portal.portal') ?> — Integra RMA</title>
  <link href="/assets/css/fonts.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: <?= app_font_stack() ?>; background: #f4f4f0; min-height: 100vh;
           display: flex; align-items: center; justify-content: center; padding: 1rem; }
    .card { background: #fff; border: 0.5px solid #d3d1c7; border-radius: 12px;
            padding: 2rem; width: 100%; max-width: 380px; min-height: 400px;
            display: flex; flex-direction: column; justify-content: center; }
    .logo { font-size: 20px; font-weight: 500; color: #2c2c2a; margin-bottom: 1.75rem; }
    label { display: block; font-size: 13px; color: #5f5e5a; margin-bottom: 5px; }
    input[type=email], input[type=password] {
      width: 100%; padding: 9px 12px; font-size: 14px; border: 0.5px solid #d3d1c7;
      border-radius: 8px; background: #fff; color: #2c2c2a; outline: none;
      font-family: inherit;   /* form controls don't inherit the page font */
      transition: border-color .15s;
    }
    input:focus { border-color: #1D9E75; }
    .field { margin-bottom: 1rem; }
    .btn { width: 100%; padding: 10px; font-size: 14px; font-weight: 500;
           background: #1D9E75; color: #fff; border: none; border-radius: 8px;
           font-family: inherit;   /* form controls don't inherit the page font */
           cursor: pointer; margin-top: 0.5rem; transition: background .15s; }
    .btn:hover { background: #0F6E56; }
    .error { background: #fcebeb; color: #791f1f; border: 0.5px solid #f09595;
             border-radius: 8px; padding: 9px 12px; font-size: 13px; margin-bottom: 1rem; }
    .footer-link { font-size: 12px; color: #888780; text-align: center; margin-top: 1rem; }
    .footer-link a { color: #1D9E75; text-decoration: none; }
  </style>
</head>
<body>
<div class="card">
  <div class="logo">
    <img src="/assets/integra.svg" alt="Integra" style="height:40px;width:auto;display:block;margin:0 auto;">
  </div>

  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="/portal/login">
    <?= csrf_field() ?>
    <div class="field">
      <label for="email"><?= __('auth.email') ?></label>
      <input type="email" id="email" name="email" required autofocus
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="password"><?= __('auth.password') ?></label>
      <input type="password" id="password" name="password" required>
    </div>
    <button type="submit" class="btn"><?= __('auth.login') ?></button>
  </form>

  <p class="footer-link"><?= __('portal.staff_prompt') ?> <a href="/auth/login"><?= __('portal.staff_login') ?></a></p>
</div>
</body>
</html>
