<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= __('auth.2fa_title') ?> — Integra RMA</title>
  <link href="/assets/css/fonts.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: <?= app_font_stack() ?>; background: #f4f4f0; min-height: 100vh;
           display: flex; align-items: center; justify-content: center; padding: 1rem; }
    /* Card and logo deliberately match auth/login.php so the two screens feel
       like one flow - same size, same vertical position. Change both together. */
    .card { background: #fff; border: 0.5px solid #d3d1c7; border-radius: 12px;
            padding: 40px 2rem 2rem; width: 100%; max-width: 380px; min-height: 400px;
            display: flex; flex-direction: column; justify-content: flex-start; }
    .logo { font-size: 20px; font-weight: 500; color: #2c2c2a; margin: 0 0 40px; }
    .logo span { color: #1D9E75; }
    .subtitle { font-size: 13px; color: #5f5e5a; text-align: center; margin-bottom: 1.75rem; }
    label { display: block; font-size: 13px; color: #5f5e5a; margin-bottom: 5px; }
    input[type=text], select {
      width: 100%; padding: 9px 12px; font-size: 14px; border: 0.5px solid #d3d1c7;
      border-radius: 8px; background: #fff; color: #2c2c2a; outline: none;
      font-family: inherit;   /* form controls don't inherit the page font */
      transition: border-color .15s;
    }
    input:focus, select:focus { border-color: #1D9E75; }
    .field { margin-bottom: 1rem; }
    .btn { width: 100%; padding: 10px; font-size: 14px; font-weight: 500;
           background: #1D9E75; color: #fff; border: none; border-radius: 8px;
           font-family: inherit;   /* form controls don't inherit the page font */
           cursor: pointer; margin-top: 0.5rem; transition: background .15s; }
    .btn:hover { background: #0F6E56; }
    .btn-secondary { background: #fff; color: #2c2c2a; border: 0.5px solid #d3d1c7; margin-top: 0.5rem; }
    .btn-secondary:hover { background: #f4f4f0; }
    .error { background: #fcebeb; color: #791f1f; border: 0.5px solid #f09595;
             border-radius: 8px; padding: 9px 12px; font-size: 13px; margin-bottom: 1rem; }
    .success { background: #e1f5ee; color: #085041; border: 0.5px solid #5dcaa5;
               border-radius: 8px; padding: 9px 12px; font-size: 13px; margin-bottom: 1rem; }
    .channel-label { display: flex; align-items: center; gap: 8px; padding: 10px 12px;
                     border: 0.5px solid #d3d1c7; border-radius: 8px; cursor: pointer;
                     margin-bottom: 8px; font-size: 14px; transition: border-color .15s; }
    .channel-label:hover { border-color: #1D9E75; }
    .channel-label input { width: auto; margin: 0; }
    .trust-row { display: flex; align-items: center; gap: 8px; margin-top: 0.75rem;
                 font-size: 13px; color: #5f5e5a; }
    .trust-row input { width: auto; }
    .back { font-size: 13px; color: #5f5e5a; text-decoration: none;
            display: block; text-align: center; margin-top: 1rem; }
    .back:hover { color: #1D9E75; }
    .countdown { font-size: 13px; color: #5f5e5a; text-align: center; margin-bottom: 1rem; }
    .countdown.expired { color: #791f1f; }
    .countdown strong { font-variant-numeric: tabular-nums; }  /* digits don't jitter */
  </style>
</head>
<body>
<div class="card">
  <div class="logo">
    <img src="/assets/integra.svg" alt="Integra" style="height:40px;width:auto;display:block;margin:0 auto;">
  </div>
  <p class="subtitle"><?= __('auth.2fa_title') ?></p>

  <?php if ($error ?? null): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php $sent = $_SESSION['2fa_sent'] ?? false; ?>
  <?php $channels = $_SESSION['2fa_channels'] ?? ['email']; ?>

  <?php if (!$sent): ?>
    <!-- Step 1: choose channel and send OTP -->
    <p style="font-size:13px;color:#5f5e5a;margin-bottom:1rem;">
      <?= __('auth.choose_channel') ?>
    </p>
    <form method="POST" action="/auth/2fa">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="send">
      <?php foreach ($channels as $ch): ?>
        <label class="channel-label">
          <input type="radio" name="channel" value="<?= htmlspecialchars($ch) ?>"
                 <?= $ch === 'email' ? 'checked' : '' ?>>
          <?= match($ch) {
            'whatsapp' => 'WhatsApp',
            'sms'      => 'SMS',
            default    => 'Email',
          } ?>
        </label>
      <?php endforeach; ?>
      <button type="submit" class="btn"><?= __('btn.send_code') ?></button>
    </form>

  <?php else: ?>
    <!-- Step 2: enter OTP -->
    <?php $channel = $_SESSION['2fa_channel'] ?? 'email'; ?>
    <div class="success">
      <?= __('auth.code_sent', ['channel' => match($channel) {
        'whatsapp' => 'WhatsApp',
        'sms'      => 'SMS',
        default    => 'Email',
      }]) ?>
    </div>

    <?php $left = otp_seconds_left((int)($_SESSION['pending_user_id'] ?? 0)); ?>
    <p class="countdown" id="otp-countdown" data-left="<?= $left ?>">
      <?= __('auth.code_expires_in', ['time' => sprintf('%d:%02d', intdiv($left, 60), $left % 60)]) ?>
    </p>

    <form method="POST" action="/auth/2fa">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="verify">
      <div class="field">
        <label for="code"><?= __('auth.code_label') ?></label>
        <input type="text" id="code" name="code" maxlength="6" pattern="[0-9]{6}"
               inputmode="numeric" autocomplete="one-time-code" autofocus
               placeholder="000000">
      </div>
      <div class="trust-row">
        <input type="checkbox" id="trust_device" name="trust_device" value="1">
        <label for="trust_device"><?= __('auth.trust_device') ?></label>
      </div>
      <button type="submit" class="btn"><?= __('btn.verify') ?></button>
    </form>

    <form method="POST" action="/auth/2fa" style="margin-top:0.5rem;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="send">
      <input type="hidden" name="channel" value="<?= htmlspecialchars($channel) ?>">
      <button type="submit" class="btn btn-secondary"><?= __('btn.resend') ?></button>
    </form>

    <?php if (count($channels) > 1): ?>
      <!-- Back to the channel list without discarding the pending login.
           Only worth showing when there is something else to switch to. -->
      <form method="POST" action="/auth/2fa" style="margin-top:0.5rem;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_channel">
        <button type="submit" class="btn btn-secondary"><?= __('auth.change_channel') ?></button>
      </form>
    <?php endif; ?>
  <?php endif; ?>

  <a href="/auth/login" class="back"><?= __('auth.back_to_login') ?></a>
</div>

<script>
// Live countdown for the code. Seeded from the server's stored expiry (rendered
// into data-left) so a refresh or a slow page load can't make it disagree.
(function () {
  var el = document.getElementById('otp-countdown');
  if (!el) return;

  var left     = parseInt(el.dataset.left, 10) || 0;
  var tplLeft  = <?= json_encode(__('auth.code_expires_in', ['time' => '%TIME%'])) ?>;
  var tplGone  = <?= json_encode(__('auth.code_expired_hint')) ?>;

  function render() {
    if (left <= 0) {
      el.textContent = tplGone;
      el.classList.add('expired');
      return;
    }
    var m = Math.floor(left / 60), s = left % 60;
    el.innerHTML = tplLeft.replace('%TIME%', '<strong>' + m + ':' + (s < 10 ? '0' : '') + s + '</strong>');
  }

  render();
  if (left > 0) {
    var t = setInterval(function () {
      left--;
      render();
      if (left <= 0) clearInterval(t);
    }, 1000);
  }
})();
</script>
</body>
</html>
