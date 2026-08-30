<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Carbon\Carbon;
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

    public  function dashboard(Request $request) {
        $next_events = Event::where('startdatetime', '>=', (new Carbon())->startOfDay())
            ->orderBy('startdatetime', 'asc')
            ->take(2)
            ->get();
        return view('dashboard', compact('next_events'));
    }

    public function test(Request $request, $path)
    {
        dd($request->all(), $path);

        return 'OK';

    }
}
