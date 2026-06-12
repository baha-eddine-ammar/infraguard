<?php

namespace App\Services;

use App\Models\PcMetricsToken;

class PcMetricsTokenService
{
    private const DEFAULT_TOKEN_NAME = 'local-pc';

    public function current(): ?PcMetricsToken
    {
        return PcMetricsToken::query()
            ->where('name', self::DEFAULT_TOKEN_NAME)
            ->first();
    }

    public function store(string $plainToken): PcMetricsToken
    {
        $token = trim($plainToken);

        return PcMetricsToken::query()->updateOrCreate(
            ['name' => self::DEFAULT_TOKEN_NAME],
            [
                'token_hash' => $this->hashToken($token),
                'token_prefix' => substr($token, 0, 4),
                'token_suffix' => substr($token, -4),
                'last_rotated_at' => now(),
            ],
        );
    }

    public function matches(string $providedToken): bool
    {
        $token = trim($providedToken);

        if ($token === '') {
            return false;
        }

        $databaseToken = $this->current();

        if ($databaseToken && hash_equals($databaseToken->token_hash, $this->hashToken($token))) {
            return true;
        }

        $configuredToken = $this->configuredToken();

        return $configuredToken !== '' && hash_equals($configuredToken, $token);
    }

    public function settingsSummary(): array
    {
        $databaseToken = $this->current();

        if ($databaseToken) {
            return [
                'configured' => true,
                'source' => 'Database',
                'masked' => $databaseToken->maskedValue(),
                'updatedLabel' => $databaseToken->last_rotated_at
                    ? $databaseToken->last_rotated_at->timezone(config('app.timezone'))->format('d M Y H:i')
                    : null,
            ];
        }

        $configuredToken = $this->configuredToken();

        if ($configuredToken !== '') {
            return [
                'configured' => true,
                'source' => '.env fallback',
                'masked' => $this->maskToken($configuredToken),
                'updatedLabel' => null,
            ];
        }

        return [
            'configured' => false,
            'source' => null,
            'masked' => null,
            'updatedLabel' => null,
        ];
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function configuredToken(): string
    {
        return trim((string) config('services.pc_metrics.token', ''));
    }

    private function maskToken(string $token): string
    {
        return substr($token, 0, 4).'********'.substr($token, -4);
    }
}
