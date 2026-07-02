<?php

namespace Ernestdefoe\Typesense\Search\Discussion;

use Ernestdefoe\Typesense\TypesenseConnection;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Search\IndexerInterface;
use Psr\Log\LoggerInterface;

/**
 * Keeps the Typesense `discussions` collection in sync. A document holds the
 * title plus the concatenated text of the discussion's visible comment posts,
 * so fulltext hits either. Visibility is NOT baked into the index — it is
 * enforced at query time by the searcher — so the index simply mirrors every
 * non-hidden discussion.
 *
 * All methods no-op cleanly when Typesense isn't configured, so enabling the
 * extension before entering connection details never errors on every save.
 */
class DiscussionIndexer implements IndexerInterface
{
    protected const MAX_CONTENT = 100000;
    protected const BUILD_CHUNK = 500;

    public function __construct(
        protected TypesenseConnection $typesense,
        protected LoggerInterface $log
    ) {
    }

    public static function index(): string
    {
        return 'discussions';
    }

    /**
     * @param Discussion[] $models
     */
    public function save(array $models): void
    {
        if (! $this->typesense->configured() || empty($models)) {
            return;
        }

        $this->ensureCollection();
        $this->upsert($models);
    }

    /**
     * @param Discussion[] $models
     */
    public function delete(array $models): void
    {
        if (! $this->typesense->configured() || empty($models)) {
            return;
        }

        $ids = array_values(array_filter(array_map(fn ($d) => (int) $d->id, $models)));
        if (empty($ids)) {
            return;
        }

        try {
            $this->collection()->documents->delete([
                'filter_by' => 'id:[' . implode(',', $ids) . ']',
            ]);
        } catch (\Throwable $e) {
            $this->log->warning('[typesense] delete failed: ' . $e->getMessage());
        }
    }

    public function build(): void
    {
        if (! $this->typesense->configured()) {
            return;
        }

        $this->flush();
        $this->createCollection();

        Discussion::query()
            ->whereNull('hidden_at')
            ->orderBy('id')
            ->chunk(self::BUILD_CHUNK, function ($discussions) {
                $this->upsert($discussions->all());
            });
    }

    public function flush(): void
    {
        if (! $this->typesense->configured()) {
            return;
        }

        try {
            $this->collection()->delete();
        } catch (\Throwable $e) {
            // Collection didn't exist — nothing to flush.
        }
    }

    /**
     * @param Discussion[] $discussions
     */
    protected function upsert(array $discussions): void
    {
        $ids = array_values(array_filter(array_map(fn ($d) => (int) $d->id, $discussions)));
        $content = $this->contentFor($ids);

        $docs = [];
        foreach ($discussions as $d) {
            if ($d->hidden_at !== null) {
                continue;
            }
            $docs[] = [
                'id' => (string) $d->id,
                'title' => (string) $d->title,
                'content' => $content[(int) $d->id] ?? '',
                'comment_count' => (int) ($d->comment_count ?? 0),
                'created_at' => (int) ($d->created_at?->timestamp ?? 0),
                'last_posted_at' => (int) ($d->last_posted_at?->timestamp ?? 0),
                'is_private' => (bool) $d->is_private,
                'is_sticky' => (bool) ($d->is_sticky ?? false),
                'user_id' => (int) ($d->user_id ?? 0),
            ];
        }

        if (empty($docs)) {
            return;
        }

        try {
            $this->collection()->documents->import($docs, ['action' => 'upsert']);
        } catch (\Throwable $e) {
            $this->log->warning('[typesense] upsert failed: ' . $e->getMessage());
        }
    }

    /**
     * Concatenated, tag-stripped text of each discussion's visible comment
     * posts, capped per discussion to keep documents a sane size.
     *
     * @param int[] $discussionIds
     * @return array<int, string>
     */
    protected function contentFor(array $discussionIds): array
    {
        $map = [];
        if (empty($discussionIds)) {
            return $map;
        }

        Post::query()
            ->where('type', 'comment')
            ->whereNull('hidden_at')
            ->whereIn('discussion_id', $discussionIds)
            ->orderBy('discussion_id')
            ->orderBy('number')
            ->select('discussion_id', 'content')
            ->chunk(2000, function ($posts) use (&$map) {
                foreach ($posts as $p) {
                    $did = (int) $p->discussion_id;
                    $current = $map[$did] ?? '';
                    if (mb_strlen($current) >= self::MAX_CONTENT) {
                        continue;
                    }
                    $map[$did] = $current . ' ' . strip_tags((string) $p->content);
                }
            });

        foreach ($map as $did => $text) {
            $map[$did] = mb_substr(trim($text), 0, self::MAX_CONTENT);
        }

        return $map;
    }

    protected function collection()
    {
        return $this->typesense->client()->collections[$this->typesense->collectionName('discussions')];
    }

    protected function ensureCollection(): void
    {
        try {
            $this->collection()->retrieve();
        } catch (\Throwable $e) {
            $this->createCollection();
        }
    }

    protected function createCollection(): void
    {
        try {
            $this->typesense->client()->collections->create([
                'name' => $this->typesense->collectionName('discussions'),
                'fields' => [
                    ['name' => 'id', 'type' => 'string'],
                    ['name' => 'title', 'type' => 'string'],
                    ['name' => 'content', 'type' => 'string'],
                    ['name' => 'comment_count', 'type' => 'int32'],
                    ['name' => 'created_at', 'type' => 'int64'],
                    ['name' => 'last_posted_at', 'type' => 'int64', 'optional' => true],
                    ['name' => 'is_private', 'type' => 'bool'],
                    ['name' => 'is_sticky', 'type' => 'bool', 'optional' => true],
                    ['name' => 'user_id', 'type' => 'int64', 'optional' => true],
                ],
                'default_sorting_field' => 'created_at',
            ]);
        } catch (\Throwable $e) {
            // Already exists (race) or create failed — surfaced on next op.
        }
    }
}
