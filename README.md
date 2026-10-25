<div align="center">

# `kkhay-php`

**Official PHP SDK for K Khay — The Non-Custodial Crypto Payment Gateway**

[![Packagist Version](https://img.shields.io/packagist/v/kkhay/kkhay.svg?style=flat-square)](https://packagist.org/packages/kkhay/kkhay)
[![CI](https://github.com/boboaungdev/kkhay-php/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/boboaungdev/kkhay-php/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg?style=flat-square)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](LICENSE)

Accept cryptocurrency payments across **190+ blockchain networks** and **500+ tokens** (EVM, Bitcoin, Solana, TON, Tron, and XRPL) with **zero custody** and automatic direct-to-wallet settlement. Works seamlessly with **Laravel, Symfony, WordPress / WooCommerce, Drupal**, or pure PHP apps.

[Gateway Dashboard](https://gateway.kkhay.com) • [API Keys](https://gateway.kkhay.com/dashboard/keys) • [Website](https://kkhay.com)

</div>

---

## ⚡ Key Highlights

- 🛡️ **100% Non-Custodial**: Payments settle directly to your destination blockchain address in seconds. K Khay never holds your private keys or customer funds.
- 🌐 **190+ Networks Supported**: 
  - **EVM Networks**: BNB Smart Chain (`bsc`), Polygon (`polygon`), Arbitrum (`arbitrum`), Base (`base`), Ethereum (`eth`), Optimism (`optimism`), Avalanche (`avalanche`), and 180+ more.
  - **UTXO Networks**: Bitcoin (`btc`), Dogecoin (`doge`), Litecoin (`ltc`), Bitcoin Cash (`bch`).
  - **High-Throughput Chains**: Solana (`sol`), TON (`ton`), Tron (`trx`), XRP Ledger (`xrp`).
- 🪙 **500+ Cryptocurrencies**: Native coins and tokens (USDT, USDC, BTC, ETH, SOL, TON, BNB, DOGE, XRP, TRX, LTC, and more).
- 🔗 **Branded Hosted Checkouts**: Instant hosted checkout URLs with dynamic QR codes, live blockchain confirmation polling, and deep links.
- 🔔 **Cryptographic Webhooks**: Timing-safe HMAC-SHA256 signature verification (`x-kkhay-signature` via `hash_equals()`).
- 🪶 **Zero External Dependencies**: Pure PHP 8.1+ standard library implementation (`ext-curl`, `ext-json`, `ext-hash`).

---

## 📦 Installation

Install via [Composer](https://packagist.org/packages/kkhay/kkhay):

```bash
composer require kkhay/kkhay
```

*(Requires PHP 8.1 or newer)*

---

## 🚀 Quickstart

### 1. Initialize the Client

Obtain your API Key from the [K Khay Gateway Dashboard](https://gateway.kkhay.com/dashboard/keys).

```php
use Kkhay\Client;

$client = new Client('kkhay_live_your_api_key_here');
```

You can optionally configure a custom base URL and request timeout:

```php
$client = new Client(
    apiKey: 'kkhay_live_your_api_key_here',
    baseUrl: 'https://api.kkhay.com',
    timeout: 30
);
```

---

### 2. Create a Crypto Payment Invoice

```php
use Kkhay\Client;
use Kkhay\InvoiceType;

$client = new Client('kkhay_live_your_api_key_here');

$response = $client->createInvoice([
    'amount'        => 49.99,
    'currency'      => 'USD',
    'payNetwork'    => 'bsc',       // or 'polygon', 'solana', 'bitcoin', 'tron', etc.
    'payToken'      => 'USDT',      // or 'USDC', 'BTC', 'ETH', 'SOL', 'TON', etc.
    'orderId'       => 'ORDER-8821',
    'title'         => 'Pro Subscription',
    'customerEmail' => 'customer@example.com',
    'redirectUrl'   => 'https://myshop.com/success',
    'cancelUrl'     => 'https://myshop.com/cancel',
    'type'          => InvoiceType::PAYMENT,
]);

$invoice = $response['invoice'];

echo "Invoice ID: " . $invoice['id'] . "\n";
echo "Pay Address: " . $invoice['payAddress'] . "\n";
echo "Checkout URL: " . $invoice['hostedUrl'] . "\n";
```

---

### 3. Query Invoice Status & Confirmations

```php
$response = $client->getInvoice('0192e2b3-5776-791b-8524-77a834164b12');
$invoice = $response['invoice'];
$payments = $response['payments'];

echo "Status: " . $invoice['status'] . "\n"; // WAITING, PAID, EXPIRED, CANCELED

foreach ($payments as $payment) {
    echo "Received: " . $payment['amountReceived'] . " on tx " . $payment['txHash'] . "\n";
    echo "Confirmations: " . $payment['confirmations'] . "\n";
}
```

---

### 4. List & Paginate Invoices

```php
use Kkhay\InvoiceStatus;

$result = $client->listInvoices([
    'page'   => 1,
    'limit'  => 20,
    'status' => InvoiceStatus::PAID,
]);

foreach ($result['items'] as $inv) {
    echo $inv['id'] . " - " . $inv['amount'] . " " . $inv['currency'] . "\n";
}
```

---

### 5. Check API Health

```php
$health = $client->checkHealth();
echo "Status: " . $health['status'] . "\n";
```

---

## 🔐 Webhook / IPN Verification

K Khay signs every Instant Payment Notification with an HMAC-SHA256 signature passed in the `x-kkhay-signature` HTTP header. Always verify the signature using your **IPN Secret Key** before processing orders.

### Pure PHP Webhook Handler

```php
use Kkhay\Webhook;
use Kkhay\WebhookEventType;
use Kkhay\Exception\SignatureVerificationException;

$rawBody   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_KKHAY_SIGNATURE'] ?? null;
$ipnSecret = getenv('KKHAY_IPN_SECRET');

try {
    $event = Webhook::parseEvent($rawBody, $signature, $ipnSecret);

    if ($event['event'] === WebhookEventType::PAYMENT_PAID || $event['event'] === WebhookEventType::PAYMENT_FINISHED) {
        $orderId = $event['order_id'];
        $amount  = $event['amount'];
        $txHash  = $event['tx_hash'];

        // Fulfill order in your database
    }

    http_response_code(200);
    echo json_encode(['ok' => true]);
} catch (SignatureVerificationException $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid webhook signature']);
}
```

---

### Laravel Controller

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Kkhay\Webhook;
use Kkhay\WebhookEventType;
use Kkhay\Exception\SignatureVerificationException;

class KkhayWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $rawPayload = $request->getContent();
        $signature  = $request->header('x-kkhay-signature');
        $ipnSecret  = config('services.kkhay.ipn_secret');

        try {
            $event = Webhook::parseEvent($rawPayload, $signature, $ipnSecret);

            switch ($event['event']) {
                case WebhookEventType::PAYMENT_PAID:
                case WebhookEventType::PAYMENT_FINISHED:
                    // Fulfill order
                    break;
                case WebhookEventType::PAYMENT_FAILED:
                case WebhookEventType::INVOICE_CANCELED:
                    // Handle cancellation
                    break;
            }

            return response()->json(['ok' => true]);
        } catch (SignatureVerificationException $e) {
            return response()->json(['error' => 'Unauthorized signature'], 401);
        }
    }
}
```

---

## 📚 Constants & Enums

### Invoice Status (`Kkhay\InvoiceStatus`)

Matches PostgreSQL schema enum values:

| Constant | Value | Description |
| :--- | :--- | :--- |
| `InvoiceStatus::WAITING` | `'WAITING'` | Awaiting customer payment on blockchain |
| `InvoiceStatus::PAID` | `'PAID'` | Transaction confirmed and settled |
| `InvoiceStatus::EXPIRED` | `'EXPIRED'` | Payment window closed without full payment |
| `InvoiceStatus::CANCELED` | `'CANCELED'` | Invoice explicitly voided or canceled |

### Invoice Type (`Kkhay\InvoiceType`)

| Constant | Value | Description |
| :--- | :--- | :--- |
| `InvoiceType::PAYMENT` | `'PAYMENT'` | Standard e-commerce payment |
| `InvoiceType::DONATION` | `'DONATION'` | Open-amount contribution |
| `InvoiceType::SUBSCRIPTION` | `'SUBSCRIPTION'` | Recurring subscription billing |

### Webhook Event Types (`Kkhay\WebhookEventType`)

| Constant | Value | Description |
| :--- | :--- | :--- |
| `WebhookEventType::PAYMENT_PAID` | `'payment.paid'` | Payment confirmed on blockchain |
| `WebhookEventType::PAYMENT_FINISHED` | `'payment.finished'` | Invoice completed and finalized |
| `WebhookEventType::PAYMENT_CONFIRMING` | `'payment.confirming'` | Transaction detected, awaiting confirmations |
| `WebhookEventType::PAYMENT_FAILED` | `'payment.failed'` | Transaction failed on-chain |
| `WebhookEventType::INVOICE_CANCELED` | `'invoice.canceled'` | Invoice was canceled |

---

## 🌐 Supported Blockchains (190+ Networks)

| Network | Code | Type | Popular Tokens |
| :--- | :--- | :--- | :--- |
| **BNB Smart Chain** | `bsc` | EVM | USDT, USDC, BNB, DAI |
| **Polygon** | `polygon` | EVM | USDT, USDC, POL, WETH |
| **Arbitrum One** | `arbitrum` | EVM (L2) | USDT, USDC, ARB, ETH |
| **Base** | `base` | EVM (L2) | USDC, ETH, DAI |
| **Ethereum** | `eth` | EVM (L1) | USDT, USDC, ETH, DAI |
| **Solana** | `solana` / `sol` | High-Throughput | SOL, USDT, USDC |
| **TON Network** | `ton` | High-Throughput | TON, USDT |
| **Tron** | `tron` / `trx` | TRC-20 | TRX, USDT, USDC |
| **Bitcoin** | `bitcoin` / `btc`| UTXO | BTC |
| **XRP Ledger** | `xrpl` / `xrp` | Ledger | XRP |
| **Dogecoin** | `dogecoin` / `doge`| UTXO | DOGE |
| **Litecoin** | `litecoin` / `ltc` | UTXO | LTC |

*And 180+ additional networks dynamically routed through K Khay.*

---

## 📄 License

MIT © [K Khay](https://kkhay.com)
