# Tournament day — design

Running a tournament from the portal instead of paper cards: assigning
divisions to rings, knowing who is busy where, sparring brackets, and
organising the trophies. All of it must keep working with no internet, which
is what shapes the architecture. Written 2026-11-09, after the Fall St. Louis
Gateway Championships (2026-11-07), before any code exists.

This is the handoff for implementation. Decisions are stated as decisions;
the few things still open are listed at the end. Read `CLAUDE.md` first for
the portal's conventions, then this.

## What went wrong on paper

The portal printed the cards by division, they were cut into envelopes, and
six tables each fetched their next envelope from the head table. Ring 1 ran
forms (black belts first, so they could be released to judge elsewhere); the
other rings ran sparring. Again and again a sparring envelope went out
containing people who were still doing forms at Ring 1. Awards were called out
by name from the finished envelopes and became a crowd.

The portal already knows everything the envelopes knew. Saturday had 118
registrants in 21 sparring and 18 forms divisions, every one of them with
height and weight on file. Seven divisions were scored in the app; the rest
were scored on paper. Adoption, not data, is the risk.

## Goals and non-goals

Goals, in the order they pay off:

1. The head table can see which divisions are ready (everyone checked in,
   nobody busy at another ring) and assign them to rings. This alone fixes
   Saturday's problem and needs none of the rest.
2. The whole thing runs with no internet, on a box carried to the venue, and
   production catches up when a link exists.
3. Sparring runs from a generated bracket with the association's pairing
   rules, overridable by hand, usable on a phone.
4. Trophies are organised from a table the app compiles, not from envelopes.

Non-goals:

- **No money at the venue.** Ever. Not cash, not card. See "Provisional
  competitors" for how a walk-up competes anyway.
- No points or scores per match. The only input is who won.
- No public results page served from the venue. The public is on the internet;
  production serves them, late if it must.
- Forms stays on the existing scoring screen. Forms is judged, not fought;
  there is no bracket. Rings and busy-tracking apply to forms, brackets do not.

## Architecture: one codebase, two installations

The venue box is a **second installation of this same portal**, started empty
for each event, loaded with one event's data in the morning, and the **only
writer** for tournament-day tables that day. Production is the only writer for
everything else. The two exchange a **command log**, not rows.

Why not point the ring tablets at chungdo.org and fall back to the laptop when
the internet drops: the state would split in two at the worst moment.
Why not a browser-only offline app: six devices each holding local state is a
distributed-merge problem; one box on a LAN is not.
Why the whole portal rather than a scoring subset: same code, same tests, same
logins, nothing to keep in step.

```
   production (kronos)                      venue box (Linux, Docker)
   ───────────────────                      ─────────────────────────
   members, money, Zulip,                   check-in, rings, brackets,
   public results page                      scoring, trophies, awards
          │                                          │
          │  GET  /api/tournament/{event}/pack       │  morning: tournament:pull
          │ ────────────────────────────────────────►│
          │  GET  /api/tournament/{event}/commands   │  every 30 s while online
          │ ────────────────────────────────────────►│  (late registrations,
          │                                          │   member edits)
          │  POST /api/tournament/{event}/commands   │  every 30 s while online
          │ ◄────────────────────────────────────────│  (check-ins, results,
          │                                          │   bracket history, …)
   applies through its own services,                 applies through its own
   fires DivisionCompleted → Zulip,                   services; fires nothing
   results page                                       outbound
```

### Venue mode

`TOURNAMENT_VENUE=true` (`config('tournament.venue')`). It is enforced in
code, in a service provider, with tests — not only by what the `.env` omits.
A production copy with production's `.env` would mail confirmation emails from
a gym, run the Zulip sync (which *removes* people), and hold the **live**
Stripe key that `CLAUDE.md` warns about. In venue mode:

- `StripeAccounts` throws on any credential lookup. Every checkout door 403s.
- The mail driver is forced to `log`. SendPortal routes and jobs are off.
- `zulip:sync` (and any command that writes to Zulip) refuses to run.
  `ZULIP_RESULTS_CHANNEL` is unset, so results post nothing (the existing
  guard), but the listener is also not registered.
- The store, public registration, and account administration are read-only.
- `QUEUE_CONNECTION=sync`. Nothing at the venue is worth a worker.
- The scheduler runs `tournament:push` and `tournament:pull` only.

On production, an event is **at venue** from the morning pull until it is
closed (`events.venue_mode_at`, `venue_closed_at`). While at venue,
production's check-in, scoring, rings and trophy pages for that event are
read-only and show a banner naming the venue URL, so nobody checks a child in
on the wrong system. Everything else on production is untouched.

### The command log

Rows stay the source of truth on each side. A `sync_commands` row is how a
change travels. This is **not** event sourcing: nothing is rebuilt from the
log, and the portal's models do not change shape.

Why commands rather than row upserts: a parent fixes a phone number at home
while the head table corrects the child's height at the venue. A row overwrite
loses one of them. "Set height from 50 to 52" applies cleanly, and if
production's height is no longer 50 it still applies and writes a conflict row
for someone to look at. **Nothing blocks on the day.**

```
sync_commands
  id, uuid, origin ('venue'|'portal'), sequence (per origin, gapless),
  type, payload (json), created_by_user_id, created_at,
  applied_at, failed_at, error

sync_sources
  origin, last_applied_sequence          -- the watermark
sync_conflicts
  sync_command_id, model, model_uuid, field, expected, found, resolved_at
```

Rules:

- **Journaled in the same transaction as the change.** `App\Services\
  Tournament\Sync\Journal::record($type, $payload)` is called inside the
  service method that makes the change, so a command exists iff the change
  committed. Services on *both* installs journal; config decides which types
  are exported in each direction (see the catalogue).
- **Applied in origin order, in a transaction each, idempotent by uuid.**
  `CommandApplier` has one handler per type and calls the portal's own
  service methods, never raw inserts: a `division.saved` arriving at
  production goes through `DivisionResults::record()` and fires the same
  events a scorer would.
- **A failure stops that source's queue.** Later commands may depend on the
  failed one. It is recorded, surfaced on the sync status page, and a person
  decides. Skipping is never automatic.
- **Payloads name things by uuid, never by id.** Both installs assign their
  own autoincrement ids. `users`, `event_registrations`,
  `event_division_versions`, `division_results` and `participation_trophies`
  get a `uuid` column (new tables have one from the start). The pack import
  may keep production's ids, since the venue DB is empty, but no command relies
  on that. In particular, a version's `data[].members` is registration ids on
  disk; in a `divisions.published` payload it is registration uuids and the
  applier maps them.
- **Field commands carry the prior value.** That is what makes a conflict
  detectable without making it blocking. Policy for user fields: **the venue
  wins** (it measured the child that morning) and the conflict is logged.
- **Transport is plain HTTPS with a bearer token** (`TOURNAMENT_SYNC_TOKEN`,
  one per venue box, checked by middleware; not a user token). The venue
  always initiates. Production never calls the venue.

#### Command catalogue

| type | direction | payload (by uuid) | applied through |
| --- | --- | --- | --- |
| `checkin.recorded` / `checkin.cleared` | venue → portal | registration, at, by | a small `CheckInRecorder` service — today `CheckInDetails` sets `checkin` on the model directly, which is where the journal call must go |
| `division.saved` | venue → portal | version, position, places, finalists, by | `DivisionResults::record()` |
| `division.reopened` | venue → portal | result | `DivisionResults::reopen()` |
| `divisions.published` | venue → portal | discipline, divisions `[{label, members: [reg uuid]}]`, discard_scoring | `EventDivisionService::save()` + `publish()` |
| `user.updated` | both | user, `{field: {from, to}}` | field-level update, conflict check |
| `user.created` | venue → portal | provisional person (see below) | `User` create, flagged provisional |
| `registration.created` | both | registration, user, event, participation | portal → venue: a late sign-up to place; venue → portal: provisional, unpaid |
| `registration.withdrawn` | portal → venue | registration | drop from division / bracket as a scratch |
| `ring.assigned` / `ring.released` | venue → portal | ring, version, position, at, by | history only |
| `bracket.generated` / `bracket.overridden` | venue → portal | bracket snapshot | history only (read-only view on production) |
| `match.decided` / `match.undone` | venue → portal | match, winner, outcome, at, by | history only |
| `award.handed` / `award.unhanded` | venue → portal | registration, kind, at, by | the award tables |

Production exports `portal → venue` types only for events at venue, and only
for people registered for them. The venue exports everything it journals.

Do **not** journal fine-grained operational taps (timer started, a tab
changed). Log what has to reach the other side or belongs in the record.

### Late registrations

**Registered on production during the day** (online, or an instructor's cash
registration): production journals `registration.created`; the venue pulls it
while it has a link and lists the person under *Arrived, not placed*. The head
table puts them in a division, which is a new version saved and published at
the venue and pushed back as `divisions.published`. `DivisionResults::
affectedBy()` already means only the changed division is touched; a bracket
not yet started regenerates, a started one needs the same explicit discard as
scoring does today.

**Walked up with no link:** a **provisional competitor**. The head table
enters name, school, rank, birth date, height, weight and a guardian name. That
is a locally created `User` with no login and `provisional_at` set, plus an
unpaid `EventRegistration` flagged the same way, journaled as `user.created`
and `registration.created`. `division_placements` already stores name, school
and rank and allows a null `user_id`, so results for this person are safe from
the first match. **After the event, on production,** an admin merges the
provisional user into an existing account or finalises it, and collects the
fee through the normal cash flow. A provisional user cannot log in, appears on
no public page by name until merged or finalised, and is listed on the admin
side until dealt with.

### Hot standby

A second box is just another sync target: it takes the same pack in the
morning and pulls the venue's command stream over the LAN every few seconds.
Promoting it is changing the router's DNS entry to its address. Losing the
primary then costs seconds, not a dump interval. (A periodic dump to a USB
stick is the cheap version if there is only one box.)

## The day, step by step

**Once, before the first event.** Build the box (below). Issue the
certificate. Run the full rehearsal at home with the last event's data: pull,
check in, assign, score, push, close, wipe.

**The week of.** Publish both disciplines on production and treat them as
final: the pack is a snapshot. Grant ring staff their permissions on
production; logins travel in the pack, so anyone who will check in, run a ring
or score must already hold `event.checkIn` or `event.recordResults` there.

**Morning.**

1. Power the router and box. A phone on the router's SSID opens the venue URL.
2. `tournament:pull {event}` on the venue box. It fetches the pack: the event,
   both published versions and their `event_divisions`, registrations with
   check-in state, the registrants and their guardians (for the sibling rule),
   schools, ranks, the Spatie role/permission tables and committee grants, and
   the staff accounts **with password hashes** so people sign in as usual.
   Nothing about payments; no member not connected to this event.
   The fallback for a venue with no internet at all is the same pack exported
   to a file the night before (`tournament:export`) and loaded from a stick.
3. The pull marks the event *at venue* on production.
4. Pulling again is safe until the day starts. The first check-in or ring
   assignment on the venue box locks the pack; after that only commands flow.

**During.** Every device talks only to the venue box. `tournament:push` runs
every 30 s and sends the outbox; `tournament:pull` fetches late registrations
and member edits. Offline, both fail quietly and retry. The head-table page
shows *last synced 14:02 · 6 commands waiting*. Evening finals run on the same
box.

**After.**

1. The sync page reads zero pending and production shows the same division
   count. The conflict list is reviewed.
2. *Close venue mode* on production, which reopens its own pages for
   corrections and lists any provisional competitors to resolve.
3. `tournament:export` to a file as the archive, then `tournament:wipe`. The
   box held names, birth dates and guardians; it does not keep them in a bag.

## Phase 1 — rings and busy-tracking

```
event_rings            id, uuid, event_id, number, name
ring_assignments       id, uuid, event_id, event_ring_id, event_division_version_id,
                       position, discipline, assigned_at, assigned_by_user_id,
                       released_at, released_by_user_id, finished_at
ring_performances      id, ring_assignment_id, event_registration_id, performed_at
                       (optional per-competitor "has performed" tick, forms rings)
```

A competitor is **busy** while any member division of theirs has an
assignment with neither `released_at` nor `finished_at`. A division is
**ready** when every member is checked in and none is busy. `finished_at` is
set by `DivisionResults::record()` (completed *or* awaiting finals) for the
assignment on that version and position; `released_at` is the head table
taking it back. A forms ring can tick competitors as performed, which is how
Master Blevins releases black belts before his division is saved; a performed
competitor does not count as busy.

Pages:

- **Head table** `/event/{slug}/rings`: rings across the top with their
  current division; below, the unassigned divisions per discipline, sorted
  ready-first, each showing checked-in count and *who* is busy *where*
  ("Kim, J — forms, Ring 1"). Assign is a tap. Release is a tap. Polls every
  5–10 s; on a LAN that is plenty and needs no websockets.
- **Ring** `/event/{slug}/ring/{number}`: the current division's competitors
  with school, rank, height, weight and check-in state; the performed tick;
  a link into the existing scoring screen for exactly this division. In
  Phase 3 the bracket lives here.
- The event subnav gains Rings under Results.

Permissions: assigning and releasing need `event.manage`; the ring page and
the performed tick need `event.recordResults` (so the `event.scorer` role
covers a ring tablet). Add nothing to the coordinator allowlist unless a new
permission is created.

Done when: a rehearsal on last Saturday's event shows the Ring 1 forms
division making its members busy, and the sparring division containing them
showing who is at Ring 1 until Ring 1 saves or ticks them.

## Phase 2 — venue mode and sync

Everything under "Architecture". Build order within the phase:

1. `tournament.venue` config and the kill switches, with a test per switch.
2. `uuid` columns and backfill on the five existing tables.
3. `Journal`, `sync_commands`, `sync_sources`, `sync_conflicts`;
   journaling added to check-in, `DivisionResults::record()/reopen()`,
   `EventDivisionService::save()/publish()`, user field updates, registration
   creation and withdrawal, ring assignment.
4. `CommandApplier` and the handlers, tested by journaling against one event
   and applying the payloads to a second event in the same test database.
   Idempotency, ordering, stop-on-failure, conflict logging.
5. The pack: `tournament:export`, the `/pack` endpoint, `tournament:pull`
   (import, lock, at-venue flag), production's read-only banner.
6. `tournament:push`, the `/commands` endpoints, the scheduler entries,
   the sync status page on the venue (`/event/{slug}/sync`).
7. Provisional competitors and the post-event resolve page on production.
8. `tournament:wipe`, and the close-venue-mode action on production.

Done when: the home rehearsal round-trips a full event — including a late
registration placed at the venue and a provisional walk-up — and production
shows the same results, Zulip posts included, with the venue box wiped.

## Phase 3 — brackets

Sparring only. A bracket belongs to a published version and position, like a
result does, and follows the same rules when versions change.

```
brackets         id, uuid, event_id, event_division_version_id, position,
                 format ('single'|'round_robin'), finals (bool),
                 status ('draft'|'running'|'complete'), generated_at, data (json seed)
bracket_matches  id, uuid, bracket_id, round, order, label,
                 a_registration_id, b_registration_id,
                 feeds_match_id, feeds_slot ('a'|'b'),
                 winner_registration_id, outcome ('win'|'forfeit'|'scratch'),
                 is_third_place, is_final, decided_at, decided_by_user_id
```

Rules:

- **Format by size.** 3 competitors: round robin (every pair fights once).
  4 or more: single elimination to the next power of two, with byes. 2: one
  match. 1: a walkover, 1st.
- **3rd place is a match, fought before the final.** Its loser is 4th. In a
  division marked for evening finals the 3rd-place match still runs in the
  day; only the final waits.
- **Round robin ties are decided by hand.** The bracket shows standings
  (wins); the places are entered on the scoring screen. A 1-1-1 is flagged,
  not resolved.
- **Byes go to the top of the bracket**; who gets them is part of the
  placement search below. Nobody gets two.
- **Finals divisions.** `finals` is set by the head table when assigning or
  generating (there is no flag on the division today; the scorer sets it at
  save time). When the bracket reaches a withheld final, it proposes the
  existing awaiting-finals save: two finalists, 3rd, 4th. At finals the last
  match is decided on the same bracket and the completing save follows.
- **Outcomes.** *Win*. *Forfeit*: the other advances; the forfeiting
  competitor keeps their place in the draw. *Scratch*: the competitor leaves
  the bracket; every remaining match of theirs is a forfeit, and at round one
  their opponent gets a bye. A withdrawn registration arriving as
  `registration.withdrawn` is a scratch. None of this touches the published
  version.
- **Undo** the last decided match only, by `event.manage`.
- **The bracket feeds the scoring screen; it does not replace it.**
  Completion is `DivisionResults::record()` with places pre-filled 1st to
  4th from the bracket; the scorer may number further down and saves. That
  keeps Zulip, the results page, the awards sheet and the participation
  trophies exactly as they are.
- **Versions.** A republish that changes a division's membership: a draft
  bracket regenerates; a running one is refused with the division names
  until the request carries the same explicit discard scoring uses, reusing
  `DivisionResults::affectedBy()`.

### Placement rules

The constraints are about where people sit in the draw, not only round one.
`BracketBuilder` scores a placement and hill-climbs by swapping slots, with a
fixed random seed per division so regenerating is reproducible:

| rule | source | how it scores |
| --- | --- | --- |
| no family against each other | `guardianships`: two registrants sharing a guardian (catches step-siblings) | heavy penalty for the same half; heavier for the same quarter |
| match on size | `users.height`, `users.weight` (every Saturday registrant had both; self-reported, so **shown**, not trusted) | penalty proportional to the normalised height+weight gap in each round-one pair |
| different schools | `users.school_id` | penalty per round-one pair from the same school; light penalty for the same quarter |
| byes | — | prefer giving a bye to whoever would otherwise take the worst size mismatch |

Divisions are 16 or smaller, so a few thousand swap evaluations is instant.
The head table overrides anything: **tap-A-then-tap-B swap** is the primary
control (drag on a phone in a gym is unreliable); drag via Sortable where it
works. Overrides are open while the bracket is a draft and closed once a match
is decided, except scratch and undo.

A client-side stopwatch on the ring page is fine and stores nothing.

Done when: last Saturday's sparring divisions generate brackets that respect
the rules on inspection, a bracket runs through to the awaiting-finals save
and the completing save, and the results page and Zulip look the same as a
hand-scored division.

## Phase 4 — trophies and awards

The awards sheet (`App\Services\AwardsSheet`) already lists per person what
they won. The new pieces are the count table and the stations.

- **Count table** `/event/{slug}/trophies/table`: rows by belt colour, columns
  1st … `winning_places` and Participation. Winners from published results in
  both disciplines (a double winner counts twice, since trophies are by place);
  participation from `App\Services\ParticipationTrophies`. Belt colour is
  `ranks.color`, which the degrees of black share, so it groups as the trophy
  table wants. **Before the event** the same table is estimated from the published
  divisions (places per division = `min(size, winning_places)`), which is the
  order quantity.
- **Stations.** The head table chooses N stations and the belt colours each
  takes. A printable per-station roster lists each person and the awards to
  hand them. Handovers are recorded in `award_handovers (event_id,
  event_registration_id, kind, discipline, place, handed_at, by)`; the
  participation list keeps its own table and behaviour.
- Names go through `DivisionPlacement::displayName()`, as everything does.

Done when: Saturday's results produce the table and six station rosters, and
ticking a handover syncs to production as `award.handed`.

## The venue box

A small Linux x86 box (8 GB is plenty) running Docker Compose: an image built
from this repo (nginx + php-fpm, or FrankenPHP), MariaDB matching production,
and a scheduler container running `schedule:work`. No queue worker. Decided
2026-11-09 over a laptop so it can live in the event kit.

- **Network.** A travel router with its own SSID, DHCP and DNS. The box has a
  static lease. The router resolves the venue hostname (e.g.
  `ring.chungdo.org`) to the box. Devices use one URL all day whether the
  router's WAN is venue Wi-Fi, a phone hotspot, or nothing.
- **HTTPS.** The check-in camera needs it. A Let's Encrypt certificate for the
  venue hostname via the DNS challenge, issued before each event and copied to
  the box. **The box's clock must be right offline**, or every device rejects
  the certificate: a battery-backed RTC or an NTP sync before leaving home.
- **Power.** Router and box on a small UPS or USB battery. A flicker should
  not reload a bracket.
- **Paper stays as cold standby** for the first events: print the cards,
  keep them at the head table, hand them out only if the system is dead. The
  print routes work at the venue too.
- **Second box** as hot standby if the budget allows; see above.

## Permissions

| what | permission |
| --- | --- |
| ring page, performed tick, deciding matches, scoring | `event.recordResults` (`event.scorer` role) |
| assign/release rings, bracket override, undo, finals flag, provisional competitors | `event.manage` |
| pull, push, sync status, wipe, close venue mode | `event.manage` |
| the sync endpoints | `TOURNAMENT_SYNC_TOKEN`, not a user |

New permissions, if any, go in `PermissionSeeder` **and**
`portal.coordinator_permissions`, and need the seeder run wherever deployed.

## Easy to get wrong

- A venue box built from a copy of production's `.env` holds the **live**
  `main_school` Stripe key and the Zulip sync bot. Venue mode must refuse
  both in code; the test suite should prove it.
- Payloads by id instead of uuid. Ids differ between the installs the moment
  the venue creates anything.
- Journaling outside the transaction. A command for a change that rolled
  back, or a change with no command, both end as a silent divergence.
- Skipping a failed command. The next one may depend on it.
- Firing `DivisionCompleted` at the venue. Results events belong to
  production only; the venue's listener registration is off in venue mode.
- Letting a republish quietly regenerate a *running* bracket.
- Forgetting that `event_registrations.checkin` is a datetime, not a flag,
  and that the archive table carries it too.
- The clock. See HTTPS.

## Decided (2026-11-09)

- Venue box is the day's only writer for tournament tables; production for
  everything else; a command log in both directions; no event sourcing.
- Two-way user data with the venue winning and conflicts logged.
- No money at the venue; walk-ups are provisional competitors resolved on
  production afterwards.
- Bracket and ring history is pushed to production and kept.
- 3rd place is a match before the final; 4th is its loser.
- Round robin for three; ties decided by hand.
- Forms uses the existing scoring screen; brackets are sparring only.
- Results, Zulip and the public page come from production only.
- A Linux box in the event kit, Docker Compose, travel router, pre-issued
  certificate.
- All four phases, in order, each committed as it lands.

## Open

- Are forms and sparring trophies physically different? If so the count
  table needs a discipline split; the design assumes not.
- Is the per-competitor "performed" tick worth the tapping, or is release at
  the head table enough? Build it; it is small.
- Does anyone get a 4th-place trophy? `winning_places` is 3 today, so no.
- Station count and whether a photo station needs anything from the app.
