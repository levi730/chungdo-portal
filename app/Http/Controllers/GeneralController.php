<?php

namespace App\Http\Controllers;

use App\Models\Event;
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

        return view('dashboard', compact('next_events'));
    }

    public function test(Request $request, $path)
    {
        dd($request->all(), $path);

        return 'OK';

    }
}
