<?php

namespace App\Http\Controllers;

use App\Models\Event;

class EventResultsController extends Controller
{
    public function show(string $slug)
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        if (! $event->resultsVisibleTo(auth()->user())) {
            return auth()->check() ? abort(403) : redirect()->guest(route('login'));
        }

        return view('event.results', ['event' => $event]);
    }

    public function scoring(string $slug)
    {
        return view('event.scoring', ['event' => Event::where('slug', $slug)->firstOrFail()]);
    }
}
