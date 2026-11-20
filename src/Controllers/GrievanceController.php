<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuthServer;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use PDO;
use Throwable;

class GrievanceController
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function index(Request $request): Response
    {
        $csrfToken = AuthServer::csrfToken();
        $categories = [
            'Academic & Curriculum',
            'Examination & Results',
            'Fee, Accounts & Scholarship',
            'CBCS Subject Allocation',
            'Library & Laboratory Facilities',
            'Administrative & Office Services',
            'Anti-Ragging / Student Welfare',
            'General Institutional Inquiry',
        ];

        return Response::view('pages/complaint/index', [
            'title'      => 'Grievance Redressal & Student Helpdesk',
            'csrfToken'  => $csrfToken,
            'categories' => $categories,
        ]);
    }

    public function submit(Request $request): Response
    {
        if (!AuthServer::verifyCsrf($request)) {
            return Response::json([
                'success' => false,
                'error'   => 'Security session token validation failed. Please refresh the page.',
            ], 400);
        }

        $rules = [
            'name'        => 'required|min:3|max:191',
            'email'       => 'required|email',
            'phone'       => 'required|phone',
            'category'    => 'required',
            'subject'     => 'required|min:4|max:191',
            'description' => 'required|min:10',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return Response::json([
                'success' => false,
                'error'   => $validator->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $submittedBy = strtoupper(trim((string)$request->input('submitted_by', 'STUDENT')));
        if (!in_array($submittedBy, ['STUDENT', 'PARENT', 'PUBLIC'], true)) {
            $submittedBy = 'STUDENT';
        }

        $year = (int)date('Y');
        $ticketNo = $this->generateTicketNo($year);
        $ticketId = sprintf('tkt_%s_%s', date('YmdHis'), bin2hex(random_bytes(4)));

        $studentId = null;
        $rollOrApp = trim((string)$request->input('student_identifier', ''));
        if ($rollOrApp !== '') {
            $checkStudent = $this->pdo->prepare('
                SELECT id FROM students
                WHERE application_no = :id OR registration_code = :id OR college_roll_no = :id
                LIMIT 1
            ');
            $checkStudent->execute(['id' => $rollOrApp]);
            $studentId = $checkStudent->fetchColumn() ?: null;
        }

        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO grievance_tickets (
                    id, ticket_no, student_id, submitted_by, name,
                    email, phone, category, subject, description,
                    status, created_at, updated_at
                ) VALUES (
                    :id, :ticket_no, :student_id, :submitted_by, :name,
                    :email, :phone, :category, :subject, :description,
                    \'PENDING\', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )
            ');

            $stmt->execute([
                'id'           => $ticketId,
                'ticket_no'    => $ticketNo,
                'student_id'   => $studentId,
                'submitted_by' => $submittedBy,
                'name'         => trim((string)$request->input('name')),
                'email'        => trim((string)$request->input('email')),
                'phone'        => trim((string)$request->input('phone')),
                'category'     => trim((string)$request->input('category')),
                'subject'      => trim((string)$request->input('subject')),
                'description'  => trim((string)$request->input('description')),
            ]);

            return Response::json([
                'success'     => true,
                'message'     => 'Your grievance ticket has been successfully registered.',
                'ticketNo'    => $ticketNo,
                'status'      => 'PENDING',
                'name'        => trim((string)$request->input('name')),
                'submittedAt' => date('d-m-Y H:i:s'),
            ], 201);
        } catch (Throwable $e) {
            Logger::exception($e, 'grievance_submit', ['email' => $request->input('email')]);
            return Response::json([
                'success' => false,
                'error'   => 'Failed to register grievance ticket: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function track(Request $request): Response
    {
        $ticketNo = strtoupper(trim((string)$request->input('ticket_no', '')));

        if ($ticketNo === '') {
            return Response::json([
                'success' => false,
                'error'   => 'Please provide your Grievance Ticket Number (e.g. TKT-2026-00001).',
            ], 422);
        }

        $stmt = $this->pdo->prepare('
            SELECT id, ticket_no, name, email, phone, category,
                   subject, description, status, admin_remarks,
                   resolved_at, created_at, updated_at
            FROM grievance_tickets
            WHERE ticket_no = :ticket_no
            LIMIT 1
        ');
        $stmt->execute(['ticket_no' => $ticketNo]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            return Response::json([
                'success' => false,
                'error'   => 'No grievance record found for Ticket No: ' . $ticketNo,
            ], 404);
        }

        return Response::json([
            'success' => true,
            'ticket'  => [
                'ticketNo'     => $ticket['ticket_no'],
                'name'         => $ticket['name'],
                'category'     => $ticket['category'],
                'subject'      => $ticket['subject'],
                'description'  => $ticket['description'],
                'status'       => $ticket['status'],
                'adminRemarks' => $ticket['admin_remarks'],
                'submittedAt'  => $ticket['created_at'],
                'resolvedAt'   => $ticket['resolved_at'],
            ],
        ]);
    }

    private function generateTicketNo(int $year): string
    {
        $prefix = sprintf('TKT-%d-', $year);
        $pattern = $prefix . '%';

        $stmt = $this->pdo->prepare('
            SELECT ticket_no FROM grievance_tickets
            WHERE ticket_no LIKE :pattern
            ORDER BY ticket_no DESC
            LIMIT 20
        ');
        $stmt->execute(['pattern' => $pattern]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $max = 0;
        foreach ($rows as $t) {
            $parts = explode('-', (string)$t);
            $last = end($parts);
            if (is_numeric($last)) {
                $val = (int)$last;
                if ($val > $max) {
                    $max = $val;
                }
            }
        }

        $serial = $max + 1;
        $checkStmt = $this->pdo->prepare('SELECT COUNT(*) FROM grievance_tickets WHERE ticket_no = :no');

        do {
            $candidate = sprintf('%s%05d', $prefix, $serial);
            $checkStmt->execute(['no' => $candidate]);
            $exists = (int)$checkStmt->fetchColumn() > 0;
            if ($exists) {
                $serial++;
            }
        } while ($exists);

        return $candidate;
    }
}

