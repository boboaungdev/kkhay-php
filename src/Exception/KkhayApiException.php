<?php

namespace Kkhay\Exception;

use Exception;

/**
 * Standard API exception thrown when K Khay endpoint returns an error.
 */
class KkhayApiException extends Exception
{
    protected int $status;
    protected ?string $errorCode;
    protected mixed $details;

    public function __construct(int $status, string $message, ?string $errorCode = null, mixed $details = null)
    {
        parent::__construct(sprintf("KkhayApiException [HTTP %d]: %s", $status, $message), $status);
        $this->status = $status;
        $this->errorCode = $errorCode;
        $this->details = $details;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getDetails(): mixed
    {
        return $this->details;
    }
}

