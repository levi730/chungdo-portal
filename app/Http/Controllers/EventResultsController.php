<?php

namespace App\Http\Controllers;

use App\Models\Event;

class EventResultsController extends Controller
{
    public function show(string $slug)
    {
        return view('event.results', ['event' => Event::where('slug', $slug)->firstOrFail()]);
    }

    public function scoring(string $slug)
    {
        return view('event.scoring', ['event' => Event::where('slug', $slug)->firstOrFail()]);
    }

    public function trophies(string $slug)
    {
        return view('event.trophies', ['event' => Event::where('slug', $slug)->firstOrFail()]);
    }
}
