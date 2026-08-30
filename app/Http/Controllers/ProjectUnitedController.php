<?php

namespace App\Http\Controllers;

use App\Exports\ProjectUnited\FinalExport;
use App\Models\School;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reporting for the retired Project United campaign (donations, t-shirts,
 * hoodies). The purchase flow was removed in 2026 — see
 * docs/project-united-retirement.md. These reports stay because
 * project_united_transactions are financial records.
 *
 * Both routes are admin-gated in routes/web.php.
 */
class ProjectUnitedController extends Controller
{
    public function report(Request $request)
    {
        $now = (new \Carbon\Carbon())->format('l, F j, Y \a\t g:ia');

        $date = new Carbon('2026-01-12');

        $trans = \App\Models\ProjectUnitedTransaction::with('user.school')
            ->where('created_at', '>=', $date)
            ->get();

        $sizes = [
            "adult_xs" => 0,
            "adult_s" => 0,
            "adult_m" => 0,
            "adult_l" => 0,
            "adult_xl" => 0,
            "adult_2xl" => 0,
            "adult_3xl" => 0,
            "adult_4xl" => 0,
            "adult_5xl" => 0,
            "kids_s" => 0,
            "kids_m" => 0,
            "kids_l" => 0,
            "kids_xl" => 0
        ];

        $sizes_hoodie = [
            "adult_xs" => 0,
            "adult_s" => 0,
            "adult_m" => 0,
            "adult_l" => 0,
            "adult_xl" => 0,
            "adult_2xl" => 0,
            "adult_3xl" => 0,
            "adult_4xl" => 0,
            "adult_5xl" => 0,
            "kids_s" => 0,
            "kids_m" => 0,
            "kids_l" => 0,
            "kids_xl" => 0
        ];
        $total = 0;
        $donation_total = 0;
        $tshirt_total = 0;
        $hoodie_total = 0;
        $hoodie_count = 0;

        foreach ($trans as $t) {
            if ($t->trans_type == "project_united_2026_tshirt") {
                $tshirt_total += $t->amount;
                if(is_string($t->metadata->raw_items)) {
                    $t->metadata->raw_items = json_decode($t->metadata->raw_items, true);
                }
                if ($t->metadata) {
                    foreach ($t->metadata->raw_items as $item) {
                        $sizes[$item["size"]] += $item["quantity"];
                        $total += $item["quantity"];
                    }
                }
            }

            if ($t->trans_type == "project_united_2026_hoodie") {
                $hoodie_total += $t->amount;
                if(is_string($t->metadata->raw_items)) {
                    $t->metadata->raw_items = json_decode($t->metadata->raw_items, true);
                }
                if ($t->metadata) {
                    foreach ($t->metadata->raw_items as $item) {
                        $sizes_hoodie[$item["size"]] += $item["quantity"];
                        $hoodie_count += $item["quantity"];
                    }
                }
            }

            if ($t->trans_type == "project_united_donation") {
                $donation_total += $t->amount;
            }
        }
        $grand_total = $tshirt_total + $donation_total + $hoodie_total;

        $size_labels = [
            'adult_xs' => 'Adult XS',
            'adult_s' => 'Adult S',
            'adult_m' => 'Adult M',
            'adult_l' => 'Adult L',
            'adult_xl' => 'Adult XL',
            'adult_2xl' => 'Adult 2XL',
            'adult_3xl' => 'Adult 3XL',
            'adult_4xl' => 'Adult 4XL',
            'adult_5xl' => 'Adult 5XL',
            'kids_s' => 'Kids S',
            'kids_m' => 'Kids M',
            'kids_l' => 'Kids L',
            'kids_xl' => 'Kids XL'
        ];

        $all_schools = [];
        $schools = School::all();
        foreach($schools as $school) {
            $all_schools[$school->id] = $school->name;
        }
        $round = 3;

        return Inertia::render('General/ProjectUnited/Report', compact('now', 'donation_total', 'hoodie_total', 'sizes', 'total', 'trans', 'size_labels', 'all_schools', 'grand_total', 'tshirt_total', 'sizes_hoodie', 'round'));
    }

    public function finalReport(Request $request)
    {

        $tshirt_recs = false;
        $hoodie_recs = false;



        $now = (new \Carbon\Carbon())->format('l, F j, Y \a\t g:ia');

        $transactions = \App\Models\ProjectUnitedTransaction::whereIn(
            "trans_type",
            ["project_united_2026_tshirt","project_united_2026_hoodie"]
        )
            ->where('id', '>', 204)
            ->get();

        $to_school = [];


        foreach ($transactions as $trans) {
            if ($trans->user) {
                $email = $trans->user->email;
            } else {
                $email = $trans->email;
            }

            if (isset($trans->metadata->school_id)){
                $school_id = $trans->metadata->school_id;
                if (!array_key_exists($school_id, $to_school)) {
                    $to_school[$school_id] = [
                        'project_united_tshirt' => [],
                        'project_united_hoodie' => []
                    ];
                }
                if (!array_key_exists($email, $to_school[$school_id][$trans->trans_type])) {
                    $to_school[$school_id][$trans->trans_type][$email]["items"] = [];
                }
                if($trans->user) {
                    $to_school[$school_id][$trans->trans_type][$email]["person_name"] = $trans->user->full_name;
                } else {
                    $to_school[$school_id][$trans->trans_type][$email]["person_name"] = $trans->metadata->email;
                }


                $to_school[$school_id][$trans->trans_type][$email]["stripe_id"] = $trans->stripe_id;
                $to_school[$school_id][$trans->trans_type][$email]["trans_type"] = $trans->trans_type;

                $to_school[$school_id][$trans->trans_type][$email]["items"] = array_merge(
                    $to_school[$school_id][$trans->trans_type][$email]["items"],
                    $trans->metadata->raw_items
                );
            } else {

                dd("no school id for ".$trans->id);
            }
        }

        $to_school_recs = [];


        foreach ($to_school as $schoolid => $items) {
            $school = School::find($schoolid);
            foreach ($items as $ttype => $orders) {
                foreach($orders as $email=>$data) {
                    $cur_total = 0;
                    $rec = [
                        'type' => $data['trans_type'],
                        'school' => $school->name,
                        'person' => $data['person_name'],
                        "adult_xs" => 0,
                        "adult_s" => 0,
                        "adult_m" => 0,
                        "adult_l" => 0,
                        "adult_xl" => 0,
                        "adult_2xl" => 0,
                        "adult_3xl" => 0,
                        "adult_4xl" => 0,
                        "adult_5xl" => 0,
                        "kids_s" => 0,
                        "kids_m" => 0,
                        "kids_l" => 0,
                        "kids_xl" => 0,
                        "total" => 0
                    ];
                    foreach ($data["items"] as $item) {
                        $rec[$item["size"]] += $item["quantity"];
                        $cur_total += $item["quantity"];
                    }
                    $rec["total"] = $cur_total;
                    $to_school_recs[] = $rec;
                }
            }

        }


        return Excel::download(new FinalExport($to_school_recs), 'project_united_final_report_rd2.xlsx');



    }
}
