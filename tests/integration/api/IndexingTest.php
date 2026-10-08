<?php

namespace Ernestdefoe\Typesense\Tests\integration\api;

use Ernestdefoe\Typesense\Tests\integration\TypesenseTestCase;
use PHPUnit\Framework\Attributes\Test;

/** Writes reach the index, and never fail because of it. */
class IndexingTest extends TypesenseTestCase
{
    private function startDiscussion(): array
    {
        return $this->call('POST', '/api/discussions', 2, ['data' => [
            'type' => 'discussions',
            'attributes' => ['title' => 'Fresh topic', 'content' => 'A brand new first post'],
        ]]);
    }

    #[Test]
    public function a_new_discussion_and_its_post_are_indexed()
    {
        $this->configured();

        [$status, $body] = $this->startDiscussion();
        $this->assertSame(201, $status, json_encode($body));

        $imports = $this->typesense->requestsTo('#^/collections/localhost_(discussions|posts)/documents/import$#');
        $this->assertNotEmpty($imports);

        $documents = implode("\n", array_column($imports, 'body'));
        $this->assertStringContainsString('"title":"Fresh topic"', $documents);
        $this->assertStringContainsString('A brand new first post', $documents);
    }

    #[Test]
    public function a_server_that_is_down_never_blocks_a_post()
    {
        $this->configured();
        $this->typesense->down = true;

        $this->assertSame(201, $this->startDiscussion()[0]);
        $this->assertNotEmpty($this->typesense->requests, 'It tried');
    }

    #[Test]
    public function posting_works_before_typesense_is_configured()
    {
        $this->assertSame(201, $this->startDiscussion()[0]);
        $this->assertSame([], $this->typesense->requests);
    }
}
