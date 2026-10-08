// Screens of the web app. Each view is `async (ctx) => Node` where ctx = { params, setTitle, setBack }.
const ME = window.ME;
const isSupplier = ['farmer', 'collection_center', 'admin'].includes(ME.role);
const isBuyer = ['buyer', 'admin'].includes(ME.role);
let CATALOG = null;
async function catalog() {
  if (!CATALOG) {
    const [units, wasteTypes] = await Promise.all([Api.get('/units'), Api.get('/waste-types')]);
    CATALOG = { units, wasteTypes };
  }
  return CATALOG;
}
const wasteName = id => nm((CATALOG?.wasteTypes || []).find(w => +w.id === +id)) || '';
const statusBadge = s => h('span', { class: 'badge ' + s }, tr('status.' + s));
const qualityLabel = q => q === 'ungraded' || q === 'any' ? tr('quality.' + q) : tr('quality.grade', { g: q });

function listingCard(s) {
  const thumb = h('div', { class: 'thumb', style: s.primary_image ? { backgroundImage: `url("${U(s.primary_image)}")` } : {} }, s.primary_image ? null : icon('leaf'));
  return h('a', { class: 'card listing', href: '#/supply/' + s.id },
    thumb,
    h('div', { class: 'body' },
      h('div', { class: 'title' }, nm({ name_ku: s.waste_name_ku, name_ar: s.waste_name_ar, name_en: s.waste_name_en })),
      h('div', { class: 'price' }, fmtPrice(s)),
      h('div', { class: 'meta' },
        h('span', {}, fmtNum(s.available_quantity) + ' ' + unitName(s)),
        h('span', {}, qualityLabel(s.quality_grade)),
        s.distance_km != null ? h('span', {}, icon('pin'), tr('dist.km', { n: fmtNum(s.distance_km) })) : (s.address ? h('span', {}, icon('pin'), s.address.slice(0, 24)) : null),
        s.status !== 'active' ? statusBadge(s.status) : null)));
}

function demandCard(d, mine) {
  return h('a', { class: 'card', href: mine ? '#/demand/' + d.id : '#/demand/' + d.id },
    h('div', { class: 'listing' }, h('div', { class: 'body' },
      h('div', { class: 'title' }, nm({ name_ku: d.waste_name_ku, name_ar: d.waste_name_ar, name_en: d.waste_name_en })),
      h('div', { class: 'price' }, tr('demand.need', { q: fmtNum(d.remaining_quantity), u: d.unit_code })),
      h('div', { class: 'meta' },
        d.max_price_per_unit ? h('span', {}, tr('demand.maxprice', { p: fmtMoney(d.max_price_per_unit) })) : null,
        h('span', {}, qualityLabel(d.minimum_quality)),
        d.organization_name ? h('span', {}, d.organization_name) : null,
        d.status !== 'active' ? statusBadge(d.status) : null))));
}

/* ------------------------------------------------------------------ Home */
async function HomeView(ctx) {
  ctx.setTitle(tr('app.name'));
  const root = h('div');
  root.append(h('div', { class: 'hero' }, h('h2', {}, tr('dash.welcome') + '، ' + ME.full_name), h('p', {}, tr('role.' + ME.role))));

  const actions = h('div', { class: 'actions' });
  if (isSupplier) actions.append(h('a', { class: 'action', href: '#/add' }, icon('plus'), tr('nav.add')), h('a', { class: 'action', href: '#/my' }, icon('list'), tr('nav.my')));
  if (isBuyer) actions.append(h('a', { class: 'action', href: '#/demand/new' }, icon('plus'), tr('demand.new')), h('a', { class: 'action', href: '#/demands' }, icon('bag'), tr('nav.demands')));
  actions.append(h('a', { class: 'action', href: '#/market' }, icon('store'), tr('nav.market')));
  if (ME.role === 'farmer') actions.append(h('a', { class: 'action', href: '#/farms' }, icon('farm'), tr('farms.title')));
  root.append(actions);

  const stats = h('div', { class: 'stats' });
  root.append(stats);
  root.append(h('div', { class: 'section-title' }, h('h2', {}, tr('home.latest')), h('a', { href: '#/market' }, tr('common.all'))));
  const list = h('div', {}, spinner());
  root.append(list);

  (async () => {
    try {
      const jobs = [Api.get('/supplies?per_page=5')];
      if (isSupplier) jobs.push(Api.get('/supplies/mine?status=active&per_page=1'), Api.get('/supplies/mine?status=draft&per_page=1'));
      if (isBuyer) jobs.push(Api.get('/demands/mine?status=active&per_page=1'));
      const [latest, a, b] = await Promise.all(jobs);
      await catalog();
      if (isSupplier) stats.append(h('div', { class: 'stat' }, h('b', {}, fmtNum(a.total)), tr('stat.active')), h('div', { class: 'stat' }, h('b', {}, fmtNum(b.total)), tr('stat.drafts')));
      else if (isBuyer) stats.append(h('div', { class: 'stat' }, h('b', {}, fmtNum(a.total)), tr('stat.demands')), h('div', { class: 'stat' }, h('b', {}, fmtNum(latest.total)), tr('stat.market')));
      else stats.remove();
      list.replaceChildren(...(latest.items.length ? latest.items.map(listingCard) : [empty('leaf', tr('market.empty'))]));
    } catch (e) { list.replaceChildren(empty('x', errMsg(e))); }
  })();
  return root;
}

/* ---------------------------------------------------------------- Market */
async function MarketView(ctx) {
  ctx.setTitle(tr('nav.market'));
  const { wasteTypes } = await catalog();
  const state = Object.assign({ q: '', waste_type_id: '', quality: '', max_price: '', sort: 'newest', lat: '', lng: '', radius_km: '' }, lsGet('market.filters') || {});
  let page = 1;
  const root = h('div');
  const list = h('div'); const more = h('div', { class: 'center' });

  const search = h('input', { class: 'input', type: 'search', placeholder: tr('market.search'), value: state.q, enterKeyHint: 'search', name: 'q',
    oninput: debounce(() => { state.q = search.value.trim(); reload(); }, 350) });
  const chipsBox = h('div', { class: 'chips scroll' });
  const drawChips = () => {
    chipsBox.replaceChildren(
      h('button', { class: 'chip' + (state.waste_type_id ? '' : ' on'), onclick: () => { state.waste_type_id = ''; drawChips(); reload(); } }, tr('common.all')),
      ...wasteTypes.map(w => h('button', { class: 'chip' + (+state.waste_type_id === +w.id ? ' on' : ''), onclick: () => { state.waste_type_id = w.id; drawChips(); reload(); } }, nm(w))));
  };
  const filterBtn = h('button', { class: 'btn ghost', 'aria-label': tr('market.filters'), onclick: openFilters }, icon('filter'));
  root.append(h('div', { style: { display: 'flex', gap: '.5rem', marginBottom: '.75rem' } }, search, filterBtn), chipsBox, h('div', { style: { height: '.75rem' } }), list, more);
  drawChips();

  function params(p) {
    const q = new URLSearchParams({ per_page: 10, page: p });
    for (const k of ['q', 'waste_type_id', 'quality', 'max_price', 'lat', 'lng', 'radius_km']) if (state[k]) q.set(k, state[k]);
    q.set('sort', state.sort === 'nearest' && !state.lat ? 'newest' : state.sort);
    return q.toString();
  }
  async function load(reset) {
    if (reset) { page = 1; list.replaceChildren(spinner()); more.replaceChildren(); }
    try {
      const r = await Api.get('/supplies?' + params(page));
      if (reset) list.replaceChildren();
      if (!r.items.length && page === 1) list.append(empty('search', tr('market.empty')));
      r.items.forEach(s => list.append(listingCard(s)));
      more.replaceChildren(page * r.per_page < r.total ? h('button', { class: 'btn secondary', onclick: () => { page++; load(false); } }, tr('common.more')) : '');
    } catch (e) { list.replaceChildren(empty('x', errMsg(e))); }
  }
  const reload = () => { lsSet('market.filters', state); load(true); };

  function openFilters() {
    const f = { ...state };
    const quality = h('div', { class: 'chips' });
    const drawQ = () => quality.replaceChildren(...['', 'A', 'B', 'C'].map(q => h('button', { type: 'button', class: 'chip' + (f.quality === q ? ' on' : ''), onclick: () => { f.quality = q; drawQ(); } }, q ? tr('quality.grade', { g: q }) : tr('common.all'))));
    drawQ();
    const sort = h('select', { class: 'input', onchange: () => { f.sort = sort.value; } },
      ...['newest', 'price_asc', 'quantity_desc', 'nearest'].map(v => h('option', { value: v, selected: f.sort === v }, tr('sort.' + v))));
    const price = h('input', { class: 'input ltr', type: 'number', inputMode: 'numeric', min: 0, value: f.max_price, oninput: () => { f.max_price = price.value; } });
    const nearBtn = h('button', { type: 'button', class: 'btn secondary block', onclick: async () => {
      try { const p = await getPosition(); f.lat = p.lat; f.lng = p.lng; f.radius_km = f.radius_km || 50; if (f.sort === 'newest') { f.sort = 'nearest'; sort.value = 'nearest'; } nearBtn.textContent = tr('market.near_on'); }
      catch { toast(tr('geo.denied'), 'error'); }
    } }, icon('nav'), f.lat ? tr('market.near_on') : tr('market.near'));
    const radius = h('select', { class: 'input', onchange: () => { f.radius_km = radius.value; } }, ...[10, 25, 50, 100, 200].map(r => h('option', { value: r, selected: +f.radius_km === r }, tr('dist.km', { n: fmtNum(r) }))));
    const s = sheet(tr('market.filters'), h('div', {},
      h('div', { class: 'field' }, h('label', {}, tr('field.quality')), quality),
      h('div', { class: 'field' }, h('label', {}, tr('market.maxprice')), price),
      h('div', { class: 'field' }, h('label', {}, tr('market.sort')), sort),
      h('div', { class: 'field' }, nearBtn, h('div', { style: { height: '.5rem' } }), radius),
      h('div', { class: 'btn-row' },
        h('button', { class: 'btn ghost', onclick: () => { Object.assign(state, { quality: '', max_price: '', sort: 'newest', lat: '', lng: '', radius_km: '' }); s.close(); reload(); } }, tr('common.reset')),
        h('button', { class: 'btn', onclick: () => { Object.assign(state, f); s.close(); reload(); } }, tr('common.apply')))));
  }
  load(true);
  return root;
}

/* ---------------------------------------------------------------- Detail */
async function SupplyView(ctx) {
  ctx.setBack('#/market');
  const [s] = await Promise.all([Api.get('/supplies/' + ctx.params[0]), catalog()]);
  ctx.setTitle(nm({ name_ku: s.waste_name_ku, name_ar: s.waste_name_ar, name_en: s.waste_name_en }));
  const owner = +s.supplier_user_id === +ME.id || ME.role === 'admin';
  const root = h('div');
  root.append(h('div', { class: 'card' },
    h('div', { class: 'gallery' }, ...(s.images.length ? s.images.map(i => h('img', { src: U(i.file_path), alt: '', loading: 'lazy' })) : [h('div', { class: 'ph' }, icon('leaf'))])),
    h('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'start', gap: '.5rem' } },
      h('h2', {}, nm({ name_ku: s.waste_name_ku, name_ar: s.waste_name_ar, name_en: s.waste_name_en })), statusBadge(s.status)),
    h('div', { class: 'price', style: { fontSize: '1.3rem' } }, fmtPrice(s)),
    h('div', { class: 'kv' },
      h('div', {}, h('small', {}, tr('detail.available')), fmtNum(s.available_quantity) + ' ' + unitName(s)),
      h('div', {}, h('small', {}, tr('field.quality')), qualityLabel(s.quality_grade)),
      h('div', {}, h('small', {}, tr('detail.from')), s.available_from),
      h('div', {}, h('small', {}, tr('detail.until')), s.available_until || '—'),
      h('div', {}, h('small', {}, tr('detail.supplier')), s.supplier_name),
      s.moisture_percent != null ? h('div', {}, h('small', {}, tr('detail.moisture')), fmtNum(s.moisture_percent) + '%') : null),
    s.address ? h('p', {}, icon('pin'), ' ', s.address) : null,
    s.latitude != null ? h('a', { class: 'btn secondary block', target: '_blank', rel: 'noopener', href: `https://www.google.com/maps?q=${s.latitude},${s.longitude}` }, icon('pin'), tr('detail.map')) : null,
    s.description ? h('p', { style: { marginTop: '1rem' } }, s.description) : null));

  if (owner && ['draft', 'active'].includes(s.status)) {
    const box = h('div', { class: 'btn-row' });
    if (s.status === 'draft') box.append(h('button', { class: 'btn', onclick: async e => { e.target.disabled = true; try { await Api.post(`/supplies/${s.id}/publish`); toast(tr('supply.published'), 'ok'); render(); } catch (er) { toast(errMsg(er), 'error'); e.target.disabled = false; } } }, tr('supply.publish')));
    box.append(h('button', { class: 'btn danger', onclick: async () => { if (await confirmSheet(tr('supply.cancel'), tr('supply.cancel_q'), tr('supply.cancel'))) { try { await Api.del('/supplies/' + s.id); toast(tr('supply.cancelled'), 'ok'); location.hash = '#/my'; } catch (er) { toast(errMsg(er), 'error'); } } } }, tr('supply.cancel')));
    root.append(box);
  } else if (!owner) root.append(h('div', { class: 'card flat center muted' }, tr('detail.offers_soon')));
  return root;
}

/* ------------------------------------------------------------ My supplies */
async function MyView(ctx) {
  ctx.setTitle(tr('nav.my'));
  await catalog();
  const root = h('div');
  const tabs = h('div', { class: 'tabs' }); const list = h('div');
  let cur = lsGet('my.tab') || '';
  const draw = () => tabs.replaceChildren(...['', 'draft', 'active', 'sold', 'cancelled'].map(t => h('button', { class: 'chip' + (cur === t ? ' on' : ''), onclick: () => { cur = t; lsSet('my.tab', t); draw(); load(); } }, t ? tr('status.' + t) : tr('common.all'))));
  async function load() {
    list.replaceChildren(spinner());
    try { const r = await Api.get('/supplies/mine?per_page=50' + (cur ? '&status=' + cur : '')); list.replaceChildren(...(r.items.length ? r.items.map(listingCard) : [empty('leaf', tr('my.empty'))])); }
    catch (e) { list.replaceChildren(empty('x', errMsg(e))); }
  }
  draw(); load();
  root.append(tabs, list, h('a', { class: 'btn block', href: '#/add' }, icon('plus'), tr('nav.add')));
  return root;
}

/* --------------------------------------------------------- Add‑supply wizard */
async function AddView(ctx) {
  ctx.setTitle(tr('nav.add')); ctx.setBack('#/');
  const { units, wasteTypes } = await catalog();
  const farms = ME.role === 'farmer' ? await Api.get('/farms').catch(() => []) : [];
  const today = new Date().toISOString().slice(0, 10);
  const D = Object.assign({ step: 0, waste_type_id: '', unit_id: units.find(u => u.code === 'ton')?.id || units[0].id, quantity: '', quality_grade: 'ungraded',
    address: '', latitude: '', longitude: '', farm_id: '', available_from: today, available_until: '', price_type: 'negotiable', price_per_unit: '', description: '' }, lsGet('draft.supply') || {});
  const photos = []; // File[] — cannot persist
  const STEPS = 7;
  const root = h('div');
  const save = () => lsSet('draft.supply', D);

  function draw() {
    D.step = Math.min(D.step, STEPS - 1); save();
    root.replaceChildren(h('div', { class: 'progress', role: 'progressbar', 'aria-valuenow': D.step + 1, 'aria-valuemax': STEPS }, h('i', { style: { width: ((D.step + 1) / STEPS * 100) + '%' } })));
    const body = h('div'); root.append(body);
    const next = h('button', { class: 'btn block', onclick: go }, D.step === STEPS - 1 ? tr('wizard.publish') : tr('common.next'));
    let valid = () => '';
    const q = t => h('div', { class: 'wizard-q' }, t);

    if (D.step === 0) {
      body.append(q(tr('wizard.q_material')), h('div', { class: 'tiles' }, ...wasteTypes.map(w => h('button', { class: 'tile' + (+D.waste_type_id === +w.id ? ' on' : ''), onclick: () => { D.waste_type_id = w.id; if (w.default_unit_id) D.unit_id = w.default_unit_id; draw(); } }, icon('leaf'), nm(w)))));
      valid = () => D.waste_type_id ? '' : tr('wizard.pick_material');
    } else if (D.step === 1) {
      const qty = h('input', { class: 'input', type: 'number', inputMode: 'decimal', min: 0, step: 'any', name: 'quantity', value: D.quantity, oninput: () => { D.quantity = qty.value; save(); } });
      const bump = d => { qty.value = Math.max(0, (+qty.value || 0) + d); D.quantity = qty.value; save(); };
      body.append(q(tr('wizard.q_quantity')),
        h('div', { class: 'stepper field' }, h('button', { type: 'button', 'aria-label': '-', onclick: () => bump(-1) }, '−'), qty, h('button', { type: 'button', 'aria-label': '+', onclick: () => bump(1) }, '+')),
        h('div', { class: 'chips field' }, ...units.map(u => h('button', { class: 'chip' + (+D.unit_id === +u.id ? ' on' : ''), onclick: () => { D.unit_id = u.id; draw(); } }, LANG === 'en' ? u.name_en : u.name_ku))),
        h('div', { class: 'label' }, tr('field.quality')),
        h('div', { class: 'chips' }, ...['A', 'B', 'C', 'ungraded'].map(g => h('button', { class: 'chip' + (D.quality_grade === g ? ' on' : ''), onclick: () => { D.quality_grade = g; draw(); } }, qualityLabel(g)))));
      valid = () => +D.quantity > 0 ? '' : tr('wizard.pick_quantity');
    } else if (D.step === 2) {
      const gps = h('button', { class: 'btn secondary block', onclick: async e => {
        e.currentTarget.disabled = true;
        try { const p = await getPosition(); D.latitude = p.lat; D.longitude = p.lng; toast(tr('geo.ok'), 'ok'); } catch { toast(tr('geo.denied'), 'error'); }
        draw();
      } }, icon('nav'), D.latitude ? tr('geo.saved') : tr('geo.use'));
      const addr = h('input', { class: 'input', name: 'address', value: D.address, placeholder: tr('wizard.address_ph'), oninput: () => { D.address = addr.value; save(); } });
      body.append(q(tr('wizard.q_location')), h('div', { class: 'field' }, gps), h('div', { class: 'field' }, h('label', {}, tr('field.address')), addr));
      if (farms.length) body.append(h('div', { class: 'field' }, h('label', {}, tr('field.farm')),
        h('select', { class: 'input', onchange: e => { D.farm_id = e.target.value; const f = farms.find(x => x.id == D.farm_id); if (f && f.latitude != null && !D.latitude) { D.latitude = f.latitude; D.longitude = f.longitude; } save(); } },
          h('option', { value: '' }, '—'), ...farms.map(f => h('option', { value: f.id, selected: f.id == D.farm_id }, f.name)))));
      valid = () => (D.latitude || D.address.trim()) ? '' : tr('wizard.pick_location');
    } else if (D.step === 3) {
      const from = h('input', { class: 'input ltr', type: 'date', name: 'available_from', min: today, value: D.available_from, onchange: () => { D.available_from = from.value; save(); } });
      const until = h('input', { class: 'input ltr', type: 'date', name: 'available_until', value: D.available_until, onchange: () => { D.available_until = until.value; save(); } });
      body.append(q(tr('wizard.q_date')), h('div', { class: 'field' }, h('label', {}, tr('detail.from')), from), h('div', { class: 'field' }, h('label', {}, tr('detail.until') + ' (' + tr('common.optional') + ')'), until));
      valid = () => !D.available_from ? tr('wizard.pick_date') : (D.available_until && D.available_until < D.available_from ? tr('wizard.bad_dates') : '');
    } else if (D.step === 4) {
      const price = h('input', { class: 'input ltr', type: 'number', inputMode: 'numeric', min: 0, name: 'price_per_unit', value: D.price_per_unit, placeholder: tr('cur.iqd'), oninput: () => { D.price_per_unit = price.value; save(); } });
      body.append(q(tr('wizard.q_price')), h('div', { class: 'tiles field' }, ...['negotiable', 'fixed', 'free'].map(t => h('button', { class: 'tile' + (D.price_type === t ? ' on' : ''), onclick: () => { D.price_type = t; draw(); } }, tr('price.' + t)))));
      if (D.price_type === 'fixed') body.append(h('div', { class: 'field' }, h('label', {}, tr('field.price_unit')), price));
      body.append(h('div', { class: 'field' }, h('label', {}, tr('field.notes') + ' (' + tr('common.optional') + ')'), h('textarea', { class: 'input', name: 'description', oninput: e => { D.description = e.target.value; save(); } }, D.description)));
      valid = () => D.price_type === 'fixed' && !(+D.price_per_unit > 0) ? tr('wizard.pick_price') : '';
    } else if (D.step === 5) {
      const grid = h('div', { class: 'photos' });
      const input = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', capture: 'environment', multiple: true, onchange: async () => {
        for (const f of input.files) if (photos.length < 6) photos.push(await shrinkImage(f));
        input.value = ''; drawGrid();
      } });
      const drawGrid = () => {
        grid.replaceChildren(...photos.map((f, i) => { const url = URL.createObjectURL(f); return h('div', { class: 'ph', style: { backgroundImage: `url("${url}")` } }, h('button', { 'aria-label': tr('common.remove'), onclick: () => { photos.splice(i, 1); drawGrid(); } }, '×')); }),
          photos.length < 6 ? h('label', { class: 'add' }, icon('camera'), tr('wizard.add_photo'), input) : '');
      };
      drawGrid();
      body.append(q(tr('wizard.q_photos')), grid, h('p', { class: 'muted small', style: { marginTop: '.75rem' } }, tr('wizard.photos_hint')));
    } else {
      const row = (k, v) => h('div', { class: 'card flat', style: { display: 'flex', justifyContent: 'space-between', gap: '1rem', marginBottom: '.5rem' } }, h('span', { class: 'muted' }, k), h('b', {}, v));
      const u = units.find(x => +x.id === +D.unit_id);
      body.append(q(tr('wizard.q_review')), row(tr('wizard.material'), wasteName(D.waste_type_id)), row(tr('field.quantity'), fmtNum(D.quantity) + ' ' + (LANG === 'en' ? u.name_en : u.name_ku)),
        row(tr('field.quality'), qualityLabel(D.quality_grade)), row(tr('field.address'), D.address || (D.latitude ? tr('geo.saved') : '—')), row(tr('detail.from'), D.available_from),
        row(tr('field.price'), D.price_type === 'fixed' ? fmtMoney(D.price_per_unit) : tr('price.' + D.price_type)), row(tr('wizard.photos'), fmtNum(photos.length)));
    }
    const nav = h('div', { class: 'wizard-nav btn-row' }, D.step > 0 ? h('button', { class: 'btn ghost', onclick: () => { D.step--; draw(); } }, tr('common.back')) : '', next);
    root.append(nav);
    root._valid = valid; root._next = next;
  }

  async function go() {
    const bad = root._valid();
    if (bad) return toast(bad, 'error');
    if (D.step < STEPS - 1) { D.step++; draw(); window.scrollTo(0, 0); return; }
    root._next.disabled = true;
    try {
      const payload = { waste_type_id: +D.waste_type_id, unit_id: +D.unit_id, quantity: D.quantity, quality_grade: D.quality_grade, available_from: D.available_from,
        available_until: D.available_until || null, address: D.address || null, latitude: D.latitude || null, longitude: D.longitude || null, farm_id: D.farm_id || null,
        price_type: D.price_type, price_per_unit: D.price_type === 'fixed' ? D.price_per_unit : null, description: D.description || null, publish: true };
      const r = await Api.post('/supplies', payload);
      let failed = 0;
      for (const f of photos) { const fd = new FormData(); fd.append('image', f, f.name || 'photo.jpg'); try { await Api.upload(`/supplies/${r.id}/images`, fd); } catch { failed++; } }
      lsSet('draft.supply', null);
      toast(failed ? tr('supply.created_photo_fail') : tr('supply.published'), failed ? 'error' : 'ok');
      location.hash = '#/supply/' + r.id;
    } catch (e) {
      root._next.disabled = false;
      toast(errMsg(e), 'error');
      const map = { waste_type_id: 0, quantity: 1, unit_id: 1, address: 2, latitude: 2, available_from: 3, available_until: 3, price_per_unit: 4 };
      const k = Object.keys(e.errors || {})[0]; if (k in map) { D.step = map[k]; draw(); }
    }
  }
  draw();
  return root;
}

/* ---------------------------------------------------------------- Demands */
async function DemandsView(ctx) {
  ctx.setTitle(tr('nav.demands'));
  await catalog();
  const root = h('div'); const list = h('div', {}, spinner());
  if (isBuyer) root.append(h('a', { class: 'btn block', href: '#/demand/new', style: { marginBottom: '1rem' } }, icon('plus'), tr('demand.new')));
  root.append(list);
  try {
    const r = await Api.get(isBuyer ? '/demands/mine?per_page=50' : '/demands?per_page=50');
    list.replaceChildren(...(r.items.length ? r.items.map(d => demandCard(d, isBuyer)) : [empty('bag', tr('demand.empty'))]));
  } catch (e) { list.replaceChildren(empty('x', errMsg(e))); }
  return root;
}

async function DemandNewView(ctx) {
  ctx.setTitle(tr('demand.new')); ctx.setBack('#/demands');
  const { units, wasteTypes } = await catalog();
  const f = h('form', { novalidate: true, onsubmit: async ev => {
    ev.preventDefault();
    const fd = Object.fromEntries(new FormData(f));
    const body = { waste_type_id: +fd.waste_type_id, unit_id: +fd.unit_id, required_quantity: fd.required_quantity, minimum_quality: fd.minimum_quality,
      max_price_per_unit: fd.max_price_per_unit || null, required_until: fd.required_until || null, address: fd.address || null,
      latitude: fd.latitude || null, longitude: fd.longitude || null, delivery_required: fd.delivery_required === 'on', publish: true };
    const btn = f.querySelector('button[type=submit]'); btn.disabled = true;
    try { await Api.post('/demands', body); toast(tr('demand.created'), 'ok'); location.hash = '#/demands'; }
    catch (e) { showFieldErrors(f, e); btn.disabled = false; }
  } },
    field(tr('wizard.material'), h('select', { class: 'input', name: 'waste_type_id' }, ...wasteTypes.map(w => h('option', { value: w.id }, nm(w))))),
    h('div', { class: 'btn-row' },
      field(tr('field.quantity'), h('input', { class: 'input ltr', name: 'required_quantity', type: 'number', inputMode: 'decimal', min: 0, step: 'any', required: true })),
      field(tr('field.unit'), h('select', { class: 'input', name: 'unit_id' }, ...units.map(u => h('option', { value: u.id }, LANG === 'en' ? u.name_en : u.name_ku))))),
    field(tr('demand.min_quality'), h('select', { class: 'input', name: 'minimum_quality' }, ...['any', 'A', 'B', 'C'].map(q => h('option', { value: q }, qualityLabel(q))))),
    field(tr('demand.maxprice_label'), h('input', { class: 'input ltr', name: 'max_price_per_unit', type: 'number', inputMode: 'numeric', min: 0 })),
    field(tr('demand.until'), h('input', { class: 'input ltr', name: 'required_until', type: 'date' })),
    field(tr('field.address'), h('input', { class: 'input', name: 'address' })),
    h('input', { type: 'hidden', name: 'latitude' }), h('input', { type: 'hidden', name: 'longitude' }),
    h('button', { type: 'button', class: 'btn secondary block', style: { marginBottom: '1rem' }, onclick: async e => {
      try { const p = await getPosition(); f.latitude.value = p.lat; f.longitude.value = p.lng; e.currentTarget.textContent = tr('geo.saved'); } catch { toast(tr('geo.denied'), 'error'); } } }, icon('nav'), tr('geo.use')),
    h('label', { class: 'chip on', style: { marginBottom: '1rem' } }, h('input', { type: 'checkbox', name: 'delivery_required', checked: true }), ' ', tr('demand.delivery')),
    h('button', { class: 'btn block', type: 'submit' }, tr('demand.publish')));
  return f;
}
const field = (label, input) => h('div', { class: 'field' }, h('label', {}, label), input);

async function DemandView(ctx) {
  ctx.setBack('#/demands');
  const [d] = await Promise.all([Api.get('/demands/' + ctx.params[0]), catalog()]);
  ctx.setTitle(nm({ name_ku: d.waste_name_ku, name_ar: d.waste_name_ar, name_en: d.waste_name_en }));
  const mine = +d.buyer_user_id === +ME.id || ME.role === 'admin';
  const root = h('div', {}, h('div', { class: 'card' },
    h('div', { style: { display: 'flex', justifyContent: 'space-between' } }, h('h2', {}, nm({ name_ku: d.waste_name_ku, name_ar: d.waste_name_ar, name_en: d.waste_name_en })), statusBadge(d.status)),
    h('div', { class: 'kv' },
      h('div', {}, h('small', {}, tr('demand.remaining')), fmtNum(d.remaining_quantity) + ' ' + d.unit_code),
      h('div', {}, h('small', {}, tr('demand.min_quality')), qualityLabel(d.minimum_quality)),
      h('div', {}, h('small', {}, tr('demand.maxprice_label')), d.max_price_per_unit ? fmtMoney(d.max_price_per_unit) : '—'),
      h('div', {}, h('small', {}, tr('demand.until')), d.required_until || '—')),
    d.address ? h('p', {}, icon('pin'), ' ', d.address) : null));
  if (mine && ['draft', 'active'].includes(d.status)) root.append(h('button', { class: 'btn danger block', onclick: async () => {
    if (await confirmSheet(tr('demand.cancel'), tr('demand.cancel_q'), tr('demand.cancel'))) { try { await Api.del('/demands/' + d.id); toast(tr('demand.cancelled'), 'ok'); location.hash = '#/demands'; } catch (e) { toast(errMsg(e), 'error'); } } } }, tr('demand.cancel')));
  return root;
}

/* ------------------------------------------------------------------ Farms */
async function FarmsView(ctx) {
  ctx.setTitle(tr('farms.title')); ctx.setBack('#/');
  const root = h('div'); const list = h('div', {}, spinner());
  const load = async () => {
    try {
      const farms = await Api.get('/farms');
      list.replaceChildren(...(farms.length ? farms.map(f => h('div', { class: 'card' }, h('h3', {}, f.name), h('div', { class: 'muted small' }, [f.area_donum ? tr('farms.area', { n: fmtNum(f.area_donum) }) : '', f.address || ''].filter(Boolean).join(' · ')))) : [empty('farm', tr('farms.empty'))]));
    } catch (e) { list.replaceChildren(empty('x', errMsg(e))); }
  };
  root.append(h('button', { class: 'btn block', style: { marginBottom: '1rem' }, onclick: addFarm }, icon('plus'), tr('farms.add')), list);
  function addFarm() {
    const f = h('form', { novalidate: true, onsubmit: async ev => {
      ev.preventDefault(); const fd = Object.fromEntries(new FormData(f));
      const btn = f.querySelector('button[type=submit]'); btn.disabled = true;
      try { await Api.post('/farms', { name: fd.name, area_donum: fd.area_donum || null, address: fd.address || null, latitude: fd.latitude || null, longitude: fd.longitude || null }); s.close(); toast(tr('common.saved'), 'ok'); load(); }
      catch (e) { showFieldErrors(f, e); btn.disabled = false; }
    } },
      field(tr('farms.name'), h('input', { class: 'input', name: 'name', required: true })),
      field(tr('farms.area_label'), h('input', { class: 'input ltr', name: 'area_donum', type: 'number', inputMode: 'decimal', min: 0, step: 'any' })),
      field(tr('field.address'), h('input', { class: 'input', name: 'address' })),
      h('input', { type: 'hidden', name: 'latitude' }), h('input', { type: 'hidden', name: 'longitude' }),
      h('button', { type: 'button', class: 'btn secondary block', style: { marginBottom: '1rem' }, onclick: async e => { try { const p = await getPosition(); f.latitude.value = p.lat; f.longitude.value = p.lng; e.currentTarget.textContent = tr('geo.saved'); } catch { toast(tr('geo.denied'), 'error'); } } }, icon('nav'), tr('geo.use')),
      h('button', { class: 'btn block', type: 'submit' }, tr('common.save')));
    const s = sheet(tr('farms.add'), f);
  }
  load();
  return root;
}

/* ---------------------------------------------------------------- Profile */
async function ProfileView(ctx) {
  ctx.setTitle(tr('nav.me'));
  const root = h('div');
  root.append(h('div', { class: 'card' }, h('h2', {}, ME.full_name), h('div', { class: 'badge' }, tr('role.' + ME.role)),
    h('p', { class: 'muted ltr', style: { marginTop: '.75rem' } }, ME.phone), ME.email ? h('p', { class: 'muted ltr' }, ME.email) : null));
  if (ME.role === 'farmer') root.append(h('a', { class: 'btn ghost block', style: { marginBottom: '.75rem' }, href: '#/farms' }, icon('farm'), tr('farms.title')));
  root.append(h('div', { class: 'card' }, h('div', { class: 'label' }, icon('globe'), ' ', tr('profile.language')),
    h('div', { class: 'chips' }, ...[['ku', 'کوردی'], ['ar', 'العربية'], ['en', 'English']].map(([c, n]) => h('a', { class: 'chip' + (LANG === c ? ' on' : ''), href: '?lang=' + c + location.hash }, n)))));
  if (window.__installPrompt) root.append(h('button', { class: 'btn secondary block', style: { marginBottom: '.75rem' }, onclick: async () => { window.__installPrompt.prompt(); window.__installPrompt = null; render(); } }, icon('download'), tr('pwa.install')));
  root.append(h('button', { class: 'btn danger block', onclick: async () => { await Api.post('/auth/logout').catch(() => {}); lsSet('draft.supply', null); location.href = U('/'); } }, icon('logout'), tr('nav.logout')));
  return root;
}

const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
