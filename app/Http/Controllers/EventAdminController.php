<?php

namespace App\Http\Controllers;

use App\EventAddons\AddonConfigurator;
use App\Http\Requests\EventRequest;
use App\Models\Event;
use App\Models\Rank;
use App\Models\School;
use App\Services\Stripe\StripeAccounts;
use Illuminate\Http\Request;

class EventAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:event.manage');
    }

    public function index()
    {
        $events = Event::withTrashed()->withCount('registrations')->with('addons')->orderByDesc('startdatetime')->get();

        return view('event.admin.index', compact('events'));
    }

    public function create()
    {
        $event = new Event();
        $ranks = Rank::orderBy('id')->get();
        $schools = School::orderBy('name')->get();

        return view('event.admin.form', ['event' => $event, 'ranks' => $ranks, 'schools' => $schools, 'creating' => true, 'stripeAccounts' => app(StripeAccounts::class)]);
    }

    public function store(EventRequest $request)
    {
        $event = new Event();
        $this->fill($event, $request);
        $event->save();

        $this->syncMedia($event, $request);
        $blocked = $this->syncAddons($event, $request);
        $this->syncParticipationAddon($event);

        return redirect()->route('events.edit', $event)->with('success', $this->flashMessage('Event created.', $blocked));
    }

    public function edit(Event $event)
    {
        $ranks = Rank::orderBy('id')->get();
        $schools = School::orderBy('name')->get();
        $event->load('addons');

        return view('event.admin.form', ['event' => $event, 'ranks' => $ranks, 'schools' => $schools, 'creating' => false, 'stripeAccounts' => app(StripeAccounts::class)]);
    }

    public function update(EventRequest $request, Event $event)
    {
        $this->fill($event, $request);
        $event->save();

        $this->syncMedia($event, $request);
        $blocked = $this->syncAddons($event, $request);
        $this->syncParticipationAddon($event);

        return redirect()->route('events.edit', $event)->with('success', $this->flashMessage('Event updated.', $blocked));
    }

    public function destroy(Event $event)
    {
        $event->delete(); // soft delete (archive)

        return redirect()->route('events.index')->with('success', 'Event archived.');
    }

    public function restore($id)
    {
        $event = Event::withTrashed()->findOrFail($id);
        abort_unless(auth()->user()->can('event.manage'), 403);
        $event->restore();

        return redirect()->route('events.index')->with('success', 'Event restored.');
    }

    public function deleteMedia(Event $event, $mediaId)
    {
        $media = $event->media()->findOrFail($mediaId);
        $media->delete();

        return back()->with('success', 'File removed.');
    }

    /** Store the focal point (0-100%) and zoom for a slideshow image. */
    public function setMediaFocus(Request $request, Event $event, $mediaId)
    {
        $media = $event->media()->findOrFail($mediaId);

        $data = $request->validate([
            'focusX' => 'required|numeric|between:0,100',
            'focusY' => 'required|numeric|between:0,100',
            'zoom' => 'nullable|numeric|between:1,3',
        ]);

        $media->setCustomProperty('focusX', round($data['focusX'], 2));
        $media->setCustomProperty('focusY', round($data['focusY'], 2));
        $media->setCustomProperty('focusZoom', round($data['zoom'] ?? 1, 2));
        $media->save();

        return response()->json(['ok' => true]);
    }

    private function fill(Event $event, EventRequest $request): void
    {
        $event->fill($request->only([
            'name', 'type', 'startdatetime', 'enddatetime', 'location', 'host_school_id', 'details', 'slug', 'map_url', 'minimum_rank_id', 'require_ticket',
        ]));

        // The Stripe account is locked once money has moved (see EventRequest),
        // and the select is disabled in that case so nothing is posted.
        if (! $event->exists || ! $event->hasPayments()) {
            $event->stripe_account = app(StripeAccounts::class)
                ->resolve($request->input('stripe_account'));
        }
    }

    /**
     * Participation isn't an admin-toggled add-on — it's intrinsic to a Combined
     * tournament. Keep an enabled, deadline-free participation add-on for combined
     * events (so answers can be stored) and disabled otherwise.
     */
    private function syncParticipationAddon(Event $event): void
    {
        if ($event->type === Event::TYPE_COMBINED) {
            \App\Models\EventAddon::updateOrCreate(
                ['event_id' => $event->id, 'type' => 'participation'],
                ['enabled' => true, 'closes_at' => null, 'settings' => []]
            );
        } else {
            \App\Models\EventAddon::where('event_id', $event->id)->where('type', 'participation')
                ->update(['enabled' => false]);
        }
    }

    private function syncMedia(Event $event, Request $request): void
    {
        foreach ((array) $request->file('forms', []) as $file) {
            $event->addMedia($file)->toMediaCollection('forms');
        }
        foreach ((array) $request->file('slideshow', []) as $file) {
            $event->addMedia($file)->toMediaCollection('slideshow-images');
        }
    }

    /**
     * @return string[] potluck items that couldn't be removed (still chosen)
     */
    private function syncAddons(Event $event, Request $request): array
    {
        (new AddonConfigurator())->apply(
            $event,
            (array) $request->input('enabled', []),
            (array) $request->input('settings', []),
            (array) $request->input('closes_at', [])
        );

        if ($request->has('potluck_catalog_present')) {
            return (new \App\Services\PotluckCatalog())->sync($event->id, (array) $request->input('potluck_catalog', []));
        }

        return [];
    }

    /** Append a "couldn't remove in-use potluck items" note to the flash message. */
    private function flashMessage(string $base, array $blocked): string
    {
        if (empty($blocked)) {
            return $base;
        }

        return $base.' Kept potluck items still chosen by registrants: '.implode(', ', $blocked).'.';
    }
}
