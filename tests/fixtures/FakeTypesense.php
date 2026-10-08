<?php

namespace Ernestdefoe\Typesense\Tests\fixtures;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A stand-in Typesense server: answers the SDK's HTTP calls in-process and
 * records them, so tests see exactly what the extension asks for.
 */
class FakeTypesense implements ClientInterface
{
    /** @var array<int, array{method: string, path: string, query: array<string, mixed>, body: string}> */
    public array $requests = [];

    /** @var array<string, int[]> ids a search of each collection returns, in rank order */
    public array $hits = [];

    /** Answer every call with a 503, as a server that is down would. */
    public bool $down = false;

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        parse_str($request->getUri()->getQuery(), $query);

        $this->requests[] = [
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'query' => $query,
            'body' => (string) $request->getBody(),
        ];

        if ($this->down) {
            return $this->json(503, ['message' => 'Not Ready or Lagging']);
        }

        $path = $request->getUri()->getPath();

        if ($path === '/health') {
            return $this->json(200, ['ok' => true]);
        }

        if (preg_match('#^/collections/([^/]+)/documents/search$#', $path, $m)) {
            $hits = array_map(fn (int $id) => ['document' => ['id' => (string) $id]], $this->hits[$m[1]] ?? []);

            return $this->json(200, ['found' => count($hits), 'hits' => $hits]);
        }

        if (preg_match('#^/collections/[^/]+/documents/import$#', $path)) {
            $lines = array_filter(explode("\n", (string) $request->getBody()));

            return new Response(200, [], implode("\n", array_map(fn () => '{"success":true}', $lines)));
        }

        return $this->json(200, []);
    }

    /** @return array<int, array{method: string, path: string, query: array<string, mixed>, body: string}> */
    public function requestsTo(string $pathPattern): array
    {
        return array_values(array_filter($this->requests, fn ($r) => preg_match($pathPattern, $r['path'])));
    }

    private function json(int $status, array $body): ResponseInterface
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }
}
