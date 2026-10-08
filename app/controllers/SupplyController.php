<?php
declare(strict_types=1);

final class SupplyController
{
    private static function filters(): array
    {
        $f = [];
        foreach (['waste_type_id', 'product_id', 'quality', 'min_price', 'max_price', 'min_quantity', 'available_by', 'q', 'lat', 'lng', 'radius_km', 'sort', 'status'] as $k) {
            $v = $_GET[$k] ?? null;
            if (is_string($v) && $v !== '') {
                $f[$k] = $v;
            }
        }
        $errors = Validator::validate($f, [
            'waste_type_id' => 'int', 'product_id' => 'int', 'quality' => 'in:A,B,C,ungraded',
            'min_price' => 'numeric', 'max_price' => 'numeric', 'min_quantity' => 'numeric', 'available_by' => 'date',
            'lat' => 'lat', 'lng' => 'lng', 'radius_km' => 'numeric|gt:0', 'sort' => 'in:newest,price_asc,quantity_desc,nearest',
            'status' => 'in:draft,active,reserved,partially_sold,sold,expired,cancelled',
        ]);
        if ($errors) {
            throw new ApiException('Validation failed', 422, $errors);
        }
        return $f;
    }

    private static function respondList(array $rows, int $total): never
    {
        [$per, , $page] = Fields::page();
        Response::ok(['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $per]);
    }

    public static function index(): void
    {
        [$rows, $total] = SupplyRepository::search(self::filters());
        self::respondList($rows, $total);
    }

    public static function mine(): void
    {
        $u = Auth::require(...SupplyService::SUPPLIER_ROLES);
        [$rows, $total] = SupplyRepository::search(self::filters(), true, (int) $u['id']);
        self::respondList($rows, $total);
    }

    public static function show(string $id): void
    {
        $s = SupplyRepository::find((int) $id);
        $viewer = Auth::user();
        $isOwner = $viewer && ((int) $viewer['id'] === (int) ($s['supplier_user_id'] ?? 0) || $viewer['role'] === 'admin');
        if (!$s || (!$isOwner && !in_array($s['status'], SupplyRepository::MARKET_STATUSES, true))) {
            throw new ApiException('Not found', 404);
        }
        $s['images'] = SupplyRepository::images((int) $id);
        Response::ok($s);
    }

    public static function create(): void
    {
        $u = Auth::require(...SupplyService::SUPPLIER_ROLES);
        $b = Request::body();
        $id = SupplyService::create($u, $b);
        if (!empty($b['publish'])) {
            SupplyService::publish($u, $id);
        }
        Response::ok(['id' => $id], 'Supply created', 201);
    }

    public static function update(string $id): void
    {
        SupplyService::update(Auth::require(...SupplyService::SUPPLIER_ROLES), (int) $id, Request::body());
        Response::ok(null, 'Supply updated');
    }

    public static function publish(string $id): void
    {
        SupplyService::publish(Auth::require(...SupplyService::SUPPLIER_ROLES), (int) $id);
        Response::ok(null, 'Supply published');
    }

    public static function delete(string $id): void
    {
        SupplyService::cancel(Auth::require(...SupplyService::SUPPLIER_ROLES), (int) $id);
        Response::ok(null, 'Supply cancelled');
    }

    public static function uploadImage(string $id): void
    {
        $r = SupplyService::addImage(Auth::require(...SupplyService::SUPPLIER_ROLES), (int) $id, $_FILES['image'] ?? null);
        Response::ok($r, 'Image uploaded', 201);
    }
}
