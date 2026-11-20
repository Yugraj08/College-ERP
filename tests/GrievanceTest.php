<?php
declare(strict_types=1);

namespace App\Tests;

use App\Controllers\ContactController;
use App\Controllers\GrievanceController;
use App\Core\AuthServer;
use App\Core\Database;
use App\Core\Request;
use PDO;
use PHPUnit\Framework\TestCase;

class GrievanceTest extends TestCase
{
    private PDO $testDb;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_COOKIE = [];

        $this->testDb = new PDO('sqlite::memory:');
        $this->testDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->testDb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->initializeSchema($this->testDb);
        Database::setConnection($this->testDb);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_COOKIE = [];
        Database::setConnection(null);
    }

    private function initializeSchema(PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE students (
                id VARCHAR(36) PRIMARY KEY,
                application_no VARCHAR(64) UNIQUE NOT NULL,
                registration_code VARCHAR(64) UNIQUE,
                college_roll_no VARCHAR(64) UNIQUE,
                full_name VARCHAR(191) NOT NULL
            );

            CREATE TABLE grievance_tickets (
                id VARCHAR(36) PRIMARY KEY,
                ticket_no VARCHAR(64) UNIQUE NOT NULL,
                student_id VARCHAR(36),
                submitted_by VARCHAR(32) NOT NULL DEFAULT \'STUDENT\',
                name VARCHAR(191) NOT NULL,
                email VARCHAR(191) NOT NULL,
                phone VARCHAR(32) NOT NULL,
                category VARCHAR(64) NOT NULL,
                subject VARCHAR(191) NOT NULL,
                description TEXT NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'PENDING\',
                admin_remarks TEXT,
                resolved_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ');
    }

    public function testSubmitGrievanceWorkflow(): void
    {
        $csrf = AuthServer::csrfToken();
        $controller = new GrievanceController($this->testDb);

        $payload = [
            '_csrf'        => $csrf,
            'name'         => 'Vikram Singh',
            'email'        => 'vikram@gmail.com',
            'phone'        => '9876501234',
            'category'     => 'Fee, Accounts & Scholarship',
            'subject'      => 'Fee receipt duplicate download request',
            'description'  => 'I lost my printed Semester 1 receipt and need a duplicate copy for scholarship application.',
            'submitted_by' => 'STUDENT',
        ];

        $request = new Request('POST', '/api/grievances/submit', [], $payload);
        $response = $controller->submit($request);

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertSame('TKT-2026-00001', $data['ticketNo']);
        $this->assertSame('PENDING', $data['status']);

        // Check persistence in database
        $stmt = $this->testDb->prepare('SELECT * FROM grievance_tickets WHERE ticket_no = :t');
        $stmt->execute(['t' => 'TKT-2026-00001']);
        $ticket = $stmt->fetch();

        $this->assertNotEmpty($ticket);
        $this->assertSame('Vikram Singh', $ticket['name']);
        $this->assertSame('Fee, Accounts & Scholarship', $ticket['category']);
    }

    public function testTrackGrievanceEndpoint(): void
    {
        // Insert a grievance with admin remarks
        $this->testDb->exec('
            INSERT INTO grievance_tickets (
                id, ticket_no, submitted_by, name, email, phone, category,
                subject, description, status, admin_remarks, resolved_at
            ) VALUES (
                \'tkt-01\', \'TKT-2026-00042\', \'STUDENT\', \'Ananya Roy\', \'ananya@gmail.com\',
                \'9123400000\', \'CBCS Subject Allocation\', \'Change of Minor Course\',
                \'Requesting change from History to Political Science.\', \'RESOLVED\',
                \'Approved by Academic Council. Subject updated.\', \'2026-07-15 14:30:00\'
            );
        ');

        $controller = new GrievanceController($this->testDb);
        $request = new Request('POST', '/api/grievances/track', [], ['ticket_no' => 'TKT-2026-00042']);

        $response = $controller->track($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertSame('TKT-2026-00042', $data['ticket']['ticketNo']);
        $this->assertSame('RESOLVED', $data['ticket']['status']);
        $this->assertSame('Approved by Academic Council. Subject updated.', $data['ticket']['adminRemarks']);
    }

    public function testContactSubmissionCreatesInquiry(): void
    {
        $csrf = AuthServer::csrfToken();
        $controller = new ContactController($this->testDb);

        $payload = [
            '_csrf'   => $csrf,
            'name'    => 'Rahul Verma',
            'email'   => 'rahul@gmail.com',
            'phone'   => '9870001122',
            'subject' => 'Hostel facilities inquiry',
            'message' => 'Are there college hostel facilities available for first year students?',
        ];

        $request = new Request('POST', '/api/contact/submit', [], $payload);
        $response = $controller->submit($request);

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertStringStartsWith('INQ-2026-', $data['reference']);
    }
}
