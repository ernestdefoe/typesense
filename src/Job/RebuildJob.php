<?php

namespace Ernestdefoe\Typesense\Job;

use Ernestdefoe\Typesense\Search\Discussion\DiscussionIndexer;
use Ernestdefoe\Typesense\Search\Post\PostIndexer;
use Ernestdefoe\Typesense\Search\User\UserIndexer;
use Flarum\Queue\AbstractJob;
use Illuminate\Contracts\Container\Container;

/**
 * Full reindex of every collection (discussions, users, posts), run off the
 * request thread (§50). Dispatched by the admin "Rebuild index" button. On the
 * default `sync` queue this still runs inline — the README recommends a real
 * queue worker so a rebuild of a large forum doesn't tie up a web request.
 */
class RebuildJob extends AbstractJob
{
    public int $tries = 1;
    public int $timeout = 3600;

    public function handle(Container $container): void
    {
        foreach ([DiscussionIndexer::class, UserIndexer::class, PostIndexer::class] as $indexer) {
            $container->make($indexer)->build();
        }
    }
}
