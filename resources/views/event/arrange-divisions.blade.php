@extends('layouts.dashboard')

@section('subnav')
    @include('partials.event.subnav')
@endsection

@section('page-title')
    {{ $event->name }} — Divisions
@endsection

@section('content')
@php($discQuery = '?discipline='.$discipline)
@php($usesNewCards = $event->isCompetition())
<div class="container-xl" x-data="divisionBoard({
        slug: @js($slug),
        discipline: @js($discipline),
        combineSexes: @js($discipline === \App\Models\EventDivision::DISCIPLINE_FORMS),
        initial: @js($board),
        csrf: @js(csrf_token()),
        autoUrl: @js(route('event.arrange-divisions.auto', $slug).$discQuery),
        naturalUrl: @js(route('event.arrange-divisions.natural', $slug).$discQuery),
        saveUrl: @js(route('event.arrange-divisions.save', $slug).$discQuery),
        ranks: @js($ranks),
        published: @js($published),
        latestVersion: @js($latestVersion),
        versionsUrl: @js(route('event.arrange-divisions.versions', $slug).$discQuery),
        versionBase: @js(url('/event/'.$slug.'/arrange-divisions/versions')),
        unpublishUrl: @js(route('event.arrange-divisions.unpublish', $slug).$discQuery),
        printByDivisionUrl: @js($usesNewCards
            ? route('event.print-tournament-cards', $slug).'?by=division&variant='.$discipline
            : route('event.print-division-cards', $slug)),
     })" @keydown.window="handleKey($event)">

    {{-- Discipline toggle — Combined events arrange sparring and forms separately. --}}
    @if(count($disciplines) > 1)
        <div class="btn-group mb-3" role="group" aria-label="Division discipline">
            @foreach($disciplines as $d)
                <a href="{{ route('event.arrange-divisions', $slug) }}?discipline={{ $d }}"
                   class="btn {{ $d === $discipline ? 'btn-primary' : 'btn-outline-primary' }}">
                    {{ ucfirst($d) }}
                </a>
            @endforeach
        </div>
    @endif

    {{-- Combined tournaments: while sign-ups are open, competitors can still change
         sparring/forms/both, which shifts division membership. Hidden once the
         Registration Fee's "sign-ups close" date has passed. --}}
    @if($event->hasForms() && $event->hasSparring())
        @php($signupsClose = $event->addon('registration_fee')?->closes_at)
        @if(! $signupsClose || $signupsClose->isFuture())
            <div class="alert alert-warning">
                <strong>Registrations aren't locked yet.</strong>
                Competitors can still change whether they compete in sparring, forms, or both{{ $signupsClose ? ' until sign-ups close on '.$signupsClose->format('M j, Y \a\t g:i a') : '' }} —
                which changes who belongs in each division. You'll usually want to arrange and publish
                <em>after</em> that so the divisions match the final roster.
            </div>
        @endif
    @endif

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        {{-- Natural: every belt × sex × age group on its own, to combine by hand.
             Auto-combine: the older pass that merges small divisions for you. --}}
        <button type="button" class="btn btn-primary" @click="naturalArrange()" :disabled="loading"
                title="One division per belt, sex and age group — nothing combined">
            <span x-show="loading !== 'natural'">&#9638; Natural divisions</span>
            <span x-show="loading === 'natural'" x-cloak>Working…</span>
        </button>
        <button type="button" class="btn btn-outline-primary" @click="autoArrange()" :disabled="loading"
                title="Combine small divisions automatically (adjacent ages, then adjacent belts)">
            <span x-show="loading !== 'auto'">&#9889; Auto-combine</span>
            <span x-show="loading === 'auto'" x-cloak>Working…</span>
        </button>
        <button type="button" class="btn btn-success" @click="save()" :disabled="!dirty || saving" title="Save (Ctrl/Cmd+S)">
            <span x-text="saving ? 'Saving…' : 'Save'"></span>
        </button>
        <button type="button" class="btn btn-outline-secondary" @click="undo()" :disabled="!canUndo" title="Undo (Ctrl/Cmd+Z)">
            &#8630; Undo
        </button>
        <button type="button" class="btn btn-outline-secondary" @click="addDivision()">+ Add division</button>

        {{-- Star the current spot (saves first if there are unsaved changes). --}}
        <button type="button" class="btn btn-outline-warning" @click="toggleStarCurrent()"
                :title="starFilled ? 'Starred — click to unstar' : 'Star this arrangement (saves first)'">
            <span x-show="starFilled" x-cloak style="color:#f1c40f">&#9733;</span>
            <span x-show="!starFilled">&#9734;</span> Star
        </button>
        <button type="button" class="btn btn-outline-secondary" @click="openHistory()">&#128337; History</button>

        <button type="button" class="btn btn-outline-primary" @click="printByDivision()"
                :disabled="!published" :title="published ? 'Print cards grouped by division (published version)' : 'Publish a version first'">
            &#128424; Print Cards
        </button>
        <label class="form-check form-check-inline mb-0 small text-muted" title="Add a cover/separator sheet before each division">
            <input class="form-check-input" type="checkbox" x-model="printCovers"> covers
        </label>

        <div class="ms-auto d-flex align-items-center gap-2">
            <span class="badge" :class="published ? 'bg-green' : 'bg-secondary'"
                  x-text="published ? 'Published · ' + published.at : 'Unpublished'"></span>
            <span class="text-muted">
                <span x-text="totalMembers"></span> registrants ·
                <span x-text="visibleCount"></span><span x-show="filterOn" x-cloak>/<span x-text="divisionCount"></span></span> divisions
                <span x-show="dirty" class="badge bg-yellow text-dark ms-1" x-cloak>unsaved changes</span>
                <span x-show="unplacedCount" class="badge bg-orange ms-1" x-cloak
                      x-text="unplacedCount + ' not placed'" title="In no division, so no card in the by-division print — drag them into one"></span>
                <span x-show="staleCount" class="badge bg-red ms-1" x-cloak
                      x-text="staleCount + ' no longer in ' + discipline" title="Changed their participation since this was arranged — remove or keep by hand"></span>
            </span>
        </div>
    </div>

    {{-- Filter bar: the On/Off switch flips between the full and filtered view;
         the checkbox selections persist across the toggle. --}}
    <div class="card mb-3 shadow-sm" x-show="divisions.length > 0 && (divisionCount || unplacedCount)" style="position: sticky; top: 0; z-index: 20;">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <label class="form-check form-switch form-check-inline mb-0 fw-bold">
                    <input class="form-check-input" type="checkbox" x-model="filterOn">
                    <span class="form-check-label" x-text="filterOn ? 'Filter On' : 'Filter Off'"></span>
                </label>

                <div class="d-flex flex-wrap align-items-start" style="column-gap: 1.5rem; row-gap: 0.15rem;" :class="{ 'opacity-50': !filterOn }">
                    <div>
                        <div class="text-muted small">Sex
                            <a href="#" class="ms-1" @click.prevent="filter.sex = ['M','F']">all</a> /
                            <a href="#" @click.prevent="filter.sex = []">none</a>
                        </div>
                        <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" value="M" x-model="filter.sex"> Boys</label>
                        <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" value="F" x-model="filter.sex"> Girls</label>
                    </div>

                    <div>
                        <div class="text-muted small">Age group
                            <a href="#" class="ms-1" @click.prevent="filter.age = ['05','09','12','16','40']">all</a> /
                            <a href="#" @click.prevent="filter.age = []">none</a>
                        </div>
                        @foreach(['05'=>'Mini Pee-Wee', '09'=>'Pee-Wee', '12'=>'Junior', '16'=>'Adult', '40'=>'Executive'] as $code => $label)
                            <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" value="{{ $code }}" x-model="filter.age"> {{ $label }}</label>
                        @endforeach
                    </div>

                    <div>
                        <div class="text-muted small">Belt rank
                            <a href="#" class="ms-1" @click.prevent="filter.ranks = allRankIds()">all</a> /
                            <a href="#" @click.prevent="filter.ranks = []">none</a>
                        </div>
                        @foreach($ranks as $rank)
                            <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" value="{{ $rank->id }}" x-model="filter.ranks"> {{ $rank->rank }}</label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <template x-if="divisionCount === 0 && unplacedCount === 0">
        <div class="empty">
            <div class="empty-title">No divisions yet</div>
            <div class="empty-subtitle text-muted">Click <b>Natural divisions</b> to see every belt, sex and age group on its own, then drag divisions together to combine them. <b>Auto-combine</b> does a first pass of the combining for you.</div>
        </div>
    </template>

    {{-- Division cards. The container is sortable (reorder by the ⠿ handle);
         each card's list is sortable and shares the 'members' group so
         registrants drag between divisions. --}}
    <div class="row row-cards" x-ref="board" x-init="initBoard()">
        <template x-for="(div, di) in divisions" :key="div._key">
            <div class="col-md-6 col-lg-4 division-card" x-show="divisionVisible(div)">
                <div class="card h-100 d-flex flex-column"
                     :class="{ 'border-danger': !div.pending && div.members.length < 4, 'border-warning border-3': div.pending && div.members.length, 'border-primary border-3': mergeOverKey === div._key, 'opacity-50': mergeSourceKey === div._key }"
                     :style="div.pending && !div.members.length ? 'border-style: dashed' : ''"
                     @dragover.prevent="if (mergeSourceKey && mergeSourceKey !== div._key && !div.pending) mergeOverKey = div._key"
                     @dragleave="if (mergeOverKey === div._key) mergeOverKey = null"
                     @drop.prevent="mergeDrop(div)">
                    <div class="card-header d-flex align-items-center gap-2">
                        <span class="div-handle text-muted" style="cursor:grab" title="Drag to reorder" x-show="!div.pending">&#x283F;</span>
                        <span class="text-muted" draggable="true" style="cursor:grab" x-show="!div.pending"
                              title="Drag onto another division to merge them"
                              @dragstart="mergeDragStart(div, $event)" @dragend="mergeSourceKey = null; mergeOverKey = null">&#10697;</span>
                        <input type="text" class="form-control form-control-sm border-0 fw-bold p-1"
                               x-model="div.label" @input="dirty = true; div._custom = true" @focus="pushHistory()"
                               :readonly="div.pending">
                        <span class="badge ms-auto" :class="div.pending ? 'bg-orange' : (div.members.length < 4 ? 'bg-danger' : 'bg-blue')"
                              x-text="div.members.length"></span>
                        <button type="button" class="btn btn-sm btn-ghost-secondary p-1" title="Merge into next"
                                x-show="!div.pending && di < divisions.length - 1" @click="mergeIntoNext(di)">&#8681;</button>
                        <button type="button" class="btn btn-sm btn-ghost-danger p-1" title="Remove division (its people go to Not placed)"
                                x-show="!div.pending" @click="removeDivision(di)">&times;</button>
                    </div>
                    <div x-show="div.pending" class="card-body py-2 small text-muted border-bottom">
                        <span x-show="!div.members.length">Drag someone here, or click the &times; on their row, to take them out of a division.</span>
                        <span x-show="div.members.length">Not in any division — registered after the last save, missing the age,
                        sex or rank to place them, or taken out by hand. Drag each into a division. Anyone left here is
                        saved in no division and gets no card in the by-division print.</span>
                    </div>
                    <div class="list-group list-group-flush member-list flex-grow-1" x-init="makeMembersSortable($el, div)"
                         style="min-height: 3rem;">
                        <template x-for="m in div.members" :key="m.id">
                            <div class="list-group-item py-1 px-2" :data-reg="m.id" style="cursor:grab">
                                <div class="d-flex align-items-start">
                                    <div class="text-truncate flex-grow-1">
                                        <span class="fw-bold" x-text="m.name"></span>
                                        <span class="text-muted small" x-text="'· ' + (m.school || '')"></span>
                                        <div class="text-muted small">
                                            <span x-text="m.rank"></span> ·
                                            age <span x-text="m.age ?? '?'"></span> ·
                                            <span x-text="(m.weight ?? '?') + ' lb'"></span>
                                            <span x-show="m.sex" x-text="'· ' + m.sex"></span>
                                        </div>
                                        <span x-show="m.stale" class="badge bg-red-lt mt-1"
                                              x-text="'No longer competing in ' + discipline"></span>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-ghost-secondary p-1 ms-1 no-drag"
                                            x-show="!div.pending" title="Take out of this division (to Not placed)"
                                            @click.stop="unplace(div, m)">&times;</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Version history --}}
    <div x-show="showHistory" x-cloak @keydown.escape.window="showHistory = false" @click.self="showHistory = false"
         style="position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 1050; display: flex; align-items: center; justify-content: center;">
        <div class="card" style="width: 34rem; max-width: 92vw; max-height: 82vh; overflow: auto;">
            <div class="card-header">
                <h3 class="card-title mb-0">Version history</h3>
                <button type="button" class="btn-close ms-auto" @click="showHistory = false"></button>
            </div>
            <div class="list-group list-group-flush">
                <template x-for="v in versions" :key="v.id">
                    <div class="list-group-item">
                        <div class="d-flex align-items-center gap-2">
                            <a href="#" @click.prevent="starVersion(v)" :title="v.starred ? 'Starred' : 'Star this version'"
                               :style="v.starred ? 'color:#f1c40f' : 'color:#adb5bd'" style="font-size:1.3rem; text-decoration:none;">
                                <span x-text="v.starred ? '★' : '☆'"></span>
                            </a>
                            <div>
                                <div class="fw-bold">
                                    <span x-text="new Date(v.created_at).toLocaleString()"></span>
                                    <span x-show="v.published" x-cloak class="badge bg-green ms-1">Published</span>
                                </div>
                                <div class="text-muted small">
                                    <span x-text="v.by"></span> · <span x-text="v.divisions"></span> divisions · <span x-text="v.members"></span> registrants
                                </div>
                            </div>
                            <div class="ms-auto d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-success" x-show="!v.published"
                                        @click="publishVersion(v.id)" title="Make this the published arrangement">Publish</button>
                                <button type="button" class="btn btn-sm btn-outline-primary"
                                        @click="restoreVersion(v.id)" title="Load this version onto the board">Restore</button>
                            </div>
                        </div>
                        <input type="text" class="form-control form-control-sm mt-2" placeholder="Add a note…"
                               x-model="v.note" @change="saveNote(v)" @keydown.enter="$event.target.blur()">
                    </div>
                </template>
                <template x-if="!versions.length">
                    <div class="list-group-item text-muted">No saved versions yet — Save to create one.</div>
                </template>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
    function divisionBoard(config) {
        return {
            slug: config.slug,
            discipline: config.discipline || 'sparring',
            combineSexes: !!config.combineSexes,
            csrf: config.csrf,
            autoUrl: config.autoUrl,
            naturalUrl: config.naturalUrl,
            saveUrl: config.saveUrl,
            versionsUrl: config.versionsUrl,
            versionBase: config.versionBase,
            unpublishUrl: config.unpublishUrl,
            printByDivisionUrl: config.printByDivisionUrl,
            printCovers: true,
            published: config.published || null,
            currentVersionId: config.latestVersion ? config.latestVersion.id : null,
            currentStarred: config.latestVersion ? config.latestVersion.starred : false,
            showHistory: false,
            versions: [],
            divisions: [],
            ranks: config.ranks || [],
            loading: false,
            saving: false,
            dirty: false,
            mergeSourceKey: null,
            mergeOverKey: null,
            history: [],
            _maxHistory: 40,
            _keySeq: 0,

            // Filter state — the On/Off switch only toggles whether these apply;
            // the selections persist either way. Everything starts checked.
            filterOn: false,
            filter: { sex: ['M', 'F'], age: ['05', '09', '12', '16', '40'], ranks: [] },

            init() {
                this.filter.ranks = this.allRankIds();
                this.setDivisions(config.initial || []);

                // The board is client-side until saved, and every way off this page
                // (the Sparring/Forms toggle, subnav, back, closing the tab) is a
                // full navigation — warn before unsaved changes are thrown away.
                window.addEventListener('beforeunload', (e) => {
                    if (!this.dirty) return;
                    e.preventDefault();
                    e.returnValue = '';
                });
            },

            get totalMembers() {
                return this.divisions.reduce((n, d) => n + d.members.length, 0);
            },

            // Real divisions — everything but the not-placed group.
            get divisionCount() {
                return this.divisions.filter((d) => !d.pending).length;
            },

            get unplacedCount() {
                return this.divisions.filter((d) => d.pending).reduce((n, d) => n + d.members.length, 0);
            },

            get staleCount() {
                return this.divisions.filter((d) => !d.pending)
                    .reduce((n, d) => n + d.members.filter((m) => m.stale).length, 0);
            },

            get visibleCount() {
                return this.divisions.filter((d) => this.divisionVisible(d)).length;
            },

            allRankIds() {
                return this.ranks.map((r) => String(r.id));
            },

            ageGroupOf(age) {
                if (age == null) return null;
                if (age <= 8) return '05';
                if (age <= 11) return '09';
                if (age <= 15) return '12';
                if (age <= 39) return '16';
                return '40';
            },

            // A division shows when it holds at least one member matching every
            // active filter dimension. Filter Off shows everything.
            divisionVisible(div) {
                // The not-placed group is always on a non-empty board, as a drop
                // target. Showing it only mid-drag shifted every card one slot as
                // the drag began, so drops landed on the wrong division.
                if (div.pending) return this.divisionCount > 0 || div.members.length > 0;
                if (!this.filterOn) return true;
                return div.members.some((m) =>
                    this.filter.sex.includes(m.sex) &&
                    this.filter.age.includes(this.ageGroupOf(m.age)) &&
                    this.filter.ranks.includes(String(m.rank_id))
                );
            },

            // --- Auto label from members (mirrors the server DivisionArranger) --
            _ageBounds: { '05': [5, 8], '09': [9, 11], '12': [12, 15], '16': [16, 39], '40': [40, 120] },
            _ageOrder: ['05', '09', '12', '16', '40'],
            _beltOrder: [6, 5, 4, 3, 2, 1, -2, -3, -4, -5, -6, -7],

            _ordinal(n) {
                const s = ['th', 'st', 'nd', 'rd'], v = n % 100;
                return n + (s[(v - 20) % 10] || s[v] || s[0]);
            },
            _beltFamily(rank) {
                if (rank >= 1) return 'Black';
                return { '-2': 'Brown', '-3': 'Purple', '-4': 'Green', '-5': 'Yellow' }[rank] || 'White';
            },
            _beltLabel(ranks) {
                ranks = [...new Set(ranks)].sort((a, b) => this._beltOrder.indexOf(a) - this._beltOrder.indexOf(b));
                const high = ranks[0], low = ranks[ranks.length - 1];
                if (low >= 1) {
                    return high === low ? this._ordinal(high) + ' Degree Black'
                        : this._ordinal(high) + '–' + this._ordinal(low) + ' Degree Black';
                }
                const hi = this._beltFamily(high), lo = this._beltFamily(low);
                return hi === lo ? hi + ' Belt' : hi + '–' + lo;
            },
            _ageLabel(groups) {
                groups = this._ageOrder.filter((a) => groups.includes(a));
                if (!groups.length) return '';
                const lo = this._ageBounds[groups[0]][0], hi = this._ageBounds[groups[groups.length - 1]][1];
                return hi >= 120 ? lo + '+' : lo + '-' + hi;
            },
            _sexWord(members, groups) {
                const hasM = members.some((m) => m.sex === 'M'), hasF = members.some((m) => m.sex === 'F');
                const youth = !groups.some((a) => a === '16' || a === '40');
                if (hasM && hasF) return youth ? 'Boys & Girls' : 'Men & Women';
                if (hasF) return youth ? 'Girls' : "Women's";
                if (hasM) return youth ? 'Boys' : "Men's";
                return '';
            },
            computeLabel(members) {
                if (!members.length) return '';
                const groups = [...new Set(members.map((m) => this.ageGroupOf(m.age)).filter(Boolean))];
                const ranks = members.map((m) => m.rank_id).filter((r) => r != null);
                // Forms divisions that mix male/female are labelled by age + belt
                // only; a single-sex one (e.g. straight from Natural) keeps its word.
                const mixed = members.some((m) => m.sex === 'M') && members.some((m) => m.sex === 'F');
                const sexWord = (this.combineSexes && mixed) ? '' : this._sexWord(members, groups);
                return [sexWord, this._ageLabel(groups), ranks.length ? this._beltLabel(ranks) : '']
                    .filter(Boolean).join(' ');
            },
            // Recompute a division's name from its members, unless it was renamed by hand.
            relabel(div) {
                if (div && !div._custom && div.members.length) {
                    div.label = this.computeLabel(div.members);
                }
            },

            // Give each division a stable client key so Alpine's x-for keeps
            // DOM nodes aligned as arrays change.
            setDivisions(list) {
                const divisions = list.map((d) => ({
                    _key: ++this._keySeq,
                    id: d.id ?? null,
                    label: d.label ?? '',
                    members: (d.members || []).slice(),
                    // The not-yet-placed group is never saved and keeps its name.
                    pending: !!d.pending,
                    _custom: !!d.pending,
                }));
                // Always keep exactly one not-placed group, first, so there is
                // somewhere to take people out to.
                const pending = divisions.filter((d) => d.pending);
                const rest = divisions.filter((d) => !d.pending);
                const group = pending[0] ?? this.newPendingGroup();
                pending.slice(1).forEach((p) => group.members.push(...p.members));
                this.divisions = [group, ...rest];
            },

            newPendingGroup() {
                return { _key: ++this._keySeq, id: null, label: @js(\App\Services\EventDivisionService::UNPLACED_LABEL),
                         members: [], pending: true, _custom: true };
            },

            get pendingGroup() {
                return this.divisions.find((d) => d.pending);
            },

            // Take one person out of a division, back to Not placed.
            unplace(div, m) {
                const i = div.members.indexOf(m);
                if (i < 0) return;
                this.pushHistory();
                div.members.splice(i, 1);
                this.pendingGroup.members.push(m);
                this.relabel(div);
                this.dirty = true;
            },

            // --- Undo ---------------------------------------------------------
            // Snapshot the board before every mutation; undo restores the last one.
            snapshot() {
                return this.divisions.map((d) => ({ id: d.id, label: d.label, pending: d.pending, members: d.members.slice() }));
            },
            pushHistory() {
                this.history.push(this.snapshot());
                if (this.history.length > this._maxHistory) this.history.shift();
            },
            get canUndo() {
                return this.history.length > 0;
            },
            undo() {
                if (! this.history.length) return;
                this.setDivisions(this.history.pop());
                this.dirty = true;
            },

            handleKey(e) {
                if (! (e.metaKey || e.ctrlKey)) return;
                const k = e.key.toLowerCase();
                if (k === 's') {
                    e.preventDefault();
                    if (this.dirty && ! this.saving) this.save();
                } else if (k === 'z') {
                    // Let native text-undo work while editing a label.
                    if (['INPUT', 'TEXTAREA'].includes(e.target.tagName)) return;
                    e.preventDefault();
                    this.undo();
                }
            },

            // --- Star / publish / history ------------------------------------
            // Star the current saved arrangement; when the board is dirty the
            // filled state clears because the unsaved edits aren't a version yet.
            get starFilled() {
                return ! this.dirty && this.currentStarred && this.currentVersionId != null;
            },
            async toggleStarCurrent() {
                if (this.dirty) await this.save();
                if (this.currentVersionId == null) return;
                const res = await fetch(this.versionBase + '/' + this.currentVersionId + '/star',
                    { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' } });
                this.currentStarred = (await res.json()).starred;
            },
            async starVersion(v) {
                const res = await fetch(this.versionBase + '/' + v.id + '/star',
                    { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' } });
                v.starred = (await res.json()).starred;
                if (v.id === this.currentVersionId) this.currentStarred = v.starred;
            },
            async saveNote(v) {
                await fetch(this.versionBase + '/' + v.id + '/note', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ note: v.note }),
                });
            },
            async publishVersion(id) {
                const res = await fetch(this.versionBase + '/' + id + '/publish',
                    { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' } });
                this.published = (await res.json()).published;
                this.versions.forEach((v) => v.published = v.id === id);
            },
            async unpublish() {
                await fetch(this.unpublishUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' } });
                this.published = null;
                this.versions.forEach((v) => v.published = false);
            },

            printByDivision() {
                if (! this.published) return;
                const n = this.unplacedCount;
                if (n && !confirm(n + (n === 1 ? ' person isn\'t' : ' people aren\'t') + ' in a division and won\'t get a card. Print anyway?')) return;
                const sep = this.printByDivisionUrl.includes('?') ? '&' : '?';
                window.open(this.printByDivisionUrl + (this.printCovers ? sep + 'covers=1' : ''), '_blank');
            },

            async openHistory() {
                const res = await fetch(this.versionsUrl, { headers: { 'Accept': 'application/json' } });
                this.versions = (await res.json()).versions;
                this.showHistory = true;
            },

            async restoreVersion(id) {
                const res = await fetch(this.versionBase + '/' + id, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                this.pushHistory();
                this.setDivisions(data.divisions);
                this.dirty = true;
                this.showHistory = false;
            },

            initBoard() {
                // Reorder division cards by their handle.
                if (this.$refs.board._sortable) this.$refs.board._sortable.destroy();
                this.$refs.board._sortable = new Sortable(this.$refs.board, {
                    group: 'divisions',
                    handle: '.div-handle',
                    draggable: '.division-card',
                    animation: 150,
                    onEnd: (evt) => {
                        if (evt.oldIndex !== evt.newIndex) {
                            this.pushHistory();
                            const [moved] = this.divisions.splice(evt.oldIndex, 1);
                            this.divisions.splice(evt.newIndex, 0, moved);
                            this.dirty = true;
                        }
                        // Rebuild every card from data with fresh keys, so a card can
                        // never be emptied or lost, whatever Sortable left in the DOM.
                        this.divisions = this.divisions.map((d) => ({ ...d, _key: ++this._keySeq }));
                    },
                });
            },

            makeMembersSortable(el, div) {
                el._division = div;
                // Drop any prior instance so re-inits don't leak Sortables.
                if (el._sortable) el._sortable.destroy();
                el._sortable = new Sortable(el, {
                    group: 'members',
                    animation: 150,
                    ghostClass: 'bg-primary-lt',
                    // The per-person × is a button, not a drag start.
                    filter: '.no-drag',
                    preventOnFilter: false,
                    onEnd: (evt) => {
                        const fromDiv = evt.from?._division;
                        const toDiv = evt.to?._division;

                        // Undo Sortable's DOM move: put the row back where it
                        // started, then change the data and let Alpine move it.
                        // (Removing it instead loses the row whenever the data
                        // doesn't change — e.g. a drop back into its own spot —
                        // because Alpine still thinks it rendered that row.)
                        if (evt.item && evt.from) {
                            const rows = [...evt.from.children]
                                .filter((c) => c !== evt.item && c.tagName !== 'TEMPLATE');
                            evt.from.insertBefore(evt.item, rows[evt.oldIndex] ?? null);
                        }

                        if (fromDiv && toDiv) {
                            const realMove = evt.from !== evt.to || evt.oldIndex !== evt.newIndex;
                            if (realMove) {
                                this.pushHistory();
                                const oldIdx = Math.min(Math.max(evt.oldIndex ?? 0, 0), fromDiv.members.length - 1);
                                const [moved] = fromDiv.members.splice(oldIdx, 1);
                                if (moved) {
                                    const newIdx = Math.min(Math.max(evt.newIndex ?? toDiv.members.length, 0), toDiv.members.length);
                                    toDiv.members.splice(newIdx, 0, moved);
                                    this.dirty = true;
                                    this.relabel(fromDiv);
                                    this.relabel(toDiv);
                                }
                            }
                        }

                    },
                });
            },

            addDivision() {
                this.pushHistory();
                this.divisions.push({ _key: ++this._keySeq, id: null, label: 'New Division', members: [], _custom: false });
                this.dirty = true;
            },

            removeDivision(di) {
                if (this.divisions[di].pending) return;
                this.pushHistory();
                // Members of a removed division go to Not placed (kept, not lost).
                const removed = this.divisions.splice(di, 1)[0];
                this.pendingGroup.members.push(...removed.members);
                this.dirty = true;
            },

            mergeIntoNext(di) {
                if (di >= this.divisions.length - 1 || this.divisions[di].pending) return;
                this.pushHistory();
                const next = this.divisions[di + 1];
                next.members.unshift(...this.divisions[di].members);
                this.divisions.splice(di, 1);
                this.relabel(next);
                this.dirty = true;
            },

            // Drag one division card onto another to merge the two.
            mergeDragStart(div, e) {
                this.mergeSourceKey = div._key;
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', String(div._key)); // Firefox needs data set
                // Drag the whole title bar, not just the small handle glyph, so it
                // is obvious which division is in hand. Keep the grab point under
                // the cursor.
                const header = e.target.closest('.card-header');
                if (header && e.dataTransfer.setDragImage) {
                    const r = header.getBoundingClientRect();
                    e.dataTransfer.setDragImage(header, e.clientX - r.left, e.clientY - r.top);
                }
            },

            mergeDrop(targetDiv) {
                const srcKey = this.mergeSourceKey;
                this.mergeSourceKey = null;
                this.mergeOverKey = null;
                if (srcKey == null || srcKey === targetDiv._key || targetDiv.pending) return;

                const srcIdx = this.divisions.findIndex((d) => d._key === srcKey);
                if (srcIdx === -1) return;

                this.pushHistory();
                targetDiv.members.push(...this.divisions[srcIdx].members);
                this.divisions.splice(srcIdx, 1);
                this.relabel(targetDiv);
                this.dirty = true;
            },

            autoArrange() {
                return this.loadArrangement(this.autoUrl, 'auto',
                    'Auto-combine discards your unsaved changes and rebuilds from scratch. Continue?');
            },

            naturalArrange() {
                return this.loadArrangement(this.naturalUrl, 'natural',
                    'Natural divisions discards your unsaved changes and rebuilds from scratch. Continue?');
            },

            // Fetch a fresh (unsaved) arrangement from the server onto the board.
            async loadArrangement(url, kind, confirmText) {
                if (this.dirty && !confirm(confirmText)) return;
                this.loading = kind;
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                    });
                    const data = await res.json();
                    this.pushHistory();
                    this.setDivisions(data.divisions);
                    this.dirty = true;
                } finally {
                    this.loading = false;
                }
            },

            async save() {
                this.saving = true;
                try {
                    // The not-yet-placed group stays unplaced: it is not a division.
                    const kept = this.divisions.filter((d) => !d.pending);
                    const payload = kept.map((d) => ({
                        id: d.id,
                        label: d.label,
                        members: d.members.map((m) => m.id),
                    }));
                    // Preserve hand-renamed flags across the reload (order is kept).
                    const custom = kept.map((d) => d._custom);
                    const res = await fetch(this.saveUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ divisions: payload }),
                    });
                    const data = await res.json();
                    if (data.ok) {
                        this.setDivisions(data.board);
                        // Line the flags up against real divisions only — the reloaded
                        // board may lead with a not-yet-placed group.
                        this.divisions.filter((d) => !d.pending).forEach((d, i) => { d._custom = custom[i] ?? false; });
                        this.dirty = false;
                        // This save created a fresh (unstarred) version — track it.
                        this.currentVersionId = data.version ? data.version.id : this.currentVersionId;
                        this.currentStarred = data.version ? data.version.starred : false;
                    }
                } finally {
                    this.saving = false;
                }
            },
        };
    }
</script>
@endpush
