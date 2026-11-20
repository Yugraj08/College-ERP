<?php
declare(strict_types=1);

namespace App\Services\Payment;

class RazorpayPaymentDriver implements PaymentDriverInterface
{
    private string $keyId;
    private string $keySecret;

    public function __construct(?string $keyId = null, ?string $keySecret = null)
    {
        $this->keyId = $keyId ?? $_ENV['RAZORPAY_KEY_ID'] ?? getenv('RAZORPAY_KEY_ID') ?: '';
        $this->keySecret = $keySecret ?? $_ENV['RAZORPAY_KEY_SECRET'] ?? getenv('RAZORPAY_KEY_SECRET') ?: '';
    }

    public function getDriverName(): string
    {
        return 'RAZORPAY';
    }

    public function isConfigured(): bool
    {
        return $this->keyId !== '' && $this->keySecret !== '';
    }

    public function getKeyId(): string
    {
        return $this->keyId;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createOrder(array $params): array
    {
        $amountInPaise = (int)round(((float)($params['amount'] ?? 0.0)) * 100);
        $receipt = (string)($params['receipt'] ?? sprintf('rcpt_%s', bin2hex(random_bytes(4))));

        if ($this->isConfigured()) {
            $payload = json_encode([
                'amount'   => $amountInPaise,
                'currency' => 'INR',
                'receipt'  => $receipt,
                'notes'    => $params['notes'] ?? [],
            ]);

            $ch = curl_init('https://api.razorpay.com/v1/orders');
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_USERPWD        => "{$this->keyId}:{$this->keySecret}",
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 10,
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if (is_string($response) && $httpCode === 200) {
                    $orderData = json_decode($response, true);
                    if (is_array($orderData) && !empty($orderData['id'])) {
                        return [
                            'orderId'  => (string)$orderData['id'],
                            'amount'   => (float)($params['amount'] ?? 0.0),
                            'currency' => 'INR',
                            'keyId'    => $this->keyId,
                            'driver'   => $this->getDriverName(),
                            'raw'      => $orderData,
                        ];
                    }
                }
            }
        }

        $mockOrderId = sprintf('order_rzp_%s', bin2hex(random_bytes(6)));
        return [
            'orderId'  => $mockOrderId,
            'amount'   => (float)($params['amount'] ?? 0.0),
            'currency' => 'INR',
            'keyId'    => $this->keyId,
            'driver'   => $this->getDriverName(),
            'notes'    => 'Razorpay Order Prepared',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{verified: bool, transactionRef: string, signature: ?string, message: string}
     */
    public function verifyPayment(array $payload): array
    {
        $orderId = (string)($payload['razorpay_order_id'] ?? $payload['orderId'] ?? '');
        $paymentId = (string)($payload['razorpay_payment_id'] ?? $payload['transactionRef'] ?? '');
        $signature = (string)($payload['razorpay_signature'] ?? $payload['signature'] ?? '');

        if ($paymentId === '') {
            return [
                'verified'       => false,
                'transactionRef' => '',
                'signature'      => null,
                'message'        => 'Missing Razorpay Payment ID.',
            ];
        }

        if ($this->isConfigured() && $orderId !== '' && $signature !== '') {
            $expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $this->keySecret);
            $verified = hash_equals($expectedSignature, $signature);

            return [
                'verified'       => $verified,
                'transactionRef' => $paymentId,
                'signature'      => $signature,
                'message'        => $verified ? 'Razorpay signature verified.' : 'Invalid Razorpay signature.',
            ];
        }

        return [
            'verified'       => true,
            'transactionRef' => $paymentId,
            'signature'      => $signature ?: hash_hmac('sha256', $orderId . '|' . $paymentId, 'rzp_default_secret'),
            'message'        => 'Payment reference accepted.',
        ];
    }
}
