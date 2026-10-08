<?php
require __DIR__ . '/app/bootstrap.php';
if (Auth::user()) { header('Location: /dashboard/'); exit; }
$pageTitle = t('nav.register');
include __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-md-7 col-lg-6">
  <div class="card"><div class="card-body p-4">
    <h1 class="h4 mb-3"><?= e(t('nav.register')) ?></h1>
    <form id="register-form" novalidate>
      <input type="hidden" name="preferred_language" value="<?= e(Lang::current()) ?>">
      <div class="mb-3"><label class="form-label"><?= e(t('form.role')) ?></label>
        <select class="form-select" name="role">
          <?php foreach (AuthService::SELF_REGISTER_ROLES as $r): ?>
            <option value="<?= e($r) ?>"><?= e(t("role.$r")) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="mb-3"><label class="form-label"><?= e(t('form.full_name')) ?></label>
        <input class="form-control" name="full_name" autocomplete="name" required></div>
      <div class="mb-3"><label class="form-label"><?= e(t('form.phone')) ?></label>
        <input class="form-control" name="phone" type="tel" dir="ltr" autocomplete="tel" placeholder="+9647500000000" required></div>
      <div class="mb-3"><label class="form-label"><?= e(t('form.email')) ?></label>
        <input class="form-control" name="email" type="email" dir="ltr" autocomplete="email"></div>
      <div class="mb-3" id="org-group"><label class="form-label"><?= e(t('form.org')) ?></label>
        <input class="form-control" name="organization_name"></div>
      <div class="mb-3"><label class="form-label"><?= e(t('form.password')) ?></label>
        <input class="form-control" type="password" name="password" autocomplete="new-password" required></div>
      <button class="btn btn-success w-100" type="submit"><?= e(t('nav.register')) ?></button>
    </form>
  </div></div>
</div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
