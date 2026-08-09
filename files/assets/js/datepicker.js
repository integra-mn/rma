/**
 * DatePicker — dd-mm-yyyy manual input + calendar popup
 *
 * Usage:
 *   <input type="text" class="datefield" data-name="field_name" data-value="2026-04-11">
 *   Optional: data-min-from="from_field_name" for To-field validation
 *
 * Hidden field name="field_name" stores yyyy-mm-dd, auto-created.
 */
(function() {

  let popup    = null;
  let active   = null;
  let viewMode = 'day';

  window._dpGetActive = () => active;

  class DatePicker {

    constructor(vis, hid) {
      this.vis = vis;
      this.hid = hid;
      this.cal = { y: new Date().getFullYear(), m: new Date().getMonth() };
      this._bind();
      this._render();
    }

    static init(vis, hid) {
      if (vis._dp) return vis._dp;
      const dp = new DatePicker(vis, hid);
      vis._dp = dp;

      // Wrap in relative div
      const inner = document.createElement('div');
      inner.style.cssText = 'position:relative;display:block;width:100%;';
      vis.parentNode.insertBefore(inner, vis);
      inner.appendChild(vis);
      inner.appendChild(hid);

      // Calendar icon
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.setAttribute('tabindex', '-1');
      btn.innerHTML = '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" width="13" height="13"><rect x="1" y="2" width="14" height="13" rx="2"/><path d="M1 6h14M5 1v2M11 1v2"/></svg>';
      btn.style.cssText = 'position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:2px;line-height:0;color:var(--text-muted);';
      btn.addEventListener('mousedown', e => {
        e.preventDefault();
        e.stopPropagation();
        if (active === dp && popup && popup.style.display !== 'none') {
          closePopup();
        } else {
          active = dp;
          dp._syncCal();
          openPopup(dp);
        }
        vis.focus();
      });
      inner.appendChild(btn);
      vis.style.paddingRight = '28px';

      return dp;
    }

    // ── Value ──────────────────────────────────────────────────
    getValue() { return this.hid.value; }

    setValue(iso) {
      this.hid.value = iso;
      this._render();
      this.vis.dispatchEvent(new Event('change', { bubbles: true }));
    }

    _isoToDisplay(iso) {
      if (iso && /^\d{4}-\d{2}-\d{2}$/.test(iso)) {
        const [y, m, d] = iso.split('-');
        return d + '-' + m + '-' + y;
      }
      return '';
    }

    _displayToIso(display) {
      const digits = display.replace(/\D/g, '');
      if (digits.length === 8) {
        const d = digits.slice(0,2), m = digits.slice(2,4), y = digits.slice(4,8);
        if (parseInt(d) >= 1 && parseInt(d) <= 31 &&
            parseInt(m) >= 1 && parseInt(m) <= 12 &&
            parseInt(y) >= 1900 && parseInt(y) <= 2100) {
          return y + '-' + m + '-' + d;
        }
      }
      return '';
    }

    // Build yyyy-mm-dd from 2/2/4-digit parts, or '' if not a real date.
    _toIso(d, m, y) {
      const dd = +d, mm = +m, yy = +y;
      if (dd < 1 || dd > 31 || mm < 1 || mm > 12 || yy < 1900 || yy > 2100) return '';
      // Reject impossible days (e.g. 31-02) — the Date constructor rolls them over.
      const dt = new Date(yy, mm - 1, dd);
      if (dt.getMonth() !== mm - 1 || dt.getDate() !== dd) return '';
      return y + '-' + m + '-' + d;
    }

    // Accept a full or shorthand entry and return canonical yyyy-mm-dd, or ''.
    // With separators, split day / month / year so single digits survive
    // (1/2/26 -> 01-02-2026). Without them, read a digit run: 8 = ddmmyyyy,
    // 6 = ddmmyy. A 2-digit year expands to 20yy. So 12/3/26, 12-3-26 and
    // 120326 all normalise to 2026-03-12.
    _normalize(raw) {
      raw = (raw || '').trim();
      if (!raw) return '';
      let d, m, y;
      const parts = raw.split(/[^\d]+/).filter(Boolean);
      if (/\D/.test(raw) && parts.length === 3) {
        d = parts[0]; m = parts[1]; y = parts[2];
        if (d.length > 2 || m.length > 2 || (y.length !== 2 && y.length !== 4)) return '';
        d = d.padStart(2, '0'); m = m.padStart(2, '0');
        if (y.length === 2) y = '20' + y;
        return this._toIso(d, m, y);
      }
      const digits = raw.replace(/\D/g, '');
      if (digits.length === 8)      { d = digits.slice(0,2); m = digits.slice(2,4); y = digits.slice(4,8); }
      else if (digits.length === 6) { d = digits.slice(0,2); m = digits.slice(2,4); y = '20' + digits.slice(4,6); }
      else return '';
      return this._toIso(d, m, y);
    }

    // Restore the last accepted text (used when a keystroke would make the day
    // or month go out of range).
    _revert() {
      const p = this._prev || '';
      this.vis.value = p;
      try { this.vis.setSelectionRange(p.length, p.length); } catch (e) {}
    }

    // Normalise to full dd-mm-yyyy on blur / Enter (expands a 2-digit year).
    _commit() {
      const iso = this._normalize(this.vis.value);
      if (iso) { this.hid.value = iso; this._validateMin(); this.setValue(iso); this._prev = this.vis.value; }
      else { this.hid.value = ''; }
    }

    _render() {
      const display = this._isoToDisplay(this.hid.value);
      this.vis.value = display || '';
      this.vis.placeholder = 'dd-mm-yyyy';
      this._prev = this.vis.value;
    }

    _syncCal() {
      const v = this.hid.value;
      if (v) {
        const [y, m] = v.split('-');
        this.cal.y = parseInt(y);
        this.cal.m = parseInt(m) - 1;
      }
    }

    _validateMin() {
      const minFrom = this.vis.dataset.minFrom;
      if (!minFrom || !this.hid.value) return;
      const fromHid = document.querySelector('input[type=hidden][name="' + minFrom + '"]');
      if (fromHid && fromHid.value && this.hid.value < fromHid.value) {
        this.hid.value = fromHid.value;
        this._render();
      }
    }

    // ── Events ─────────────────────────────────────────────────
    _bind() {
      const vis = this.vis;
      vis.setAttribute('autocomplete', 'off');
      vis.setAttribute('spellcheck', 'false');
      vis.setAttribute('inputmode', 'numeric');

      // Smart input reformatter with live day/month range checks.
      vis.addEventListener('input', () => {
        let digits = vis.value.replace(/\D/g, '').slice(0, 8);

        // Auto-pad: a day starting 4-9 can only be 04-09, a month starting 2-9
        // can only be 02-09 — pad to two digits so the next field starts.
        if (digits.length >= 1 && digits[0] > '3') digits = '0' + digits;
        digits = digits.slice(0, 8);
        if (digits.length >= 3 && digits[2] > '1') digits = digits.slice(0, 2) + '0' + digits.slice(2);
        digits = digits.slice(0, 8);

        // Refuse an out-of-range day (01-31) or month (01-12): keep the last
        // valid text instead of accepting the bad digit.
        if (digits.length >= 2) { const dd = +digits.slice(0, 2); if (dd < 1 || dd > 31) { this._revert(); return; } }
        if (digits.length >= 4) { const mm = +digits.slice(2, 4); if (mm < 1 || mm > 12) { this._revert(); return; } }

        let formatted;
        if (digits.length <= 2)      formatted = digits;
        else if (digits.length <= 4) formatted = digits.slice(0, 2) + '-' + digits.slice(2);
        else                         formatted = digits.slice(0, 2) + '-' + digits.slice(2, 4) + '-' + digits.slice(4);

        vis.value = formatted;
        this._prev = formatted;

        // A complete 8-digit entry becomes the ISO value straight away.
        if (digits.length === 8) {
          const iso = this._toIso(digits.slice(0, 2), digits.slice(2, 4), digits.slice(4, 8));
          this.hid.value = iso || '';
          if (iso) { this._validateMin(); this._syncCal(); if (popup && popup.style.display !== 'none') renderPopup(); }
        } else {
          this.hid.value = '';
        }
      });

      // Allow only digits and dots/slashes/dashes
      vis.addEventListener('keydown', e => {
        const k = e.key;
        if (k === 'Escape') { closePopup(); return; }
        if (k === 'Enter')  { this._commit(); return; }   // normalise before submit
        if (k === 'Tab') return;
        // Allow: digits, backspace, delete, arrows, ctrl+a/c/v/x, dots/slashes
        if (/^\d$/.test(k)) return;
        if (['Backspace','Delete','ArrowLeft','ArrowRight','Home','End','Tab'].includes(k)) return;
        if (e.ctrlKey || e.metaKey) return;
        if (k === '.' || k === '/' || k === '-') {
          e.preventDefault();
          const digits = vis.value.replace(/\D/g, '');
          const pos = vis.selectionStart;
          if (digits.length === 0) return;
          if (digits.length <= 2) {
            // Complete dd and move to mm
            const dd = digits.padStart(2, '0');
            vis.value = dd + '-';
            vis.dispatchEvent(new Event('input', {bubbles: true}));
            vis.setSelectionRange(3, 3);
          } else if (digits.length <= 4) {
            // Complete mm and move to yyyy
            const dd = digits.slice(0,2);
            const mm = digits.slice(2).padStart(2, '0');
            vis.value = dd + '-' + mm + '-';
            vis.dispatchEvent(new Event('input', {bubbles: true}));
            vis.setSelectionRange(6, 6);
          } else if (pos === 2 || pos === 5) {
            vis.setSelectionRange(pos + 1, pos + 1);
          }
          return;
        }
        // Block anything else
        e.preventDefault();
      });

      vis.addEventListener('blur', () => {
        closePopup();
        this._commit();   // expand shorthand (e.g. 12-03-26 -> 12-03-2026)
      });

      // Pasting a shorthand date normalises it in one shot.
      vis.addEventListener('paste', (e) => {
        const cd = e.clipboardData || window.clipboardData;
        const iso = this._normalize(cd ? cd.getData('text') : '');
        if (iso) {
          e.preventDefault();
          this.setValue(iso);
          this._validateMin();
          this._syncCal();
          if (popup && popup.style.display !== 'none') renderPopup();
        }
      });
    }

    selectDate(y, m, d) {
      const iso = y.toString().padStart(4,'0') + '-' +
                  (m+1).toString().padStart(2,'0') + '-' +
                  d.toString().padStart(2,'0');
      const minFrom = this.vis.dataset.minFrom;
      if (minFrom) {
        const fromHid = document.querySelector('input[type=hidden][name="' + minFrom + '"]');
        if (fromHid && fromHid.value && iso < fromHid.value) return;
      }
      this.setValue(iso);
      closePopup();
      this.vis.focus();
    }
  }

  // ── Popup ────────────────────────────────────────────────────
  function buildPopup() {
    popup = document.createElement('div');
    popup.id = 'dp-popup';
    popup.style.cssText = 'position:fixed;z-index:9999;background:var(--bg-surface,#fff);border:0.5px solid var(--border,#d3d1c7);border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.12);padding:12px;width:252px;user-select:none;font-family:inherit;font-size:13px;display:none;';
    document.body.appendChild(popup);
    document.addEventListener('mousedown', e => {
      if (!popup || popup.style.display === 'none') return;
      if (popup.contains(e.target)) return;
      if (active && active.vis.closest('div') && active.vis.closest('div').contains(e.target)) return;
      closePopup();
    });
    // The popup is position:fixed, so once open its coordinates must be
    // recomputed whenever the page (or any scroll container) scrolls or the
    // window resizes — otherwise it stays put while the field scrolls away.
    // Capture phase catches scrolling inside nested scrollable ancestors too.
    window.addEventListener('scroll', positionPopup, true);
    window.addEventListener('resize', positionPopup);
  }

  function positionPopup() {
    if (!popup || !active || popup.style.display === 'none') return;
    const r  = active.vis.getBoundingClientRect();
    const ph = popup.offsetHeight || 290;
    const top = window.innerHeight - r.bottom > ph + 8 ? r.bottom + 4 : r.top - ph - 4;
    popup.style.top  = Math.max(4, top) + 'px';
    popup.style.left = Math.min(r.left, window.innerWidth - 260) + 'px';
  }

  function openPopup(dp) {
    if (!popup) buildPopup();
    viewMode = 'day';
    popup.style.display = 'block';
    renderPopup();
    positionPopup();
  }

  function closePopup() {
    if (popup) popup.style.display = 'none';
  }

  function renderPopup() {
    if (!popup || !active) return;
    viewMode === 'year' ? renderYears() : renderDays();
  }

  const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  const DAYS   = ['Mo','Tu','We','Th','Fr','Sa','Su'];

  function renderDays() {
    const dp    = active;
    const sel   = dp.hid.value || null;
    const today = new Date(); today.setHours(0,0,0,0);
    const { y, m } = dp.cal;
    const first = new Date(y, m, 1).getDay();
    const dim   = new Date(y, m+1, 0).getDate();
    const off   = (first + 6) % 7;
    const acc   = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#1D9E75';
    const mut   = getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim() || '#888';

    let h = `<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
      <button type="button" onmousedown="event.preventDefault();event.stopPropagation();window._dpM(-1)" style="background:none;border:none;cursor:pointer;padding:4px 8px;font-size:16px;color:${mut};border-radius:6px;">‹</button>
      <button type="button" onmousedown="event.preventDefault();event.stopPropagation();window._dpTV()" style="background:none;border:none;cursor:pointer;font-size:13px;font-weight:500;padding:4px 8px;border-radius:6px;font-family:inherit;color:var(--text-primary);">${MONTHS[m]} ${y}</button>
      <button type="button" onmousedown="event.preventDefault();event.stopPropagation();window._dpM(1)" style="background:none;border:none;cursor:pointer;padding:4px 8px;font-size:16px;color:${mut};border-radius:6px;">›</button>
    </div>
    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;margin-bottom:4px;">
      ${DAYS.map(d=>`<div style="text-align:center;font-size:11px;color:${mut};padding:2px 0;font-weight:500;">${d}</div>`).join('')}
    </div>
    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;">`;

    for (let i=0;i<off;i++) h += '<div></div>';
    for (let d=1;d<=dim;d++) {
      const dt = new Date(y, m, d); dt.setHours(0,0,0,0);
      const iso = y+'-'+(m+1).toString().padStart(2,'0')+'-'+d.toString().padStart(2,'0');
      const isSel   = sel === iso;
      const isToday = dt.getTime() === today.getTime();
      const isSun   = dt.getDay() === 0;
      const isSat   = dt.getDay() === 6;
      const color   = isSel ? '#fff' : (isSun||isSat) ? '#a32d2d' : 'var(--text-primary)';
      const bg      = isSel ? acc : 'transparent';
      const border  = isToday && !isSel ? `1px solid ${acc}` : '1px solid transparent';
      h += `<button type="button" onmousedown="event.preventDefault();event.stopPropagation();window._dpD(${y},${m},${d})"
        style="background:${bg};color:${color};border:${border};border-radius:6px;padding:5px 2px;font-size:12px;cursor:pointer;font-family:inherit;font-weight:${isSel?'500':'normal'};width:100%;text-align:center;"
        onmouseover="if(!${isSel})this.style.background='var(--bg-subtle)'"
        onmouseout="if(!${isSel})this.style.background='${bg}'">${d}</button>`;
    }
    h += '</div>';
    popup.innerHTML = h;
  }

  function renderYears() {
    const dp   = active;
    const base = Math.floor(dp.cal.y / 12) * 12;
    const acc  = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#1D9E75';
    const mut  = getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim() || '#888';
    const now  = new Date().getFullYear();

    let h = `<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
      <button type="button" onmousedown="event.preventDefault();event.stopPropagation();window._dpYP(-1)" style="background:none;border:none;cursor:pointer;padding:4px 8px;font-size:16px;color:${mut};border-radius:6px;">‹</button>
      <span style="font-size:13px;font-weight:500;">${base} – ${base+11}</span>
      <button type="button" onmousedown="event.preventDefault();event.stopPropagation();window._dpYP(1)" style="background:none;border:none;cursor:pointer;padding:4px 8px;font-size:16px;color:${mut};border-radius:6px;">›</button>
    </div><div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;">`;

    for (let y=base;y<base+12;y++) {
      const cur = y === dp.cal.y;
      h += `<button type="button" onmousedown="event.preventDefault();event.stopPropagation();window._dpY(${y})"
        style="background:${cur?acc:'none'};color:${cur?'#fff':'var(--text-primary)'};border:1px solid ${(!cur&&y===now)?acc:'transparent'};border-radius:6px;padding:8px 2px;font-size:12px;cursor:pointer;font-family:inherit;text-align:center;"
        onmouseover="if(!${cur})this.style.background='var(--bg-subtle)'"
        onmouseout="if(!${cur})this.style.background='none'">${y}</button>`;
    }
    h += '</div>';
    popup.innerHTML = h;
  }

  window._dpM  = d => { if (!active) return; active.cal.m+=d; if(active.cal.m>11){active.cal.m=0;active.cal.y++;}if(active.cal.m<0){active.cal.m=11;active.cal.y--;} renderDays(); };
  window._dpTV = () => { if (!active) return; viewMode = viewMode==='day'?'year':'day'; renderPopup(); };
  window._dpD  = (y,m,d) => { if (active) active.selectDate(y,m,d); };
  window._dpY  = y => { if (!active) return; active.cal.y=y; viewMode='day'; renderDays(); };
  window._dpYP = d => { if (!active) return; active.cal.y+=d*12; renderYears(); };

  function initAll() {
    document.querySelectorAll('input.datefield').forEach(vis => {
      if (vis._dp) return;
      const name = vis.dataset.name;
      let hid = Array.from(vis.parentNode.children).find(el => el.type==='hidden' && el.name===name);
      if (!hid) {
        hid = document.createElement('input');
        hid.type = 'hidden';
        hid.name = name;
        vis.parentNode.insertBefore(hid, vis.nextSibling);
      }
      DatePicker.init(vis, hid);
      const preset = vis.dataset.value || hid.value;
      if (preset && /^\d{4}-\d{2}-\d{2}$/.test(preset)) {
        vis._dp.hid.value = preset;
        vis._dp._render();
      }
    });
  }

  document.addEventListener('DOMContentLoaded', initAll);
  window.DatePicker = DatePicker;
  window.DatePickerInitAll = initAll;

})();
