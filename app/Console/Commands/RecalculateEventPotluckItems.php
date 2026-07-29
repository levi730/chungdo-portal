<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\PotluckOptions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateEventPotluckItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'event:potluck-items:recalculate
                            {event_id : id of event in question}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate the current count of event potluck items';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): void
    {

        // Make $args (if any) available to the file being included
        $event = Event::find($this->argument('event_id'));

        $registrations = $event->registrations;
        $answers = \App\Models\EventRegistrationAddon::whereIn('event_registration_id', $registrations->pluck('pivot.id')->filter())
            ->get()->groupBy('event_registration_id');
        foreach ($registrations as $r) {
            $r->pivot->setRelation('addonAnswers', $answers->get($r->pivot->id, collect()));
        }

        $map = [];
        foreach($registrations as $r) {
            $itemId = $r->pivot->potluckItemId();
            if($itemId) {
                $map[$itemId] = ($map[$itemId] ?? 0) + 1;
            }
        }

        DB::table('potluck_options')->where('event_id', $event->id)->update(['current_count'=>0]);

        foreach($map as $id=>$count) {
            $p = PotluckOptions::find($id);
            $p->current_count = $count;
            $p->save();
            echo $p->category . "|" . $p->item . ": " . $count . "\n";
        }

        echo "Done.\n";

    }
}
