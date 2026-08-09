/* Search-select — turns native <select> elements into searchable dropdowns in
   the filter-dropdown (Odmori) look. Applied to EVERY select on the page (opt
   out with class="no-enhance"). The native <select> is hidden but kept as the
   value-holder, so form submission and existing onchange logic keep working.

   - Rebuilds when the native options change (MutationObserver) — dependent
     lists like Model stay in sync.
   - Reflects programmatic value changes (sel.value = x) via a value-setter
     shim, so edit modals that populate fields update the label automatically.
   - Exposes _customBtn / _customRebuild for backward compatibility with code
     written against the previous custom-select component. */
(function () {
  'use strict';

  var SEARCH_PH  = (window.SS_SEARCH || 'Search');
  var NO_RESULTS = (window.SS_NO_RESULTS || 'No results');
  var SEARCH_MIN = 7; // show the search box only when there are more options than this

  function enhance(sel) {
    if (sel._ss) return;
    if (sel.multiple || sel.classList.contains('no-enhance')) return;
    sel._ss = true;

    var wrap = document.createElement('div');
    wrap.className = 'ss-wrap';
    // Preserve an explicit width (filter toolbars use e.g. width:160px);
    // form fields have none and should fill their container.
    wrap.style.width = sel.style.width || '100%';
    sel.parentNode.insertBefore(wrap, sel);
    wrap.appendChild(sel);
    sel.style.display = 'none';

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'ss-btn';
    btn.innerHTML = '<span class="ss-label"></span>'
      + '<svg class="ss-chev" viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 1l4 4 4-4"/></svg>';
    wrap.appendChild(btn);
    var label = btn.querySelector('.ss-label');

    var menu = document.createElement('div');
    menu.className = 'ss-menu';
    var search = document.createElement('input');
    search.type = 'text';
    search.className = 'ss-search';
    search.setAttribute('autocomplete', 'off');
    search.placeholder = SEARCH_PH;
    var list = document.createElement('div');
    list.className = 'ss-list';
    menu.appendChild(search);
    menu.appendChild(list);
    wrap.appendChild(menu);

    function syncLabel() {
      var o = sel.options[sel.selectedIndex];
      label.textContent = o ? o.text : '';
      label.classList.toggle('placeholder', !(o && o.value !== ''));
    }

    function markActive() {
      list.querySelectorAll('.ss-item').forEach(function (it) {
        it.classList.toggle('active', it.dataset.value === sel.value);
      });
    }

    function buildList() {
      list.innerHTML = '';
      // Include every option — an empty value can be a real "All …" filter
      // choice, not just a prompt, so it must stay selectable.
      Array.prototype.forEach.call(sel.options, function (opt) {
        var it = document.createElement('button');
        it.type = 'button';
        it.className = 'ss-item' + (opt.selected ? ' active' : '');
        it.textContent = opt.text;
        it.dataset.value = opt.value;
        it.addEventListener('click', function () {
          sel.value = opt.value;       // triggers the value shim → sync
          close();
          sel.dispatchEvent(new Event('change', { bubbles: true }));
        });
        list.appendChild(it);
      });
      search.style.display = sel.options.length > SEARCH_MIN ? '' : 'none';
      syncLabel();
    }

    function visibleItems() {
      return Array.prototype.filter.call(list.querySelectorAll('.ss-item'), function (it) {
        return it.style.display !== 'none';
      });
    }

    function filter() {
      var q = search.value.toLowerCase().trim();
      var any = false;
      list.querySelectorAll('.ss-item').forEach(function (it) {
        var m = !q || it.textContent.toLowerCase().indexOf(q) !== -1;
        it.style.display = m ? '' : 'none';
        it.classList.remove('highlight');
        if (m) any = true;
      });
      var empty = menu.querySelector('.ss-empty');
      if (!any) {
        if (!empty) { empty = document.createElement('div'); empty.className = 'ss-empty'; empty.textContent = NO_RESULTS; menu.appendChild(empty); }
      } else if (empty) { empty.remove(); }
    }

    function highlightMove(dir) {
      var vis = visibleItems();
      if (!vis.length) return;
      var i = vis.findIndex(function (it) { return it.classList.contains('highlight'); });
      if (i !== -1) vis[i].classList.remove('highlight');
      i = (i + dir + vis.length) % vis.length;
      vis[i].classList.add('highlight');
      vis[i].scrollIntoView({ block: 'nearest' });
    }

    function open() {
      closeAll();
      var r = wrap.getBoundingClientRect();
      var need = Math.min(300, list.scrollHeight + 60);
      menu.classList.toggle('drop-up', window.innerHeight - r.bottom < need && r.top > need);
      wrap.classList.add('open');
      search.value = '';
      filter();
      markActive();
      if (search.style.display !== 'none') search.focus();
      var act = list.querySelector('.ss-item.active');
      if (act) act.scrollIntoView({ block: 'center' });
    }
    function close() { wrap.classList.remove('open'); }

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      wrap.classList.contains('open') ? close() : open();
    });
    menu.addEventListener('click', function (e) { e.stopPropagation(); });
    search.addEventListener('input', filter);
    search.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); highlightMove(1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); highlightMove(-1); }
      else if (e.key === 'Enter') {
        e.preventDefault();
        var hi = list.querySelector('.ss-item.highlight') || visibleItems()[0];
        if (hi) hi.click();
      } else if (e.key === 'Escape') { close(); btn.focus(); }
    });

    // Reflect programmatic value changes (edit modals do `sel.value = ...`).
    try {
      var desc = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
      if (desc && desc.set) {
        Object.defineProperty(sel, 'value', {
          configurable: true,
          get: function () { return desc.get.call(this); },
          set: function (v) { desc.set.call(this, v); syncLabel(); markActive(); }
        });
      }
    } catch (e) { /* fall back to change-event syncing below */ }

    new MutationObserver(buildList).observe(sel, { childList: true });
    sel.addEventListener('change', function () { syncLabel(); markActive(); });

    // Back-compat with the previous custom-select component.
    sel._customBtn = btn;
    sel._customRebuild = buildList;

    buildList();
  }

  function closeAll() {
    document.querySelectorAll('.ss-wrap.open').forEach(function (w) { w.classList.remove('open'); });
  }
  document.addEventListener('click', closeAll);

  function initAll() { document.querySelectorAll('select').forEach(enhance); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAll);
  else initAll();
  window.SearchSelectInit = initAll;
})();
