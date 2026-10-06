<?php

namespace Kkhay\Tests;

use PHPUnit\Framework\TestCase;
use Kkhay\Webhook;
use Kkhay\Exception\SignatureVerificationException;

class WebhookTest extends TestCase
{
    public function testVerifySignatureValid(): void
    {
        $secret = 'whsec_test_secret_123';
        $payload = json_encode([
            'event' => 'payment.finished',
            'invoice_id' => 'inv_123',
            'pay_amount' => '50.00',
            'pay_token' => 'USDT',
        ]);

        $signature = hash_hmac('sha256', $payload, $secret);

        $this->assertTrue(Webhook::verifySignature($payload, $signature, $secret));
    }

    public function testVerifySignatureTampered(): void
    {
        $secret = 'whsec_test_secret_123';
        $payload = json_encode(['event' => 'payment.finished']);
        $signature = hash_hmac('sha256', $payload, $secret);

        $this->assertFalse(Webhook::verifySignature($payload . 'tampered', $signature, $secret));
        $this->assertFalse(Webhook::verifySignature($payload, $signature, 'wrong_secret'));
        $this->assertFalse(Webhook::verifySignature($payload, null, $secret));
    }

    public function testParseEvent(): void
    {
        $secret = 'whsec_secret';
        $payload = json_encode([
            'event' => 'payment.finished',
            'invoice_id' => 'inv_abc',
            'order_id' => 'ORD-1',
        ]);
        $signature = hash_hmac('sha256', $payload, $secret);

        $event = Webhook::parseEvent($payload, $signature, $secret);
        $this->assertEquals('payment.finished', $event['event']);
        $this->assertEquals('inv_abc', $event['invoice_id']);

        $this->expectException(SignatureVerificationException::class);
        Webhook::parseEvent($payload, 'invalid_signature', $secret);
    }
}

