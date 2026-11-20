<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\CollegeConfig;
use App\Core\AuthServer;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\AcademicStructureService;
use App\Services\Payment\PaymentService;
use PDO;
use RuntimeException;
use Throwable;

class AdmissionController
{
    private PDO $pdo;
    private AcademicStructureService $academicService;
    private PaymentService $paymentService;

    public function __construct(
        ?PDO $pdo = null,
        ?AcademicStructureService $academicService = null,
        ?PaymentService $paymentService = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->academicService = $academicService ?? new AcademicStructureService($this->pdo);
        $this->paymentService = $paymentService ?? new PaymentService($this->pdo);
    }

    public function index(Request $request): Response
    {
        $csrfToken = AuthServer::csrfToken();
        $courses = CollegeConfig::COURSE_CATALOG;
        $facultySubjects = CollegeConfig::FACULTY_SUBJECTS;

        return Response::view('pages/admission/index', [
            'title'           => 'Online Admission & Semester Progression (Session 2026–2030)',
            'csrfToken'       => $csrfToken,
            'courses'         => $courses,
            'facultySubjects' => $facultySubjects,
        ]);
    }

    public function applyFresh(Request $request): Response
    {
        if (!AuthServer::verifyCsrf($request)) {
            return Response::json([
                'success' => false,
                'error'   => 'Security session verification failed. Please refresh the page and try again.',
            ], 400);
        }

        $rules = [
            'full_name'     => 'required|min:3|max:191',
            'father_name'   => 'required|min:3|max:191',
            'mother_name'   => 'required|min:3|max:191',
            'date_of_birth' => 'required',
            'gender'        => 'required',
            'category'      => 'required',
            'phone'         => 'required|phone',
            'email'         => 'required|email',
            'address'       => 'required|min:5',
            'course_code'   => 'required',
            'mjc_subject'   => 'required',
            'mic_subject'   => 'required',
            'mdc_subject'   => 'required',
            'aec_subject'   => 'required',
            'sec_subject'   => 'required',
            'vac_subject'   => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return Response::json([
                'success' => false,
                'error'   => $validator->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $courseCode = strtoupper(trim((string)$request->input('course_code')));
        if (!isset(CollegeConfig::COURSE_CATALOG[$courseCode])) {
            return Response::json([
                'success' => false,
                'error'   => "Invalid academic course selection: {$courseCode}",
            ], 422);
        }

        $year = (int)date('Y');
        $expectedFee = CollegeConfig::getCourseFee($courseCode);
        $paymentMethod = strtoupper(trim((string)$request->input('payment_method', 'ONLINE_GATEWAY')));
        if (!in_array($paymentMethod, ['ONLINE_GATEWAY', 'UPI', 'BANK_CHALLAN'], true)) {
            $paymentMethod = 'ONLINE_GATEWAY';
        }

        $paymentDetails = (array)($request->input('payment_details') ?? []);
        if (empty($paymentDetails)) {
            $paymentDetails = [
                'orderId'        => $request->input('order_id') ?? $request->input('gateway_order_id'),
                'transactionRef' => $request->input('transaction_ref') ?? $request->input('utr_number'),
                'signature'      => $request->input('signature'),
                'utrNumber'      => $request->input('utr_number'),
                'journalNo'      => $request->input('journal_no'),
            ];
        }

        $this->pdo->beginTransaction();

        try {
            $academicInfo = $this->academicService->ensureAcademicSessionAndCourse($courseCode);
            $codes = $this->academicService->generateSafeAdmissionCodes($year);
            $applicationNo = $codes['applicationNo'];
            $registrationCode = $codes['registrationCode'];

            $studentId = sprintf('std_%s_%s', date('YmdHis'), bin2hex(random_bytes(3)));

            $dob = date('Y-m-d', strtotime((string)$request->input('date_of_birth')));

            $insertStudent = $this->pdo->prepare('
                INSERT INTO students (
                    id, application_no, registration_code, full_name,
                    father_name, mother_name, date_of_birth, gender,
                    category, phone, email, address, session_id,
                    course_id, current_semester, status, created_at, updated_at
                ) VALUES (
                    :id, :app_no, :reg_code, :full_name,
                    :father_name, :mother_name, :dob, :gender,
                    :category, :phone, :email, :address, :session_id,
                    :course_id, 1, \'APPLIED\', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )
            ');

            $insertStudent->execute([
                'id'          => $studentId,
                'app_no'      => $applicationNo,
                'reg_code'    => $registrationCode,
                'full_name'   => trim((string)$request->input('full_name')),
                'father_name' => trim((string)$request->input('father_name')),
                'mother_name' => trim((string)$request->input('mother_name')),
                'dob'         => $dob,
                'gender'      => trim((string)$request->input('gender')),
                'category'    => trim((string)$request->input('category', 'General')),
                'phone'       => trim((string)$request->input('phone')),
                'email'       => trim((string)$request->input('email')),
                'address'     => trim((string)$request->input('address')),
                'session_id'  => $academicInfo['sessionId'],
                'course_id'   => $academicInfo['courseId'],
            ]);

            $semProgId = sprintf('sem_%s_1', substr($studentId, 0, 16));
            $insertSemRecord = $this->pdo->prepare('
                INSERT INTO student_semester_records (
                    id, student_id, semester, academic_session_id, status, is_fee_cleared, enrolled_at, created_at, updated_at
                ) VALUES (
                    :id, :student_id, 1, :session_id, \'ENROLLED\', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )
            ');
            $insertSemRecord->execute([
                'id'         => $semProgId,
                'student_id' => $studentId,
                'session_id' => $academicInfo['sessionId'],
            ]);

            $selectedSubjects = [
                'MJC' => (string)$request->input('mjc_subject'),
                'MIC' => (string)$request->input('mic_subject'),
                'MDC' => (string)$request->input('mdc_subject'),
                'AEC' => (string)$request->input('aec_subject'),
                'SEC' => (string)$request->input('sec_subject'),
                'VAC' => (string)$request->input('vac_subject'),
            ];

            $enrolledSubjects = $this->academicService->enrollStudentSubjects(
                $studentId,
                1,
                $academicInfo['courseId'],
                $academicInfo['departmentId'],
                $selectedSubjects
            );

            $paymentResult = $this->paymentService->recordAndVerifyPayment(
                $studentId,
                1,
                'ADMISSION_CUM_SEM1',
                "Semester 1 Admission Fee - {$courseCode}",
                $expectedFee,
                $paymentMethod,
                $paymentDetails
            );

            $this->pdo->commit();

            return Response::json([
                'success'          => true,
                'message'          => 'Application and CBCS subject registration completed successfully.',
                'applicationNo'    => $applicationNo,
                'registrationCode' => $registrationCode,
                'receiptNo'        => $paymentResult['receiptNo'],
                'studentId'        => $studentId,
                'courseCode'       => $courseCode,
                'courseName'       => $academicInfo['courseName'],
                'amountPaid'       => $expectedFee,
                'paymentStatus'    => $paymentResult['status'],
                'subjects'         => $enrolledSubjects,
                'appliedAt'        => date('d-m-Y H:i:s'),
            ], 201);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Logger::exception($e, 'fresh_admission_apply', [
                'course' => $courseCode,
                'phone'  => $request->input('phone'),
            ]);

            return Response::json([
                'success' => false,
                'error'   => 'Failed to process admission application: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function statusLookup(Request $request): Response
    {
        $applicationNo = trim((string)$request->input('application_no', ''));
        $dobOrPhone = trim((string)$request->input('dob_or_phone', ''));

        if ($applicationNo === '') {
            return Response::json([
                'success' => false,
                'error'   => 'Please enter your Application Number (e.g. MKR-2026-00001).',
            ], 422);
        }

        $stmt = $this->pdo->prepare('
            SELECT s.id, s.application_no, s.registration_code, s.college_roll_no,
                   s.full_name, s.date_of_birth, s.phone, s.email, s.category,
                   s.current_semester, s.status, s.created_at,
                   c.name AS course_name, c.code AS course_code,
                   sess.name AS session_name
            FROM students s
            JOIN courses c ON s.course_id = c.id
            JOIN academic_sessions sess ON s.session_id = sess.id
            WHERE s.application_no = :app_no
               OR s.registration_code = :app_no
               OR s.college_roll_no = :app_no
            LIMIT 1
        ');
        $stmt->execute(['app_no' => $applicationNo]);
        $student = $stmt->fetch();

        if (!$student) {
            return Response::json([
                'success' => false,
                'error'   => 'No record found with the supplied identifier. Please verify your Application Number.',
            ], 404);
        }

        if ($dobOrPhone !== '') {
            $rawDob = (string)$student['date_of_birth'];
            $normalizedInput = str_replace(['/', '-', '.'], '', $dobOrPhone);
            $normalizedDob = str_replace(['/', '-', '.'], '', $rawDob);
            $cleanPhone = preg_replace('/\D/', '', (string)$student['phone']) ?? '';
            $inputPhone = preg_replace('/\D/', '', $dobOrPhone) ?? '';

            $isMatch = ($dobOrPhone === $rawDob || $normalizedInput === $normalizedDob || ($cleanPhone !== '' && $cleanPhone === $inputPhone));
            if (!$isMatch) {
                return Response::json([
                    'success' => false,
                    'error'   => 'Verification credential did not match our records for this application.',
                ], 403);
            }
        }

        $enrolledSubjects = $this->academicService->getEnrolledSubjects((string)$student['id'], (int)$student['current_semester']);

        $feeStmt = $this->pdo->prepare('
            SELECT receipt_no, target_semester, amount, payment_method, status, paid_at, transaction_ref
            FROM fee_payments
            WHERE student_id = :sid
            ORDER BY created_at DESC
        ');
        $feeStmt->execute(['sid' => $student['id']]);
        $payments = $feeStmt->fetchAll();

        return Response::json([
            'success'   => true,
            'student'   => [
                'applicationNo'    => $student['application_no'],
                'registrationCode' => $student['registration_code'],
                'collegeRollNo'    => $student['college_roll_no'],
                'fullName'         => $student['full_name'],
                'courseName'       => $student['course_name'],
                'courseCode'       => $student['course_code'],
                'session'          => $student['session_name'],
                'semester'         => (int)$student['current_semester'],
                'status'           => $student['status'],
                'appliedAt'        => $student['created_at'],
            ],
            'subjects'  => $enrolledSubjects,
            'payments'  => $payments,
        ]);
    }

    public function progressionRegister(Request $request): Response
    {
        if (!AuthServer::verifyCsrf($request)) {
            return Response::json([
                'success' => false,
                'error'   => 'Security session verification failed. Please refresh the page.',
            ], 400);
        }

        $identifier = trim((string)$request->input('identifier', ''));
        $targetSemester = (int)$request->input('target_semester', 2);
        $paymentMethod = strtoupper(trim((string)$request->input('payment_method', 'ONLINE_GATEWAY')));

        if ($identifier === '') {
            return Response::json([
                'success' => false,
                'error'   => 'Please enter your Registration Code or College Roll Number.',
            ], 422);
        }

        $stmt = $this->pdo->prepare('
            SELECT s.*, c.code AS course_code, c.name AS course_name, sess.name AS session_name
            FROM students s
            JOIN courses c ON s.course_id = c.id
            JOIN academic_sessions sess ON s.session_id = sess.id
            WHERE s.registration_code = :id OR s.college_roll_no = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $identifier]);
        $student = $stmt->fetch();

        if (!$student) {
            return Response::json([
                'success' => false,
                'error'   => 'Student not found in institutional records.',
            ], 404);
        }

        $currentSem = (int)$student['current_semester'];
        if ($targetSemester !== ($currentSem + 1)) {
            return Response::json([
                'success' => false,
                'error'   => "Invalid semester progression: Current semester is {$currentSem}. You can only register for Semester " . ($currentSem + 1) . '.',
            ], 422);
        }

        $feeCheck = $this->pdo->prepare('
            SELECT COUNT(*) FROM fee_payments
            WHERE student_id = :sid AND target_semester = :sem AND status = \'COMPLETED\'
        ');
        $feeCheck->execute(['sid' => $student['id'], 'sem' => $targetSemester]);
        if ((int)$feeCheck->fetchColumn() > 0) {
            return Response::json([
                'success' => false,
                'error'   => "Fee for Semester {$targetSemester} has already been paid and cleared.",
            ], 422);
        }

        $courseCode = (string)$student['course_code'];
        $feeBreakdown = CollegeConfig::calculateSemesterFeeBreakdown($courseCode, $targetSemester);
        $amount = $feeBreakdown['totalAmount'];

        $paymentDetails = (array)($request->input('payment_details') ?? [
            'orderId'        => $request->input('order_id'),
            'transactionRef' => $request->input('transaction_ref') ?? $request->input('utr_number'),
            'signature'      => $request->input('signature'),
            'utrNumber'      => $request->input('utr_number'),
            'journalNo'      => $request->input('journal_no'),
        ]);

        $this->pdo->beginTransaction();

        try {
            $paymentResult = $this->paymentService->recordAndVerifyPayment(
                (string)$student['id'],
                $targetSemester,
                'SEMESTER_PROMOTION',
                "Semester {$targetSemester} Progression Fee - {$courseCode}",
                $amount,
                $paymentMethod,
                $paymentDetails
            );

            $semProgId = sprintf('sem_%s_%d', substr((string)$student['id'], 0, 16), $targetSemester);
            $checkSem = $this->pdo->prepare('
                SELECT id FROM student_semester_records
                WHERE student_id = :sid AND semester = :sem
                LIMIT 1
            ');
            $checkSem->execute([
                'sid' => $student['id'],
                'sem' => $targetSemester,
            ]);
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
                    'id'      => $semProgId,
                    'sid'     => $student['id'],
                    'sem'     => $targetSemester,
                    'sess_id' => $student['session_id'],
                ]);
            }

            $updStudent = $this->pdo->prepare('
                UPDATE students
                SET current_semester = :sem, updated_at = CURRENT_TIMESTAMP
                WHERE id = :sid
            ');
            $updStudent->execute([
                'sem' => $targetSemester,
                'sid' => $student['id'],
            ]);

            $this->pdo->commit();

            return Response::json([
                'success'        => true,
                'message'        => "Progression registration for Semester {$targetSemester} completed successfully.",
                'receiptNo'      => $paymentResult['receiptNo'],
                'targetSemester' => $targetSemester,
                'amountPaid'     => $amount,
                'student'        => [
                    'collegeRollNo'    => $student['college_roll_no'],
                    'registrationCode' => $student['registration_code'],
                    'fullName'         => $student['full_name'],
                ],
            ]);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Logger::exception($e, 'progression_register', ['student_id' => $student['id'], 'target_sem' => $targetSemester]);
            return Response::json([
                'success' => false,
                'error'   => 'Progression registration failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}

