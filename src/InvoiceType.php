<?php

namespace Kkhay;

/**
 * Invoice type constants matching the PostgreSQL GatewayInvoiceType enum.
 */
final class InvoiceType
{
    public const PAYMENT = 'PAYMENT';
    public const DONATION = 'DONATION';
    public const SUBSCRIPTION = 'SUBSCRIPTION';

    private function __construct() {}
}

