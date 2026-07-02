<?php

namespace Ernestdefoe\Typesense\Search;

use Ernestdefoe\Typesense\TypesenseConnection;
use Flarum\Search\AbstractFulltextFilter;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\SearchState;
use Psr\Log\LoggerInterface;

/**
 * Executes the fulltext part of a search against Typesense: asks for matching
 * IDs (ranked), constrains the Eloquent query to those IDs, and sets relevance
 * as the default sort. Permissions never depend on this — the searcher's
 * getQuery() has already applied whereVisibleTo — so Typesense only narrows and
 * orders candidates. On any error the search degrades to "no matches" instead
 * of throwing, so a search box never 500s because the search server blipped.
 */
abstract class AbstractTypesenseFulltextFilter extends AbstractFulltextFilter
{
    public function __construct(
        protected TypesenseConnection $typesense,
        protected LoggerInterface $log
    ) {
    }

    abstract protected function index(): string;

    abstract protected function queryBy(): string;

    abstract protected function idColumn(): string;

    protected function queryByWeights(): ?string
    {
        return null;
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

        $query->whereIn($this->idColumn(), $ids);

        // Preserve Typesense relevance as the default sort (applied only when no
        // explicit sort was requested). Portable CASE ordering — MySQL/PG/SQLite.
        $cases = [];
        $bindings = [];
        foreach (array_values($ids) as $position => $id) {
            $cases[] = 'WHEN ? THEN ' . $position;
            $bindings[] = $id;
        }
        $orderSql = 'CASE ' . $this->idColumn() . ' ' . implode(' ', $cases) . ' END';

        $state->setDefaultSort(function ($q) use ($orderSql, $bindings) {
            $q->orderByRaw($orderSql, $bindings);
        });
    }

    /**
     * @return int[] IDs in relevance order (empty on miss/error)
     */
    protected function matchingIds(string $value): array
    {
        if (! $this->typesense->configured()) {
            return [];
        }

        try {
            $params = [
                'q' => $value,
                'query_by' => $this->queryBy(),
                'per_page' => 250,
                'include_fields' => 'id',
                'prioritize_exact_match' => true,
            ];
            if ($this->queryByWeights() !== null) {
                $params['query_by_weights'] = $this->queryByWeights();
            }

            $response = $this->typesense->client()
                ->collections[$this->typesense->collectionName($this->index())]
                ->documents
                ->search($params);

            return array_values(array_filter(array_map(
                fn ($hit) => (int) ($hit['document']['id'] ?? 0),
                $response['hits'] ?? []
            )));
        } catch (\Throwable $e) {
            $this->log->error('[typesense] ' . $this->index() . ' search failed: ' . $e->getMessage());

            return [];
        }
    }
}
