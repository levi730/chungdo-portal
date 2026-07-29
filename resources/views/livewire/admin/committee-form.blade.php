<div>
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row row-cards">
        {{-- Details --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Committee details</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md mb-3">
                            <label class="form-label required">Name</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   wire:model.live.debounce.400ms="name" placeholder="e.g. Tournament Committee">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md mb-3">
                            <label class="form-label required">Slug</label>
                            <input type="text" class="form-control @error('slug') is-invalid @enderror"
                                   wire:model.live="slug" placeholder="tournament-committee">
                            @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <small class="form-hint">Used as the Zulip group name. Lowercase letters, numbers, and hyphens.</small>
                        </div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" rows="3" wire:model="description"
                                  placeholder="What this committee is responsible for."></textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Members --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Members</h3></div>
                <div class="card-body">
                    <label class="form-label">Add a member</label>
                    <div class="position-relative mb-3">
                        <input type="text" class="form-control"
                               wire:model.live.debounce.300ms="search"
                               placeholder="Search students by name or email…"
                               autocomplete="off">

                        @if ($this->searchResults->isNotEmpty())
                            <div class="card position-absolute w-100 mt-1 shadow" style="z-index: 1030;">
                                <div class="list-group list-group-flush">
                                    @foreach ($this->searchResults as $result)
                                        <button type="button"
                                                class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                                                wire:click="addMember({{ $result->id }})">
                                            <span>{{ $result->lastname }}, {{ $result->firstname }}</span>
                                            <span class="text-secondary small">{{ $result->email }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @elseif (strlen(trim($search)) >= 2)
                            <div class="card position-absolute w-100 mt-1 shadow" style="z-index: 1030;">
                                <div class="list-group-item text-secondary">No matching students.</div>
                            </div>
                        @endif
                    </div>

                    @if ($this->selectedMembers->isEmpty())
                        <div class="text-secondary">No members yet. Search above to add students.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-vcenter">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Added</th>
                                        <th class="w-1"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->selectedMembers as $member)
                                        <tr wire:key="member-{{ $member->id }}">
                                            <td>{{ $member->lastname }}, {{ $member->firstname }}</td>
                                            <td class="text-secondary">{{ $member->email }}</td>
                                            <td class="text-secondary">
                                                {{ $member->added_at ? \Illuminate\Support\Carbon::parse($member->added_at)->format('M j, Y') : 'Not yet saved' }}
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-ghost-danger"
                                                        wire:click="removeMember({{ $member->id }})">
                                                    Remove
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="text-secondary small mt-1">{{ $this->selectedMembers->count() }} member(s)</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="col-12">
            <div class="d-flex align-items-center">
                <button type="button" class="btn btn-primary" wire:click="save">Save Committee</button>
                <a href="{{ route('admin.committees.index') }}" class="btn btn-link">Cancel</a>

                @if ($committeeId)
                    <button type="button" class="btn btn-outline-danger ms-auto"
                            wire:click="delete"
                            wire:confirm="Delete this committee? Members will be removed from it.">
                        Delete committee
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
