// Login / register forms (public pages).
(function () {
  const tr = k => (window.I18N && window.I18N[k]) || k;
  function bind(id, path) {
    const form = document.getElementById(id);
    if (!form) return;
    form.addEventListener('submit', async ev => {
      ev.preventDefault();
      const btn = form.querySelector('button[type=submit]');
      form.querySelectorAll('.field-error').forEach(n => n.remove());
      btn.disabled = true;
      try {
        await Api.post(path, Object.fromEntries(new FormData(form)));
        location.href = (window.BASE || '') + '/dashboard/';
      } catch (e) {
        btn.disabled = false;
        let shown = false;
        for (const [k, msgs] of Object.entries(e.errors || {})) {
          const input = form.querySelector('[name="' + k + '"]');
          if (!input) continue;
          const d = document.createElement('div'); d.className = 'field-error'; d.textContent = msgs[0];
          input.closest('.field').appendChild(d); shown = true;
        }
        if (!shown) {
          const d = document.createElement('div'); d.className = 'field-error'; d.setAttribute('role', 'alert');
          d.textContent = e.message || tr('err.generic'); btn.before(d);
        }
      }
    });
  }
  bind('login-form', '/auth/login');
  bind('register-form', '/auth/register');
  const role = document.querySelector('#register-form [name=role]');
  if (role) { const g = document.getElementById('org-group'); const f = () => g.classList.toggle('hidden', role.value === 'farmer'); role.addEventListener('change', f); f(); }
})();
