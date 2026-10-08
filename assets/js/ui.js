// Tiny UI toolkit: DOM builder (XSS-safe: text only), icons, toast, bottom sheet, formatting.
const LANG = document.documentElement.lang || 'ku';
const tr = (k, vars) => {
  let s = (window.I18N && window.I18N[k]) || k;
  if (vars) for (const [a, b] of Object.entries(vars)) s = s.replace(`{${a}}`, b);
  return s;
};
const nm = o => (o && (o['name_' + LANG] || o.name_ku || o.name_en)) || '';

function h(tag, props, ...kids) {
  const el = document.createElement(tag);
  for (const [k, v] of Object.entries(props || {})) {
    if (v === undefined || v === null || v === false) continue;
    if (k === 'class') el.className = v;
    else if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
    else if (k === 'style' && typeof v === 'object') Object.assign(el.style, v);
    else if (k in el && k !== 'list' && k !== 'form') { try { el[k] = v; } catch { el.setAttribute(k, v); } }
    else el.setAttribute(k, v === true ? '' : v);
  }
  const add = k => {
    if (Array.isArray(k)) k.forEach(add);
    else if (k instanceof Node) el.appendChild(k);
    else if (k !== null && k !== undefined && k !== false) el.appendChild(document.createTextNode(String(k)));
  };
  kids.forEach(add);
  return el;
}

const ICONS = {
  home: '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
  store: '<path d="M3 9l1.5-5h15L21 9"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/><path d="M5 12v8h14v-8"/>',
  plus: '<path d="M12 5v14M5 12h14"/>', list: '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
  user: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
  bag: '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
  back: '<path d="M15 18l-6-6 6-6"/>', pin: '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
  filter: '<path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/>', search: '<circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>',
  camera: '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
  check: '<path d="M20 6L9 17l-5-5"/>', x: '<path d="M18 6L6 18M6 6l12 12"/>',
  logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
  nav: '<path d="M3 11l19-9-9 19-2-8-8-2z"/>',
  leaf: '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>',
  farm: '<path d="M2 20h20"/><path d="M4 20V10l8-6 8 6v10"/><path d="M9 20v-6h6v6"/>',
  globe: '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/>',
  download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
};
function icon(name, cls = '') {
  const s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  s.setAttribute('viewBox', '0 0 24 24'); s.setAttribute('class', 'ic ' + cls); s.setAttribute('aria-hidden', 'true');
  s.innerHTML = ICONS[name] || ''; // static trusted markup only
  return s;
}

const NUM = (() => { try { return new Intl.NumberFormat(LANG === 'en' ? 'en' : 'ar-IQ', { maximumFractionDigits: 2 }); } catch { return new Intl.NumberFormat('en'); } })();
const fmtNum = n => NUM.format(Number(n));
const fmtMoney = n => fmtNum(n) + ' ' + tr('cur.iqd');
function fmtPrice(s) {
  if (s.price_type === 'free') return tr('price.free');
  if (s.price_per_unit == null || s.price_per_unit === '') return tr('price.negotiable');
  return fmtMoney(s.price_per_unit) + ' / ' + (s.unit_name_ku ? unitName(s) : s.unit_code);
}
const unitName = s => (LANG === 'en' ? s.unit_code : (s.unit_name_ku || s.unit_code));

function toast(msg, type = '') {
  let wrap = document.querySelector('.toast-wrap');
  if (!wrap) { wrap = h('div', { class: 'toast-wrap', role: 'status', 'aria-live': 'polite' }); document.body.appendChild(wrap); }
  const t = h('div', { class: 'toast ' + type }, msg);
  wrap.appendChild(t);
  setTimeout(() => t.remove(), 3200);
}

function sheet(title, content) {
  const close = () => { bg.remove(); document.removeEventListener('keydown', onKey); };
  const onKey = e => { if (e.key === 'Escape') close(); };
  const bg = h('div', { class: 'sheet-bg', onclick: e => { if (e.target === bg) close(); } },
    h('div', { class: 'sheet', role: 'dialog', 'aria-modal': 'true', 'aria-label': title }, h('div', { class: 'grab' }), h('h2', {}, title), content));
  document.addEventListener('keydown', onKey);
  document.body.appendChild(bg);
  return { close };
}
const confirmSheet = (title, text, okLabel) => new Promise(res => {
  const s = sheet(title, h('div', {}, h('p', { class: 'muted' }, text),
    h('div', { class: 'btn-row' },
      h('button', { class: 'btn ghost', onclick: () => { s.close(); res(false); } }, tr('common.cancel')),
      h('button', { class: 'btn danger', onclick: () => { s.close(); res(true); } }, okLabel || tr('common.ok')))));
});

const spinner = () => h('div', { class: 'spinner', role: 'progressbar' });
const empty = (ic, text) => h('div', { class: 'empty' }, icon(ic), h('p', {}, text));
function errMsg(e) {
  const first = Object.values(e.errors || {}).flat()[0];
  return first || e.message || tr('err.generic');
}
function showFieldErrors(form, e) {
  form.querySelectorAll('.field-error').forEach(n => n.remove());
  form.querySelectorAll('.invalid').forEach(n => n.classList.remove('invalid'));
  let any = false;
  for (const [k, msgs] of Object.entries(e.errors || {})) {
    const input = form.querySelector(`[name="${k}"]`);
    if (!input) continue;
    any = true; input.classList.add('invalid');
    input.closest('.field')?.appendChild(h('div', { class: 'field-error' }, msgs[0]));
  }
  if (!any || !Object.keys(e.errors || {}).length) toast(errMsg(e), 'error');
}
function lsGet(k) { try { return JSON.parse(localStorage.getItem(k)); } catch { return null; } }
function lsSet(k, v) { try { v === null ? localStorage.removeItem(k) : localStorage.setItem(k, JSON.stringify(v)); } catch { /* storage unavailable */ } }

/** Downscale a photo before upload — phone cameras produce multi‑MB files, the server limit is 5 MB. */
async function shrinkImage(file, max = 1600) {
  if (!/^image\/(jpeg|png|webp)$/.test(file.type)) return file;
  try {
    const bmp = await createImageBitmap(file);
    const k = Math.min(1, max / Math.max(bmp.width, bmp.height));
    const c = document.createElement('canvas'); c.width = Math.round(bmp.width * k); c.height = Math.round(bmp.height * k);
    c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
    const blob = await new Promise(r => c.toBlob(r, 'image/jpeg', 0.85));
    return blob ? new File([blob], 'photo.jpg', { type: 'image/jpeg' }) : file;
  } catch { return file; }
}
function getPosition() {
  return new Promise((res, rej) => {
    if (!navigator.geolocation) return rej(new Error('no geolocation'));
    navigator.geolocation.getCurrentPosition(p => res({ lat: +p.coords.latitude.toFixed(6), lng: +p.coords.longitude.toFixed(6) }), rej, { enableHighAccuracy: true, timeout: 12000 });
  });
}
