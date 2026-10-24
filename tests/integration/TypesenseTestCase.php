<?php

namespace Ernestdefoe\Typesense\Tests\integration;

use Ernestdefoe\Typesense\Tests\fixtures\FakeConnection;
use Ernestdefoe\Typesense\Tests\fixtures\FakeTypesense;
use Ernestdefoe\Typesense\TypesenseConnection;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;

abstract class TypesenseTestCase extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected FakeTypesense $typesense;

    /** False to talk to the real HTTP client instead of the fake server. */
    protected bool $fake = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-typesense');

        $this->prepareDatabase([User::class => [$this->normalUser()]]);

        $this->typesense = new FakeTypesense();
    }

    /** Connection details entered, answered by the fake server. */
    protected function configured(): void
    {
        $this->setting('ernestdefoe-typesense.api_key', 'test-key');
        $this->setting('ernestdefoe-typesense.host', 'typesense.test');
    }

    /** Connection details entered for a server that refuses every connection. */
    protected function unreachable(): void
    {
        $this->fake = false;
        $this->setting('ernestdefoe-typesense.api_key', 'test-key');
        $this->setting('ernestdefoe-typesense.host', '127.0.0.1');
        $this->setting('ernestdefoe-typesense.port', '1');
    }

    /** Boot the forum with the fake server behind the real connection class. */
    protected function boot(): void
    {
        $container = $this->app()->getContainer();

        if ($this->fake) {
            $container->instance(TypesenseConnection::class, $container->make(FakeConnection::class, ['fakeServer' => $this->typesense]));
        }
    }

    /** @return array{0: int, 1: mixed} */
    protected function call(string $method, string $path, ?int $actor = null, ?array $json = null, array $query = []): array
    {
        $this->boot();

        $options = $actor ? ['authenticatedAs' => $actor] : [];
        if ($json !== null) {
            $options['json'] = $json;
        }

        $request = $this->request($method, $path, $options)->withQueryParams($query);

        if (! $actor && $method !== 'GET') {
            $session = $this->send($this->request('GET', '/'));
            $request = $this->request($method, $path, $options + ['cookiesFrom' => $session])
                ->withHeader('X-CSRF-Token', $session->getHeaderLine('X-CSRF-Token'));
        }

        $response = $this->send($request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }
}
