<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
$user = requirePageAuth();
$pageTitle = t('nav.dashboard');
$wasteTypes = MasterDataRepository::wasteTypes();
$nameCol = ['ku' => 'name_ku', 'ar' => 'name_ar', 'en' => 'name_en'][Lang::current()];
include dirname(__DIR__) . '/includes/header.php';
?>
<h1 class="h4"><?= e(t('dash.welcome')) ?>، <?= e($user['full_name']) ?>
  <span class="badge bg-success"><?= e(t('role.' . $user['role'])) ?></span></h1>
<p class="text-muted"><?= e(t('dash.coming')) ?></p>
<div class="card"><div class="card-body">
  <h2 class="h6"><?= e(t('dash.catalog')) ?></h2>
  <ul class="list-inline mb-0">
    <?php foreach ($wasteTypes as $w): ?>
      <li class="list-inline-item badge text-bg-light border"><?= e($w[$nameCol] ?: $w['name_ku']) ?></li>
    <?php endforeach; ?>
  </ul>
</div></div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
