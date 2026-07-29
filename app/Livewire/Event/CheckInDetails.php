<?php

namespace App\Livewire\Event;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Rank;
use App\Models\School;
use App\Models\User;
use Livewire\Component;

/**
 * Per-registrant check-in: verify/edit each person's info and mark them checked
 * in, advancing through a household one card at a time. Replaces the Inertia
 * CheckIn/Detail + UserForm pages.
 */
class CheckInDetails extends Component
{
    public string $slug;

    public int $eventId;

    /** @var int[] user ids still to process */
    public array $userIds = [];

    /** @var array<int, array<string, mixed>> one form per remaining user */
    public array $forms = [];

    public ?array $flash = null;

    public function mount(string $slug, string $users): void
    {
        $event = Event::where('slug', $slug)->firstOrFail();
        $this->slug = $slug;
        $this->eventId = $event->id;
        $this->userIds = array_values(array_filter(array_map('intval', explode(',', $users))));
        $this->loadForms();
    }

    private function loadForms(): void
    {
        $this->forms = [];
        $users = User::whereIn('id', $this->userIds)->with(['school', 'rank'])->get()->keyBy('id');

        foreach ($this->userIds as $id) {
            $user = $users->get($id);
            if (! $user) {
                continue;
            }
            $reg = EventRegistration::where('event_id', $this->eventId)->where('user_id', $id)->first();

            $this->forms[] = [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'registered' => (bool) $reg,
                'checked_in' => $reg && $reg->checkin,
                'tshirt' => $reg ? $reg->tshirtSize() : null,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'rank_id' => $user->rank_id,
                'school_id' => $user->school_id,
                'dob' => optional($user->dob)->format('Y-m-d'),
                'height' => $user->height,
                'weight' => $user->weight,
                'sex' => $user->sex,
            ];
        }
    }

    public function checkIn(int $index): void
    {
        $this->validate([
            "forms.$index.firstname" => 'required',
            "forms.$index.lastname" => 'required',
            "forms.$index.height" => 'required|numeric',
            "forms.$index.weight" => 'required|numeric',
            "forms.$index.sex" => 'required|in:F,M',
            "forms.$index.dob" => 'required|date',
            "forms.$index.school_id" => 'required',
            "forms.$index.rank_id" => 'required',
        ], attributes: [
            "forms.$index.firstname" => 'first name',
            "forms.$index.lastname" => 'last name',
            "forms.$index.school_id" => 'school',
            "forms.$index.rank_id" => 'rank',
        ]);

        $f = $this->forms[$index];
        $user = User::findOrFail($f['id']);
        $user->update([
            'firstname' => $f['firstname'],
            'lastname' => $f['lastname'],
            'rank_id' => $f['rank_id'],
            'school_id' => $f['school_id'],
            'dob' => $f['dob'],
            'height' => $f['height'],
            'weight' => $f['weight'],
            'sex' => $f['sex'],
        ]);

        $reg = EventRegistration::where('event_id', $this->eventId)->where('user_id', $f['id'])->first();
        if ($reg) {
            $reg->checkin = now();
            $reg->save();
        }

        $this->flash = ['type' => 'success', 'msg' => "'".$user->full_name."' was checked in!"];
        $this->removeUser($f['id']);
    }

    public function skip(int $id): void
    {
        $this->removeUser($id);
    }

    private function removeUser(int $id): void
    {
        $this->userIds = array_values(array_filter($this->userIds, fn ($x) => $x !== $id));
        $this->loadForms();
    }

    public function render()
    {
        return view('livewire.event.check-in-details', [
            'ranks' => Rank::orderBy('id')->get(),
            'schools' => School::orderBy('shortname')->get(),
            'event' => Event::find($this->eventId),
        ]);
    }
}
