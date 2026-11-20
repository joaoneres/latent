<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class ClientException extends Exception
{
    public function __construct(string $message, Throwable $previous)
    {
        parent::__construct($message, previous: $previous);
    }
}
