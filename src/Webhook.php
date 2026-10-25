<?php

namespace Kkhay;

use Kkhay\Exception\SignatureVerificationException;

class Webhook
{
    /**
     * Verifies the cryptographic HMAC-SHA256 signature sent with incoming K Khay IPN webhooks.
     *
     * @param string|array $payload Raw JSON body string or decoded associative array
     * @param string|null $signature The signature header value ('x-kkhay-signature')
     * @param string $ipnSecret Your gateway IPN Secret Key
     * @return bool True if authentic and untampered
     */
    public static function verifySignature(string|array $payload, ?string $signature, string $ipnSecret): bool
    {
        if (empty($signature) || empty($ipnSecret)) {
            return false;
        }

        $rawBody = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_SLASHES);
        $expectedSignature = hash_hmac('sha256', $rawBody, $ipnSecret);

        return hash_equals($expectedSignature, trim($signature));
    }

    /**
     * Validates and parses an incoming K Khay webhook payload.
     *
     * @param string $rawBody The raw HTTP request body string
     * @param string|null $signature The 'x-kkhay-signature' header
     * @param string $ipnSecret Your gateway IPN Secret Key
     * @return array Decoded webhook event payload
     * @throws SignatureVerificationException If signature does not match
     */
    public static function parseEvent(string $rawBody, ?string $signature, string $ipnSecret): array
    {
        if (!self::verifySignature($rawBody, $signature, $ipnSecret)) {
            throw new SignatureVerificationException("Invalid K Khay webhook signature: Request rejected.");
        }

        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException("Failed to decode webhook payload as valid JSON.");
        }

        return $data;
    }
}

