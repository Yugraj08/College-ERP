<?php
declare(strict_types=1);

namespace App\Services\Payment;

use App\Core\Database;
use App\Core\Logger;
use PDO;
use RuntimeException;
use Throwable;

class PaymentService
{
    private PDO $pdo;
    private array $drivers = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();

        $simulator = new SimulatorPaymentDriver();
        $razorpay = new RazorpayPaymentDriver();
        $upi = new UpiPaymentDriver();
        $challan = new BankChallanDriver();

        $this->drivers['SIMULATOR'] = $simulator;
        $this->drivers['RAZORPAY'] = $razorpay;
        $this->drivers['UPI'] = $upi;
        $this->drivers['BANK_CHALLAN'] = $challan;
    }

    public function resolveDriver(string $method = 'ONLINE_GATEWAY'): PaymentDriverInterface
    {
        $method = strtoupper(trim($method));

        if ($method === 'UPI') {
            return $this->drivers['UPI'];
        }

        if ($method === 'BANK_CHALLAN') {
            return $this->drivers['BANK_CHALLAN'];
        }

        /** @var RazorpayPaymentDriver $razorpay */
        $razorpay = $this->drivers['RAZORPAY'];
        if ($razorpay->isConfigured()) {
            return $razorpay;
        }

        return $this->drivers['SIMULATOR'];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function initializeOrder(string $paymentMethod, array $params): array
    {
        $driver = $this->resolveDriver($paymentMethod);
        return $driver->createOrder($params);
    }

    /**
     * @param array<string, mixed> $paymentDetails
     * @return array<string, mixed>
     */
    public function recordAndVerifyPayment(
        string $studentId,
        int $targetSemester,
        string $paymentType,
        string $title,
        float $amount,
        string $paymentMethod,
        array $paymentDetails = []
    ): array {
        $driver = $this->resolveDriver($paymentMethod);
        $verification = $driver->verifyPayment($paymentDetails);

        if (!$verification['verified']) {
            throw new RuntimeException($verification['message'] ?: 'Payment verification failed.');
        }

        $isPendingMethod = ($paymentMethod === 'BANK_CHALLAN');
        $status = $isPendingMethod ? 'PENDING' : 'COMPLETED';
        $paidAt = $isPendingMethod ? null : date('Y-m-d H:i:s');

        $ownsTransaction = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $ownsTransaction = true;
        }

        try {
            $year = (int)date('Y');
            $receiptNo = $this->generateSequentialReceiptNo($year, $targetSemester);
            $paymentId = sprintf('pay_%s_%s', date('YmdHis'), bin2hex(random_bytes(4)));

            $stmt = $this->pdo->prepare('
                INSERT INTO fee_payments (
                    id, receipt_no, student_id, target_semester, payment_type,
                    title, amount, payment_method, status, transaction_ref,
                    gateway_order_id, gateway_signature, is_promotion_triggered,
                    paid_at, notes, created_at
                ) VALUES (
                    :id, :receipt_no, :student_id, :target_sem, :payment_type,
                    :title, :amount, :payment_method, :status, :transaction_ref,
                    :gateway_order_id, :gateway_signature, 0,
                    :paid_at, :notes, CURRENT_TIMESTAMP
                )
            ');

            $stmt->execute([
                'id'                => $paymentId,
                'receipt_no'        => $receiptNo,
                'student_id'        => $studentId,
                'target_sem'        => $targetSemester,
                'payment_type'      => $paymentType,
                'title'             => $title,
                'amount'            => $amount,
                'payment_method'    => $paymentMethod,
                'status'            => $status,
                'transaction_ref'   => $verification['transactionRef'],
                'gateway_order_id'  => $paymentDetails['orderId'] ?? null,
                'gateway_signature' => $verification['signature'],
                'paid_at'           => $paidAt,
                'notes'             => json_encode([
                    'driver'      => $driver->getDriverName(),
                    'verification'=> $verification,
                ]),
            ]);

            if ($status === 'COMPLETED') {
                $semRecordStmt = $this->pdo->prepare('
                    UPDATE student_semester_records
                    SET is_fee_cleared = 1,
                        status = \'FEES_PAID\',
                        updated_at = CURRENT_TIMESTAMP
                    WHERE student_id = :student_id AND semester = :semester
                ');
                $semRecordStmt->execute([
                    'student_id' => $studentId,
                    'semester'   => $targetSemester,
                ]);
            }

            $auditId = sprintf('aud_%s_%s', date('YmdHis'), bin2hex(random_bytes(4)));
            $auditStmt = $this->pdo->prepare('
                INSERT INTO audit_logs (id, user_id, action, entity, entity_id, new_values, details, timestamp)
                VALUES (:id, NULL, :action, \'fee_payments\', :entity_id, :new_values, :details, CURRENT_TIMESTAMP)
            ');
            $auditStmt->execute([
                'id'         => $auditId,
                'action'     => 'FEE_PAYMENT_' . $status,
                'entity_id'  => $paymentId,
                'new_values' => json_encode(['receipt_no' => $receiptNo, 'amount' => $amount, 'method' => $paymentMethod]),
                'details'    => "Receipt {$receiptNo} generated for student {$studentId} (Semester {$targetSemester})",
            ]);

            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            return [
                'paymentId'      => $paymentId,
                'receiptNo'      => $receiptNo,
                'studentId'      => $studentId,
                'targetSemester' => $targetSemester,
                'amount'         => $amount,
                'paymentMethod'  => $paymentMethod,
                'status'         => $status,
                'transactionRef' => $verification['transactionRef'],
                'paidAt'         => $paidAt,
                'driver'         => $driver->getDriverName(),
            ];
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Logger::exception($e, 'process_payment', ['student_id' => $studentId, 'method' => $paymentMethod]);
            throw $e;
        }
    }

    public function generateSequentialReceiptNo(int $year, int $semester): string
    {
        $prefix = sprintf('REC-%d-S%d-', $year, $semester);
        $pattern = $prefix . '%';

        $stmt = $this->pdo->prepare('
            SELECT receipt_no FROM fee_payments
            WHERE receipt_no LIKE :pattern
            ORDER BY receipt_no DESC
            LIMIT 50
        ');
        $stmt->execute(['pattern' => $pattern]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $maxSerial = 0;
        foreach ($rows as $receipt) {
            $parts = explode('-', (string)$receipt);
            $last = end($parts);
            if (is_numeric($last)) {
                $num = (int)$last;
                if ($num > $maxSerial) {
                    $maxSerial = $num;
                }
            }
        }

        $serial = $maxSerial + 1;

        $checkStmt = $this->pdo->prepare('SELECT COUNT(*) FROM fee_payments WHERE receipt_no = :receipt_no');

        do {
            $candidate = sprintf('%s%06d', $prefix, $serial);
            $checkStmt->execute(['receipt_no' => $candidate]);
            $exists = (int)$checkStmt->fetchColumn() > 0;
            if ($exists) {
                $serial++;
            }
        } while ($exists);

        return $candidate;
    }
}
