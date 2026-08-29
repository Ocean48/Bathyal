<?php

namespace App\Core;

class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $queryParams;
    private array $bodyParams;
    private array $files;
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
        $this->files = $this->parseFiles();
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

    private function parseFiles(): array
    {
        $normalized = [];
        if (empty($_FILES)) {
            return $normalized;
        }

        foreach ($_FILES as $key => $fileInfo) {
            if (is_array($fileInfo['name'])) {
                $count = count($fileInfo['name']);
                for ($i = 0; $i < $count; $i++) {
                    if (empty($fileInfo['name'][$i]) || $fileInfo['error'][$i] === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    $normalized[$key][] = [
                        'name' => $fileInfo['name'][$i],
                        'type' => $fileInfo['type'][$i] ?? 'application/octet-stream',
                        'tmp_name' => $fileInfo['tmp_name'][$i],
                        'error' => $fileInfo['error'][$i],
                        'size' => $fileInfo['size'][$i],
                        'extension' => strtolower(pathinfo($fileInfo['name'][$i], PATHINFO_EXTENSION)),
                    ];
                }
            } else {
                if (empty($fileInfo['name']) || $fileInfo['error'] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $normalized[$key] = [
                    'name' => $fileInfo['name'],
                    'type' => $fileInfo['type'] ?? 'application/octet-stream',
                    'tmp_name' => $fileInfo['tmp_name'],
                    'error' => $fileInfo['error'],
                    'size' => $fileInfo['size'],
                    'extension' => strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION)),
                ];
            }
        }

        return $normalized;
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
        $queryToken = $this->get('token') ?: $this->get('auth_token');
        if (!empty($queryToken) && is_scalar($queryToken)) {
            return trim((string)$queryToken);
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

    public function hasFile(string $key): bool
    {
        return !empty($this->files[$key]);
    }

    public function file(string $key): ?array
    {
        if (!isset($this->files[$key])) {
            return null;
        }
        $f = $this->files[$key];
        if (is_array($f) && isset($f[0]) && is_array($f[0])) {
            return $f[0];
        }
        return is_array($f) && isset($f['name']) ? $f : null;
    }

    public function files(?string $key = null): array
    {
        if ($key === null) {
            return $this->files;
        }
        if (!isset($this->files[$key])) {
            return [];
        }
        $f = $this->files[$key];
        if (is_array($f) && isset($f[0]) && is_array($f[0])) {
            return $f;
        }
        if (is_array($f) && isset($f['name'])) {
            return [$f];
        }
        return [];
    }

    public function setFiles(array $files): void
    {
        $this->files = $files;
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
