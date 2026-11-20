<?php
declare(strict_types=1);

namespace App\Tests;

use App\Config\CollegeConfig;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Validator;
use App\Services\AcademicStructureService;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BusinessLogicTest extends TestCase
{
    public function testCanonicalProgramFees(): void
    {
        $this->assertSame(2800.00, CollegeConfig::COURSE_CATALOG['BA-SS']['totalFee']);
        $this->assertSame(2800.00, CollegeConfig::COURSE_CATALOG['BA-HUM']['totalFee']);
        $this->assertSame(3200.00, CollegeConfig::COURSE_CATALOG['BCOM']['totalFee']);

        $this->assertSame(2800.00, CollegeConfig::getCourseFee('BA-SS'));
        $this->assertSame(2800.00, CollegeConfig::getCourseFee('BA-HUM'));
        $this->assertSame(3200.00, CollegeConfig::getCourseFee('BCOM'));
    }

    public function testFeeBreakdownCalculation(): void
    {
        $breakdown = CollegeConfig::calculateSemesterFeeBreakdown('BA-SS', 1);
        $this->assertSame(1800.00, $breakdown['tuitionFee']);
        $this->assertSame(500.00, $breakdown['admissionFee']);
        $this->assertSame(500.00, $breakdown['examFee']);
        $this->assertSame(0.00, $breakdown['miscFee']);
        $this->assertSame(2800.00, $breakdown['totalAmount']);

        $bcomBreakdown = CollegeConfig::calculateSemesterFeeBreakdown('BCOM', 1);
        $this->assertSame(2200.00, $bcomBreakdown['tuitionFee']);
        $this->assertSame(3200.00, $bcomBreakdown['totalAmount']);
    }

    public function testBrabuCbcsStatutoryCredits(): void
    {
        $credits = AcademicStructureService::CBCS_CREDIT_MATRIX;
        $this->assertSame(6, $credits['MJC']);
        $this->assertSame(4, $credits['MIC']);
        $this->assertSame(4, $credits['MDC']);
        $this->assertSame(2, $credits['AEC']);
        $this->assertSame(2, $credits['SEC']);
        $this->assertSame(2, $credits['VAC']);

        $semesterTotal = array_sum($credits);
        $this->assertSame(20, $semesterTotal);
    }

    public function testIdentityNumberFormatters(): void
    {
        $this->assertSame('MKR-2026-00001', CollegeConfig::formatApplicationNo(2026, 1));
        $this->assertSame('MKR-2026-00123', CollegeConfig::formatApplicationNo(2026, 123));

        $this->assertSame('MKR-REG-2026-00001', CollegeConfig::formatRegistrationCode(2026, 1));
        $this->assertSame('MKR-REG-2026-00456', CollegeConfig::formatRegistrationCode(2026, 456));

        $this->assertSame('26/BASS/0001', CollegeConfig::formatCollegeRollNo(2026, 'BASS', 1));
        $this->assertSame('26/BAHUM/0042', CollegeConfig::formatCollegeRollNo(2026, 'BAHUM', 42));
        $this->assertSame('26/BCOM/0999', CollegeConfig::formatCollegeRollNo(2026, 'BCOM', 999));
    }

    public function testEvaluateRegistrationWindow(): void
    {
        $now = new DateTimeImmutable('2026-06-15 12:00:00');

        $activeWindow = [
            'is_active'  => 1,
            'start_date' => '2026-06-01 00:00:00',
            'end_date'   => '2026-06-30 23:59:59',
        ];
        $eval = CollegeConfig::evaluateRegistrationWindow($activeWindow, 1, $now);
        $this->assertSame('OPEN', $eval['status']);

        $futureWindow = [
            'is_active'  => 1,
            'start_date' => '2026-07-01 00:00:00',
            'end_date'   => '2026-07-31 23:59:59',
        ];
        $evalFuture = CollegeConfig::evaluateRegistrationWindow($futureWindow, 1, $now);
        $this->assertSame('NOT_STARTED', $evalFuture['status']);

        $pastWindow = [
            'is_active'  => 1,
            'start_date' => '2026-05-01 00:00:00',
            'end_date'   => '2026-05-31 23:59:59',
        ];
        $evalPast = CollegeConfig::evaluateRegistrationWindow($pastWindow, 1, $now);
        $this->assertSame('CLOSED', $evalPast['status']);

        $nullWindowEval = CollegeConfig::evaluateRegistrationWindow(null, 1, $now);
        $this->assertSame('OPEN', $nullWindowEval['status']);
    }

    public function testOverdueRetention(): void
    {
        $now = new DateTimeImmutable('2026-06-15 12:00:00');

        $active = CollegeConfig::computeOverdueRetention(null, 30, $now);
        $this->assertFalse($active['isOverdue']);
        $this->assertFalse($active['purgeEligible']);
        $this->assertSame(30, $active['daysRemaining']);

        $markedRecent = '2026-06-05 12:00:00';
        $grace = CollegeConfig::computeOverdueRetention($markedRecent, 30, $now);
        $this->assertTrue($grace['isOverdue']);
        $this->assertFalse($grace['purgeEligible']);
        $this->assertSame(10, $grace['daysElapsed']);
        $this->assertSame(20, $grace['daysRemaining']);

        $markedOld = '2026-05-01 12:00:00';
        $expired = CollegeConfig::computeOverdueRetention($markedOld, 30, $now);
        $this->assertTrue($expired['isOverdue']);
        $this->assertTrue($expired['purgeEligible']);
        $this->assertSame(0, $expired['daysRemaining']);
    }

    public function testNormalizeWindowDates(): void
    {
        $normalized = CollegeConfig::normalizeWindowDates('2026-06-01', '2026-06-30');
        $this->assertSame('2026-06-01 00:00:00', $normalized['startDate']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-30 23:59:59', $normalized['endDate']->format('Y-m-d H:i:s'));

        $this->expectException(InvalidArgumentException::class);
        CollegeConfig::normalizeWindowDates('2026-06-30', '2026-06-01');
    }

    public function testRouterTrailingSlashAndQueryParameters(): void
    {
        $router = new Router();
        $router->get('/login', function (Request $req): Response {
            return Response::json(['page' => 'login']);
        });
        $router->get('/api/students', function (Request $req): Response {
            return Response::json(['search' => $req->query('search')]);
        });

        // 1. Exact match
        $req1 = new Request('GET', '/login');
        $res1 = $router->dispatch($req1);
        $this->assertSame(200, $res1->getStatusCode());

        // 2. Trailing slash match
        $req2 = new Request('GET', '/login/');
        $res2 = $router->dispatch($req2);
        $this->assertSame(200, $res2->getStatusCode());

        // 3. Query parameter match without 404
        $req3 = new Request('GET', '/api/students?search=test&page=2', ['search' => 'test', 'page' => '2']);
        $res3 = $router->dispatch($req3);
        $this->assertSame(200, $res3->getStatusCode());
        $this->assertStringContainsString('test', $res3->getContent());
    }

    public function testBcryptCost12Security(): void
    {
        $password = 'Mkr@arvind98';
        $hash = Auth::hashPassword($password);

        $this->assertTrue(str_starts_with($hash, '$2y$12$'));
        $this->assertTrue(Auth::verifyPassword($password, $hash));
        $this->assertFalse(Auth::verifyPassword('WrongPassword', $hash));
    }

    public function testValidatorIndianPhoneRegex(): void
    {
        $validPhones = [
            ['phone' => '9876543210'],
            ['phone' => '7597052444'],
            ['phone' => '6200000000'],
            ['phone' => '+919876543210'],
        ];

        foreach ($validPhones as $data) {
            $v = Validator::make($data, ['phone' => 'required|phone']);
            $this->assertTrue($v->passes(), "Phone {$data['phone']} should be valid");
        }

        $invalidPhones = [
            ['phone' => '1234567890'], // starts with 1
            ['phone' => '5876543210'], // starts with 5
            ['phone' => '98765'],      // short
            ['phone' => 'abcdefghij'], // letters
        ];

        foreach ($invalidPhones as $data) {
            $v = Validator::make($data, ['phone' => 'required|phone']);
            $this->assertTrue($v->fails(), "Phone {$data['phone']} should be invalid");
        }
    }
}
