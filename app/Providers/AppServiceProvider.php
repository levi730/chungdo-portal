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

        // Nav menus, resolved when the nav actually renders rather than when the
        // application boots.
        //
        // These used to be View::share()d with data queried here in boot(). That
        // ran two queries on EVERY boot — every artisan command, every queue
        // job, `migrate` included — which is why it needed a try/catch for the
        // case where the tables don't exist yet. It also froze the menu for the
        // life of the process: fine when a process serves one request, wrong
        // under Octane or any long-lived worker, and the reason a school created
        // in a test never appeared in the nav.
        //
        // A composer runs at render time, so the queries happen only on a page
        // that has a nav, and always against current data. No guard is needed
        // because `migrate` never renders a view.
        View::composer('partials.nav', function ($view) {
            $view->with([
                'school_menu' => School::orderBy('name')->get(),
                'event_menu' => Event::orderBy('startdatetime', 'DESC')->take(6)->get(),
            ]);
        });

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
