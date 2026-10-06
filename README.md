# K Khay PHP SDK 🐘

Official PHP SDK for the **[K Khay Sovereign Crypto Payment Gateway](https://kkhay.com)**.

Accept non-custodial and custodial crypto payments (USDT, USDC, BNB, ETH, and native tokens on BSC, Polygon, Arbitrum, Base, and Ethereum) across **WooCommerce, WordPress, Laravel, Symfony, OpenCart**, or custom PHP apps.

---

## 📦 Installation

Install via [Composer](https://packagist.org/packages/kkhay/kkhay):

```bash
composer require kkhay/kkhay
```

*(Requires PHP 8.0 or newer)*

---

## ⚡ Quick Start

### 1. Initialize Client

```php
use Kkhay\Client;

$client = new Client('kkhay_live_your_api_key_here');
```

### 2. Create a Crypto Invoice

```php
$response = $client->createInvoice([
    'priceAmount' => 49.99,
    'priceCurrency' => 'USD',
    'payNetwork' => 'bsc',
    'payToken' => 'USDT',
    'orderId' => 'WC-ORDER-8821',
    'title' => 'VIP Lifetime Access',
    'customerEmail' => 'buyer@example.com',
    'redirectUrl' => 'https://myshop.com/checkout/order-received',
    'cancelUrl' => 'https://myshop.com/cart',
]);

$invoice = $response['invoice'];
echo "Invoice ID: " . $invoice['id'] . "\n";
echo "Pay URL: " . $invoice['hostedUrl'] . "\n";
```

### 3. Check Invoice Status & Blockchain Confirmations

```php
$data = $client->getInvoice('inv_9f81a7b2');
$invoice = $data['invoice'];

echo "Current Status: " . $invoice['status'] . "\n";
foreach ($data['payments'] as $payment) {
    echo "Tx Hash: " . $payment['txHash'] . "\n";
    echo "Confirmations: " . $payment['confirmations'] . "\n";
}
```

---

## 🔐 Webhook / IPN Verification

### Pure PHP Webhook Endpoint

```php
use Kkhay\Webhook;
use Kkhay\Exception\SignatureVerificationException;

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_KKHAY_SIGNATURE'] ?? null;
$ipnSecret = getenv('KKHAY_IPN_SECRET');

try {
    $event = Webhook::parseEvent($payload, $signature, $ipnSecret);

    if ($event['event'] === 'payment.finished') {
        $orderId = $event['order_id'];
        $txHash = $event['tx_hash'];
        // Mark order paid in database
    }

    http_response_code(200);
    echo json_encode(['ok' => true]);
} catch (SignatureVerificationException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
}
```

### Laravel Controller

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Kkhay\Webhook;

class KkhayWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $raw = $request->getContent();
        $sig = $request->header('x-kkhay-signature');

        if (!Webhook::verifySignature($raw, $sig, config('services.kkhay.ipn_secret'))) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $data = json_decode($raw, true);
        // Process webhook
        return response()->json(['ok' => true]);
    }
}
```

---

## 📄 License

MIT © [K Khay](https://kkhay.com)

