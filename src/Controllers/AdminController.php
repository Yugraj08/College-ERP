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
use App\Core\Validator;
use App\Services\AcademicStructureService;
use App\Services\StudentDirectoryService;
use DateTimeImmutable;
use PDO;
use Throwable;

class AdminController
{
    private PDO $pdo;
    private AcademicStructureService $academicService;
    private StudentDirectoryService $directoryService;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->academicService = new AcademicStructureService($this->pdo);
        $this->directoryService = new StudentDirectoryService($this->pdo);
    }

    public function principal(Request $request): Response
    {
        $user = Auth::requireRole(['SUPER_ADMIN', 'ADMIN']);

        $stats = $this->getDashboardMetrics();
        $pendingAdmissions = $this->getPendingAdmissionsQueue();
        $windows = $this->getRegistrationWindows();
        $facultyList = $this->getFacultyList();
        $notices = $this->getRecentNotices();
        $grievances = $this->getPendingGrievances();
        $departments = $this->getDepartmentsList();
        $courses = $this->getCoursesList();
        $sessions = $this->getSessionsList();

        return Response::view('pages/admin/principal', [
            'user'              => $user,
            'stats'             => $stats,
            'pendingAdmissions' => $pendingAdmissions,
            'windows'           => $windows,
            'facultyList'       => $facultyList,
            'notices'           => $notices,
            'grievances'        => $grievances,
            'departments'       => $departments,
            'courses'           => $courses,
            'sessions'          => $sessions,
            'csrfToken'         => AuthServer::csrfToken(),
        ], 'layouts/admin');
    }

    public function students(Request $request): Response
    {
        $user = Auth::requireRole(['SUPER_ADMIN', 'ADMIN']);
        $courses = $this->getCoursesList();

        return Response::view('pages/admin/students', [
            'user'      => $user,
            'courses'   => $courses,
            'csrfToken' => AuthServer::csrfToken(),
        ], 'layouts/admin');
    }

    public function apiSearchStudents(Request $request): Response
    {
        Auth::requireRole(['SUPER_ADMIN', 'ADMIN']);

        $filters = [
            'search'      => $request->input('search'),
            'course_code' => $request->input('course_code'),
            'semester'    => $request->input('semester'),
            'status'      => $request->input('status'),
            'category'    => $request->input('category'),
        ];
        $page = (int)($request->input('page') ?? 1);
        $perPage = (int)($request->input('per_page') ?? 15);

        $result = $this->directoryService->searchStudents($filters, $page, $perPage);

        return Response::json([
            'success'    => true,
            'students'   => $result['students'],
            'pagination' => $result['pagination'],
        ]);
    }

    public function exportStudentsCsv(Request $request): Response
    {
        Auth::requireRole(['SUPER_ADMIN', 'ADMIN']);

        $filters = [
            'search'      => $request->input('search'),
            'course_code' => $request->input('course_code'),
            'semester'    => $request->input('semester'),
            'status'      => $request->input('status'),
            'category'    => $request->input('category'),
        ];

        $result = $this->directoryService->searchStudents($filters, 1, 5000);
        $students = $result['students'];

        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return Response::text('Failed to generate CSV', 500);
        }

        fputcsv($output, [
            'Application No',
            'Registration Code',
            'Roll No',
            'Full Name',
            'Father Name',
            'Course',
            'Semester',
            'Status',
            'Category',
            'Gender',
            'Phone',
            'Email',
            'Created At',
        ]);

        foreach ($students as $s) {
            fputcsv($output, [
                $s['application_no'] ?? '',
                $s['registration_code'] ?? '',
                $s['college_roll_no'] ?? 'PENDING',
                $s['full_name'] ?? '',
                $s['father_name'] ?? '',
                $s['course_name'] ?? '',
                $s['current_semester'] ?? '',
                $s['status'] ?? '',
                $s['category'] ?? '',
                $s['gender'] ?? '',
                $s['phone'] ?? '',
                $s['email'] ?? '',
                $s['created_at'] ?? '',
            ]);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        $filename = 'mkr_students_export_' . date('Ymd_His') . '.csv';

        return new Response(
            $csvContent ?: '',
            200,
            [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            ]
        );
    }

    public function scrutinizeAdmission(Request $request): Response
    {
        Auth::requireRole(['SUPER_ADMIN', 'ADMIN']);

        if (!AuthServer::verifyCsrf($request)) {
            return Response::json(['success' => false, 'error' => 'CSRF verification failed.'], 400);
        }

        $studentId = trim((string)$request->input('student_id', ''));
        $action = strtoupper(trim((string)$request->input('action', '')));
        $remarks = trim((string)$request->input('remarks', ''));

        if ($studentId === '' || !in_array($action, ['APPROVE', 'REJECT'], true)) {
            return Response::json(['success' => false, 'error' => 'Invalid scrutiny parameters.'], 422);
        }

        $stmt = $this->pdo->prepare('
            SELECT s.*, c.code AS course_code, c.name AS course_name
            FROM students s
            JOIN courses c ON s.course_id = c.id
            WHERE s.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $studentId]);
        $student = $stmt->fetch();

        if (!$student) {
            return Response::json(['success' => false, 'error' => 'Student record not found.'], 404);
        }

        try {
            if ($action === 'APPROVE') {
                $year = (int)date('Y');
                $courseCode = (string)$student['course_code'];
                $rollNo = $this->academicService->generateSafeCollegeRollNo($year, $courseCode);

                $upd = $this->pdo->prepare('
                    UPDATE students
                    SET college_roll_no = :roll_no, status = \'ADMITTED\', updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ');
                $upd->execute(['roll_no' => $rollNo, 'id' => $studentId]);

                $updSem = $this->pdo->prepare('
                    UPDATE student_semester_records
                    SET status = \'ADMITTED\', updated_at = CURRENT_TIMESTAMP
                    WHERE student_id = :sid AND semester = 1
                ');
                $updSem->execute(['sid' => $studentId]);

                $updEnr = $this->pdo->prepare('
                    UPDATE student_subject_enrollments
                    SET is_approved = 1
                    WHERE student_id = :sid AND semester = 1
                ');
                $updEnr->execute(['sid' => $studentId]);

                $this->logAction('ADMISSION_APPROVED', $studentId, [
                    'college_roll_no' => $rollNo,
                    'approved_at'     => date('Y-m-d H:i:s'),
                    'remarks'         => $remarks,
                ], "Admission approved for {$student['full_name']}. Roll No: {$rollNo}");

                return Response::json([
                    'success'   => true,
                    'message'   => "Admission approved. Roll number {$rollNo} assigned.",
                    'studentId' => $studentId,
                    'rollNo'    => $rollNo,
                    'status'    => 'ADMITTED',
                ]);
            }

            $upd = $this->pdo->prepare('
                UPDATE students
                SET status = \'REJECTED\', updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ');
            $upd->execute(['id' => $studentId]);

            $this->logAction('ADMISSION_REJECTED', $studentId, [
                'rejected_at' => date('Y-m-d H:i:s'),
                'remarks'     => $remarks,
            ], "Admission rejected for {$student['full_name']}. Remarks: {$remarks}");

            return Response::json([
                'success'   => true,
                'message'   => "Admission rejected for student {$student['full_name']}.",
                'studentId' => $studentId,
                'status'    => 'REJECTED',
            ]);
        } catch (Throwable $e) {
            Logger::exception($e, 'scrutinize_admission', ['student_id' => $studentId, 'action' => $action]);
            return Response::json(['success' => false, 'error' => 'Scrutiny update failed: ' . $e->getMessage()], 500);
        }
    }

    public function saveRegistrationWindow(Request $request): Response
    {
        Auth::requireRole(['SUPER_ADMIN', 'ADMIN']);

        if (!AuthServer::verifyCsrf($request)) {
            return Response::json(['success' => false, 'error' => 'CSRF verification failed.'], 400);
        }

        $rules = [
            'course_id'           => 'required',
            'semester'            => 'required|integer',
            'academic_session_id' => 'required',
            'start_date'          => 'required',
            'end_date'            => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return Response::json(['success' => false, 'error' => $validator->first()], 422);
        }

        $courseId = trim((string)$request->input('course_id'));
        $semester = (int)$request->input('semester');
        $sessionId = trim((string)$request->input('academic_session_id'));
        $startDate = date('Y-m-d H:i:s', strtotime((string)$request->input('start_date')));
        $endDate = date('Y-m-d H:i:s', strtotime((string)$request->input('end_date')));

        if (strtotime($endDate) < strtotime($startDate)) {
            return Response::json(['success' => false, 'error' => 'Window end date must be on or after start date.'], 422);
        }

        $graceDays = (int)($request->input('grace_days') ?? CollegeConfig::OVERDUE_GRACE_DAYS);
        $isActive = (int)($request->input('is_active') ?? 1);

        try {
            $check = $this->pdo->prepare('
                SELECT id FROM registration_windows
                WHERE course_id = :cid AND semester = :sem AND academic_session_id = :sess_id
                LIMIT 1
            ');
            $check->execute(['cid' => $courseId, 'sem' => $semester, 'sess_id' => $sessionId]);
            $existing = $check->fetch();

            if ($existing) {
                $upd = $this->pdo->prepare('
                    UPDATE registration_windows
                    SET start_date = :s_date, end_date = :e_date, grace_days = :grace, is_active = :active, updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ');
                $upd->execute([
                    's_date' => $startDate,
                    'e_date' => $endDate,
                    'grace'  => $graceDays,
                    'active' => $isActive,
                    'id'     => $existing['id'],
                ]);
                $windowId = $existing['id'];
            } else {
                $windowId = sprintf('win_%s_%s', date('YmdHis'), bin2hex(random_bytes(3)));
                $ins = $this->pdo->prepare('
                    INSERT INTO registration_windows (
                        id, academic_session_id, course_id, semester, start_date, end_date, grace_days, is_active, created_at, updated_at
                    ) VALUES (
                        :id, :sess_id, :cid, :sem, :s_date, :e_date, :grace, :active, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                    )
                ');
                $ins->execute([
                    'id'      => $windowId,
                    'sess_id' => $sessionId,
                    'cid'     => $courseId,
                    'sem'     => $semester,
                    's_date'  => $startDate,
                    'e_date'  => $endDate,
                    'grace'   => $graceDays,
                    'active'  => $isActive,
                ]);
            }

            return Response::json([
                'success'  => true,
                'message'  => 'Registration window updated successfully.',
                'windowId' => $windowId,
            ]);
        } catch (Throwable $e) {
            Logger::exception($e, 'save_registration_window');
            return Response::json(['success' => false, 'error' => 'Failed to save registration window: ' . $e->getMessage()], 500);
        }
    }

    public function createFaculty(Request $request): Response
    {
        Auth::requireRole(['SUPER_ADMIN', 'ADMIN']);

        if (!AuthServer::verifyCsrf($request)) {
            return Response::json(['success' => false, 'error' => 'CSRF verification failed.'], 400);
        }

        $rules = [
            'full_name'     => 'required|min:3',
            'email'         => 'required|email',
            'phone'         => 'required|phone',
            'department_id' => 'required',
            'designation'   => 'required',
            'password'      => 'required|min:6',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return Response::json(['success' => false, 'error' => $validator->first()], 422);
        }

        $email = trim((string)$request->input('email'));
        $phone = trim((string)$request->input('phone'));
        $name = trim((string)$request->input('full_name'));
        $deptId = trim((string)$request->input('department_id'));
        $designation = trim((string)$request->input('designation'));
        $password = (string)$request->input('password');

        $check = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $check->execute(['email' => $email]);
        if ($check->fetch()) {
            return Response::json(['success' => false, 'error' => 'User account with this email already exists.'], 422);
        }

        $this->pdo->beginTransaction();

        try {
            $userId = sprintf('usr_fac_%s_%s', date('YmdHis'), bin2hex(random_bytes(3)));
            $profileId = sprintf('fac_%s_%s', date('YmdHis'), bin2hex(random_bytes(3)));
            $employeeCode = sprintf('FAC-%s', strtoupper(bin2hex(random_bytes(3))));

            $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            $insUser = $this->pdo->prepare('
                INSERT INTO users (id, username, email, password_hash, role, is_active, created_at, updated_at)
                VALUES (:id, :username, :email, :hash, \'FACULTY\', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ');
            $insUser->execute([
                'id'       => $userId,
                'username' => $employeeCode,
                'email'    => $email,
                'hash'     => $passwordHash,
            ]);

            $insProfile = $this->pdo->prepare('
                INSERT INTO faculty_profiles (
                    id, user_id, employee_code, full_name, designation, department_id,
                    email, phone, qualification, specialization, joining_date, is_active,
                    created_at, updated_at
                ) VALUES (
                    :id, :uid, :code, :name, :desig, :dept_id,
                    :email, :phone, :qual, :spec, CURRENT_DATE, 1,
                    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )
            ');
            $insProfile->execute([
                'id'      => $profileId,
                'uid'     => $userId,
                'code'    => $employeeCode,
                'name'    => $name,
                'desig'   => $designation,
                'dept_id' => $deptId,
                'email'   => $email,
                'phone'   => $phone,
                'qual'    => $request->input('qualification') ?? 'Post Graduate / UGC-NET',
                'spec'    => $request->input('specialization') ?? 'General',
            ]);

            $this->pdo->commit();

            return Response::json([
                'success'      => true,
                'message'      => "Faculty account created successfully. Staff Code: {$employeeCode}",
                'employeeCode' => $employeeCode,
                'facultyId'    => $profileId,
            ], 201);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Logger::exception($e, 'create_faculty', ['email' => $email]);
            return Response::json(['success' => false, 'error' => 'Failed to create faculty member: ' . $e->getMessage()], 500);
        }
    }

    public function toggleFacultyStatus(Request $request): Response
    {
        Auth::requireRole(['SUPER_ADMIN', 'ADMIN']);

        if (!AuthServer::verifyCsrf($request)) {
            return Response::json(['success' => false, 'error' => 'CSRF verification failed.'], 400);
        }

        $facultyId = trim((string)$request->input('faculty_id', ''));
        if ($facultyId === '') {
            return Response::json(['success' => false, 'error' => 'Faculty ID required.'], 422);
        }

        $stmt = $this->pdo->prepare('SELECT id, user_id, is_active FROM faculty_profiles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $facultyId]);
        $fac = $stmt->fetch();

        if (!$fac) {
            return Response::json(['success' => false, 'error' => 'Faculty member not found.'], 404);
        }

        $newStatus = ((int)$fac['is_active'] === 1) ? 0 : 1;

        $upd = $this->pdo->prepare('UPDATE faculty_profiles SET is_active = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $upd->execute(['status' => $newStatus, 'id' => $facultyId]);

        if (!empty($fac['user_id'])) {
            $updUser = $this->pdo->prepare('UPDATE users SET is_active = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :uid');
            $updUser->execute(['status' => $newStatus, 'uid' => $fac['user_id']]);
        }

        return Response::json([
            'success'   => true,
            'message'   => 'Faculty status updated.',
            'isActive'  => $newStatus === 1,
            'facultyId' => $facultyId,
        ]);
    }

    public function resetFacultyPassword(Request $request): Response
    {
        Auth::requireRole(['SUPER_ADMIN', 'ADMIN']);

        if (!AuthServer::verifyCsrf($request)) {
            return Response::json(['success' => false, 'error' => 'CSRF verification failed.'], 400);
        }

        $facultyId = trim((string)$request->input('faculty_id', ''));
        $newPassword = (string)$request->input('new_password', '');

        if ($facultyId === '' || strlen($newPassword) < 6) {
            return Response::json(['success' => false, 'error' => 'Password must be at least 6 characters.'], 422);
        }

        $stmt = $this->pdo->prepare('SELECT user_id FROM faculty_profiles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $facultyId]);
        $fac = $stmt->fetch();

        if (!$fac || empty($fac['user_id'])) {
            return Response::json(['success' => false, 'error' => 'Faculty user login record not found.'], 404);
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $upd = $this->pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = CURRENT_TIMESTAMP WHERE id = :uid');
        $upd->execute(['hash' => $hash, 'uid' => $fac['user_id']]);

        return Response::json([
            'success' => true,
            'message' => 'Faculty password has been reset successfully.',
        ]);
    }

    public function broadcastNotice(Request $request): Response
    {
        Auth::requireRole(['SUPER_ADMIN', 'ADMIN']);

        if (!AuthServer::verifyCsrf($request)) {
            return Response::json(['success' => false, 'error' => 'CSRF verification failed.'], 400);
        }

        $rules = [
            'title'    => 'required|min:4|max:255',
            'content'  => 'required|min:5',
            'category' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return Response::json(['success' => false, 'error' => $validator->first()], 422);
        }

        $title = trim((string)$request->input('title'));
        $content = trim((string)$request->input('content'));
        $category = trim((string)$request->input('category'));
        $id = sprintf('not_%s_%s', date('YmdHis'), bin2hex(random_bytes(3)));

        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO notices (id, title, content, category, is_published, published_at, created_at, updated_at)
                VALUES (:id, :title, :content, :cat, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ');
            $stmt->execute([
                'id'      => $id,
                'title'   => $title,
                'content' => $content,
                'cat'     => $category,
            ]);

            return Response::json([
                'success'  => true,
                'message'  => 'Institutional notice broadcasted successfully.',
                'noticeId' => $id,
            ], 201);
        } catch (Throwable $e) {
            Logger::exception($e, 'broadcast_notice');
            return Response::json(['success' => false, 'error' => 'Failed to broadcast notice: ' . $e->getMessage()], 500);
        }
    }

    public function deleteNotice(Request $request): Response
    {
        Auth::requireRole(['SUPER_ADMIN', 'ADMIN']);

        if (!AuthServer::verifyCsrf($request)) {
            return Response::json(['success' => false, 'error' => 'CSRF verification failed.'], 400);
        }

        $noticeId = trim((string)$request->input('notice_id', ''));
        if ($noticeId === '') {
            return Response::json(['success' => false, 'error' => 'Notice ID is required.'], 422);
        }

        try {
            $stmt = $this->pdo->prepare('DELETE FROM notices WHERE id = :id');
            $stmt->execute(['id' => $noticeId]);

            $auditId = sprintf('aud_%s_%s', date('YmdHis'), bin2hex(random_bytes(4)));
            $audit = $this->pdo->prepare('
                INSERT INTO audit_logs (id, user_id, action, entity, entity_id, details, timestamp)
                VALUES (:id, NULL, :action, \'notices\', :eid, :details, CURRENT_TIMESTAMP)
            ');
            $audit->execute([
                'id'      => $auditId,
                'action'  => 'DELETE_NOTICE',
                'eid'     => $noticeId,
                'details' => "Notice {$noticeId} removed by administrator",
            ]);

            return Response::json([
                'success' => true,
                'message' => 'Announcement removed successfully.',
            ]);
        } catch (Throwable $e) {
            Logger::exception($e, 'delete_notice');
            return Response::json(['success' => false, 'error' => 'Failed to remove notice: ' . $e->getMessage()], 500);
        }
    }

    public function resolveGrievance(Request $request): Response
    {
        Auth::requireRole(['SUPER_ADMIN', 'ADMIN']);

        if (!AuthServer::verifyCsrf($request)) {
            return Response::json(['success' => false, 'error' => 'CSRF verification failed.'], 400);
        }

        $ticketNo = trim((string)$request->input('ticket_no', ''));
        $status = strtoupper(trim((string)$request->input('status', 'RESOLVED')));
        $remarks = trim((string)$request->input('admin_remarks', ''));

        if ($ticketNo === '' || !in_array($status, ['IN_REVIEW', 'RESOLVED', 'REJECTED'], true)) {
            return Response::json(['success' => false, 'error' => 'Invalid resolution parameters.'], 422);
        }

        try {
            $stmt = $this->pdo->prepare('
                UPDATE grievance_tickets
                SET status = :status, admin_remarks = :remarks, resolved_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE ticket_no = :no
            ');
            $stmt->execute([
                'status'  => $status,
                'remarks' => $remarks,
                'no'      => $ticketNo,
            ]);

            return Response::json([
                'success' => true,
                'message' => "Grievance ticket {$ticketNo} updated to {$status}.",
            ]);
        } catch (Throwable $e) {
            Logger::exception($e, 'resolve_grievance', ['ticket_no' => $ticketNo]);
            return Response::json(['success' => false, 'error' => 'Failed to update grievance: ' . $e->getMessage()], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getDashboardMetrics(): array
    {
        $pendingAdm = (int)$this->pdo->query('SELECT COUNT(*) FROM students WHERE status = \'APPLIED\'')->fetchColumn();
        $totalAdm = (int)$this->pdo->query('SELECT COUNT(*) FROM students WHERE status = \'ADMITTED\'')->fetchColumn();
        $activeFac = (int)$this->pdo->query('SELECT COUNT(*) FROM faculty_profiles WHERE is_active = 1')->fetchColumn();
        $openTickets = (int)$this->pdo->query('SELECT COUNT(*) FROM grievance_tickets WHERE status IN (\'PENDING\', \'IN_REVIEW\')')->fetchColumn();

        $todayFee = (float)$this->pdo->query('
            SELECT COALESCE(SUM(amount), 0) FROM fee_payments
            WHERE status = \'COMPLETED\' AND DATE(paid_at) = CURRENT_DATE
        ')->fetchColumn();

        $totalFee = (float)$this->pdo->query('
            SELECT COALESCE(SUM(amount), 0) FROM fee_payments
            WHERE status = \'COMPLETED\'
        ')->fetchColumn();

        $pendingFee = (float)$this->pdo->query('
            SELECT COALESCE(SUM(amount), 0) FROM fee_payments
            WHERE status = \'PENDING\'
        ')->fetchColumn();

        return [
            'pendingAdmissions'    => $pendingAdm,
            'admittedStudents'     => $totalAdm,
            'activeFaculty'        => $activeFac,
            'openGrievances'       => $openTickets,
            'todayCollection'      => $todayFee,
            'totalCollection'      => $totalFee,
            'pendingReconcile'     => $pendingFee,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getPendingAdmissionsQueue(): array
    {
        $stmt = $this->pdo->query('
            SELECT s.id, s.application_no, s.registration_code, s.full_name,
                   s.date_of_birth, s.phone, s.email, s.category, s.created_at,
                   c.name AS course_name, c.code AS course_code
            FROM students s
            JOIN courses c ON s.course_id = c.id
            WHERE s.status = \'APPLIED\'
            ORDER BY s.created_at ASC
            LIMIT 20
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getRegistrationWindows(): array
    {
        $stmt = $this->pdo->query('
            SELECT rw.*, c.name AS course_name, c.code AS course_code, sess.name AS session_name
            FROM registration_windows rw
            JOIN courses c ON rw.course_id = c.id
            JOIN academic_sessions sess ON rw.academic_session_id = sess.id
            ORDER BY rw.start_date DESC
            LIMIT 20
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getFacultyList(): array
    {
        $stmt = $this->pdo->query('
            SELECT f.*, d.name AS department_name
            FROM faculty_profiles f
            JOIN departments d ON f.department_id = d.id
            ORDER BY f.full_name ASC
            LIMIT 50
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getRecentNotices(): array
    {
        $stmt = $this->pdo->query('
            SELECT * FROM notices
            ORDER BY published_at DESC
            LIMIT 15
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getPendingGrievances(): array
    {
        $stmt = $this->pdo->query('
            SELECT * FROM grievance_tickets
            WHERE status IN (\'PENDING\', \'IN_REVIEW\')
            ORDER BY created_at ASC
            LIMIT 20
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getDepartmentsList(): array
    {
        return $this->pdo->query('SELECT id, name, code, faculty_type FROM departments ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getCoursesList(): array
    {
        return $this->pdo->query('SELECT id, name, code FROM courses ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getSessionsList(): array
    {
        return $this->pdo->query('SELECT id, name, is_current FROM academic_sessions ORDER BY start_year DESC')->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $newValues
     */
    private function logAction(string $action, string $entityId, array $newValues, string $details): void
    {
        $auditId = sprintf('aud_%s_%s', date('YmdHis'), bin2hex(random_bytes(3)));
        $stmt = $this->pdo->prepare('
            INSERT INTO audit_logs (id, user_id, action, entity, entity_id, new_values, details, timestamp)
            VALUES (:id, NULL, :action, \'students\', :entity_id, :new_values, :details, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            'id'         => $auditId,
            'action'     => $action,
            'entity_id'  => $entityId,
            'new_values' => json_encode($newValues),
            'details'    => $details,
        ]);
    }
}
