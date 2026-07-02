<?php

namespace Ernestdefoe\Typesense\Search\Discussion;

use Ernestdefoe\Typesense\TypesenseConnection;
use Flarum\Search\AbstractFulltextFilter;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\SearchState;
use Psr\Log\LoggerInterface;

/**
 * Executes the fulltext part of a discussion search against Typesense. It asks
 * Typesense for the matching discussion IDs (ranked), constrains the Eloquent
 * query to those IDs, and sets relevance as the default sort. Everything about
 * *who may see what* stays with the Eloquent query (getQuery already applied
 * whereVisibleTo) — Typesense only narrows candidates and orders them.
 *
 * On any Typesense error the search degrades to "no matches" rather than
 * throwing, so a search box never 500s because the search server blipped.
 */
class FulltextFilter extends AbstractFulltextFilter
{
    public function __construct(
        protected TypesenseConnection $typesense,
        protected LoggerInterface $log
    ) {
    }

    public function search(SearchState $state, string $value): void
    {
        /** @var DatabaseSearchState $state */
        $query = $state->getQuery();

        $ids = $this->matchingIds($value);

        if (empty($ids)) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereIn('discussions.id', $ids);

        // Preserve Typesense's relevance ranking as the default sort (applied
        // only when the caller didn't request an explicit sort). Portable
        // CASE ordering — works on MySQL, PostgreSQL and SQLite alike.
        $cases = [];
        $bindings = [];
        foreach (array_values($ids) as $position => $id) {
            $cases[] = 'WHEN ? THEN ' . $position;
            $bindings[] = $id;
        }
        $orderSql = 'CASE discussions.id ' . implode(' ', $cases) . ' END';

        $state->setDefaultSort(function ($q) use ($orderSql, $bindings) {
            $q->orderByRaw($orderSql, $bindings);
        });
    }

    /**
     * @return int[] discussion IDs in relevance order (empty on miss/error)
     */
    protected function matchingIds(string $value): array
    {
        if (! $this->typesense->configured()) {
            return [];
        }

        try {
            $collection = $this->typesense->collectionName('discussions');
            $response = $this->typesense->client()
                ->collections[$collection]
                ->documents
                ->search([
                    'q' => $value,
                    'query_by' => 'title,content',
                    'query_by_weights' => '3,1',
                    'per_page' => 250,
                    'include_fields' => 'id',
                    'prioritize_exact_match' => true,
                ]);

            return array_values(array_filter(array_map(
                fn ($hit) => (int) ($hit['document']['id'] ?? 0),
                $response['hits'] ?? []
            )));
        } catch (\Throwable $e) {
            $this->log->error('[typesense] discussion search failed: ' . $e->getMessage());

            return [];
        }
    }
}
