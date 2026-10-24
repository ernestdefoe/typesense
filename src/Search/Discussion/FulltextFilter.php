<?php

namespace Ernestdefoe\Typesense\Search\Discussion;

use Ernestdefoe\Typesense\Search\AbstractTypesenseFulltextFilter;

class FulltextFilter extends AbstractTypesenseFulltextFilter
{
    protected function databaseFilter(): string
    {
        return \Flarum\Discussion\Search\FulltextFilter::class;
    }

    protected function index(): string
    {
        return 'discussions';
    }

    protected function queryBy(): string
    {
        return 'title,content';
    }

    protected function queryByWeights(): ?string
    {
        return '3,1';
    }

    protected function idColumn(): string
    {
        return 'discussions.id';
    }
}
