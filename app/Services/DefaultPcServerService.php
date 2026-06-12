<?php

namespace App\Services;

use App\Models\Server;
use Illuminate\Support\Str;

class DefaultPcServerService
{
    public const IDENTIFIER = 'local-pc';
    public const NAME = 'Local PC';

    public function __construct(
        private readonly PcMetricsTokenService $pcMetricsTokenService,
    ) {
    }

    public function resolve(): Server
    {
        $server = Server::query()->firstOrCreate(
            ['identifier' => self::IDENTIFIER],
            [
                'name' => self::NAME,
                'description' => 'Default monitored machine for the Server Section live PC card.',
                'server_type' => 'Local PC',
                'api_token' => $this->tokenForNewRecord(),
            ],
        );

        return $this->syncDefaultServer($server);
    }

    public function resolveForIdentifier(?string $identifier): ?Server
    {
        if ($this->isDefaultIdentifier($identifier)) {
            return $this->resolve();
        }

        return Server::query()
            ->where('identifier', $identifier)
            ->first();
    }

    public function normalizeIdentifier(?string $identifier): string
    {
        $normalized = Str::lower(trim((string) $identifier));

        return $normalized !== '' ? $normalized : self::IDENTIFIER;
    }

    public function credentialsMatch(Server $server, string $providedToken): bool
    {
        if ($this->isDefaultIdentifier($server->identifier) && $this->pcMetricsTokenService->matches($providedToken)) {
            return true;
        }

        $expectedToken = $this->expectedTokenFor($server);

        return $providedToken !== ''
            && $expectedToken !== ''
            && hash_equals($expectedToken, $providedToken);
    }

    private function syncDefaultServer(Server $server): Server
    {
        $updates = [
            'name' => self::NAME,
            'server_type' => 'Local PC',
        ];

        $configuredToken = $this->configuredToken();

        if ($configuredToken !== '' && ! hash_equals($server->api_token, $configuredToken)) {
            $updates['api_token'] = $configuredToken;
        }

        $server->forceFill($updates);

        if ($server->isDirty()) {
            $server->save();

            return $server->refresh();
        }

        return $server;
    }

    private function expectedTokenFor(Server $server): string
    {
        if ($this->isDefaultIdentifier($server->identifier)) {
            $configuredToken = $this->configuredToken();

            if ($configuredToken !== '') {
                return $configuredToken;
            }
        }

        return (string) $server->api_token;
    }

    private function isDefaultIdentifier(?string $identifier): bool
    {
        return in_array($this->normalizeIdentifier($identifier), [
            self::IDENTIFIER,
            'my-pc',
        ], true);
    }

    private function tokenForNewRecord(): string
    {
        return $this->configuredToken() ?: Str::random(48);
    }

    private function configuredToken(): string
    {
        return trim((string) config('services.pc_metrics.token', ''));
    }
}
