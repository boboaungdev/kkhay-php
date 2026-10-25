<?php

namespace Kkhay;

use Kkhay\Exception\KkhayApiException;

class Client
{
    public const VERSION = '1.0.4';

    private string $apiKey;
    private string $baseUrl;
    private int $timeout;

    /**
     * @param string $apiKey Your secret gateway API key (e.g. 'kkhay_live_...')
     * @param string $baseUrl Base API URL, defaults to 'https://api.kkhay.com'
     * @param int $timeout Request timeout in seconds (default 30)
     */
    public function __construct(string $apiKey, string $baseUrl = 'https://api.kkhay.com', int $timeout = 30)
    {
        $cleanKey = trim($apiKey);
        if (empty($cleanKey)) {
            throw new \InvalidArgumentException("Kkhay\\Client: A valid 'apiKey' string is required.");
        }

        $this->apiKey = $cleanKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getUrl(string $path): string
    {
        $cleanPath = str_starts_with($path, '/') ? $path : '/' . $path;
        if (str_ends_with($this->baseUrl, '/api') || str_contains($this->baseUrl, 'api.')) {
            return $this->baseUrl . $cleanPath;
        }
        return $this->baseUrl . '/api' . $cleanPath;
    }

    /**
     * Internal HTTP executor via cURL
     *
     * @throws KkhayApiException
     */
    protected function request(string $path, string $method = 'GET', ?array $data = null): array
    {
        $url = $this->getUrl($path);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        $headers = [
            'Accept: application/json',
            'x-api-key: ' . $this->apiKey,
            'User-Agent: kkhay-php/' . self::VERSION,
        ];

        if ($data !== null) {
            $jsonData = json_encode($data);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new KkhayApiException(500, "Network error: " . $curlError);
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $message = "Request failed with status " . $httpCode;
            $code = null;
            $details = null;

            if (is_array($decoded)) {
                $message = $decoded['message'] ?? $decoded['error'] ?? $message;
                $code = $decoded['code'] ?? null;
                $details = $decoded['details'] ?? null;
            } elseif (!empty($response)) {
                $message = $response;
            }

            throw new KkhayApiException($httpCode, $message, $code, $details);
        }

        return is_array($decoded) ? $decoded : ['data' => $response];
    }

    /**
     * Create a new sovereign crypto payment invoice.
     *
     * @param array $params Invoice parameters (amount, payNetwork, payToken, etc.)
     * @return array
     * @throws \InvalidArgumentException|KkhayApiException
     */
    public function createInvoice(array $params): array
    {
        $amount = $params['amount'] ?? $params['priceAmount'] ?? null;
        if ($amount === null || floatval($amount) <= 0) {
            throw new \InvalidArgumentException("createInvoice: 'amount' (or 'priceAmount') must be a positive number.");
        }

        if (empty($params['payNetwork'])) {
            throw new \InvalidArgumentException("createInvoice: 'payNetwork' is required (e.g. 'bsc', 'polygon', 'solana', 'bitcoin').");
        }

        if (empty($params['payToken'])) {
            throw new \InvalidArgumentException("createInvoice: 'payToken' is required (e.g. 'USDT', 'USDC', 'BTC', 'ETH', 'SOL').");
        }

        $currency = $params['currency'] ?? $params['priceCurrency'] ?? 'USD';

        $payload = array_merge($params, [
            'amount' => floatval($amount),
            'priceAmount' => floatval($amount),
            'currency' => strtoupper(trim($currency)),
            'priceCurrency' => strtoupper(trim($currency)),
            'payNetwork' => strtolower(trim($params['payNetwork'])),
            'payToken' => strtoupper(trim($params['payToken'])),
            'type' => $params['type'] ?? InvoiceType::PAYMENT,
        ]);

        return $this->request('/v1/gateway/invoices', 'POST', $payload);
    }

    /**
     * Retrieve status, transaction hashes, and details of an invoice by ID.
     *
     * @param string $invoiceId The unique invoice UUID
     * @return array
     * @throws \InvalidArgumentException|KkhayApiException
     */
    public function getInvoice(string $invoiceId): array
    {
        $cleanId = trim($invoiceId);
        if (empty($cleanId)) {
            throw new \InvalidArgumentException("getInvoice: 'invoiceId' is required.");
        }
        return $this->request('/v1/gateway/invoices/' . rawurlencode($cleanId), 'GET');
    }

    /**
     * List and paginate gateway invoices with optional filtering.
     *
     * @param array $query Filter and pagination options (page, limit, status, type, search)
     * @return array
     * @throws KkhayApiException
     */
    public function listInvoices(array $query = []): array
    {
        $queryString = !empty($query) ? '?' . http_build_query($query) : '';
        return $this->request('/v1/gateway/invoices' . $queryString, 'GET');
    }

    /**
     * Check gateway API health status.
     *
     * @return array
     * @throws KkhayApiException
     */
    public function checkHealth(): array
    {
        return $this->request('/health', 'GET');
    }
}
