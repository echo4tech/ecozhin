<?php
declare(strict_types=1);

/** @var Router $router */

// Auth
$router->add('GET', '/auth/csrf', [AuthController::class, 'csrf']);
$router->add('POST', '/auth/register', [AuthController::class, 'register']);
$router->add('POST', '/auth/login', [AuthController::class, 'login']);
$router->add('POST', '/auth/logout', [AuthController::class, 'logout']);
$router->add('GET', '/auth/me', [AuthController::class, 'me']);

// Master data
$router->add('GET', '/units', [MasterDataController::class, 'units']);
$router->add('GET', '/products', [MasterDataController::class, 'products']);
$router->add('GET', '/waste-types', [MasterDataController::class, 'wasteTypes']);
$router->add('GET', '/geo/governorates', [MasterDataController::class, 'governorates']);
$router->add('GET', '/geo/districts', [MasterDataController::class, 'districts']);
$router->add('GET', '/settings/public', [MasterDataController::class, 'settings']);

// Admin master data
$router->add('POST', '/waste-types', [MasterDataController::class, 'createWasteType']);
$router->add('PUT', '/waste-types/{id}', [MasterDataController::class, 'updateWasteType']);
$router->add('POST', '/products', [MasterDataController::class, 'createProduct']);
