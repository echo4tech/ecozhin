// Thin JSON client: attaches CSRF token, refreshes it once on 419.
const BASE = window.BASE || '';
const U = p => BASE + p; // prefix an absolute app path with the sub-folder (e.g. /ecozhin)
const Api = (() => {
  let csrf = null;
  const fetchToken = async () => {
    const r = await fetch(BASE + '/api/auth/csrf', { credentials: 'same-origin' });
    csrf = (await r.json()).data.token;
  };
  const request = async (method, path, body, retried = false) => {
    if (method !== 'GET' && !csrf) await fetchToken();
    const isForm = body instanceof FormData;
    const headers = { 'X-CSRF-Token': csrf || '' };
    if (!isForm) headers['Content-Type'] = 'application/json';
    let res;
    try {
      res = await fetch(BASE + '/api' + path, { method, credentials: 'same-origin', headers, body: body === undefined ? undefined : (isForm ? body : JSON.stringify(body)) });
    } catch (e) { const err = new Error(window.I18N?.['err.network'] || 'Network error'); err.network = true; err.errors = {}; throw err; }
    const json = await res.json().catch(() => ({ success: false, message: 'Bad response' }));
    if (res.status === 419 && !retried) { await fetchToken(); return request(method, path, body, true); }
    if (!res.ok || !json.success) { const e = new Error(json.message); e.status = res.status; e.errors = json.errors || {}; throw e; }
    if (path.startsWith('/auth/')) csrf = null; // session id rotates on login/logout
    return json.data;
  };
  return {
    get: p => request('GET', p), post: (p, b) => request('POST', p, b), put: (p, b) => request('PUT', p, b),
    del: p => request('DELETE', p), upload: (p, fd) => request('POST', p, fd),
  };
})();
