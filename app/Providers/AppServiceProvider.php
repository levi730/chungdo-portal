<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\School;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Sendportal\Base\Facades\Sendportal;
use Illuminate\Support\Facades\Blade;
use App\Services\MarkdownProcessorService;
use App\Models\PassportClient;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Register any application services.
     */
    public function register(): void
    {

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Sendportal::setCurrentWorkspaceIdResolver(function () {
            return 1;
        });
        Sendportal::setSidebarHtmlContentResolver(function () {
            return view('layouts.sendportal.sidebar.manageUsersMenuItem')->render();
        });
        Sendportal::setHeaderHtmlContentResolver(function () {
            return view('layouts.sendportal.header.userManagementHeader')->render();
        });


        Blade::directive('md', function ($expression) {
            return "<?php echo app(" . MarkdownProcessorService::class . "::class)->render($expression); ?>";
        });

        // Shared nav menus. Guarded so the app can still boot when these tables
        // don't exist yet (fresh DB migrations, test bootstrap) — otherwise every
        // artisan command, including `migrate`, would fail on boot.
        try {
            $school_data = School::orderBy('name')
                ->get();

            $event_data = Event::orderBy('startdatetime', 'DESC')
                ->take(6)
                ->get();
        } catch (\Throwable $e) {
            $school_data = collect();
            $event_data = collect();
        }

        View::share([
            'school_menu' => $school_data,
            'event_menu' => $event_data,
        ]);

        $this->bootAuth();
        $this->bootRoute();
    }

    public function bootAuth()
    {
        // Trusted confidential clients skip the OAuth consent screen — required
        // to preserve the OIDC nonce (see App\Models\PassportClient).
        Passport::useClientModel(PassportClient::class);

        Gate::before(function ($user, $ability) {
            return $user->hasRole('super.admin') ? true : null;
        });

        // User administration is restricted to super.admins. (The before() hook
        // already grants them everything; this makes the ability explicit for
        // `can:manage-users` route/nav checks and denies everyone else.)
        Gate::define('manage-users', fn ($user) => $user->hasRole('super.admin'));
    }

    public function bootRoute()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

    }
}
