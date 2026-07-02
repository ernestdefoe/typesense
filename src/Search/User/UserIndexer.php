<?php

namespace Ernestdefoe\Typesense\Search\User;

use Ernestdefoe\Typesense\Search\AbstractIndexer;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Indexes every user by username and display name for fast, typo-tolerant
 * user lookup. Visibility is enforced at query time by the searcher.
 */
class UserIndexer extends AbstractIndexer
{
    public static function index(): string
    {
        return 'users';
    }

    protected function schema(): array
    {
        return [
            'fields' => [
                ['name' => 'id', 'type' => 'string'],
                ['name' => 'username', 'type' => 'string'],
                ['name' => 'display_name', 'type' => 'string'],
                ['name' => 'joined_at', 'type' => 'int64'],
            ],
            'default_sorting_field' => 'joined_at',
        ];
    }

    protected function baseQuery(): Builder
    {
        return User::query();
    }

    /**
     * @param User[] $models
     */
    protected function documentsFor(array $models): array
    {
        $docs = [];
        foreach ($models as $u) {
            $docs[] = [
                'id' => (string) $u->id,
                'username' => (string) $u->username,
                'display_name' => (string) ($u->display_name ?? $u->username),
                'joined_at' => (int) ($u->joined_at?->timestamp ?? 0),
            ];
        }

        return $docs;
    }
}
