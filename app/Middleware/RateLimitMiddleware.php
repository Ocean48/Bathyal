<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class RateLimitMiddleware implements MiddlewareInterface
{
    private static array $requests = [];
    private int $maxRequests;
    private int $windowSeconds;

    public function __construct(int $maxRequests = 60, int $windowSeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }

    public function handle(Request $request, callable $next): mixed
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $path = $request->getPath();
        $key = md5("{$ip}_{$path}");
        $now = time();

        if (!isset(self::$requests[$key])) {
            self::$requests[$key] = [];
        }

        // Clean expired timestamps outside sliding window
        self::$requests[$key] = array_filter(
            self::$requests[$key],
            fn($timestamp) => ($now - $timestamp) < $this->windowSeconds
        );

        if (count(self::$requests[$key]) >= $this->maxRequests) {
            header('Retry-After: ' . $this->windowSeconds);
            Response::error('Too many requests. Rate limit exceeded.', 429, [
                'limit' => $this->maxRequests,
                'window_seconds' => $this->windowSeconds,
            ]);
        }

        self::$requests[$key][] = $now;

        return $next($request);
    }
}
