<?php

namespace App\Livewire\Admin;

use App\Models\School;
use App\Models\SchoolInstructor;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Who owns a school — the rows in school_instructors.
 *
 * This is not cosmetic: SchoolPolicy reads exactly this relationship to decide
 * who may edit a school, so adding someone here hands them edit rights. That is
 * why the whole component requires `school.manage` rather than merely being an
 * owner: otherwise an owner could grant a stranger rights to their school, or
 * remove their co-owners and take sole control, with nobody in the association
 * involved.
 *
 * Changes save immediately. The surrounding school form is plain Blade, and a
 * half-saved owner list is worse than one that is always current.
 */
class SchoolOwners extends Component
{
    public School $school;

    public string $search = '';

    /**
     * Re-checked on every request, including Livewire's AJAX updates, which
     * don't pass through the route middleware.
     */
    public function boot(): void
    {
        abort_unless(auth()->user()?->can('school.manage'), 403);
    }

    #[Computed]
    public function owners(): Collection
    {
        // Qualified: users has a school_id of its own, so an unqualified
        // where() is ambiguous once the join is on.
        return SchoolInstructor::where('school_instructors.school_id', $this->school->id)
            ->join('users', 'users.id', '=', 'school_instructors.user_id')
            ->orderBy('users.lastname')
            ->orderBy('users.firstname')
            ->get([
                'school_instructors.id as row_id',
                'school_instructors.principal',
                'users.id',
                'users.firstname',
                'users.lastname',
                'users.email',
            ]);
    }

    #[Computed]
    public function searchResults(): Collection
    {
        $term = trim($this->search);

        if (strlen($term) < 2) {
            return collect();
        }

        return User::query()
            ->whereNotIn('id', $this->owners->pluck('id'))
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

    public function addOwner(int $userId): void
    {
        if (! User::whereKey($userId)->exists()) {
            return;
        }

        // principal defaults on: every existing row is a principal, and an
        // owner not shown on the directory is the unusual case.
        SchoolInstructor::firstOrCreate(
            ['school_id' => $this->school->id, 'user_id' => $userId],
            ['principal' => 1],
        );

        $this->reset('search');
        unset($this->owners);
    }

    public function removeOwner(int $userId): void
    {
        SchoolInstructor::where('school_id', $this->school->id)
            ->where('user_id', $userId)
            ->delete();

        unset($this->owners);
    }

    /** Whether this owner is listed publicly as a principal instructor. */
    public function togglePrincipal(int $userId): void
    {
        $row = SchoolInstructor::where('school_id', $this->school->id)
            ->where('user_id', $userId)
            ->first();

        if ($row) {
            $row->update(['principal' => $row->principal ? 0 : 1]);
            unset($this->owners);
        }
    }

    public function render()
    {
        return view('livewire.admin.school-owners');
    }
}
