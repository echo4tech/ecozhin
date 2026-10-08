<?php
// Page guard: require login (optionally a role) or redirect to /login.php.
function requirePageAuth(string ...$roles): array
{
    $user = Auth::user();
    if (!$user || $user['status'] !== 'active' || ($roles && !in_array($user['role'], $roles, true))) {
        header('Location: ' . url('/login.php'));
        exit;
    }
    return $user;
}
