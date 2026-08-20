// Flash-free list search / filter / pagination.
//
// Any <form data-live-list> on a list page drives an in-place update: typing in
// #list-search (debounced), changing a <select>, submitting, or clicking a
// pagination link fetches just the results fragment (?ajax=1) and swaps
// #list-results — no full page reload, so focus and scroll are preserved.
// The address bar is kept in sync (via replaceState) so refresh/bookmark work,
// and out-of-date responses are ignored so fast typing can't show stale rows.
(function () {
  var form = document.querySelector('form[data-live-list]');
  if (!form) return;

  var results = document.getElementById('list-results');
  var search  = document.getElementById('list-search');
  var base    = form.getAttribute('action');
  var timer, reqId = 0;

  function buildParams(page) {
    var p = new URLSearchParams();
    // Hidden fields first: a list that lives inside a tabbed page carries which
    // tab it is in, and dropping it sent the request to a different screen.
    form.querySelectorAll('input[type=hidden][name]').forEach(function (h) {
      if (h.value) p.set(h.name, h.value);
    });
    if (search && search.value) p.set('q', search.value);
    form.querySelectorAll('select[name]').forEach(function (sel) {
      if (sel.value) p.set(sel.name, sel.value);
    });
    if (page && page > 1) p.set('page', page);
    return p;
  }

  function run(page) {
    if (!results) return;
    var p = buildParams(page);
    // Keep the URL shareable (no ajax flag in the address bar).
    history.replaceState(null, '', base + (p.toString() ? '?' + p.toString() : ''));
    p.set('ajax', '1');
    var mine = ++reqId;
    fetch(base + '?' + p.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        if (mine !== reqId) return;   // a newer request already answered
        results.innerHTML = html;
      })
      .catch(function () { /* leave current rows on network error */ });
  }

  if (search) {
    search.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(function () { run(1); }, 250);
    });
  }
  form.querySelectorAll('select[name]').forEach(function (sel) {
    sel.addEventListener('change', function () { run(1); });
  });
  form.addEventListener('submit', function (e) { e.preventDefault(); run(1); });

  // Pagination links live inside the swapped fragment — delegate.
  if (results) {
    results.addEventListener('click', function (e) {
      var a = e.target.closest('a[data-page]');
      if (!a) return;
      e.preventDefault();
      run(parseInt(a.getAttribute('data-page'), 10) || 1);
    });
  }
})();
