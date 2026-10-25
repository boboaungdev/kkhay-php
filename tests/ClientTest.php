<?php

namespace Kkhay\Tests;

use PHPUnit\Framework\TestCase;
use Kkhay\Client;
use Kkhay\InvoiceStatus;
use Kkhay\InvoiceType;
use Kkhay\WebhookEventType;
use Kkhay\Exception\KkhayApiException;
use InvalidArgumentException;

/**
 * Mock client that intercepts request() for unit testing without live network calls.
 */
class TestableClient extends Client
{
    public ?string $lastPath = null;
    public ?string $lastMethod = null;
    public ?array $lastData = null;
    public array $mockResponse = [];

    protected function request(string $path, string $method = 'GET', ?array $data = null): array
    {
        $this->lastPath = $path;
        $this->lastMethod = $method;
        $this->lastData = $data;
        return $this->mockResponse;
    }
}

class ClientTest extends TestCase
{
    public function testConstructValid(): void
    {
        $client = new Client('kkhay_live_test_key_123');
        $this->assertInstanceOf(Client::class, $client);
        $this->assertEquals('kkhay_live_test_key_123', $client->getApiKey());
        $this->assertEquals('https://api.kkhay.com', $client->getBaseUrl());
        $this->assertEquals(30, $client->getTimeout());
    }

    public function testConstructCustomOptions(): void
    {
        $client = new Client('kkhay_live_key', 'https://gateway.example.com/', 45);
        $this->assertEquals('https://gateway.example.com', $client->getBaseUrl());
        $this->assertEquals(45, $client->getTimeout());
    }

    public function testConstructEmptyKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Client('   ');
    }

    public function testGetUrlFormatting(): void
    {
        $apiClient = new Client('key', 'https://api.kkhay.com');
        $this->assertEquals('https://api.kkhay.com/v1/gateway/invoices', $apiClient->getUrl('/v1/gateway/invoices'));
        $this->assertEquals('https://api.kkhay.com/v1/gateway/invoices', $apiClient->getUrl('v1/gateway/invoices'));

        $customClient = new Client('key', 'https://gateway.internal');
        $this->assertEquals('https://gateway.internal/api/v1/gateway/invoices', $customClient->getUrl('/v1/gateway/invoices'));
    }

    public function testVersionConstant(): void
    {
        $this->assertEquals('1.0.4', Client::VERSION);
    }

    public function testInvoiceStatusConstants(): void
    {
        $this->assertEquals('WAITING', InvoiceStatus::WAITING);
        $this->assertEquals('PAID', InvoiceStatus::PAID);
        $this->assertEquals('EXPIRED', InvoiceStatus::EXPIRED);
        $this->assertEquals('CANCELED', InvoiceStatus::CANCELED);
    }

    public function testInvoiceTypeConstants(): void
    {
        $this->assertEquals('PAYMENT', InvoiceType::PAYMENT);
        $this->assertEquals('DONATION', InvoiceType::DONATION);
        $this->assertEquals('SUBSCRIPTION', InvoiceType::SUBSCRIPTION);
    }

    public function testWebhookEventTypeConstants(): void
    {
        $this->assertEquals('payment.paid', WebhookEventType::PAYMENT_PAID);
        $this->assertEquals('payment.finished', WebhookEventType::PAYMENT_FINISHED);
        $this->assertEquals('payment.confirming', WebhookEventType::PAYMENT_CONFIRMING);
        $this->assertEquals('payment.failed', WebhookEventType::PAYMENT_FAILED);
        $this->assertEquals('invoice.canceled', WebhookEventType::INVOICE_CANCELED);
    }

    public function testCreateInvoiceValidationMissingAmount(): void
    {
        $client = new Client('key');
        $this->expectException(InvalidArgumentException::class);
        $client->createInvoice([
            'payNetwork' => 'bsc',
            'payToken' => 'USDT',
        ]);
    }

    public function testCreateInvoiceValidationZeroAmount(): void
    {
        $client = new Client('key');
        $this->expectException(InvalidArgumentException::class);
        $client->createInvoice([
            'amount' => 0,
            'payNetwork' => 'bsc',
            'payToken' => 'USDT',
        ]);
    }

    public function testCreateInvoiceValidationMissingNetwork(): void
    {
        $client = new Client('key');
        $this->expectException(InvalidArgumentException::class);
        $client->createInvoice([
            'amount' => 100,
            'payToken' => 'USDT',
        ]);
    }

    public function testCreateInvoiceValidationMissingToken(): void
    {
        $client = new Client('key');
        $this->expectException(InvalidArgumentException::class);
        $client->createInvoice([
            'amount' => 100,
            'payNetwork' => 'polygon',
        ]);
    }

    public function testGetInvoiceValidationEmptyId(): void
    {
        $client = new Client('key');
        $this->expectException(InvalidArgumentException::class);
        $client->getInvoice('');
    }

    public function testCreateInvoiceNormalization(): void
    {
        $client = new TestableClient('test_key');
        $client->mockResponse = [
            'invoice' => [
                'id' => '0192e2b3-5776-791b-8524-77a834164b12',
                'status' => 'WAITING',
                'hostedUrl' => 'https://kkhay.com/gateway/invoice/0192e2b3-5776-791b-8524-77a834164b12',
            ],
        ];

        $res = $client->createInvoice([
            'amount' => '49.99',
            'payNetwork' => 'BSC ',
            'payToken' => 'usdt ',
            'orderId' => 'ORD-101',
        ]);

        $this->assertEquals('/v1/gateway/invoices', $client->lastPath);
        $this->assertEquals('POST', $client->lastMethod);
        $this->assertEquals(49.99, $client->lastData['amount']);
        $this->assertEquals(49.99, $client->lastData['priceAmount']);
        $this->assertEquals('USD', $client->lastData['currency']);
        $this->assertEquals('USD', $client->lastData['priceCurrency']);
        $this->assertEquals('bsc', $client->lastData['payNetwork']);
        $this->assertEquals('USDT', $client->lastData['payToken']);
        $this->assertEquals('PAYMENT', $client->lastData['type']);
        $this->assertEquals('ORD-101', $client->lastData['orderId']);
        $this->assertEquals('0192e2b3-5776-791b-8524-77a834164b12', $res['invoice']['id']);
    }

    public function testCreateInvoiceWithPriceAmountAndCustomCurrency(): void
    {
        $client = new TestableClient('test_key');
        $client->mockResponse = ['ok' => true];

        $client->createInvoice([
            'priceAmount' => 150.50,
            'priceCurrency' => 'eur',
            'payNetwork' => 'solana',
            'payToken' => 'usdc',
            'type' => InvoiceType::SUBSCRIPTION,
        ]);

        $this->assertEquals(150.50, $client->lastData['amount']);
        $this->assertEquals(150.50, $client->lastData['priceAmount']);
        $this->assertEquals('EUR', $client->lastData['currency']);
        $this->assertEquals('EUR', $client->lastData['priceCurrency']);
        $this->assertEquals('solana', $client->lastData['payNetwork']);
        $this->assertEquals('USDC', $client->lastData['payToken']);
        $this->assertEquals('SUBSCRIPTION', $client->lastData['type']);
    }

    public function testGetInvoiceExecution(): void
    {
        $client = new TestableClient('test_key');
        $client->mockResponse = [
            'invoice' => ['id' => 'inv_test_999', 'status' => 'PAID'],
            'payments' => [],
        ];

        $res = $client->getInvoice('inv_test_999');

        $this->assertEquals('/v1/gateway/invoices/inv_test_999', $client->lastPath);
        $this->assertEquals('GET', $client->lastMethod);
        $this->assertEquals('PAID', $res['invoice']['status']);
    }

    public function testListInvoicesQueryString(): void
    {
        $client = new TestableClient('test_key');
        $client->mockResponse = ['items' => []];

        $client->listInvoices(['limit' => 10, 'status' => 'PAID']);

        $this->assertEquals('/v1/gateway/invoices?limit=10&status=PAID', $client->lastPath);
        $this->assertEquals('GET', $client->lastMethod);
    }

    public function testCheckHealth(): void
    {
        $client = new TestableClient('test_key');
        $client->mockResponse = ['status' => 'ok'];

        $res = $client->checkHealth();

        $this->assertEquals('/health', $client->lastPath);
        $this->assertEquals('GET', $client->lastMethod);
        $this->assertEquals('ok', $res['status']);
    }

    public function testKkhayApiException(): void
    {
        $ex = new KkhayApiException(404, 'Invoice not found', 'INVOICE_NOT_FOUND', ['id' => '123']);
        $this->assertEquals(404, $ex->getStatus());
        $this->assertEquals('INVOICE_NOT_FOUND', $ex->getErrorCode());
        $this->assertEquals(['id' => '123'], $ex->getDetails());
        $this->assertStringContainsString('KkhayApiException [HTTP 404]: Invoice not found', $ex->getMessage());
    }
}
