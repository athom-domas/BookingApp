<?php

namespace App\Exceptions;

class RescheduleConflictException extends \RuntimeException
{
    public const FORBIDDEN     = 'forbidden';
    public const WRONG_STATUS  = 'wrong_status';
    public const OUTSIDE_HOURS = 'outside_hours';
    public const CONFLICT      = 'conflict';

    public function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }
}
