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
use PDO;
use Throwable;

class ContactController
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function index(Request $request): Response
    {
        $csrfToken = AuthServer::csrfToken();

        return Response::view('pages/contact/index', [
            'title'     => 'Contact College Administration & Campus Helpdesk',
            'csrfToken' => $csrfToken,
            'college'   => [
                'name'      => CollegeConfig::INSTITUTION_NAME,
                'location'  => CollegeConfig::CAMPUS_LOCATION,
                'phone'     => CollegeConfig::HELPLINE_PHONE,
                'email'     => CollegeConfig::OFFICIAL_EMAIL,
                'university'=> CollegeConfig::AFFILIATED_UNIVERSITY,
            ],
        ]);
    }

    public function submit(Request $request): Response
    {
        if (!AuthServer::verifyCsrf($request)) {
            return Response::json([
                'success' => false,
                'error'   => 'Security verification failed. Please refresh the page.',
            ], 400);
        }

        $rules = [
            'name'    => 'required|min:3',
            'email'   => 'required|email',
            'phone'   => 'required|phone',
            'subject' => 'required|min:3',
            'message' => 'required|min:5',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return Response::json([
                'success' => false,
                'error'   => $validator->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $year = (int)date('Y');
            $ticketNo = sprintf('INQ-%d-%s', $year, strtoupper(bin2hex(random_bytes(3))));
            $id = sprintf('inq_%s_%s', date('YmdHis'), bin2hex(random_bytes(3)));

            $stmt = $this->pdo->prepare('
                INSERT INTO grievance_tickets (
                    id, ticket_no, submitted_by, name, email,
                    phone, category, subject, description, status,
                    created_at, updated_at
                ) VALUES (
                    :id, :ticket_no, \'PUBLIC\', :name, :email,
                    :phone, \'General Inquiry\', :subject, :description, \'PENDING\',
                    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )
            ');

            $stmt->execute([
                'id'          => $id,
                'ticket_no'   => $ticketNo,
                'name'        => trim((string)$request->input('name')),
                'email'       => trim((string)$request->input('email')),
                'phone'       => trim((string)$request->input('phone')),
                'subject'     => trim((string)$request->input('subject')),
                'description' => trim((string)$request->input('message')),
            ]);

            return Response::json([
                'success'   => true,
                'message'   => 'Your inquiry has been submitted to the college administrative secretariat.',
                'reference' => $ticketNo,
            ], 201);
        } catch (Throwable $e) {
            Logger::exception($e, 'contact_submit', ['email' => $request->input('email')]);
            return Response::json([
                'success' => false,
                'error'   => 'Unable to send message at this moment. Please call our helpline directly: ' . CollegeConfig::HELPLINE_PHONE,
            ], 500);
        }
    }
}

