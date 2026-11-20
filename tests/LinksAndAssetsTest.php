<?php
declare(strict_types=1);

namespace App\Tests;

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Core\Request;
use App\Core\Response;
use PHPUnit\Framework\TestCase;

class LinksAndAssetsTest extends TestCase
{
    public function testStaticAssetsExistOnDisk(): void
    {
        $base = dirname(__DIR__);

        $requiredAssets = [
            'public/assets/logo/mkr-college-emblem.webp',
            'public/assets/logo/mkr-dr-grd-college-logo.webp',
            'public/assets/erp-panel/image-1.png',
            'public/assets/erp-panel/image-2.webp',
            'public/js/vendor/alpine.min.js',
            'public/js/app.js',
            'public/css/app.css',
        ];

        foreach ($requiredAssets as $relPath) {
            $fullPath = $base . '/' . $relPath;
            $this->assertFileExists($fullPath, "Required asset missing: {$relPath}");
            $this->assertGreaterThan(50, filesize($fullPath), "Asset file is empty: {$relPath}");
        }
    }

    public function testHomeViewRendering(): void
    {
        $controller = new HomeController();
        $request = new Request('GET', '/');
        $response = $controller->index($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $html = $response->getContent();
        $this->assertStringContainsString('Matashri Kaushalya Ramdev Dr. Ganesh Ray Degree College', $html);
        $this->assertStringContainsString('Online Admission Form', $html);
        $this->assertStringContainsString('Grievance Redressal', $html);
        $this->assertStringContainsString('ERP Login', $html);
        $this->assertStringContainsString('alpine.min.js', $html);
        $this->assertStringContainsString('mkr-college-emblem.webp', $html);
    }

    public function testLoginViewRenderingAndAssetIntegrity(): void
    {
        $controller = new AuthController();
        $request = new Request('GET', '/login');
        $response = $controller->showLoginForm($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $html = $response->getContent();
        $this->assertStringContainsString('ERP Portal Login', $html);
        $this->assertStringContainsString('/assets/erp-panel/image-1.png', $html);
        $this->assertStringContainsString('/assets/erp-panel/image-2.webp', $html);
        $this->assertStringContainsString('/assets/logo/mkr-college-emblem.webp', $html);
        $this->assertStringContainsString('/js/vendor/alpine.min.js', $html);
        $this->assertStringContainsString('x-data', $html);
        $this->assertStringContainsString('SUPER_ADMIN', $html);
        $this->assertStringContainsString('FACULTY', $html);
        $this->assertStringContainsString('STUDENT', $html);
        $this->assertStringContainsString('+91-7597052444', $html);
    }
}
