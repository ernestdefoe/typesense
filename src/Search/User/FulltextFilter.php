<?php

namespace Ernestdefoe\Typesense\Search\User;

use Ernestdefoe\Typesense\Search\AbstractTypesenseFulltextFilter;

class FulltextFilter extends AbstractTypesenseFulltextFilter
{
    protected function databaseFilter(): string
    {
        return \Flarum\User\Search\FulltextFilter::class;
    }

    protected function index(): string
    {
        return 'users';
    }

    protected function queryBy(): string
    {
        return 'username,display_name';
    }

    protected function idColumn(): string
    {
        return 'users.id';
    }
}
