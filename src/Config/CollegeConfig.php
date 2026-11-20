<?php
declare(strict_types=1);

namespace App\Config;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

class CollegeConfig
{
    public const INSTITUTION_NAME = 'Matashri Kaushalya Ramdev Dr. Ganesh Ray Degree College';
    public const INSTITUTION_CODE = 'MKR';
    public const AFFILIATED_UNIVERSITY = 'B.R.A. Bihar University (BRABU), Muzaffarpur';
    public const CAMPUS_LOCATION = 'Punaura Dham, Sitamarhi, Bihar (843302)';
    public const HELPLINE_PHONE = '+91-7597052444';
    public const OFFICIAL_EMAIL = 'mkrcollege@gmail.com';

    public const OVERDUE_GRACE_DAYS = 30;
    public const MAX_SEMESTERS = 8;

    public const COLLEGE_DEPARTMENTS = [
        'History',
        'Political Science',
        'Economics',
        'Commerce',
        'Hindi',
        'English',
        'Philosophy',
        'Sociology',
        'Psychology',
        'Geography',
        'Urdu',
        'Sanskrit',
    ];

    public const COURSE_CATALOG = [
        'BA-SS' => [
            'code'           => 'BA-SS',
            'name'           => "Bachelor's of Arts (BA) – Social Science",
            'facultyType'    => 'Social Science',
            'rollPrefix'     => 'BASS',
            'tuitionFee'     => 1800.00,
            'admissionFee'   => 500.00,
            'examFee'        => 500.00,
            'miscFee'        => 0.00,
            'totalFee'       => 2800.00,
            'durationYears'  => 4,
            'totalSemesters' => 8,
        ],
        'BA-HUM' => [
            'code'           => 'BA-HUM',
            'name'           => "Bachelor's of Arts (B.A) – Humanities",
            'facultyType'    => 'Humanities',
            'rollPrefix'     => 'BAHUM',
            'tuitionFee'     => 1800.00,
            'admissionFee'   => 500.00,
            'examFee'        => 500.00,
            'miscFee'        => 0.00,
            'totalFee'       => 2800.00,
            'durationYears'  => 4,
            'totalSemesters' => 8,
        ],
        'BCOM' => [
            'code'           => 'BCOM',
            'name'           => "Bachelor's of Commerce (B.COM)",
            'facultyType'    => 'Commerce',
            'rollPrefix'     => 'BCOM',
            'tuitionFee'     => 2200.00,
            'admissionFee'   => 500.00,
            'examFee'        => 500.00,
            'miscFee'        => 0.00,
            'totalFee'       => 3200.00,
            'durationYears'  => 4,
            'totalSemesters' => 8,
        ],
    ];

    public const FACULTY_SUBJECTS = [
        'Social Science' => [
            'mjc' => [
                'HISTORY', 'POLITICAL SCIENCE', 'ECONOMICS', 'SOCIOLOGY', 
                'PSYCHOLOGY', 'GEOGRAPHY', 'HOME SCIENCE', 
                'ANCIENT HISTORY & CULTURE', 'PUBLIC ADMINISTRATION'
            ],
            'mic' => [
                'POLITICAL SCIENCE', 'HISTORY', 'ECONOMICS', 'SOCIOLOGY', 
                'PSYCHOLOGY', 'GEOGRAPHY', 'HOME SCIENCE', 'ENGLISH', 'HINDI'
            ],
            'mdc' => ['ECONOMICS', 'HISTORY', 'POLITICAL SCIENCE', 'PHILOSOPHY', 'SOCIOLOGY', 'ENGLISH', 'HINDI'],
            'aec' => ['Hindi', 'English', 'Urdu', 'Sanskrit'],
            'sec' => ['Creative Writing', 'Digital Marketing', 'Basic IT Tools'],
            'vac' => ['Development of Personality', 'Ethics and Values', 'Swachh Bharat'],
        ],
        'Humanities' => [
            'mjc' => ['HINDI', 'ENGLISH', 'URDU', 'PHILOSOPHY', 'MUSIC', 'SANSKRIT', 'MAITHILI', 'BHOJPURI'],
            'mic' => ['ENGLISH', 'HINDI', 'URDU', 'HISTORY', 'POLITICAL SCIENCE', 'PHILOSOPHY', 'MUSIC'],
            'mdc' => ['HISTORY', 'POLITICAL SCIENCE', 'SOCIOLOGY', 'PHILOSOPHY', 'ECONOMICS'],
            'aec' => ['Hindi', 'English', 'Urdu', 'Sanskrit'],
            'sec' => ['Creative Writing', 'Digital Marketing', 'Basic IT Tools'],
            'vac' => ['Development of Personality', 'Ethics and Values', 'Swachh Bharat'],
        ],
        'Commerce' => [
            'mjc' => ['ACCOUNTING & FINANCE', 'MARKETING', 'HUMAN RESOURCE MANAGEMENT'],
            'mic' => ['BUSINESS MANAGEMENT', 'ECONOMICS', 'MARKETING', 'ACCOUNTING & FINANCE'],
            'mdc' => ['ECONOMICS', 'COMMUNICATION STUDIES', 'POLITICAL SCIENCE', 'SOCIOLOGY'],
            'aec' => ['Hindi', 'English', 'Urdu', 'Sanskrit'],
            'sec' => ['Digital Marketing', 'Basic IT Tools', 'Creative Writing'],
            'vac' => ['Development of Personality', 'Financial Literacy', 'Ethics and Values'],
        ],
    ];

    /**
     * Resolves course code ("BA-SS", "BA-HUM", "BCOM") or faculty label ("Social Science", etc.)
     *
     * @return array<string, mixed>
     */
    public static function resolveCourseConfig(string $identifier): array
    {
        $normalized = strtolower(trim($identifier));

        if ($normalized === 'bcom' || str_contains($normalized, 'commerce')) {
            return self::COURSE_CATALOG['BCOM'];
        }
        if ($normalized === 'ba-hum' || str_contains($normalized, 'humanities')) {
            return self::COURSE_CATALOG['BA-HUM'];
        }
        return self::COURSE_CATALOG['BA-SS'];
    }

    /**
     * @return array{tuitionFee: float, admissionFee: float, examFee: float, miscFee: float, totalAmount: float}
     */
    public static function calculateSemesterFeeBreakdown(string $courseIdentifier, int $semester = 1): array
    {
        $cfg = self::resolveCourseConfig($courseIdentifier);
        return [
            'tuitionFee'   => $cfg['tuitionFee'],
            'admissionFee' => $cfg['admissionFee'],
            'examFee'      => $cfg['examFee'],
            'miscFee'      => $cfg['miscFee'],
            'totalAmount'  => $cfg['totalFee'],
        ];
    }

    public static function getCoursePrefix(string $courseIdentifier): string
    {
        return self::resolveCourseConfig($courseIdentifier)['rollPrefix'];
    }

    public static function getCourseFee(string $courseIdentifier): float
    {
        return self::resolveCourseConfig($courseIdentifier)['totalFee'];
    }

    public static function formatApplicationNo(int $year, int $serial): string
    {
        return sprintf('%s-%d-%05d', self::INSTITUTION_CODE, $year, $serial);
    }

    public static function formatRegistrationCode(int $year, int $serial): string
    {
        return sprintf('%s-REG-%d-%05d', self::INSTITUTION_CODE, $year, $serial);
    }

    public static function formatCollegeRollNo(int $year, string $rollPrefix, int $serial): string
    {
        $shortYear = $year % 100;
        $cleanPrefix = strtoupper(trim($rollPrefix));
        return sprintf('%02d/%s/%04d', $shortYear, $cleanPrefix, $serial);
    }

    /**
     * Normalizes YYYY-MM-DD to inclusive 00:00:00 to 23:59:59 boundaries.
     *
     * @return array{startDate: DateTimeImmutable, endDate: DateTimeImmutable}
     */
    public static function normalizeWindowDates(string $startDateInput, string $endDateInput): array
    {
        $start = new DateTimeImmutable(trim($startDateInput) . ' 00:00:00');
        $end   = new DateTimeImmutable(trim($endDateInput) . ' 23:59:59');

        if ($start > $end) {
            throw new InvalidArgumentException('Registration End Date cannot be earlier than Start Date.');
        }

        return ['startDate' => $start, 'endDate' => $end];
    }

    /**
     * @param array<string, mixed>|null $window
     * @return array{status: 'OPEN'|'NOT_STARTED'|'CLOSED', message: string}
     */
    public static function evaluateRegistrationWindow(?array $window, int $targetSemester, ?DateTimeInterface $now = null): array
    {
        if ($window === null || empty($window['is_active'])) {
            return ['status' => 'OPEN', 'message' => ''];
        }

        $current = $now ?? new DateTimeImmutable();
        $start   = new DateTimeImmutable((string)$window['start_date']);
        $end     = new DateTimeImmutable((string)$window['end_date']);

        if ($current < $start) {
            return [
                'status'  => 'NOT_STARTED',
                'message' => 'Registration for Semester ' . $targetSemester . ' will open on ' . $start->format('d M Y') . '.',
            ];
        }

        if ($current > $end) {
            return [
                'status'  => 'CLOSED',
                'message' => 'Registration deadline for Semester ' . $targetSemester . ' closed on ' . $end->format('d M Y') . '. Your record enters 30-day grace retention.',
            ];
        }

        return ['status' => 'OPEN', 'message' => ''];
    }

    /**
     * Computes exact 30-day overdue retention & auto-purge eligibility.
     *
     * @return array{isOverdue: bool, daysElapsed: int, daysRemaining: int, purgeEligible: bool}
     */
    public static function computeOverdueRetention(?string $markedForDeletionAt, int $graceDays = self::OVERDUE_GRACE_DAYS, ?DateTimeInterface $now = null): array
    {
        if ($markedForDeletionAt === null || trim($markedForDeletionAt) === '') {
            return [
                'isOverdue'     => false,
                'daysElapsed'   => 0,
                'daysRemaining' => $graceDays,
                'purgeEligible' => false,
            ];
        }

        $current       = $now ?? new DateTimeImmutable();
        $markedDate    = new DateTimeImmutable($markedForDeletionAt);
        
        $diffSeconds   = max(0, $current->getTimestamp() - $markedDate->getTimestamp());
        $daysElapsed   = (int)floor($diffSeconds / 86400);
        $daysRemaining = max(0, $graceDays - $daysElapsed);

        return [
            'isOverdue'     => true,
            'daysElapsed'   => $daysElapsed,
            'daysRemaining' => $daysRemaining,
            'purgeEligible' => $daysElapsed >= $graceDays,
        ];
    }
}
