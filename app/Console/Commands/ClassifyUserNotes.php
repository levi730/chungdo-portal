<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\UserNote;
use Illuminate\Console\Command;

/**
 * Walks the notes written before temporary/permanent existed and asks which
 * each one is.
 *
 * The rename migration made every existing note permanent, because that keeps
 * them all visible. That is right for "hard of hearing" and wrong for "cannot
 * stay for finals" — this is how the second kind gets demoted and attached to
 * the event it was written for, which the old form never recorded.
 *
 * Run it with --dry-run first to read the list without changing anything.
 */
class ClassifyUserNotes extends Command
{
    protected $signature = 'notes:classify
                            {--dry-run : List the notes and their candidate events, change nothing}
                            {--all : Revisit every note, not just the ones written before the split}';

    protected $description = 'Sort existing member notes into permanent and temporary, attaching temporary ones to an event';

    public function handle(): int
    {
        $notes = UserNote::with(['user', 'event'])
            ->when(! $this->option('all'), fn ($q) => $q->whereNull('event_id')->permanent())
            ->orderBy('created_at')
            ->get();

        if ($notes->isEmpty()) {
            $this->info('Nothing to classify.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $changed = 0;

        foreach ($notes as $note) {
            $this->newLine();
            $this->line(sprintf(
                '<comment>#%d</comment>  %s  <info>%s</info>',
                $note->id,
                $note->created_at->format('Y-m-d'),
                $note->user?->fullname ?? 'unknown member'
            ));
            $this->line('  '.trim((string) $note->note));

            $candidates = $this->candidateEvents($note);

            if ($dryRun) {
                $this->line('  <fg=gray>nearest events: '.($candidates->isEmpty()
                    ? 'none'
                    : $candidates->map(fn (Event $e) => "[{$e->id}] {$e->name} ".$e->startdatetime?->format('Y-m-d'))->implode(' · ')
                ).'</>');

                continue;
            }

            $choice = $this->choice('  Permanent or temporary?', ['permanent', 'temporary', 'skip'], 'permanent');

            if ($choice === 'skip') {
                continue;
            }

            if ($choice === 'permanent') {
                $note->update(['scope' => UserNote::SCOPE_PERMANENT]);
                $changed++;

                continue;
            }

            $eventId = $this->askForEvent($candidates);

            if ($eventId === null) {
                $this->warn('  No event chosen — left as it was. A temporary note without an event would never be shown.');

                continue;
            }

            $note->update(['scope' => UserNote::SCOPE_TEMPORARY, 'event_id' => $eventId]);
            $changed++;
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry run — nothing changed.' : "Updated {$changed} note(s).");

        return self::SUCCESS;
    }

    /**
     * The events a note written on a given day most likely belongs to, nearest
     * start date first. A note is almost always entered in the run-up to the
     * event it is about, so this is a shortlist, not an answer.
     *
     * @return \Illuminate\Support\Collection<int, Event>
     */
    private function candidateEvents(UserNote $note)
    {
        return Event::whereNotNull('startdatetime')
            ->get()
            ->sortBy(fn (Event $e) => abs($e->startdatetime->diffInSeconds($note->created_at)))
            ->take(5)
            ->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Event>  $candidates
     */
    private function askForEvent($candidates): ?int
    {
        if ($candidates->isNotEmpty()) {
            $this->line('  Nearest events:');
            foreach ($candidates as $e) {
                $this->line(sprintf('    [%d] %s (%s)', $e->id, $e->name, $e->startdatetime->format('Y-m-d')));
            }
        }

        $answer = trim((string) $this->ask('  Event id (blank to leave this note alone)'));

        if ($answer === '') {
            return null;
        }

        if (! Event::whereKey($answer)->exists()) {
            $this->error("  No event with id {$answer}.");

            return $this->askForEvent($candidates);
        }

        return (int) $answer;
    }
}
