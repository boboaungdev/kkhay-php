<?php

namespace Kkhay;

/**
 * Webhook event types dispatched by K Khay Gateway Instant Payment Notifications (IPN).
 */
final class WebhookEventType
{
    public const PAYMENT_PAID = 'payment.paid';
    public const PAYMENT_FINISHED = 'payment.finished';
    public const PAYMENT_CONFIRMING = 'payment.confirming';
    public const PAYMENT_FAILED = 'payment.failed';
    public const INVOICE_CANCELED = 'invoice.canceled';

    private function __construct() {}
}

