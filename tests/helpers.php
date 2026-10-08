<?php
declare(strict_types=1);
// Shared by the API tests.
$fail = 0;

function client(string $base): Closure
{
    $jar = tempnam(sys_get_temp_dir(), 'jar');
    $csrf = null;
    $call = function (string $method, string $path, ?array $body = null, bool $sendCsrf = true) use ($base, $jar, &$csrf): array {
        $do = function () use ($method, $path, $body, $base, $jar, &$csrf, $sendCsrf) {
            $ch = curl_init($base . '/api' . $path);
            $h = ($body && array_filter($body, fn($v) => $v instanceof CURLFile)) ? [] : ['Content-Type: application/json'];
            if ($csrf && $sendCsrf) {
                $h[] = "X-CSRF-Token: $csrf";
            }
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $h,
                CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_POSTFIELDS => $body === null ? null : (array_filter($body, fn($v) => $v instanceof CURLFile) ? $body : json_encode($body))]);
            $raw = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            return [$code, json_decode((string) $raw, true) ?? ['raw' => $raw]];
        };
        return $do();
    };
    $refresh = function () use ($call, &$csrf) { $csrf = $call('GET', '/auth/csrf')[1]['data']['token']; };
    $refresh();
    return function (string $method, string $path, ?array $body = null, bool $sendCsrf = true) use ($call, $refresh, &$csrf) {
        $r = $call($method, $path, $body, $sendCsrf);
        if (in_array($path, ['/auth/login', '/auth/register', '/auth/logout'], true)) {
            $refresh(); // session id regenerated on login -> new csrf
        }
        return $r;
    };
}

function check(string $name, bool $cond, mixed $detail = null): void
{
    global $fail;
    echo ($cond ? 'PASS' : 'FAIL') . "  $name\n";
    if (!$cond) { $fail++; echo '      ' . json_encode($detail, JSON_UNESCAPED_UNICODE) . "\n"; }
}

