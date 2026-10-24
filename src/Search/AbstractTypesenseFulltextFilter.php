<?php

namespace Ernestdefoe\Typesense\Search;

use Ernestdefoe\Typesense\TypesenseConnection;
use Flarum\Search\AbstractFulltextFilter;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\SearchState;

/**
 * Executes the fulltext part of a search against Typesense: asks for matching
 * IDs (ranked), constrains the Eloquent query to those IDs, and sets relevance
 * as the default sort. Permissions never depend on this — the searcher's
 * getQuery() has already applied whereVisibleTo — so Typesense only narrows and
 * orders candidates. When Typesense is not configured or does not answer, the
 * search falls back to core's database fulltext filter for that request, so a
 * search server outage means slower results, never none.
 */
abstract class AbstractTypesenseFulltextFilter extends AbstractFulltextFilter
{
    public function __construct(
        protected TypesenseConnection $typesense
    ) {
    }

    abstract protected function index(): string;

    /**
     * Core's database fulltext filter for this resource, used when Typesense
     * cannot answer.
     *
     * @return class-string<AbstractFulltextFilter>
     */
    abstract protected function databaseFilter(): string;

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

        if ($ids === null) {
            resolve($this->databaseFilter())->search($state, $value);

            return;
        }

        if (empty($ids)) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereIn($this->idColumn(), $ids);

        // Preserve Typesense relevance as the default sort (applied only when no
        // explicit sort was requested). Portable CASE ordering — MySQL/PG/SQLite.
        // 🚨 The column goes through the grammar: raw SQL skips the table
        // prefix, and a bare `discussions.id` broke every prefixed forum.
        $column = $this->idColumn();
        $state->setDefaultSort(function ($q) use ($column, $ids) {
            $sql = 'CASE '.$q->getQuery()->getGrammar()->wrap($column);
            foreach (array_keys($ids) as $position) {
                $sql .= ' WHEN ? THEN '.(int) $position;
            }
            $q->orderByRaw($sql.' END', $ids);
        });
    }

    /**
     * @return int[]|null IDs in relevance order, or null when Typesense could not be asked
     */
    protected function matchingIds(string $value): ?array
    {
        if (! $this->typesense->configured()) {
            return null;
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
            $this->typesense->unreachable($this->index().' search', $e);

            return null;
        }
    }
}
