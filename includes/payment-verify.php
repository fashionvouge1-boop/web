<?php
/**
 * Payment verification scaffold.
 *
 * Confirms a gateway return/callback against the provider so an order is only
 * marked "paid" once the payment is genuinely captured. The Demo gateway needs
 * no verification, while Razorpay and Stripe checks activate automatically once
 * their keys are added in config.php.
 */
require_once __DIR__ . '/functions.php';

/**
 * Verify a gateway callback.
 *
 * @param string $code   gateway code (demo|cod|razorpay|stripe)
 * @param array  $order  full order row (needs order_number, total, payment_ref)
 * @param array  $params callback parameters (typically $_GET + $_POST)
 * @return array{status:string, ref:?string, message:string} status = paid|pending|failed
 */
function verify_payment(string $code, array $order, array $params): array
{
    global $GATEWAYS;
    $cfg = $GATEWAYS[$code] ?? [];

    switch ($code) {
        case 'demo':
            // Sandbox gateway — nothing to verify, treat as paid.
            return ['status' => 'paid', 'ref' => $order['payment_ref'] ?? null, 'message' => 'Sandbox payment accepted.'];

        case 'cod':
            return ['status' => 'pending', 'ref' => $order['payment_ref'] ?? null, 'message' => 'Payment due on delivery.'];

        case 'upi':
            // Direct UPI app deep links have no server-side callback, so the
            // customer confirms on the UPI page. For production, integrate a
            // UPI payment provider (e.g. Razorpay/Cashfree) and verify via webhook.
            if (($params['upi'] ?? '') === 'done') {
                return ['status' => 'paid', 'ref' => $order['payment_ref'] ?: ('UPI-' . $order['order_number']), 'message' => 'Customer confirmed UPI payment.'];
            }
            return ['status' => 'pending', 'ref' => $order['payment_ref'] ?? null, 'message' => 'Awaiting UPI payment.'];

        case 'razorpay':
            $secret    = (string)($cfg['key_secret'] ?? '');
            $paymentId = (string)($params['razorpay_payment_id'] ?? '');
            $rzpOrder  = (string)($params['razorpay_order_id'] ?? '');
            $signature = (string)($params['razorpay_signature'] ?? '');
            if ($secret === '' || $paymentId === '' || $signature === '') {
                return ['status' => 'pending', 'ref' => $paymentId ?: null, 'message' => 'Awaiting Razorpay confirmation.'];
            }
            $expected = hash_hmac('sha256', $rzpOrder . '|' . $paymentId, $secret);
            if (hash_equals($expected, $signature)) {
                return ['status' => 'paid', 'ref' => $paymentId, 'message' => 'Razorpay signature verified.'];
            }
            return ['status' => 'failed', 'ref' => $paymentId ?: null, 'message' => 'Razorpay signature mismatch.'];

        case 'stripe':
            $secret    = (string)($cfg['secret_key'] ?? '');
            $sessionId = (string)($params['session_id'] ?? '');
            if ($secret === '' || $sessionId === '') {
                return ['status' => 'pending', 'ref' => $sessionId ?: null, 'message' => 'Awaiting Stripe confirmation.'];
            }
            $ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($sessionId));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $secret],
                CURLOPT_TIMEOUT        => 30,
            ]);
            $body = curl_exec($ch);
            curl_close($ch);
            $json = $body ? json_decode($body, true) : null;
            if (($json['payment_status'] ?? '') === 'paid') {
                return ['status' => 'paid', 'ref' => $sessionId, 'message' => 'Stripe session paid.'];
            }
            return ['status' => 'pending', 'ref' => $sessionId ?: null, 'message' => 'Stripe session not paid yet.'];
    }

    return ['status' => 'pending', 'ref' => null, 'message' => 'Unknown gateway.'];
}
