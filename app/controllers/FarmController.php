<?php
declare(strict_types=1);

final class FarmController
{
    private const RULES = [
        'name' => 'required|string|max:150', 'area_donum' => 'numeric|gte:0', 'address' => 'string|max:1000',
        'latitude' => 'lat', 'longitude' => 'lng', 'village_id' => 'int',
    ];

    private static function data(array $b): array
    {
        $errors = Validator::validate($b, self::RULES);
        $d = [
            'name' => trim((string) ($b['name'] ?? '')),
            'village_id' => Fields::nullable($b, 'village_id') === null ? null : (int) $b['village_id'],
            'area_donum' => Fields::nullableNum($b, 'area_donum'),
            'address' => Fields::nullable($b, 'address'),
            'latitude' => Fields::nullableNum($b, 'latitude'),
            'longitude' => Fields::nullableNum($b, 'longitude'),
        ];
        if (!$errors && $d['village_id'] !== null) {
            Fields::ensureExists('villages', $d['village_id'], 'village_id', $errors);
        }
        if ($errors) {
            throw new ApiException('Validation failed', 422, $errors);
        }
        return $d;
    }

    public static function index(): void
    {
        $u = Auth::require('farmer', 'admin');
        Response::ok(FarmRepository::forFarmer((int) $u['id']));
    }

    public static function create(): void
    {
        $u = Auth::require('farmer');
        $id = FarmRepository::create((int) $u['id'], self::data(Request::body()));
        Response::ok(['id' => $id], 'Farm created', 201);
    }

    public static function update(string $id): void
    {
        $u = Auth::require('farmer');
        $farm = FarmRepository::find((int) $id);
        if (!$farm || (int) $farm['farmer_user_id'] !== (int) $u['id']) {
            throw new ApiException('Not found', 404);
        }
        FarmRepository::update((int) $id, self::data(Request::body()));
        Response::ok(null, 'Farm updated');
    }
}
