<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

class Logger
{
    private static string $logDir = '';

    public static function setLogDir(string $dir): void
    {
        self::$logDir = $dir;
    }

    public static function getLogDir(): string
    {
        if (self::$logDir === '') {
            self::$logDir = dirname(__DIR__, 2) . '/storage/logs';
        }
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
        return self::$logDir;
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        $logFile = self::getLogDir() . '/app-' . date('Y-m-d') . '.log';
        $entry = [
            'timestamp' => date('c'),
            'level'     => strtoupper($level),
            'message'   => $message,
            'user_id'   => $context['user_id'] ?? Auth::id(),
            'ip'        => $context['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'context'   => $context,
        ];

        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function exception(Throwable $e, string $action = '', array $context = []): void
    {
        $context['action'] = $action;
        $context['exception'] = get_class($e);
        $context['file'] = $e->getFile() . ':' . $e->getLine();
        $context['trace'] = $e->getTraceAsString();

        self::log('CRITICAL', $e->getMessage(), $context);
    }
}
