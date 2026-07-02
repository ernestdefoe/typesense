<?php

namespace Ernestdefoe\Typesense\Search;

use Flarum\Search\AbstractDriver;

/**
 * The Typesense search driver. Selected per-resource via the
 * `search_driver_<ModelClass>` setting; core's SearchManager only routes
 * fulltext (text-query) searches here — plain filter/browse stays on the
 * database driver — and the searcher's getQuery() still enforces visibility,
 * so Typesense never decides permissions.
 */
class TypesenseSearchDriver extends AbstractDriver
{
    public static function name(): string
    {
        return 'typesense';
    }
}
