<?php

namespace Ernestdefoe\Typesense\Search\Post;

use Ernestdefoe\Typesense\Search\AbstractTypesenseFulltextFilter;

class FulltextFilter extends AbstractTypesenseFulltextFilter
{
    protected function databaseFilter(): string
    {
        return \Flarum\Post\Filter\FulltextFilter::class;
    }

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
