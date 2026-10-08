// Thin JSON client: attaches CSRF token, refreshes it once on 419.
const Api = (() => {
  let csrf = null;
  const fetchToken = async () => {
    const r = await fetch('/api/auth/csrf', { credentials: 'same-origin' });
    csrf = (await r.json()).data.token;
  };
  const request = async (method, path, body, retried = false) => {
    if (method !== 'GET' && !csrf) await fetchToken();
    const res = await fetch('/api' + path, {
      method, credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf || '' },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
    const json = await res.json().catch(() => ({ success: false, message: 'Bad response' }));
    if (res.status === 419 && !retried) { await fetchToken(); return request(method, path, body, true); }
    if (!res.ok || !json.success) { const e = new Error(json.message); e.status = res.status; e.errors = json.errors || {}; throw e; }
    if (path.startsWith('/auth/')) csrf = null; // session id rotates on login/logout
    return json.data;
  };
  return { get: p => request('GET', p), post: (p, b) => request('POST', p, b), put: (p, b) => request('PUT', p, b) };
})();
