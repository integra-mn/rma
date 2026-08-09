/* File-field — replaces the browser's native "Choose File / No file chosen"
   control (whose text is untranslatable shadow-DOM) with a styled, translated
   split control: [ Izaberi fajl │ filename ]. The native <input type="file">
   is kept (hidden) so form submission is unchanged; clicking the button opens
   the picker and the filename updates on change. Opt in with class="file-field". */
(function () {
  'use strict';

  var CHOOSE = (window.FF_CHOOSE || 'Choose file');
  var NONE   = (window.FF_NONE   || 'No file chosen');

  function enhance(input) {
    if (input._ff) return;
    input._ff = true;

    var wrap = document.createElement('div');
    wrap.className = 'ff-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    input.classList.add('ff-native'); // visually hidden, still functional

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'ff-btn';
    btn.textContent = CHOOSE;

    var name = document.createElement('span');
    name.className = 'ff-name';
    name.textContent = NONE;

    wrap.appendChild(btn);
    wrap.appendChild(name);

    btn.addEventListener('click', function () { input.click(); });
    input.addEventListener('change', function () {
      var f = input.files && input.files.length;
      name.textContent = f ? input.files[0].name : NONE;
      name.classList.toggle('empty', !f);
    });
    name.classList.add('empty');
  }

  function initAll() { document.querySelectorAll('input[type="file"].file-field').forEach(enhance); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAll);
  else initAll();
  window.FileFieldInit = initAll;
})();
