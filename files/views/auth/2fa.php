<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="/assets/img/favicon.png">
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
            padding: 50px 2rem 2rem; width: 100%; max-width: 380px; min-height: 400px;
            display: flex; flex-direction: column; justify-content: flex-start; }
    .logo { font-size: 20px; font-weight: 500; color: #2c2c2a; margin: 0 0 50px; }
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
    /* Fixed line-height and min-height: the label swaps between Send Code
       and Enter Code as the channel changes, and without these the box
       resizes with its text. */
    .btn { width: 100%; padding: 10px; font-size: 14px; font-weight: 500;
           line-height: 20px; min-height: 42px;
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
    /* justify-content centres the box+label pair as a group; align-items keeps
       the box on the text baseline rather than floating above it. */
    .trust-row { display: flex; align-items: center; justify-content: center;
                 gap: 8px; margin: 20px 0 10px; font-size: 13px; color: #5f5e5a; }
    .trust-row input { width: auto; margin: 0; }
    .trust-row label { margin-bottom: 0; line-height: 1; }

    /* The code is the focus of this screen - centre the field and its label,
       and space the digits out so a 6-digit code is easy to read back. */
    .code-label { text-align: center; }
    .code-input { text-align: center; font-size: 20px; letter-spacing: 6px;
                  font-variant-numeric: tabular-nums; }
    /* Six boxes across a 380px card: 6*44 + 5*8 = 304px, comfortably inside
       the ~316px of usable width. */
    .code-boxes { display: flex; gap: 8px; justify-content: center; }
    .code-box { width: 44px; height: 52px; padding: 0; text-align: center;
                font-size: 22px; font-variant-numeric: tabular-nums;
                border: 0.5px solid #d3d1c7; border-radius: 8px; background: #fff;
                color: #2c2c2a; outline: none; font-family: inherit;
                transition: border-color .15s; }
    .code-box:focus { border-color: #1D9E75; }
    .code-box.filled { border-color: #1D9E75; }
    .back { font-size: 13px; color: #5f5e5a; text-decoration: none;
            display: block; text-align: center; margin-top: 1rem; }
    .back:hover { color: #1D9E75; }
    .countdown { font-size: 13px; color: #5f5e5a; text-align: center; margin-bottom: 1rem; }
    .countdown.expired { color: #791f1f; }
    .countdown strong { font-variant-numeric: tabular-nums; }  /* digits don't jitter */
    .spinner { width: 28px; height: 28px; margin: 0 auto; border-radius: 50%;
               border: 2px solid #e8e6e0; border-top-color: #1D9E75;
               animation: spin .7s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    /* Respect users who ask for reduced motion; the text still says what is
       happening, so the spin is decoration rather than information. */
    @media (prefers-reduced-motion: reduce) { .spinner { animation: none; } }
  </style>
</head>
<body>
<div class="card">
  <div class="logo">
    <img src="/assets/integra.svg" alt="Integra" style="height:40px;width:auto;display:block;margin:0 auto;">
  </div>
  <?php if ($error ?? null): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php $sent = $_SESSION['2fa_sent'] ?? false; ?>
  <?php $channels = $_SESSION['2fa_channels'] ?? ['email']; ?>

  <?php if (!$sent): ?>
    <!-- Sending takes a second or two (SMTP handshake, or the SMS API call).
         Swap to this panel the instant the form is submitted, so the screen
         visibly moves on instead of appearing frozen. Hidden without JS, where
         the plain form post still works exactly as before. -->
    <div id="sending-panel" style="display:none;text-align:center;padding:1rem 0;">
      <div class="spinner" aria-hidden="true"></div>
      <p style="font-size:14px;color:#5f5e5a;margin-top:14px;"><?= __('auth.sending_code') ?></p>
      <p style="font-size:12px;color:#888780;margin-top:6px;"><?= __('auth.sending_wait') ?></p>
    </div>

    <div id="choose-panel">
    <!-- Step 1: choose channel and send OTP -->
    <p style="font-size:13px;color:#5f5e5a;margin-bottom:1rem;">
      <?= __('auth.choose_channel') ?>
    </p>
    <form method="POST" action="/auth/2fa" id="send-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="send">
      <?php
        // The authenticator wins when it is available. It only ever appears in
        // $channels for someone who finished enrolling, and for them it is the
        // strongest option and needs nothing sent — so it is the default even
        // if their profile still names email from before they enrolled.
        //
        // Otherwise: the channel chosen in Moj profil, provided the role still
        // allows it and it is switched on app-wide; failing that the first
        // available, rather than assuming email exists.
        $preferred = $_SESSION['2fa_preferred'] ?? null;
        $default   = in_array('totp', $channels, true)
                   ? 'totp'
                   : (in_array($preferred, $channels, true) ? $preferred : ($channels[0] ?? 'email'));
      ?>
      <?php foreach ($channels as $ch): ?>
        <label class="channel-label">
          <input type="radio" name="channel" value="<?= htmlspecialchars($ch) ?>"
                 <?= $ch === $default ? 'checked' : '' ?>>
          <?= match($ch) {
            'totp'     => __('auth.channel_totp'),
            'whatsapp' => 'WhatsApp',
            'sms'      => 'SMS',
            default    => 'Email',
          } ?>
        </label>
      <?php endforeach; ?>
      <button type="submit" class="btn" id="send-btn"
                data-send="<?= htmlspecialchars(__('btn.send_code')) ?>"
                data-enter="<?= htmlspecialchars(__('btn.enter_code')) ?>"><?= $default === 'totp' ? __('btn.enter_code') : __('btn.send_code') ?></button>
    </form>
    </div><!-- /choose-panel -->

  <?php else: ?>
    <!-- Step 2: enter OTP -->
    <?php $channel = $_SESSION['2fa_channel'] ?? 'email'; ?>
    <?php if ($channel === 'totp'): ?>
      <!-- Nothing was sent and the app rotates its own code every 30s, so a
           server-side countdown would be misleading here. -->
      <p class="countdown"><?= __('auth.totp_hint') ?></p>
    <?php else: ?>
      <?php $left = otp_seconds_left((int)($_SESSION['pending_user_id'] ?? 0)); ?>
      <p class="countdown" id="otp-countdown" data-left="<?= $left ?>">
        <?= __('auth.code_expires_in', ['time' => sprintf('%d:%02d', intdiv($left, 60), $left % 60)]) ?>
      </p>
    <?php endif; ?>

    <form method="POST" action="/auth/2fa">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="verify">
      <div class="field">
        <label for="code-1" class="code-label"><?= __('auth.code_label') ?></label>
        <!-- Six boxes, one digit each. They carry no name: JS combines them into
             the hidden field below, so the server still receives a single `code`
             exactly as before. The first box keeps autocomplete="one-time-code"
             so a phone still offers the SMS code, and the paste/autofill handler
             spreads a multi-character value across all six. -->
        <div class="code-boxes" id="code-boxes">
          <?php for ($i = 1; $i <= 6; $i++): ?>
            <input type="text" id="code-<?= $i ?>" class="code-box"
                   inputmode="numeric" pattern="[0-9]*" maxlength="1"
                   aria-label="<?= __('auth.code_digit', ['n' => $i]) ?>"
                   <?= $i === 1 ? 'autocomplete="one-time-code" autofocus' : 'autocomplete="off"' ?>>
          <?php endfor; ?>
        </div>
        <input type="hidden" name="code" id="code">
        <noscript>
          <!-- Without JS the boxes cannot be combined, so fall back to the plain
               field this screen used before. -->
          <input type="text" name="code" maxlength="6" pattern="[0-9]{6}"
                 inputmode="numeric" class="code-input" style="margin-top:8px;">
        </noscript>
      </div>
      <div class="trust-row">
        <input type="checkbox" id="trust_device" name="trust_device" value="1">
        <label for="trust_device"><?= __('auth.trust_device') ?></label>
      </div>
      <button type="submit" class="btn"><?= __('btn.verify') ?></button>
    </form>

    <?php if ($channel !== 'totp'): ?>
      <form method="POST" action="/auth/2fa" style="margin-top:0.5rem;" data-waiting="1">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="send">
        <input type="hidden" name="channel" value="<?= htmlspecialchars($channel) ?>">
        <button type="submit" class="btn btn-secondary"><?= __('btn.resend') ?></button>
      </form>
    <?php endif; ?>

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
// Sending the code blocks for a second or two while the mail server or SMS API
// answers. Show that immediately: swap the channel chooser for a waiting panel,
// and stop a second submit — an impatient double-click would otherwise send two
// codes, and only the newer one would work.
(function () {
  var form   = document.getElementById('send-form');
  var choose = document.getElementById('choose-panel');
  var wait   = document.getElementById('sending-panel');

  if (form && choose && wait) {
    form.addEventListener('submit', function () {
      choose.style.display = 'none';
      wait.style.display   = 'block';
    });
  }

  // Resend sits on the code screen, where there is no panel to swap - just
  // disable the button so the click clearly registered.
  document.querySelectorAll('form[data-waiting] button[type=submit]').forEach(function (btn) {
    btn.form.addEventListener('submit', function () {
      btn.disabled = true;
      btn.textContent = <?= json_encode(__('auth.sending_code')) ?>;
    });
  });
})();

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

<script>
// The button says "Enter Code" for the authenticator and "Send Code" for the
// channels that actually send something. Switching channel switches the label,
// so it never promises to send a message that no one will receive.
(function () {
  var btn = document.getElementById('send-btn');
  if (!btn) return;
  document.querySelectorAll('input[name="channel"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      btn.textContent = radio.value === 'totp'
        ? btn.getAttribute('data-enter')
        : btn.getAttribute('data-send');
    });
  });
})();
</script>

<script>
// Six one-digit boxes behaving like a single field.
//
// The boxes carry no name; this fills the hidden `code` input, so the server
// still receives one 6-digit value and nothing behind it had to change.
(function () {
  var wrap = document.getElementById('code-boxes');
  var joined = document.getElementById('code');
  if (!wrap || !joined) return;

  var boxes = Array.prototype.slice.call(wrap.querySelectorAll('.code-box'));
  var form  = wrap.closest('form');

  function digitsOnly(t) { return (t || '').replace(/\D/g, ''); }

  function sync() {
    var code = boxes.map(function (b) { return b.value; }).join('');
    joined.value = code;
    boxes.forEach(function (b) { b.classList.toggle('filled', b.value !== ''); });
    return code;
  }

  // Spread a multi-character value across the boxes. This covers three cases
  // at once: pasting a code from an email, a phone autofilling an SMS code
  // into the first box, and a password manager doing the same.
  function spread(text, startIndex) {
    var chars = digitsOnly(text).split('');
    if (!chars.length) return;
    var i = startIndex;
    while (chars.length && i < boxes.length) { boxes[i++].value = chars.shift(); }
    boxes[Math.min(i, boxes.length - 1)].focus();
    finish();
  }

  function finish() {
    var code = sync();
    // Submitting on the sixth digit saves hunting for the button — the code is
    // complete and there is nothing else to fill in.
    if (code.length === boxes.length && form && !form.__submitted) {
      form.__submitted = true;
      if (form.requestSubmit) form.requestSubmit(); else form.submit();
    }
  }

  boxes.forEach(function (box, index) {
    box.addEventListener('input', function () {
      if (box.value.length > 1) { spread(box.value, index); return; }
      box.value = digitsOnly(box.value);
      if (box.value && index < boxes.length - 1) boxes[index + 1].focus();
      finish();
    });

    box.addEventListener('keydown', function (e) {
      if (e.key === 'Backspace' && !box.value && index > 0) {
        // Step back and clear, so holding backspace walks the code away.
        e.preventDefault();
        boxes[index - 1].value = '';
        boxes[index - 1].focus();
        sync();
      } else if (e.key === 'ArrowLeft' && index > 0) {
        e.preventDefault(); boxes[index - 1].focus();
      } else if (e.key === 'ArrowRight' && index < boxes.length - 1) {
        e.preventDefault(); boxes[index + 1].focus();
      }
    });

    box.addEventListener('paste', function (e) {
      e.preventDefault();
      spread((e.clipboardData || window.clipboardData).getData('text'), index);
    });

    // Selecting the content on focus means typing replaces rather than appends.
    box.addEventListener('focus', function () { box.select(); });
  });

  if (form) {
    form.addEventListener('submit', function (e) {
      if (sync().length !== boxes.length) {
        // Incomplete: stop, and put the cursor on the first empty box.
        e.preventDefault();
        form.__submitted = false;
        var first = boxes.filter(function (b) { return !b.value; })[0];
        if (first) first.focus();
      }
    });
  }
})();
</script>
</body>
</html>
