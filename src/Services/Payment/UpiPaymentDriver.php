<?php
declare(strict_types=1);

namespace App\Services\Payment;

use App\Config\CollegeConfig;

class UpiPaymentDriver implements PaymentDriverInterface
{
    public const COLLEGE_UPI_VPA = 'mkrcollege@sbi';

    public function getDriverName(): string
    {
        return 'UPI';
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createOrder(array $params): array
    {
        $amount = (float)($params['amount'] ?? 0.0);
        $orderId = sprintf('upi_ord_%s_%s', date('YmdHis'), bin2hex(random_bytes(3)));
        $studentName = urlencode((string)($params['studentName'] ?? 'MKR Student'));

        $upiUri = sprintf(
            'upi://pay?pa=%s&pn=%s&am=%.2f&cu=INR&tn=%s',
            self::COLLEGE_UPI_VPA,
            urlencode(CollegeConfig::INSTITUTION_CODE),
            $amount,
            urlencode('Semester Fee Payment - ' . $studentName)
        );

        return [
            'orderId'     => $orderId,
            'amount'      => $amount,
            'currency'    => 'INR',
            'driver'      => $this->getDriverName(),
            'upiVpa'      => self::COLLEGE_UPI_VPA,
            'upiUri'      => $upiUri,
            'instructions'=> 'Scan QR code or transfer via UPI App to ' . self::COLLEGE_UPI_VPA . ', then enter the 12-digit UPI UTR number.',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{verified: bool, transactionRef: string, signature: ?string, message: string}
     */
    public function verifyPayment(array $payload): array
    {
        $utr = trim((string)($payload['utrNumber'] ?? $payload['transactionRef'] ?? ''));

        if ($utr === '' || !preg_match('/^\d{12}$/', $utr)) {
            return [
                'verified'       => false,
                'transactionRef' => '',
                'signature'      => null,
                'message'        => 'A valid 12-digit UPI UTR / Transaction Reference number is required.',
            ];
        }

        return [
            'verified'       => true,
            'transactionRef' => 'UPI-' . $utr,
            'signature'      => hash('sha256', 'UPI|' . $utr . '|' . self::COLLEGE_UPI_VPA),
            'message'        => 'UPI payment reference registered for reconciliation.',
        ];
    }
}
