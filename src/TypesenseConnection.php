<?php

namespace Ernestdefoe\Typesense;

use Flarum\Foundation\Config;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Cache\Repository as Cache;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\HttplugClient;
use Typesense\Client;

/**
 * Thin wrapper over the Typesense PHP SDK. Everything that touches the SDK
 * goes through here so the rest of the extension is insulated from SDK API
 * changes (CLAUDE.md §55) and so connection config is read from the settings
 * table in exactly one place (never env — §52).
 *
 * The API key is a secret: it is read here server-side only and is never
 * exposed through serializeToForum (§21).
 */
class TypesenseConnection
{
    /**
     * How long one outage is reported as one log line.
     */
    protected const DOWN_TTL = 60;

    private ?Client $client = null;

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected Config $config,
        protected Cache $cache,
        protected LoggerInterface $log
    ) {
    }

    public function host(): string
    {
        return trim((string) $this->settings->get('ernestdefoe-typesense.host', '127.0.0.1')) ?: '127.0.0.1';
    }

    public function port(): string
    {
        return trim((string) $this->settings->get('ernestdefoe-typesense.port', '8108')) ?: '8108';
    }

    public function protocol(): string
    {
        $p = trim((string) $this->settings->get('ernestdefoe-typesense.protocol', 'http'));

        return $p === 'https' ? 'https' : 'http';
    }

    public function apiKey(): string
    {
        return trim((string) $this->settings->get('ernestdefoe-typesense.api_key', ''));
    }

    /**
     * Collection prefix keeps installs isolated when several forums share one
     * Typesense instance. Falls back to a slug of the forum's host so two
     * forums never clobber each other's index out of the box.
     *
     * 🚨 The host comes from config.php: Flarum 2 has no `forum_url` setting,
     * and reading one gave every forum the same "flarum_" prefix. Installs
     * that were already indexed under it keep it — a migration stores
     * "flarum" as their prefix, so their collections are not orphaned.
     */
    public function prefix(): string
    {
        $prefix = trim((string) $this->settings->get('ernestdefoe-typesense.collection_prefix', ''));
        if ($prefix === '') {
            $prefix = $this->config->url()->getHost() ?: 'flarum';
        }

        return preg_replace('/[^A-Za-z0-9_]/', '_', $prefix).'_';
    }

    public function collectionName(string $index): string
    {
        return $this->prefix().$index;
    }

    public function configured(): bool
    {
        return $this->apiKey() !== '' && $this->host() !== '';
    }

    public function client(): Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        // 🚨 The SDK has no timeout of its own (its old `connection_timeout_seconds`
        // is ignored), so the HTTP client carries it. Without one, a server
        // that stopped answering held every search for PHP's 60 s socket
        // timeout. A visitor's request waits at most 2 s; the CLI (rebuilds,
        // queue workers) gives a big import time to be processed.
        return $this->client = new Client([
            'api_key' => $this->apiKey(),
            'nodes' => [[
                'host' => $this->host(),
                'port' => $this->port(),
                'protocol' => $this->protocol(),
            ]],
            'num_retries' => 0,
            'client' => new HttplugClient(HttpClient::create([
                'timeout' => PHP_SAPI === 'cli' ? 30 : 2,
            ])),
        ]);
    }

    /**
     * Logs a failed call to Typesense once per outage, not once per request:
     * the first failure sets a short-lived flag and later ones stay quiet
     * until it expires. Only reached on failure, so it costs nothing while
     * the server is up.
     */
    public function unreachable(string $what, \Throwable $e): void
    {
        if ($this->cache->add('ernestdefoe-typesense.down', true, self::DOWN_TTL)) {
            $this->log->warning("[typesense] $what failed: {$e->getMessage()}. Until Typesense answers, searches use the database and changes are not indexed; `php flarum typesense:index` catches the index up.");
        }
    }

    /**
     * Reachability check for the admin "Test connection" button. Returns a
     * short human-readable status; never throws.
     */
    public function ping(): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'error' => 'not_configured'];
        }

        try {
            $health = $this->client()->health->retrieve();

            return ['ok' => (bool) ($health['ok'] ?? false)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
