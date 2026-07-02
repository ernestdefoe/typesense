<?php

namespace Ernestdefoe\Typesense\Console;

use Ernestdefoe\Typesense\Search\Discussion\DiscussionIndexer;
use Ernestdefoe\Typesense\TypesenseConnection;
use Flarum\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputOption;

class IndexCommand extends AbstractCommand
{
    public function __construct(
        protected TypesenseConnection $typesense,
        protected DiscussionIndexer $indexer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('typesense:index')
            ->setDescription('Rebuild (or flush) the Typesense search index.')
            ->addOption('flush', null, InputOption::VALUE_NONE, 'Delete the index instead of rebuilding it.');
    }

    protected function fire(): int
    {
        if (! $this->typesense->configured()) {
            $this->error('Typesense is not configured. Set the host, port, protocol and API key under Admin → Typesense Search.');

            return 1;
        }

        if ($this->input->getOption('flush')) {
            $this->info('Flushing the Typesense index…');
            $this->indexer->flush();
            $this->info('Done.');

            return 0;
        }

        $this->info('Rebuilding the Typesense index — this can take a while on large forums…');
        $this->indexer->build();
        $this->info('Done. Set Discussions to use the Typesense driver under Admin → Typesense Search if you haven\'t already.');

        return 0;
    }
}
