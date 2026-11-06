<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when the shared batch-code named lock cannot be acquired. This is an
 * infrastructure error, not a business-rule failure: the caller must show an
 * error and leave any pending submission Pending (never auto-reject it).
 */
class BatchCodeLockTimeoutException extends RuntimeException
{
    public static function forPrefix(string $prefix): self
    {
        return new self("The {$prefix} batch code is temporarily unavailable. Please try again.");
    }
}
