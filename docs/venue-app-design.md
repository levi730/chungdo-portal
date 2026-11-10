# The venue app

A separate, small application that runs a tournament at the venue. It loads
one event from a pack file in the morning, runs the whole day on its own —
check-in, the head table and rings, sparring brackets, scoring, trophies —
posts results to the portal whenever it can see it, and exports one file at
the end for the portal to keep as the permanent record.

This replaces the **venue half** of Phase 2 in `docs/tournament-day-design.md`
(the portal running in `TOURNAMENT_VENUE` mode with its kill switches) and
keeps the **portal half** (the pack, the command log, the sync endpoints,
`CommandApplier`, provisional-competitor resolution, closing the event). It
also takes over Phases 3 and 4: brackets and trophies are built in the app,
not the portal, because they only happen at a venue. Phase 1's ring pages in
the portal become a prototype the app supersedes.

Written 2026-11-10 as the handoff for the build. Nothing in it exists yet. The
reasoning behind choosing this over the venue box and over a paper-only
backup is in the memory note `tournament-offline-alternatives`; the short
version: the venue box shares code with the portal, so every future portal
feature has to be checked for what it does on a box holding a live Stripe key
and the Zulip bot, and the user model journals every save across the whole
portal for the sake of one day a year. The app has none of that by
construction, and its scope is bounded: what happens between the morning
pack and the evening file.

## Goals and non-goals

Goals:

- **Runs with no internet at all**, from the first check-in to the last
  trophy, on a box in the event kit with phones and tablets on a travel
  router.
- **One way to run a tournament.** The app is used at every tournament,
  online or not. There is no "which system are we on today".
- **The portal stays the record.** Results, the public results page, Zulip,
  ring and bracket history, trophies handed, walk-ups — everything the app
  does reaches the portal, live when it can, from the file when it can't.
- **No money at the venue, ever.** Nothing about payments travels either way.
- **Nothing on the box after the day.** Names, birth dates and guardians are
  wiped once the portal has the file.

Non-goals:

- Registration, payment, membership, mailing, Zulip. The portal's.
- Forms scoring with anything but the existing place-entry screen. Brackets
  are sparring only.
- Automatic reading of paper. The paper packet (below) is re-keyed.
- Replacing the portal's own check-in for non-tournament events.

## Architecture: two apps, one contract

```
portal (chungdo.org)                        venue app (ring.chungdo.org)
────────────────────                        ────────────────────────────
registrations, divisions board     pack ──▶ load in the morning
members, schools, ranks                     check-in, walk-ups
                                            head table, rings
sync endpoints  ◀── commands (live) ──      brackets, matches
CommandApplier  ◀── outbox file ──────      scoring, results
results page, Zulip                         trophies, awards sheet
provisional resolution, corrections         export, wipe
```

**The contract is the shared thing, not code.** The two apps are separate
repositories with separate schemas. What they agree on is:

1. **The pack**: one JSON file describing one event, produced by the portal.
2. **The command log**: the envelope and payload catalogue Phase 2 defined,
   with the bracket and match types added.
3. **The endpoints**: `GET /api/tournament/{event}/pack`, `GET` and `POST
   /api/tournament/{event}/commands`, behind `TOURNAMENT_SYNC_TOKEN`. Already
   built on the portal; the app is their client.
4. **The outbox file**: the same command array the `POST` would carry,
   written to a file, uploaded on the portal's sync page.

Both repositories carry the same fixture pack and the same fixture outbox
under `tests/fixtures/`, and each has a test that reads them. Changing the
contract means changing both fixtures in the same week. The pack has a
`format` field (the portal's `Pack::FORMAT`); the app refuses a format it
does not know, and the portal rejects commands whose `format` it does not
know.

**The portal is the only writer for everything that is not the day**, and the
app is the only writer for the day's tables of its one event while the event
is at the venue — the same split Phase 2 made, with the portal's pages for
that event read-only behind the banner until the event is closed on the
portal's sync page.

**Results notifications are not a separate API.** A `division.saved` command
applied on the portal goes through `DivisionResults::record()` and fires
`DivisionCompleted`, which is what posts to Zulip and puts the division on
the public page. One channel, one format, whether the command arrived over
HTTPS during the day or from the file that night.

## The app

**Stack.** Laravel 12, Livewire, Tabler — the portal's own stack, so screens
look and behave the same and the people who maintain the portal can maintain
this. SQLite in WAL mode with a busy timeout; no MariaDB. One event at a time,
one database file. Packaged as a Docker image built on FrankenPHP: one
container for the web server and one for `schedule:work`, the database file on
a volume. (A static FrankenPHP binary is the later refinement if Docker on the
box proves a nuisance.)

**No accounts.** The app has no users table in the login sense. Each device
opens a **station**: Door, Head table, Ring *n*, Trophy table. A station is
unlocked with that station's PIN, set on the Load screen and printed on the
day's packet, and holds for the day in the device's session. On opening a
station the person picks their name from the pack's **staff list** (names and
uuids; no password hashes travel). That name is what the app journals as `by`,
and the portal's handlers resolve it to the user it already has. Nobody types
a password at a mat.

| station | can |
| --- | --- |
| Door | check in and clear check-in, enter a walk-up, open any ring page read-only |
| Head table | everything: assign and release rings, edit the board, generate and override brackets, undo a match, flag finals, scoring, trophies, sync, export, wipe |
| Ring | its own ring: decide matches, scratch, save places; read-only elsewhere |
| Trophy table | the count table and station rosters, tick handovers |

**Schema.** The app's own, named by uuid where the contract names things, with
local autoincrement ids for joins. Nothing here is the portal's table shape;
it is what the day needs.

```
event            slug, name, date, winning_places, portal_sequence, loaded_at, locked_at
schools          id (portal's), name
ranks            id (portal's), name, color, sort
people           uuid, firstname, lastname, dob, sex, height, weight, rank_id, school_id,
                 provisional (bool), provisional_guardian, notes (text, as the cards print them)
guardianships    guardian_uuid, dependent_uuid
registrations    uuid, person_uuid, participation ('forms'|'sparring'|'both'), checkin (datetime),
                 provisional (bool), withdrawn_at
staff            uuid, name, roles (json)
versions         uuid, discipline, published_at, data (json: [{label, members: [registration uuid]}])
rings            uuid, number, name
assignments      uuid, ring_id, version_uuid, position, assigned_at, released_at, finished_at, by_uuid
results          uuid, version_uuid, position, discipline, name, completed_at, awaiting_finals_at, by_uuid
placements       result_id, registration_uuid, place (nullable: a finalist), name, school, rank
brackets         uuid, version_uuid, position, format, finals, status, seed, generated_at
matches          uuid, bracket_id, round, order, label, a_uuid, b_uuid, feeds_match_id, feeds_slot,
                 winner_uuid, outcome, is_third_place, is_final, decided_at, by_uuid
awards           uuid, registration_uuid, kind ('participation'|'place'), discipline, place, handed_at, by_uuid
stations         name, pin_hash
outbox           uuid, sequence (gapless), type, payload, created_at, acknowledged_at
inbox            uuid, sequence, type, payload, received_at, applied_at, failed_at, error, skipped_at
conflicts        inbox_id, model, model_uuid, field, expected, found, incoming
```

`versions.data` keeps the division's members as registration uuids on disk,
not ids: the app never has to map them, and a `divisions.published` payload is
the row as it is.

**Journal and outbox.** Every write that the portal must learn about is made
by a service method that writes the row and the `outbox` command in one
transaction, exactly as Phase 2's `Journal::record()` does, and throws outside
one. The app has no "export only some types" switch: it journals everything in
the catalogue and the outbox is the whole day.

**Inbox.** The portal's commands for the event (late registrations,
withdrawals, member edits) arrive by `GET …/commands?after=` and are applied
in sequence, each in its own transaction, through the same services. A
failure stops the inbox until the head table retries or skips it. User-field
conflicts are logged and the app's value stands, which is Phase 2's rule
("the venue measured the child that morning").

**Online and offline are not modes.** The scheduler pushes the outbox and
pulls the inbox every 30 seconds. When the portal is unreachable both fail
quietly and the next run tries again. Every screen shows one line: *portal:
synced 14:02 · 6 waiting* or *portal: unreachable since 13:40 · 41 waiting*.
Nothing a station does ever waits on the portal.

## The pack (format 2)

Produced by the portal: `tournament:export {event}` to a file, or `GET
/api/tournament/{event}/pack`. Fetching or exporting with `--at-venue` marks
the event *at the venue* on the portal, as today. Format 2 is a **slimmer
pack than format 1**, built for an app that has no accounts and no Spatie
tables:

In it:

- the event: slug, name, date, `winning_places`, the portal's command
  watermark (`portal_sequence`) and the venue sequence to continue from if
  this is a re-pull;
- every school and rank, with the portal's ids (the one place ids cross:
  both are created on the portal only, as Phase 2 decided);
- the registrants and their guardians: the eight fields in
  `Payloads::USER_FIELDS` plus the notes the registration cards print
  (permanent notes and this event's temporary ones, through
  `UserNote::visibleForEvent()`), by uuid;
- the registrations: uuid, person, participation, check-in state,
  provisional flag;
- both published versions with members as registration uuids;
- rings, assignments, results, placements and trophies the portal already
  holds for the event (a re-pull after a half day, or a two-day event);
- the staff list: uuid and name of everyone holding `event.checkIn`,
  `event.recordResults` or `event.manage`, with which of those they hold.

Not in it: passwords, two-factor secrets, emails, phones, roles, permissions,
committees, add-on settings, anything about money, and nobody not connected to
the event. A guardian who is not a registrant travels as a name and uuid only,
for the sibling rule.

The pack has `format: 2`, the event slug and a `generated_at`. Loading a pack
**empties the app's event tables first**; it refuses once the app has journaled
anything for that event (`event.locked_at`), and then only the inbox flows,
which is Phase 2's lock rule. A re-pull before the day starts is always safe.

The portal keeps producing format 1 for as long as the venue-mode box exists;
`Pack::build($event, format: 2)` is the new path. Once venue mode is removed
from the portal, format 1 goes with it.

## The command log

The envelope is Phase 2's `SyncCommand::toWire()`: `uuid`, `origin`
(`venue`), `event` (slug), `sequence` (gapless per event), `type`, `payload`,
`created_at`. Payloads name people and registrations by uuid, versions and
results by uuid, ranks and schools by id, and carry `by` (a staff uuid) and
`at` wherever the portal's handlers read them. The catalogue is Phase 2's with
the bracket and match types filled in:

| type | direction | payload | on the portal |
| --- | --- | --- | --- |
| `checkin.recorded` / `checkin.cleared` | app → portal | registration, at, by | built |
| `user.created` | app → portal | the provisional person | built |
| `user.updated` | both | user, `{field: {from, to}}` | built; conflicts logged, app wins |
| `registration.created` | both | registration, user, participation, provisional, checkin, at | built |
| `registration.withdrawn` | portal → app | registration | built on the portal; the app scratches |
| `divisions.published` | app → portal | version uuid, discipline, divisions `[{label, members}]` | built |
| `division.saved` | app → portal | version, position, places `{registration: place}`, finalists, result uuid, at, by | built; fires `DivisionCompleted` |
| `division.reopened` | app → portal | result, by | built |
| `ring.assigned` / `ring.released` | app → portal | ring `{uuid, number, name}`, version, position, assignment uuid, at, by | built (history) |
| `bracket.generated` | app → portal | bracket uuid, version, position, format, finals, seed, matches `[{uuid, round, order, label, a, b, feeds, slot, third, final}]` | **new**: stored as history, read-only view later |
| `bracket.overridden` | app → portal | bracket, matches (the round-one slots after the swap), by | **new**: history |
| `match.decided` / `match.undone` | app → portal | match, winner, outcome, at, by | **new**: history |
| `award.handed` / `award.unhanded` | app → portal | registration, kind, discipline, place, award uuid, at, by | built for `participation`; `place` needs Phase 4's table |

The app sends `format: 2` with every batch; the portal's `receive()` rejects a
batch whose format it does not know rather than storing commands it cannot
apply.

**The outbox file** is `{format, event, exported_at, commands: [...]}`, the
same array the `POST` carries, written by *Export* on the app's sync screen.
The portal's sync page gains an **Upload outbox** control that hands the array
to `CommandApplier::receive()` and `applyPending()` exactly as the endpoint
does; duplicates by uuid are ignored, so a file uploaded after a partial
live sync is safe, and so is uploading it twice.

## Rules carried over

These are the portal's rules today and the app keeps them; the portal's
handlers assume them.

- **Busy and ready are derived, never stored.** A competitor is busy while a
  division of theirs has an open assignment; a division is ready when every
  member is checked in and none is busy. **No early release**: everyone stays
  at the ring until the division is saved, first-round losses and finished
  forms included. The head table may send a division that is not ready after
  a confirm naming who is missing.
- **One open division per ring and one ring per division.** Saving the
  division finishes its assignment. A division awaiting finals may be sent to
  a ring again in the evening.
- **A division is named by version uuid and position**, and a result or a
  bracket follows its division onto a newly published version by who is in
  it (`DivisionResults::identity()`'s rule: same members, same division). A
  republish that changes a division's membership clears that division's
  result, regenerates a draft bracket, and refuses to touch a running
  bracket until the request carries an explicit discard.
- **Scoring**: `place` can repeat and has no ceiling beyond the division's
  size; a finalist is a placement with a null place; "1st and 2nd at finals"
  saves two finalists plus 3rd and below and sets `awaiting_finals_at`; the
  completing save needs a 1st and is a new result post, not an edit. A saved
  division is closed; reopening is Head table only. Placements copy name,
  school and rank at save time.
- **Names.** The app shows full names everywhere, including provisional
  competitors, because the day needs them. The portal keeps hiding a
  provisional name until resolved (`DivisionPlacement::displayName()`).
- **Participation trophies**: 8 and under is Mini Pee Wee, 9 to 11 is Pee
  Wee, age from the birth date at the event's date; a win is a place from 1
  to `winning_places` in either discipline; a child reaches the list once
  checked in and with every division of theirs saved.

### Brackets (from Phase 3, unchanged)

Sparring only. A bracket belongs to a version and position like a result.

- **Format by size.** 3: round robin. 4 or more: single elimination to the
  next power of two, byes at the top, nobody gets two. 2: one match. 1: a
  walkover, 1st.
- **3rd place is a match, fought before the final.** Its loser is 4th. In a
  finals division the 3rd-place match still runs in the day.
- **Round robin ties are decided by hand.** Standings are shown; a 1-1-1 is
  flagged, and the places are entered on the scoring screen.
- **Finals** is set by the head table when assigning or generating. When
  the bracket reaches a withheld final it proposes the awaiting-finals save.
- **Outcomes.** Win; forfeit (the other advances, the draw is unchanged);
  scratch (the competitor leaves the bracket, every remaining match of theirs
  is a forfeit, a round-one opponent gets a bye). A `registration.withdrawn`
  from the inbox is a scratch.
- **Undo** the last decided match only, Head table.
- **The bracket feeds the scoring screen; it does not replace it.** Finishing
  a bracket opens the save with 1st to 4th pre-filled; the ring may number
  further and saves. That is what keeps results, Zulip and trophies identical
  to a hand-scored division.
- **Placement** hill-climbs slot swaps from a fixed seed per division: heavy
  penalty for two registrants sharing a guardian in the same half, heavier
  in the same quarter; penalty proportional to the height and weight gap in
  each round-one pair; penalty per round-one pair from the same school, light
  for the same quarter; byes to whoever would otherwise take the worst size
  mismatch. Sixteen or fewer per division, so it is instant.
- **Override** is tap-A-then-tap-B swap, with drag where it works, open while
  the bracket is a draft and closed once a match is decided, except scratch
  and undo.

### Trophies (from Phase 4, unchanged)

- **Count table**: rows by belt colour (`ranks.color`; the degrees of black
  share one), columns 1st … `winning_places` and Participation, from saved
  results in both disciplines, a double winner counting twice. Before the
  event the same table is estimated from the published divisions, which is
  the order quantity — that estimate stays a **portal** page, since it is
  needed weeks before.
- **Stations**: the head table chooses N and the colours each takes; a
  printable per-station roster lists each person and what to hand them;
  ticking a handover journals `award.handed`.
- The awards sheet, as the portal has it: 1st per forms division, sparring
  places to `winning_places`, the participation list, stamped with the time.

## Screens

- **Load.** Pick the pack file (or fetch it, with the portal URL and token),
  set the four station PINs, see the event's counts. Re-load is offered until
  the first journaled change.
- **Door.** The check-in list: search, the camera QR scanner (the portal's
  cards carry the registration's code; the app scans the same code), the
  hardware-scanner path, Save & Check In on a details page that shows the
  person's notes. A **Walk-up** form: name, school, rank, birth date, sex,
  height, weight, guardian name, participation, checked in on the spot,
  journaled as `user.created` and `registration.created`, provisional.
  *Arrived, not placed* lists late registrations from the inbox and walk-ups
  until the head table puts them in a division.
- **Head table.** Rings across the top, each with its division, how long it
  has been out, and ready/busy; divisions down the side in board order, with
  readiness, who is missing or where they are, and Assign. A **Board** tab
  edits the divisions of a discipline (move, merge, rename, place a late
  arrival) and publishes a new version, journaled, with the same discard
  confirmation scoring and brackets use. Finals flag per division.
- **Ring *n*.** The division out at this ring: the roster with check-in state,
  the bracket (sparring) with Win / Forfeit / Scratch on the current match
  and a stopwatch that stores nothing, or the scoring entry (forms), and Save,
  which closes the division and frees the ring. A ring page polls every 10
  seconds; the head table every 5.
- **Trophy table.** The count table, the station rosters, the participation
  list, each with tick boxes that journal.
- **Awards sheet.** Printable.
- **Sync.** Portal reachability, outbox and inbox counts, the last contact,
  failures with Retry and Skip, **Export outbox file**, **Export archive**
  (every table, for the kit's USB stick), and **Wipe**, which asks twice.
- **Print.** The paper packet: head-table ledger, one ring sheet per division
  per discipline (roster on the front; the bracket drawn for its size, places
  box, finalist lines and finals tick on the back; a QR naming the version,
  position and discipline), check-in rosters per school, blank walk-up forms
  with the waiver line, the trophy tally, blank spares. Printed at home the
  night before. See "Paper" below.

Phones first: every control is a tap target, nothing needs hover, and the ring
page works one-handed.

## Paper

The box does not survive a power cut, and a tournament has to. The packet
above is printed at home from the same pack, and the rule is that **the ring
sheet travels with every division and the ring writes the places on it when
done, even on a good day**. The screens do the managing; the sheet is the
signed record and the fallback, and there is no switchover moment. When the
box is back, the head table re-keys the sheets: scanning a sheet's QR opens
that division's save screen (the portal's scoring page takes
`?version=&position=` already, and the app's does the same). A photo of each
sheet can be attached to its result on the portal afterwards; that is a
portal feature and not in this build.

## The day, step by step

**The week of.** Publish both disciplines on the portal and treat them as
final. Export the pack to the kit's stick and print the packet. Confirm the
box's clock, the router, the certificate.

**Morning.**

1. Power the router and box. A phone on the router's SSID opens
   `https://ring.chungdo.org`.
2. On Load, fetch the pack if the portal is reachable, else load the stick's
   copy. Set the PINs. The event is now at the venue on the portal, whose
   pages for it are read-only behind the banner.
3. Door opens check-in; the head table creates rings. The first journaled
   change locks the pack.

**During.** Every device talks only to the box. The scheduler pushes and pulls
every 30 seconds; results reach Zulip and the public page within a minute of
a save when the portal is reachable, and queue when it is not. Late
registrations made on the portal appear under *Arrived, not placed*. Walk-ups
are entered at the door. Evening finals run on the same box.

**After.**

1. If the outbox drained live, the portal's sync page shows zero pending and
   the same division count. If not, Export the outbox file to the stick and
   upload it on the portal's sync page at home. Either way the archive goes
   to the stick.
2. **Close the event** on the portal's sync page. The portal's own pages open
   for corrections and the provisional competitors are listed to merge or
   finalise, and their fee collected (the open item from Phase 2: a way to
   attach a remittance to an existing registration).
3. **Wipe** the box.

## The portal side

What changes on the portal, all on the Phase 2 branch, none of it on main
until the association approves:

1. **Pack format 2**, `Pack::build($event, format: 2)`, with its fixture and
   test. `tournament:export --format=2`. The endpoint takes `?format=2`.
2. **Handlers for `bracket.generated`, `bracket.overridden`, `match.decided`
   and `match.undone`** in `CommandApplier`, storing history: `brackets` and
   `bracket_matches` tables as Phase 3 sketched them, written by the handlers
   only, with a read-only bracket view on the event's results pages later.
   Until the view exists the tables are still worth keeping; the command rows
   alone are not a usable record.
3. **`award.handed` with `kind: place`**, and the `award_handovers` table
   from Phase 4.
4. **Upload outbox** on `/event/{slug}/sync`, feeding `receive()` and
   `applyPending()`; `format` checked in `receive()`.
5. **Resolving provisional competitors** stays as built. Collecting the fee
   still needs the remittance-to-existing-registration change.
6. **Later, in its own step:** remove venue mode from the portal — the kill
   switches, `VenueModeServiceProvider`, `RefuseAtVenue`, `tournament:pull`,
   `tournament:push`, `tournament:wipe`, the `laravel_venue` test database —
   and the User observer's journaling of every save, keeping only the
   portal-side journal of `user.updated`, `registration.created` and
   `registration.withdrawn` for an event at a venue. Not before the app has
   run a real tournament.
7. **Phase 1's head table and ring pages** stay as the read-only view of ring
   history the handlers already write; assigning from the portal is refused
   while the event is at the venue, as today, and has no reason to exist
   otherwise.

No new permission anywhere. The sync endpoints answer to the token; the
portal's pages keep `event.manage`.

## Build order

Each step is committed as it lands and has a "done when". The app is a new
repository, `chungdo-ring`, with its own DDEV project for development and a
Pest suite; the rehearsal is that DDEV project pointed at `chungdo.ddev.site`
on the Phase 2 branch, which is the two-project setup already worked out.

1. **Skeleton and the contract.** The schema, the pack importer with the
   fixture, the station PINs and staff picker, the journal and outbox, the
   inbox and applier for the portal → app types, `tournament:push` and
   `tournament:pull` against the real endpoints, the sync screen with export.
   *Done when* the fixture pack loads, a check-in journals, the outbox
   reaches a dev portal and applies, and the file export uploads to it with
   the same effect.
2. **Door.** Check-in with search, camera and scanner; walk-ups; *Arrived,
   not placed*. *Done when* Saturday's registrants check in by QR from a phone
   on the router and a walk-up reaches the portal as provisional.
3. **Head table and rings.** Rings, assign and release, busy and ready, the
   ring page with the roster, forms scoring and Save, the board editor with
   publish. *Done when* a forms division goes out, is scored at the ring, and
   the portal shows it on the results page and in Zulip.
4. **Brackets.** Generation with the placement rules, override, outcomes,
   undo, finals, the save pre-filled from the bracket, round robin. *Done
   when* Saturday's sparring divisions generate brackets that respect the
   rules on inspection, one runs to the awaiting-finals save and the
   completing save, and the portal's result is indistinguishable from a
   hand-scored one.
5. **Trophies and awards.** Count table, stations and rosters, participation
   list, awards sheet, `award.handed` both kinds. *Done when* Saturday's
   results produce the table and six rosters and a tick reaches the portal.
6. **Paper.** The packet PDF and the QR-opens-save path. *Done when* a
   division is re-keyed from its sheet in under a minute.
7. **Portal side** (items 1 to 5 above), interleaved where a step needs it:
   format 2 before step 1, the bracket handlers before step 4, the award
   table before step 5.
8. **Packaging and rehearsal.** The Docker image, the box, the router, the
   certificate, the archive to USB every minute by the scheduler, wipe. *Done
   when* the full day runs at home on the box from the stick with the
   router's WAN unplugged, and the portal has everything the next morning
   from the file.

## The box

As `docs/tournament-day-design.md` has it, minus MariaDB: a small Linux x86
box in the event kit, a travel router with its own SSID, DHCP and DNS
resolving `ring.chungdo.org` to the box's static lease, a Let's Encrypt
certificate for that name issued before each event by the DNS challenge (the
camera needs HTTPS), the clock right offline, router and box on a UPS. The
SQLite file makes the cheap standby real: the scheduler copies it to the
stick every minute with `sqlite3 .backup`, and a second box or a laptop with
the image becomes the venue by copying the file and changing the router's DNS
entry.

## Easy to get wrong

- **Journaling outside the transaction**, on either side. A command for a
  change that rolled back, or a change with no command, ends as silent
  divergence. The journal throws outside a transaction; keep it that way.
- **Naming by id.** Only ranks and schools cross by id. Everything else is
  uuid, and the app's ids are its own.
- **Skipping a failed command automatically.** The next may depend on it.
- **Letting the app post to Zulip** or anywhere. It has no credentials for
  anything but the portal's token, and that is the point: do not add any.
- **A republish quietly regenerating a running bracket.**
- **Sending `by` the portal cannot resolve.** `division.saved` on the portal
  requires a user; the staff picker exists so every `by` is a portal uuid.
- **Loading a pack over a locked event.** The importer refuses; do not add a
  force flag for convenience.
- **Forgetting the paper.** The packet is printed from the same pack; make
  it part of the export step, not an afterthought.
- **The clock.** A wrong clock rejects the certificate on every device.

## Decided (2026-11-10)

- A separate app, not the portal in a mode. The portal half of Phase 2 stays;
  the venue half is removed later.
- The contract is the pack, the command log and the endpoints Phase 2
  defined, with a slimmer pack format and the bracket and match types added.
- Stations and PINs instead of accounts; staff names travel for `by`.
- SQLite, FrankenPHP in Docker, the same travel router and certificate plan.
- Results reach the portal only as commands; there is no separate results API.
- Brackets and trophies are built in the app. The pre-event trophy estimate
  stays on the portal.
- The paper packet is part of the app, printed from the pack, and the ring
  sheet travels with every division.
- Everything from the earlier Decided list still stands: 3rd place before the
  final, round robin for three with ties by hand, forms on the scoring
  screen, no money at the venue, no early release, nothing on main until
  approved.

## Open

- The repository name and where the image is built (the box itself, or a
  laptop that pushes an image).
- Whether the app should also run the **pre-event** trophy estimate, so the
  portal page can go; the design keeps it on the portal.
- The Phase 2 open items unchanged: collecting a walk-up's fee afterwards;
  the waiver's wording on websites; 4th-place trophies; a photo station.
- Whether a second ring sheet is wanted per discipline for a competitor in
  both, or the roster simply lists both.
