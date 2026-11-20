<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

class StudentDirectoryService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{students: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function searchStudents(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $conditions[] = '(
                s.full_name LIKE :search_term
                OR s.application_no LIKE :search_term
                OR s.registration_code LIKE :search_term
                OR s.college_roll_no LIKE :search_term
                OR s.phone LIKE :search_term
                OR s.email LIKE :search_term
            )';
            $params['search_term'] = $term;
        }

        if (!empty($filters['course_id'])) {
            $conditions[] = 's.course_id = :course_id';
            $params['course_id'] = (string)$filters['course_id'];
        }

        if (!empty($filters['course_code'])) {
            $conditions[] = 'c.code = :course_code';
            $params['course_code'] = strtoupper(trim((string)$filters['course_code']));
        }

        if (!empty($filters['semester'])) {
            $conditions[] = 's.current_semester = :semester';
            $params['semester'] = (int)$filters['semester'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = 's.status = :status';
            $params['status'] = strtoupper(trim((string)$filters['status']));
        }

        if (!empty($filters['category'])) {
            $conditions[] = 's.category = :category';
            $params['category'] = trim((string)$filters['category']);
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countSql = "
            SELECT COUNT(*) FROM students s
            JOIN courses c ON s.course_id = c.id
            JOIN academic_sessions sess ON s.session_id = sess.id
            {$whereClause}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $totalPages = (int)ceil($total / $perPage);

        $dataSql = "
            SELECT
                s.id, s.application_no, s.registration_code, s.college_roll_no,
                s.full_name, s.father_name, s.mother_name, s.date_of_birth,
                s.gender, s.category, s.phone, s.email, s.address,
                s.current_semester, s.status, s.created_at,
                c.id AS course_id, c.name AS course_name, c.code AS course_code,
                sess.id AS session_id, sess.name AS session_name
            FROM students s
            JOIN courses c ON s.course_id = c.id
            JOIN academic_sessions sess ON s.session_id = sess.id
            {$whereClause}
            ORDER BY s.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $dataStmt = $this->pdo->prepare($dataSql);
        foreach ($params as $key => $val) {
            $dataStmt->bindValue($key, $val);
        }
        $dataStmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        $students = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'students'   => $students,
            'pagination' => [
                'total'      => $total,
                'page'       => $page,
                'perPage'    => $perPage,
                'totalPages' => $totalPages,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStudentDetails(string $identifier): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT s.*, c.name AS course_name, c.code AS course_code, sess.name AS session_name
            FROM students s
            JOIN courses c ON s.course_id = c.id
            JOIN academic_sessions sess ON s.session_id = sess.id
            WHERE s.id = :id OR s.application_no = :id OR s.registration_code = :id OR s.college_roll_no = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $identifier]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            return null;
        }

        $studentId = (string)$student['id'];

        $subStmt = $this->pdo->prepare('
            SELECT e.semester, e.is_approved, e.created_at,
                   sub.id AS subject_id, sub.code AS subject_code, sub.name AS subject_name,
                   sub.category, sub.credits
            FROM student_subject_enrollments e
            JOIN subjects sub ON e.subject_id = sub.id
            WHERE e.student_id = :sid
            ORDER BY e.semester ASC,
                CASE sub.category
                    WHEN \'MJC\' THEN 1
                    WHEN \'MIC\' THEN 2
                    WHEN \'MDC\' THEN 3
                    WHEN \'AEC\' THEN 4
                    WHEN \'SEC\' THEN 5
                    WHEN \'VAC\' THEN 6
                    ELSE 7
                END
        ');
        $subStmt->execute(['sid' => $studentId]);
        $subjects = $subStmt->fetchAll(PDO::FETCH_ASSOC);

        $feeStmt = $this->pdo->prepare('
            SELECT id, receipt_no, target_semester, payment_type, title, amount,
                   payment_method, status, transaction_ref, paid_at, created_at
            FROM fee_payments
            WHERE student_id = :sid
            ORDER BY target_semester ASC, created_at DESC
        ');
        $feeStmt->execute(['sid' => $studentId]);
        $fees = $feeStmt->fetchAll(PDO::FETCH_ASSOC);

        $promoStmt = $this->pdo->prepare('
            SELECT p.from_semester, p.to_semester, p.promoted_by, p.remarks, p.created_at,
                   fp.receipt_no
            FROM promotion_histories p
            LEFT JOIN fee_payments fp ON p.trigger_payment_id = fp.id
            WHERE p.student_id = :sid
            ORDER BY p.to_semester ASC
        ');
        $promoStmt->execute(['sid' => $studentId]);
        $promotions = $promoStmt->fetchAll(PDO::FETCH_ASSOC);

        $ciaStmt = $this->pdo->prepare('
            SELECT c.*, sub.name AS subject_name, sub.code AS subject_code
            FROM continuous_internal_assessments c
            JOIN subjects sub ON c.subject_id = sub.id
            WHERE c.student_id = :sid
            ORDER BY c.semester ASC
        ');
        $ciaStmt->execute(['sid' => $studentId]);
        $ciaMarks = $ciaStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'student'    => $student,
            'subjects'   => $subjects,
            'fees'       => $fees,
            'promotions' => $promotions,
            'ciaMarks'   => $ciaMarks,
        ];
    }
}
