<div>
    @php($quoteLines = collect($this->quote['students'])->keyBy('user_id'))

    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title mb-0">1 &middot; Students</h3></div>
        <div class="card-body">
            <p class="text-secondary small mb-3">Search for each student who paid you. Students from your school are listed first.</p>

            <div class="position-relative mb-3">
                <input type="text" class="form-control"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Search by name or email…"
                       autocomplete="off">

                @if ($this->searchResults->isNotEmpty())
                    <div class="card position-absolute w-100 mt-1 shadow" style="z-index: 1030;">
                        <div class="list-group list-group-flush">
                            @foreach ($this->searchResults as $result)
                                @if ($result->blocked_reason)
                                    <div class="list-group-item d-flex justify-content-between align-items-center text-secondary">
                                        <span>{{ $result->lastname }}, {{ $result->firstname }}
                                            <span class="small">&middot; {{ $result->school?->shortname }} &middot; {{ $result->rank?->rank }}</span></span>
                                        <span class="badge bg-secondary-lt">{{ $result->blocked_reason }}</span>
                                    </div>
                                @else
                                    <button type="button"
                                            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                                            wire:click="add({{ $result->id }})">
                                        <span>{{ $result->lastname }}, {{ $result->firstname }}
                                            <span class="text-secondary small">&middot; {{ $result->school?->shortname }} &middot; {{ $result->rank?->rank }}@if($result->age) &middot; age {{ $result->age }}@endif</span></span>
                                        <span class="text-primary small">Add</span>
                                    </button>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @elseif (strlen(trim($search)) >= 2)
                    <div class="card position-absolute w-100 mt-1 shadow" style="z-index: 1030;">
                        <div class="list-group-item text-secondary">No matching members.</div>
                    </div>
                @endif
            </div>

            @error('students') <div class="alert alert-danger">{{ $message }}</div> @enderror

            @if (empty($students))
                <div class="text-secondary">No students yet.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-vcenter">
                        <thead>
                            <tr>
                                <th>Student</th>
                                @foreach ($this->addons as $addon)
                                    @continue($addon->type === 'registration_fee')
                                    <th>{{ $addon->type === 'participation' ? 'Competing in' : $addon->label() }}</th>
                                @endforeach
                                <th class="text-end">Amount</th>
                                <th class="w-1"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($students as $id => $answers)
                                @php($u = $this->selected[$id] ?? null)
                                @continue(! $u)
                                <tr wire:key="student-{{ $id }}">
                                    <td>
                                        {{ $u->full_name }}
                                        <div class="text-secondary small">{{ $u->school?->shortname }} &middot; {{ $u->rank?->rank }}</div>
                                    </td>
                                    @foreach ($this->addons as $addon)
                                        @continue($addon->type === 'registration_fee')
                                        <td>
                                            @switch($addon->type)
                                                @case('tshirt')
                                                    <select wire:model.live="students.{{ $id }}.tshirt" class="form-select form-select-sm">
                                                        <option value="">None</option>
                                                        @foreach (['S','M','L','XL','2XL','3XL'] as $s)<option value="{{ $s }}">Adult {{ $s }}</option>@endforeach
                                                        @foreach (['YXS' => 'XS','YS' => 'S','YM' => 'M','YL' => 'L'] as $v => $s)<option value="{{ $v }}">Youth {{ $s }}</option>@endforeach
                                                    </select>
                                                    @break
                                                @case('participation')
                                                    <select wire:model.live="students.{{ $id }}.participation" class="form-select form-select-sm">
                                                        <option value="both">Sparring + Forms</option>
                                                        <option value="sparring">Sparring only</option>
                                                        <option value="forms">Forms only</option>
                                                    </select>
                                                    @break
                                                @case('meal_ticket')
                                                    <label class="form-check form-switch mb-1">
                                                        <input class="form-check-input" type="checkbox" wire:model.live="students.{{ $id }}.meal.attending">
                                                        <span class="form-check-label">Eating</span>
                                                    </label>
                                                    <div class="input-group input-group-sm" style="width: 9rem;">
                                                        <span class="input-group-text">+</span>
                                                        <input type="number" min="0" class="form-control" wire:model.live.debounce.400ms="students.{{ $id }}.meal.additional">
                                                        <span class="input-group-text">add'l</span>
                                                    </div>
                                                    @break
                                                @case('guests')
                                                    <input type="number" min="0" max="{{ (int) $addon->setting('max', 0) }}" class="form-control form-control-sm" style="width: 5rem;"
                                                           wire:model.live.debounce.400ms="students.{{ $id }}.guests">
                                                    @break
                                                @case('volunteer')
                                                    @foreach ($addon->setting('options', []) as $opt)
                                                        <label class="form-check mb-0 small">
                                                            <input class="form-check-input" type="checkbox" value="{{ $opt }}" wire:model.live="students.{{ $id }}.volunteer">
                                                            <span class="form-check-label">{{ $opt }}</span>
                                                        </label>
                                                    @endforeach
                                                    @break
                                            @endswitch
                                        </td>
                                    @endforeach
                                    <td class="text-end">${{ number_format($quoteLines[$id]['amount'] ?? 0, 2) }}</td>
                                    <td><button type="button" class="btn btn-sm btn-ghost-danger" wire:click="remove({{ $id }})">Remove</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="{{ $this->addons->where('type', '!=', 'registration_fee')->count() + 1 }}" class="text-end">Total to collect</th>
                                <th class="text-end fs-3">${{ number_format($this->quote['total'], 2) }}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @if ($this->event->addon('registration_fee')?->enabled && collect((array) $this->event->addon('registration_fee')->setting('discounts', []))->filter()->isNotEmpty())
                    <p class="text-secondary small mb-0">Family discounts apply to brothers and sisters in the same household, not to the batch as a whole.</p>
                @endif
            @endif
        </div>
    </div>

    @if (! empty($students))
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title mb-0">2 &middot; Payment</h3></div>
            <div class="card-body">
                @if ($this->canAssign)
                    <div class="mb-3">
                        <label class="form-label">Responsible for the money</label>
                        <select class="form-select" wire:model="responsibleId" style="max-width: 24rem;">
                            @foreach ($this->instructors as $i)
                                <option value="{{ $i->id }}">{{ $i->lastname }}, {{ $i->firstname }}@if($i->id === auth()->id()) (me)@endif</option>
                            @endforeach
                        </select>
                        <div class="form-hint">The instructor who collected the money and will turn it in.</div>
                    </div>
                @endif

                <div class="mb-3">
                    <label class="form-label">Note <span class="text-secondary">(optional)</span></label>
                    <input type="text" class="form-control" wire:model="note" maxlength="500" placeholder="e.g. 2 checks, rest cash">
                </div>

                <div class="alert alert-info">
                    Checks are payable to <b>{{ app(\App\Services\Stripe\StripeAccounts::class)->payee($this->event->stripe_account) }}</b>.
                </div>

                <label class="form-check">
                    <input class="form-check-input" type="checkbox" wire:model="confirmed">
                    <span class="form-check-label">
                        <b>${{ number_format($this->quote['total'], 2) }}</b> has been collected for these {{ count($students) }} student(s),
                        and {{ $this->canAssign ? "the instructor named above is" : "I am" }} responsible for turning it in.
                    </span>
                </label>
                @error('confirmed') <div class="text-danger small">{{ $message }}</div> @enderror

                <button type="button" class="btn btn-primary mt-3" wire:click="submit" wire:loading.attr="disabled">
                    Register {{ count($students) }} student(s)
                    <span wire:loading wire:target="submit" class="spinner-border spinner-border-sm ms-1"></span>
                </button>
            </div>
        </div>
    @endif
</div>
