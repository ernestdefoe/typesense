<?php

namespace Ernestdefoe\Typesense\Provider;

use Ernestdefoe\Typesense\Search\Discussion\TypesenseDiscussionSearcher;
use Ernestdefoe\Typesense\TypesenseConnection;
use Flarum\Discussion\Search\DiscussionSearcher;
use Flarum\Foundation\AbstractServiceProvider;

/**
 * The Typesense discussion searcher is a distinct class (so it owns its own
 * fulltext-filter mapping without clobbering the database driver's). That means
 * filters and mutators registered against core's DiscussionSearcher — by core
 * itself and by extensions such as flarum/tags (`tag:` refinement) — wouldn't
 * reach it. Here we mirror them across at resolution time, so a Typesense
 * fulltext search honours exactly the same refinements as the database search.
 *
 * Note: this only affects result *refinement*. Permissions never depend on it —
 * the searcher's getQuery() applies whereVisibleTo regardless.
 */
class SearchProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(TypesenseConnection::class);

        $this->container->extend('flarum.search.filters', function (array $filters) {
            $parent = $filters[DiscussionSearcher::class] ?? [];
            $mine = $filters[TypesenseDiscussionSearcher::class] ?? [];

            $filters[TypesenseDiscussionSearcher::class] = array_values(
                array_unique(array_merge($mine, $parent))
            );

            return $filters;
        });

        $this->container->extend('flarum.search.mutators', function (array $mutators) {
            $parent = $mutators[DiscussionSearcher::class] ?? [];
            $mine = $mutators[TypesenseDiscussionSearcher::class] ?? [];

            $mutators[TypesenseDiscussionSearcher::class] = array_merge($mine, $parent);

            return $mutators;
        });
    }
}
