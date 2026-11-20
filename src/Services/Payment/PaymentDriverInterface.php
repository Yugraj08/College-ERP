<?php
declare(strict_types=1);

namespace App\Services\Payment;

interface PaymentDriverInterface
{
    public function getDriverName(): string;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createOrder(array $params): array;

    /**
     * @param array<string, mixed> $payload
     * @return array{verified: bool, transactionRef: string, signature: ?string, message: string}
     */
    public function verifyPayment(array $payload): array;
}
