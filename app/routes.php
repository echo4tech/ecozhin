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

// Farms (farmer)
$router->add('GET', '/farms', [FarmController::class, 'index']);
$router->add('POST', '/farms', [FarmController::class, 'create']);
$router->add('PUT', '/farms/{id}', [FarmController::class, 'update']);

// Supply marketplace  (/supplies/mine must be registered before /supplies/{id})
$router->add('GET', '/supplies', [SupplyController::class, 'index']);
$router->add('GET', '/supplies/mine', [SupplyController::class, 'mine']);
$router->add('POST', '/supplies', [SupplyController::class, 'create']);
$router->add('GET', '/supplies/{id}', [SupplyController::class, 'show']);
$router->add('PUT', '/supplies/{id}', [SupplyController::class, 'update']);
$router->add('DELETE', '/supplies/{id}', [SupplyController::class, 'delete']);
$router->add('POST', '/supplies/{id}/publish', [SupplyController::class, 'publish']);
$router->add('POST', '/supplies/{id}/images', [SupplyController::class, 'uploadImage']);

// Buyer demands
$router->add('GET', '/demands', [DemandController::class, 'index']);
$router->add('GET', '/demands/mine', [DemandController::class, 'mine']);
$router->add('POST', '/demands', [DemandController::class, 'create']);
$router->add('GET', '/demands/{id}', [DemandController::class, 'show']);
$router->add('PUT', '/demands/{id}', [DemandController::class, 'update']);
$router->add('DELETE', '/demands/{id}', [DemandController::class, 'delete']);
$router->add('POST', '/demands/{id}/publish', [DemandController::class, 'publish']);
