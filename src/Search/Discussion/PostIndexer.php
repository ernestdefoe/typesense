<?php

namespace Ernestdefoe\Typesense\Search\Discussion;

use Ernestdefoe\Typesense\TypesenseConnection;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Search\IndexerInterface;

/**
 * Posts aren't searched directly, but their text is part of the parent
 * discussion's document — so any post create/edit/delete re-indexes the
 * discussion(s) it belongs to. build()/flush() are owned by DiscussionIndexer;
 * here they're deliberate no-ops so a full rebuild isn't run twice.
 */
class PostIndexer implements IndexerInterface
{
    public function __construct(
        protected TypesenseConnection $typesense,
        protected DiscussionIndexer $discussions
    ) {
    }

    public static function index(): string
    {
        return 'discussions';
    }

    /**
     * @param Post[] $models
     */
    public function save(array $models): void
    {
        $this->reindexParents($models);
    }

    /**
     * @param Post[] $models
     */
    public function delete(array $models): void
    {
        $this->reindexParents($models);
    }

    public function build(): void
    {
    }

    public function flush(): void
    {
    }

    /**
     * @param Post[] $posts
     */
    protected function reindexParents(array $posts): void
    {
        if (! $this->typesense->configured() || empty($posts)) {
            return;
        }

        $ids = array_values(array_unique(array_filter(array_map(
            fn ($p) => (int) $p->discussion_id,
            $posts
        ))));
        if (empty($ids)) {
            return;
        }

        $discussions = Discussion::query()->whereIn('id', $ids)->get()->all();
        if (! empty($discussions)) {
            $this->discussions->save($discussions);
        }
    }
}
