<?php

namespace Ernestdefoe\Typesense\Search\User;

use Flarum\User\Search\UserSearcher;

/**
 * Distinct searcher class so this driver gets its own fulltext-filter mapping.
 * Visibility, filters and pagination are inherited from core's UserSearcher.
 */
class TypesenseUserSearcher extends UserSearcher
{
}
