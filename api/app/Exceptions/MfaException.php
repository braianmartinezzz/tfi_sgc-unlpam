<?php

namespace App\Exceptions;

use RuntimeException;

/** Error esperable del flujo MFA: el controlador lo convierte en una respuesta JSON. */
class MfaException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 401)
    {
        parent::__construct($message);
    }
}
