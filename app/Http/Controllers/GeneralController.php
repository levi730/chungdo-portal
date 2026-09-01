<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Product;
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
        // Featured events pinned on top, soonest upcoming filling in below —
        // see Event::forHomepage().
        $next_events = Event::forHomepage();

        // Store items are opt-in only: a product appears here because someone
        // ticked "Feature on the home page" for it, not because it happens to
        // be on sale. Nothing featured means no store row at all — see
        // Product::forHomepage().
        $featured_products = Product::forHomepage();

        return view('dashboard', compact('next_events', 'featured_products'));
    }

    public function test(Request $request, $path)
    {
        dd($request->all(), $path);

        return 'OK';

    }
}
