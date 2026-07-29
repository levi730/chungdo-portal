<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\School;

class SchoolController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        if (! $user) {
            abort(500, 'user not found');
        }

        $all_schools = School::orderBy('name')->get();
        $editable_schools = [];
        foreach ($all_schools as $school) {
            if (auth()->user()->can('edit', $school)) {
                $editable_schools[] = $school;
            }
        }

        return view('school.manage.list', compact('editable_schools', 'all_schools'));

    }

    public function view($id)
    {
        $school = School::findOrFail($id);
        $events = Event::upcoming()->orderBy('startdatetime')->get();

        return view('school.view', compact('school', 'events'));
    }

    public function edit($id)
    {
        $school = School::findOrFail($id);

        return view('school.manage.edit', compact('school'));
    }

    public function event($school_id, $event_slug)
    {
        if (! auth()->user()->can('event.viewAllSchoolRegistrants')) {
            abort(403);
        }

        $school = School::findOrFail($school_id);
        $event = Event::where('slug', '=', $event_slug)->firstOrFail();
        $registrants = $event->users()
            ->where('school_id', '=', $school->id)
            ->orderBy('rank_id', 'desc')
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get();

        return view('school.event', compact('school', 'event', 'registrants'));
    }
}
