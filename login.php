<?php
require __DIR__ . '/app/bootstrap.php';
if (Auth::user()) { header('Location: /dashboard/'); exit; }
$pageTitle = t('nav.login');
include __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-md-6 col-lg-5">
  <div class="card"><div class="card-body p-4">
    <h1 class="h4 mb-3"><?= e(t('nav.login')) ?></h1>
    <form id="login-form" novalidate>
      <div class="mb-3"><label class="form-label"><?= e(t('form.login')) ?></label>
        <input class="form-control" name="login" autocomplete="username" required></div>
      <div class="mb-3"><label class="form-label"><?= e(t('form.password')) ?></label>
        <input class="form-control" type="password" name="password" autocomplete="current-password" required></div>
      <button class="btn btn-success w-100" type="submit"><?= e(t('nav.login')) ?></button>
    </form>
  </div></div>
</div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
