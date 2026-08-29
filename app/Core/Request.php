<?php

namespace App\Core;

class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $queryParams;
    private array $bodyParams;
    private array $headers;
    private array $routeParams = [];
    private ?array $user = null;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->path = parse_url($this->uri, PHP_URL_PATH) ?: '/';
        $this->queryParams = $_GET;
        $this->headers = $this->parseHeaders();
        $this->bodyParams = $this->parseBody();
    }

    public static function createFromGlobals(): self
    {
        return new self();
    }

    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$headerName] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'AUTHORIZATION'])) {
                $headerName = strtolower(str_replace('_', '-', $key));
                $headers[$headerName] = $value;
            }
        }
        return $headers;
    }

    private function parseBody(): array
    {
        if (in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $contentType = $this->header('content-type', '');
            if (str_contains($contentType, 'application/json')) {
                $rawInput = file_get_contents('php://input');
                if (!empty($rawInput)) {
                    $json = json_decode($rawInput, true);
                    if (is_array($json)) {
                        return $json;
                    }
                }
            }
            return $_POST;
        }
        return [];
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        $key = strtolower($key);
        return $this->headers[$key] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization');
        if ($auth && preg_match('/Bearer\s+(\S+)/i', $auth, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->bodyParams)) {
            return $this->bodyParams[$key];
        }
        if (array_key_exists($key, $this->queryParams)) {
            return $this->queryParams[$key];
        }
        return $this->routeParams[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->queryParams, $this->routeParams, $this->bodyParams);
    }

    public function getString(string $key, string $default = ''): string
    {
        $val = $this->get($key, $default);
        return is_scalar($val) ? trim((string)$val) : $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $val = $this->get($key, $default);
        return is_numeric($val) ? (int)$val : $default;
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        $val = $this->get($key, $default);
        return is_numeric($val) ? (float)$val : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $val = $this->get($key, $default);
        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    public function getArray(string $key, array $default = []): array
    {
        $val = $this->get($key, $default);
        return is_array($val) ? $val : $default;
    }

    public function getJson(): array
    {
        return $this->bodyParams;
    }

    public function setUser(?array $user): void
    {
        $this->user = $user;
    }

    public function user(): ?array
    {
        return $this->user;
    }

    public function userId(): ?int
    {
        return isset($this->user['id']) ? (int)$this->user['id'] : null;
    }
}
