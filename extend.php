<?php

use Ernestdefoe\Typesense\Api\Controller\RebuildController;
use Ernestdefoe\Typesense\Api\Controller\TestConnectionController;
use Ernestdefoe\Typesense\Console\IndexCommand;
use Ernestdefoe\Typesense\Provider\SearchProvider;
use Ernestdefoe\Typesense\Search\Discussion\DiscussionIndexer;
use Ernestdefoe\Typesense\Search\Discussion\FulltextFilter;
use Ernestdefoe\Typesense\Search\Discussion\PostIndexer;
use Ernestdefoe\Typesense\Search\Discussion\TypesenseDiscussionSearcher;
use Ernestdefoe\Typesense\Search\TypesenseSearchDriver;
use Flarum\Discussion\Discussion;
use Flarum\Extend;
use Flarum\Post\Post;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js'),

    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\SearchDriver(TypesenseSearchDriver::class))
        ->addSearcher(Discussion::class, TypesenseDiscussionSearcher::class)
        ->setFulltext(TypesenseDiscussionSearcher::class, FulltextFilter::class),

    (new Extend\SearchIndex())
        ->indexer(Discussion::class, DiscussionIndexer::class)
        ->indexer(Post::class, PostIndexer::class),

    new Extend\ServiceProvider(SearchProvider::class),

    (new Extend\Console())
        ->command(IndexCommand::class),

    (new Extend\Routes('api'))
        ->get('/typesense/status', 'ernestdefoe-typesense.status', TestConnectionController::class)
        ->post('/typesense/rebuild', 'ernestdefoe-typesense.rebuild', RebuildController::class),
];
