<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\CollegeConfig;
use App\Core\Database;
use PDO;
use RuntimeException;

class AcademicStructureService
{
    public const CBCS_CREDIT_MATRIX = [
        'MJC' => 6,
        'MIC' => 4,
        'MDC' => 4,
        'AEC' => 2,
        'SEC' => 2,
        'VAC' => 2,
    ];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * @return array{sessionId: string, sessionName: string, courseId: string, courseCode: string, courseName: string, departmentId: string, courseConfig: array<string, mixed>}
     */
    public function ensureAcademicSessionAndCourse(string $courseIdentifier): array
    {
        $courseConfig = CollegeConfig::resolveCourseConfig($courseIdentifier);
        $courseCode = $courseConfig['code'];
        $courseName = $courseConfig['name'];
        $facultyType = $courseConfig['facultyType'];

        $sessionStmt = $this->pdo->prepare('
            SELECT id, name FROM academic_sessions
            WHERE is_current = 1
            ORDER BY start_year DESC
            LIMIT 1
        ');
        $sessionStmt->execute();
        $session = $sessionStmt->fetch();

        if (!$session) {
            $sessionId = 'sess-2026-2030-curr';
            $sessionName = '2026-2030';
            $insertSession = $this->pdo->prepare('
                INSERT INTO academic_sessions (id, name, start_year, end_year, is_current, created_at, updated_at)
                VALUES (:id, :name, 2026, 2030, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ');
            $insertSession->execute(['id' => $sessionId, 'name' => $sessionName]);
        } else {
            $sessionId = (string)$session['id'];
            $sessionName = (string)$session['name'];
        }

        $deptStmt = $this->pdo->prepare('
            SELECT id FROM departments
            WHERE faculty_type = :faculty_type
            ORDER BY id ASC
            LIMIT 1
        ');
        $deptStmt->execute(['faculty_type' => $facultyType]);
        $dept = $deptStmt->fetch();

        if (!$dept) {
            $deptCodeMap = [
                'Social Science' => ['id' => 'dept-pols', 'code' => 'POLS', 'name' => 'Political Science'],
                'Humanities'     => ['id' => 'dept-hind', 'code' => 'HIND', 'name' => 'Hindi'],
                'Commerce'       => ['id' => 'dept-comm', 'code' => 'COMM', 'name' => 'Commerce'],
            ];
            $dInfo = $deptCodeMap[$facultyType] ?? ['id' => 'dept-gen', 'code' => 'GEN', 'name' => $facultyType];
            $insertDept = $this->pdo->prepare('
                INSERT INTO departments (id, name, code, faculty_type, created_at)
                VALUES (:id, :name, :code, :faculty, CURRENT_TIMESTAMP)
            ');
            $insertDept->execute([
                'id'      => $dInfo['id'],
                'name'    => $dInfo['name'],
                'code'    => $dInfo['code'],
                'faculty' => $facultyType,
            ]);
            $departmentId = $dInfo['id'];
        } else {
            $departmentId = (string)$dept['id'];
        }

        $courseStmt = $this->pdo->prepare('
            SELECT id, name, code, department_id FROM courses
            WHERE code = :code
            LIMIT 1
        ');
        $courseStmt->execute(['code' => $courseCode]);
        $course = $courseStmt->fetch();

        if (!$course) {
            $courseId = 'crs-' . strtolower(str_replace('_', '-', $courseCode));
            $insertCourse = $this->pdo->prepare('
                INSERT INTO courses (id, name, code, duration_year, total_semesters, department_id, created_at)
                VALUES (:id, :name, :code, :duration, :semesters, :dept_id, CURRENT_TIMESTAMP)
            ');
            $insertCourse->execute([
                'id'        => $courseId,
                'name'      => $courseName,
                'code'      => $courseCode,
                'duration'  => $courseConfig['durationYears'],
                'semesters' => $courseConfig['totalSemesters'],
                'dept_id'   => $departmentId,
            ]);
        } else {
            $courseId = (string)$course['id'];
        }

        return [
            'sessionId'    => $sessionId,
            'sessionName'  => $sessionName,
            'courseId'     => $courseId,
            'courseCode'   => $courseCode,
            'courseName'   => $courseName,
            'departmentId' => $departmentId,
            'courseConfig' => $courseConfig,
        ];
    }

    /**
     * @return array{courseId: string, courseCode: string, semester: int, category: string, tuitionFee: float, admissionFee: float, examFee: float, miscFee: float, totalAmount: float}
     */
    public function getEffectiveSemesterFee(
        string $courseId,
        string $courseCode,
        int $semester = 1,
        ?string $category = null
    ): array {
        $breakdown = CollegeConfig::calculateSemesterFeeBreakdown($courseCode, $semester);

        return [
            'courseId'     => $courseId,
            'courseCode'   => $courseCode,
            'semester'     => $semester,
            'category'     => $category ?? 'General',
            'tuitionFee'   => $breakdown['tuitionFee'],
            'admissionFee' => $breakdown['admissionFee'],
            'examFee'      => $breakdown['examFee'],
            'miscFee'      => $breakdown['miscFee'],
            'totalAmount'  => $breakdown['totalAmount'],
        ];
    }

    /**
     * @return array{serial: int, applicationNo: string, registrationCode: string}
     */
    public function generateSafeAdmissionCodes(int $year): array
    {
        $appPattern = sprintf('%s-%d-%%', CollegeConfig::INSTITUTION_CODE, $year);
        $regPattern = sprintf('%s-REG-%d-%%', CollegeConfig::INSTITUTION_CODE, $year);

        $stmt = $this->pdo->prepare('
            SELECT application_no, registration_code
            FROM students
            WHERE application_no LIKE :app_pattern OR registration_code LIKE :reg_pattern
            ORDER BY id DESC
        ');
        $stmt->execute(['app_pattern' => $appPattern, 'reg_pattern' => $regPattern]);
        $rows = $stmt->fetchAll();

        $maxSerial = 0;
        foreach ($rows as $row) {
            if (!empty($row['application_no'])) {
                $parts = explode('-', (string)$row['application_no']);
                $s = (int)end($parts);
                if ($s > $maxSerial) {
                    $maxSerial = $s;
                }
            }
            if (!empty($row['registration_code'])) {
                $parts = explode('-', (string)$row['registration_code']);
                $s = (int)end($parts);
                if ($s > $maxSerial) {
                    $maxSerial = $s;
                }
            }
        }

        $serial = $maxSerial + 1;

        $checkStmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM students
            WHERE application_no = :app_no OR registration_code = :reg_code
        ');

        do {
            $applicationNo = CollegeConfig::formatApplicationNo($year, $serial);
            $registrationCode = CollegeConfig::formatRegistrationCode($year, $serial);

            $checkStmt->execute([
                'app_no'   => $applicationNo,
                'reg_code' => $registrationCode,
            ]);
            $exists = (int)$checkStmt->fetchColumn() > 0;

            if ($exists) {
                $serial++;
            }
        } while ($exists);

        return [
            'serial'           => $serial,
            'applicationNo'    => $applicationNo,
            'registrationCode' => $registrationCode,
        ];
    }

    public function generateApplicationNo(int $year): string
    {
        return $this->generateSafeAdmissionCodes($year)['applicationNo'];
    }

    public function generateRegistrationCode(int $year): string
    {
        return $this->generateSafeAdmissionCodes($year)['registrationCode'];
    }

    public function generateSafeCollegeRollNo(int $year, string $courseCode): string
    {
        $prefix = CollegeConfig::getCoursePrefix($courseCode);
        $shortYear = sprintf('%02d', $year % 100);
        $pattern = sprintf('%s/%s/%%', $shortYear, $prefix);

        $stmt = $this->pdo->prepare('
            SELECT college_roll_no FROM students
            WHERE college_roll_no LIKE :pattern
            ORDER BY id DESC
        ');
        $stmt->execute(['pattern' => $pattern]);
        $rows = $stmt->fetchAll();

        $maxSerial = 0;
        foreach ($rows as $row) {
            if (!empty($row['college_roll_no'])) {
                $parts = explode('/', (string)$row['college_roll_no']);
                $s = (int)end($parts);
                if ($s > $maxSerial) {
                    $maxSerial = $s;
                }
            }
        }

        $serial = $maxSerial + 1;

        $checkStmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM students
            WHERE college_roll_no = :roll_no
        ');

        do {
            $rollNo = CollegeConfig::formatCollegeRollNo($year, $prefix, $serial);
            $checkStmt->execute(['roll_no' => $rollNo]);
            $exists = (int)$checkStmt->fetchColumn() > 0;

            if ($exists) {
                $serial++;
            }
        } while ($exists);

        return $rollNo;
    }

    public function generateCollegeRollNo(int $year, string $courseCode): string
    {
        return $this->generateSafeCollegeRollNo($year, $courseCode);
    }

    /**
     * @param array<string, string> $selectedSubjects (e.g. ['MJC' => 'HISTORY', 'MIC' => 'POLITICAL SCIENCE', ...])
     * @return array<int, array<string, mixed>>
     */
    public function enrollStudentSubjects(
        string $studentId,
        int $semester,
        string $courseId,
        string $departmentId,
        array $selectedSubjects
    ): array {
        $allowedCategories = ['MJC', 'MIC', 'MDC', 'AEC', 'SEC', 'VAC'];
        $enrolled = [];

        foreach ($selectedSubjects as $category => $subjectName) {
            $category = strtoupper(trim($category));
            if (!in_array($category, $allowedCategories, true)) {
                continue;
            }

            $subjectInfo = $this->resolveSubject(
                $courseId,
                $departmentId,
                $semester,
                $category,
                (string)$subjectName
            );

            $enrollmentId = sprintf('enr_%s_%s_%d', substr($studentId, 0, 12), strtolower($category), $semester);

            $checkEnr = $this->pdo->prepare('
                SELECT id FROM student_subject_enrollments
                WHERE student_id = :student_id AND subject_id = :subject_id AND semester = :semester
                LIMIT 1
            ');
            $checkEnr->execute([
                'student_id' => $studentId,
                'subject_id' => $subjectInfo['id'],
                'semester'   => $semester,
            ]);
            $existingEnr = $checkEnr->fetch();

            if (!$existingEnr) {
                $stmt = $this->pdo->prepare('
                    INSERT INTO student_subject_enrollments
                        (id, student_id, subject_id, semester, is_approved, created_at)
                    VALUES
                        (:id, :student_id, :subject_id, :semester, 1, CURRENT_TIMESTAMP)
                ');
                $stmt->execute([
                    'id'         => $enrollmentId,
                    'student_id' => $studentId,
                    'subject_id' => $subjectInfo['id'],
                    'semester'   => $semester,
                ]);
            }

            $enrolled[] = [
                'enrollment_id' => $enrollmentId,
                'category'      => $category,
                'subject_id'    => $subjectInfo['id'],
                'subject_code'  => $subjectInfo['code'],
                'subject_name'  => $subjectInfo['name'],
                'credits'       => $subjectInfo['credits'],
            ];
        }

        return $enrolled;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getEnrolledSubjects(string $studentId, int $semester): array
    {
        $stmt = $this->pdo->prepare('
            SELECT
                e.id AS enrollment_id,
                e.semester,
                e.is_approved,
                e.created_at,
                s.id AS subject_id,
                s.code AS subject_code,
                s.name AS subject_name,
                s.category,
                s.credits
            FROM student_subject_enrollments e
            JOIN subjects s ON e.subject_id = s.id
            WHERE e.student_id = :student_id AND e.semester = :semester
            ORDER BY CASE s.category
                WHEN \'MJC\' THEN 1
                WHEN \'MIC\' THEN 2
                WHEN \'MDC\' THEN 3
                WHEN \'AEC\' THEN 4
                WHEN \'SEC\' THEN 5
                WHEN \'VAC\' THEN 6
                ELSE 7
            END
        ');

        $stmt->execute([
            'student_id' => $studentId,
            'semester'   => $semester,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * @return array{id: string, code: string, name: string, credits: int, category: string}
     */
    private function resolveSubject(
        string $courseId,
        string $departmentId,
        int $semester,
        string $category,
        string $subjectName
    ): array {
        $cleanName = trim($subjectName);
        $cleanSlug = strtoupper(preg_replace('/[^A-Z0-9]+/', '-', $cleanName));
        $cleanSlug = trim((string)$cleanSlug, '-');

        $courseCodeStmt = $this->pdo->prepare('SELECT code FROM courses WHERE id = :id LIMIT 1');
        $courseCodeStmt->execute(['id' => $courseId]);
        $courseCode = (string)($courseCodeStmt->fetchColumn() ?: 'UG');

        $deterministicCode = sprintf('%s-S%d-%s-%s', $courseCode, $semester, $category, $cleanSlug);

        $stmt = $this->pdo->prepare('
            SELECT id, code, name, credits, category
            FROM subjects
            WHERE course_id = :course_id
              AND semester = :semester
              AND category = :category
              AND (code = :code OR UPPER(TRIM(name)) = UPPER(:name))
            LIMIT 1
        ');
        $stmt->execute([
            'course_id' => $courseId,
            'semester'  => $semester,
            'category'  => $category,
            'code'      => $deterministicCode,
            'name'      => $cleanName,
        ]);

        $existing = $stmt->fetch();
        if ($existing && !empty($existing['id'])) {
            return [
                'id'       => (string)$existing['id'],
                'code'     => (string)$existing['code'],
                'name'     => (string)$existing['name'],
                'credits'  => (int)$existing['credits'],
                'category' => (string)$existing['category'],
            ];
        }

        $credits = self::CBCS_CREDIT_MATRIX[$category] ?? 2;
        $subjectId = sprintf(
            'subj_%s_s%d_%s_%s',
            strtolower(str_replace('-', '_', $courseCode)),
            $semester,
            strtolower($category),
            strtolower(str_replace('-', '_', $cleanSlug))
        );

        $insertStmt = $this->pdo->prepare('
            INSERT INTO subjects
                (id, code, name, semester, credits, category, course_id, department_id, created_at)
            VALUES
                (:id, :code, :name, :semester, :credits, :category, :course_id, :department_id, CURRENT_TIMESTAMP)
        ');
        $insertStmt->execute([
            'id'            => $subjectId,
            'code'          => $deterministicCode,
            'name'          => $cleanName,
            'semester'      => $semester,
            'credits'       => $credits,
            'category'      => $category,
            'course_id'     => $courseId,
            'department_id' => $departmentId,
        ]);

        return [
            'id'       => $subjectId,
            'code'     => $deterministicCode,
            'name'     => $cleanName,
            'credits'  => $credits,
            'category' => $category,
        ];
    }
}
