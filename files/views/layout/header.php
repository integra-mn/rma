<?php
defined('RMS') or die('Direct access not permitted');

require_once dirname(dirname(dirname(__FILE__))) . '/helpers/lang.php';

$user    = current_user();
$theme   = $user['theme'] ?? 'midnight';
// Map legacy theme codes -> new ones so sessions cached before the rename (and
// any un-migrated rows) still resolve to a valid theme class / palette.
$theme   = ['light' => 'midnight', 'blue' => 'ocean', 'contrast' => 'focus'][$theme] ?? $theme;
$role    = $user['role']  ?? '';
$uri     = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

function nav_active(string $prefix): string {
    global $uri;
    return str_starts_with($uri, $prefix) ? ' active' : '';
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($user['lang'] ?? 'en') ?>">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="/assets/img/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?= csrf_meta() ?>
  <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' — ' : '' ?>Integra RMA</title>
  <?php $font_slug = strtolower(str_replace(' ', '-', setting('app_font', 'Montserrat'))); ?>
  <link rel="preload" href="/assets/fonts/<?= $font_slug ?>-400-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="/assets/fonts/<?= $font_slug ?>-500-latin.woff2" as="font" type="font/woff2" crossorigin>
  <?php
    // The stylesheet is served with a 30-day cache and no version on the URL,
    // so a deploy changed nothing for anyone who did not hard-refresh — which
    // is everyone. Stamping the file's own mtime on it means a new deploy is a
    // new URL, fetched once, and unchanged files stay cached as before.
    $css_v = static function (string $rel): string {
        $t = @filemtime(ROOT . $rel);
        return $rel . ($t ? '?v=' . $t : '');
    };
  ?>
  <link rel="stylesheet" href="<?= $css_v('/assets/css/fonts.css') ?>">
  <link rel="stylesheet" href="<?= $css_v('/assets/css/app.css') ?>">
  <link rel="stylesheet" href="/assets/css/filefield.css">
  <link rel="stylesheet" href="/assets/css/search-select.css">
  <script>
    // Auto-attach CSRF token to same-origin fetch() POST/PUT/PATCH/DELETE.
    // Reads token from <meta name="csrf-token">. Works for existing code
    // without any per-call changes.
    (function(){
      var meta = document.querySelector('meta[name="csrf-token"]');
      var token = meta ? meta.getAttribute('content') : '';
      if (!token || !window.fetch) return;
      var origFetch = window.fetch.bind(window);
      window.fetch = function(input, init){
        init = init || {};
        var method = (init.method || (input && input.method) || 'GET').toUpperCase();
        if (['POST','PUT','PATCH','DELETE'].indexOf(method) === -1) {
          return origFetch(input, init);
        }
        // Only attach to same-origin (or relative) URLs.
        var url = (typeof input === 'string') ? input : (input && input.url) || '';
        try {
          var u = new URL(url, location.href);
          if (u.origin !== location.origin) return origFetch(input, init);
        } catch (e) { /* relative — same-origin */ }
        var headers = new Headers(init.headers || {});
        if (!headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', token);
        init.headers = headers;
        // If body is FormData without _csrf, append it so non-JSON POSTs work too.
        if (init.body && typeof FormData !== 'undefined' && init.body instanceof FormData) {
          if (!init.body.has('_csrf')) init.body.append('_csrf', token);
        }
        return origFetch(input, init);
      };
    })();
  </script>
  <script>window.SS_SEARCH = <?= json_encode(__('btn.search')) ?>; window.SS_NO_RESULTS = <?= json_encode(__('msg.no_results')) ?>;
    window.FF_CHOOSE = <?= json_encode(__('label.choose_file')) ?>; window.FF_NONE = <?= json_encode(__('label.no_file')) ?>;</script>
  <script src="/assets/js/search-select.js" defer></script>
  <script src="/assets/js/file-field.js" defer></script>
  <script src="/assets/js/list-state.js" defer></script>
  <script src="/assets/js/datepicker.js" defer></script>
  <script src="/assets/js/cities-me.js" defer></script>
  <script src="/assets/js/qrcode.min.js"></script>
  <?php
    // Apply admin appearance settings as CSS overrides. Colour values are
    // already validated in settings_save (#RRGGBB), so we only do a final
    // belt-and-braces filter_var() before interpolation.
    $sw   = (int)setting('sidebar_width', 250);
    $th   = (int)setting('topbar_height', 64);
    $fs   = (int)setting('font_size', 14);
    $sfs  = (int)setting('sidebar_font_size', 13);
    $br   = (int)setting('border_radius', 12);
    $td   = setting('table_density', 'normal');
    $td_pad = match($td) { 'compact' => '5px 8px', 'comfortable' => '13px 12px', default => '9px 10px' };

    // Per-theme colours: the active theme's saved palette (each already validated,
    // falling back to that theme's built-in default).
    $tc   = theme_colors($theme);
    $ac   = $tc['accent_color'];   $acd = $tc['accent_dark'];
    $sbg  = $tc['sidebar_bg'];     $sln = $tc['sidebar_link'];
    $sac  = $tc['sidebar_active']; $shv = $tc['sidebar_hover'];
    $pbg  = $tc['page_bg'];        $crd = $tc['card_bg'];
    $brd  = $tc['border_color'];   $txt = $tc['text_color'];
  ?>
  <style>
    :root {
      --app-font: <?= app_font_stack() ?>;
      --border-radius-app: <?= $br ?>px;
      /* Width tiers — single source of truth.
         --w-content : Administration, Settings, Devices (broader)
         --w-form    : pure form / detail pages (New RMA, RMA view, Repair view) */
      --w-content: 1200px;
      --w-form:    1000px;
    }
    /* The active theme's admin-editable palette (Settings → Appearance). Targets
       the same themed <body> as app.css's defaults so specificity is equal and
       this later rule wins; only the current theme is emitted. */
    body.theme-<?= htmlspecialchars($theme) ?> {
      --accent:         <?= $ac ?>;
      --accent-dark:    <?= $acd ?>;
      --sidebar-bg:     <?= $sbg ?>;
      --sidebar-text:   <?= $sln ?>;
      --sidebar-hover:  <?= $shv ?>;
      --sidebar-active: <?= $sac ?>;
      --bg-page:        <?= $pbg ?>;
      --bg-surface:     <?= $crd ?>;
      --border:         <?= $brd ?>;
      --text-primary:   <?= $txt ?>;
    }
    .sidebar { width: <?= $sw ?>px; }
    .sidebar, .sidebar-link, .sidebar-section, .sidebar-footer { font-size: <?= $sfs ?>px; }
    .topbar  { height: <?= $th ?>px; }
    /* Base font size scales the whole content area relative to the 14px design
       baseline. The UI uses fixed px sizes throughout, so a plain body
       font-size changes almost nothing — zooming the content is what makes the
       setting actually visible. The sidebar (own size control) and topbar are
       excluded so their chrome stays put. */
    .main-content { zoom: <?= round($fs / 14, 4) ?>; }
    .data-table td, .data-table th { padding: <?= $td_pad ?>; }
    .card, .btn { border-radius: <?= $br ?>px; }
    @media (max-width: 768px) { .sidebar { left: -<?= $sw ?>px; } .sidebar.open { left: 0; } }
  </style>
</head>
<body class="theme-<?= htmlspecialchars($theme) ?><?= setting('tab_style','underline') === 'boxed' ? ' tabs-boxed' : '' ?>">

<?php if ($user): ?>
<aside class="sidebar" id="sidebar">

  <a href="/" class="sidebar-logo">
    <img src="/assets/integra.svg" alt="Integra"
         style="width:auto;height:36px;filter:brightness(0) invert(1);opacity:0.9;">
  </a>

  <?php if (can('rma', 'view') || can('repair', 'view') || can('parts', 'view')): ?>
  <?php endif; ?>

  <?php if ($role === 'partner'): ?>
    <!-- Partner portal sidebar — only portal routes visible. -->
    <a href="/portal" class="sidebar-link<?= $uri === '/portal' ? ' active' : '' ?>" style="margin-top:7px;">
      <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
        <rect x="1" y="1" width="6" height="6" rx="1"/>
        <rect x="9" y="1" width="6" height="6" rx="1"/>
        <rect x="1" y="9" width="6" height="6" rx="1"/>
        <rect x="9" y="9" width="6" height="6" rx="1"/>
      </svg>
      <?= __('nav.dashboard') ?>
    </a>
      <a href="/portal/rma" class="sidebar-link<?= nav_active('/portal/rma') ?>">
      <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
        <rect x="2" y="2" width="12" height="12" rx="2"/>
        <path d="M5 8h6M5 5h6M5 11h4"/>
      </svg>
      My RMAs
    </a>

  <?php else: ?>
  <!-- Staff sidebar — permissions-gated per module. -->
  <a href="/" class="sidebar-link<?= $uri === '/' ? ' active' : '' ?>" style="margin-top:7px;">
    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
      <rect x="1" y="1" width="6" height="6" rx="1"/>
      <rect x="9" y="1" width="6" height="6" rx="1"/>
      <rect x="1" y="9" width="6" height="6" rx="1"/>
      <rect x="9" y="9" width="6" height="6" rx="1"/>
    </svg>
    <?= __('nav.dashboard') ?>
  </a>

  <?php if (can('rma', 'view')): ?>
  <a href="/rma" class="sidebar-link<?= nav_active('/rma') ?>">
    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
      <rect x="2" y="2" width="12" height="12" rx="2"/>
      <path d="M5 8h6M5 5h6M5 11h4"/>
    </svg>
    <?= __('nav.rma') ?>
  </a>
  <?php endif; ?>

  <?php if (can('repair', 'view')): ?>
  <a href="/repair" class="sidebar-link<?= nav_active('/repair') ?>">
    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
      <path d="M10.5 2.5a3 3 0 0 1 0 4.24l-6 6a1.5 1.5 0 0 1-2.12-2.12l6-6A3 3 0 0 1 10.5 2.5z"/>
      <circle cx="11" cy="4" r="1" fill="currentColor" stroke="none"/>
    </svg>
    <?= __('nav.repairs') ?>
  </a>
  <?php endif; ?>

  <?php if (can('claims', 'view')): ?>
  <a href="/claims" class="sidebar-link<?= nav_active('/claims') ?>">
    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
      <path d="M8 1.5 2.5 3.5v4c0 3 2.3 5.6 5.5 6.9 3.2-1.3 5.5-3.9 5.5-6.9v-4L8 1.5z"/>
      <path d="M5.8 7.8 7.3 9.3l3-3.2"/>
    </svg>
    <?= __('ins.queue') ?>
  </a>
  <?php endif; ?>

  <?php if (can('shipments', 'view')): ?>
  <a href="/shipments" class="sidebar-link<?= nav_active('/shipments') ?>">
    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
      <path d="M1 4h9v7H1zM10 6h3l2 2v3h-5z"/>
      <circle cx="4" cy="13" r="1.3"/>
      <circle cx="12" cy="13" r="1.3"/>
    </svg>
    <?= __('nav.shipments') ?>
  </a>
  <?php endif; ?>

  <?php if (can('parts', 'view')): ?>
  <!-- Suppliers is a Parts tab but keeps its own /suppliers URL, so the
       highlight has to cover both or the sidebar goes blank on that tab. -->
  <a href="/parts" class="sidebar-link<?= nav_active('/parts') ?: nav_active('/suppliers') ?>">
    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
      <path d="M8 2L14 5.5v5L8 14 2 10.5v-5L8 2z"/>
      <path d="M8 2v12M2 5.5l6 3.5 6-3.5"/>
    </svg>
    <?= __('nav.parts') ?>
  </a>
  <?php endif; ?>

  <?php if (can('devices', 'view')): ?>
  <a href="/devices" class="sidebar-link<?= nav_active('/devices') ?>">
    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
      <rect x="4.5" y="1.5" width="7" height="13" rx="1.5"/>
      <line x1="6.8" y1="12.6" x2="9.2" y2="12.6"/>
    </svg>
    <?= __('nav.devices') ?>
  </a>
  <?php endif; ?>
  <?php if (can('customers', 'view') || can('partners', 'view')): ?>
  <?php endif; ?>

  <?php if (can('partners', 'view')): ?>
  <a href="/partners" class="sidebar-link<?= nav_active('/partners') ?>">
    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
      <circle cx="5" cy="5" r="2.5"/>
      <circle cx="11" cy="5" r="2.5"/>
      <path d="M1 14c0-2.5 2-4 4.5-4M15 14c0-2.5-2-4-4.5-4"/>
    </svg>
    <?= __('nav.partners') ?>
  </a>
  <?php endif; ?>


  <?php if (can('customers', 'view')): ?>
  <a href="/customers" class="sidebar-link<?= nav_active('/customers') ?>">
    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
      <circle cx="8" cy="5" r="3"/>
      <path d="M2 14c0-3.314 2.686-5 6-5s6 1.686 6 5"/>
    </svg>
    <?= __('nav.customers') ?>
  </a>
  <?php endif; ?>

  <?php if (can('invoicing', 'view')): ?>
  <a href="/invoices" class="sidebar-link<?= nav_active('/invoices') ?>">
    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
      <rect x="3" y="1" width="10" height="14" rx="1.5"/>
      <path d="M6 5h4M6 8h4M6 11h2"/>
    </svg>
    <?= __('nav.invoices') ?>
  </a>
  <?php endif; ?>

  <?php if (can('reports', 'view')): ?>
  <a href="/reports" class="sidebar-link<?= nav_active('/reports') ?>">
    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
      <path d="M2 12L6 7l3 3 2-2 3 3"/>
      <rect x="1" y="1" width="14" height="14" rx="2"/>
    </svg>
    <?= __('nav.reports') ?>
  </a>
  <?php endif; ?>

  <!-- Both links are permission-driven now. They shared one hardcoded role
       list, so Settings > Permissions could never grant either of them:
       you could tick the boxes and nothing happened. -->
  <?php if (can('administration', 'view')): ?>
  <a href="/administration" class="sidebar-link<?= nav_active('/administration') ?>">
    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
      <circle cx="6" cy="5" r="2.5"/>
      <path d="M1 14c0-3 2.2-4.5 5-4.5s5 1.5 5 4.5"/>
      <path d="M11 2l1.5 1.5L14 2M11 5l1.5 1.5L14 5"/>
    </svg>
    <?= __('nav.administration') ?>
  </a>
  <?php endif; ?>
  <?php if (can('settings', 'view')): ?>
  <a href="/settings" class="sidebar-link<?= nav_active('/settings') ?>">
    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
      <circle cx="8" cy="8" r="2.5"/>
      <path d="M8 1v2M8 13v2M1 8h2M13 8h2M3.05 3.05l1.41 1.41M11.54 11.54l1.41 1.41M3.05 12.95l1.41-1.41M11.54 4.46l1.41-1.41"/>
    </svg>
    <?= __('nav.settings') ?>
  </a>
  <?php endif; ?>
  <?php endif; /* /staff sidebar — see partner branch at top */ ?>

  <div class="sidebar-footer">
    <a href="/profile" class="user-name" style="text-decoration:none;"><?= htmlspecialchars($user['name']) ?></a>
    <a href="/auth/logout" title="<?= __('nav.logout') ?>" aria-label="<?= __('nav.logout') ?>">
      <svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M6 14H3a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/>
        <path d="M10.5 11 14 8l-3.5-3"/>
        <path d="M14 8H6"/>
      </svg>
    </a>
  </div>

</aside>
<?php endif; ?>

<div class="main-wrapper">
  <?php if ($user): ?>
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:10px;">
      <button class="hamburger" onclick="document.getElementById('sidebar').classList.toggle('open')" aria-label="Menu">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 6h14M3 10h14M3 14h14"/>
        </svg>
      </button>
      <?php if (isset($breadcrumb_parent)): ?>
        <span class="topbar-title">
          <a href="<?= htmlspecialchars($breadcrumb_parent_url ?? '/admin') ?>"
             style="color:var(--text-primary);text-decoration:none;font-weight:500;">
            <?= htmlspecialchars($breadcrumb_parent) ?>
          </a>
          <span style="color:var(--text-muted);margin:0 6px;">›</span>
<?php /* Controllers can set $topbar_title to '' to suppress the title when
         the body already shows it (e.g. portal RMA detail with hero). */ ?>
<?= htmlspecialchars($topbar_title ?? ($page_title ?? '')) ?>
        </span>
      <?php else: ?>
        <span class="topbar-title"><?= htmlspecialchars($topbar_title ?? ($page_title ?? 'Integra RMA')) ?></span>
      <?php endif; ?>
    </div>
    <div class="topbar-right">
      <!-- Toast notification -->
      <div id="topbar-toast" style="display:none;align-items:center;gap:8px;padding:6px 14px;border-radius:20px;font-size:13px;font-weight:500;max-width:320px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;transition:opacity 2s ease;"></div>
      <!-- Language selector — expands out of the pill as one connected panel -->
      <?php
      $curLang = $user['lang'] ?? 'en';
      $flagMap = ['en'=>'gb','me'=>'me','de'=>'de','fr'=>'fr','es'=>'es','it'=>'it'];
      $curFlag = $flagMap[$curLang] ?? 'gb';
      $langs = [
        'en' => ['flag'=>'gb', 'label'=>'EN'],
        'me' => ['flag'=>'me', 'label'=>'ME'],
        'de' => ['flag'=>'de', 'label'=>'DE'],
        'fr' => ['flag'=>'fr', 'label'=>'FR'],
        'es' => ['flag'=>'es', 'label'=>'ES'],
        'it' => ['flag'=>'it', 'label'=>'IT'],
      ];
      ?>
      <?php if (can('preferences', 'lang')): ?>
      <div class="lang-switcher" id="lang-switcher">
        <button type="button" class="lang-current" aria-haspopup="true" aria-expanded="false">
          <span class="lang-flag">
            <img src="/assets/flags/<?= $curFlag ?>.svg" width="24" height="24" alt="<?= strtoupper($curLang) ?>">
          </span>
          <span class="lang-code"><?= strtoupper($curLang) ?></span>
          <svg class="lang-caret" viewBox="0 0 10 6" width="10" height="6" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 1l4 4 4-4"/></svg>
        </button>

        <div class="lang-menu" id="lang-drop">
          <?php foreach ($langs as $code => $l): $isCur = ($code === $curLang); ?>
            <a href="#" class="lang-option<?= $isCur ? ' active' : '' ?>"
               onclick="<?= $isCur ? '' : "switchLangTo('".$code."');" ?>return false;">
              <span class="lang-flag">
                <img src="/assets/flags/<?= $l['flag'] ?>.svg" width="24" height="24" alt="<?= $l['label'] ?>">
              </span>
              <span class="lang-code"><?= $l['label'] ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; /* can preferences.lang */ ?>
    </div>
  </div>
  <?php endif; ?>

  <main class="main-content">

  <?php if (!empty($_SESSION['flash'])): ?>
    <div style="padding:1rem 1.5rem 0;" id="flash-msg">
      <div class="alert alert-<?= htmlspecialchars($_SESSION['flash']['type']) ?>">
        <?= htmlspecialchars($_SESSION['flash']['message']) ?>
      </div>
    </div>
    <script>
      setTimeout(function() {
        const el = document.getElementById('flash-msg');
        if (el) {
          el.style.transition = 'opacity 2s ease';
          el.style.opacity = '0';
          setTimeout(function() { el.style.display = 'none'; }, 2000);
        }
      }, 4000);
    </script>
    <?php unset($_SESSION['flash']); ?>
  <?php endif; ?>

<script>
function switchLangTo(lang) {
  window.location.href = '/admin/lang?l=' + lang + '&from=' + encodeURIComponent(window.location.pathname + window.location.search);
}

// Language switcher — click the pill to toggle; click-away or Esc to close.
(function() {
  var sw = document.getElementById('lang-switcher');
  if (!sw) return;
  var btn = sw.querySelector('.lang-current');
  function close() { sw.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); }
  btn.addEventListener('click', function(e) {
    e.stopPropagation();
    var open = sw.classList.toggle('open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  document.addEventListener('click', function(e) { if (!sw.contains(e.target)) close(); });
  document.addEventListener('keydown', function(e) { if (e.key === 'Escape') close(); });
})();

// Auto-dismiss all success alerts — 4s visible, 0.5s fade
document.addEventListener('DOMContentLoaded', function() {
  setTimeout(function() {
    document.querySelectorAll('.alert-success').forEach(function(el) {
      el.style.transition = 'opacity 2s ease';
      el.style.opacity = '0';
      setTimeout(function() { el.style.display = 'none'; }, 2000);
    });
  }, 4000);
});
function cycleTheme() {
  const themes  = ['midnight','ocean','focus'];
  const current = ([...document.body.classList].find(c => c.startsWith('theme-')) || 'theme-midnight').replace('theme-','');
  const next    = themes[(themes.indexOf(current) + 1) % themes.length];
  document.body.className = document.body.className.replace(/theme-\S+/, 'theme-' + next);
  fetch('/admin/theme?t=' + next);
}
function switchLang() {
  const current = document.documentElement.lang || 'en';
  const next    = current === 'me' ? 'en' : 'me';
  fetch('/admin/lang?l=' + next).then(() => location.reload());
}
document.addEventListener('click', function(e) {
  const sidebar = document.getElementById('sidebar');
  if (sidebar && !sidebar.contains(e.target) && !e.target.closest('.hamburger')) {
    sidebar.classList.remove('open');
  }
});
</script>

<style>
.custom-select-wrap { position:relative; }
/* An open field lifts above the fields below it so its panel isn't covered. */
.custom-select-wrap.open { z-index:50; }
.custom-select-btn {
  width:100%; height:40px; padding:0 10px; gap:8px; font-size:13px;
  border:0.5px solid var(--border); border-radius:8px;
  background:var(--bg-surface); color:var(--text-primary);
  cursor:pointer; text-align:left; display:flex; align-items:center;
  justify-content:space-between; font-family:inherit; box-sizing:border-box;
  position:relative; z-index:2; transition:border-radius .15s ease;
}
.custom-select-btn > span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.custom-select-btn svg { flex-shrink:0; color:var(--text-muted); transition:transform .18s ease; }
.custom-select-wrap.open .custom-select-btn svg { transform:rotate(180deg); }
.custom-select-drop {
  display:none; position:absolute; left:0; right:0;
  background:var(--bg-surface); border:0.5px solid var(--border);
  box-shadow:0 8px 18px rgba(0,0,0,0.08);
  z-index:1; padding:4px; max-height:220px; overflow-y:auto;
}
/* Connected — the field grows downward as one shape (like the language switcher) */
.custom-select-drop.drop-down { top:100%; border-top:none; border-radius:0 0 8px 8px; }
.custom-select-wrap.open-down .custom-select-btn {
  border-bottom-left-radius:0; border-bottom-right-radius:0; border-bottom-color:transparent;
}
/* Connected — grows upward when there isn't room below */
.custom-select-drop.drop-up { bottom:100%; border-bottom:none; border-radius:8px 8px 0 0; }
.custom-select-wrap.open-up .custom-select-btn {
  border-top-left-radius:0; border-top-right-radius:0; border-top-color:transparent;
}
.custom-select-item {
  padding:8px 10px; font-size:13px; cursor:pointer;
  border-radius:7px; color:var(--text-primary);
}
.custom-select-item:hover { background:var(--bg-subtle); }
.custom-select-item.selected { background:var(--accent); color:#fff; }

/* ── Language switcher — pill that expands into a connected panel ───────── */
.lang-switcher { position:relative; }
.lang-switcher .lang-flag {
  width:24px; height:24px; border-radius:50%; overflow:hidden;
  display:inline-flex; flex-shrink:0;
}
.lang-switcher .lang-flag img { width:24px; height:24px; object-fit:cover; border-radius:50%; }
.lang-switcher .lang-code { font-weight:500; }

/* Trigger button — matches .ss-btn (search-select) for a consistent look. */
.lang-current {
  display:flex; align-items:center; gap:7px;
  width:100%; box-sizing:border-box;
  background:var(--bg-surface);
  border:0.5px solid var(--border); border-radius:8px;
  padding:5px 12px 5px 6px;
  font-size:13px; color:var(--text-primary); cursor:pointer;
  position:relative;
}
.lang-current:hover { background:var(--bg-subtle); }
.lang-current .lang-caret { margin-left:2px; color:var(--text-muted); transition:transform .18s ease; }

/* Detached floating menu — same shape as .ss-menu. */
.lang-menu {
  display:none;
  position:absolute; top:calc(100% + 6px); left:0; right:0;
  box-sizing:border-box;
  background:var(--bg-surface);
  border:0.5px solid var(--border);
  border-radius:10px;
  padding:6px;
  z-index:200;
  box-shadow:0 8px 22px rgba(0,0,0,0.12);
}
.lang-switcher.open .lang-menu { display:block; }
.lang-switcher.open .lang-caret { transform:rotate(180deg); }

/* Rows — same padding/hover/active as .ss-item. */
.lang-option {
  display:flex; align-items:center; gap:7px;
  padding:8px 10px; margin-top:2px;
  border-radius:6px; text-decoration:none;
  font-size:13px; color:var(--text-primary);
  transition:background .15s;
}
.lang-option:first-child { margin-top:0; }
.lang-option:hover { background:var(--bg-subtle); }
.lang-option.active { background:var(--accent-bg); color:var(--accent-text); font-weight:600; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Retired: all selects are now enhanced by search-select.js (the Odmori-style
  // searchable dropdown). This legacy enhancer is disabled by matching nothing.
  document.querySelectorAll('select.__legacy_custom_select_disabled__').forEach(function(sel) {
    // Wrap and hide native select
    const wrap = document.createElement('div');
    wrap.className = 'custom-select-wrap';
    sel.parentNode.insertBefore(wrap, sel);
    wrap.appendChild(sel);
    sel.style.display = 'none';

    // Button
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'custom-select-btn';
    btn.innerHTML = '<span>—</span>'
      + '<svg viewBox="0 0 10 6" width="10" height="6" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 1l4 4 4-4"/></svg>';
    wrap.appendChild(btn);
    sel._customBtn = btn;

    // Dropdown container (items populated by buildItems)
    const drop = document.createElement('div');
    drop.className = 'custom-select-drop';
    wrap.appendChild(drop);

    // (Re)build dropdown items from the current <select> options.
    // Exposed on the element so other code (or a MutationObserver) can
    // force a rebuild when options change dynamically.
    function buildItems() {
      drop.innerHTML = '';
      Array.from(sel.options).forEach(function(opt) {
        if (!opt.value) return; // skip placeholder
        const item = document.createElement('div');
        item.className = 'custom-select-item' + (opt.selected ? ' selected' : '');
        item.textContent = opt.text;
        item.dataset.value = opt.value;
        item.addEventListener('mousedown', function(e) {
          e.preventDefault();
          sel.value = opt.value;
          btn.querySelector('span').textContent = opt.text;
          drop.querySelectorAll('.custom-select-item').forEach(i => i.classList.remove('selected'));
          item.classList.add('selected');
          drop.style.display = 'none';
          sel.dispatchEvent(new Event('change', {bubbles:true}));
        });
        drop.appendChild(item);
      });
      const selectedOpt = sel.options[sel.selectedIndex];
      btn.querySelector('span').textContent = (selectedOpt && selectedOpt.text) ? selectedOpt.text : '—';
    }
    sel._customRebuild = buildItems;
    buildItems();

    // Auto-rebuild when native <select> children change (filterModels etc.).
    new MutationObserver(buildItems).observe(sel, {childList: true});

    // Toggle open/close
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      const isOpen = drop.style.display === 'block';
      closeAllCustomSelects();
      if (!isOpen) {
        const rect = wrap.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom;
        const dropH = Math.min(sel.options.length * 37 + 8, 220);
        const up = spaceBelow < dropH + 8;
        drop.className = 'custom-select-drop ' + (up ? 'drop-up' : 'drop-down');
        drop.style.display = 'block';
        wrap.classList.add('open', up ? 'open-up' : 'open-down');
      }
    });
  });

  function closeAllCustomSelects() {
    document.querySelectorAll('.custom-select-drop').forEach(d => d.style.display = 'none');
    document.querySelectorAll('.custom-select-wrap').forEach(w => w.classList.remove('open','open-up','open-down'));
  }
  document.addEventListener('click', closeAllCustomSelects);
});
</script>

<script>
// ── Evidence: desktop upload ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('input[id^="ev-input-"]').forEach(function(input) {
    input.addEventListener('change', function() {
      const card   = input.dataset.card;
      const files  = Array.from(input.files);
      const grid   = document.getElementById('ev-grid-' + card);
      const prog   = document.getElementById('ev-progress-' + card);
      const bar    = document.getElementById('ev-bar-' + card);
      const status = document.getElementById('ev-status-' + card);
      if (!files.length) return;
      let done = 0;
      prog.style.display = 'block';
      grid.style.display = 'grid';
      files.forEach(function(file) {
        const fd = new FormData();
        fd.append('file', file);
        fd.append('repair_job_id', (input.dataset.repair && input.dataset.repair !== '0') ? input.dataset.repair : '');
        fd.append('rma_id', (input.dataset.rma && input.dataset.rma !== '0') ? input.dataset.rma : '');
        fd.append('stage', input.dataset.stage);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/evidence/upload');
        xhr.onload = function() {
          done++;
          bar.style.width = Math.round(done / files.length * 100) + '%';
          status.textContent = done + ' of ' + files.length + ' uploaded';
          try {
            const res = JSON.parse(xhr.responseText);
            if (res.success) evAddThumb(card, res.id, res.url, res.filename);
          } catch(e) {}
          if (done === files.length) {
            setTimeout(function() { prog.style.display = 'none'; bar.style.width = '0%'; }, 1500);
          }
        };
        xhr.send(fd);
      });
      input.value = '';
    });
  });
});

function evAddThumb(card, id, url, name) {
  const grid = document.getElementById('ev-grid-' + card);
  if (!grid) return;
  grid.style.display = 'grid';
  const thumb = document.createElement('div');
  thumb.className = 'ev-thumb';
  thumb.dataset.id = id;
  thumb.style.cssText = 'position:relative;aspect-ratio:1;border-radius:8px;overflow:hidden;background:var(--bg-subtle);';
  thumb.innerHTML = '<img src="' + url + '" data-name="' + (name||'').replace(/"/g,'&quot;') + '" style="width:100%;height:100%;object-fit:cover;cursor:pointer;" onclick="evLightbox(this,\'' + (name||'').replace(/'/g,"\\'") + '\')">'
    + '<button onclick="evDelete(' + id + ',\'' + card + '\')" style="position:absolute;top:4px;right:4px;background:rgba(0,0,0,0.55);border:none;border-radius:50%;width:22px;height:22px;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>';
  grid.appendChild(thumb);
  const h2 = document.querySelector('#ev-card-' + card + ' h2');
  if (h2) {
    let badge = h2.querySelector('span');
    if (!badge) { badge = document.createElement('span'); badge.style.cssText = 'font-size:12px;font-weight:400;color:var(--text-muted);margin-left:6px;'; h2.appendChild(badge); }
    badge.textContent = grid.querySelectorAll('.ev-thumb').length;
  }
}

function evDelete(id, card) {
  appConfirm(<?= json_encode(__('msg.confirm_delete')) ?>, function () {
    fetch('/evidence/' + id + '/delete', {method:'POST'})
      .then(function(r) { return r.json(); })
      .then(function(res) {
        if (!res.success) return;
        const thumb = document.querySelector('.ev-thumb[data-id="' + id + '"]');
        if (thumb) thumb.remove();
        const grid = document.getElementById('ev-grid-' + card);
        const h2 = document.querySelector('#ev-card-' + card + ' h2');
        const count = grid ? grid.querySelectorAll('.ev-thumb').length : 0;
        if (h2) {
          const badge = h2.querySelector('span');
          if (badge) { if (count) badge.textContent = count; else badge.remove(); }
        }
      });
  });
}

// The open photo and the ones beside it. A card's grid is the set you page
// through: the RMA and repair screens can both show more than one card, and
// arrows that jumped between them would be a surprise.
let evLbShots = [], evLbAt = 0;

function evLightbox(target, name) {
  // Called with the clicked <img>, so its neighbours are known. Older markup
  // passed a src string; then the image is found by matching that src.
  const img = (typeof target === 'string')
    ? Array.from(document.querySelectorAll('.ev-thumb img')).find(function (i) { return i.src === target; })
    : target;
  const src = (typeof target === 'string') ? target : (img ? img.src : '');

  const grid = img ? img.closest('[id^="ev-grid-"]') : null;
  evLbShots = grid
    ? Array.from(grid.querySelectorAll('.ev-thumb img')).map(function (i) {
        return { src: i.src, name: i.dataset.name || '' };
      })
    : [];
  if (!evLbShots.length) evLbShots = [{ src: src, name: name || '' }];
  const at = evLbShots.findIndex(function (s) { return s.src === src; });
  evLbAt = at < 0 ? 0 : at;

  let lb = document.getElementById('ev-lightbox');
  if (!lb) {
    lb = document.createElement('div');
    lb.id = 'ev-lightbox';
    lb.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:pointer;';
    lb.onclick = function (e) { if (e.target === lb) evLbClose(); };
    document.body.appendChild(lb);
    document.addEventListener('keydown', evLbKey);
  }
  lb.style.display = 'flex';
  evLbRender();
}

function evLbClose() {
  const lb = document.getElementById('ev-lightbox');
  if (lb) lb.remove();
  document.removeEventListener('keydown', evLbKey);
}

function evLbKey(e) {
  if (!document.getElementById('ev-lightbox')) return;
  if (e.key === 'Escape')     evLbClose();
  if (e.key === 'ArrowLeft')  evLbStep(-1);
  if (e.key === 'ArrowRight') evLbStep(1);
}

// Wraps around, so the arrows never dead-end on a set of three.
function evLbStep(by) {
  if (evLbShots.length < 2) return;
  evLbAt = (evLbAt + by + evLbShots.length) % evLbShots.length;
  evLbRender();
}

// The caption says 52174_001, not 52174_001.webp — the extension is how the
// file is stored, not what the photo is called. Only a short trailing
// extension is taken, so a name that simply contains a dot survives.
function evLbLabel(name) {
  return String(name || '').replace(/\.[A-Za-z0-9]{2,5}$/, '');
}

function evLbRender() {
  const lb = document.getElementById('ev-lightbox');
  if (!lb) return;
  const shot = evLbShots[evLbAt] || { src: '', name: '' };
  const many = evLbShots.length > 1;
  const chev = 'position:absolute;top:50%;transform:translateY(-50%);background:rgba(0,0,0,0.45);border:none;border-radius:50%;width:44px;height:44px;display:flex;align-items:center;justify-content:center;cursor:pointer;padding:0;';
  const arrow = function (dir) {
    return '<button onclick="event.stopPropagation();evLbStep(' + (dir === 'prev' ? -1 : 1) + ')" '
      + 'aria-label="' + dir + '" style="' + chev + (dir === 'prev' ? 'left:24px;' : 'right:24px;') + '">'
      + '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" '
      + 'stroke-linecap="round" stroke-linejoin="round"><polyline points="'
      + (dir === 'prev' ? '15 18 9 12 15 6' : '9 18 15 12 9 6') + '"/></svg></button>';
  };

  lb.innerHTML =
      '<div style="position:relative;display:flex;flex-direction:column;align-items:center;">'
    +   '<img src="' + shot.src + '" style="max-width:90vw;max-height:85vh;border-radius:8px;box-shadow:0 8px 40px rgba(0,0,0,0.6);">'
    +   '<p style="color:rgba(255,255,255,0.7);font-size:13px;margin-top:12px;">'
    +     evLbLabel(shot.name) + (many ? ' &nbsp;·&nbsp; ' + (evLbAt + 1) + ' / ' + evLbShots.length : '')
    +   '</p>'
    + '</div>'
    + (many ? arrow('prev') + arrow('next') : '');
}

// ── Evidence: QR / mobile upload ─────────────────────────────────────────
let evQrPollTimer = null, evQrCard = null, evQrBaseCount = 0, evVisitTimer = null;

function evShowQr(card, repairId, rmaId, stage) {
  // Support both old per-card modal and new shared modal
  const modal  = document.getElementById('ev-qr-modal') || document.getElementById('ev-qr-modal-' + card);
  const imgDiv = document.getElementById('ev-qr-img') || document.getElementById('ev-qr-img-' + card);
  const nc     = document.getElementById('ev-qr-newcount') || document.getElementById('ev-qr-poll-' + card);
  if (!modal || !imgDiv) { appAlert(<?= json_encode(__('misc.qr_modal_missing')) ?>); return; }
  imgDiv.innerHTML = '<p style="color:var(--text-muted);font-size:13px;">Generating...</p>';
  nc.textContent = '';
  evQrCard = card;
  const grid = document.getElementById('ev-grid-' + card);
  evQrBaseCount = grid ? grid.querySelectorAll('.ev-thumb').length : 0;
  modal.style.display = 'flex';

  const fd = new FormData();
  fd.append('repair_job_id', repairId || '');
  fd.append('rma_id', rmaId || '');
  fd.append('stage', stage);

  fetch('/evidence/token', {method:'POST', body:fd})
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (!res.success) { imgDiv.innerHTML = '<p style="color:#a32d2d;">Error generating link</p>'; return; }
      imgDiv.innerHTML = '<div id="ev-qr-canvas"></div>';
      if (typeof QRCode !== 'undefined') {
        new QRCode(document.getElementById('ev-qr-canvas'), {
          text: res.url, width: 200, height: 200,
          colorDark: '#1a1a18', colorLight: '#ffffff',
          correctLevel: QRCode.CorrectLevel.M
        });
      } else {
        imgDiv.innerHTML = '<p style="font-size:11px;word-break:break-all;padding:1rem;color:var(--text-secondary);">' + res.url + '</p>';
      }
      // Poll for phone opening the page
      evVisitTimer = setInterval(function() {
        fetch('/evidence/token-status?token=' + res.token)
          .then(function(r) { return r.json(); })
          .then(function(data) {
            const nc = document.getElementById('ev-qr-newcount') || document.getElementById('ev-qr-poll-' + card);
            if (data.completed) {
              clearInterval(evVisitTimer); evVisitTimer = null;
              if (nc) { nc.style.color = 'var(--accent)'; nc.textContent = '✓ Done — refreshing...'; }
              setTimeout(function() { location.reload(); }, 1000);
            } else if (data.visited) {
              if (nc && !nc.textContent.includes('connected')) {
                nc.style.color = 'var(--accent)';
                nc.textContent = '📱 Phone connected — take your photos!';
                setTimeout(evCloseQr, 2500);
              }
            }
          }).catch(function(){});
      }, 2000);
      evQrPollTimer = setInterval(function() {
        fetch('/evidence/poll?' + (repairId ? 'r='+repairId : 'rma='+rmaId) + '&stage='+stage)
          .then(function(r) { return r.json(); })
          .then(function(photos) {
            if (!Array.isArray(photos)) return;
            const g = document.getElementById('ev-grid-' + card);
            if (!g) return;
            const known = new Set(Array.from(g.querySelectorAll('.ev-thumb')).map(function(t) { return t.dataset.id; }));
            photos.forEach(function(p) {
              if (!known.has(String(p.id))) {
                evAddThumb(card, p.id, p.url, p.original_name);
              }
            });
            const total = g.querySelectorAll('.ev-thumb').length - evQrBaseCount;
            const ncEl = document.getElementById('ev-qr-newcount');
            if (ncEl && total > 0) ncEl.textContent = total + ' new photo' + (total > 1 ? 's' : '') + ' received ✓';
          }).catch(function(){});
      }, 4000);
    });
}

function evCloseQr() {
  const modal = document.getElementById('ev-qr-modal') || (evQrCard ? document.getElementById('ev-qr-modal-' + evQrCard) : null);
  if (modal) modal.style.display = 'none';
  if (evQrPollTimer) { clearInterval(evQrPollTimer); evQrPollTimer = null; }
  evQrCard = null;
  // Note: evVisitTimer keeps running until completed
}

// Alias for old evidence_card.php compatibility
function evQR(card, repairId, rmaId, stage) { evShowQr(card, repairId, rmaId, stage); }
function evQRClose(card) { evCloseQr(); }
</script>
