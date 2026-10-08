<?php
require __DIR__ . '/app/bootstrap.php';
if (Auth::user()) { header('Location: /dashboard/'); exit; }
$pageTitle = t('nav.home');
include __DIR__ . '/includes/header.php';
?>
<div class="public-wrap center">
  <div style="margin-top:2.5rem"><img src="/assets/images/icon.svg" width="96" height="96" alt=""></div>
  <h1 style="margin-top:1rem"><?= e(t('app.name')) ?></h1>
  <p class="lead"><b><?= e(t('home.title')) ?></b></p>
  <p class="muted"><?= e(t('home.subtitle')) ?></p>
  <div class="card" style="text-align:start;margin-top:1.5rem">
    <p>🌾 <?= e(t('home.f1')) ?></p><p>🏭 <?= e(t('home.f2')) ?></p><p style="margin:0">📍 <?= e(t('home.f3')) ?></p>
  </div>
  <a class="btn block" href="/register.php" style="margin-top:1rem"><?= e(t('home.cta')) ?></a>
  <a class="btn ghost block" href="/login.php" style="margin-top:.6rem"><?= e(t('nav.login')) ?></a>
  <p style="margin-top:1.5rem" class="small"><a href="?lang=ku">کوردی</a> · <a href="?lang=ar">العربية</a> · <a href="?lang=en">English</a></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
