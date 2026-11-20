<?php
declare(strict_types=1);

if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

require_once dirname(__DIR__) . '/src/autoload.php';

use App\Controllers\AdminController;
use App\Controllers\AdmissionController;
use App\Controllers\AuthController;
use App\Controllers\ContactController;
use App\Controllers\FacultyController;
use App\Controllers\GrievanceController;
use App\Controllers\HomeController;
use App\Controllers\StudentController;
use App\Core\Auth;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

try {
    $request = Request::capture();
    $router = new Router();

    // Home Route
    $router->get('/', [HomeController::class, 'index']);

    // Centralized Authentication Routes
    $router->get('/login', [AuthController::class, 'showLoginForm']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->get('/logout', [AuthController::class, 'logout']);
    $router->post('/logout', [AuthController::class, 'logout']);
    $router->post('/api/auth/login', [AuthController::class, 'login']);
    $router->post('/api/auth/logout', [AuthController::class, 'logout']);
    $router->get('/api/auth/me', [AuthController::class, 'me']);
    $router->post('/api/auth/forgot-password', [AuthController::class, 'forgotPassword']);

    // Institutional Management Portals (Phase 8 Production Endpoints)
    // 1. Super Admin & Principal Governance Suite
    $router->get('/admin/principal', [AdminController::class, 'principal']);
    $router->post('/api/admin/admissions/scrutinize', [AdminController::class, 'scrutinizeAdmission']);
    $router->post('/api/admin/windows/save', [AdminController::class, 'saveRegistrationWindow']);
    $router->post('/api/admin/faculty/create', [AdminController::class, 'createFaculty']);
    $router->post('/api/admin/faculty/toggle-status', [AdminController::class, 'toggleFacultyStatus']);
    $router->post('/api/admin/faculty/reset-password', [AdminController::class, 'resetFacultyPassword']);
    $router->post('/api/admin/notices/broadcast', [AdminController::class, 'broadcastNotice']);
    $router->post('/api/admin/notices/delete', [AdminController::class, 'deleteNotice']);
    $router->post('/api/admin/grievances/resolve', [AdminController::class, 'resolveGrievance']);

    // 2. Student Directory & Search Registry
    $router->get('/admin/students', [AdminController::class, 'students']);
    $router->get('/api/admin/students', [AdminController::class, 'apiSearchStudents']);
    $router->get('/api/admin/students/export', [AdminController::class, 'exportStudentsCsv']);

    // 3. Faculty Academic Portal (Attendance & BRABU 30-Mark CIA Register)
    $router->get('/faculty', [FacultyController::class, 'index']);
    $router->get('/api/faculty/students', [FacultyController::class, 'getStudentsForSubject']);
    $router->post('/api/faculty/attendance/save', [FacultyController::class, 'saveAttendance']);
    $router->post('/api/faculty/cia/save', [FacultyController::class, 'saveCia']);

    // 4. Student Academic & Progression Portal
    $router->get('/student', [StudentController::class, 'index']);
    $router->post('/api/student/pay-progression', [StudentController::class, 'payProgression']);
    $router->get('/api/student/receipt', [StudentController::class, 'getReceipt']);

    // Admission & CBCS Progression Routes
    $router->get('/admission', [AdmissionController::class, 'index']);
    $router->post('/api/admissions/apply', [AdmissionController::class, 'applyFresh']);
    $router->post('/api/admissions/status', [AdmissionController::class, 'statusLookup']);
    $router->post('/api/admissions/progression', [AdmissionController::class, 'progressionRegister']);

    // Grievance Redressal Routes
    $router->get('/complaint', [GrievanceController::class, 'index']);
    $router->post('/api/grievances/submit', [GrievanceController::class, 'submit']);
    $router->post('/api/grievances/track', [GrievanceController::class, 'track']);

    // Public Contact & Inquiry Routes
    $router->get('/contact', [ContactController::class, 'index']);
    $router->post('/api/contact/submit', [ContactController::class, 'submit']);

    $response = $router->dispatch($request);
    $response->send();
} catch (Throwable $e) {
    Logger::exception($e, 'http_dispatch', [
        'uri'    => $_SERVER['REQUEST_URI'] ?? '/',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    ]);

    if (!headers_sent()) {
        http_response_code($e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500);
        header('Content-Type: text/html; charset=utf-8');
    }

    $title = $e->getCode() === 401 ? 'Authentication Required' : 'Service Temporarily Unavailable';
    $message = $e->getCode() === 401 ? 'Please log in to access this protected institutional portal.' : 'An internal error occurred. Our technical administration team has been notified.';
    $actionLink = $e->getCode() === 401 ? '/login' : '/';
    $actionText = $e->getCode() === 401 ? 'Go to Login Portal' : 'Return to Home';

    echo '<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>' . htmlspecialchars($title) . ' - MKR College ERP</title></head>
<body style="font-family: sans-serif; text-align: center; padding: 80px 20px; background-color: #f8fafc;">
    <h1 style="color: #800000; font-size: 36px; margin-bottom: 8px;">' . htmlspecialchars($title) . '</h1>
    <p style="color: #475569; font-size: 16px; max-width: 500px; margin: 0 auto 24px;">' . htmlspecialchars($message) . '</p>
    <a href="' . htmlspecialchars($actionLink) . '" style="display: inline-block; background-color: #800000; color: #ffffff; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: bold;">' . htmlspecialchars($actionText) . '</a>
</body>
</html>';
}
