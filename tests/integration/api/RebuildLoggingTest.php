<?php

namespace Ernestdefoe\Typesense\Tests\integration\api;

use Ernestdefoe\Typesense\Search\Discussion\DiscussionIndexer;
use Ernestdefoe\Typesense\Tests\integration\TypesenseTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/** A rebuild that Typesense rejects says so in the log; a normal one stays quiet. */
class RebuildLoggingTest extends TypesenseTestCase
{
    /** @var string[] */
    private array $logged = [];

    private function rebuild(): void
    {
        $container = $this->app()->getContainer();
        $logged = &$this->logged;
        $container->instance(LoggerInterface::class, new class($logged) extends AbstractLogger {
            public function __construct(private array &$logged)
            {
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->logged[] = (string) $message;
            }
        });

        $this->boot();
        $container->make(DiscussionIndexer::class)->build();
    }

    #[Test]
    public function a_first_build_with_no_collection_yet_logs_nothing()
    {
        $this->configured();
        $this->typesense->statuses = ['DELETE #^/collections/[^/]+$#' => 404];

        $this->rebuild();

        $this->assertSame([], $this->logged);
        $this->assertNotEmpty($this->typesense->requestsTo('#^/collections$#'), 'It created the collection');
    }

    #[Test]
    public function a_collection_created_meanwhile_logs_nothing()
    {
        $this->configured();
        $this->typesense->statuses = ['POST #^/collections$#' => 409];

        $this->rebuild();

        $this->assertSame([], $this->logged);
    }

    #[Test]
    public function a_rejected_api_key_is_logged()
    {
        $this->configured();
        $this->typesense->statuses = ['DELETE #^/collections/[^/]+$#' => 401, 'POST #^/collections$#' => 401];

        $this->rebuild();

        $this->assertCount(1, $this->logged, 'Once per outage, not once per call');
        $this->assertStringContainsString('discussions flush failed', $this->logged[0]);
    }

    #[Test]
    public function a_rejected_schema_is_logged()
    {
        $this->configured();
        $this->typesense->statuses = ['POST #^/collections$#' => 400];

        $this->rebuild();

        $this->assertCount(1, $this->logged);
        $this->assertStringContainsString('discussions create failed', $this->logged[0]);
    }
}
