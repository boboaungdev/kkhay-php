<?php

namespace Kkhay\Exception;

use Exception;

/**
 * Thrown when an incoming IPN webhook payload fails HMAC signature verification.
 */
class SignatureVerificationException extends Exception
{
}

