<?php defined('RMS') or die('Direct access not permitted'); ?>
<?php
/**
 * Six one-digit boxes that behave as a single code field.
 *
 * Used by the login 2FA screen and by the authenticator setup on Moj profil.
 * Both need the same behaviour but different widths, so size comes in as a
 * variable and the CSS and script are emitted only once per page.
 *
 * The boxes carry no name. The script combines them into a hidden input, so
 * whatever handles the form still receives one `code` value exactly as it did
 * when this was a single field.
 *
 * Variables:
 *   $cb_width   box width in px   (default 44)
 *   $cb_height  box height in px  (default 52)
 *   $cb_gap     gap in px         (default 8)
 *   $cb_id      unique id prefix  (default 'code')
 */
$cb_width  = $cb_width  ?? 44;
$cb_height = $cb_height ?? 52;
$cb_gap    = $cb_gap    ?? 8;
$cb_id     = $cb_id     ?? 'code';

// Emit the shared CSS and script once, however many groups a page renders.
static $cb_assets_done = false;
?>
<div class="code-boxes" id="<?= htmlspecialchars($cb_id) ?>-boxes"
     style="display:flex;gap:<?= (int)$cb_gap ?>px;justify-content:center;">
  <?php for ($i = 1; $i <= 6; $i++): ?>
    <input type="text" id="<?= htmlspecialchars($cb_id) ?>-<?= $i ?>" class="code-box"
           inputmode="numeric" pattern="[0-9]*" maxlength="1"
           aria-label="<?= __('auth.code_digit', ['n' => $i]) ?>"
           style="width:<?= (int)$cb_width ?>px;height:<?= (int)$cb_height ?>px;"
           <?= $i === 1 ? 'autocomplete="one-time-code" autofocus' : 'autocomplete="off"' ?>>
  <?php endfor; ?>
</div>
<input type="hidden" name="code" id="<?= htmlspecialchars($cb_id) ?>">
<noscript>
  <!-- Without JS the boxes cannot be combined, so offer the plain field. -->
  <input type="text" name="code" maxlength="6" pattern="[0-9]{6}"
         inputmode="numeric" style="text-align:center;letter-spacing:6px;margin-top:8px;">
</noscript>

<?php if (!$cb_assets_done): $cb_assets_done = true; ?>
<style>
  .code-box { padding: 0; text-align: center; font-size: 20px;
              font-variant-numeric: tabular-nums;
              border: 0.5px solid #d3d1c7; border-radius: 8px; background: #fff;
              color: #2c2c2a; outline: none; font-family: inherit;
              transition: border-color .15s; }
  .code-box:focus, .code-box.filled { border-color: #1D9E75; }
</style>
<script>
// Every .code-boxes group on the page behaves as one field.
(function () {
  document.querySelectorAll('.code-boxes').forEach(function (wrap) {
    var joined = document.getElementById(wrap.id.replace(/-boxes$/, ''));
    if (!joined) return;

    var boxes = Array.prototype.slice.call(wrap.querySelectorAll('.code-box'));
    var form  = wrap.closest('form');

    function digitsOnly(t) { return (t || '').replace(/\D/g, ''); }

    function sync() {
      var code = boxes.map(function (b) { return b.value; }).join('');
      joined.value = code;
      boxes.forEach(function (b) { b.classList.toggle('filled', b.value !== ''); });
      return code;
    }

    // Spread a multi-character value across the boxes. One path covers three
    // cases: pasting a code from an email, a phone autofilling an SMS code into
    // the first box, and a password manager doing the same.
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
      // Submitting on the sixth digit saves hunting for the button — the code
      // is complete and there is nothing else to fill in.
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

      // Selecting on focus means typing replaces rather than appends.
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
  });
})();
</script>
<?php endif; ?>
