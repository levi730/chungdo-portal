<?php

namespace App\Http\Controllers;

use App\Http\Requests\SchoolRequest;
use App\Models\Event;
use App\Models\School;

class SchoolController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        $manages = $user->can('school.manage');

        // withTrashed for anyone who can manage schools, so an archived one can
        // be found and restored; everyone else sees only live schools.
        $all_schools = School::query()
            ->when($manages, fn ($q) => $q->withTrashed())
            ->orderBy('name')
            ->get();

        // Which schools this user may edit, resolved in one query rather than
        // asking the policy per school — the policy's instructor lookup would
        // otherwise run once per card. The policy still guards the edit itself.
        $editable_school_ids = $manages
            ? $all_schools->pluck('id')->all()
            : $user->instructor_of()->pluck('schools.id')->all();

        return view('school.index', compact('all_schools', 'editable_school_ids'));
    }

    public function view($id)
    {
        $school = School::findOrFail($id);
        $events = Event::upcoming()->orderBy('startdatetime')->get();

        return view('school.view', compact('school', 'events'));
    }

    public function create()
    {
        $this->authorize('create', School::class);

        return view('school.manage.form', ['school' => new School(), 'creating' => true]);
    }

    public function store(SchoolRequest $request)
    {
        $school = School::create($request->validated());

        return redirect()->route('school.view', $school->id)
            ->with('success', $school->name.' added.');
    }

    public function edit($id)
    {
        $school = School::withTrashed()->findOrFail($id);
        $this->authorize('update', $school);

        return view('school.manage.form', compact('school') + ['creating' => false]);
    }

    public function update(SchoolRequest $request, $id)
    {
        $school = School::withTrashed()->findOrFail($id);
        $this->authorize('update', $school);

        $school->update($request->validated());

        return redirect()->route('school.view', $school->id)
            ->with('success', $school->name.' saved.');
    }

    /**
     * Archive, never destroy. users.school_id, school_instructors.school_id and
     * product_orders.pickup_school_id all point here.
     */
    public function destroy($id)
    {
        $school = School::findOrFail($id);
        $this->authorize('delete', $school);

        $school->delete();

        return redirect()->route('school.index')
            ->with('success', $school->name.' archived. Its members and records are kept.');
    }

    public function restore($id)
    {
        $school = School::withTrashed()->findOrFail($id);
        $this->authorize('restore', $school);

        $school->restore();

        return redirect()->route('school.index')->with('success', $school->name.' restored.');
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
