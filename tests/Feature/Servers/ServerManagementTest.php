<?php

namespace Tests\Feature\Servers;

use App\Models\Server;
use App\Models\User;
use App\Services\PcMetricsTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_head_can_create_a_server(): void
    {
        $admin = User::factory()->create([
            'role' => 'department_head',
            'status' => 'approved',
            'is_approved' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('servers.store'), [
            'name' => 'SRV-APP-01',
            'identifier' => 'srv-app-01',
            'description' => 'Primary application workstation',
            'ip_address' => '192.168.1.24',
            'server_type' => 'Workstation',
        ]);

        $server = Server::query()->first();

        $response->assertRedirect(route('servers.show', $server));
        $this->assertNotNull($server);
        $this->assertNotEmpty($server->api_token);
        $this->assertSame('srv-app-01', $server->identifier);
        $this->assertSame('Primary application workstation', $server->description);
        $this->assertSame('192.168.1.24', $server->ip_address);
        $this->assertSame('Workstation', $server->server_type);
    }

    public function test_staff_cannot_access_server_create_page(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'status' => 'approved',
            'is_approved' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('servers.create'))
            ->assertForbidden();
    }

    public function test_department_head_can_save_pc_metrics_token_from_server_section(): void
    {
        config()->set('services.pc_metrics.token', null);

        $admin = User::factory()->departmentHead()->create();
        $token = 'database-token-1234567890';

        $response = $this->actingAs($admin)
            ->from(route('servers.index'))
            ->post(route('servers.pc-metrics-token.update'), [
                'pc_metrics_token' => $token,
            ]);

        $response
            ->assertRedirect(route('servers.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pc_metrics_tokens', [
            'name' => 'local-pc',
            'token_prefix' => 'data',
            'token_suffix' => '7890',
        ]);

        $this->assertDatabaseMissing('pc_metrics_tokens', [
            'token_hash' => $token,
        ]);

        $this->actingAs($admin)
            ->get(route('servers.index'))
            ->assertOk()
            ->assertSeeText('data********7890')
            ->assertDontSeeText($token);

        $this->assertTrue(app(PcMetricsTokenService::class)->matches($token));
    }

    public function test_staff_cannot_update_pc_metrics_token(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)
            ->post(route('servers.pc-metrics-token.update'), [
                'pc_metrics_token' => 'database-token-1234567890',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('pc_metrics_tokens', 0);
    }

    public function test_server_section_auto_creates_the_default_local_pc_card(): void
    {
        config()->set('services.pc_metrics.token', 'pc-test-token');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('servers.index'))
            ->assertOk()
            ->assertSeeText('Server section')
            ->assertSeeText('Local PC monitoring')
            ->assertSeeText('Local PC')
            ->assertSeeText('CPU usage')
            ->assertSeeText('RAM usage')
            ->assertSeeText('Disk usage')
            ->assertSeeText('Network status')
            ->assertDontSeeText('Add Server');

        $this->assertDatabaseHas('servers', [
            'name' => 'Local PC',
            'identifier' => 'local-pc',
            'server_type' => 'Local PC',
            'api_token' => 'pc-test-token',
        ]);
    }

    public function test_registered_server_still_exposes_its_dedicated_live_feed(): void
    {
        $user = User::factory()->create();

        $server = Server::query()->create([
            'name' => 'SRV-DB-01',
            'identifier' => 'srv-db-01',
            'description' => 'Primary database node',
            'ip_address' => '10.10.10.15',
            'server_type' => 'Database',
            'api_token' => 'token-123',
            'last_seen_at' => now(),
        ]);

        $server->metrics()->create([
            'cpu_percent' => 42.5,
            'ram_used_mb' => 8192,
            'ram_total_mb' => 16384,
            'disk_used_gb' => 320.4,
            'disk_total_gb' => 1000,
            'storage_free_gb' => 679.6,
            'storage_total_gb' => 1000,
            'temperature_c' => 58.3,
            'network_connected' => true,
            'network_name' => 'Ethernet',
            'network_speed_mbps' => 1000,
            'network_ipv4' => '10.10.10.15',
            'net_rx_mbps' => 12.8,
            'net_tx_mbps' => 4.2,
            'uptime_seconds' => 86400,
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('servers.index'))
            ->assertOk()
            ->assertSeeText('Local PC')
            ->assertSeeText('CPU usage')
            ->assertSeeText('RAM usage')
            ->assertSeeText('Disk usage')
            ->assertSeeText('Network status')
            ->assertDontSeeText('SRV-DB-01')
            ->assertDontSeeText('LOCAL-WORKSTATION');

        $feed = $this->actingAs($user)->getJson(route('servers.feed', $server));

        $feed->assertOk()
            ->assertJsonPath('name', 'SRV-DB-01')
            ->assertJsonPath('identifier', 'srv-db-01')
            ->assertJsonPath('status', 'Live')
            ->assertJsonCount(6, 'metrics');
    }

    public function test_server_section_live_state_endpoint_returns_one_focused_card_payload(): void
    {
        config()->set('services.pc_metrics.token', 'pc-test-token');

        $user = User::factory()->create();

        $server = Server::query()->create([
            'name' => 'Old Local Name',
            'identifier' => 'local-pc',
            'api_token' => 'old-token',
            'last_seen_at' => now(),
        ]);

        $server->metrics()->create([
            'cpu_percent' => 12.3,
            'ram_used_mb' => 2048,
            'ram_total_mb' => 16384,
            'disk_used_gb' => 120,
            'disk_total_gb' => 1000,
            'network_connected' => true,
            'network_name' => 'Older row',
            'net_rx_mbps' => 1.1,
            'net_tx_mbps' => 0.5,
            'created_at' => now()->addMinute(),
        ]);

        $server->metrics()->create([
            'cpu_percent' => 64.5,
            'ram_used_mb' => 8192,
            'ram_total_mb' => 16384,
            'disk_used_gb' => 420,
            'disk_total_gb' => 1000,
            'storage_free_gb' => 580,
            'storage_total_gb' => 1000,
            'temperature_c' => 49.2,
            'network_connected' => true,
            'network_name' => 'Ethernet',
            'network_speed_mbps' => 1000,
            'network_ipv4' => '10.10.10.21',
            'net_rx_mbps' => 9.4,
            'net_tx_mbps' => 2.1,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson(route('servers.live-state'));

        $response
            ->assertOk()
            ->assertHeader('Pragma', 'no-cache')
            ->assertJsonPath('server.name', 'Local PC')
            ->assertJsonPath('server.identifier', 'local-pc')
            ->assertJsonPath('server.status', 'Live')
            ->assertJsonPath('server.latestMetric.cpu.raw', 64.5)
            ->assertJsonPath('server.latestMetric.network.rxMbps', 9.4)
            ->assertJsonPath('server.latestMetric.network.txMbps', 2.1)
            ->assertJsonCount(4, 'server.metrics');

        $this->assertSame(
            ['cpu', 'ram', 'disk', 'network'],
            collect($response->json('server.metrics'))->pluck('key')->all()
        );

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);

        $this->assertArrayNotHasKey('temperature', $response->json('server.latestMetric'));
        $this->assertArrayNotHasKey('storage', $response->json('server.latestMetric'));

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'name' => 'Local PC',
            'api_token' => 'pc-test-token',
        ]);
    }

    public function test_server_identifier_is_normalized_before_validation_and_storage(): void
    {
        $admin = User::factory()->create([
            'role' => 'department_head',
            'status' => 'approved',
            'is_approved' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('servers.store'), [
            'name' => '  SRV-APP-02  ',
            'identifier' => '  SRV-APP-02  ',
        ]);

        $server = Server::query()->first();

        $response->assertRedirect(route('servers.show', $server));
        $this->assertSame('SRV-APP-02', $server->name);
        $this->assertSame('srv-app-02', $server->identifier);
    }

    public function test_server_identifier_validation_rejects_case_only_duplicates(): void
    {
        $admin = User::factory()->create([
            'role' => 'department_head',
            'status' => 'approved',
            'is_approved' => true,
        ]);

        Server::query()->create([
            'name' => 'SRV-APP-01',
            'identifier' => 'srv-app-01',
            'api_token' => 'token-123',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('servers.create'))
            ->post(route('servers.store'), [
                'name' => 'SRV-APP-01 Duplicate',
                'identifier' => 'SRV-APP-01',
            ]);

        $response->assertRedirect(route('servers.create'));
        $response->assertSessionHasErrors('identifier');
        $this->assertDatabaseCount('servers', 1);
    }

    public function test_server_api_token_is_hidden_from_default_serialization(): void
    {
        $server = Server::query()->create([
            'name' => 'SRV-DB-01',
            'identifier' => 'srv-db-01',
            'api_token' => 'token-123',
        ]);

        $this->assertArrayNotHasKey('api_token', $server->toArray());
    }
}
