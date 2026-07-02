<?php

namespace Ernestdefoe\Typesense\Search\Post;

use Ernestdefoe\Typesense\Search\AbstractIndexer;
use Flarum\Post\Post;
use Illuminate\Database\Eloquent\Builder;

/**
 * Indexes individual (non-hidden) comment posts into a `posts` collection for
 * post-scoped search. Distinct from the discussion index, which folds post text
 * into the parent discussion's document for discussion search.
 */
class PostIndexer extends AbstractIndexer
{
    protected const MAX_CONTENT = 100000;

    public static function index(): string
    {
        return 'posts';
    }

    protected function schema(): array
    {
        return [
            'fields' => [
                ['name' => 'id', 'type' => 'string'],
                ['name' => 'content', 'type' => 'string'],
                ['name' => 'discussion_id', 'type' => 'int64'],
                ['name' => 'user_id', 'type' => 'int64', 'optional' => true],
                ['name' => 'number', 'type' => 'int32', 'optional' => true],
                ['name' => 'created_at', 'type' => 'int64'],
            ],
            'default_sorting_field' => 'created_at',
        ];
    }

    protected function baseQuery(): Builder
    {
        return Post::query()->where('type', 'comment')->whereNull('hidden_at');
    }

    /**
     * @param Post[] $models
     */
    protected function documentsFor(array $models): array
    {
        $docs = [];
        foreach ($models as $p) {
            if ($p->type !== 'comment' || $p->hidden_at !== null) {
                continue;
            }
            $docs[] = [
                'id' => (string) $p->id,
                'content' => mb_substr(strip_tags((string) $p->content), 0, self::MAX_CONTENT),
                'discussion_id' => (int) $p->discussion_id,
                'user_id' => (int) ($p->user_id ?? 0),
                'number' => (int) ($p->number ?? 0),
                'created_at' => (int) ($p->created_at?->timestamp ?? 0),
            ];
        }

        return $docs;
    }
}
