<?php

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;

class AIController extends BaseController
{
    private function proxyFastAPI(string $path, array $payload = []): void
    {
        $aiBaseUrl = Config::get('ai.url', 'http://ai:8000');
        $url = rtrim($aiBaseUrl, '/') . '/' . ltrim($path, '/');

        $options = [
            'http' => [
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($payload),
                'timeout' => 5.0,
                'ignore_errors' => true,
            ],
        ];

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            $this->error('AI microservice is currently unreachable', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $statusCode = 200;
        if (isset($http_response_header[0]) && preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match)) {
            $statusCode = (int)$match[1];
        }

        $decoded = json_decode($result, true);
        if ($statusCode >= 400) {
            $this->error($decoded['detail'] ?? 'AI service error', $statusCode);
        }

        $this->json($decoded);
    }

    public function estimateTask(Request $request): void
    {
        $payload = $request->getJson();
        $this->validate($payload, [
            'title' => 'required',
        ]);

        $this->proxyFastAPI('/api/v1/ai/estimate-task', $payload);
    }

    public function optimizeSchedule(Request $request): void
    {
        $payload = $request->getJson();
        $this->validate($payload, [
            'tasks' => 'required',
        ]);

        $this->proxyFastAPI('/api/v1/ai/optimize-schedule', $payload);
    }

    public function decomposeTask(Request $request): void
    {
        $payload = $request->getJson();
        $this->validate($payload, [
            'title' => 'required',
        ]);

        $this->proxyFastAPI('/api/v1/ai/decompose-task', $payload);
    }

    public function parsePrompt(Request $request): void
    {
        $payload = $request->getJson();
        $this->validate($payload, [
            'prompt' => 'required',
        ]);

        $this->proxyFastAPI('/api/v1/ai/parse-prompt', $payload);
    }

    public function recommendDependencies(Request $request): void
    {
        $payload = $request->getJson();
        $this->validate($payload, [
            'tasks' => 'required',
        ]);

        $this->proxyFastAPI('/api/v1/ai/recommend-dependencies', $payload);
    }
}
