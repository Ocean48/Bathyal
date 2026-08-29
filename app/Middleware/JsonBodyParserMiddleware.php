<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class JsonBodyParserMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): mixed
    {
        $contentType = $request->header('content-type', '');
        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE']) && str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            if (!empty($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Response::error('Invalid JSON payload: ' . json_last_error_msg(), Response::HTTP_BAD_REQUEST);
                }
            }
        }

        return $next($request);
    }
}
