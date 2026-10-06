<?php

namespace Kkhay;

use Kkhay\Exception\KkhayApiException;

class Client
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;

    /**
     * @param string $apiKey Your secret merchant API key (e.g. 'kkhay_live_...')
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

    private function getUrl(string $path): string
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
    private function request(string $path, string $method = 'GET', ?array $data = null): array
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
            'User-Agent: kkhay-php/1.0.0',
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
     * Create a new crypto invoice.
     *
     * @param array $params Invoice parameters (priceAmount, payNetwork, payToken, etc.)
     * @return array
     */
    public function createInvoice(array $params): array
    {
        if (empty($params['priceAmount']) || floatval($params['priceAmount']) <= 0) {
            throw new \InvalidArgumentException("createInvoice: 'priceAmount' must be a positive number.");
        }
        if (empty($params['payNetwork'])) {
            throw new \InvalidArgumentException("createInvoice: 'payNetwork' is required (e.g. 'bsc', 'polygon').");
        }
        if (empty($params['payToken'])) {
            throw new \InvalidArgumentException("createInvoice: 'payToken' is required (e.g. 'USDT', 'USDC').");
        }

        return $this->request('/v1/merchant/invoices', 'POST', $params);
    }

    /**
     * Retrieve status and transaction details of an invoice.
     *
     * @param string $invoiceId The unique invoice ID
     * @return array
     */
    public function getInvoice(string $invoiceId): array
    {
        if (empty(trim($invoiceId))) {
            throw new \InvalidArgumentException("getInvoice: 'invoiceId' is required.");
        }
        return $this->request('/v1/merchant/invoices/' . rawurlencode(trim($invoiceId)), 'GET');
    }

    /**
     * List and paginate invoices.
     *
     * @param array $query Filter and pagination options (page, limit, status, search)
     * @return array
     */
    public function listInvoices(array $query = []): array
    {
        $queryString = !empty($query) ? '?' . http_build_query($query) : '';
        return $this->request('/v1/merchant/invoices' . $queryString, 'GET');
    }

    /**
     * Check gateway API health.
     *
     * @return array
     */
    public function checkHealth(): array
    {
        return $this->request('/health', 'GET');
    }
}

