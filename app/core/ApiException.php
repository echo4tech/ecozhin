<?php
declare(strict_types=1);

final class ApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 400, public readonly array $errors = [])
    {
        parent::__construct($message);
    }
}
