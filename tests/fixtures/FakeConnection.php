<?php

namespace Ernestdefoe\Typesense\Tests\fixtures;

use Ernestdefoe\Typesense\TypesenseConnection;
use Flarum\Foundation\Config;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Cache\Repository as Cache;
use Psr\Log\LoggerInterface;
use Typesense\Client;

/** The real connection, configured from settings as usual, talking to a FakeTypesense. */
class FakeConnection extends TypesenseConnection
{
    private ?Client $fake = null;

    public function __construct(SettingsRepositoryInterface $settings, Config $config, Cache $cache, LoggerInterface $log, public FakeTypesense $fakeServer)
    {
        parent::__construct($settings, $config, $cache, $log);
    }

    public function client(): Client
    {
        return $this->fake ??= new Client([
            'api_key' => $this->apiKey(),
            'nodes' => [['host' => $this->host(), 'port' => $this->port(), 'protocol' => $this->protocol()]],
            'num_retries' => 0,
            'client' => $this->fakeServer,
        ]);
    }
}
