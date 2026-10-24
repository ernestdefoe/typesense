<?php

namespace Ernestdefoe\Typesense\Search;

use Ernestdefoe\Typesense\TypesenseConnection;
use Flarum\Search\IndexerInterface;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Builder;
use Psr\Log\LoggerInterface;
use Typesense\Exceptions\ObjectNotFound;

/**
 * Shared Typesense collection plumbing for the concrete resource indexers.
 * Subclasses declare their collection name (index()), schema, base query for a
 * full build, and how to turn a batch of models into documents. Everything
 * no-ops cleanly when Typesense isn't configured, so enabling the extension
 * before entering connection details never errors on a save.
 */
abstract class AbstractIndexer implements IndexerInterface
{
    protected const BUILD_CHUNK = 500;

    /**
     * How long a confirmed collection is trusted before it is checked again.
     */
    protected const EXISTS_TTL = 600;

    public function __construct(
        protected TypesenseConnection $typesense,
        protected LoggerInterface $log,
        protected Cache $cache
    ) {
    }

    /**
     * Field list + default_sorting_field for the collection.
     *
     * @return array{fields: array<array<string, mixed>>, default_sorting_field: string}
     */
    abstract protected function schema(): array;

    /**
     * @param object[] $models
     * @return array<array<string, mixed>>
     */
    abstract protected function documentsFor(array $models): array;

    abstract protected function baseQuery(): Builder;

    public function save(array $models): void
    {
        if (! $this->typesense->configured() || empty($models)) {
            return;
        }

        // 🚨 One failed call ends the attempt: on a server that is down, the
        // collection check, a create and the import each waited out the
        // timeout in turn, and each logged a warning on every save.
        try {
            $this->ensureCollection();
            $this->upsert($this->documentsFor($models));
        } catch (\Throwable $e) {
            // The collection may have gone since it was last confirmed; check
            // again on the next save rather than trusting the cache.
            $this->cache->forget($this->existsKey());
            $this->typesense->unreachable(static::index().' indexing', $e);
        }
    }

    public function delete(array $models): void
    {
        if (! $this->typesense->configured() || empty($models)) {
            return;
        }

        $ids = array_values(array_filter(array_map(fn ($m) => (int) $m->getKey(), $models)));
        if (empty($ids)) {
            return;
        }

        try {
            $this->collection()->documents->delete([
                'filter_by' => 'id:['.implode(',', $ids).']',
            ]);
        } catch (\Throwable $e) {
            $this->typesense->unreachable(static::index().' delete', $e);
        }
    }

    public function build(): void
    {
        if (! $this->typesense->configured()) {
            return;
        }

        $this->flush();
        $this->createCollection();

        $this->baseQuery()
            ->orderBy('id')
            ->chunk(self::BUILD_CHUNK, function ($models) {
                $this->import($this->documentsFor($models->all()));
            });
    }

    public function flush(): void
    {
        if (! $this->typesense->configured()) {
            return;
        }

        $this->cache->forget($this->existsKey());

        try {
            $this->collection()->delete();
        } catch (\Throwable $e) {
            // Collection didn't exist — nothing to flush.
        }
    }

    /**
     * @param array<array<string, mixed>> $docs
     */
    protected function import(array $docs): void
    {
        try {
            $this->upsert($docs);
        } catch (\Throwable $e) {
            $this->log->warning('[typesense] '.static::index().' upsert failed: '.$e->getMessage());
        }
    }

    /**
     * @param array<array<string, mixed>> $docs
     */
    protected function upsert(array $docs): void
    {
        if (! empty($docs)) {
            $this->collection()->documents->import($docs, ['action' => 'upsert']);
        }
    }

    protected function collection(): \Typesense\Collection
    {
        return $this->typesense->client()->collections[$this->typesense->collectionName(static::index())];
    }

    /**
     * 🚨 Remembered in the cache, not asked on every save. Every post, reply,
     * edit and profile change is indexed, and on a host with the sync queue
     * that runs inside the visitor's request — so a retrieve per save doubled
     * the round trips to Typesense on every write, for an answer that almost
     * never changes. flush() forgets it, so a rebuild still re-creates.
     */
    protected function ensureCollection(): void
    {
        if ($this->cache->get($this->existsKey())) {
            return;
        }

        try {
            $this->collection()->retrieve();
            $this->cache->put($this->existsKey(), true, self::EXISTS_TTL);
        } catch (ObjectNotFound) {
            $this->createCollection();
        }
    }

    protected function existsKey(): string
    {
        return 'ernestdefoe-typesense.exists.'.$this->typesense->collectionName(static::index());
    }

    protected function createCollection(): void
    {
        try {
            $this->typesense->client()->collections->create(array_merge(
                ['name' => $this->typesense->collectionName(static::index())],
                $this->schema()
            ));
        } catch (\Throwable $e) {
            // Already exists (race) or create failed — surfaced on next op.
        }
    }
}
