<?php
/** @var string $pageTitle */
$user = Auth::user();
$rtl = Lang::dir() === 'rtl';
$bs = $rtl
    ? 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css'
    : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
?>
<!doctype html>
<html lang="<?= e(Lang::current()) ?>" dir="<?= Lang::dir() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? t('app.name')) ?> — <?= e(t('app.name')) ?></title>
<link rel="stylesheet" href="<?= $bs ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<nav class="navbar navbar-expand-md bg-success navbar-dark">
  <div class="container">
    <a class="navbar-brand fw-bold" href="/"><i class="bi bi-recycle"></i> <?= e(t('app.name')) ?></a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div id="nav" class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto me-auto-rtl align-items-md-center gap-md-2">
        <?php if ($user): ?>
          <li class="nav-item"><a class="nav-link" href="/dashboard/"><i class="bi bi-speedometer2"></i> <?= e(t('nav.dashboard')) ?></a></li>
          <li class="nav-item"><a class="nav-link" href="#" id="logout"><i class="bi bi-box-arrow-right"></i> <?= e(t('nav.logout')) ?></a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="/login.php"><?= e(t('nav.login')) ?></a></li>
          <li class="nav-item"><a class="btn btn-light btn-sm" href="/register.php"><?= e(t('nav.register')) ?></a></li>
        <?php endif; ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-translate"></i></a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="?lang=ku">کوردی</a></li>
            <li><a class="dropdown-item" href="?lang=ar">العربية</a></li>
            <li><a class="dropdown-item" href="?lang=en">English</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
<main class="container py-4">
