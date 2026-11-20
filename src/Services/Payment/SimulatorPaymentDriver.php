<?php
declare(strict_types=1);

namespace App\Services\Payment;

class SimulatorPaymentDriver implements PaymentDriverInterface
{
    public function getDriverName(): string
    {
        return 'SIMULATOR';
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createOrder(array $params): array
    {
        $amount = (float)($params['amount'] ?? 0.0);
        $orderId = sprintf('sim_ord_%s_%s', date('YmdHis'), bin2hex(random_bytes(4)));

        return [
            'orderId'     => $orderId,
            'amount'      => $amount,
            'currency'    => 'INR',
            'driver'      => $this->getDriverName(),
            'notes'       => $params['notes'] ?? 'Simulated test transaction',
            'isSimulator' => true,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{verified: bool, transactionRef: string, signature: ?string, message: string}
     */
    public function verifyPayment(array $payload): array
    {
        $orderId = (string)($payload['orderId'] ?? $payload['gateway_order_id'] ?? '');
        $txnRef = (string)($payload['transactionRef'] ?? $payload['transaction_ref'] ?? '');

        if ($txnRef === '') {
            $txnRef = sprintf('TXN-SIM-%s-%s', date('YmdHis'), strtoupper(bin2hex(random_bytes(3))));
        }

        $signature = hash('sha256', $orderId . '|' . $txnRef . '|mkr_sim_secret');

        return [
            'verified'       => true,
            'transactionRef' => $txnRef,
            'signature'      => $signature,
            'message'        => 'Simulated transaction verified successfully.',
        ];
    }
}
