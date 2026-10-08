<?php
require __DIR__ . '/app/bootstrap.php';
if (Auth::user()) { header('Location: ' . url('/dashboard/')); exit; }
$pageTitle = t('nav.login');
include __DIR__ . '/includes/header.php';
?>
<div class="public-wrap">
  <a href="<?= url('/') ?>" class="small"><?= e(t('common.back')) ?></a>
  <h1 style="margin-top:1rem"><?= e(t('nav.login')) ?></h1>
  <form id="login-form" class="card" novalidate>
    <div class="field"><label for="l"><?= e(t('form.login')) ?></label>
      <input id="l" class="input ltr" name="login" autocomplete="username" inputmode="email" autocapitalize="none" required></div>
    <div class="field"><label for="p"><?= e(t('form.password')) ?></label>
      <input id="p" class="input ltr" type="password" name="password" autocomplete="current-password" required></div>
    <button class="btn block" type="submit"><?= e(t('nav.login')) ?></button>
  </form>
  <p class="center"><a href="<?= url('/register.php') ?>"><?= e(t('nav.register')) ?></a></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
