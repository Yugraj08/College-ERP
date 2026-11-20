<?php
declare(strict_types=1);

namespace App\Tests;

use App\Config\CollegeConfig;
use App\Controllers\AuthController;
use App\Core\Auth;
use App\Core\AuthServer;
use App\Core\Database;
use App\Core\Request;
use PDO;
use PHPUnit\Framework\TestCase;

class AuthFlowTest extends TestCase
{
    private PDO $testDb;

    protected function setUp(): void
    {
        Auth::logout();
        $_SESSION = [];
        $_COOKIE = [];

        $this->testDb = new PDO('sqlite::memory:');
        $this->testDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->testDb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->initializeTestSchema($this->testDb);
        Database::setConnection($this->testDb);
    }

    protected function tearDown(): void
    {
        Auth::logout();
        $_SESSION = [];
        $_COOKIE = [];
        Database::setConnection(null);
    }

    private function initializeTestSchema(PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE users (
                id VARCHAR(36) PRIMARY KEY,
                email VARCHAR(191) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(32) NOT NULL,
                full_name VARCHAR(191) NOT NULL,
                is_active INT NOT NULL DEFAULT 1,
                phone VARCHAR(32) UNIQUE,
                last_login_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE departments (
                id VARCHAR(36) PRIMARY KEY,
                name VARCHAR(128) NOT NULL,
                code VARCHAR(32) NOT NULL,
                faculty_type VARCHAR(64) NOT NULL
            );

            CREATE TABLE faculty_profiles (
                id VARCHAR(36) PRIMARY KEY,
                user_id VARCHAR(36),
                employee_code VARCHAR(64) UNIQUE NOT NULL,
                full_name VARCHAR(191) NOT NULL,
                designation VARCHAR(64) NOT NULL,
                department_id VARCHAR(36) NOT NULL,
                email VARCHAR(191) UNIQUE NOT NULL,
                phone VARCHAR(32) UNIQUE NOT NULL,
                is_active INT NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE students (
                id VARCHAR(36) PRIMARY KEY,
                user_id VARCHAR(36),
                application_no VARCHAR(64) UNIQUE NOT NULL,
                registration_code VARCHAR(64) UNIQUE,
                college_roll_no VARCHAR(64) UNIQUE,
                full_name VARCHAR(191) NOT NULL,
                date_of_birth DATE NOT NULL,
                phone VARCHAR(32) NOT NULL,
                email VARCHAR(191) NOT NULL,
                course_id VARCHAR(36) NOT NULL,
                session_id VARCHAR(36) NOT NULL,
                current_semester INT NOT NULL DEFAULT 1,
                status VARCHAR(32) NOT NULL DEFAULT \'APPLIED\',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE audit_logs (
                id VARCHAR(36) PRIMARY KEY,
                user_id VARCHAR(36),
                action VARCHAR(64) NOT NULL,
                entity VARCHAR(64) NOT NULL,
                entity_id VARCHAR(64),
                old_values TEXT,
                new_values TEXT,
                details TEXT,
                ip_address VARCHAR(45),
                user_agent VARCHAR(255),
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ');

        $adminHash = Auth::hashPassword('Mkr@arvind98');
        $stmt = $pdo->prepare('
            INSERT INTO users (id, email, password_hash, role, full_name, is_active, phone)
            VALUES (\'usr-admin-01\', \'mkrcollege@gmail.com\', :hash, \'SUPER_ADMIN\', \'Principal Arvind Kumar\', 1, \'+917597052444\')
        ');
        $stmt->execute(['hash' => $adminHash]);

        $facultyHash = Auth::hashPassword('FacultyPass@123');
        $userStmt = $pdo->prepare('
            INSERT INTO users (id, email, password_hash, role, full_name, is_active, phone)
            VALUES (\'usr-fac-01\', \'history.hod@mkrcollege.com\', :hash, \'FACULTY\', \'Dr. R. K. Sharma\', 1, \'9876543210\')
        ');
        $userStmt->execute(['hash' => $facultyHash]);

        $facStmt = $pdo->prepare('
            INSERT INTO faculty_profiles (id, user_id, employee_code, full_name, designation, department_id, email, phone, is_active)
            VALUES (\'fac-01\', \'usr-fac-01\', \'FAC-HIST-01\', \'Dr. R. K. Sharma\', \'Professor & HOD\', \'dept-hist\', \'history.hod@mkrcollege.com\', \'9876543210\', 1)
        ');
        $facStmt->execute();

        $studentStmt = $pdo->prepare('
            INSERT INTO students (id, user_id, application_no, registration_code, college_roll_no, full_name, date_of_birth, phone, email, course_id, session_id, current_semester, status)
            VALUES (\'std-01\', NULL, \'MKR-2026-00001\', \'MKR-REG-2026-00001\', \'26/BASS/0001\', \'Aarav Kumar\', \'2005-08-15\', \'9123456780\', \'aarav@gmail.com\', \'crs-ba-ss\', \'sess-2026-2030\', 1, \'ADMITTED\')
        ');
        $studentStmt->execute();
    }

    public function testShowLoginFormRendersSuccessfully(): void
    {
        $controller = new AuthController($this->testDb);
        $request = new Request('GET', '/login');

        $response = $controller->showLoginForm($request);

        $this->assertSame(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('ERP Portal Login', $content);
        $this->assertStringContainsString('action="/login"', $content);
        $this->assertStringContainsString('SUPER_ADMIN', $content);
        $this->assertStringContainsString('FACULTY', $content);
        $this->assertStringContainsString('STUDENT', $content);
        $this->assertStringContainsString('/assets/erp-panel/image-1.png', $content);
        $this->assertStringContainsString('/assets/erp-panel/image-2.webp', $content);
    }

    public function testShowLoginFormRedirectsIfAlreadyLoggedIn(): void
    {
        Auth::login([
            'id'        => 'usr-admin-01',
            'email'     => 'mkrcollege@gmail.com',
            'role'      => 'SUPER_ADMIN',
            'full_name' => 'Principal',
        ]);

        $controller = new AuthController($this->testDb);
        $request = new Request('GET', '/login');

        $response = $controller->showLoginForm($request);

        $this->assertSame(302, $response->getStatusCode());
        $headers = $response->getHeaders();
        $this->assertSame('/admin/principal', $headers['Location'] ?? '');
    }

    public function testLoginRejectsInvalidCsrf(): void
    {
        $controller = new AuthController($this->testDb);
        $postData = [
            'role'       => 'SUPER_ADMIN',
            'identifier' => 'mkrcollege@gmail.com',
            'password'   => 'Mkr@arvind98',
            '_csrf'      => 'invalid_csrf_token',
        ];
        $request = new Request('POST', '/login', [], $postData, [], [], ['HTTP_ACCEPT' => 'application/json']);

        $response = $controller->login($request);

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Security session validation failed', $data['error']);
    }

    public function testSuperAdminLoginSuccess(): void
    {
        $csrf = AuthServer::csrfToken();
        $controller = new AuthController($this->testDb);
        $postData = [
            'role'       => 'SUPER_ADMIN',
            'identifier' => 'mkrcollege@gmail.com',
            'password'   => 'Mkr@arvind98',
            '_csrf'      => $csrf,
        ];
        $request = new Request('POST', '/login', [], $postData);

        $response = $controller->login($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/admin/principal', $response->getHeaders()['Location'] ?? '');
        $this->assertTrue(Auth::check());
        $this->assertSame('SUPER_ADMIN', Auth::role());
        $this->assertSame('usr-admin-01', Auth::id());
    }

    public function testSuperAdminLoginFailsWithInvalidPassword(): void
    {
        $csrf = AuthServer::csrfToken();
        $controller = new AuthController($this->testDb);
        $postData = [
            'role'       => 'SUPER_ADMIN',
            'identifier' => 'mkrcollege@gmail.com',
            'password'   => 'WrongAdminPassword',
            '_csrf'      => $csrf,
        ];
        $request = new Request('POST', '/login', [], $postData, [], [], ['HTTP_ACCEPT' => 'application/json']);

        $response = $controller->login($request);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertFalse(Auth::check());
    }

    public function testFacultyLoginSuccessByEmployeeCode(): void
    {
        $csrf = AuthServer::csrfToken();
        $controller = new AuthController($this->testDb);
        $postData = [
            'role'       => 'FACULTY',
            'identifier' => 'FAC-HIST-01',
            'password'   => 'FacultyPass@123',
            '_csrf'      => $csrf,
        ];
        $request = new Request('POST', '/login', [], $postData);

        $response = $controller->login($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/faculty', $response->getHeaders()['Location'] ?? '');
        $this->assertTrue(Auth::check());
        $this->assertSame('FACULTY', Auth::role());
        $this->assertSame('fac-01', Auth::id());
    }

    public function testFacultyLoginSuccessByPhone(): void
    {
        $csrf = AuthServer::csrfToken();
        $controller = new AuthController($this->testDb);
        $postData = [
            'role'       => 'FACULTY',
            'identifier' => '9876543210',
            'password'   => 'FacultyPass@123',
            '_csrf'      => $csrf,
        ];
        $request = new Request('POST', '/login', [], $postData);

        $response = $controller->login($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/faculty', $response->getHeaders()['Location'] ?? '');
        $this->assertTrue(Auth::check());
        $this->assertSame('FACULTY', Auth::role());
    }

    public function testStudentLoginSuccessByRollNoAndDob(): void
    {
        $csrf = AuthServer::csrfToken();
        $controller = new AuthController($this->testDb);
        $postData = [
            'role'       => 'STUDENT',
            'identifier' => '26/BASS/0001',
            'password'   => '2005-08-15',
            '_csrf'      => $csrf,
        ];
        $request = new Request('POST', '/login', [], $postData);

        $response = $controller->login($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/student', $response->getHeaders()['Location'] ?? '');
        $this->assertTrue(Auth::check());
        $this->assertSame('STUDENT', Auth::role());
        $this->assertSame('std-01', Auth::id());
    }

    public function testStudentLoginSuccessByApplicationNoAndFormattedDob(): void
    {
        $csrf = AuthServer::csrfToken();
        $controller = new AuthController($this->testDb);
        $postData = [
            'role'       => 'STUDENT',
            'identifier' => 'MKR-2026-00001',
            'password'   => '15-08-2005',
            '_csrf'      => $csrf,
        ];
        $request = new Request('POST', '/login', [], $postData);

        $response = $controller->login($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/student', $response->getHeaders()['Location'] ?? '');
        $this->assertTrue(Auth::check());
    }

    public function testStudentLoginSuccessByRegistrationCodeAndPhone(): void
    {
        $csrf = AuthServer::csrfToken();
        $controller = new AuthController($this->testDb);
        $postData = [
            'role'       => 'STUDENT',
            'identifier' => 'MKR-REG-2026-00001',
            'password'   => '9123456780',
            '_csrf'      => $csrf,
        ];
        $request = new Request('POST', '/login', [], $postData);

        $response = $controller->login($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/student', $response->getHeaders()['Location'] ?? '');
        $this->assertTrue(Auth::check());
    }

    public function testLogoutClearsSession(): void
    {
        Auth::login([
            'id'   => 'usr-admin-01',
            'role' => 'SUPER_ADMIN',
        ]);
        $this->assertTrue(Auth::check());

        $controller = new AuthController($this->testDb);
        $request = new Request('GET', '/logout');

        $response = $controller->logout($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaders()['Location'] ?? '');
        $this->assertFalse(Auth::check());
    }

    public function testForgotPasswordApiReturnsHelpdeskInfo(): void
    {
        $controller = new AuthController($this->testDb);
        $postData = ['role' => 'STUDENT', 'identifier' => 'MKR-2026-00001'];
        $request = new Request('POST', '/api/auth/forgot-password', [], $postData);

        $response = $controller->forgotPassword($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertSame('STUDENT', $data['role']);
        $this->assertSame(CollegeConfig::HELPLINE_PHONE, $data['helpline']);
        $this->assertSame(CollegeConfig::OFFICIAL_EMAIL, $data['email']);
        $this->assertStringContainsString('Date of Birth', $data['instructions']);
    }

    public function testApiAuthLoginReturnsJsonWithRedirect(): void
    {
        $csrf = AuthServer::csrfToken();
        $controller = new AuthController($this->testDb);
        $postData = [
            'role'       => 'SUPER_ADMIN',
            'identifier' => 'mkrcollege@gmail.com',
            'password'   => 'Mkr@arvind98',
            '_csrf'      => $csrf,
        ];
        $request = new Request('POST', '/api/auth/login', [], $postData);

        $response = $controller->login($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('SUPER_ADMIN', $data['role']);
        $this->assertSame('/admin/principal', $data['redirect']);
        $this->assertTrue(Auth::check());
        $this->assertSame('mkr_erp_token', Auth::TOKEN_COOKIE_NAME);
    }

    public function testApiAuthMeEndpoint(): void
    {
        $controller = new AuthController($this->testDb);

        // 1. Unauthenticated request
        $unauthRequest = new Request('GET', '/api/auth/me');
        $unauthResponse = $controller->me($unauthRequest);
        $this->assertSame(401, $unauthResponse->getStatusCode());
        $unauthData = json_decode($unauthResponse->getContent(), true);
        $this->assertFalse($unauthData['authenticated']);

        // 2. Authenticated request
        Auth::login([
            'id'        => 'usr-admin-01',
            'email'     => 'mkrcollege@gmail.com',
            'role'      => 'SUPER_ADMIN',
            'full_name' => 'Principal Arvind Kumar',
        ]);

        $authRequest = new Request('GET', '/api/auth/me');
        $authResponse = $controller->me($authRequest);
        $this->assertSame(200, $authResponse->getStatusCode());
        $authData = json_decode($authResponse->getContent(), true);
        $this->assertTrue($authData['authenticated']);
        $this->assertSame('SUPER_ADMIN', $authData['user']['role']);
        $this->assertSame('Principal Arvind Kumar', $authData['user']['full_name']);
    }

    public function testApiAuthLogoutReturnsJson(): void
    {
        Auth::login([
            'id'   => 'usr-admin-01',
            'role' => 'SUPER_ADMIN',
        ]);
        $this->assertTrue(Auth::check());

        $controller = new AuthController($this->testDb);
        $request = new Request('POST', '/api/auth/logout');

        $response = $controller->logout($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('/login', $data['redirect']);
        $this->assertFalse(Auth::check());
    }

    public function testBootstrapAdminFallbackWhenDbEmpty(): void
    {
        $emptyDb = new PDO('sqlite::memory:');
        $emptyDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $csrf = AuthServer::csrfToken();

        $controller = new AuthController($emptyDb);
        $postData = [
            'role'       => 'SUPER_ADMIN',
            'identifier' => 'mkrcollege@gmail.com',
            'password'   => 'Mkr@arvind98',
            '_csrf'      => $csrf,
        ];
        $request = new Request('POST', '/api/auth/login', [], $postData);

        $response = $controller->login($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('SUPER_ADMIN', $data['role']);
        $this->assertSame('/admin/principal', $data['redirect']);
        $this->assertTrue(Auth::check());
    }
}
