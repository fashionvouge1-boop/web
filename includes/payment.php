<?php
/**
 * Payment gateway abstraction.
 *
 * Every gateway implements PaymentGatewayInterface so the checkout flow does
 * not need to know which provider is used. A gateway returns one of:
 *
 *   ['status' => 'paid',     'ref' => '...', 'redirect' => 'payment-success?...']
 *   ['status' => 'pending',  'ref' => '...', 'redirect' => 'payment-success?...']  // e.g. COD
 *   ['status' => 'redirect', 'ref' => '...', 'redirect' => 'https://gateway...']   // external page
 *
 * The app ships with Cash-on-Delivery and a sandbox "Demo" gateway that work
 * with no keys, plus ready-to-enable Razorpay and Stripe integrations.
 */
require_once __DIR__ . '/functions.php';

interface PaymentGatewayInterface
{
    public function code(): string;
    public function label(): string;
    public function isEnabled(): bool;
    /** Begin a payment for the given order. */
    public function pay(array $order): array;
}

abstract class BaseGateway implements PaymentGatewayInterface
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function label(): string
    {
        return $this->config['label'] ?? ucfirst($this->code());
    }

    public function isEnabled(): bool
    {
        return (bool)($this->config['enabled'] ?? false);
    }

    /** Small cURL helper for the REST-based gateways. */
    protected function httpPost(string $url, array $fields, array $headers = [], bool $json = false): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json ? json_encode($fields) : http_build_query($fields),
            CURLOPT_HTTPHEADER     => array_merge(
                $json ? ['Content-Type: application/json'] : ['Content-Type: application/x-www-form-urlencoded'],
                $headers
            ),
            CURLOPT_TIMEOUT        => 30,
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['code' => $code, 'body' => $body, 'error' => $err, 'json' => $body ? json_decode($body, true) : null];
    }
}

/* --------------------------------------------------------------------- *
 *  Cash on Delivery — order is placed, payment collected later.
 * --------------------------------------------------------------------- */
class CodGateway extends BaseGateway
{
    public function code(): string { return 'cod'; }

    public function pay(array $order): array
    {
        return [
            'status'   => 'pending',
            'ref'      => 'COD-' . $order['order_number'],
            'redirect' => 'payment-success.php?order=' . urlencode($order['order_number']),
        ];
    }
}

/* --------------------------------------------------------------------- *
 *  Demo / sandbox card & UPI gateway — simulates an instant success.
 *  Use this to test the complete checkout flow without any API keys.
 * --------------------------------------------------------------------- */
class DemoGateway extends BaseGateway
{
    public function code(): string { return 'demo'; }

    public function pay(array $order): array
    {
        return [
            'status'   => 'paid',
            'ref'      => 'DEMO-' . strtoupper(bin2hex(random_bytes(4))),
            'redirect' => 'payment-success.php?order=' . urlencode($order['order_number']),
        ];
    }
}

/* --------------------------------------------------------------------- *
 *  UPI Apps — Google Pay / PhonePe / Paytm deep links (no API keys needed).
 *  Order is created as "pending" and settled on the dedicated UPI page.
 * --------------------------------------------------------------------- */
class UpiGateway extends BaseGateway
{
    public function code(): string { return 'upi'; }

    public function pay(array $order): array
    {
        return [
            'status'   => 'pending',
            'ref'      => 'UPI-' . $order['order_number'],
            'redirect' => 'payment-upi.php?order=' . urlencode($order['order_number']),
        ];
    }
}

/* --------------------------------------------------------------------- *
 *  Razorpay — real integration (enable + add keys in config.php).
 * --------------------------------------------------------------------- */
class RazorpayGateway extends BaseGateway
{
    public function code(): string { return 'razorpay'; }

    public function pay(array $order): array
    {
        $keyId     = $this->config['key_id'] ?? '';
        $keySecret = $this->config['key_secret'] ?? '';
        if (!$keyId || !$keySecret) {
            throw new RuntimeException('Razorpay keys are not configured.');
        }

        $res = $this->httpPost(
            'https://api.razorpay.com/v1/orders',
            [
                'amount'   => (int)round($order['total'] * 100), // paise
                'currency' => 'INR',
                'receipt'  => $order['order_number'],
            ],
            ['Authorization: Basic ' . base64_encode($keyId . ':' . $keySecret)],
            true
        );

        if (empty($res['json']['id'])) {
            throw new RuntimeException('Razorpay order creation failed: ' . ($res['body'] ?: $res['error']));
        }

        $rzpOrderId = $res['json']['id'];
        $html = '<script src="https://checkout.razorpay.com/v1/checkout.js"></script>'
              . '<script>new Razorpay({'
              . 'key:' . json_encode($keyId) . ','
              . 'order_id:' . json_encode($rzpOrderId) . ','
              . 'amount:' . json_encode((int)round($order['total'] * 100)) . ','
              . 'currency:"INR",'
              . 'name:' . json_encode(SITE_NAME) . ','
              . 'prefill:{name:' . json_encode($order['shipping_name'])
              . ',email:' . json_encode($order['shipping_email'])
              . ',contact:' . json_encode($order['shipping_phone']) . '},'
              . 'handler:function(r){ window.location.href=' . json_encode(url('payment-success.php?order=' . urlencode($order['order_number']) . '&payment_id=')) . ' + r.razorpay_payment_id; },'
              . 'theme:{color:"#e11d48"}'
              . '}).open();</script>';

        return ['status' => 'pending', 'ref' => $rzpOrderId, 'redirect' => null, 'html' => $html];
    }
}

/* --------------------------------------------------------------------- *
 *  Stripe Checkout — real integration (enable + add keys in config.php).
 * --------------------------------------------------------------------- */
class StripeGateway extends BaseGateway
{
    public function code(): string { return 'stripe'; }

    public function pay(array $order): array
    {
        $secret = $this->config['secret_key'] ?? '';
        if (!$secret) {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        $fields = [
            'mode'                                     => 'payment',
            'success_url'                              => url('payment-success.php?order=' . urlencode($order['order_number']) . '&session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url'                               => url('checkout.php'),
            'line_items[0][price_data][currency]'      => 'inr',
            'line_items[0][price_data][product_data][name]' => SITE_NAME . ' Order ' . $order['order_number'],
            'line_items[0][price_data][unit_amount]'   => (int)round($order['total'] * 100),
            'line_items[0][quantity]'                  => 1,
        ];

        $res = $this->httpPost('https://api.stripe.com/v1/checkout/sessions', $fields, [
            'Authorization: Bearer ' . $secret,
        ]);

        if (empty($res['json']['url'])) {
            throw new RuntimeException('Stripe session creation failed: ' . ($res['body'] ?: $res['error']));
        }

        return ['status' => 'redirect', 'ref' => $res['json']['id'], 'redirect' => $res['json']['url']];
    }
}

/* --------------------------------------------------------------------- *
 *  Factory helpers
 * --------------------------------------------------------------------- */

/** All enabled gateways keyed by code. */
function available_gateways(): array
{
    global $GATEWAYS;
    $out = [];
    foreach ($GATEWAYS as $code => $cfg) {
        if (!empty($cfg['enabled'])) {
            $out[$code] = instantiate_gateway($code, $cfg);
        }
    }
    return $out;
}

/** Return a single gateway instance (or null). */
function get_gateway(string $code): ?PaymentGatewayInterface
{
    global $GATEWAYS;
    if (!isset($GATEWAYS[$code])) {
        return null;
    }
    return instantiate_gateway($code, $GATEWAYS[$code]);
}

/** Is this gateway offered but currently unavailable for delivery? */
function gateway_blocked(string $code): bool
{
    return $code === 'cod' && defined('COD_BLOCKED') && COD_BLOCKED;
}

function instantiate_gateway(string $code, array $cfg): PaymentGatewayInterface
{
    switch ($code) {
        case 'cod':      return new CodGateway($cfg);
        case 'demo':     return new DemoGateway($cfg);
        case 'upi':      return new UpiGateway($cfg);
        case 'razorpay': return new RazorpayGateway($cfg);
        case 'stripe':   return new StripeGateway($cfg);
        default:         throw new RuntimeException('Unknown gateway: ' . $code);
    }
}
