<?php
declare(strict_types=1);

namespace App\Core;

class Request
{
    private string $method;
    private string $path;
    private array $query;
    private array $post;
    private array $json;
    private array $files;
    private array $server;
    private array $cookies;
    private array $headers;
    private array $params = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $json
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     * @param array<string, mixed> $cookies
     * @param array<string, string> $headers
     */
    public function __construct(
        string $method = 'GET',
        string $path = '/',
        array $query = [],
        array $post = [],
        array $json = [],
        array $files = [],
        array $server = [],
        array $cookies = [],
        array $headers = []
    ) {
        $this->method = strtoupper($method);
        $parsedPath = parse_url($path, PHP_URL_PATH);
        $cleanPath = '/' . ltrim(is_string($parsedPath) ? $parsedPath : '/', '/');
        $this->path = ($cleanPath !== '/' && str_ends_with($cleanPath, '/')) ? rtrim($cleanPath, '/') : $cleanPath;
        $this->query = $query;
        $this->post = $post;
        $this->json = $json;
        $this->files = $files;
        $this->server = $server;
        $this->cookies = $cookies;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        foreach ($server as $key => $val) {
            if (is_string($key) && str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                if (!isset($this->headers[$headerName])) {
                    $this->headers[$headerName] = (string)$val;
                }
            }
        }
    }

    public static function capture(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir = dirname($scriptName);
        if ($scriptDir !== '/' && $scriptDir !== '\\' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
        }
        $path = '/' . ltrim($path, '/');

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $json = [];
        if (stripos($contentType, 'application/json') !== false) {
            $rawBody = file_get_contents('php://input');
            if (!empty($rawBody)) {
                $decoded = json_decode($rawBody, true);
                if (is_array($decoded)) {
                    $json = $decoded;
                }
            }
        }

        $headers = [];
        if (function_exists('getallheaders')) {
            $allHeaders = getallheaders();
            if (is_array($allHeaders)) {
                $headers = $allHeaders;
            }
        } else {
            foreach ($_SERVER as $key => $value) {
                if (str_starts_with($key, 'HTTP_')) {
                    $headerName = str_replace('_', '-', strtolower(substr($key, 5)));
                    $headers[$headerName] = (string)$value;
                } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                    $headerName = str_replace('_', '-', strtolower($key));
                    $headers[$headerName] = (string)$value;
                }
            }
        }

        return new self(
            $method,
            $path,
            $_GET,
            $_POST,
            $json,
            $_FILES,
            $_SERVER,
            $_COOKIE,
            $headers
        );
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function isPost(): bool
    {
        return $this->isMethod('POST');
    }

    public function isGet(): bool
    {
        return $this->isMethod('GET');
    }

    public function isAjax(): bool
    {
        return strtolower($this->header('x-requested-with', '')) === 'xmlhttprequest' ||
               str_contains($this->header('accept', ''), 'application/json');
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function json(string $key, mixed $default = null): mixed
    {
        return $this->json[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->json)) {
            return $this->json[$key];
        }
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }
        if (array_key_exists($key, $this->query)) {
            return $this->query[$key];
        }
        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->json);
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) &&
               is_array($this->files[$key]) &&
               ($this->files[$key]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    public function cookie(string $key, ?string $default = null): ?string
    {
        return $this->cookies[$key] ?? $default;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function param(string $key, ?string $default = null): ?string
    {
        return $this->params[$key] ?? $default;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getIp(): string
    {
        return $this->server['HTTP_CF_CONNECTING_IP']
            ?? $this->server['HTTP_X_FORWARDED_FOR']
            ?? $this->server['REMOTE_ADDR']
            ?? '127.0.0.1';
    }

    public function ip(): string
    {
        return $this->getIp();
    }

    public function getUserAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? 'Unknown';
    }

    public function userAgent(): string
    {
        return $this->getUserAgent();
    }

    public function wantsJson(): bool
    {
        $accept = (string)$this->header('accept', '');
        return str_contains($accept, '/json') || str_contains($accept, '+json');
    }
}
