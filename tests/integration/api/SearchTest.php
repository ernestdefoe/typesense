<?php

namespace Ernestdefoe\Typesense\Tests\integration\api;

use Carbon\Carbon;
use Ernestdefoe\Typesense\Tests\integration\TypesenseTestCase;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use PHPUnit\Framework\Attributes\Test;

/**
 * Searching through Typesense: it only narrows and orders candidates, and
 * Flarum still decides what the searcher may see.
 *
 * Discussions 1-3; 2 is hidden.
 */
class SearchTest extends TypesenseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $discussions = $posts = [];
        foreach ([1, 2, 3] as $id) {
            $discussions[] = ['id' => $id, 'title' => "Discussion $id", 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now()->addMinutes($id), 'user_id' => 2, 'first_post_id' => $id, 'comment_count' => 1, 'hidden_at' => $id === 2 ? Carbon::now() : null];
            $posts[] = ['id' => $id, 'discussion_id' => $id, 'number' => 1, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Text '.$id.'</p></t>'];
        }

        $this->prepareDatabase([Discussion::class => $discussions, Post::class => $posts]);
    }

    private function useTypesenseFor(string $model): void
    {
        $this->setting("search_driver_$model", 'typesense');
    }

    private function search(string $q, ?int $actor = null): array
    {
        [$status, $body] = $this->call('GET', '/api/discussions', $actor, null, ['filter' => ['q' => $q]]);

        $this->assertSame(200, $status, json_encode($body));

        return array_map('intval', array_column($body['data'], 'id'));
    }

    #[Test]
    public function the_forum_is_told_which_searches_typesense_answers()
    {
        $this->useTypesenseFor(Discussion::class);
        $this->useTypesenseFor(Post::class);

        [, $body] = $this->call('GET', '/api');

        $this->assertSame(['discussions', 'posts'], $body['data']['attributes']['typesenseSearch']);
    }

    #[Test]
    public function only_typesenses_matches_come_back_and_only_where_the_searcher_may_look()
    {
        $this->configured();
        $this->useTypesenseFor(Discussion::class);
        $this->typesense->hits['localhost_discussions'] = [2, 1];

        $this->assertSame([1], $this->search('discussion'), 'Not 3, which did not match, nor the hidden 2');

        $query = $this->typesense->requestsTo('#/documents/search$#')[0]['query'];
        $this->assertSame('discussion', $query['q']);
        $this->assertSame('title,content', $query['query_by']);
    }

    #[Test]
    public function results_come_in_typesenses_order()
    {
        $this->configured();
        $this->useTypesenseFor(Discussion::class);
        // Discussion 3 is the newest, so the list's own order would put it first.
        $this->typesense->hits['localhost_discussions'] = [1, 3];

        $this->assertSame([1, 3], $this->search('discussion'));
    }

    #[Test]
    public function a_server_that_is_down_means_no_results_not_an_error()
    {
        $this->configured();
        $this->useTypesenseFor(Discussion::class);
        $this->typesense->down = true;

        $this->assertSame([], $this->search('discussion'));
    }
}
