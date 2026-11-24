<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A third-party API (Open-Meteo, OpenAI) failed. The message is safe to show
 * to the user; $status is the HTTP status we answer the browser with.
 */
class UpstreamException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 502)
    {
        parent::__construct($message);
    }
}
