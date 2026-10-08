<?php

namespace Ernestdefoe\Typesense\Tests\integration\api;

use Ernestdefoe\Typesense\Tests\integration\TypesenseTestCase;
use PHPUnit\Framework\Attributes\Test;

/** The admin page's two buttons: test the connection, rebuild the index. */
class AdminEndpointsTest extends TypesenseTestCase
{
    #[Test]
    public function both_are_refused_to_everyone_but_an_admin()
    {
        $this->configured();

        foreach ([['GET', '/api/typesense/status'], ['POST', '/api/typesense/rebuild']] as [$method, $path]) {
            $this->assertSame(403, $this->call($method, $path)[0], "Guest: $path");
            $this->assertSame(403, $this->call($method, $path, 2)[0], "Member: $path");
        }

        $this->assertSame([], $this->typesense->requestsTo('#^/health$|^/collections$#'), 'Neither a health check nor a rebuild reached Typesense');
    }

    #[Test]
    public function the_status_says_when_nothing_is_configured()
    {
        [$status, $body] = $this->call('GET', '/api/typesense/status', 1);

        $this->assertSame(200, $status);
        $this->assertSame(['ok' => false, 'error' => 'not_configured'], $body);
    }

    #[Test]
    public function the_status_asks_the_server()
    {
        $this->configured();

        [$status, $body] = $this->call('GET', '/api/typesense/status', 1);

        $this->assertSame(200, $status);
        $this->assertSame(['ok' => true], $body);
        $this->assertCount(1, $this->typesense->requestsTo('#^/health$#'));
    }

    #[Test]
    public function the_status_reports_a_server_that_is_down_without_failing()
    {
        $this->configured();
        $this->typesense->down = true;

        [$status, $body] = $this->call('GET', '/api/typesense/status', 1);

        $this->assertSame(200, $status);
        $this->assertFalse($body['ok']);
    }

    #[Test]
    public function a_rebuild_needs_connection_details()
    {
        [$status, $body] = $this->call('POST', '/api/typesense/rebuild', 1);

        $this->assertSame(422, $status);
        $this->assertSame('not_configured', $body['error']);
    }

    #[Test]
    public function a_rebuild_recreates_every_collection()
    {
        $this->configured();

        [$status] = $this->call('POST', '/api/typesense/rebuild', 1);

        $this->assertSame(202, $status);

        // The test queue runs the job at once: each collection is dropped and created again.
        $created = array_map(fn ($r) => json_decode($r['body'], true)['name'], $this->typesense->requestsTo('#^/collections$#'));
        $this->assertEqualsCanonicalizing(['localhost_discussions', 'localhost_users', 'localhost_posts'], $created);
    }
}
