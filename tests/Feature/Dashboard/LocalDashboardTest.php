<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LocalDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_visitors_open_the_dashboard_as_staff_without_a_persistent_login(): void
    {
        $this->app->instance('env', 'local');
        $user = User::factory()->create();
        config(['app.local_dashboard_user_email' => $user->email]);

        $this->get('http://127.0.0.1/')
            ->assertRedirect('/dashboard')
            ->assertSessionMissing(Auth::guard('web')->getName());

        Auth::forgetGuards();

        $this->get('http://127.0.0.1/dashboard')
            ->assertOk()
            ->assertSeeText('Server section');

        $this->getJson('http://127.0.0.1/dashboard/temperature-feed')->assertOk();
        $this->get('http://127.0.0.1/admin')->assertForbidden();
    }

    public function test_an_opted_in_account_is_ignored_outside_the_local_environment(): void
    {
        $user = User::factory()->create();
        config(['app.local_dashboard_user_email' => $user->email]);

        $this->get('http://127.0.0.1/')->assertOk();
        $this->get('http://127.0.0.1/dashboard')->assertRedirect('/login');
    }

    public function test_remote_visitors_still_need_to_log_in_in_the_local_environment(): void
    {
        $this->app->instance('env', 'local');
        $user = User::factory()->create();
        config(['app.local_dashboard_user_email' => $user->email]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->get('http://127.0.0.1/dashboard')
            ->assertRedirect('/login');
    }
}
