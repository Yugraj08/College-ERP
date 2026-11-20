<?php
declare(strict_types=1);

namespace App\Core;

class AuthServer
{
    private const CSRF_KEY = '_mkr_csrf_token';
    private const FLASH_KEY = '_mkr_flash_messages';

    public static function csrfToken(): string
    {
        Auth::startSession();

        if (empty($_SESSION[self::CSRF_KEY]) || !is_string($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::CSRF_KEY];
    }

    public static function verifyCsrf(Request $request): bool
    {
        Auth::startSession();

        $sessionToken = $_SESSION[self::CSRF_KEY] ?? null;
        if (!is_string($sessionToken) || empty($sessionToken)) {
            return false;
        }

        $submittedToken = $request->input('_csrf')
            ?? $request->input('csrf_token')
            ?? $request->header('x-csrf-token')
            ?? $request->header('x-xsrf-token');

        if (!is_string($submittedToken)) {
            return false;
        }

        return hash_equals($sessionToken, $submittedToken);
    }

    public static function setFlash(string $type, string $message): void
    {
        Auth::startSession();
        if (!isset($_SESSION[self::FLASH_KEY]) || !is_array($_SESSION[self::FLASH_KEY])) {
            $_SESSION[self::FLASH_KEY] = [];
        }
        $_SESSION[self::FLASH_KEY][$type] = $message;
    }

    public static function getFlash(string $type): ?string
    {
        Auth::startSession();
        if (isset($_SESSION[self::FLASH_KEY][$type])) {
            $msg = $_SESSION[self::FLASH_KEY][$type];
            unset($_SESSION[self::FLASH_KEY][$type]);
            return (string)$msg;
        }
        return null;
    }

    public static function hasFlash(string $type): bool
    {
        Auth::startSession();
        return isset($_SESSION[self::FLASH_KEY][$type]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getSessionUser(): ?array
    {
        return Auth::user();
    }

    public static function setSecureCookie(string $name, string $value, int $expirySeconds = 86400, string $path = '/'): bool
    {
        if (headers_sent()) {
            return false;
        }

        $secure = Auth::isHttps();

        return setcookie($name, $value, [
            'expires'  => time() + $expirySeconds,
            'path'     => $path,
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
