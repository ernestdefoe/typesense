<?php

namespace Ernestdefoe\Typesense\Provider;

use Ernestdefoe\Typesense\Search\Discussion\TypesenseDiscussionSearcher;
use Ernestdefoe\Typesense\Search\Post\TypesensePostSearcher;
use Ernestdefoe\Typesense\Search\User\TypesenseUserSearcher;
use Ernestdefoe\Typesense\TypesenseConnection;
use Flarum\Discussion\Search\DiscussionSearcher;
use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Post\Filter\PostSearcher;
use Flarum\User\Search\UserSearcher;

/**
 * Each Typesense searcher is a distinct class (so it owns its own fulltext
 * mapping without clobbering the database driver's). That means filters and
 * mutators registered against core's searchers — by core and by extensions such
 * as flarum/tags (`tag:` refinement) — wouldn't reach them. Here we mirror them
 * across at resolution time, so a Typesense fulltext search honours exactly the
 * same refinements as the database search.
 *
 * This only affects result *refinement*; permissions never depend on it — every
 * searcher's getQuery() applies whereVisibleTo regardless.
 */
class SearchProvider extends AbstractServiceProvider
{
    /**
     * Typesense searcher => the core searcher whose filters/mutators it mirrors.
     */
    protected const MIRROR = [
        TypesenseDiscussionSearcher::class => DiscussionSearcher::class,
        TypesenseUserSearcher::class => UserSearcher::class,
        TypesensePostSearcher::class => PostSearcher::class,
    ];

    public function register(): void
    {
        $this->container->singleton(TypesenseConnection::class);

        $this->container->extend('flarum.search.filters', function (array $filters) {
            foreach (self::MIRROR as $mine => $parent) {
                $filters[$mine] = array_values(array_unique(array_merge(
                    $filters[$mine] ?? [],
                    $filters[$parent] ?? []
                )));
            }

            return $filters;
        });

        $this->container->extend('flarum.search.mutators', function (array $mutators) {
            foreach (self::MIRROR as $mine => $parent) {
                $mutators[$mine] = array_merge(
                    $mutators[$mine] ?? [],
                    $mutators[$parent] ?? []
                );
            }

            return $mutators;
        });
    }
}
