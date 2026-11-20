<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuthServer;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use PDO;
use Throwable;

class FacultyController
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function index(Request $request): Response
    {
        $user = Auth::requireRole(['FACULTY', 'SUPER_ADMIN', 'ADMIN']);

        $facultyProfile = $this->resolveFacultyProfile($user);
        $facultyId = $facultyProfile['id'] ?? null;

        $subjects = $this->getFacultySubjects($facultyProfile);
        $recentSessions = $this->getRecentAttendanceSessions($facultyId);
        $recentCia = $this->getRecentCiaAssessments($facultyId);

        return Response::view('pages/faculty/index', [
            'user'           => $user,
            'facultyProfile' => $facultyProfile,
            'subjects'       => $subjects,
            'recentSessions' => $recentSessions,
            'recentCia'      => $recentCia,
            'csrfToken'      => AuthServer::csrfToken(),
        ]);
    }

    public function getStudentsForSubject(Request $request): Response
    {
        Auth::requireRole(['FACULTY', 'SUPER_ADMIN', 'ADMIN']);

        $subjectId = trim((string)$request->input('subject_id', ''));
        $semester = (int)($request->input('semester') ?? 1);

        if ($subjectId === '') {
            return Response::json(['success' => false, 'error' => 'Subject ID is required.'], 422);
        }

        $stmt = $this->pdo->prepare('
            SELECT s.id AS student_id, s.college_roll_no, s.registration_code, s.full_name,
                   s.current_semester, e.is_approved
            FROM student_subject_enrollments e
            JOIN students s ON e.student_id = s.id
            WHERE e.subject_id = :subject_id AND e.semester = :sem AND s.status = \'ADMITTED\'
            ORDER BY s.college_roll_no ASC, s.full_name ASC
        ');
        $stmt->execute(['subject_id' => $subjectId, 'sem' => $semester]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $ciaStmt = $this->pdo->prepare('
            SELECT student_id, mid_term_exam, assignment, attendance_score, total_cia_score, is_finalized
            FROM continuous_internal_assessments
            WHERE subject_id = :subject_id AND semester = :sem
        ');
        $ciaStmt->execute(['subject_id' => $subjectId, 'sem' => $semester]);
        $ciaRows = $ciaStmt->fetchAll(PDO::FETCH_ASSOC);

        $ciaMap = [];
        foreach ($ciaRows as $c) {
            $ciaMap[$c['student_id']] = $c;
        }

        foreach ($students as &$st) {
            $sid = (string)$st['student_id'];
            $st['cia'] = $ciaMap[$sid] ?? [
                'mid_term_exam'    => 0.0,
                'assignment'       => 0.0,
                'attendance_score' => 0.0,
                'total_cia_score'  => 0.0,
                'is_finalized'     => 0,
            ];
        }
        unset($st);

        return Response::json([
            'success'  => true,
            'students' => $students,
        ]);
    }

    public function saveAttendance(Request $request): Response
    {
        $user = Auth::requireRole(['FACULTY', 'SUPER_ADMIN', 'ADMIN']);

        if (!AuthServer::verifyCsrf($request)) {
            return Response::json(['success' => false, 'error' => 'CSRF verification failed.'], 400);
        }

        $rules = [
            'subject_id'   => 'required',
            'semester'     => 'required|integer',
            'session_date' => 'required',
            'time_slot'    => 'required',
            'records'      => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return Response::json(['success' => false, 'error' => $validator->first()], 422);
        }

        $facultyProfile = $this->resolveFacultyProfile($user);
        $facultyId = $facultyProfile['id'] ?? null;
        if (!$facultyId) {
            return Response::json(['success' => false, 'error' => 'Faculty profile required to record attendance.'], 403);
        }

        $subjectId = trim((string)$request->input('subject_id'));
        $semester = (int)$request->input('semester');
        $sessionDate = date('Y-m-d', strtotime((string)$request->input('session_date')));
        $timeSlot = trim((string)$request->input('time_slot'));
        $roomNo = trim((string)($request->input('room_no') ?? ''));
        $topic = trim((string)($request->input('topic_covered') ?? ''));
        $records = (array)$request->input('records');

        $sessStmt = $this->pdo->query('SELECT id FROM academic_sessions WHERE is_current = 1 LIMIT 1');
        $academicSessionId = $sessStmt->fetchColumn() ?: 'sess-2026-2030';

        $totalPresent = 0;
        $totalAbsent = 0;
        foreach ($records as $r) {
            $status = strtoupper(trim((string)($r['status'] ?? 'PRESENT')));
            if ($status === 'PRESENT') {
                $totalPresent++;
            } else {
                $totalAbsent++;
            }
        }

        $this->pdo->beginTransaction();

        try {
            $sessionId = sprintf('att_ses_%s_%s', date('YmdHis'), bin2hex(random_bytes(3)));

            $insSession = $this->pdo->prepare('
                INSERT INTO attendance_sessions (
                    id, faculty_id, subject_id, academic_session_id, semester,
                    session_date, time_slot, room_no, topic_covered,
                    total_present, total_absent, created_at, updated_at
                ) VALUES (
                    :id, :fac_id, :sub_id, :sess_id, :sem,
                    :s_date, :slot, :room, :topic,
                    :p_count, :a_count, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )
            ');
            $insSession->execute([
                'id'      => $sessionId,
                'fac_id'  => $facultyId,
                'sub_id'  => $subjectId,
                'sess_id' => $academicSessionId,
                'sem'     => $semester,
                's_date'  => $sessionDate,
                'slot'    => $timeSlot,
                'room'    => $roomNo,
                'topic'   => $topic,
                'p_count' => $totalPresent,
                'a_count' => $totalAbsent,
            ]);

            $insRecord = $this->pdo->prepare('
                INSERT INTO attendance_records (
                    id, session_id, student_id, faculty_id, subject_id, semester, date, status, remarks, created_at
                ) VALUES (
                    :id, :session_id, :student_id, :fac_id, :sub_id, :sem, :rec_date, :status, :remarks, CURRENT_TIMESTAMP
                )
            ');

            foreach ($records as $rec) {
                $studentId = trim((string)($rec['student_id'] ?? ''));
                if ($studentId === '') {
                    continue;
                }
                $status = strtoupper(trim((string)($rec['status'] ?? 'PRESENT')));
                if (!in_array($status, ['PRESENT', 'ABSENT', 'LEAVE', 'EXCUSED'], true)) {
                    $status = 'PRESENT';
                }

                $recordId = sprintf('att_rec_%s_%s', date('YmdHis'), bin2hex(random_bytes(3)));
                $insRecord->execute([
                    'id'         => $recordId,
                    'session_id' => $sessionId,
                    'student_id' => $studentId,
                    'fac_id'     => $facultyId,
                    'sub_id'     => $subjectId,
                    'sem'        => $semester,
                    'rec_date'   => $sessionDate,
                    'status'     => $status,
                    'remarks'    => $rec['remarks'] ?? null,
                ]);
            }

            $this->pdo->commit();

            return Response::json([
                'success'      => true,
                'message'      => "Attendance registered for {$totalPresent} present, {$totalAbsent} absent students.",
                'sessionId'    => $sessionId,
                'totalPresent' => $totalPresent,
                'totalAbsent'  => $totalAbsent,
            ], 201);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Logger::exception($e, 'save_attendance', ['faculty_id' => $facultyId, 'subject_id' => $subjectId]);
            return Response::json(['success' => false, 'error' => 'Failed to save attendance: ' . $e->getMessage()], 500);
        }
    }

    public function saveCia(Request $request): Response
    {
        $user = Auth::requireRole(['FACULTY', 'SUPER_ADMIN', 'ADMIN']);

        if (!AuthServer::verifyCsrf($request)) {
            return Response::json(['success' => false, 'error' => 'CSRF verification failed.'], 400);
        }

        $rules = [
            'subject_id' => 'required',
            'student_id' => 'required',
            'semester'   => 'required|integer',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return Response::json(['success' => false, 'error' => $validator->first()], 422);
        }

        $facultyProfile = $this->resolveFacultyProfile($user);
        $facultyId = $facultyProfile['id'] ?? null;
        if (!$facultyId) {
            return Response::json(['success' => false, 'error' => 'Faculty profile required to enter CIA scores.'], 403);
        }

        $subjectId = trim((string)$request->input('subject_id'));
        $studentId = trim((string)$request->input('student_id'));
        $semester = (int)$request->input('semester');

        $midTerm = (float)($request->input('mid_term_exam') ?? 0.0);
        $assignment = (float)($request->input('assignment') ?? 0.0);
        $attendance = (float)($request->input('attendance_score') ?? 0.0);

        if ($midTerm < 0 || $midTerm > 15) {
            return Response::json(['success' => false, 'error' => 'Mid-term Exam score must be between 0 and 15 (BRABU statutory max 15).'], 422);
        }

        if ($assignment < 0 || $assignment > 10) {
            return Response::json(['success' => false, 'error' => 'Assignment score must be between 0 and 10 (BRABU statutory max 10).'], 422);
        }

        if ($attendance < 0 || $attendance > 5) {
            return Response::json(['success' => false, 'error' => 'Attendance score must be between 0 and 5 (BRABU statutory max 5).'], 422);
        }

        $totalCia = round($midTerm + $assignment + $attendance, 2);

        try {
            $check = $this->pdo->prepare('
                SELECT id FROM continuous_internal_assessments
                WHERE student_id = :sid AND subject_id = :sub_id AND semester = :sem
                LIMIT 1
            ');
            $check->execute(['sid' => $studentId, 'sub_id' => $subjectId, 'sem' => $semester]);
            $existing = $check->fetch();

            if ($existing) {
                $upd = $this->pdo->prepare('
                    UPDATE continuous_internal_assessments
                    SET mid_term_exam = :mid,
                        assignment = :asgn,
                        attendance_score = :att,
                        total_cia_score = :total,
                        evaluation_date = CURRENT_TIMESTAMP,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ');
                $upd->execute([
                    'mid'   => $midTerm,
                    'asgn'  => $assignment,
                    'att'   => $attendance,
                    'total' => $totalCia,
                    'id'    => $existing['id'],
                ]);
                $ciaId = $existing['id'];
            } else {
                $ciaId = sprintf('cia_%s_%s', date('YmdHis'), bin2hex(random_bytes(3)));
                $ins = $this->pdo->prepare('
                    INSERT INTO continuous_internal_assessments (
                        id, faculty_id, subject_id, student_id, semester,
                        mid_term_exam, assignment, attendance_score, total_cia_score,
                        is_finalized, evaluation_date, created_at, updated_at
                    ) VALUES (
                        :id, :fac_id, :sub_id, :sid, :sem,
                        :mid, :asgn, :att, :total,
                        0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                    )
                ');
                $ins->execute([
                    'id'     => $ciaId,
                    'fac_id' => $facultyId,
                    'sub_id' => $subjectId,
                    'sid'    => $studentId,
                    'sem'    => $semester,
                    'mid'    => $midTerm,
                    'asgn'   => $assignment,
                    'att'    => $attendance,
                    'total'  => $totalCia,
                ]);
            }

            return Response::json([
                'success'       => true,
                'message'       => "CIA score recorded: {$totalCia} / 30",
                'ciaId'         => $ciaId,
                'studentId'     => $studentId,
                'midTerm'       => $midTerm,
                'assignment'    => $assignment,
                'attendance'    => $attendance,
                'totalCiaScore' => $totalCia,
            ]);
        } catch (Throwable $e) {
            Logger::exception($e, 'save_cia', ['student_id' => $studentId, 'subject_id' => $subjectId]);
            return Response::json(['success' => false, 'error' => 'Failed to record CIA marks: ' . $e->getMessage()], 500);
        }
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>|null
     */
    private function resolveFacultyProfile(array $user): ?array
    {
        $userId = $user['id'] ?? '';
        $stmt = $this->pdo->prepare('
            SELECT f.*, d.name AS department_name
            FROM faculty_profiles f
            LEFT JOIN departments d ON f.department_id = d.id
            WHERE f.user_id = :uid OR f.email = :email
            LIMIT 1
        ');
        $stmt->execute(['uid' => $userId, 'email' => $user['email'] ?? '']);
        $fac = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fac) {
            return $fac;
        }

        if (in_array($user['role'] ?? '', ['SUPER_ADMIN', 'ADMIN'], true)) {
            $fallback = $this->pdo->query('
                SELECT f.*, d.name AS department_name
                FROM faculty_profiles f
                LEFT JOIN departments d ON f.department_id = d.id
                ORDER BY f.id ASC
                LIMIT 1
            ')->fetch(PDO::FETCH_ASSOC);
            if ($fallback) {
                return $fallback;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $facultyProfile
     * @return array<int, array<string, mixed>>
     */
    private function getFacultySubjects(?array $facultyProfile): array
    {
        if ($facultyProfile && !empty($facultyProfile['department_id'])) {
            $stmt = $this->pdo->prepare('
                SELECT s.id, s.code, s.name, s.semester, s.credits, s.category, c.name AS course_name
                FROM subjects s
                JOIN courses c ON s.course_id = c.id
                WHERE s.department_id = :dept_id
                ORDER BY s.semester ASC, s.name ASC
                LIMIT 30
            ');
            $stmt->execute(['dept_id' => $facultyProfile['department_id']]);
            $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($subs)) {
                return $subs;
            }
        }

        $all = $this->pdo->query('
            SELECT s.id, s.code, s.name, s.semester, s.credits, s.category, c.name AS course_name
            FROM subjects s
            JOIN courses c ON s.course_id = c.id
            ORDER BY s.semester ASC, s.name ASC
            LIMIT 30
        ');
        return $all->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getRecentAttendanceSessions(?string $facultyId): array
    {
        $params = [];
        $where = '';
        if ($facultyId) {
            $where = 'WHERE a.faculty_id = :fac_id';
            $params['fac_id'] = $facultyId;
        }

        $stmt = $this->pdo->prepare("
            SELECT a.*, s.name AS subject_name, s.code AS subject_code
            FROM attendance_sessions a
            JOIN subjects s ON a.subject_id = s.id
            {$where}
            ORDER BY a.session_date DESC, a.created_at DESC
            LIMIT 15
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getRecentCiaAssessments(?string $facultyId): array
    {
        $params = [];
        $where = '';
        if ($facultyId) {
            $where = 'WHERE c.faculty_id = :fac_id';
            $params['fac_id'] = $facultyId;
        }

        $stmt = $this->pdo->prepare("
            SELECT c.*, st.full_name AS student_name, st.college_roll_no,
                   s.name AS subject_name, s.code AS subject_code
            FROM continuous_internal_assessments c
            JOIN students st ON c.student_id = st.id
            JOIN subjects s ON c.subject_id = s.id
            {$where}
            ORDER BY c.updated_at DESC
            LIMIT 20
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
