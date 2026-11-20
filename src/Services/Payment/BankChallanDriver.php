<?php
declare(strict_types=1);

namespace App\Services\Payment;

use App\Config\CollegeConfig;

class BankChallanDriver implements PaymentDriverInterface
{
    public const BANK_NAME = 'State Bank of India (SBI)';
    public const BRANCH = 'Punaura Branch, Sitamarhi';
    public const ACCOUNT_NO = '39820010005432';
    public const IFSC_CODE = 'SBIN0012345';

    public function getDriverName(): string
    {
        return 'BANK_CHALLAN';
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createOrder(array $params): array
    {
        $amount = (float)($params['amount'] ?? 0.0);
        $orderId = sprintf('CHLN-%s-%s', date('Y'), strtoupper(bin2hex(random_bytes(4))));

        return [
            'orderId'     => $orderId,
            'challanNo'   => $orderId,
            'amount'      => $amount,
            'currency'    => 'INR',
            'driver'      => $this->getDriverName(),
            'bankName'    => self::BANK_NAME,
            'branch'      => self::BRANCH,
            'accountNo'   => self::ACCOUNT_NO,
            'ifsc'        => self::IFSC_CODE,
            'beneficiary' => CollegeConfig::INSTITUTION_NAME,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{verified: bool, transactionRef: string, signature: ?string, message: string}
     */
    public function verifyPayment(array $payload): array
    {
        $journalNo = trim((string)($payload['journalNo'] ?? $payload['transactionRef'] ?? ''));

        if ($journalNo === '') {
            return [
                'verified'       => false,
                'transactionRef' => '',
                'signature'      => null,
                'message'        => 'Bank deposit journal / scroll reference number is required.',
            ];
        }

        return [
            'verified'       => true,
            'transactionRef' => 'CHALLAN-' . $journalNo,
            'signature'      => hash('sha256', 'CHALLAN|' . $journalNo . '|' . self::ACCOUNT_NO),
            'message'        => 'Bank challan deposit registered with offline verification pending.',
        ];
    }
}
