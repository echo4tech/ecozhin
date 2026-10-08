<?php
declare(strict_types=1);

/** Rules: required|string|numeric|int|email|min:N|max:N|in:a,b|gt:N|date|nullable */
final class Validator
{
    public static function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleStr) {
            $rs = explode('|', $ruleStr);
            $value = $data[$field] ?? null;
            $empty = $value === null || $value === '';
            if ($empty) {
                if (in_array('required', $rs, true)) {
                    $errors[$field][] = "$field is required";
                }
                continue;
            }
            foreach ($rs as $r) {
                [$name, $arg] = array_pad(explode(':', $r, 2), 2, null);
                $err = match ($name) {
                    'string' => is_string($value) ? null : "$field must be text",
                    'numeric' => is_numeric($value) ? null : "$field must be a number",
                    'int' => filter_var($value, FILTER_VALIDATE_INT) !== false ? null : "$field must be an integer",
                    'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "$field must be a valid email",
                    'min' => self::size($value) >= (float) $arg ? null : "$field must be at least $arg",
                    'max' => self::size($value) <= (float) $arg ? null : "$field must be at most $arg",
                    'gt' => is_numeric($value) && (float) $value > (float) $arg ? null : "$field must be greater than $arg",
                    'in' => in_array((string) $value, explode(',', (string) $arg), true) ? null : "$field is invalid",
                    'date' => strtotime((string) $value) !== false && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) ? null : "$field must be a date (YYYY-MM-DD)",
                    'phone' => preg_match('/^\+?[0-9]{7,15}$/', (string) $value) ? null : "$field must be a valid phone number",
                    default => null,
                };
                if ($err) {
                    $errors[$field][] = $err;
                }
            }
        }
        return $errors;
    }

    private static function size(mixed $v): float
    {
        return is_numeric($v) ? (float) $v : (float) mb_strlen((string) $v);
    }
}
