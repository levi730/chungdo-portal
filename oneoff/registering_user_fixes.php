<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

$sql = 'SELECT MIN(user_id) as main_id, GROUP_CONCAT(user_id) as group_ids
FROM event_registrations
WHERE event_id = ?
GROUP BY created_at;';

$res = DB::select($sql, [6]);
foreach ($res as $rec) {
    $whole_group = \App\Models\EventRegistration::where('event_id', 6)->whereIn('user_id', explode(",", $rec->group_ids))
        ->update(['registering_user_id' => $rec->main_id]);

    $sql = "UPDATE event_registrations SET potluck_item_id = null WHERE event_id = 6 AND user_id IN (" . $rec->group_ids . ") AND user_id <> registering_user_id";
    DB::statement($sql);
}
echo "Done.\n\n";
