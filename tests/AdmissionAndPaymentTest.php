<?php
declare(strict_types=1);

namespace App\Tests;

use App\Config\CollegeConfig;
use App\Controllers\AdmissionController;
use App\Core\AuthServer;
use App\Core\Database;
use App\Core\Request;
use App\Services\AcademicStructureService;
use App\Services\Payment\BankChallanDriver;
use App\Services\Payment\PaymentService;
use App\Services\Payment\RazorpayPaymentDriver;
use App\Services\Payment\SimulatorPaymentDriver;
use App\Services\Payment\UpiPaymentDriver;
use PDO;
use PHPUnit\Framework\TestCase;

class AdmissionAndPaymentTest extends TestCase
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
            CREATE TABLE users (
                id VARCHAR(36) PRIMARY KEY,
                email VARCHAR(191) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(32) NOT NULL,
                full_name VARCHAR(191) NOT NULL,
                is_active INT NOT NULL DEFAULT 1,
                phone VARCHAR(32) UNIQUE
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
                name VARCHAR(128) UNIQUE NOT NULL,
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

            CREATE TABLE students (
                id VARCHAR(36) PRIMARY KEY,
                user_id VARCHAR(36),
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

        $pdo->exec('
            INSERT INTO academic_sessions (id, name, start_year, end_year, is_current)
            VALUES (\'sess-2026-2030\', \'2026-2030\', 2026, 2030, 1);

            INSERT INTO departments (id, name, code, faculty_type)
            VALUES (\'dept-hist\', \'History\', \'HIST\', \'Social Science\');

            INSERT INTO courses (id, name, code, duration_year, total_semesters, department_id)
            VALUES (\'crs-ba-ss\', \'Bachelor of Arts – Social Science\', \'BA-SS\', 4, 8, \'dept-hist\');
        ');
    }

    public function testPaymentDriverInterfaceAndResolution(): void
    {
        $service = new PaymentService($this->testDb);

        $driver = $service->resolveDriver('ONLINE_GATEWAY');
        $this->assertInstanceOf(SimulatorPaymentDriver::class, $driver);
        $this->assertSame('SIMULATOR', $driver->getDriverName());

        $upiDriver = $service->resolveDriver('UPI');
        $this->assertInstanceOf(UpiPaymentDriver::class, $upiDriver);
        $this->assertSame('UPI', $upiDriver->getDriverName());

        $challanDriver = $service->resolveDriver('BANK_CHALLAN');
        $this->assertInstanceOf(BankChallanDriver::class, $challanDriver);
        $this->assertSame('BANK_CHALLAN', $challanDriver->getDriverName());
    }

    public function testSimulatorPaymentVerification(): void
    {
        $driver = new SimulatorPaymentDriver();
        $order = $driver->createOrder(['amount' => 2800.00]);

        $this->assertStringStartsWith('sim_ord_', $order['orderId']);
        $this->assertSame(2800.00, $order['amount']);

        $verification = $driver->verifyPayment([
            'orderId'        => $order['orderId'],
            'transactionRef' => 'TXN-SIM-TEST-001',
        ]);

        $this->assertTrue($verification['verified']);
        $this->assertSame('TXN-SIM-TEST-001', $verification['transactionRef']);
        $this->assertNotEmpty($verification['signature']);
    }

    public function testRazorpayDriverSignatureVerification(): void
    {
        $driver = new RazorpayPaymentDriver('rzp_test_key_123', 'rzp_test_secret_abc');
        $this->assertTrue($driver->isConfigured());

        $orderId = 'order_test_987654';
        $paymentId = 'pay_test_123456';
        $signature = hash_hmac('sha256', $orderId . '|' . $paymentId, 'rzp_test_secret_abc');

        $result = $driver->verifyPayment([
            'razorpay_order_id'   => $orderId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature'  => $signature,
        ]);

        $this->assertTrue($result['verified']);
        $this->assertSame($paymentId, $result['transactionRef']);

        // Invalid signature must fail
        $invalid = $driver->verifyPayment([
            'razorpay_order_id'   => $orderId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature'  => 'tampered_signature',
        ]);
        $this->assertFalse($invalid['verified']);
    }

    public function testUpiDriverVerification(): void
    {
        $driver = new UpiPaymentDriver();
        $order = $driver->createOrder(['amount' => 2800.00]);
        $this->assertStringContainsString('mkrcollege@sbi', $order['upiUri']);

        $validUtr = $driver->verifyPayment(['utrNumber' => '423589123456']);
        $this->assertTrue($validUtr['verified']);
        $this->assertSame('UPI-423589123456', $validUtr['transactionRef']);

        $invalidUtr = $driver->verifyPayment(['utrNumber' => '123']);
        $this->assertFalse($invalidUtr['verified']);
    }

    public function testSequentialReceiptGeneration(): void
    {
        $service = new PaymentService($this->testDb);

        $receipt1 = $service->generateSequentialReceiptNo(2026, 1);
        $this->assertSame('REC-2026-S1-000001', $receipt1);

        $stmt = $this->testDb->prepare('
            INSERT INTO fee_payments (id, receipt_no, student_id, target_semester, payment_type, title, amount, payment_method, status)
            VALUES (\'p-1\', :rcpt, \'s-1\', 1, \'ADMISSION_CUM_SEM1\', \'Fee\', 2800, \'ONLINE_GATEWAY\', \'COMPLETED\')
        ');
        $stmt->execute(['rcpt' => $receipt1]);

        $receipt2 = $service->generateSequentialReceiptNo(2026, 1);
        $this->assertSame('REC-2026-S1-000002', $receipt2);
    }

    public function testFreshAdmissionApplicationWorkflow(): void
    {
        $csrf = AuthServer::csrfToken();
        $controller = new AdmissionController($this->testDb);

        $payload = [
            '_csrf'          => $csrf,
            'full_name'      => 'Aarav Sharma',
            'father_name'    => 'Ramesh Sharma',
            'mother_name'    => 'Sunita Sharma',
            'date_of_birth'  => '2005-08-15',
            'gender'         => 'Male',
            'category'       => 'General',
            'phone'          => '9876543210',
            'email'          => 'aarav@gmail.com',
            'address'        => 'Punaura, Sitamarhi',
            'course_code'    => 'BA-SS',
            'mjc_subject'    => 'HISTORY',
            'mic_subject'    => 'POLITICAL SCIENCE',
            'mdc_subject'    => 'ECONOMICS',
            'aec_subject'    => 'HINDI',
            'sec_subject'    => 'DIGITAL MARKETING',
            'vac_subject'    => 'ETHICS AND CULTURE',
            'payment_method' => 'ONLINE_GATEWAY',
            'transaction_ref'=> 'TXN-SIM-AARAV-001',
        ];

        $request = new Request('POST', '/api/admissions/apply', [], $payload);
        $response = $controller->applyFresh($request);

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertSame('MKR-2026-00001', $data['applicationNo']);
        $this->assertSame('MKR-REG-2026-00001', $data['registrationCode']);
        $this->assertSame('REC-2026-S1-000001', $data['receiptNo']);
        $this->assertSame(2800.00, (float)$data['amountPaid']);
        $this->assertCount(6, $data['subjects']);

        // Check database persistence
        $studentStmt = $this->testDb->prepare('SELECT * FROM students WHERE application_no = :app');
        $studentStmt->execute(['app' => 'MKR-2026-00001']);
        $student = $studentStmt->fetch();
        $this->assertNotEmpty($student);
        $this->assertSame('Aarav Sharma', $student['full_name']);
        $this->assertSame('APPLIED', $student['status']);

        // Check fee payment recorded
        $feeStmt = $this->testDb->prepare('SELECT * FROM fee_payments WHERE receipt_no = :rcpt');
        $feeStmt->execute(['rcpt' => 'REC-2026-S1-000001']);
        $fee = $feeStmt->fetch();
        $this->assertNotEmpty($fee);
        $this->assertSame('COMPLETED', $fee['status']);
        $this->assertSame(2800.00, (float)$fee['amount']);

        // Check semester record updated
        $semStmt = $this->testDb->prepare('SELECT * FROM student_semester_records WHERE student_id = :sid');
        $semStmt->execute(['sid' => $student['id']]);
        $sem = $semStmt->fetch();
        $this->assertSame(1, (int)$sem['is_fee_cleared']);
    }

    public function testStatusLookupEndpoint(): void
    {
        // First submit an application
        $csrf = AuthServer::csrfToken();
        $controller = new AdmissionController($this->testDb);

        $payload = [
            '_csrf'          => $csrf,
            'full_name'      => 'Pooja Kumari',
            'father_name'    => 'Manoj Kumar',
            'mother_name'    => 'Kiran Devi',
            'date_of_birth'  => '2006-03-20',
            'gender'         => 'Female',
            'category'       => 'OBC',
            'phone'          => '9123456789',
            'email'          => 'pooja@gmail.com',
            'address'        => 'Dumra, Sitamarhi',
            'course_code'    => 'BA-SS',
            'mjc_subject'    => 'POLITICAL SCIENCE',
            'mic_subject'    => 'HISTORY',
            'mdc_subject'    => 'SOCIOLOGY',
            'aec_subject'    => 'ENGLISH',
            'sec_subject'    => 'COMPUTER BASICS',
            'vac_subject'    => 'ENVIRONMENTAL STUDIES',
            'payment_method' => 'ONLINE_GATEWAY',
        ];
        $controller->applyFresh(new Request('POST', '/api/admissions/apply', [], $payload));

        // Query status lookup
        $lookupReq = new Request('POST', '/api/admissions/status', [], [
            'application_no' => 'MKR-2026-00001',
            'dob_or_phone'   => '2006-03-20',
        ]);
        $lookupRes = $controller->statusLookup($lookupReq);

        $this->assertSame(200, $lookupRes->getStatusCode());
        $data = json_decode($lookupRes->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertSame('Pooja Kumari', $data['student']['fullName']);
        $this->assertNotEmpty($data['subjects']);
        $this->assertNotEmpty($data['payments']);
    }

    public function testProgressionRegistration(): void
    {
        $csrf = AuthServer::csrfToken();
        $controller = new AdmissionController($this->testDb);

        // Pre-insert an admitted student in Semester 1
        $this->testDb->exec('
            INSERT INTO students (
                id, application_no, registration_code, college_roll_no, full_name,
                father_name, mother_name, date_of_birth, gender, category, phone, email,
                address, session_id, course_id, current_semester, status
            ) VALUES (
                \'std-prog-01\', \'MKR-2026-00099\', \'MKR-REG-2026-00099\', \'26/BASS/0099\', \'Rohan Verma\',
                \'Sunil Verma\', \'Anita Devi\', \'2005-01-01\', \'Male\', \'General\', \'9871112233\', \'rohan@gmail.com\',
                \'Sitamarhi\', \'sess-2026-2030\', \'crs-ba-ss\', 1, \'ADMITTED\'
            );
        ');

        // Progress to Semester 2
        $progReq = new Request('POST', '/api/admissions/progression', [], [
            '_csrf'           => $csrf,
            'identifier'      => '26/BASS/0099',
            'target_semester' => 2,
            'payment_method'  => 'ONLINE_GATEWAY',
        ]);

        $progRes = $controller->progressionRegister($progReq);

        $this->assertSame(200, $progRes->getStatusCode());
        $data = json_decode($progRes->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertSame(2, $data['targetSemester']);
        $this->assertSame(2800.00, (float)$data['amountPaid']);
        $this->assertStringStartsWith('REC-2026-S2-', $data['receiptNo']);

        // Out-of-order semester jump (Sem 2 -> Sem 4) must fail
        $invalidJumpReq = new Request('POST', '/api/admissions/progression', [], [
            '_csrf'           => $csrf,
            'identifier'      => '26/BASS/0099',
            'target_semester' => 4,
            'payment_method'  => 'ONLINE_GATEWAY',
        ]);
        $invalidJumpRes = $controller->progressionRegister($invalidJumpReq);
        $this->assertSame(422, $invalidJumpRes->getStatusCode());
        $invalidJumpData = json_decode($invalidJumpRes->getContent(), true);
        $this->assertFalse($invalidJumpData['success']);
        $this->assertStringContainsString('Invalid semester progression', $invalidJumpData['error']);

        // Double billing prevention (attempting to register for Sem 2 again) must fail
        $doubleBillingReq = new Request('POST', '/api/admissions/progression', [], [
            '_csrf'           => $csrf,
            'identifier'      => '26/BASS/0099',
            'target_semester' => 2,
            'payment_method'  => 'ONLINE_GATEWAY',
        ]);
        $doubleBillingRes = $controller->progressionRegister($doubleBillingReq);
        $this->assertSame(422, $doubleBillingRes->getStatusCode());
        $doubleBillingData = json_decode($doubleBillingRes->getContent(), true);
        $this->assertFalse($doubleBillingData['success']);
    }
}
