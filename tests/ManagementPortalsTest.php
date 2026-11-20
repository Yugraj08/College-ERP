<?php
declare(strict_types=1);

namespace App\Tests;

use App\Controllers\AdminController;
use App\Controllers\FacultyController;
use App\Controllers\StudentController;
use App\Core\Auth;
use App\Core\AuthServer;
use App\Core\Request;
use App\Services\PromotionService;
use App\Services\StudentDirectoryService;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ManagementPortalsTest extends TestCase
{
    private PDO $testDb;

    protected function setUp(): void
    {
        $this->testDb = new PDO('sqlite::memory:');
        $this->testDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->testDb->exec('
            CREATE TABLE users (
                id VARCHAR(36) PRIMARY KEY,
                username VARCHAR(64) UNIQUE NOT NULL,
                email VARCHAR(191) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(32) NOT NULL,
                is_active INT NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE academic_sessions (
                id VARCHAR(36) PRIMARY KEY,
                name VARCHAR(64) UNIQUE NOT NULL,
                start_year INT NOT NULL,
                end_year INT NOT NULL,
                is_current INT NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE departments (
                id VARCHAR(36) PRIMARY KEY,
                name VARCHAR(128) NOT NULL,
                code VARCHAR(32) UNIQUE NOT NULL,
                faculty_type VARCHAR(64) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE courses (
                id VARCHAR(36) PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                code VARCHAR(32) UNIQUE NOT NULL,
                duration_year INT NOT NULL DEFAULT 4,
                total_semesters INT NOT NULL DEFAULT 8,
                department_id VARCHAR(36) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE subjects (
                id VARCHAR(36) PRIMARY KEY,
                code VARCHAR(64) UNIQUE NOT NULL,
                name VARCHAR(191) NOT NULL,
                semester INT NOT NULL,
                credits INT NOT NULL DEFAULT 4,
                category VARCHAR(32) NOT NULL,
                course_id VARCHAR(36) NOT NULL,
                department_id VARCHAR(36) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE registration_windows (
                id VARCHAR(36) PRIMARY KEY,
                academic_session_id VARCHAR(36) NOT NULL,
                course_id VARCHAR(36) NOT NULL,
                semester INT NOT NULL,
                start_date DATETIME NOT NULL,
                end_date DATETIME NOT NULL,
                grace_days INT NOT NULL DEFAULT 30,
                is_active INT NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE faculty_profiles (
                id VARCHAR(36) PRIMARY KEY,
                user_id VARCHAR(36) UNIQUE,
                employee_code VARCHAR(64) UNIQUE NOT NULL,
                full_name VARCHAR(191) NOT NULL,
                designation VARCHAR(64) NOT NULL,
                department_id VARCHAR(36) NOT NULL,
                email VARCHAR(191) UNIQUE NOT NULL,
                phone VARCHAR(32) UNIQUE NOT NULL,
                qualification VARCHAR(128),
                specialization VARCHAR(128),
                joining_date DATE,
                is_active INT NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE students (
                id VARCHAR(36) PRIMARY KEY,
                user_id VARCHAR(36) UNIQUE,
                application_no VARCHAR(64) UNIQUE NOT NULL,
                registration_code VARCHAR(64) UNIQUE,
                college_roll_no VARCHAR(64) UNIQUE,
                full_name VARCHAR(191) NOT NULL,
                father_name VARCHAR(191) NOT NULL,
                mother_name VARCHAR(191) NOT NULL,
                date_of_birth DATE NOT NULL,
                gender VARCHAR(32) NOT NULL,
                category VARCHAR(32) NOT NULL DEFAULT \'General\',
                phone VARCHAR(32) NOT NULL,
                email VARCHAR(191) NOT NULL,
                address TEXT NOT NULL,
                session_id VARCHAR(36) NOT NULL,
                course_id VARCHAR(36) NOT NULL,
                current_semester INT NOT NULL DEFAULT 1,
                status VARCHAR(32) NOT NULL DEFAULT \'APPLIED\',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE student_semester_records (
                id VARCHAR(36) PRIMARY KEY,
                student_id VARCHAR(36) NOT NULL,
                semester INT NOT NULL,
                academic_session_id VARCHAR(36) NOT NULL,
                status VARCHAR(64) NOT NULL DEFAULT \'ENROLLED\',
                is_fee_cleared INT NOT NULL DEFAULT 0,
                enrolled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE student_subject_enrollments (
                id VARCHAR(36) PRIMARY KEY,
                student_id VARCHAR(36) NOT NULL,
                subject_id VARCHAR(36) NOT NULL,
                semester INT NOT NULL,
                is_approved INT NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE fee_payments (
                id VARCHAR(36) PRIMARY KEY,
                receipt_no VARCHAR(64) UNIQUE NOT NULL,
                student_id VARCHAR(36) NOT NULL,
                target_semester INT NOT NULL,
                payment_type VARCHAR(64) NOT NULL,
                title VARCHAR(191) NOT NULL,
                amount DECIMAL(10, 2) NOT NULL,
                payment_method VARCHAR(32) NOT NULL,
                status VARCHAR(32) NOT NULL,
                transaction_ref VARCHAR(128),
                gateway_order_id VARCHAR(128),
                gateway_signature TEXT,
                is_promotion_triggered INT NOT NULL DEFAULT 0,
                paid_at DATETIME,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE promotion_histories (
                id VARCHAR(36) PRIMARY KEY,
                student_id VARCHAR(36) NOT NULL,
                from_semester INT NOT NULL,
                to_semester INT NOT NULL,
                academic_session_id VARCHAR(36) NOT NULL,
                trigger_payment_id VARCHAR(36),
                promoted_by VARCHAR(64) NOT NULL DEFAULT \'SYSTEM_AUTO_PAYMENT\',
                remarks TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE attendance_sessions (
                id VARCHAR(36) PRIMARY KEY,
                faculty_id VARCHAR(36) NOT NULL,
                subject_id VARCHAR(36) NOT NULL,
                academic_session_id VARCHAR(36) NOT NULL,
                semester INT NOT NULL,
                session_date DATE NOT NULL,
                time_slot VARCHAR(64) NOT NULL,
                room_no VARCHAR(32),
                topic_covered TEXT,
                total_present INT NOT NULL DEFAULT 0,
                total_absent INT NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE attendance_records (
                id VARCHAR(36) PRIMARY KEY,
                session_id VARCHAR(36) NOT NULL,
                student_id VARCHAR(36) NOT NULL,
                faculty_id VARCHAR(36) NOT NULL,
                subject_id VARCHAR(36) NOT NULL,
                semester INT NOT NULL,
                date DATE NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'PRESENT\',
                remarks VARCHAR(128),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE continuous_internal_assessments (
                id VARCHAR(36) PRIMARY KEY,
                faculty_id VARCHAR(36) NOT NULL,
                subject_id VARCHAR(36) NOT NULL,
                student_id VARCHAR(36) NOT NULL,
                semester INT NOT NULL,
                mid_term_exam DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
                assignment DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
                attendance_score DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
                total_cia_score DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
                is_finalized INT NOT NULL DEFAULT 0,
                evaluation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
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

            CREATE TABLE notices (
                id VARCHAR(36) PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                category VARCHAR(64) NOT NULL DEFAULT \'General\',
                is_published INT NOT NULL DEFAULT 1,
                attachment VARCHAR(255),
                published_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE audit_logs (
                id VARCHAR(36) PRIMARY KEY,
                user_id VARCHAR(36),
                action VARCHAR(64) NOT NULL,
                entity VARCHAR(64) NOT NULL,
                entity_id VARCHAR(64),
                new_values TEXT,
                details TEXT,
                ip_address VARCHAR(45),
                user_agent VARCHAR(255),
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ');

        // Seed core foundation
        $this->testDb->exec('
            INSERT INTO academic_sessions (id, name, start_year, end_year, is_current)
            VALUES (\'sess-2026-2030\', \'2026-2030\', 2026, 2030, 1);

            INSERT INTO departments (id, name, code, faculty_type)
            VALUES (\'dept-hist\', \'History\', \'HIST\', \'Social Science\');

            INSERT INTO courses (id, name, code, duration_year, total_semesters, department_id)
            VALUES (\'crs-ba-ss\', \'Bachelor of Arts – Social Science\', \'BA-SS\', 4, 8, \'dept-hist\');

            INSERT INTO subjects (id, code, name, semester, credits, category, course_id, department_id)
            VALUES (\'sub-mjc-hist\', \'BA-SS-S1-MJC-HISTORY\', \'History of Ancient India\', 1, 6, \'MJC\', \'crs-ba-ss\', \'dept-hist\');

            INSERT INTO users (id, username, email, password_hash, role, is_active)
            VALUES (\'usr-admin-01\', \'mkrcollege@gmail.com\', \'mkrcollege@gmail.com\', \'fake_hash\', \'SUPER_ADMIN\', 1);

            INSERT INTO users (id, username, email, password_hash, role, is_active)
            VALUES (\'usr-fac-01\', \'FAC-1001\', \'faculty@mkrcollege.in\', \'fake_hash\', \'FACULTY\', 1);

            INSERT INTO faculty_profiles (id, user_id, employee_code, full_name, designation, department_id, email, phone)
            VALUES (\'fac-prof-01\', \'usr-fac-01\', \'FAC-1001\', \'Dr. Arvind Kumar\', \'Associate Professor\', \'dept-hist\', \'faculty@mkrcollege.in\', \'9876500001\');
        ');

        Auth::setUser([
            'id'        => 'usr-admin-01',
            'username'  => 'mkrcollege@gmail.com',
            'email'     => 'mkrcollege@gmail.com',
            'role'      => 'SUPER_ADMIN',
            'full_name' => 'Principal MKR College',
        ]);
    }

    public function testPromotionServiceAdvancesSemesterAndClearsFee(): void
    {
        // Insert student in Semester 1
        $this->testDb->exec('
            INSERT INTO students (
                id, application_no, registration_code, college_roll_no, full_name,
                father_name, mother_name, date_of_birth, gender, category, phone, email,
                address, session_id, course_id, current_semester, status
            ) VALUES (
                \'std-promo-01\', \'MKR-2026-00050\', \'MKR-REG-2026-00050\', \'26/BASS/0050\', \'Kavita Kumari\',
                \'Shyam Ray\', \'Geeta Devi\', \'2005-06-10\', \'Female\', \'General\', \'9871110001\', \'kavita@gmail.com\',
                \'Sitamarhi\', \'sess-2026-2030\', \'crs-ba-ss\', 1, \'ADMITTED\'
            );
        ');

        $service = new PromotionService($this->testDb);
        $result = $service->promoteStudent(
            'std-promo-01',
            2,
            2800.00,
            'ONLINE_GATEWAY',
            ['orderId' => 'ord_123', 'transactionRef' => 'TXN-SIM-P1']
        );

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['fromSemester']);
        $this->assertSame(2, $result['toSemester']);
        $this->assertStringStartsWith('REC-2026-S2-', $result['receiptNo']);

        // Check student updated
        $stmt = $this->testDb->prepare('SELECT current_semester, status FROM students WHERE id = :id');
        $stmt->execute(['id' => 'std-promo-01']);
        $row = $stmt->fetch();
        $this->assertSame(2, (int)$row['current_semester']);
        $this->assertSame('ADMITTED', $row['status']);

        // Check fee payment recorded
        $feeStmt = $this->testDb->prepare('SELECT status, is_promotion_triggered FROM fee_payments WHERE receipt_no = :rcpt');
        $feeStmt->execute(['rcpt' => $result['receiptNo']]);
        $fee = $feeStmt->fetch();
        $this->assertSame('COMPLETED', $fee['status']);

        // Check promotion history created
        $promoStmt = $this->testDb->prepare('SELECT from_semester, to_semester FROM promotion_histories WHERE student_id = :sid');
        $promoStmt->execute(['sid' => 'std-promo-01']);
        $history = $promoStmt->fetch();
        $this->assertSame(1, (int)$history['from_semester']);
        $this->assertSame(2, (int)$history['to_semester']);
    }

    public function testPromotionServiceRejectsNonSequentialJumps(): void
    {
        $this->testDb->exec('
            INSERT INTO students (
                id, application_no, registration_code, full_name, father_name, mother_name,
                date_of_birth, gender, phone, email, address, session_id, course_id, current_semester, status
            ) VALUES (
                \'std-promo-02\', \'MKR-2026-00051\', \'MKR-REG-2026-00051\', \'Rahul Roy\', \'Manoj Roy\', \'Kiran Devi\',
                \'2005-02-14\', \'Male\', \'9871110002\', \'rahul@gmail.com\', \'Sitamarhi\', \'sess-2026-2030\', \'crs-ba-ss\', 1, \'ADMITTED\'
            );
        ');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sequential progression required');

        $service = new PromotionService($this->testDb);
        // Attempting to jump directly from Sem 1 to Sem 3
        $service->promoteStudent('std-promo-02', 3, 2800.00);
    }

    public function testPromotionServiceIdempotencyRejectsDoubleBilling(): void
    {
        $this->testDb->exec('
            INSERT INTO students (
                id, application_no, registration_code, full_name, father_name, mother_name,
                date_of_birth, gender, phone, email, address, session_id, course_id, current_semester, status
            ) VALUES (
                \'std-promo-03\', \'MKR-2026-00052\', \'MKR-REG-2026-00052\', \'Sneha Jha\', \'B K Jha\', \'Sunita Devi\',
                \'2005-03-10\', \'Female\', \'9871110003\', \'sneha@gmail.com\', \'Sitamarhi\', \'sess-2026-2030\', \'crs-ba-ss\', 1, \'ADMITTED\'
            );
        ');

        $service = new PromotionService($this->testDb);
        $service->promoteStudent('std-promo-03', 2, 2800.00);

        // Attempting to pay/promote for Sem 2 again must throw
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already been paid and cleared');

        $service->promoteStudent('std-promo-03', 2, 2800.00);
    }

    public function testPromotionServiceRegistrationWindowValidation(): void
    {
        $this->testDb->exec('
            INSERT INTO students (
                id, application_no, registration_code, full_name, father_name, mother_name,
                date_of_birth, gender, phone, email, address, session_id, course_id, current_semester, status
            ) VALUES (
                \'std-promo-04\', \'MKR-2026-00053\', \'MKR-REG-2026-00053\', \'Amit Kumar\', \'R Kumar\', \'S Devi\',
                \'2005-04-10\', \'Male\', \'9871110004\', \'amit@gmail.com\', \'Sitamarhi\', \'sess-2026-2030\', \'crs-ba-ss\', 1, \'ADMITTED\'
            );

            -- Past closed window
            INSERT INTO registration_windows (id, academic_session_id, course_id, semester, start_date, end_date, grace_days, is_active)
            VALUES (\'win-expired\', \'sess-2026-2030\', \'crs-ba-ss\', 2, \'2020-01-01 00:00:00\', \'2020-01-31 00:00:00\', 5, 1);
        ');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Registration window for Semester 2 closed');

        $service = new PromotionService($this->testDb);
        $service->promoteStudent('std-promo-04', 2, 2800.00);
    }

    public function testSearchStudentsWithMultiFiltersAndPagination(): void
    {
        $this->testDb->exec('
            INSERT INTO students (id, application_no, registration_code, full_name, father_name, mother_name, date_of_birth, gender, category, phone, email, address, session_id, course_id, current_semester, status)
            VALUES (\'s-1\', \'MKR-2026-00101\', \'MKR-REG-101\', \'Alok Sharma\', \'F1\', \'M1\', \'2005-01-01\', \'Male\', \'General\', \'9800000001\', \'alok@gmail.com\', \'Add1\', \'sess-2026-2030\', \'crs-ba-ss\', 1, \'ADMITTED\');

            INSERT INTO students (id, application_no, registration_code, full_name, father_name, mother_name, date_of_birth, gender, category, phone, email, address, session_id, course_id, current_semester, status)
            VALUES (\'s-2\', \'MKR-2026-00102\', \'MKR-REG-102\', \'Pooja Singh\', \'F2\', \'M2\', \'2005-02-01\', \'Female\', \'OBC\', \'9800000002\', \'pooja@gmail.com\', \'Add2\', \'sess-2026-2030\', \'crs-ba-ss\', 2, \'ADMITTED\');
        ');

        $service = new StudentDirectoryService($this->testDb);

        // Search by keyword
        $searchRes = $service->searchStudents(['search' => 'Alok']);
        $this->assertSame(1, $searchRes['pagination']['total']);
        $this->assertSame('Alok Sharma', $searchRes['students'][0]['full_name']);

        // Search by semester
        $semRes = $service->searchStudents(['semester' => 2]);
        $this->assertSame(1, $semRes['pagination']['total']);
        $this->assertSame('Pooja Singh', $semRes['students'][0]['full_name']);
    }

    public function testScrutinizeAdmissionApproveAssignsRollNumber(): void
    {
        $this->testDb->exec('
            INSERT INTO students (id, application_no, registration_code, full_name, father_name, mother_name, date_of_birth, gender, category, phone, email, address, session_id, course_id, current_semester, status)
            VALUES (\'std-app-99\', \'MKR-2026-00099\', \'MKR-REG-2026-00099\', \'Gaurav Pathak\', \'Father\', \'Mother\', \'2005-05-05\', \'Male\', \'General\', \'9811199999\', \'gaurav@gmail.com\', \'Sitamarhi\', \'sess-2026-2030\', \'crs-ba-ss\', 1, \'APPLIED\');

            INSERT INTO student_semester_records (id, student_id, semester, academic_session_id, status, is_fee_cleared)
            VALUES (\'sem-rec-99\', \'std-app-99\', 1, \'sess-2026-2030\', \'ENROLLED\', 1);
        ');

        $csrf = AuthServer::csrfToken();
        $controller = new AdminController($this->testDb);

        $req = new Request('POST', '/api/admin/admissions/scrutinize', [], [
            '_csrf'      => $csrf,
            'student_id' => 'std-app-99',
            'action'     => 'APPROVE',
            'remarks'    => 'Eligible for UG CBCS program',
        ]);

        $res = $controller->scrutinizeAdmission($req);
        $this->assertSame(200, $res->getStatusCode());

        $data = json_decode($res->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('ADMITTED', $data['status']);
        $this->assertStringStartsWith('26/BASS/', $data['rollNo']);

        // Check student in database
        $check = $this->testDb->query('SELECT college_roll_no, status FROM students WHERE id = \'std-app-99\'')->fetch();
        $this->assertSame('ADMITTED', $check['status']);
        $this->assertNotEmpty($check['college_roll_no']);
    }

    public function testScrutinizeAdmissionReject(): void
    {
        $this->testDb->exec('
            INSERT INTO students (id, application_no, registration_code, full_name, father_name, mother_name, date_of_birth, gender, category, phone, email, address, session_id, course_id, current_semester, status)
            VALUES (\'std-app-98\', \'MKR-2026-00098\', \'MKR-REG-2026-00098\', \'Invalid Candidate\', \'Father\', \'Mother\', \'2005-05-05\', \'Male\', \'General\', \'9811199998\', \'inv@gmail.com\', \'Sitamarhi\', \'sess-2026-2030\', \'crs-ba-ss\', 1, \'APPLIED\');
        ');

        $csrf = AuthServer::csrfToken();
        $controller = new AdminController($this->testDb);

        $req = new Request('POST', '/api/admin/admissions/scrutinize', [], [
            '_csrf'      => $csrf,
            'student_id' => 'std-app-98',
            'action'     => 'REJECT',
            'remarks'    => 'Incomplete intermediate marksheet',
        ]);

        $res = $controller->scrutinizeAdmission($req);
        $this->assertSame(200, $res->getStatusCode());

        $data = json_decode($res->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('REJECTED', $data['status']);
    }

    public function testCreateFacultyAccountAndToggleStatus(): void
    {
        $csrf = AuthServer::csrfToken();
        $controller = new AdminController($this->testDb);

        $createReq = new Request('POST', '/api/admin/faculty/create', [], [
            '_csrf'         => $csrf,
            'full_name'     => 'Dr. Manoj Mishra',
            'email'         => 'mmishra@mkrcollege.in',
            'phone'         => '9876543200',
            'department_id' => 'dept-hist',
            'designation'   => 'Assistant Professor',
            'password'      => 'Faculty@123',
        ]);

        $res = $controller->createFaculty($createReq);
        $this->assertSame(201, $res->getStatusCode());
        $data = json_decode($res->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertStringStartsWith('FAC-', $data['employeeCode']);
        $facId = $data['facultyId'];

        // Toggle status
        $toggleReq = new Request('POST', '/api/admin/faculty/toggle-status', [], [
            '_csrf'      => $csrf,
            'faculty_id' => $facId,
        ]);
        $toggleRes = $controller->toggleFacultyStatus($toggleReq);
        $toggleData = json_decode($toggleRes->getContent(), true);
        $this->assertTrue($toggleData['success']);
        $this->assertFalse($toggleData['isActive']);
    }

    public function testBroadcastNotice(): void
    {
        $csrf = AuthServer::csrfToken();
        $controller = new AdminController($this->testDb);

        $req = new Request('POST', '/api/admin/notices/broadcast', [], [
            '_csrf'    => $csrf,
            'title'    => 'UG CBCS Semester 1 Mid-Term Examination Schedule Announced',
            'category' => 'Exam',
            'content'  => 'Examinations will commence from 15th December 2026. Hall tickets available in student dashboard.',
        ]);

        $res = $controller->broadcastNotice($req);
        $this->assertSame(201, $res->getStatusCode());

        $check = $this->testDb->query('SELECT COUNT(*) FROM notices WHERE category = \'Exam\'')->fetchColumn();
        $this->assertSame(1, (int)$check);
    }

    public function testDeleteNotice(): void
    {
        $csrf = AuthServer::csrfToken();
        $controller = new AdminController($this->testDb);

        $this->testDb->exec('
            INSERT INTO notices (id, title, content, category, is_published, published_at, created_at, updated_at)
            VALUES (\'not-test-del-01\', \'Notice to be removed\', \'Temporary announcement details\', \'General\', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);
        ');

        $req = new Request('POST', '/api/admin/notices/delete', [], [
            '_csrf'     => $csrf,
            'notice_id' => 'not-test-del-01',
        ]);

        $res = $controller->deleteNotice($req);
        $this->assertSame(200, $res->getStatusCode());

        $check = $this->testDb->query('SELECT COUNT(*) FROM notices WHERE id = \'not-test-del-01\'')->fetchColumn();
        $this->assertSame(0, (int)$check);
    }

    public function testResolveGrievance(): void
    {
        $this->testDb->exec('
            INSERT INTO grievance_tickets (id, ticket_no, name, email, phone, category, subject, description, status)
            VALUES (\'tkt-01\', \'TKT-2026-00001\', \'Student Complainant\', \'st@gmail.com\', \'9876543210\', \'Fee\', \'Receipt issue\', \'Need duplicate receipt\', \'PENDING\');
        ');

        $csrf = AuthServer::csrfToken();
        $controller = new AdminController($this->testDb);

        $req = new Request('POST', '/api/admin/grievances/resolve', [], [
            '_csrf'         => $csrf,
            'ticket_no'     => 'TKT-2026-00001',
            'status'        => 'RESOLVED',
            'admin_remarks' => 'Duplicate fee receipt generated and emailed to student.',
        ]);

        $res = $controller->resolveGrievance($req);
        $this->assertSame(200, $res->getStatusCode());

        $ticket = $this->testDb->query('SELECT status, admin_remarks FROM grievance_tickets WHERE ticket_no = \'TKT-2026-00001\'')->fetch();
        $this->assertSame('RESOLVED', $ticket['status']);
        $this->assertStringContainsString('Duplicate fee receipt generated', $ticket['admin_remarks']);
    }

    public function testFacultyAttendanceAndCiaRecording(): void
    {
        $this->testDb->exec('
            INSERT INTO students (id, application_no, registration_code, college_roll_no, full_name, father_name, mother_name, date_of_birth, gender, phone, email, address, session_id, course_id, current_semester, status)
            VALUES (\'std-fac-01\', \'MKR-2026-00201\', \'MKR-REG-201\', \'26/BASS/0201\', \'Deepak Kumar\', \'F\', \'M\', \'2005-01-01\', \'Male\', \'9800112233\', \'deepak@gmail.com\', \'Punaura\', \'sess-2026-2030\', \'crs-ba-ss\', 1, \'ADMITTED\');

            INSERT INTO student_subject_enrollments (id, student_id, subject_id, semester, is_approved)
            VALUES (\'enr-01\', \'std-fac-01\', \'sub-mjc-hist\', 1, 1);
        ');

        $csrf = AuthServer::csrfToken();
        $facultyController = new FacultyController($this->testDb);

        // 1. Save Attendance
        $attReq = new Request('POST', '/api/faculty/attendance/save', [], [
            '_csrf'        => $csrf,
            'subject_id'   => 'sub-mjc-hist',
            'semester'     => 1,
            'session_date' => '2026-11-16',
            'time_slot'    => '10:00 AM - 11:00 AM',
            'room_no'      => 'Room 101',
            'topic_covered'=> 'Unit 1 Introduction',
            'records'      => [
                ['student_id' => 'std-fac-01', 'status' => 'PRESENT', 'remarks' => 'Good participation'],
            ],
        ]);

        $attRes = $facultyController->saveAttendance($attReq);
        $this->assertSame(201, $attRes->getStatusCode());
        $attData = json_decode($attRes->getContent(), true);
        $this->assertTrue($attData['success']);
        $this->assertSame(1, $attData['totalPresent']);

        // 2. Save CIA
        $ciaReq = new Request('POST', '/api/faculty/cia/save', [], [
            '_csrf'            => $csrf,
            'subject_id'       => 'sub-mjc-hist',
            'student_id'       => 'std-fac-01',
            'semester'         => 1,
            'mid_term_exam'    => 13.5,
            'assignment'       => 8.5,
            'attendance_score' => 4.5,
        ]);

        $ciaRes = $facultyController->saveCia($ciaReq);
        $this->assertSame(200, $ciaRes->getStatusCode());
        $ciaData = json_decode($ciaRes->getContent(), true);
        $this->assertTrue($ciaData['success']);
        $this->assertSame(26.50, (float)$ciaData['totalCiaScore']);

        // 3. Invalid CIA exceeds max 15
        $invalidCiaReq = new Request('POST', '/api/faculty/cia/save', [], [
            '_csrf'            => $csrf,
            'subject_id'       => 'sub-mjc-hist',
            'student_id'       => 'std-fac-01',
            'semester'         => 1,
            'mid_term_exam'    => 18.0, // Exceeds max 15
            'assignment'       => 8.0,
            'attendance_score' => 4.0,
        ]);
        $invalidRes = $facultyController->saveCia($invalidCiaReq);
        $this->assertSame(422, $invalidRes->getStatusCode());
    }

    public function testStudentPortalReceiptAndProgression(): void
    {
        $this->testDb->exec('
            INSERT INTO users (id, username, email, password_hash, role, is_active)
            VALUES (\'usr-std-01\', \'std@gmail.com\', \'std@gmail.com\', \'hash\', \'STUDENT\', 1);

            INSERT INTO students (id, user_id, application_no, registration_code, college_roll_no, full_name, father_name, mother_name, date_of_birth, gender, phone, email, address, session_id, course_id, current_semester, status)
            VALUES (\'std-01\', \'usr-std-01\', \'MKR-2026-00777\', \'MKR-REG-777\', \'26/BASS/0777\', \'Anjali Kumari\', \'Father\', \'Mother\', \'2005-09-09\', \'Female\', \'9876543299\', \'std@gmail.com\', \'Sitamarhi\', \'sess-2026-2030\', \'crs-ba-ss\', 1, \'ADMITTED\');

            INSERT INTO fee_payments (id, receipt_no, student_id, target_semester, payment_type, title, amount, payment_method, status, paid_at)
            VALUES (\'fee-01\', \'REC-2026-S1-000777\', \'std-01\', 1, \'ADMISSION_CUM_SEM1\', \'Semester 1 Fee\', 2800.00, \'ONLINE_GATEWAY\', \'COMPLETED\', CURRENT_TIMESTAMP);
        ');

        Auth::setUser([
            'id'       => 'usr-std-01',
            'username' => 'std@gmail.com',
            'email'    => 'std@gmail.com',
            'role'     => 'STUDENT',
        ]);

        $csrf = AuthServer::csrfToken();
        $studentController = new StudentController($this->testDb);

        // 1. Get Receipt Details
        $rcptReq = new Request('GET', '/api/student/receipt', ['receipt_no' => 'REC-2026-S1-000777']);
        $rcptRes = $studentController->getReceipt($rcptReq);
        $this->assertSame(200, $rcptRes->getStatusCode());
        $rcptData = json_decode($rcptRes->getContent(), true);
        $this->assertTrue($rcptData['success']);
        $this->assertSame('Anjali Kumari', $rcptData['receipt']['full_name']);

        // 2. Pay Progression to Semester 2
        $progReq = new Request('POST', '/api/student/pay-progression', [], [
            '_csrf'           => $csrf,
            'target_semester' => 2,
            'payment_method'  => 'ONLINE_GATEWAY',
        ]);
        $progRes = $studentController->payProgression($progReq);
        $this->assertSame(200, $progRes->getStatusCode());
        $progData = json_decode($progRes->getContent(), true);
        $this->assertTrue($progData['success']);
        $this->assertSame(2, $progData['targetSemester']);
        $this->assertStringStartsWith('REC-2026-S2-', $progData['receiptNo']);
    }

    protected function tearDown(): void
    {
        Auth::setUser(null);
        parent::tearDown();
    }
}
