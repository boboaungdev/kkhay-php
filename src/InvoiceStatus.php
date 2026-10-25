<?php

namespace Kkhay;

/**
 * Invoice status constants matching the PostgreSQL GatewayInvoiceStatus enum.
 */
final class InvoiceStatus
{
    public const WAITING = 'WAITING';
    public const PAID = 'PAID';
    public const EXPIRED = 'EXPIRED';
    public const CANCELED = 'CANCELED';

    private function __construct() {}
}

