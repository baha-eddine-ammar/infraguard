<?php

namespace Tests\Feature\Api;

use App\Models\Server;
use App\Services\PcMetricsTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_metrics_payload_is_stored(): void
    {
        $beforeRequest = now()->subSecond();

        $server = Server::query()->create([
            'name' => 'SRV-APP-01',
            'identifier' => 'srv-app-01',
            'api_token' => 'secret-token',
        ]);

        $response = $this->withHeaders([
            'X-Server-Token' => 'secret-token',
        ])->postJson(route('api.server-metrics.store'), [
            'identifier' => 'srv-app-01',
            'cpu_percent' => 32.5,
            'ram_used_mb' => 4096,
            'ram_total_mb' => 8192,
            'disk_used_gb' => 200.5,
            'disk_total_gb' => 500,
            'storage_free_gb' => 299.5,
            'storage_total_gb' => 500,
            'temperature_c' => 54.5,
            'network_connected' => true,
            'network_name' => 'Ethernet',
            'network_speed_mbps' => 1000,
            'network_ipv4' => '192.168.1.24',
            'net_rx_mbps' => 8.2,
            'net_tx_mbps' => 3.4,
            'uptime_seconds' => 7200,
            'sampled_at' => '2001-01-01T00:00:00+00:00',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('server.identifier', 'srv-app-01');

        $this->assertDatabaseHas('server_metrics', [
            'server_id' => $server->id,
            'ram_used_mb' => 4096,
            'network_name' => 'Ethernet',
        ]);

        $freshServer = $server->fresh();

        $this->assertTrue($freshServer->last_seen_at->greaterThanOrEqualTo($beforeRequest));
        $this->assertNotSame('2001-01-01 00:00:00', $freshServer->last_seen_at?->format('Y-m-d H:i:s'));
    }

    public function test_local_pc_metrics_payload_can_create_default_server_without_identifier(): void
    {
        config()->set('services.pc_metrics.token', 'pc-test-token');

        $response = $this->withHeaders([
            'X-Server-Token' => 'pc-test-token',
        ])->postJson(route('api.server-metrics.store'), [
            'cpu_percent' => 41.8,
            'ram_used_mb' => 6144,
            'ram_total_mb' => 16384,
            'disk_used_gb' => 280.5,
            'disk_total_gb' => 1000,
            'network_connected' => true,
            'network_name' => 'Ethernet',
            'network_rx' => 7.3,
            'network_tx' => 1.9,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('server.name', 'Local PC')
            ->assertJsonPath('server.identifier', 'local-pc');

        $server = Server::query()->where('identifier', 'local-pc')->first();

        $this->assertNotNull($server);
        $this->assertSame('pc-test-token', $server->api_token);

        $this->assertDatabaseHas('server_metrics', [
            'server_id' => $server->id,
            'ram_used_mb' => 6144,
            'network_name' => 'Ethernet',
            'net_rx_mbps' => 7.3,
            'net_tx_mbps' => 1.9,
        ]);
    }

    public function test_local_pc_metrics_payload_accepts_database_token_without_env_token(): void
    {
        config()->set('services.pc_metrics.token', null);

        app(PcMetricsTokenService::class)->store('database-token-1234567890');

        $response = $this->withHeaders([
            'X-Server-Token' => 'database-token-1234567890',
        ])->postJson(route('api.server-metrics.store'), [
            'cpu_percent' => 52.4,
            'ram_used_mb' => 7000,
            'ram_total_mb' => 16000,
            'disk_used_gb' => 255.25,
            'disk_total_gb' => 512,
            'network_rx' => 4.8,
            'network_tx' => 1.6,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('server.name', 'Local PC')
            ->assertJsonPath('server.identifier', 'local-pc');

        $server = Server::query()->where('identifier', 'local-pc')->first();

        $this->assertNotNull($server);
        $this->assertDatabaseHas('server_metrics', [
            'server_id' => $server->id,
            'ram_used_mb' => 7000,
            'net_rx_mbps' => 4.8,
            'net_tx_mbps' => 1.6,
        ]);
    }

    public function test_invalid_token_is_rejected(): void
    {
        Server::query()->create([
            'name' => 'SRV-APP-01',
            'identifier' => 'srv-app-01',
            'api_token' => 'secret-token',
        ]);

        $response = $this->withHeaders([
            'X-Server-Token' => 'wrong-token',
        ])->postJson(route('api.server-metrics.store'), [
            'identifier' => 'srv-app-01',
            'cpu_percent' => 32.5,
            'ram_used_mb' => 4096,
            'ram_total_mb' => 8192,
            'disk_used_gb' => 200.5,
            'disk_total_gb' => 500,
            'net_rx_mbps' => 8.2,
            'net_tx_mbps' => 3.4,
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid server credentials.');

        $this->assertDatabaseCount('server_metrics', 0);
    }

    public function test_token_must_be_sent_in_header_not_request_body(): void
    {
        Server::query()->create([
            'name' => 'SRV-APP-01',
            'identifier' => 'srv-app-01',
            'api_token' => 'secret-token',
        ]);

        $response = $this->postJson(route('api.server-metrics.store'), [
            'identifier' => 'srv-app-01',
            'api_token' => 'secret-token',
            'cpu_percent' => 32.5,
            'ram_used_mb' => 4096,
            'ram_total_mb' => 8192,
            'disk_used_gb' => 200.5,
            'disk_total_gb' => 500,
            'net_rx_mbps' => 8.2,
            'net_tx_mbps' => 3.4,
        ]);

        $response->assertUnauthorized();

        $this->assertDatabaseCount('server_metrics', 0);
    }

    public function test_identifier_matching_is_case_insensitive_for_agent_payloads(): void
    {
        $server = Server::query()->create([
            'name' => 'SRV-APP-01',
            'identifier' => 'srv-app-01',
            'api_token' => 'secret-token',
        ]);

        $response = $this->withHeaders([
            'X-Server-Token' => 'secret-token',
        ])->postJson(route('api.server-metrics.store'), [
            'identifier' => '  SRV-APP-01  ',
            'cpu_percent' => 32.5,
            'ram_used_mb' => 4096,
            'ram_total_mb' => 8192,
            'disk_used_gb' => 200.5,
            'disk_total_gb' => 500,
            'net_rx_mbps' => 8.2,
            'net_tx_mbps' => 3.4,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('server_metrics', [
            'server_id' => $server->id,
            'ram_used_mb' => 4096,
        ]);
    }
}
