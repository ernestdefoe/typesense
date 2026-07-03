<?php

use Ernestdefoe\Typesense\Api\Controller\RebuildController;
use Ernestdefoe\Typesense\Api\Controller\TestConnectionController;
use Ernestdefoe\Typesense\Console\IndexCommand;
use Ernestdefoe\Typesense\Provider\SearchProvider;
use Ernestdefoe\Typesense\Search\Discussion\DiscussionIndexer;
use Ernestdefoe\Typesense\Search\Discussion\FulltextFilter as DiscussionFulltextFilter;
use Ernestdefoe\Typesense\Search\Discussion\PostReindexer;
use Ernestdefoe\Typesense\Search\Discussion\TypesenseDiscussionSearcher;
use Ernestdefoe\Typesense\Search\Post\FulltextFilter as PostFulltextFilter;
use Ernestdefoe\Typesense\Search\Post\PostIndexer;
use Ernestdefoe\Typesense\Search\Post\TypesensePostSearcher;
use Ernestdefoe\Typesense\Search\TypesenseSearchDriver;
use Ernestdefoe\Typesense\Search\User\FulltextFilter as UserFulltextFilter;
use Ernestdefoe\Typesense\Search\User\TypesenseUserSearcher;
use Ernestdefoe\Typesense\Search\User\UserIndexer;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Schema\Attribute;
use Flarum\Discussion\Discussion;
use Flarum\Extend;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js'),

    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less'),

    /*
     * Tells the frontend which search tabs are answered by Typesense so the
     * search modal can show a "Powered by Typesense" badge. Booleans only —
     * the connection details and API key never leave the server.
     */
    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            Attribute::make('typesenseSearch')->get(function () {
                $settings = resolve(SettingsRepositoryInterface::class);

                $resources = [
                    'discussions' => Discussion::class,
                    'users' => User::class,
                    'posts' => Post::class,
                ];

                return array_keys(array_filter(
                    $resources,
                    fn (string $model) => $settings->get("search_driver_$model") === TypesenseSearchDriver::name()
                ));
            }),
        ]),

    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\SearchDriver(TypesenseSearchDriver::class))
        ->addSearcher(Discussion::class, TypesenseDiscussionSearcher::class)
        ->setFulltext(TypesenseDiscussionSearcher::class, DiscussionFulltextFilter::class)
        ->addSearcher(User::class, TypesenseUserSearcher::class)
        ->setFulltext(TypesenseUserSearcher::class, UserFulltextFilter::class)
        ->addSearcher(Post::class, TypesensePostSearcher::class)
        ->setFulltext(TypesensePostSearcher::class, PostFulltextFilter::class),

    (new Extend\SearchIndex())
        ->indexer(Discussion::class, DiscussionIndexer::class)
        ->indexer(Post::class, PostReindexer::class)
        ->indexer(Post::class, PostIndexer::class)
        ->indexer(User::class, UserIndexer::class),

    new Extend\ServiceProvider(SearchProvider::class),

    (new Extend\Console())
        ->command(IndexCommand::class),

    (new Extend\Routes('api'))
        ->get('/typesense/status', 'ernestdefoe-typesense.status', TestConnectionController::class)
        ->post('/typesense/rebuild', 'ernestdefoe-typesense.rebuild', RebuildController::class),
];
