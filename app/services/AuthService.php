<?php
declare(strict_types=1);

final class AuthService
{
    public const SELF_REGISTER_ROLES = ['farmer', 'buyer', 'transport', 'collection_center'];

    private const ORG_TYPE = ['buyer' => 'buyer_company', 'transport' => 'transport_company', 'collection_center' => 'collection_center'];

    public static function register(array $in): array
    {
        $errors = Validator::validate($in, [
            'full_name' => 'required|string|min:2|max:150',
            'phone' => 'required|phone',
            'email' => 'email|max:190',
            'password' => 'required|string|min:8|max:200',
            'role' => 'required|in:' . implode(',', self::SELF_REGISTER_ROLES),
            'preferred_language' => 'in:ku,ar,en',
            'organization_name' => 'string|max:190',
        ]);
        if ($errors) {
            throw new ApiException('Validation failed', 422, $errors);
        }

        $phone = (string) $in['phone'];
        $email = ($in['email'] ?? '') !== '' ? strtolower((string) $in['email']) : null;
        if (UserRepository::exists($phone, $email)) {
            throw new ApiException('Validation failed', 422, ['phone' => ['Phone or email is already registered']]);
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $userId = UserRepository::create([
                'role_id' => UserRepository::roleId($in['role']),
                'full_name' => trim((string) $in['full_name']),
                'phone' => $phone,
                'email' => $email,
                'password_hash' => password_hash((string) $in['password'], PASSWORD_DEFAULT),
                'preferred_language' => $in['preferred_language'] ?? 'ku',
            ]);
            if ($in['role'] === 'farmer') {
                Database::query('INSERT INTO farmer_profiles (user_id) VALUES (?)', [$userId]);
            } elseif (!empty($in['organization_name'])) {
                Database::query(
                    'INSERT INTO organizations (owner_user_id, organization_type, name, phone, email) VALUES (?,?,?,?,?)',
                    [$userId, self::ORG_TYPE[$in['role']], trim((string) $in['organization_name']), $phone, $email]
                );
            }
            Database::query('INSERT INTO notification_preferences (user_id) VALUES (?)', [$userId]);
            AuditRepository::log($userId, 'REGISTER', 'user', $userId, null, ['role' => $in['role']]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        Auth::login($userId);
        return UserRepository::publicView(UserRepository::find($userId));
    }

    public static function login(string $login, string $password): array
    {
        if (!RateLimiter::hit('login:' . Request::ip() . ':' . strtolower($login), 5, 900)) {
            throw new ApiException('Too many login attempts, try again later', 429);
        }
        $user = UserRepository::findByLogin($login);
        // Constant-ish time: always run a verify.
        $hash = $user['password_hash'] ?? '$2y$10$Fbmmf5MhkyC3m4lX13eOeuA1rwIff7bnCr616Nbi1k/RG6YFHGTzy';
        $valid = password_verify($password, $hash);
        if (!$user || !$valid) {
            throw new ApiException('Invalid credentials', 401);
        }
        if ($user['status'] !== 'active') {
            throw new ApiException('Account is ' . $user['status'], 403);
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        Auth::login((int) $user['id']);
        UserRepository::touchLogin((int) $user['id']);
        AuditRepository::log((int) $user['id'], 'LOGIN', 'user', (int) $user['id']);
        return UserRepository::publicView($user);
    }
}
