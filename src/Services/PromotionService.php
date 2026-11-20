<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\CollegeConfig;
use App\Core\Database;
use App\Core\Logger;
use App\Services\Payment\PaymentService;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

class PromotionService
{
    private PDO $pdo;
    private PaymentService $paymentService;

    public function __construct(?PDO $pdo = null, ?PaymentService $paymentService = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->paymentService = $paymentService ?? new PaymentService($this->pdo);
    }

    /**
     * @param array<string, mixed> $paymentDetails
     * @return array<string, mixed>
     */
    public function promoteStudent(
        string $studentId,
        int $targetSemester,
        float $amount,
        string $paymentMethod = 'ONLINE_GATEWAY',
        array $paymentDetails = [],
        string $promotedBy = 'SYSTEM_AUTO_PAYMENT',
        ?string $remarks = null
    ): array {
        if ($targetSemester < 2 || $targetSemester > CollegeConfig::MAX_SEMESTERS) {
            throw new InvalidArgumentException("Invalid target semester: {$targetSemester}. Valid semesters are 2 to " . CollegeConfig::MAX_SEMESTERS . '.');
        }

        $stmt = $this->pdo->prepare('
            SELECT s.*, c.code AS course_code, c.name AS course_name, sess.id AS session_id, sess.name AS session_name
            FROM students s
            JOIN courses c ON s.course_id = c.id
            JOIN academic_sessions sess ON s.session_id = sess.id
            WHERE s.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $studentId]);
        $student = $stmt->fetch();

        if (!$student) {
            throw new RuntimeException("Student record not found for ID: {$studentId}");
        }

        $feeCheck = $this->pdo->prepare('
            SELECT id FROM fee_payments
            WHERE student_id = :sid AND target_semester = :sem AND status = \'COMPLETED\'
            LIMIT 1
        ');
        $feeCheck->execute(['sid' => $studentId, 'sem' => $targetSemester]);
        if ($feeCheck->fetch()) {
            throw new RuntimeException("Fee for Semester {$targetSemester} has already been paid and cleared.");
        }

        $currentSem = (int)$student['current_semester'];
        if ($targetSemester !== ($currentSem + 1)) {
            throw new InvalidArgumentException("Sequential progression required: Student is currently in Semester {$currentSem} and can only advance to Semester " . ($currentSem + 1) . '.');
        }

        $this->validateRegistrationWindow((string)$student['course_id'], $targetSemester, (string)$student['session_id']);

        $ownsTransaction = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $ownsTransaction = true;
        }

        try {
            $courseCode = (string)$student['course_code'];
            $paymentResult = $this->paymentService->recordAndVerifyPayment(
                $studentId,
                $targetSemester,
                'SEMESTER_PROMOTION',
                "Semester {$targetSemester} Progression Fee - {$courseCode}",
                $amount,
                $paymentMethod,
                $paymentDetails
            );

            $semRecordId = sprintf('sem_%s_%d', substr($studentId, 0, 16), $targetSemester);
            $checkSem = $this->pdo->prepare('
                SELECT id FROM student_semester_records
                WHERE student_id = :sid AND semester = :sem
                LIMIT 1
            ');
            $checkSem->execute(['sid' => $studentId, 'sem' => $targetSemester]);
            $existingSem = $checkSem->fetch();

            if ($existingSem) {
                $updSem = $this->pdo->prepare('
                    UPDATE student_semester_records
                    SET is_fee_cleared = 1, status = \'FEES_PAID\', updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ');
                $updSem->execute(['id' => $existingSem['id']]);
            } else {
                $insSem = $this->pdo->prepare('
                    INSERT INTO student_semester_records (
                        id, student_id, semester, academic_session_id, status, is_fee_cleared, enrolled_at, created_at, updated_at
                    ) VALUES (
                        :id, :sid, :sem, :sess_id, \'FEES_PAID\', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                    )
                ');
                $insSem->execute([
                    'id'      => $semRecordId,
                    'sid'     => $studentId,
                    'sem'     => $targetSemester,
                    'sess_id' => $student['session_id'],
                ]);
            }

            $updStudent = $this->pdo->prepare('
                UPDATE students
                SET current_semester = :target_sem, status = \'ADMITTED\', updated_at = CURRENT_TIMESTAMP
                WHERE id = :sid
            ');
            $updStudent->execute([
                'target_sem' => $targetSemester,
                'sid'        => $studentId,
            ]);

            $promoHistoryId = sprintf('promo_%s_%s', date('YmdHis'), bin2hex(random_bytes(4)));
            $insPromo = $this->pdo->prepare('
                INSERT INTO promotion_histories (
                    id, student_id, from_semester, to_semester, academic_session_id, trigger_payment_id, promoted_by, remarks, created_at
                ) VALUES (
                    :id, :sid, :from_sem, :to_sem, :sess_id, :pay_id, :promoted_by, :remarks, CURRENT_TIMESTAMP
                )
            ');
            $insPromo->execute([
                'id'          => $promoHistoryId,
                'sid'         => $studentId,
                'from_sem'    => $currentSem,
                'to_sem'      => $targetSemester,
                'sess_id'     => $student['session_id'],
                'pay_id'      => $paymentResult['paymentId'] ?? null,
                'promoted_by' => $promotedBy,
                'remarks'     => $remarks ?? "Promoted from Semester {$currentSem} to {$targetSemester} via {$paymentMethod}",
            ]);

            $auditId = sprintf('aud_%s_%s', date('YmdHis'), bin2hex(random_bytes(4)));
            $auditStmt = $this->pdo->prepare('
                INSERT INTO audit_logs (id, user_id, action, entity, entity_id, new_values, details, timestamp)
                VALUES (:id, NULL, :action, \'students\', :entity_id, :new_values, :details, CURRENT_TIMESTAMP)
            ');
            $auditStmt->execute([
                'id'         => $auditId,
                'action'     => 'SEMESTER_PROMOTION',
                'entity_id'  => $studentId,
                'new_values' => json_encode([
                    'fromSemester'   => $currentSem,
                    'targetSemester' => $targetSemester,
                    'receiptNo'      => $paymentResult['receiptNo'],
                    'promotedBy'     => $promotedBy,
                ]),
                'details'    => "Student {$student['full_name']} promoted to Semester {$targetSemester}",
            ]);

            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            return [
                'success'        => true,
                'studentId'      => $studentId,
                'fromSemester'   => $currentSem,
                'toSemester'     => $targetSemester,
                'targetSemester' => $targetSemester,
                'receiptNo'      => $paymentResult['receiptNo'],
                'amountPaid'     => $amount,
                'paymentMethod'  => $paymentMethod,
                'paymentStatus'  => $paymentResult['status'],
                'promotionId'    => $promoHistoryId,
            ];
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Logger::exception($e, 'student_promotion_service', ['student_id' => $studentId, 'target_sem' => $targetSemester]);
            throw $e;
        }
    }

    public function validateRegistrationWindow(string $courseId, int $semester, string $sessionId): void
    {
        $stmt = $this->pdo->prepare('
            SELECT start_date, end_date, grace_days, is_active
            FROM registration_windows
            WHERE course_id = :course_id AND semester = :semester AND academic_session_id = :session_id AND is_active = 1
            LIMIT 1
        ');
        $stmt->execute([
            'course_id'  => $courseId,
            'semester'   => $semester,
            'session_id' => $sessionId,
        ]);
        $window = $stmt->fetch();

        if (!$window) {
            return;
        }

        $now = new DateTimeImmutable();
        $startDate = new DateTimeImmutable((string)$window['start_date']);
        $endDate = new DateTimeImmutable((string)$window['end_date']);
        $graceDays = (int)($window['grace_days'] ?? CollegeConfig::OVERDUE_GRACE_DAYS);
        $finalDeadline = $endDate->modify("+{$graceDays} days");

        if ($now < $startDate) {
            throw new RuntimeException("Registration window for Semester {$semester} is not yet open. It begins on " . $startDate->format('d M Y') . '.');
        }

        if ($now > $finalDeadline) {
            throw new RuntimeException("Registration window for Semester {$semester} closed on " . $finalDeadline->format('d M Y') . ' (including grace period).');
        }
    }
}
