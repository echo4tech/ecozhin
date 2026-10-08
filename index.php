<?php
require __DIR__ . '/app/bootstrap.php';
$pageTitle = t('nav.home');
include __DIR__ . '/includes/header.php';
?>
<section class="hero text-center">
  <h1 class="display-6 fw-bold"><?= e(t('home.title')) ?></h1>
  <p class="lead text-muted"><?= e(t('home.subtitle')) ?></p>
  <a class="btn btn-success btn-lg" href="/register.php"><?= e(t('home.cta')) ?></a>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
