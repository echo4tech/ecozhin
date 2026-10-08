<?php
require __DIR__ . '/app/bootstrap.php';
$lang = Lang::current();
header('Content-Type: application/manifest+json; charset=utf-8');
echo json_encode([
    'name' => t('app.name') . ' — ' . t('manifest.tagline'),
    'short_name' => t('app.name'),
    'description' => t('home.subtitle'),
    'lang' => $lang, 'dir' => Lang::dir(),
    'start_url' => url('/dashboard/'), 'scope' => url('/'), 'id' => url('/'),
    'display' => 'standalone', 'orientation' => 'portrait',
    'background_color' => '#f3f6f2', 'theme_color' => '#1a7a4a',
    'icons' => [
        ['src' => url('/assets/images/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => url('/assets/images/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => url('/assets/images/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
