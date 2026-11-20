<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

class Auth
{
    private const SESSION_KEY = 'mkr_auth_user';
    public const TOKEN_COOKIE_NAME = 'mkr_erp_token';

    private static ?array $currentUser = null;

    public static function hashPassword(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $plainPassword, string $hash): bool
    {
        return password_verify($plainPassword, $hash);
    }

    public static function isHttps(): bool
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' && $_SERVER['HTTPS'] !== '') {
            return true;
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
            return true;
        }
        return false;
    }

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = self::isHttps();

        session_set_cookie_params([
            'lifetime' => 86400 * 7,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function login(array $user, bool $remember = false): void
    {
        self::startSession();
        session_regenerate_id(true);

        unset($user['password_hash']);

        $_SESSION[self::SESSION_KEY] = $user;
        self::$currentUser = $user;

        $token = self::createToken($user, $remember ? (86400 * 30) : 86400);

        if (!headers_sent()) {
            $secure = self::isHttps();
            setcookie(self::TOKEN_COOKIE_NAME, $token, [
                'expires'  => $remember ? (time() + 86400 * 30) : 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION[self::SESSION_KEY] = null;
        unset($_SESSION[self::SESSION_KEY]);
        $_SESSION = [];

        self::$currentUser = null;
        unset($_COOKIE[self::TOKEN_COOKIE_NAME], $_COOKIE['mkr_session_token']);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        if (!headers_sent()) {
            setcookie(self::TOKEN_COOKIE_NAME, '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        if (self::$currentUser !== null) {
            return self::$currentUser;
        }

        self::startSession();

        if (!empty($_SESSION[self::SESSION_KEY]) && is_array($_SESSION[self::SESSION_KEY])) {
            self::$currentUser = $_SESSION[self::SESSION_KEY];
            return self::$currentUser;
        }

        $cookieToken = $_COOKIE[self::TOKEN_COOKIE_NAME] ?? $_COOKIE['mkr_session_token'] ?? null;
        if ($cookieToken) {
            $payload = self::verifyToken($cookieToken);
            if ($payload !== null) {
                self::$currentUser = $payload;
                $_SESSION[self::SESSION_KEY] = $payload;
                return self::$currentUser;
            }
        }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            $bearerToken = trim(substr($authHeader, 7));
            $payload = self::verifyToken($bearerToken);
            if ($payload !== null) {
                self::$currentUser = $payload;
                return self::$currentUser;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public static function setUser(?array $user): void
    {
        self::$currentUser = $user;
    }

    public static function id(): ?string
    {
        $user = self::user();
        return isset($user['id']) ? (string)$user['id'] : null;
    }

    public static function role(): ?string
    {
        $user = self::user();
        return isset($user['role']) ? (string)$user['role'] : null;
    }

    /**
     * @param string|array<string> $roles
     */
    public static function hasRole(string|array $roles): bool
    {
        $userRole = self::role();
        if ($userRole === null) {
            return false;
        }

        $allowed = is_array($roles) ? $roles : [$roles];
        return in_array($userRole, $allowed, true);
    }

    /**
     * @param string|array<string> $allowedRoles
     * @return array<string, mixed>
     * @throws RuntimeException
     */
    public static function requireRole(string|array $allowedRoles): array
    {
        $user = self::user();
        if ($user === null) {
            throw new RuntimeException("Unauthenticated access. Please log in.", 401);
        }

        $allowed = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
        $currentRole = $user['role'] ?? null;

        if (!in_array($currentRole, $allowed, true)) {
            throw new RuntimeException("Access Denied: Role '{$currentRole}' lacks sufficient privileges.", 403);
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function createToken(array $payload, int $expirySeconds = 86400): string
    {
        $secret = self::getAppSecret();

        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $claims = array_merge($payload, [
            'iat' => time(),
            'exp' => time() + $expirySeconds,
        ]);

        $base64Header = self::base64UrlEncode((string)json_encode($header));
        $base64Claims = self::base64UrlEncode((string)json_encode($claims));

        $signature = hash_hmac('sha256', "{$base64Header}.{$base64Claims}", $secret, true);
        $base64Signature = self::base64UrlEncode($signature);

        return "{$base64Header}.{$base64Claims}.{$base64Signature}";
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $claimsB64, $sigB64] = $parts;

        $secret = self::getAppSecret();
        $expectedSignature = hash_hmac('sha256', "{$headerB64}.{$claimsB64}", $secret, true);

        if (!hash_equals(self::base64UrlEncode($expectedSignature), $sigB64)) {
            return null;
        }

        $claimsJson = self::base64UrlDecode($claimsB64);
        $claims = json_decode($claimsJson, true);

        if (!is_array($claims)) {
            return null;
        }

        if (isset($claims['exp']) && $claims['exp'] < time()) {
            return null;
        }

        return $claims;
    }

    private static function getAppSecret(): string
    {
        return $_ENV['APP_SECRET'] ?? getenv('APP_SECRET') ?: 'mkr_college_erp_default_fallback_secret_key_32_chars';
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
