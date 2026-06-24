<?php

namespace App\Exceptions;

use RuntimeException;

class WhatsAppWindowExpiredException extends RuntimeException
{
    public function __construct(string $phone)
    {
        parent::__construct("WhatsApp 24h window expired for {$phone}");
    }
}
