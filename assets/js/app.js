// App shell: hash router, top bar, bottom navigation, offline banner, service worker.
const ROUTES = [
  [/^\/$/, HomeView, 'home'], [/^\/market$/, MarketView, 'market'], [/^\/supply\/(\d+)$/, SupplyView, 'market'],
  [/^\/my$/, MyView, 'my', () => isSupplier], [/^\/add$/, AddView, 'add', () => isSupplier],
  [/^\/demands$/, DemandsView, 'demands'], [/^\/demand\/new$/, DemandNewView, 'demands', () => isBuyer], [/^\/demand\/(\d+)$/, DemandView, 'demands'],
  [/^\/farms$/, FarmsView, 'me', () => ME.role === 'farmer'], [/^\/me$/, ProfileView, 'me'],
];
const NAV = {
  farmer: ['home', 'market', 'add', 'my', 'me'], collection_center: ['home', 'market', 'add', 'my', 'me'],
  buyer: ['home', 'market', 'demands', 'me'], admin: ['home', 'market', 'add', 'demands', 'me'],
};
const NAV_DEF = { home: ['#/', 'home'], market: ['#/market', 'store'], add: ['#/add', 'plus'], my: ['#/my', 'list'], demands: ['#/demands', 'bag'], me: ['#/me', 'user'] };

const shell = {
  title: h('h1'), back: h('button', { class: 'icon-btn hidden', 'aria-label': tr('common.back'), onclick: () => { location.hash = shell.backTo || '#/'; } }, icon('back', 'flip-rtl')),
  logo: h('span', { class: 'icon-btn' }, icon('leaf')),
  main: h('main', { class: 'app-main', id: 'view' }), nav: h('nav', { class: 'bottom-nav', 'aria-label': 'main' }),
  offline: h('div', { class: 'offline-bar hidden', role: 'status' }, tr('err.network')), backTo: null,
};
const ctxFor = params => ({ params, setTitle: t => { shell.title.textContent = t; document.title = t + ' — ' + tr('app.name'); }, setBack: to => { shell.backTo = to; } });

let token = 0;
async function render() {
  const my = ++token;
  const path = (location.hash.slice(1) || '/').split('?')[0];
  shell.backTo = null;
  const route = ROUTES.find(([re, , , guard]) => re.test(path) && (!guard || guard()));
  if (!route) { location.hash = '#/'; return; }
  const [re, view, navKey] = route;
  shell.main.replaceChildren(spinner());
  shell.main.classList.remove('no-nav');
  try {
    const node = await view(ctxFor(path.match(re).slice(1)));
    if (my !== token) return;
    shell.main.replaceChildren(node);
  } catch (e) {
    if (my !== token) return;
    if (e.status === 401) { location.href = '/login.php'; return; }
    shell.main.replaceChildren(empty('x', e.status === 404 ? tr('err.notfound') : errMsg(e)), h('button', { class: 'btn secondary block', onclick: render }, tr('common.retry')));
  }
  shell.back.classList.toggle('hidden', !shell.backTo); shell.logo.classList.toggle('hidden', !!shell.backTo);
  shell.nav.querySelectorAll('a').forEach(a => a.classList.toggle('on', a.dataset.key === navKey));
  window.scrollTo(0, 0);
}

function boot() {
  const items = (NAV[ME.role] || ['home', 'market', 'me']);
  shell.nav.append(...items.map(k => {
    const [href, ic] = NAV_DEF[k];
    return h('a', { href, 'data-key': k, class: k === 'add' ? 'fab' : '' }, k === 'add' ? h('span', { class: 'fab-c' }, icon(ic)) : icon(ic), tr('nav.' + k));
  }));
  document.getElementById('app').append(h('header', { class: 'topbar' }, shell.back, shell.logo, shell.title), shell.offline, shell.main, shell.nav);
  window.addEventListener('hashchange', render);
  const setOnline = () => shell.offline.classList.toggle('hidden', navigator.onLine);
  addEventListener('online', setOnline); addEventListener('offline', setOnline); setOnline();
  window.addEventListener('beforeinstallprompt', e => { e.preventDefault(); window.__installPrompt = e; });
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});
  render();
}
boot();
