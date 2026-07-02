<?php

namespace Ernestdefoe\Typesense\Search\Post;

use Flarum\Post\Filter\PostSearcher;

/**
 * Distinct searcher class so this driver gets its own fulltext-filter mapping.
 * Post visibility (which cascades discussion + tag visibility), filters and
 * pagination are inherited from core's PostSearcher.
 */
class TypesensePostSearcher extends PostSearcher
{
}
