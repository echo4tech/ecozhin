<?php
declare(strict_types=1);

final class DemandController
{
    private const ROLES = ['buyer', 'admin'];
    private const RULES = [
        'waste_type_id' => 'required|int', 'unit_id' => 'required|int', 'required_quantity' => 'required|numeric|gt:0',
        'minimum_quality' => 'in:A,B,C,any', 'max_price_per_unit' => 'numeric|gte:0', 'required_from' => 'date', 'required_until' => 'date',
        'latitude' => 'lat', 'longitude' => 'lng', 'address' => 'string|max:1000', 'organization_id' => 'int',
    ];

    private static function data(array $b, array $user): array
    {
        $errors = Validator::validate($b, self::RULES);
        $d = [
            'waste_type_id' => (int) ($b['waste_type_id'] ?? 0), 'unit_id' => (int) ($b['unit_id'] ?? 0),
            'organization_id' => Fields::nullable($b, 'organization_id') === null ? null : (int) $b['organization_id'],
            'required_quantity' => $b['required_quantity'] ?? 0, 'minimum_quality' => $b['minimum_quality'] ?? 'any',
            'max_price_per_unit' => Fields::nullableNum($b, 'max_price_per_unit'),
            'required_from' => Fields::nullable($b, 'required_from'), 'required_until' => Fields::nullable($b, 'required_until'),
            'delivery_required' => isset($b['delivery_required']) ? (int) (bool) $b['delivery_required'] : 1,
            'latitude' => Fields::nullableNum($b, 'latitude'), 'longitude' => Fields::nullableNum($b, 'longitude'),
            'address' => Fields::nullable($b, 'address'),
        ];
        if (($d['latitude'] === null) !== ($d['longitude'] === null)) {
            $errors['latitude'][] = 'latitude and longitude must be given together';
        }
        if ($d['required_from'] && $d['required_until'] && $d['required_until'] < $d['required_from']) {
            $errors['required_until'][] = 'required_until must not be before required_from';
        }
        if (!$errors) {
            Fields::ensureExists('waste_types', $d['waste_type_id'], 'waste_type_id', $errors, 'AND active = 1');
            Fields::ensureExists('units', $d['unit_id'], 'unit_id', $errors);
            if ($d['organization_id'] !== null) {
                Fields::ensureExists('organizations', $d['organization_id'], 'organization_id', $errors, 'AND owner_user_id = ' . (int) $user['id']);
            }
        }
        if ($errors) {
            throw new ApiException('Validation failed', 422, $errors);
        }
        return $d;
    }

    private static function owned(array $user, string $id): array
    {
        $d = DemandRepository::find((int) $id);
        if (!$d || ((int) $d['buyer_user_id'] !== (int) $user['id'] && $user['role'] !== 'admin')) {
            throw new ApiException('Not found', 404);
        }
        return $d;
    }

    private static function list(?int $buyerId): never
    {
        $f = array_filter(['waste_type_id' => $_GET['waste_type_id'] ?? null, 'status' => $_GET['status'] ?? null], static fn($v) => is_string($v) && $v !== '');
        $errors = Validator::validate($f, ['waste_type_id' => 'int', 'status' => 'in:draft,active,partially_filled,fulfilled,expired,cancelled']);
        if ($errors) {
            throw new ApiException('Validation failed', 422, $errors);
        }
        [$rows, $total] = DemandRepository::list($buyerId, $f);
        [$per, , $page] = Fields::page();
        Response::ok(['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $per]);
    }

    /** Open demands: visible to logged-in suppliers/admins so buyers' details are not public. */
    public static function index(): void
    {
        Auth::require('farmer', 'collection_center', 'buyer', 'admin');
        self::list(null);
    }

    public static function mine(): void
    {
        self::list((int) Auth::require(...self::ROLES)['id']);
    }

    public static function show(string $id): void
    {
        $u = Auth::require('farmer', 'collection_center', 'buyer', 'admin');
        $d = DemandRepository::find((int) $id);
        $own = $d && ((int) $d['buyer_user_id'] === (int) $u['id'] || $u['role'] === 'admin');
        if (!$d || (!$own && !in_array($d['status'], ['active', 'partially_filled'], true))) {
            throw new ApiException('Not found', 404);
        }
        Response::ok($d);
    }

    public static function create(): void
    {
        $u = Auth::require(...self::ROLES);
        $b = Request::body();
        $d = self::data($b, $u);
        $id = DemandRepository::create((int) $u['id'], $d);
        if (!empty($b['publish'])) {
            DemandRepository::setStatus($id, 'active');
        }
        AuditRepository::log((int) $u['id'], 'CREATE_DEMAND', 'buyer_demand', $id, null, $d);
        Response::ok(['id' => $id], 'Demand created', 201);
    }

    public static function update(string $id): void
    {
        $u = Auth::require(...self::ROLES);
        $old = self::owned($u, $id);
        if (!in_array($old['status'], ['draft', 'active'], true)) {
            throw new ApiException('Only draft or active demands can be edited', 409);
        }
        $d = self::data(Request::body(), $u);
        $filled = (float) $old['required_quantity'] - (float) $old['remaining_quantity'];
        if ((float) $d['required_quantity'] < $filled) {
            throw new ApiException('Validation failed', 422, ['required_quantity' => ["cannot be below already filled amount ($filled)"]]);
        }
        DemandRepository::update((int) $id, $d);
        AuditRepository::log((int) $u['id'], 'UPDATE_DEMAND', 'buyer_demand', (int) $id, ['required_quantity' => $old['required_quantity']], $d);
        Response::ok(null, 'Demand updated');
    }

    public static function publish(string $id): void
    {
        $u = Auth::require(...self::ROLES);
        if (self::owned($u, $id)['status'] !== 'draft') {
            throw new ApiException('Only draft demands can be published', 409);
        }
        DemandRepository::setStatus((int) $id, 'active');
        Response::ok(null, 'Demand published');
    }

    public static function delete(string $id): void
    {
        $u = Auth::require(...self::ROLES);
        if (!in_array(self::owned($u, $id)['status'], ['draft', 'active'], true)) {
            throw new ApiException('Demand cannot be cancelled in its current state', 409);
        }
        DemandRepository::setStatus((int) $id, 'cancelled');
        AuditRepository::log((int) $u['id'], 'CANCEL_DEMAND', 'buyer_demand', (int) $id);
        Response::ok(null, 'Demand cancelled');
    }
}
