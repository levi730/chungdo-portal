<?php

namespace App\Http\Controllers;

use App\Exports\ProjectUnited\FinalExport;
use App\Models\School;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Maatwebsite\Excel\Facades\Excel;

class ProjectUnitedController extends Controller
{
    public function index(Request $request)
    {
        $now = new \Carbon\Carbon();
        $cutoff = new \Carbon\Carbon('2026-02-20 12:00:00');
        $tshirts_available = !($now > $cutoff);
        $hoodies_available = !($now > $cutoff);
        $def_school = null;
        $all_schools = School::all();

        $logged_in_user = auth()->check();
        if ($logged_in_user) {
            $user = auth()->user();
            if($user->school) {
                $def_school = $user->school->id;
            }
        }

        return Inertia::render('General/ProjectUnited/Index', compact('tshirts_available', 'def_school', 'logged_in_user', 'all_schools', 'hoodies_available'));
    }

    public function processDonation(Request $request)
    {
        $amount = $request->input('amount');

        Stripe::setApiKey(config('services.stripe.secret'));

        if(auth()->check()) {
            $email = auth()->user()->email;
        } else {
            $email = $request->input('email');
        }

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => $amount * 100,
                    'product_data' => [
                        'name' => 'One-Time Donation: Project United',
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'customer_email' => $email,
            'metadata' => [
                'user_id' => auth()->user()->id ?? null,
                'trans_type' => 'project_united_donation',
                'email' => $email
            ],
            'success_url' => url('/project-united/donate/success'),
            'cancel_url' => url('/project-united/donate/cancel'),

        ]);

        return response()->json(['url' => $session->url]);
    }

    public function donationSuccess(Request $request)
    {
        //Successful donation, record stuff here
        return Inertia::render('General/ProjectUnited/DonationSuccess');
    }

    public function donationCancel(Request $request)
    {
        return Inertia::render('General/ProjectUnited/DonationCancel');
    }

    public function processTshirt(Request $request)
    {
        $items = $request->input('items', []);
        $lineItems = [];

        foreach ($items as $item) {
            $size = $item['size'];
            $quantity = $item['quantity'];

            $label = strtoupper(str_replace('_', ' ', $size)); // Optional
            $basePrice = 25;
            $upchargeMap = [
                'adult_2xl' => 2,
                'adult_3xl' => 3,
                'adult_4xl' => 4,
            ];

            $upcharge = $upchargeMap[$size] ?? 0;
            $unitAmount = ($basePrice + $upcharge) * 100;

            $lineItems[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => $unitAmount,
                    'product_data' => [
                        'name' => "2026 T-Shirt ($label)",
                    ],
                ],
                'quantity' => $quantity,
            ];
        }

        Stripe::setApiKey(config('services.stripe.secret'));

        if(auth()->check()) {
            $email = auth()->user()->email;
        } else {
            $email = $request->input('email');
        }

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => url('/project-united/tshirt/success?session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => url('/project-united/tshirt/cancel'),
            'customer_email' => $email,
            'metadata' => [
                'trans_type' => 'project_united_2026_tshirt',
                'raw_items' => json_encode($items),
                'user_id' => optional($request->user())->id,
                'email' => $email,
                'mailing_address' => json_encode($request->input('mailing_address')),
                'school_id' => $request->input('school_id'),
            ],
        ]);


        return response()->json(['url' => $session->url]);
    }


    public function processHoodie(Request $request)
    {
        $items = $request->input('items', []);
        $lineItems = [];

        $basePriceAdult = 55;
        $basePriceYouth = 42;
        $upchargeMap = [
            'adult_2xl' => 2,
            'adult_3xl' => 3,
            'adult_4xl' => 4,
        ];

        foreach ($items as $item) {
            $size = $item['size'];
            $quantity = $item['quantity'];

            $label = strtoupper(str_replace('_', ' ', $size)); // Optional

            if (str_starts_with($size, 'adult_')) {
                $basePrice = $basePriceAdult;
            } else {
                $basePrice = $basePriceYouth;
            }


            $upcharge = $upchargeMap[$size] ?? 0;
            $unitAmount = ($basePrice + $upcharge) * 100;

            $lineItems[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => $unitAmount,
                    'product_data' => [
                        'name' => "2026 Hoodie ($label)",
                    ],
                ],
                'quantity' => $quantity,
            ];
        }

        Stripe::setApiKey(config('services.stripe.secret'));

        if(auth()->check()) {
            $email = auth()->user()->email;
        } else {
            $email = $request->input('email');
        }

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => url('/project-united/tshirt/success?session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => url('/project-united/tshirt/cancel'),
            'customer_email' => $email,
            'metadata' => [
                'trans_type' => 'project_united_2026_hoodie',
                'raw_items' => json_encode($items),
                'user_id' => optional($request->user())->id,
                'email' => $email,
                'mailing_address' => json_encode($request->input('mailing_address')),
                'school_id' => $request->input('school_id'),
            ],
        ]);


        return response()->json(['url' => $session->url]);
    }


    public function tshirtSuccess(Request $request)
    {
        //Successful donation, record stuff here
        return Inertia::render('General/ProjectUnited/TshirtSuccess');
    }

    public function tshirtCancel(Request $request)
    {
        return Inertia::render('General/ProjectUnited/TshirtCancel');
    }

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

    /*private function getNameFromStripeCS($stripeid) {
        $stripe = new \Stripe\StripeClient(config("services.stripe.secret"));
        $session = $stripe->checkout->sessions->retrieve($stripeid);
        $paymentIntentId = $session->payment_intent;
        $paymentIntent = $stripe->paymentIntents->retrieve($paymentIntentId);
        $paymentMethod = $stripe->paymentMethods->retrieve(
            $paymentIntent->payment_method
        );
        return $paymentMethod->billing_details->name;
    }*/

}
