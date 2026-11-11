<?php

namespace App\Services\Simulation;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/**
 * Hard safety rail for the local-only simulated usage run: the simulator
 * must never touch production, a production copy, Gmail, or any real
 * queue worker. Every simulation command calls enter() before doing
 * anything else.
 */
class SimulationGuard
{
    public const DatabasePrefix = 'deh_simulation';

    private const OllamaContextTokens = 8192;

    /** Neutral name shown instead of the client's while on simulated data. */
    public const DemoBrandName = 'FAQ Autoresponder';

    public static function isSimulationDatabase(string $database): bool
    {
        return str_starts_with($database, self::DatabasePrefix);
    }

    public function assertSafe(string $environment, string $database): void
    {
        if ($environment !== 'local') {
            throw new RuntimeException("Refusing to simulate: APP_ENV is [{$environment}], not [local].");
        }

        if (! self::isSimulationDatabase($database)) {
            throw new RuntimeException('Refusing to simulate: database ['.$database.'] does not start with ['.self::DatabasePrefix.'].');
        }
    }

    /**
     * Point the app at the simulation database and switch off everything
     * that could reach the outside world, except the AI provider calls the
     * pipeline itself makes.
     */
    public function enter(string $database): void
    {
        $this->assertSafe((string) app()->environment(), $database);

        $connection = (string) config('database.default');

        config([
            "database.connections.{$connection}.database" => $database,
            'queue.default' => 'sync',
            'broadcasting.default' => 'null',
        ]);

        DB::purge($connection);
        DB::reconnect($connection);

        $this->assertSafe((string) app()->environment(), (string) DB::connection()->getDatabaseName());

        Http::preventStrayRequests();
        Http::allowStrayRequests([
            rtrim((string) config('ai.providers.openai.url', 'https://api.openai.com/v1'), '/').'/*',
            rtrim((string) config('ai.providers.ollama.url', 'http://localhost:11434'), '/').'/*',
        ]);

        // Jobs run on the sync queue here, where a rate-limit release would
        // silently drop the job instead of retrying it later.
        RateLimiter::for('openai', fn (object $job): Limit => Limit::none());
        RateLimiter::for('gmail', fn (object $job): Limit => Limit::none());
    }

    public function useTextModel(?string $provider, ?string $model): void
    {
        $provider ??= (string) config('ai.default');

        config(['ai.default' => $provider]);

        if ($model !== null && $model !== '') {
            config(["ai.providers.{$provider}.models.text.default" => $model]);
        }

        if ($provider === 'ollama') {
            $this->tuneOllamaRequests();
        }
    }

    /**
     * Local debugging only: switch off "thinking" (minutes per call on a
     * laptop) and widen the context window so prompts with five FAQ
     * matches aren't truncated. Done here rather than on the agents so
     * production code stays untouched.
     */
    private function tuneOllamaRequests(): void
    {
        $ollamaUrl = rtrim((string) config('ai.providers.ollama.url', 'http://localhost:11434'), '/');

        Http::globalRequestMiddleware(function (RequestInterface $request) use ($ollamaUrl): RequestInterface {
            if (! str_starts_with((string) $request->getUri(), $ollamaUrl.'/api/chat')) {
                return $request;
            }

            $body = json_decode((string) $request->getBody(), associative: true) ?? [];
            $body['think'] = false;
            $body['options'] = [...($body['options'] ?? []), 'num_ctx' => self::OllamaContextTokens];

            return $request->withBody(Utils::streamFor(json_encode($body, JSON_THROW_ON_ERROR)));
        });
    }
}
