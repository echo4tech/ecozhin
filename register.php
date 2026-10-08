<?php
require __DIR__ . '/app/bootstrap.php';
if (Auth::user()) { header('Location: ' . url('/dashboard/')); exit; }
$pageTitle = t('nav.register');
include __DIR__ . '/includes/header.php';
?>
<div class="public-wrap">
  <a href="<?= url('/') ?>" class="small"><?= e(t('common.back')) ?></a>
  <h1 style="margin-top:1rem"><?= e(t('nav.register')) ?></h1>
  <form id="register-form" class="card" novalidate>
    <input type="hidden" name="preferred_language" value="<?= e(Lang::current()) ?>">
    <div class="field"><label><?= e(t('form.role')) ?></label>
      <select class="input" name="role">
        <?php foreach (AuthService::SELF_REGISTER_ROLES as $r): ?>
          <option value="<?= e($r) ?>"><?= e(t("role.$r")) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="field"><label><?= e(t('form.full_name')) ?></label>
      <input class="input" name="full_name" autocomplete="name" required></div>
    <div class="field"><label><?= e(t('form.phone')) ?></label>
      <input class="input ltr" name="phone" type="tel" autocomplete="tel" placeholder="+9647500000000" required></div>
    <div class="field"><label><?= e(t('form.email')) ?></label>
      <input class="input ltr" name="email" type="email" autocomplete="email"></div>
    <div class="field hidden" id="org-group"><label><?= e(t('form.org')) ?></label>
      <input class="input" name="organization_name"></div>
    <div class="field"><label><?= e(t('form.password')) ?></label>
      <input class="input ltr" type="password" name="password" autocomplete="new-password" required></div>
    <button class="btn block" type="submit"><?= e(t('nav.register')) ?></button>
  </form>
  <p class="center"><a href="<?= url('/login.php') ?>"><?= e(t('nav.login')) ?></a></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
