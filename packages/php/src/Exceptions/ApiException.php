<?php

namespace Avatarak\Exceptions;

/** The API answered with an error (or something that is not JSON). Base class of the more specific ones. */
class ApiException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0, public readonly ?array $body = null)
    {
        parent::__construct($message, $status);
    }
}
