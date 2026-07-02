<?php

namespace Ernestdefoe\Typesense;

use Flarum\Settings\SettingsRepositoryInterface;
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
    private ?Client $client = null;

    public function __construct(
        protected SettingsRepositoryInterface $settings
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
     * Typesense instance. Falls back to a slug of the forum URL host so two
     * forums never clobber each other's index out of the box.
     */
    public function prefix(): string
    {
        $prefix = trim((string) $this->settings->get('ernestdefoe-typesense.collection_prefix', ''));
        if ($prefix !== '') {
            return preg_replace('/[^A-Za-z0-9_]/', '_', $prefix) . '_';
        }
        $host = parse_url((string) $this->settings->get('forum_url', ''), PHP_URL_HOST) ?: 'flarum';
        return preg_replace('/[^A-Za-z0-9_]/', '_', $host) . '_';
    }

    public function collectionName(string $index): string
    {
        return $this->prefix() . $index;
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

        return $this->client = new Client([
            'api_key' => $this->apiKey(),
            'nodes' => [[
                'host' => $this->host(),
                'port' => $this->port(),
                'protocol' => $this->protocol(),
            ]],
            'connection_timeout_seconds' => 2,
            'client' => new HttplugClient(),
        ]);
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
