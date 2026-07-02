<?php

namespace Ernestdefoe\Typesense\Console;

use Ernestdefoe\Typesense\Search\Discussion\DiscussionIndexer;
use Ernestdefoe\Typesense\Search\Post\PostIndexer;
use Ernestdefoe\Typesense\Search\User\UserIndexer;
use Ernestdefoe\Typesense\TypesenseConnection;
use Flarum\Console\AbstractCommand;
use Flarum\Search\IndexerInterface;
use Symfony\Component\Console\Input\InputOption;

class IndexCommand extends AbstractCommand
{
    public function __construct(
        protected TypesenseConnection $typesense,
        protected DiscussionIndexer $discussions,
        protected UserIndexer $users,
        protected PostIndexer $posts
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('typesense:index')
            ->setDescription('Rebuild (or flush) the Typesense search indexes: discussions, users and posts.')
            ->addOption('flush', null, InputOption::VALUE_NONE, 'Delete the indexes instead of rebuilding them.');
    }

    protected function fire(): int
    {
        if (! $this->typesense->configured()) {
            $this->error('Typesense is not configured. Set the host, port, protocol and API key under Admin → Typesense Search.');

            return 1;
        }

        /** @var array<string, IndexerInterface> $indexers */
        $indexers = [
            'discussions' => $this->discussions,
            'users' => $this->users,
            'posts' => $this->posts,
        ];

        $flush = (bool) $this->input->getOption('flush');

        foreach ($indexers as $name => $indexer) {
            if ($flush) {
                $this->info("Flushing $name…");
                $indexer->flush();
            } else {
                $this->info("Rebuilding $name — this can take a while on large forums…");
                $indexer->build();
            }
        }

        $this->info('Done.' . ($flush ? '' : ' Enable the Typesense driver per resource under Admin → Typesense Search.'));

        return 0;
    }
}
