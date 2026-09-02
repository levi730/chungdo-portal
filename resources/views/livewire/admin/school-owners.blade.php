<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title mb-0">Owners</h3>
    </div>
    <div class="card-body">

        <p class="text-secondary">
            The people who run this school. Anyone listed here can edit its details,
            so this list is also who has access — only association staff can change it.
        </p>

        <div class="mb-3 position-relative">
            <label class="form-label">Add an owner</label>
            <input type="text" class="form-control"
                   placeholder="Search by name or email"
                   wire:model.live.debounce.300ms="search"
                   autocomplete="off">

            @if($this->searchResults->isNotEmpty())
                {{-- The background is the fix, not the z-index. A .list-group is
                     transparent by default, so an absolutely positioned one sits
                     on top of the content below while letting it show straight
                     through — the results and the text beneath appeared printed
                     over each other. --}}
                <div class="list-group position-absolute w-100 shadow rounded"
                     style="z-index:1030; background: var(--tblr-bg-surface, #fff);">
                    @foreach($this->searchResults as $result)
                        <button type="button" class="list-group-item list-group-item-action"
                                wire:key="result-{{ $result->id }}"
                                wire:click="addOwner({{ $result->id }})">
                            {{ $result->lastname }}, {{ $result->firstname }}
                            <span class="text-secondary small">{{ $result->email }}</span>
                        </button>
                    @endforeach
                </div>
            @elseif(strlen(trim($search)) >= 2)
                <div class="text-secondary small mt-1">Nobody matching that.</div>
            @endif
        </div>

        @if($this->owners->isEmpty())
            <div class="text-secondary">
                No owners yet. Until someone is listed, only association staff can edit this school.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th class="text-center">On the directory</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->owners as $owner)
                            <tr wire:key="owner-{{ $owner->id }}">
                                <td>{{ $owner->lastname }}, {{ $owner->firstname }}</td>
                                <td class="text-secondary">{{ $owner->email }}</td>
                                <td class="text-center">
                                    {{-- principal drives principal_instructors_text, which is
                                         what both directories show under the school name. --}}
                                    <input type="checkbox" class="form-check-input m-0"
                                           @checked($owner->principal)
                                           wire:click="togglePrincipal({{ $owner->id }})">
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-ghost-danger"
                                            wire:click="removeOwner({{ $owner->id }})"
                                            wire:confirm="Remove {{ $owner->firstname }} {{ $owner->lastname }} as an owner? They will lose the ability to edit this school.">
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <small class="form-hint mt-2">
            Changes here save straight away — they are not part of the Save button above.
        </small>
    </div>
</div>
