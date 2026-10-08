const tr = k => (window.I18N && window.I18N[k]) || k;

function showErrors($form, err) {
  $form.find('.alert').remove();
  const msgs = Object.values(err.errors || {}).flat();
  const html = (msgs.length ? msgs : [err.message || tr('err.generic')]).map(m => $('<div>').text(m).html()).join('<br>');
  $form.prepend(`<div class="alert alert-danger" role="alert">${html}</div>`);
}

function bindAuthForm(selector, path, redirect) {
  $(selector).on('submit', async function (ev) {
    ev.preventDefault();
    const $f = $(this), data = Object.fromEntries(new FormData(this));
    $f.find('button[type=submit]').prop('disabled', true);
    try { await Api.post(path, data); location.href = redirect; }
    catch (err) { showErrors($f, err); }
    finally { $f.find('button[type=submit]').prop('disabled', false); }
  });
}

$(function () {
  bindAuthForm('#login-form', '/auth/login', '/dashboard/');
  bindAuthForm('#register-form', '/auth/register', '/dashboard/');
  $('#logout').on('click', async e => { e.preventDefault(); await Api.post('/auth/logout'); location.href = '/'; });
  $('#register-form [name=role]').on('change', function () {
    $('#org-group').toggle(['buyer', 'transport', 'collection_center'].includes(this.value));
  }).trigger('change');
});
