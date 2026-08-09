/* List-state — preserves a list page's scroll position and search text so a
   page-based edit (e.g. Partneri, Kupci) returns you exactly where you were,
   making it feel as seamless as an in-place popup.

   Opt in by adding `data-list-state` to the list's search <input>. State is
   saved on leave and restored ONLY when you come back from a sub-page of this
   list (its edit page) — a fresh visit from the menu starts clean. */
(function () {
  'use strict';

  var input = document.querySelector('input[data-list-state]');
  if (!input) return; // opt-in only

  var KEY = 'liststate:' + location.pathname;

  // Remember where we were when leaving (clicking Uredi, Cancel, saving, …).
  // `pagehide` is the reliable, prompt-free event for this (unlike beforeunload,
  // it never risks a "Leave site?" dialog).
  window.addEventListener('pagehide', function () {
    try {
      sessionStorage.setItem(KEY, JSON.stringify({ q: input.value, y: window.scrollY }));
    } catch (e) { /* private mode / quota — ignore */ }
  });

  // Restore only when returning from a child page of this list (…/<id>/edit).
  var childPrefix = location.origin + location.pathname.replace(/\/+$/, '') + '/';
  if ((document.referrer || '').indexOf(childPrefix) !== 0) {
    try { sessionStorage.removeItem(KEY); } catch (e) {}
    return;
  }

  var st;
  try { st = JSON.parse(sessionStorage.getItem(KEY) || 'null'); } catch (e) { st = null; }
  if (!st) return;

  if (st.q) {
    input.value = st.q;
    input.dispatchEvent(new Event('input', { bubbles: true })); // re-run the list's filter
  }
  if (typeof st.y === 'number') window.scrollTo(0, st.y);
})();
