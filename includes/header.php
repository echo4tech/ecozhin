<?php
/** @var string $pageTitle */
$rtl = Lang::dir() === 'rtl';
?>
<!doctype html>
<html lang="<?= e(Lang::current()) ?>" dir="<?= Lang::dir() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1a7a4a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title><?= e($pageTitle ?? t('app.name')) ?> — <?= e(t('app.name')) ?></title>
<link rel="manifest" href="/manifest.php?lang=<?= e(Lang::current()) ?>">
<link rel="icon" href="/assets/images/icon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/images/icon-192.png">
<link rel="stylesheet" href="/assets/css/app.css?v=<?= APP_VERSION ?>">
</head>
<body>
