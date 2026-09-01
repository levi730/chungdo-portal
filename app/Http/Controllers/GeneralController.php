<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\ProductOrder;
use App\Services\HomepageHighlights;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;

class GeneralController extends Controller
{
    public function firstVisit(Request $request, $slug = null)
    {
        if (! $request->hasCookie('firstVisitMade')) {
            Cookie::queue('firstVisitMade', time(), 2147483647);
        }

        return view('general.first-visit');
    }

    public function dashboard(Request $request)
    {
        // Events and store items share one priority scale, so a shirt can sit
        // between two events. The top one or two get the large treatment; the
        // rest fill the grid. See App\Services\HomepageHighlights.
        $highlights = app(HomepageHighlights::class);
        $featured = $highlights->featured();
        $rest = $highlights->rest();

        // The summary band. All of this already existed on the User; none of it
        // had anywhere to be shown, while the left half of this page held three
        // lines of placeholder text.
        $user = $request->user();

        $my_registrations = $user
            ? $user->events()->where('startdatetime', '>=', now())->orderBy('startdatetime')->get()
            : collect();

        $household_count = $user ? $user->dependents()->count() : 0;

        $my_orders = $user
            ? ProductOrder::where('user_id', $user->id)
                ->where('status', ProductOrder::STATUS_PAID)
                ->latest('paid_at')
                ->get()
            : collect();

        return view('dashboard', compact(
            'featured', 'rest',
            'user', 'my_registrations', 'household_count', 'my_orders'
        ));
    }

    public function test(Request $request, $path)
    {
        dd($request->all(), $path);

        return 'OK';

    }
}
