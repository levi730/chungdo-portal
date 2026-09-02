<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title mb-0">Owners</h3>
    </div>
    <div class="card-body">

        <p class="text-secondary">
            The people who run this school. Anyone listed here can edit its details,
            so this list is also who has access — only association staff can change it.
        </p>

    {{-- `active` is the highlighted result. Keyboard handling lives here rather
         than in Livewire so arrowing through the list costs no round trips —
         only Enter does anything server-side, and it does it by clicking the
         button that already carries the wire:click. --}}
    <div class="mb-3 position-relative" x-data="{ active: -1 }">
            <label class="form-label" for="owner-search">Add an owner</label>
            <input id="owner-search" type="text" class="form-control"
                   placeholder="Search by name or email"
                   wire:model.live.debounce.300ms="search"
                   autocomplete="off"
                   role="combobox"
                   aria-controls="owner-search-results"
                   aria-autocomplete="list"
                   aria-expanded="{{ $this->searchResults->isNotEmpty() ? 'true' : 'false' }}"
                   @input="active = -1"
                   @keydown.arrow-down.prevent="active = Math.min(active + 1, ($refs.results?.children.length ?? 0) - 1); $refs.results?.children[active]?.scrollIntoView({ block: 'nearest' })"
                   @keydown.arrow-up.prevent="active = Math.max(active - 1, 0); $refs.results?.children[active]?.scrollIntoView({ block: 'nearest' })"
                   @keydown.enter.prevent="$refs.results?.children[active]?.click()"
                   @keydown.escape.prevent="active = -1; $wire.set('search', '')">

            @if($this->searchResults->isNotEmpty())
                {{-- The background is the fix, not the z-index. A .list-group is
                     transparent by default, so an absolutely positioned one sits
                     on top of the content below while letting it show straight
                     through — the results and the text beneath appeared printed
                     over each other. --}}
                <div id="owner-search-results" x-ref="results" role="listbox"
                     class="list-group position-absolute w-100 shadow rounded"
                     style="z-index:1030; background: var(--tblr-bg-surface, #fff); max-height:16rem; overflow-y:auto;">
                    @foreach($this->searchResults as $result)
                        <button type="button" role="option"
                                class="list-group-item list-group-item-action"
                                :class="{ 'active': active === {{ $loop->index }} }"
                                :aria-selected="active === {{ $loop->index }}"
                                @mouseenter="active = {{ $loop->index }}"
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

            @if($this->searchResults->isNotEmpty())
                <small class="form-hint mt-1">Arrow keys to move, Enter to add, Esc to clear.</small>
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
