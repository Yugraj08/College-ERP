<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

class Response
{
    private int $statusCode;
    private array $headers;
    private string $content;

    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        echo $this->content;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public static function json(array $data, int $statusCode = 200, array $headers = []): self
    {
        $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            $encoded = '{"success":false,"error":"JSON encoding failed"}';
            $statusCode = 500;
        }

        $headers['Content-Type'] = 'application/json; charset=utf-8';
        return new self($encoded, $statusCode, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function html(string $html, int $statusCode = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'text/html; charset=utf-8';
        return new self($html, $statusCode, $headers);
    }

    public static function redirect(string $url, int $statusCode = 302): self
    {
        return new self('', $statusCode, ['Location' => $url]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function view(string $viewPath, array $data = [], ?string $layout = 'layouts/main'): self
    {
        $baseDir = dirname(__DIR__, 2) . '/views/';

        $resolvedView = $baseDir . ltrim($viewPath, '/');
        if (!str_ends_with($resolvedView, '.php')) {
            $resolvedView .= '.php';
        }

        if (!file_exists($resolvedView)) {
            $nested = $baseDir . trim($viewPath, '/') . '/index.php';
            if (file_exists($nested)) {
                $resolvedView = $nested;
            } else {
                throw new RuntimeException("View template not found: {$viewPath}");
            }
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $resolvedView;
        $content = ob_get_clean();

        if ($layout !== null) {
            $resolvedLayout = $baseDir . ltrim($layout, '/');
            if (!str_ends_with($resolvedLayout, '.php')) {
                $resolvedLayout .= '.php';
            }

            if (file_exists($resolvedLayout)) {
                $viewData = array_merge($data, ['content' => $content]);
                extract($viewData, EXTR_SKIP);
                ob_start();
                require $resolvedLayout;
                $content = ob_get_clean();
            }
        }

        return self::html($content ?: '', 200);
    }
}
