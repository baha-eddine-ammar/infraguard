<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UseLocalDashboardAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $email = config('app.local_dashboard_user_email');

        if (! app()->isLocal() || blank($email)
            || ! in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true)
            || ! in_array($request->getHost(), ['localhost', '127.0.0.1', '[::1]'], true)
        ) {
            return $next($request);
        }

        $guard = Auth::guard('web');

        if ($guard->guest()) {
            $user = User::query()->where('email', $email)->first();

            if (! $user?->isStaff() || ! $user->hasApprovedStatus() || ! $user->hasVerifiedEmail()) {
                return $next($request);
            }

            $guard->setUser($user);
        }

        if ($request->is('/')) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
