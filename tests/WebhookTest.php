<?php

namespace Kkhay\Tests;

use PHPUnit\Framework\TestCase;
use Kkhay\Webhook;
use Kkhay\WebhookEventType;
use Kkhay\Exception\SignatureVerificationException;
use InvalidArgumentException;

class WebhookTest extends TestCase
{
    public function testVerifySignatureValid(): void
    {
        $secret = 'whsec_test_secret_123';
        $payload = json_encode([
            'event' => WebhookEventType::PAYMENT_PAID,
            'gateway_id' => 'gw_live_123',
            'invoice_id' => '0192e2b3-5776-791b-8524-77a834164b12',
            'amount' => 50.00,
            'currency' => 'USD',
            'pay_network' => 'bsc',
            'pay_token' => 'USDT',
        ]);

        $signature = hash_hmac('sha256', $payload, $secret);

        $this->assertTrue(Webhook::verifySignature($payload, $signature, $secret));
        // Verify with surrounding whitespace
        $this->assertTrue(Webhook::verifySignature($payload, "  " . $signature . "  \n", $secret));
    }

    public function testVerifySignatureWithArrayPayload(): void
    {
        $secret = 'whsec_test_secret_123';
        $data = [
            'event' => WebhookEventType::PAYMENT_FINISHED,
            'invoice_id' => 'inv_abc',
        ];
        $payloadString = json_encode($data, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $payloadString, $secret);

        $this->assertTrue(Webhook::verifySignature($data, $signature, $secret));
    }

    public function testVerifySignatureTampered(): void
    {
        $secret = 'whsec_test_secret_123';
        $payload = json_encode(['event' => 'payment.paid']);
        $signature = hash_hmac('sha256', $payload, $secret);

        $this->assertFalse(Webhook::verifySignature($payload . 'tampered', $signature, $secret));
        $this->assertFalse(Webhook::verifySignature($payload, $signature, 'wrong_secret'));
        $this->assertFalse(Webhook::verifySignature($payload, null, $secret));
        $this->assertFalse(Webhook::verifySignature($payload, '', $secret));
        $this->assertFalse(Webhook::verifySignature($payload, $signature, ''));
    }

    public function testParseEventSuccess(): void
    {
        $secret = 'whsec_secret_456';
        $payload = json_encode([
            'event' => WebhookEventType::PAYMENT_PAID,
            'gateway_id' => 'gw_prod_999',
            'invoice_id' => 'inv_0192e2b3',
            'order_id' => 'ORD-5544',
            'amount' => 120.00,
            'currency' => 'USD',
            'pay_network' => 'solana',
            'pay_token' => 'USDC',
            'tx_hash' => '5eykt4UsFv8P8NJdTREpY1vzqKqZKvdpKuc147dw2N9d',
        ]);
        $signature = hash_hmac('sha256', $payload, $secret);

        $event = Webhook::parseEvent($payload, $signature, $secret);
        $this->assertEquals(WebhookEventType::PAYMENT_PAID, $event['event']);
        $this->assertEquals('gw_prod_999', $event['gateway_id']);
        $this->assertEquals('inv_0192e2b3', $event['invoice_id']);
        $this->assertEquals('ORD-5544', $event['order_id']);
        $this->assertEquals(120.00, $event['amount']);
        $this->assertEquals('solana', $event['pay_network']);
        $this->assertEquals('USDC', $event['pay_token']);
    }

    public function testParseEventThrowsOnInvalidSignature(): void
    {
        $secret = 'whsec_secret';
        $payload = json_encode(['event' => 'payment.paid']);

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('Invalid K Khay webhook signature: Request rejected.');

        Webhook::parseEvent($payload, 'invalid_signature_hex', $secret);
    }

    public function testParseEventThrowsOnInvalidJson(): void
    {
        $secret = 'whsec_secret';
        $payload = 'this is not json';
        $signature = hash_hmac('sha256', $payload, $secret);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to decode webhook payload as valid JSON.');

        Webhook::parseEvent($payload, $signature, $secret);
    }
}
