<?php
declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase;

class HostingerDeploymentTest extends TestCase
{
    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = dirname(__DIR__);
    }

    public function testRootHtaccessIntegrityAndSecurityDirectives(): void
    {
        $path = $this->rootDir . '/.htaccess';
        $this->assertFileExists($path, 'Root .htaccess must exist.');
        $content = (string)file_get_contents($path);

        $this->assertGreaterThan(50, strlen($content));
        $this->assertStringContainsString('RewriteEngine On', $content);
        $this->assertStringContainsString('Options -Indexes', $content);

        // Blocks direct access to backend internal directories
        $this->assertMatchesRegularExpression('/RewriteRule \^\(src\|views\|database\|tests\|vendor\)/', $content);

        // Blocks sensitive file extensions
        $this->assertMatchesRegularExpression('/RewriteRule \\\.\(env\.\*\|sql\|json\|lock\|xml\|md\|git\.\*\)\$/', $content);

        // Delegates to public/
        $this->assertStringContainsString('RewriteCond %{REQUEST_URI} !^/public/', $content);
        $this->assertStringContainsString('RewriteRule ^(.*)$ public/$1 [L]', $content);

        // Security headers
        $this->assertStringContainsString('Header set X-Content-Type-Options "nosniff"', $content);
        $this->assertStringContainsString('Header set X-Frame-Options "SAMEORIGIN"', $content);
    }

    public function testPublicHtaccessRoutingAndAssetCaching(): void
    {
        $path = $this->rootDir . '/public/.htaccess';
        $this->assertFileExists($path, 'public/.htaccess must exist.');
        $content = (string)file_get_contents($path);

        $this->assertGreaterThan(50, strlen($content));
        $this->assertStringContainsString('RewriteEngine On', $content);
        $this->assertStringContainsString('RewriteCond %{REQUEST_FILENAME} !-f', $content);
        $this->assertStringContainsString('RewriteCond %{REQUEST_FILENAME} !-d', $content);
        $this->assertStringContainsString('RewriteRule ^ index.php [L,QSA]', $content);

        // Prohibits access to .env and database dumps
        $this->assertStringContainsString('<FilesMatch', $content);
        $this->assertStringContainsString('Require all denied', $content);

        // Asset caching for LiteSpeed/Apache
        $this->assertStringContainsString('ExpiresActive On', $content);
        $this->assertStringContainsString('image/webp', $content);
        $this->assertStringContainsString('text/css', $content);
        $this->assertStringContainsString('application/javascript', $content);
    }

    public function testDatabaseSchemaCoversAllRequiredTablesAndForeignKeys(): void
    {
        $path = $this->rootDir . '/database/schema.sql';
        $this->assertFileExists($path, 'database/schema.sql must exist.');
        $sql = (string)file_get_contents($path);

        $expectedTables = [
            'users',
            'academic_sessions',
            'departments',
            'courses',
            'subjects',
            'students',
            'student_semester_records',
            'registration_windows',
            'faculty_profiles',
            'fee_payments',
            'promotion_histories',
            'attendance_sessions',
            'attendance_records',
            'continuous_internal_assessments',
            'grievance_tickets',
            'notices',
            'student_subject_enrollments',
            'audit_logs',
        ];

        foreach ($expectedTables as $table) {
            $this->assertMatchesRegularExpression(
                "/CREATE TABLE (IF NOT EXISTS )?{$table}\\b/i",
                $sql,
                "Schema must declare table: {$table}"
            );
        }

        // Engine & collation
        $this->assertStringContainsString('ENGINE=InnoDB', $sql);
        $this->assertStringContainsString('utf8mb4_unicode_ci', $sql);

        // Relational safety
        $this->assertStringContainsString('FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL', $sql);
        $this->assertStringContainsString('FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT', $sql);
        $this->assertStringContainsString('FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE', $sql);
    }

    public function testDatabaseSeedContainsCompleteCatalogAndNoSyntaxErrors(): void
    {
        $path = $this->rootDir . '/database/seed.sql';
        $this->assertFileExists($path, 'database/seed.sql must exist.');
        $seed = (string)file_get_contents($path);

        // Academic session 2026-2030
        $this->assertStringContainsString('2026-2030', $seed);

        // Core departments
        $this->assertStringContainsString('History', $seed);
        $this->assertStringContainsString('Political Science', $seed);
        $this->assertStringContainsString('Commerce', $seed);

        // Courses
        $this->assertStringContainsString('crs-ba-ss', $seed);
        $this->assertStringContainsString('crs-ba-hum', $seed);
        $this->assertStringContainsString('crs-bcom', $seed);

        // Super Admin account & Bcrypt cost 12 hash
        $this->assertStringContainsString('mkrcollege@gmail.com', $seed);
        $this->assertStringContainsString('$2y$12$', $seed);

        // BRABU CBCS Statutory 6 credits for Major Course
        $this->assertStringContainsString("'MJC', 'crs-ba-ss'", $seed);
        $this->assertMatchesRegularExpression("/'MJC',\\s*'crs-ba-ss'[^;]*6/s", $seed);

        // Ensure no duplicate ON DUPLICATE KEY clauses
        $this->assertDoesNotMatchRegularExpression(
            '/ON DUPLICATE KEY UPDATE[^\n]+\n\s*ON DUPLICATE KEY UPDATE/i',
            $seed,
            'Seed file must not contain duplicated ON DUPLICATE KEY UPDATE statements.'
        );
    }

    public function testStrictTypeDeclarationsAcrossAllSourceFiles(): void
    {
        $srcDir = $this->rootDir . '/src';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcDir));

        $scannedCount = 0;
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = (string)file_get_contents($file->getPathname());
                $this->assertStringStartsWith(
                    "<?php\ndeclare(strict_types=1);",
                    $content,
                    "File {$file->getFilename()} must begin with strict_types declaration."
                );
                $scannedCount++;
            }
        }

        $this->assertGreaterThanOrEqual(15, $scannedCount, 'Expected at least 15 core PHP files to be scanned.');
    }

    public function testDeploymentDocumentationCoversHostingerRequirements(): void
    {
        $path = $this->rootDir . '/DEPLOYMENT.md';
        $this->assertFileExists($path, 'DEPLOYMENT.md must exist.');
        $doc = (string)file_get_contents($path);

        $this->assertStringContainsString('Hostinger', $doc);
        $this->assertStringContainsString('LiteSpeed', $doc);
        $this->assertStringContainsString('PHP 8.2', $doc);
        $this->assertStringContainsString('pdo_mysql', $doc);
        $this->assertStringContainsString('schema.sql', $doc);
        $this->assertStringContainsString('seed.sql', $doc);
        $this->assertStringContainsString('mkrcollege@gmail.com', $doc);
        $this->assertStringContainsString('Mkr@arvind98', $doc);
    }
}
