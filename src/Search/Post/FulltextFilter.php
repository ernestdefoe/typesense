<?php

namespace Ernestdefoe\Typesense\Search\Post;

use Ernestdefoe\Typesense\Search\AbstractTypesenseFulltextFilter;

class FulltextFilter extends AbstractTypesenseFulltextFilter
{
    protected function index(): string
    {
        return 'posts';
    }

    protected function queryBy(): string
    {
        return 'content';
    }

    protected function idColumn(): string
    {
        return 'posts.id';
    }
}
