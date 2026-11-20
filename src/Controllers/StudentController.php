<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\CollegeConfig;
use App\Core\Auth;
use App\Core\AuthServer;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Services\PromotionService;
use PDO;
use Throwable;

class StudentController
{
    private PDO $pdo;
    private PromotionService $promotionService;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->promotionService = new PromotionService($this->pdo);
    }

    public function index(Request $request): Response
    {
        $user = Auth::requireRole(['STUDENT', 'SUPER_ADMIN', 'ADMIN']);

        $student = $this->resolveStudentProfile($user);
        if (!$student) {
            return Response::view('pages/student/index', [
                'user'      => $user,
                'student'   => null,
                'csrfToken' => AuthServer::csrfToken(),
            ]);
        }

        $studentId = (string)$student['id'];
        $currentSem = (int)$student['current_semester'];
        $courseCode = (string)$student['course_code'];

        $attendanceStats = $this->calculateAttendanceStats($studentId, $currentSem);
        $enrolledSubjects = $this->getEnrolledSubjects($studentId, $currentSem);
        $ciaScores = $this->getCiaScores($studentId, $currentSem);
        $feeLedger = $this->getFeeLedger($studentId);

        $nextSemester = $currentSem < CollegeConfig::MAX_SEMESTERS ? ($currentSem + 1) : null;
        $nextSemesterFee = null;
        $isNextSemPaid = false;

        if ($nextSemester !== null) {
            $feeBreakdown = CollegeConfig::calculateSemesterFeeBreakdown($courseCode, $nextSemester);
            $nextSemesterFee = $feeBreakdown['totalAmount'];

            $checkNextFee = $this->pdo->prepare('
                SELECT COUNT(*) FROM fee_payments
                WHERE student_id = :sid AND target_semester = :sem AND status = \'COMPLETED\'
            ');
            $checkNextFee->execute(['sid' => $studentId, 'sem' => $nextSemester]);
            $isNextSemPaid = ((int)$checkNextFee->fetchColumn()) > 0;
        }

        return Response::view('pages/student/index', [
            'user'             => $user,
            'student'          => $student,
            'attendance'       => $attendanceStats,
            'enrolledSubjects' => $enrolledSubjects,
            'ciaScores'        => $ciaScores,
            'feeLedger'        => $feeLedger,
            'nextSemester'     => $nextSemester,
            'nextSemesterFee'  => $nextSemesterFee,
            'isNextSemPaid'    => $isNextSemPaid,
            'csrfToken'        => AuthServer::csrfToken(),
        ]);
    }

    public function payProgression(Request $request): Response
    {
        $user = Auth::requireRole(['STUDENT', 'SUPER_ADMIN', 'ADMIN']);

        if (!AuthServer::verifyCsrf($request)) {
            return Response::json(['success' => false, 'error' => 'CSRF verification failed.'], 400);
        }

        $student = $this->resolveStudentProfile($user);
        if (!$student) {
            return Response::json(['success' => false, 'error' => 'Student record not found.'], 404);
        }

        $studentId = (string)$student['id'];
        $currentSem = (int)$student['current_semester'];
        $targetSem = (int)($request->input('target_semester') ?? ($currentSem + 1));
        $paymentMethod = strtoupper(trim((string)$request->input('payment_method', 'ONLINE_GATEWAY')));
        $utrNumber = trim((string)$request->input('utr_number', ''));
        $journalNo = trim((string)$request->input('journal_no', ''));

        if ($targetSem !== ($currentSem + 1)) {
            return Response::json([
                'success' => false,
                'error'   => "Sequential semester progression required. Current: {$currentSem}, Target must be: " . ($currentSem + 1) . '.',
            ], 422);
        }

        $courseCode = (string)$student['course_code'];
        $feeBreakdown = CollegeConfig::calculateSemesterFeeBreakdown($courseCode, $targetSem);
        $amount = $feeBreakdown['totalAmount'];

        $paymentDetails = [
            'orderId'        => $request->input('order_id'),
            'transactionRef' => $request->input('transaction_ref') ?: ($utrNumber ?: $journalNo),
            'utrNumber'      => $utrNumber,
            'journalNo'      => $journalNo,
        ];

        try {
            $result = $this->promotionService->promoteStudent(
                $studentId,
                $targetSem,
                $amount,
                $paymentMethod,
                $paymentDetails,
                'STUDENT_PORTAL',
                "Student self-service promotion to Semester {$targetSem}"
            );

            return Response::json([
                'success'        => true,
                'message'        => "Congratulations! You have successfully registered and cleared fees for Semester {$targetSem}.",
                'receiptNo'      => $result['receiptNo'],
                'targetSemester' => $targetSem,
                'amountPaid'     => $amount,
                'paymentStatus'  => $result['paymentStatus'],
            ]);
        } catch (Throwable $e) {
            Logger::exception($e, 'student_pay_progression', ['student_id' => $studentId, 'target_sem' => $targetSem]);
            return Response::json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function getReceipt(Request $request): Response
    {
        $user = Auth::requireRole(['STUDENT', 'SUPER_ADMIN', 'ADMIN']);

        $receiptNo = strtoupper(trim((string)$request->input('receipt_no', '')));
        if ($receiptNo === '') {
            return Response::json(['success' => false, 'error' => 'Receipt number is required.'], 422);
        }

        $stmt = $this->pdo->prepare('
            SELECT fp.*, s.full_name, s.college_roll_no, s.registration_code, s.application_no,
                   c.name AS course_name, c.code AS course_code, sess.name AS session_name
            FROM fee_payments fp
            JOIN students s ON fp.student_id = s.id
            JOIN courses c ON s.course_id = c.id
            JOIN academic_sessions sess ON s.session_id = sess.id
            WHERE fp.receipt_no = :rcpt
            LIMIT 1
        ');
        $stmt->execute(['rcpt' => $receiptNo]);
        $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$receipt) {
            return Response::json(['success' => false, 'error' => 'Receipt record not found.'], 404);
        }

        $student = $this->resolveStudentProfile($user);
        $role = $user['role'] ?? 'STUDENT';
        if ($role === 'STUDENT' && $student && (string)$receipt['student_id'] !== (string)$student['id']) {
            return Response::json(['success' => false, 'error' => 'Unauthorized access to requested fee receipt.'], 403);
        }

        return Response::json([
            'success' => true,
            'receipt' => $receipt,
        ]);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>|null
     */
    private function resolveStudentProfile(array $user): ?array
    {
        $userId = $user['id'] ?? '';
        $stmt = $this->pdo->prepare('
            SELECT s.*, c.name AS course_name, c.code AS course_code, sess.name AS session_name
            FROM students s
            JOIN courses c ON s.course_id = c.id
            JOIN academic_sessions sess ON s.session_id = sess.id
            WHERE s.user_id = :uid OR s.email = :email OR s.phone = :phone
            LIMIT 1
        ');
        $stmt->execute([
            'uid'   => $userId,
            'email' => $user['email'] ?? '',
            'phone' => $user['username'] ?? '',
        ]);
        $st = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($st) {
            return $st;
        }

        if (in_array($user['role'] ?? '', ['SUPER_ADMIN', 'ADMIN'], true)) {
            $fallback = $this->pdo->query('
                SELECT s.*, c.name AS course_name, c.code AS course_code, sess.name AS session_name
                FROM students s
                JOIN courses c ON s.course_id = c.id
                JOIN academic_sessions sess ON s.session_id = sess.id
                WHERE s.status = \'ADMITTED\'
                ORDER BY s.college_roll_no ASC, s.created_at DESC
                LIMIT 1
            ')->fetch(PDO::FETCH_ASSOC);
            if ($fallback) {
                return $fallback;
            }
        }

        return null;
    }

    /**
     * @return array{totalLectures: int, attendedLectures: int, percentage: float}
     */
    private function calculateAttendanceStats(string $studentId, int $semester): array
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) AS total,
                   SUM(CASE WHEN status = \'PRESENT\' THEN 1 ELSE 0 END) AS attended
            FROM attendance_records
            WHERE student_id = :sid AND semester = :sem
        ');
        $stmt->execute(['sid' => $studentId, 'sem' => $semester]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $total = (int)($row['total'] ?? 0);
        $attended = (int)($row['attended'] ?? 0);
        $pct = $total > 0 ? round(($attended / $total) * 100, 1) : 100.0;

        return [
            'totalLectures'    => $total,
            'attendedLectures' => $attended,
            'percentage'       => $pct,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getEnrolledSubjects(string $studentId, int $semester): array
    {
        $stmt = $this->pdo->prepare('
            SELECT s.code AS subject_code, s.name AS subject_name, s.category, s.credits
            FROM student_subject_enrollments e
            JOIN subjects s ON e.subject_id = s.id
            WHERE e.student_id = :sid AND e.semester = :sem
            ORDER BY
                CASE s.category
                    WHEN \'MJC\' THEN 1
                    WHEN \'MIC\' THEN 2
                    WHEN \'MDC\' THEN 3
                    WHEN \'AEC\' THEN 4
                    WHEN \'SEC\' THEN 5
                    WHEN \'VAC\' THEN 6
                    ELSE 7
                END
        ');
        $stmt->execute(['sid' => $studentId, 'sem' => $semester]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getCiaScores(string $studentId, int $semester): array
    {
        $stmt = $this->pdo->prepare('
            SELECT c.*, s.name AS subject_name, s.code AS subject_code, s.category
            FROM continuous_internal_assessments c
            JOIN subjects s ON c.subject_id = s.id
            WHERE c.student_id = :sid AND c.semester = :sem
            ORDER BY s.category ASC
        ');
        $stmt->execute(['sid' => $studentId, 'sem' => $semester]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getFeeLedger(string $studentId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM fee_payments
            WHERE student_id = :sid
            ORDER BY target_semester ASC, created_at DESC
        ');
        $stmt->execute(['sid' => $studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
