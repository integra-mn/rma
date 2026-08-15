<?php defined('RMS') or die(); ?>

  <!-- Tabs. Bottom border spans the full page width. Tab content below is
       wrapped in a 900px column so forms stay readable. -->
  <div class="tab-bar">
    <?php
    $tabs = [
      'general'    => __('settings.general'),
      'appearance' => __('settings.appearance'),
      'smtp'       => __('settings.communications'),
      'integrations' => __('settings.integrations'),
      'fiscal'     => __('settings.fiscalization'),
      'image'      => __('settings.images'),
      'templates'  => __('settings.templates'),
      'permissions'=> __('admin.permissions'),
    ];
    foreach ($tabs as $t => $label): ?>
      <a href="/settings?stab=<?= $t ?>"
         class="tab<?= $stab === $t ? ' active' : '' ?>">
        <?= $label ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Content column — sits below the full-width tab bar. Capped at --w-content. -->
  <div style="max-width:var(--w-content);">

  <!-- ── GENERAL ── -->
  <?php if ($stab === 'general'): ?>
  <div class="card">
    <form method="POST" action="/admin/settings/save" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="tab" value="general">

      <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;"><?= __('settings.application') ?></h2>

      <div class="form-grid" style="">
        <div class="field">
          <label><?= __('settings.app_name') ?></label>
          <input type="text" name="app_name" value="<?= htmlspecialchars(setting('app_name', 'Integra RMA')) ?>">
          <p style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= __('settings.app_name_hint') ?></p>
        </div>
        <div class="field">
          <label><?= __('settings.company_name') ?></label>
          <input type="text" name="company_name" value="<?= htmlspecialchars(setting('company_name', 'Integra Service')) ?>">
          <p style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= __('settings.company_name_hint') ?></p>
        </div>
        <div class="field">
          <label><?= __('settings.logo') ?></label>
          <input type="file" name="app_logo" accept=".png,.svg,.jpg,.webp" class="file-field">
          <?php $logo = setting('app_logo'); if ($logo): ?>
            <p style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= __('settings.current_label') ?> <?= htmlspecialchars($logo) ?></p>
          <?php endif; ?>
        </div>
      </div>

      <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;margin-top:1.5rem;"><?= __('settings.defaults_new_users') ?></h2>

      <div class="form-grid" style="">
        <div class="field">
          <label><?= __('settings.default_language') ?></label>
          <select name="default_lang">
            <?php foreach ($languages as $l): ?>
              <option value="<?= htmlspecialchars($l['code']) ?>" <?= setting('default_lang','en') === $l['code'] ? 'selected' : '' ?>>
                <?= strtoupper(htmlspecialchars($l['code'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label><?= __('settings.default_location') ?></label>
          <select name="default_location">
            <option value=""><?= __('settings.location_none') ?></option>
            <?php foreach ($locations as $loc): ?>
              <option value="<?= (int)$loc['id'] ?>" <?= (int)setting('default_location',0) === (int)$loc['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($loc['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;margin-top:1.5rem;"><?= __('settings.sessions') ?></h2>

      <div class="form-grid">
        <div class="field">
          <label><?= __('settings.session_idle') ?></label>
          <input type="number" name="session_idle_minutes" min="5" max="1440"
                 value="<?= (int) setting('session_idle_minutes', '120') ?>">
          <p style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= __('settings.session_idle_hint') ?></p>
        </div>
        <div class="field">
          <label><?= __('settings.session_max') ?></label>
          <input type="number" name="session_max_minutes" min="5" max="10080"
                 value="<?= (int) setting('session_max_minutes', '480') ?>">
          <p style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= __('settings.session_max_hint') ?></p>
        </div>
      </div>
      <p style="font-size:12px;color:var(--text-muted);margin:0 0 1.5rem;"><?= __('settings.session_note') ?></p>

      <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;margin-top:1.5rem;"><?= __('settings.rma_numbering') ?></h2>

      <div class="field" style="max-width:320px;margin-bottom:8px;">
        <label><?= __('settings.rma_number_format') ?></label>
        <input type="text" name="rma_number_format" value="<?= htmlspecialchars(setting('rma_number_format','{LOC}-{YEAR}-{SEQ5}')) ?>">
      </div>
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:1rem;">
        Tokens: <code>{LOC}</code> = location code &nbsp;·&nbsp; <code>{YYYY}</code> / <code>{YEAR}</code> = 4-digit year &nbsp;·&nbsp; <code>{YY}</code> = 2-digit year &nbsp;·&nbsp; <code>{SEQ4}</code> / <code>{SEQ5}</code> / <code>{SEQ6}</code> = 4/5/6-digit sequence
      </p>

      <?php
        // Restarting the sequence only makes sense when the number contains a
        // year — otherwise the same numbers would come round again. Without one
        // the option is disabled, not merely discouraged.
        $rma_fmt      = setting('rma_number_format','{LOC}-{YEAR}-{SEQ5}');
        $rma_has_year = (bool) preg_match('/\{(YY|YYYY|YEAR)\}/', $rma_fmt);
      ?>
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
        <input type="checkbox" id="rma_number_reset_yearly" name="rma_number_reset_yearly" value="1"
               <?= setting('rma_number_reset_yearly','0') === '1' && $rma_has_year ? 'checked' : '' ?>
               <?= $rma_has_year ? '' : 'disabled' ?>>
        <label for="rma_number_reset_yearly" id="rma-reset-label"
               style="font-size:13px;font-weight:500;margin-bottom:0;<?= $rma_has_year ? '' : 'color:var(--text-muted);' ?>">
          <?= __('settings.rma_reset_yearly') ?>
        </label>
      </div>
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:1.5rem;" id="rma-reset-hint">
        <?= $rma_has_year ? __('settings.rma_reset_yearly_hint') : __('settings.rma_reset_needs_year') ?>
      </p>

      <script>
      // Keep the checkbox in step with the format field as it is typed, so the
      // rule is visible immediately rather than only after saving.
      (function () {
        var fmt  = document.querySelector('input[name="rma_number_format"]');
        var box  = document.getElementById('rma_number_reset_yearly');
        var lbl  = document.getElementById('rma-reset-label');
        var hint = document.getElementById('rma-reset-hint');
        if (!fmt || !box) return;

        var WITH_YEAR = <?= json_encode(__('settings.rma_reset_yearly_hint')) ?>;
        var NO_YEAR   = <?= json_encode(__('settings.rma_reset_needs_year')) ?>;

        function sync() {
          var hasYear = /\{(YY|YYYY|YEAR)\}/.test(fmt.value);
          box.disabled = !hasYear;
          if (!hasYear) box.checked = false;
          lbl.style.color = hasYear ? '' : 'var(--text-muted)';
          hint.textContent = hasYear ? WITH_YEAR : NO_YEAR;
        }
        fmt.addEventListener('input', sync);
      })();
      </script>

      <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;">PDF</h2>
      <div class="form-grid" style="">
        <div class="field">
          <label><?= __('settings.pdf_engine') ?></label>
          <select name="pdf_engine">
            <option value="html" <?= setting('pdf_engine','html')==='html'?'selected':'' ?>><?= __('settings.pdf_engine_html') ?></option>
            <option value="mpdf" <?= setting('pdf_engine','html')==='mpdf'?'selected':'' ?>><?= __('settings.pdf_engine_mpdf') ?></option>
          </select>
        </div>
        <div class="field">
          <label><?= __('settings.paper_size') ?></label>
          <select name="pdf_paper_size">
            <?php foreach (['A4','A5','Letter'] as $ps): ?>
              <option value="<?= $ps ?>" <?= setting('pdf_paper_size','A4')===$ps?'selected':'' ?>><?= $ps ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <button type="submit" class="btn btn-primary"><?= __('btn.save_changes') ?></button>
    </form>
  </div>

  <!-- ── APPEARANCE ── -->
  <?php elseif ($stab === 'appearance'): ?>

  <!-- Theme picker (per-user preference, not a global setting).
       Reaching /settings requires settings.view permission, so anyone who
       can see this page can also flip their own theme. -->
  <?php
      $current_theme = $user['theme'] ?? 'midnight';
      $theme_list = [
          'midnight' => [__('settings.theme_light'),    '#f4f4f0', '#1D9E75'],
          'ocean'    => [__('settings.theme_blue'),     '#eef3f9', '#185fa5'],
          'focus'    => [__('settings.theme_contrast'), '#ffffff', '#00cc66'],
      ];
  ?>
  <?php
    // Colour fields shown (editable) inside each theme's dropdown, in display order.
    $color_labels = [
      'sidebar_bg'=>__('settings.sidebar_bg'), 'sidebar_hover'=>__('settings.sidebar_hover_bg'),
      'sidebar_link'=>__('settings.sidebar_link_default'), 'sidebar_active'=>__('settings.sidebar_link_active'),
      'page_bg'=>__('settings.page_bg'), 'card_bg'=>__('settings.card_bg'),
      'border_color'=>__('settings.border_color'), 'text_color'=>__('settings.text_color'),
      'accent_color'=>__('settings.accent_color'), 'accent_dark'=>__('settings.accent_dark'),
    ];
  ?>
  <div class="card" style="margin-bottom:1.5rem;">
    <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;"><?= __('nav.theme') ?></h2>

    <!-- Theme picker: tabs + the selected theme's colour pills in ONE rounded box,
         so the colours expand seamlessly below the tabs (not detached). The active
         tab is just bold + accent text; divider only between tabs; the chevron
         inside each tab toggles that theme's colours without navigating. -->
    <div style="background:var(--bg-subtle);border:1px solid var(--border);border-radius:10px;overflow:hidden;">
      <div style="display:flex;">
        <?php $ti = 0; foreach ($theme_list as $code => [$label, $bg, $accent]): $active = $current_theme === $code; ?>
          <div id="theme-tab-<?= $code ?>" style="flex:1;display:flex;align-items:stretch;border-bottom:1px solid transparent;<?= $ti > 0 ? 'border-left:1px solid var(--border);' : '' ?>">
            <a href="/admin/theme?t=<?= $code ?>&redirect=<?= urlencode('/settings?stab=appearance') ?>"
               style="flex:1;display:flex;align-items:center;justify-content:center;padding:11px 4px 11px 12px;text-decoration:none;font-size:14px;font-weight:<?= $active ? 500 : 400 ?>;color:<?= $active ? 'var(--accent)' : 'var(--text-primary)' ?>;">
              <?= $label ?>
            </a>
            <button type="button" class="theme-chevron" onclick="toggleThemePanel('<?= $code ?>', this)"
                    style="padding:0 12px;border:none;background:none;cursor:pointer;color:var(--text-muted);display:flex;align-items:center;transition:transform .15s;"><svg width="11" height="7" viewBox="0 0 12 8" fill="none" aria-hidden="true" style="pointer-events:none;"><path d="M1.5 2.5 6 6.5l4.5-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
          </div>
        <?php $ti++; endforeach; ?>
      </div>
      <?php /* Colour pills for each theme; inputs use form="appearance-form" so they
               save with the Appearance form below even though they sit in this box. */ ?>
      <?php foreach ($theme_list as $code => $_tl): $tc = theme_colors($code); ?>
        <div id="theme-panel-<?= $code ?>" style="display:none;padding:14px 12px;">
          <div class="form-grid" style="grid-template-columns:repeat(5,1fr);align-items:end;">
            <?php foreach ($color_labels as $key => $lbl): $val = htmlspecialchars($tc[$key]); ?>
              <div class="field">
                <label><?= $lbl ?></label>
                <div class="color-pill">
                  <input type="color" form="appearance-form" name="theme_<?= $code ?>_<?= $key ?>" value="<?= $val ?>">
                  <input type="text"  form="appearance-form" name="theme_<?= $code ?>_<?= $key ?>_hex" value="<?= $val ?>" placeholder="<?= $val ?>">
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <script>
  function toggleThemePanel(code, btn) {
    var panel = document.getElementById('theme-panel-' + code);
    var isOpen = panel.style.display === 'block';
    document.querySelectorAll('[id^="theme-panel-"]').forEach(function(p){ p.style.display = 'none'; });
    document.querySelectorAll('.theme-chevron').forEach(function(b){ b.style.transform = ''; });
    // Reset every tab's bottom border; the expanded tab loses it so it merges
    // with the colours below.
    document.querySelectorAll('[id^="theme-tab-"]').forEach(function(t){ t.style.borderBottomColor = 'transparent'; });
    if (!isOpen) {
      panel.style.display = 'block';
      btn.style.transform = 'rotate(180deg)';
      document.querySelectorAll('[id^="theme-tab-"]').forEach(function(t){
        t.style.borderBottomColor = (t.id === 'theme-tab-' + code) ? 'transparent' : 'var(--border)';
      });
    }
  }
  </script>

  <div class="card">
    <form method="POST" action="/admin/settings/save" id="appearance-form">
      <?= csrf_field() ?>
      <input type="hidden" name="tab" value="appearance">
      <!-- Saving Appearance also promotes the admin's current theme to the
           global default for new users (see save_appearance in the controller). -->
      <input type="hidden" name="default_theme" value="<?= htmlspecialchars($current_theme) ?>">

      <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;"><?= __('settings.layout') ?></h2>

      <div class="form-grid" style="grid-template-columns:repeat(5,1fr)">
        <div class="field">
          <label><?= __('settings.sidebar_width') ?></label>
          <input type="number" name="sidebar_width" value="<?= (int)setting('sidebar_width', 250) ?>" min="200" max="360">
        </div>
        <div class="field">
          <label><?= __('settings.topbar_height') ?></label>
          <input type="number" name="topbar_height" value="<?= (int)setting('topbar_height', 64) ?>" min="48" max="100">
        </div>
        <div class="field">
          <label><?= __('settings.base_font_size') ?></label>
          <input type="number" name="font_size" value="<?= (int)setting('font_size', 14) ?>" min="12" max="18">
        </div>
        <div class="field">
          <label><?= __('settings.sidebar_font_size') ?></label>
          <input type="number" name="sidebar_font_size" value="<?= (int)setting('sidebar_font_size', 13) ?>" min="12" max="18">
        </div>
        <div class="field">
          <label><?= __('settings.border_radius') ?></label>
          <input type="number" name="border_radius" value="<?= (int)setting('border_radius', 8) ?>" min="0" max="20">
        </div>
      </div>

      <div class="form-grid" style="">
        <div class="field">
          <label><?= __('settings.table_density') ?></label>
          <select name="table_density">
            <?php foreach (['compact'=>__('settings.density_compact'),'normal'=>__('settings.density_normal'),'comfortable'=>__('settings.density_comfortable')] as $v => $l): ?>
              <option value="<?= $v ?>" <?= setting('table_density','normal') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label><?= __('settings.interface_font') ?></label>
          <select name="app_font">
            <?php foreach (array_keys(APP_FONTS) as $f): ?>
              <option value="<?= htmlspecialchars($f) ?>" <?= setting('app_font','Montserrat') === $f ? 'selected' : '' ?>><?= htmlspecialchars($f) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label><?= __('settings.tab_style') ?></label>
          <select name="tab_style">
            <?php foreach (['underline'=>__('settings.tab_underline'),'boxed'=>__('settings.tab_boxed')] as $v => $l): ?>
              <option value="<?= $v ?>" <?= setting('tab_style','underline') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <button type="submit" class="btn btn-primary"><?= __('btn.save_changes') ?></button>

      <script>
      // Keep each colour swatch and its hex text box in sync BOTH ways. The
      // swatch -> hex direction was missing, so a colour picked in the native
      // picker never reached the hex field — and save_appearance reads the
      // *_hex value first, so the picked colour was silently dropped.
      (function () {
        document.querySelectorAll('input[type="color"]').forEach(function (swatch) {
          var hex = document.querySelector('input[name="' + swatch.name + '_hex"]');
          if (!hex) return;
          swatch.addEventListener('input', function () { hex.value = swatch.value.toUpperCase(); });
          hex.addEventListener('input', function () {
            if (/^#[0-9a-fA-F]{6}$/.test(hex.value)) swatch.value = hex.value;
          });
        });
      })();
      </script>
    </form>
  </div>

  <!-- ── COMMUNICATIONS (sub-tabbed) ── -->
  <?php elseif ($stab === 'smtp'): ?>
  <?php
    // Who this channel is used to notify. The same two settings per channel as
    // the grid had, but sitting under the gateway they belong to — configure
    // how a channel connects, then say who it serves, without leaving the pane.
    $notify_audiences = function (string $ch): void {
        echo '<div style="margin:1.25rem 0;">';
        echo '<h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:0.75rem;">'
           . __('settings.notify_use_for') . '</h2>';
        foreach (['customer' => __('settings.notify_customers'),
                  'partner'  => __('settings.notify_partners')] as $who => $label) {
            // Email reaches both today; SMS reaches customers only, the rule
            // agreed for partners, who prefer email. WhatsApp ships off.
            $default = $ch === 'email' ? '1' : (($ch === 'sms' && $who === 'customer') ? '1' : '0');
            $key     = "notify_{$who}_{$ch}";
            printf('<div style="display:flex;align-items:center;gap:8px;margin-bottom:0.5rem;">'
                 . '<input type="checkbox" id="%1$s" name="%1$s" value="1"%2$s>'
                 . '<label for="%1$s" style="font-size:13px;margin-bottom:0;">%3$s</label></div>',
                 $key, setting_on($key, $default === '1') ? ' checked' : '', htmlspecialchars($label));
        }
        echo '<p style="font-size:12px;color:var(--text-muted);margin-top:4px;">'
           . __('settings.notify_note') . '</p>';
        echo '</div>';
    };
  ?>
  <?php $csub = $_GET['csub'] ?? 'smtp'; if (!in_array($csub, ['smtp','whatsapp','sms'], true)) $csub = 'smtp'; ?>
  <?php
  // Messaging providers shared by SMS and WhatsApp. Each provider stores its
  // config under "<channel>_<provider>_<field>" so the two channels never
  // collide even when they use the same gateway. Keep this in sync with
  // MSG_PROVIDERS in settingsController.php.
  $provider_defs = [
      'mtel'       => ['label' => 'm:tel', 'fields' => [
          ['url',    __('settings.api_url'),    'text',   'https://dev.mtel.me:60333/smsApi/sendSMSCG.php'],
          ['user',   __('settings.username'),   'text',   ''],
          ['secret', __('settings.api_secret'), 'secret', ''],
      ]],
      'infobip'    => ['label' => 'Infobip', 'fields' => [
          ['base',   __('settings.base_url'),       'text',   'https://xxxxx.api.infobip.com'],
          ['apikey', __('settings.api_key'),        'secret', ''],
          ['from',   __('settings.from_sender_id'), 'text',   'Integra'],
      ]],
      'vonage'     => ['label' => 'Vonage', 'fields' => [
          ['apikey', __('settings.api_key'),        'text',   ''],
          ['secret', __('settings.api_secret'),     'secret', ''],
          ['from',   __('settings.from_sender_id'), 'text',   'Integra'],
      ]],
      'clickatell' => ['label' => 'Clickatell', 'fields' => [
          ['apikey', __('settings.api_key'),        'secret', ''],
          ['from',   __('settings.from_sender_id'), 'text',   ''],
      ]],
  ];
  // Renders the provider <select> + one config panel per provider. A non-empty
  // choice means the channel is active; "Disabled" means off (no checkbox).
  $render_provider_select = function (string $channel, array $keys, array $extra = []) use ($provider_defs) {
      $current = setting($channel . '_provider', '');
      $options = ['' => __('settings.provider_disabled')];
      foreach ($keys as $k) { $options[$k] = $provider_defs[$k]['label']; }
      foreach ($extra as $k => $lbl) { $options[$k] = $lbl; }
      ?>
      <div class="field" style="margin-bottom:1rem;">
        <label><?= __('settings.provider') ?></label>
        <select name="<?= $channel ?>_provider" onchange="document.querySelectorAll('.<?= $channel ?>-panel').forEach(el=>el.style.display='none');var p=document.getElementById('<?= $channel ?>-'+this.value);if(p)p.style.display='';">
          <?php foreach ($options as $v => $l): ?>
            <option value="<?= $v ?>" <?= $current === $v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php foreach ($keys as $k): $def = $provider_defs[$k]; ?>
        <div id="<?= $channel ?>-<?= $k ?>" class="<?= $channel ?>-panel" style="<?= $current === $k ? '' : 'display:none;' ?>">
          <div class="form-grid" style="">
            <?php foreach ($def['fields'] as [$fk, $flabel, $ftype, $fph]):
                $skey = "{$channel}_{$k}_{$fk}"; $val = setting($skey, ''); ?>
              <div class="field">
                <label><?= $flabel ?></label>
                <?php if ($ftype === 'secret'): ?>
                  <input type="password" name="<?= $skey ?>"
                         placeholder="<?= $val ? __('settings.leave_blank_current') : htmlspecialchars($fph) ?>"
                         autocomplete="new-password">
                <?php else: ?>
                  <input type="text" name="<?= $skey ?>"
                         value="<?= htmlspecialchars($val) ?>"
                         placeholder="<?= htmlspecialchars($fph) ?>" autocomplete="off">
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach;
  };
  ?>
  <div class="tab-bar">
    <?php foreach (['smtp'=>'Email','sms'=>'SMS','whatsapp'=>'WhatsApp'] as $k => $l): ?>
      <a href="/settings?stab=smtp&csub=<?= $k ?>" class="tab<?= $csub === $k ? ' active' : '' ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>

  <?php if ($csub === 'smtp'): ?>
  <div class="card">
    <form method="POST" action="/admin/settings/save">
      <?= csrf_field() ?>
      <input type="hidden" name="tab" value="smtp">

      <style>
        .smtp-dim.is-off label { color: var(--text-muted); }
        /* background-color, not the background shorthand: the shorthand would
           reset background-image and take the chevron off the select. */
        .smtp-dim.is-off input,
        .smtp-dim.is-off select {
          opacity: 0.55; background-color: var(--bg-subtle); cursor: not-allowed;
        }
        /* Enkripcija is disabled rather than readonly, having no readonly of
           its own, and a browser greys a disabled control's text with its own
           colour — so it read a different tone from the readonly inputs beside
           it. Stated explicitly, both now dim by the same 0.55 and nothing
           else. */
        .smtp-dim.is-off select:disabled {
          color: var(--text-primary);
          -webkit-text-fill-color: var(--text-primary);
        }
      </style>
      <script>
        /**
         * Iskljuceno locks every field but Servis itself.
         *
         * readonly, not disabled: a disabled input submits nothing, and
         * save_smtp() writes whatever arrives — so disabling these would blank
         * the host, port, user and from address on the next save, losing
         * exactly what the switch exists to preserve. readonly cannot be typed
         * into and still submits.
         *
         * A <select> has no readonly, so Enkripcija is disabled and a hidden
         * twin carries its value instead.
         */
        function smtpDim(off) {
          document.querySelectorAll('.smtp-dim').forEach(function (el) {
            el.classList.toggle('is-off', off);
          });
          document.querySelectorAll('.smtp-dim input').forEach(function (i) {
            i.readOnly = off;
          });
          document.querySelectorAll('.smtp-dim select').forEach(function (sel) {
            sel.disabled = off;
            var id = 'smtp-mirror-' + sel.name;
            var twin = document.getElementById(id);
            if (off) {
              if (!twin) {
                twin = document.createElement('input');
                twin.type = 'hidden'; twin.id = id; twin.name = sel.name;
                sel.parentNode.appendChild(twin);
              }
              twin.value = sel.value;
            } else if (twin) {
              twin.remove();
            }
          });
        }
        // This script sits above the fields it acts on, so the first call has
        // to wait for them: run at parse time it found no select, threw, and
        // left everything editable after a save — which is exactly what it was
        // added to prevent.
        document.addEventListener('DOMContentLoaded', function () {
          var sel = document.getElementById('smtp-enabled');
          if (sel) smtpDim(sel.value !== '1');
        });
      </script>

      <!-- Servis, Hostname, Port and Enkripcija are one decision made together
           — where mail goes and how it gets there — so they sit on one line.
           SMS and WhatsApp switch off by picking Iskljuceno in their provider
           list; Email had no equivalent, and deleting the host to stop it lost
           the setting rather than parking it. -->
      <div class="form-grid" style="grid-template-columns:repeat(4,1fr)">
        <div class="field">
          <label><?= __('settings.channel_state') ?></label>
          <select name="smtp_enabled" id="smtp-enabled" class="custom-select"
                onchange="smtpDim(this.value !== '1')">
            <option value="1" <?= setting_on('smtp_enabled', true) ? 'selected' : '' ?>><?= __('settings.channel_on') ?></option>
            <option value="0" <?= setting_on('smtp_enabled', true) ? '' : 'selected' ?>><?= __('settings.provider_disabled') ?></option>
          </select>
        </div>
        <div class="field smtp-dim">
          <label><?= __('settings.smtp_host') ?></label>
          <input type="text" name="smtp_host" value="<?= htmlspecialchars(setting('smtp_host','')) ?>">
        </div>
        <div class="field smtp-dim">
          <label><?= __('settings.port') ?></label>
          <input type="number" name="smtp_port" value="<?= (int)setting('smtp_port',587) ?>">
        </div>
        <div class="field smtp-dim">
          <label><?= __('settings.encryption') ?></label>
          <select name="smtp_encryption">
            <?php foreach (['none'=>__('settings.enc_none'),'ssl'=>'SSL','tls'=>'STARTTLS'] as $v => $l): ?>
              <option value="<?= $v ?>" <?= setting('smtp_encryption','tls') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-grid smtp-dim" style="grid-template-columns:repeat(4,1fr)">
        <div class="field">
          <label><?= __('settings.username') ?></label>
          <input type="text" name="smtp_user" value="<?= htmlspecialchars(setting('smtp_user','')) ?>" autocomplete="off">
        </div>
        <div class="field">
          <label><?= __('label.password') ?></label>
          <input type="password" name="smtp_pass" placeholder="<?= __('settings.leave_blank_current') ?>" autocomplete="new-password">
        </div>
        <div class="field">
          <label><?= __('settings.from_name') ?></label>
          <input type="text" name="smtp_from_name" value="<?= htmlspecialchars(setting('smtp_from_name','Integra RMA')) ?>">
        </div>
        <div class="field">
          <label><?= __('settings.from_email') ?></label>
          <input type="email" name="smtp_from" value="<?= htmlspecialchars(setting('smtp_from','')) ?>">
        </div>
      </div>

      <?php $notify_audiences('email'); ?>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <button type="submit" class="btn btn-primary"><?= __('btn.save_changes') ?></button>
        <div style="display:flex;gap:8px;align-items:center;">
          <input type="email" id="test-email" placeholder="<?= __('settings.send_test_to') ?>" style="width:200px;">
          <button type="button" class="btn" onclick="testSmtp()"><?= __('settings.test_connection') ?></button>
        </div>
      </div>
      <p id="smtp-result" style="font-size:13px;margin-top:10px;"></p>
    </form>
  </div>

  <?php elseif ($csub === 'whatsapp'): ?>
  <!-- ── WHATSAPP ── -->
  <div class="card">
    <form method="POST" action="/admin/settings/save">
      <?= csrf_field() ?>
      <input type="hidden" name="tab" value="whatsapp">

      <?php $render_provider_select('whatsapp', ['infobip', 'vonage', 'clickatell']); ?>

      <?php $notify_audiences('whatsapp'); ?>
      <button type="submit" class="btn btn-primary"><?= __('btn.save_changes') ?></button>
    </form>
  </div>

  <?php elseif ($csub === 'sms'): ?>
  <!-- ── SMS ── -->
  <div class="card">
    <form method="POST" action="/admin/settings/save">
      <?= csrf_field() ?>
      <input type="hidden" name="tab" value="sms">

      <?php $render_provider_select('sms', ['mtel', 'infobip', 'vonage', 'clickatell']); ?>

      <?php $notify_audiences('sms'); ?>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <button type="submit" class="btn btn-primary"><?= __('btn.save_changes') ?></button>
        <div style="display:flex;gap:8px;align-items:center;">
          <input type="tel" id="test-sms-to" placeholder="<?= __('settings.sms_test_to') ?>" style="width:200px;">
          <button type="button" class="btn" onclick="testSms()"><?= __('settings.test_connection') ?></button>
        </div>
      </div>
      <p id="sms-result" style="font-size:13px;margin-top:10px;"></p>
    </form>
  </div>

  <?php endif; /* /csub */ ?>

  <!-- ── VENDOR INTEGRATIONS ── -->
  <?php elseif ($stab === 'integrations'): ?>
  <?php
  $isub = $_GET['isub'] ?? 'apple'; if (!in_array($isub, ['apple','tcl'], true)) $isub = 'apple';
  // Yes/No dropdown used for each vendor's Active + Warranty-check toggles.
  $yesno = function (string $label, string $name, bool $on) {
      ?>
      <div class="field" style="width:185px;flex:0 0 185px;">
        <label style="white-space:nowrap;"><?= $label ?></label>
        <select name="<?= $name ?>">
          <option value="1" <?= $on ? 'selected' : '' ?>><?= __('label.yes') ?></option>
          <option value="0" <?= $on ? '' : 'selected' ?>><?= __('label.no') ?></option>
        </select>
      </div>
      <?php
  };
  ?>
  <div class="tab-bar">
    <?php foreach (['apple'=>'Apple','tcl'=>'TCL'] as $k => $l): ?>
      <a href="/settings?stab=integrations&isub=<?= $k ?>" class="tab<?= $isub === $k ? ' active' : '' ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>

  <?php if ($isub === 'apple'): ?>
  <?php
  // Load current adapter row for Apple. If it doesn't exist yet, blank it out.
  $gsx = db_row(
    "SELECT a.endpoint_url, a.credentials, a.is_active, a.last_tested_at
     FROM vendors v JOIN vendor_adapters a ON a.vendor_id = v.id
     WHERE v.slug = 'apple' LIMIT 1"
  );
  $gsx_creds = is_array($gsx) ? (json_decode($gsx['credentials'] ?? '{}', true) ?: []) : [];
  ?>
  <div class="card">
    <form method="POST" action="/admin/settings/save">
      <?= csrf_field() ?>
      <input type="hidden" name="tab" value="gsx">

      <div style="display:flex;gap:16px;margin-bottom:1.25rem;">
        <?php $yesno(__('settings.active'), 'gsx_enabled', !empty($gsx['is_active'])); ?>
        <?php $yesno(__('settings.warranty_check'), 'gsx_warranty_check', (string)setting('gsx_warranty_check','1') === '1'); ?>
      </div>

      <div class="form-grid" style="grid-template-columns:repeat(3,1fr)">
        <div class="field">
          <label><?= __('settings.gsx_api_url') ?></label>
          <input type="url" name="gsx_endpoint_url"
                 value="<?= htmlspecialchars($gsx['endpoint_url'] ?? '') ?>"
                 placeholder="https://api.apple.com/gsx/v2/…" autocomplete="off">
        </div>
        <div class="field">
          <label><?= __('settings.gsx_sold_to') ?></label>
          <input type="text" name="gsx_sold_to"
                 value="<?= htmlspecialchars($gsx_creds['sold_to'] ?? '') ?>" autocomplete="off">
        </div>
        <div class="field">
          <label><?= __('settings.gsx_ship_to') ?></label>
          <input type="text" name="gsx_ship_to"
                 value="<?= htmlspecialchars($gsx_creds['ship_to'] ?? '') ?>" autocomplete="off">
        </div>
        <div class="field" style="grid-column:1/-1;">
          <label><?= __('settings.gsx_bearer_token') ?></label>
          <input type="password" name="gsx_auth_token"
                 placeholder="<?= !empty($gsx_creds['auth_token']) ? __('settings.leave_blank_current') : __('settings.paste_bearer_token') ?>"
                 autocomplete="new-password">
        </div>
        <div class="field" style="grid-column:1/-1;">
          <label><?= __('settings.gsx_cert_path') ?></label>
          <input type="text" name="gsx_cert_path"
                 value="<?= htmlspecialchars($gsx_creds['cert_path'] ?? '') ?>"
                 placeholder="/etc/integra/gsx/client.pem" autocomplete="off">
        </div>
        <div class="field" style="grid-column:1/-1;">
          <label><?= __('settings.gsx_key_path') ?></label>
          <input type="text" name="gsx_key_path"
                 value="<?= htmlspecialchars($gsx_creds['key_path'] ?? '') ?>"
                 placeholder="/etc/integra/gsx/client.key" autocomplete="off">
        </div>
        <div class="field">
          <label><?= __('settings.gsx_timeout') ?></label>
          <input type="number" name="gsx_timeout" min="5" max="60"
                 value="<?= (int)($gsx_creds['timeout_seconds'] ?? 15) ?>" style="max-width:100px;">
        </div>
      </div>

      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <button type="submit" class="btn btn-primary"><?= __('btn.save_changes') ?></button>
        <button type="button" class="btn" onclick="testGsx()"><?= __('settings.test_connection') ?></button>
        <span id="gsx-last-tested" style="font-size:12px;color:var(--text-muted);">
          <?= !empty($gsx['last_tested_at']) ? __('settings.last_tested') . ' ' . format_datetime($gsx['last_tested_at']) : '' ?>
        </span>
      </div>
      <p id="gsx-result" style="font-size:13px;margin-top:10px;"></p>
    </form>
  </div>

  <script>
  function testGsx() {
    var out = document.getElementById('gsx-result');
    out.style.color = 'var(--text-muted)';
    out.textContent = 'Testing…';
    fetch('/admin/settings/gsx-test', {method: 'POST'})
      .then(function (r) { return r.json(); })
      .then(function (res) {
        out.style.color = res.ok ? '#085041' : '#791f1f';
        out.textContent = (res.ok ? '✓ ' : '✗ ') + (res.message || (res.ok ? 'OK' : 'Failed'));
      })
      .catch(function () {
        out.style.color = '#791f1f';
        out.textContent = '✗ Network error';
      });
  }
  </script>

  <?php elseif ($isub === 'tcl'): ?>
  <div class="card">
    <form method="POST" action="/admin/settings/save">
      <?= csrf_field() ?>
      <input type="hidden" name="tab" value="tcl">

      <div style="display:flex;gap:16px;margin-bottom:1.25rem;">
        <?php $yesno(__('settings.active'), 'tcl_enabled', (string)setting('tcl_enabled','0') === '1'); ?>
        <?php $yesno(__('settings.warranty_check'), 'tcl_warranty_check', (string)setting('tcl_warranty_check','0') === '1'); ?>
      </div>

      <div class="form-grid" style="grid-template-columns:repeat(3,1fr)">
        <div class="field">
          <label><?= __('settings.base_url') ?></label>
          <input type="url" name="tcl_base_url"
                 value="<?= htmlspecialchars(setting('tcl_base_url','')) ?>"
                 placeholder="https://api.tcl.com/…" autocomplete="off">
        </div>
        <div class="field">
          <label><?= __('settings.api_key') ?></label>
          <input type="password" name="tcl_api_key"
                 placeholder="<?= setting('tcl_api_key') ? __('settings.leave_blank_current') : '' ?>"
                 autocomplete="new-password">
        </div>
      </div>

      <button type="submit" class="btn btn-primary"><?= __('btn.save_changes') ?></button>
    </form>
  </div>
  <?php endif; /* /isub */ ?>

  <!-- ── FISCALIZATION ── -->
  <?php elseif ($stab === 'fiscal'): ?>
  <div class="card">
    <form method="POST" action="/admin/settings/save" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="tab" value="fiscal">

      <div style="display:flex;align-items:center;gap:8px;margin-bottom:1.5rem;">
        <input type="checkbox" id="fiscal_enabled" name="fiscal_enabled" value="1"
               <?= setting('fiscal_enabled','0') === '1' ? 'checked' : '' ?>>
        <label for="fiscal_enabled" style="font-size:13px;font-weight:500;margin-bottom:0;"><?= __('settings.fiscal_enable') ?></label>
      </div>

      <div class="form-grid" style="">
        <div class="field">
          <label><?= __('settings.environment') ?></label>
          <select name="fiscal_env">
            <option value="test"       <?= setting('fiscal_env','test') === 'test' ? 'selected' : '' ?>><?= __('settings.env_test') ?></option>
            <option value="production" <?= setting('fiscal_env','test') === 'production' ? 'selected' : '' ?>><?= __('settings.env_production') ?></option>
          </select>
        </div>
        <div class="field">
          <label><?= __('settings.fiscal_tin') ?></label>
          <input type="text" name="fiscal_tin" value="<?= htmlspecialchars(setting('fiscal_tin','')) ?>" placeholder="12345678">
        </div>
        <div class="field">
          <label><?= __('settings.fiscal_operator') ?></label>
          <input type="text" name="fiscal_operator_code" value="<?= htmlspecialchars(setting('fiscal_operator_code','')) ?>">
        </div>
        <div class="field">
          <label><?= __('settings.fiscal_bunit') ?></label>
          <input type="text" name="fiscal_bunit_code" value="<?= htmlspecialchars(setting('fiscal_bunit_code','')) ?>">
        </div>
        <div class="field">
          <label><?= __('settings.fiscal_tcr') ?></label>
          <input type="text" name="fiscal_tcr_code" value="<?= htmlspecialchars(setting('fiscal_tcr_code','')) ?>">
        </div>
        <div class="field">
          <label><?= __('settings.fiscal_sw') ?></label>
          <input type="text" name="fiscal_sw_code" value="<?= htmlspecialchars(setting('fiscal_sw_code','')) ?>">
        </div>
        <div class="field">
          <label><?= __('settings.fiscal_maint') ?></label>
          <input type="text" name="fiscal_maint_code" value="<?= htmlspecialchars(setting('fiscal_maint_code','')) ?>">
        </div>
      </div>

      <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;margin-top:1.5rem;"><?= __('settings.certificate_pfx') ?></h2>
      <div class="form-grid">
        <div class="field">
          <label><?= __('settings.cert_file') ?></label>
          <input type="file" name="fiscal_cert" accept=".pfx,.p12" class="file-field">
          <?php $cert = setting('fiscal_cert_path'); if ($cert): ?>
            <p style="font-size:12px;color:var(--accent-text);margin-top:4px;"><?= __('settings.cert_uploaded') ?></p>
          <?php endif; ?>
        </div>
        <div class="field">
          <label><?= __('settings.cert_password') ?></label>
          <input type="password" name="fiscal_cert_pass" placeholder="<?= __('settings.leave_blank_current') ?>" autocomplete="new-password">
        </div>
      </div>

      <p style="font-size:12px;color:var(--text-muted);margin:1rem 0;">
        <?= __('settings.cert_help') ?>
      </p>

      <button type="submit" class="btn btn-primary"><?= __('btn.save_changes') ?></button>
    </form>
  </div>

  <!-- ── IMAGE ── -->
  <?php elseif ($stab === 'image'): ?>
  <div class="card">
    <form method="POST" action="/admin/settings/save">
      <?= csrf_field() ?>
      <input type="hidden" name="tab" value="image">

      <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;"><?= __('settings.processing') ?></h2>

      <div class="form-grid" style="">
        <div class="field">
          <label><?= __('settings.max_width') ?></label>
          <input type="number" name="img_max_width" value="<?= (int)setting('img_max_width',1920) ?>" min="640" max="4096">
        </div>
        <div class="field">
          <label><?= __('settings.max_height') ?></label>
          <input type="number" name="img_max_height" value="<?= (int)setting('img_max_height',1920) ?>" min="640" max="4096">
        </div>
        <div class="field">
          <label><?= __('settings.webp_quality') ?></label>
          <input type="number" name="img_quality" value="<?= (int)setting('img_quality',85) ?>" min="50" max="100">
        </div>
        <div class="field">
          <label><?= __('settings.thumb_size') ?></label>
          <input type="number" name="img_thumb_size" value="<?= (int)setting('img_thumb_size',400) ?>" min="100" max="800">
        </div>
        <div class="field">
          <label><?= __('settings.max_upload') ?></label>
          <input type="number" name="img_max_upload_mb" value="<?= (int)setting('img_max_upload_mb',20) ?>" min="1" max="50">
        </div>
      </div>

      <button type="submit" class="btn btn-primary"><?= __('btn.save_changes') ?></button>
    </form>
  </div>

  <!-- ── TEMPLATES ── -->
  <?php elseif ($stab === 'templates'): ?>
  <div>

    <?php
    $grouped = [];
    foreach ($templates as $t) {
      $grouped[$t['code']][$t['channel']][$t['lang']] = $t;
    }
    $lang_filter = $_GET['lang'] ?? 'en';
    $chan_filter = $_GET['channel'] ?? 'email';
    ?>

    <!-- Channels as a tab bar, the component the rest of the app already uses
         for this shape of choice. Buttons read as actions; these switch between
         views of the same thing, which is what a tab says.

         Language stays a small pair on the right rather than a second tab bar:
         two bars side by side compete for the same attention, and the channel
         is the larger choice — it decides which templates exist, where the
         language only decides which wording of one you are editing. -->
    <div class="tab-bar" style="margin-bottom:1rem;align-items:flex-end;">
      <?php foreach (['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'] as $ch => $ch_label): ?>
        <a href="/settings?stab=templates&channel=<?= $ch ?>&lang=<?= $lang_filter ?>"
           class="tab<?= $chan_filter === $ch ? ' active' : '' ?>"><?= $ch_label ?></a>
      <?php endforeach; ?>
      <span style="margin-left:auto;display:flex;gap:6px;padding-bottom:6px;">
        <?php foreach (['en','me'] as $lc): ?>
          <a href="/settings?stab=templates&channel=<?= $chan_filter ?>&lang=<?= $lc ?>"
             style="font-size:12px;padding:3px 10px;border-radius:6px;text-decoration:none;
                    border:0.5px solid <?= $lang_filter === $lc ? 'var(--accent)' : 'var(--border)' ?>;
                    color:<?= $lang_filter === $lc ? 'var(--accent)' : 'var(--text-secondary)' ?>;"><?= strtoupper($lc) ?></a>
        <?php endforeach; ?>
      </span>
    </div>

    <?php foreach ($grouped as $code => $channels): ?>
      <?php $tmpl = $channels[$chan_filter][$lang_filter] ?? null; ?>
      <?php if (!$tmpl) continue; ?>

      <div class="card" style="margin-bottom:1rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">
          <h2 style="font-size:13px;font-weight:500;color:var(--text-secondary);">
            <?= htmlspecialchars($code) ?>
            <span style="font-size:11px;padding:1px 6px;border-radius:4px;background:var(--bg-subtle);color:var(--text-muted);margin-left:6px;"><?= $chan_filter ?></span>
            <span style="font-size:11px;padding:1px 6px;border-radius:4px;background:var(--bg-subtle);color:var(--text-muted);margin-left:4px;"><?= strtoupper($lang_filter) ?></span>
          </h2>
        </div>
        <form method="POST" action="/admin/settings/save">
      <?= csrf_field() ?>
          <input type="hidden" name="tab" value="template">
          <input type="hidden" name="template_id" value="<?= (int)$tmpl['id'] ?>">
          <?php if ($chan_filter === 'email'): ?>
          <div class="field">
            <label><?= __('settings.subject') ?></label>
            <input type="text" name="subject" value="<?= htmlspecialchars($tmpl['subject'] ?? '') ?>">
          </div>
          <?php endif; ?>
          <div class="field">
            <label><?= __('settings.body') ?></label>
            <textarea name="body" rows="5" style="font-family:monospace;font-size:12px;"><?= htmlspecialchars($tmpl['body']) ?></textarea>
          </div>
          <p style="font-size:11px;color:var(--text-muted);margin-bottom:8px;">
            Tokens: :customer :number :device :status :tracking_url :est_completion :note :code :survey_url
          </p>
          <button type="submit" class="btn btn-primary btn-sm"><?= __('btn.save') ?></button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($stab === 'permissions'): ?>
  <?php include views_path('admin/tabs/permissions.php'); ?>
  <?php endif; ?>

  </div><!-- /1280px content column -->

<script>
function testSms() {
  const to = document.getElementById('test-sms-to').value.trim();
  const result = document.getElementById('sms-result');
  if (!to) { result.style.color = '#a32d2d'; result.textContent = <?= json_encode(__('settings.sms_no_number')) ?>; return; }
  result.style.color = 'var(--text-muted)';
  result.textContent = '…';
  fetch('/admin/settings/sms-test', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'to=' + encodeURIComponent(to)
  })
  .then(r => r.json())
  .then(d => {
    result.style.color = d.success ? 'var(--accent)' : '#a32d2d';
    result.textContent = d.message;
  })
  .catch(() => {
    result.style.color = '#a32d2d';
    result.textContent = <?= json_encode(__('misc.network_error')) ?>;
  });
}

function testSmtp() {
  const email = document.getElementById('test-email').value;
  const result = document.getElementById('smtp-result');
  if (!email) { result.textContent = 'Enter an email address first.'; return; }
  result.style.color = 'var(--text-muted)';
  result.textContent = 'Testing...';
  fetch('/admin/settings/smtp-test', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'to=' + encodeURIComponent(email)
  })
  .then(r => r.json())
  .then(d => {
    result.style.color = d.success ? 'var(--accent)' : '#a32d2d';
    result.textContent = d.message;
  });
}
</script>
