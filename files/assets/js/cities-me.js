/**
 * Montenegrin city <-> postal-code auto-link.
 *
 * On blur of any input[name="city"]/[walkin_city]/... pair with a matching
 * zip_code/zip input:
 *   - fills the partner field when it is empty, and
 *   - normalises the city name to its canonical plain-ASCII form
 *     (e.g. "Nikšić" / "niksic" / "NIKSIC" -> "Niksic").
 *
 * Staff can still type anything (foreign cities, nicknames) and it will be
 * left alone — we only rewrite when the typed value matches a known entry.
 *
 * Source: Pošta Crne Gore — postal codes by general post office.
 */
(function () {
  // Single source of truth: [folded key, canonical ASCII name, zip code].
  var CITIES = [
    ['podgorica',    'Podgorica',    '81000'],
    ['tuzi',         'Tuzi',         '81206'],
    ['kolasin',      'Kolasin',      '81210'],
    ['cetinje',      'Cetinje',      '81250'],
    ['niksic',       'Niksic',       '81400'],
    ['danilovgrad',  'Danilovgrad',  '81410'],
    ['pluzine',      'Pluzine',      '81435'],
    ['savnik',       'Savnik',       '81450'],
    ['bijelo polje', 'Bijelo Polje', '84000'],
    ['mojkovac',     'Mojkovac',     '84205'],
    ['pljevlja',     'Pljevlja',     '84210'],
    ['zabljak',      'Zabljak',      '84220'],
    ['berane',       'Berane',       '84300'],
    ['rozaje',       'Rozaje',       '84310'],
    ['petnjica',     'Petnjica',     '84316'],
    ['andrijevica',  'Andrijevica',  '84320'],
    ['plav',         'Plav',         '84325'],
    ['gusinje',      'Gusinje',      '84326'],
    ['bar',          'Bar',          '85000'],
    ['budva',        'Budva',        '85310'],
    ['tivat',        'Tivat',        '85320'],
    ['kotor',        'Kotor',        '85330'],
    ['herceg novi',  'Herceg Novi',  '85340'],
    ['ulcinj',       'Ulcinj',       '85360']
  ];

  var FOLDED_TO_ASCII = {};
  var FOLDED_TO_ZIP   = {};
  var ZIP_TO_ASCII    = {};
  CITIES.forEach(function (c) {
    FOLDED_TO_ASCII[c[0]] = c[1];
    FOLDED_TO_ZIP[c[0]]   = c[2];
    ZIP_TO_ASCII[c[2]]    = c[1];
  });

  function fold(s) {
    return String(s || '')
      .toLowerCase()
      .trim()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/\s+/g, ' ');
  }

  // Classify an input by its name: returns 'city', 'zip', or null.
  function kindOf(name) {
    if (!name) return null;
    if (/(^|_)city$/i.test(name)) return 'city';
    if (/(^|_)zip(_code)?$/i.test(name)) return 'zip';
    return null;
  }

  // Find the paired input in the same <form>, honouring shared prefix.
  function pair(input) {
    var form = input.form || input.closest('form') || document;
    var kind = kindOf(input.name);
    if (!kind) return null;
    var prefix = input.name
      .replace(/(^|_)(zip_code|zip|city)$/i, '')
      .replace(/_$/, '');
    var names = kind === 'city'
      ? [prefix ? prefix + '_zip_code' : 'zip_code',
         prefix ? prefix + '_zip'      : 'zip']
      : [prefix ? prefix + '_city'     : 'city'];
    for (var i = 0; i < names.length; i++) {
      var el = form.querySelector('input[name="' + names[i] + '"]');
      if (el) return el;
    }
    return null;
  }

  document.addEventListener('blur', function (e) {
    var el = e.target;
    if (!(el instanceof HTMLInputElement)) return;
    var kind = kindOf(el.name);
    if (!kind) return;

    var other = pair(el);
    if (!other) return;

    if (kind === 'city') {
      var folded = fold(el.value);
      if (!FOLDED_TO_ZIP[folded]) return;    // unknown city — leave alone
      // Canonicalise the city spelling (only overwrite our own folding;
      // a pre-typed different variant still gets normalised).
      var canonical = FOLDED_TO_ASCII[folded];
      if (el.value !== canonical) el.value = canonical;
      if (other.value.trim() === '') other.value = FOLDED_TO_ZIP[folded];
    } else {
      var zip = el.value.trim();
      if (!ZIP_TO_ASCII[zip]) return;        // unknown zip — leave alone
      if (other.value.trim() === '') other.value = ZIP_TO_ASCII[zip];
    }
  }, true); // capture — 'blur' doesn't bubble
})();
