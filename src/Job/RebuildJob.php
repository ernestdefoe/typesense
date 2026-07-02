<?php

namespace Ernestdefoe\Typesense\Job;

use Ernestdefoe\Typesense\Search\Discussion\DiscussionIndexer;
use Flarum\Queue\AbstractJob;
use Illuminate\Contracts\Container\Container;

/**
 * Full reindex, run off the request thread (§50). Dispatched by the admin
 * "Rebuild index" button. On the default `sync` queue this still runs inline —
 * document in the README that a real queue worker is recommended so a rebuild
 * of a large forum doesn't tie up a web request.
 */
class RebuildJob extends AbstractJob
{
    public int $tries = 1;
    public int $timeout = 3600;

    public function handle(Container $container): void
    {
        $container->make(DiscussionIndexer::class)->build();
    }
}
