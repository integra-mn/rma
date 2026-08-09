<?php defined('RMS') or die('Direct access not permitted'); ?>
  </main>

</div>

<!-- Global in-app confirmation dialog — replaces the browser's native confirm().
     Any <form> or <a> with a data-confirm="message" attribute is intercepted and
     runs through this styled modal instead. JS can also call appConfirm(msg, cb). -->
<div id="app-confirm" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:200;align-items:center;justify-content:center;">
  <div style="background:var(--bg-surface);border-radius:12px;padding:1.5rem;width:100%;max-width:420px;margin:1rem;box-shadow:0 10px 40px rgba(0,0,0,0.25);">
    <p id="app-confirm-msg" style="font-size:14px;line-height:1.5;margin:0 0 1.25rem;color:var(--text-primary);"></p>
    <div class="modal-actions" style="display:flex;gap:8px;justify-content:flex-end;">
      <button type="button" id="app-confirm-cancel" class="btn"><?= __('btn.cancel') ?></button>
      <button type="button" id="app-confirm-ok" class="btn btn-danger"><?= __('btn.confirm') ?></button>
    </div>
  </div>
</div>
<script>
(function () {
  var overlay = document.getElementById('app-confirm');
  var msgEl   = document.getElementById('app-confirm-msg');
  var okBtn   = document.getElementById('app-confirm-ok');
  var cancel  = document.getElementById('app-confirm-cancel');
  var pending = null;

  var OK_LABEL      = <?= json_encode(__('btn.ok')) ?>;
  var CONFIRM_LABEL = <?= json_encode(__('btn.confirm')) ?>;

  function open(message, onOk) {
    msgEl.textContent = message || '';
    pending = onOk;
    cancel.style.display = '';                 // confirm mode: Cancel + Confirm
    okBtn.textContent = CONFIRM_LABEL;
    okBtn.className   = 'btn btn-danger';
    overlay.style.display = 'flex';
    okBtn.focus();
  }
  // Notice-only dialog (replaces alert()): single OK button, nothing destructive.
  function openAlert(message, onOk) {
    msgEl.textContent = message || '';
    pending = onOk || null;
    cancel.style.display = 'none';
    okBtn.textContent = OK_LABEL;
    okBtn.className   = 'btn btn-primary';
    overlay.style.display = 'flex';
    okBtn.focus();
  }
  function close() { overlay.style.display = 'none'; pending = null; }

  okBtn.addEventListener('click', function () { var cb = pending; close(); if (cb) cb(); });
  cancel.addEventListener('click', close);
  overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && overlay.style.display === 'flex') close(); });

  // Public helpers for JS flows that used to call confirm() / alert().
  window.appConfirm = open;
  window.appAlert   = openAlert;

  // Intercept form submits (capture phase). The message can live on the form
  // (data-confirm) or on the specific submit button that triggered it, so one
  // form can have both a plain Save and a confirmed Delete. The clicked button
  // is preserved via requestSubmit so its name/value still reaches the server.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || form.__confirmed) return;
    var submitter = e.submitter;
    var msg = (form.matches && form.matches('[data-confirm]') && form.getAttribute('data-confirm'))
           || (submitter && submitter.matches && submitter.matches('[data-confirm]') && submitter.getAttribute('data-confirm'));
    if (!msg) return;
    e.preventDefault();
    open(msg, function () {
      form.__confirmed = true;
      if (submitter && form.requestSubmit) form.requestSubmit(submitter);
      else form.submit();
    });
  }, true);

  // Intercept link clicks that carry data-confirm.
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[data-confirm]') : null;
    if (a) { e.preventDefault(); open(a.getAttribute('data-confirm'), function () { window.location = a.href; }); }
  });
})();
</script>
</body>
</html>
