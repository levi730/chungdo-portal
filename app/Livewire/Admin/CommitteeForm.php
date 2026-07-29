<?php

namespace App\Livewire\Admin;

use App\Models\Committee;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CommitteeForm extends Component
{
    public ?int $committeeId = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    /** Once the slug is edited by hand, stop auto-deriving it from the name. */
    public bool $slugTouched = false;

    /** Selected member user ids (order preserved for display). */
    public array $memberIds = [];

    /** Autocomplete query for adding members. */
    public string $search = '';

    public function boot(): void
    {
        abort_unless(Gate::allows('manage-users'), 403);
    }

    public function mount(?int $committeeId = null): void
    {
        if ($committeeId) {
            $committee = Committee::findOrFail($committeeId);
            $this->committeeId = $committee->id;
            $this->name = $committee->name;
            $this->slug = (string) $committee->slug;
            $this->description = (string) $committee->description;
            $this->memberIds = $committee->members()->pluck('users.id')->all();
            // Don't auto-rewrite an established slug when the name is edited.
            $this->slugTouched = true;
        }
    }

    public function updatedName(): void
    {
        if (! $this->slugTouched) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function updatedSlug(): void
    {
        $this->slugTouched = true;
    }

    /**
     * Student users matching the search, excluding those already selected.
     */
    #[Computed]
    public function searchResults()
    {
        $term = trim($this->search);

        if (strlen($term) < 2) {
            return collect();
        }

        return User::query()
            ->where('is_student', 1)
            ->whereNotIn('id', $this->memberIds)
            ->where(function ($q) use ($term) {
                $q->where('firstname', 'like', "%{$term}%")
                    ->orWhere('lastname', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            })
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->limit(8)
            ->get(['id', 'firstname', 'lastname', 'email']);
    }

    /**
     * The selected members, in display order, with the pivot "added" date for
     * members already persisted on this committee.
     */
    #[Computed]
    public function selectedMembers()
    {
        if (empty($this->memberIds)) {
            return collect();
        }

        $addedAt = $this->committeeId
            ? Committee::find($this->committeeId)?->members()
                ->pluck('committee_user.created_at', 'users.id') ?? collect()
            : collect();

        return User::whereIn('id', $this->memberIds)
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get(['id', 'firstname', 'lastname', 'email'])
            ->map(function ($user) use ($addedAt) {
                $user->added_at = $addedAt[$user->id] ?? null;

                return $user;
            });
    }

    public function addMember(int $userId): void
    {
        // Only students can be committee members.
        $isStudent = User::where('id', $userId)->where('is_student', 1)->exists();

        if ($isStudent && ! in_array($userId, $this->memberIds, true)) {
            $this->memberIds[] = $userId;
        }

        $this->reset('search');
    }

    public function removeMember(int $userId): void
    {
        $this->memberIds = array_values(array_filter(
            $this->memberIds,
            fn ($id) => $id !== $userId
        ));
    }

    public function save()
    {
        // Default the slug from the name, and normalise whatever was entered.
        $this->slug = Str::slug($this->slug !== '' ? $this->slug : $this->name);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('committees', 'name')->ignore($this->committeeId)],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', Rule::unique('committees', 'slug')->ignore($this->committeeId)],
            'description' => ['nullable', 'string'],
        ]);

        $committee = $this->committeeId
            ? Committee::findOrFail($this->committeeId)
            : new Committee();

        $committee->name = $validated['name'];
        $committee->slug = $validated['slug'];
        $committee->description = $validated['description'] ?: null;
        $committee->save();

        // Keep only valid student ids, then diff against current membership so
        // existing members keep their original pivot created_at ("added" date).
        $validIds = User::where('is_student', 1)
            ->whereIn('id', $this->memberIds)
            ->pluck('id')
            ->all();

        $current = $committee->members()->pluck('users.id')->all();
        $toAttach = array_diff($validIds, $current);
        $toDetach = array_diff($current, $validIds);

        if ($toAttach) {
            $committee->members()->attach($toAttach); // withTimestamps() sets created_at
        }
        if ($toDetach) {
            $committee->members()->detach($toDetach);
        }

        session()->flash('admin-committee-success', "Committee \"{$committee->name}\" saved.");

        return redirect()->route('admin.committees.index');
    }

    public function delete()
    {
        if ($this->committeeId) {
            $committee = Committee::findOrFail($this->committeeId);
            $name = $committee->name;
            $committee->delete(); // pivot rows cascade
            session()->flash('admin-committee-success', "Committee \"{$name}\" deleted.");
        }

        return redirect()->route('admin.committees.index');
    }

    public function render()
    {
        return view('livewire.admin.committee-form');
    }
}
